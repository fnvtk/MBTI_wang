# 管理端「通用推送 Hook」扩展方案（设计说明）

> 状态：**已落地（含企业专属 + 全平台回落）**  
> 背景：当前仅支持**飞书自定义机器人**获客推送；需在**超管端**与**企业管理端**提供可配置的**通用 HTTP 推送 Hook**（可与飞书并存或分阶段替代），便于对接企业微信、钉钉、自建中间层、Zapier 等。

---

## 一、现状摘要

### 1.1 飞书获客推送（已实现）

| 项目 | 说明 |
|------|------|
| **后端服务** | `api/app/common/service/FeishuLeadWebhookService.php` |
| **配置键** | `system_config.key = feishu_lead_webhook` |
| **作用域** | 固定 `enterprise_id = 0`（**全局一条**，非按企业隔离） |
| **配置项** | `enabled`、`webhookUrl`（飞书机器人 URL）、`contactPerson`（卡片展示用） |
| **触发场景** | 支付成功（订单维度去重）、用户首次绑定手机号等；内部组文案后调用飞书 Bot API（`postWebhook`） |
| **去重** | 表 `delivery_dedup`（`scene` + `dedupKey` 联合唯一；飞书获客为 `scene=feishu_lead`，出站为 `scene=outbound_hook`） |

### 1.2 管理端 API

- **超管**：`superadmin/Settings` → `getFeishuLeadConfig` / `updateFeishuLeadConfig`（与下述共用同一配置行）。
- **企业管理员**：`admin/Settings` → `GET/PUT /api/v1/admin/settings/feishu-lead`  
  - 权限：`admin` / `enterprise_admin`  
  - **读写仍为 `enterprise_id=0`**，即**企业端与超管端改的是同一份全局飞书配置**。

### 1.3 前端

- 存在组件 `admin/src/views/admin/FeishuLeadConfigPanel.vue`（调用 `/admin/settings/feishu-lead`）。
- 需在**超管「系统设置」**与**企业「系统设置」**中明确挂载入口（若尚未挂载到路由 Tab，实现时需补全）。

### 1.4 本方案覆盖范围

1. **通用 HTTP JSON**：已实现 `OutboundPushHookService`，与飞书协议解耦。  
2. **多租户**：通用 Hook 已支持 **`enterprise_id` 分行 + 全平台回落**；飞书仍为全局一条（若未来要对飞书按企业隔离，需另起需求）。

---

## 二、目标能力（通用推送 Hook）

### 2.1 定义

在关键业务事件发生时，向管理员配置的 **HTTPS URL** 发送 **HTTP POST**，请求体为**统一 JSON**（与飞书格式解耦），接收方可为：

- 自建网关（再转发到飞书 / 企微 / 钉钉）；
- Serverless / 自动化平台；
- 企业内 CRM / 数据仓库。

### 2.2 建议支持的事件类型（与现有飞书触发对齐，可迭代）

| 事件编码 | 说明 | 备注 |
|----------|------|------|
| `lead.order_paid` | 订单支付成功 | 与 `onOrderPaid` 对齐，含订单号、金额、产品类型等 |
| `lead.phone_bound` | 用户首次绑定手机号 | 与 `onPhoneBound` 对齐 |
| **`test.result_completed`** | **用户完成测评且结果已落库** | 问卷提交（`/api/test/submit`）或带 token 的分析写库（如 `/api/analyze`）成功后；**每条测试记录仅推一次**（按 `testResultId` 去重） |
| （可选）`analytics.summary` | 周期性或关键行为汇总 | 二期 |

**`test.result_completed` 建议触发点（实现时择需挂载，避免重复推送）：**

- 问卷类：`POST /api/test/submit` 成功写入 `test_results` 后；  
- 人脸/AI 类：`Analyze` 等流程在**已写入 `mbti_test_results` / `test_results` 对应记录**且拿到主键 `id` 后；  
- 若同一链路既写库又触发支付，**支付成功**仍走 `lead.order_paid`，**测评完成**走 `test.result_completed`，二者语义分离（先完成测评、后付费的场景下可能先后各推一条）。

### 2.3 推送内容规范（参考：用户购买成功 · 实时推送）

业务侧希望**机器人/群消息**与 **HTTP JSON** 使用**同一套语义字段**，便于飞书、企微、通用 Hook 共用。下列对照参考常见「购买成功实时通知」样式（标题行 + 键值行）。

#### 2.3.1 事件 `lead.order_paid` — 字段对照表（对齐当前项目）

| 展示文案（中文） | JSON 路径（建议） | 类型 | 说明 |
|------------------|-------------------|------|------|
| 标题行 | `payload.display.title` | string | 固定文案如：`用户购买成功（实时推送）`，前端可加前缀图标 `💰` |
| 订单号 | `payload.orderNo` | string | 与库表 **`orders.orderNo`** 一致，与小程序 `miniprogram/utils/payment.js` → **`generateOrderId(productType)`** 生成的商户单号相同；规则：**业务前缀 + `YYYYMMDDHHmmss` + 3 位随机**，总长 ≤32（与微信 `out_trade_no` 一致）。前缀示例：`FACE`（面相）、`MBTI`、`DISC`、`PDP`、`REPT`（完整报告）、`TEAM`（团队分析）、`RCG`（充值）、`DPER`/`DTEAM`（深度服务）、`VIP`、`TNUM` 等；未命中映射时用 `productType` 前 6 位大写（如 `SBTI`）。示例：`FACE20260414083927001` |
| 用户 | `payload.userName` | string | `wechat_users.nickname`，缺省可展示「微信用户」 |
| 手机 | `payload.phone` | string | 已绑定则展示，未绑定可为空字符串 |
| 商品 | `payload.productTitle` | string | 对应订单 **`productTitle`**，创建支付时来自前端 `description`；后端默认文案规则：`Payment::create` 在 `description` 为空时为 **`AI性格测试-{productType}`**（见 `api/app/controller/api/Payment.php`）。小程序侧示例：人脸完整报告为 `AI人脸性格分析完整报告`（`purchaseFaceTest`） |
| 金额 | `payload.amountYuan` | string | **元**，保留两位小数；库内 **`orders.amount` 为分**，展示时 `amountFen/100` |
| 状态 | `payload.status` | string | 与订单状态一致，支付成功推送场景一般为 `paid`（以实际 `orders.status` 落库值为准） |
| 支付时间 | `payload.paidAt` | string | `YYYY-MM-DD HH:mm:ss`（东八区），取支付成功写入时刻 |

补充（机器处理用，可选展示）：`payload.orderId`（`orders` 表主键）、`payload.userId`、`payload.amountFen`（分）、`payload.productType`。

**`productType`（当前项目常用值，与支付创建入参一致）**：`face`、`mbti`、`sbti`、`disc`、`pdp`、`resume`、`recharge`、`report`、`team_analysis`、`vip`、`test_count`、`single_test`、`deep_personal`、`deep_team` 等；以后端 `Payment` 与定价校验为准。

**`payload.sourceLabel`（推荐与飞书一致）**：推送文案中的「来源/业务说明」可与现有飞书 **`FeishuLeadWebhookService::sourceLabelForOrder()`** 使用同一套规则，例如：

- `recharge` → `企业余额·充值支付成功`  
- 其它类型：中文业务名 +（若有商品标题则带「标题」）+ `·支付成功`；无标题且金额恰为 1 元时可为 `xxx·1元支付·支付成功`  

这样 HTTP Hook、飞书机器人、后台列表语义一致。

#### 2.3.2 飞书 / 纯文本模板（与上表一致，便于复制实现）

单条消息可拼为**多行文本**（与飞书 `text` 内容一致）。以下为**与本项目订单号规则、商品描述习惯一致**的示例（金额 1 元场景，人脸完整报告）：

```text
💰 用户购买成功（实时推送）
订单号: FACE20260414083927001
用户: 微信用户
手机: 18302257611
商品: AI人脸性格分析完整报告
金额: 1.00
状态: paid
支付时间: 2026-04-14 08:39:27
来源: 面相测试·「AI人脸性格分析完整报告」·支付成功
```

说明：

- **订单号**：勿用与本项目无关的 `MP…` 示例；应使用 **`FACE`/`MBTI`/… + 时间戳 + 三位随机** 格式（见上表）。  
- **商品**：填真实落库的 `productTitle`/`description`，如 MBTI 单次可能为后台定价返回的标题，或前端传入的说明字符串。  
- **来源**：可选单独一行，文案与 **`sourceLabelForOrder`** 一致，便于与飞书侧「来源: xxx」对照；若接收方不需要可省略。  
- 手机号为空时，`手机:` 行可写「未绑定」或省略该行（产品二选一并写死）。  
- `商品` 过长时可截断（如最多 80 字 + `…`），与 `FeishuLeadWebhookService::oneLine` 对标题的截断策略可统一为 **40 字**（飞书来源行）或 **80 字**（商品列），实现时二选一并写死。

#### 2.3.3 事件 `lead.phone_bound`（首次绑定手机，可选另一套标题）

建议标题：`📋 新获客` 或 `用户完成手机号授权`，字段可含：`userName`、`phone`、`bindAt`、`sourceLabel`（与现有飞书获客文案对齐），具体 JSON 在实现时单独列 `payload` 子结构，避免与 `order_paid` 混用同一 schema。

#### 2.3.4 事件 `test.result_completed` — 字段对照表（用户测评结果 · 实时推送）

与「购买成功」并列：**结果落库后即推**，便于运营侧即时看到「谁做完了什么测评、结果是什么」，不依赖是否付费。

| 展示文案（中文） | JSON 路径（建议） | 类型 | 说明 |
|------------------|-------------------|------|------|
| 标题行 | `payload.display.title` | string | 如：`用户测评完成（实时推送）`，图标建议 `📊` |
| 记录 ID | `payload.testResultId` | int/string | 对应库表 `test_results` / `mbti_test_results` 主键 `id`，便于溯源与去重 |
| 用户 | `payload.userName` | string | 昵称 |
| 手机 | `payload.phone` | string | 已绑定则展示，未绑定可为空或「未绑定」 |
| 测评类型 | `payload.testType` | string | 与库一致：`mbti` / `sbti` / `disc` / `pdp` / `face` / `ai` / `resume` 等 |
| 测评类型（中文） | `payload.testTypeLabel` | string | 可选，便于直接展示，如 `MBTI 性格测试`、`面相分析` |
| 结果摘要 | `payload.resultSummary` | string | 一行可读摘要，与小程序/后台列表「结果」列一致（如 MBTI 四字母、SBTI 类型+中文、PDP/DISC 主类型等） |
| 完成时间 | `payload.completedAt` | string | `YYYY-MM-DD HH:mm:ss`，东八区，取记录 `createdAt` 或提交成功时刻 |
| 企业 | `tenant.enterpriseId` / `tenant.enterpriseName` | — | 与全局 `tenant` 一致；个人版无企业则为 `null` 或 `0` |

补充（可选、接收方高级用法）：`payload.resultMeta`（结构化片段，与接口 `resultMeta` 对齐）、`payload.enterpriseId`（行内冗余）、`payload.userId`。  
**隐私**：若结果含敏感长文本，默认只推 `resultSummary`；完整 JSON 入 `resultData` **默认不下发**，需单独开关「推送完整结果」（合规评审后）。

#### 2.3.5 飞书 / 纯文本模板（`test.result_completed`）

```text
📊 用户测评完成（实时推送）
记录ID: 8848
用户: Ming871
手机: 18302257611
测评类型: MBTI
结果摘要: INTJ
完成时间: 2026-04-14 09:15:33
```

- `测评类型` 行可同时展示英文 code + 中文标签，例如：`SBTI · BOSS（领导者）`，由 `testType` + `resultSummary` 组合策略决定（产品统一即可）。  
- 面相/AI 类：`结果摘要` 可为短文案（如 PDP/面相主类型），过长时截断（如 80 字）。

**去重**：`dedupKey` 建议 `test.result_completed:{testResultId}`（与 envelope `_dedupKey` 一致）；表 `delivery_dedup`，出站场景 `scene=outbound_hook`，库内仅存 `_dedupKey` 原值（展示/API 仍可带 `push_hook:` 前缀）。

### 2.4 HTTP 请求格式（建议）

- **Method**：`POST`
- **Header**  
  - `Content-Type: application/json`  
  - `X-MBTI-Event: lead.order_paid`（事件类型）  
  - `X-MBTI-Delivery-Id: <uuid>`（投递 ID，便于接收方去重）  
  - `X-MBTI-Signature: sha256=<hex>`（可选，见安全）  
- **Body**：根级除 `event`、`occurredAt`、`environment`、`tenant`、`payload` 外，增加 **`hook`**（见 §3.2 / 上文示例），用于区分**业务归属租户**与**实际用于签名的配置行**。
- **Body（`lead.order_paid` 完整示例）**

```json
{
  "event": "lead.order_paid",
  "occurredAt": "2026-04-14T08:39:27+08:00",
  "environment": "production",
  "hook": {
    "configEnterpriseId": 12,
    "usedPlatformFallback": false
  },
  "tenant": {
    "enterpriseId": 0,
    "enterpriseName": null
  },
  "payload": {
    "display": {
      "title": "用户购买成功（实时推送）",
      "emoji": "💰"
    },
    "orderId": 456,
    "orderNo": "FACE20260414083927001",
    "userId": 789,
    "userName": "微信用户",
    "phone": "18302257611",
    "productTitle": "AI人脸性格分析完整报告",
    "productType": "face",
    "amountYuan": "1.00",
    "amountFen": 100,
    "status": "paid",
    "paidAt": "2026-04-14 08:39:27",
    "sourceLabel": "面相测试·「AI人脸性格分析完整报告」·支付成功"
  }
}
```

接收方若只需落库，优先解析 `payload` 内上表字段；若需渲染成飞书卡片，可用 `display.title` + 各键值行，或与 `2.3.2` 模板由服务端统一生成 `payload.textBody`（可选字段）供直接转发。

#### 2.4.1 Body 示例（`test.result_completed`）

```json
{
  "event": "test.result_completed",
  "occurredAt": "2026-04-14T09:15:33+08:00",
  "environment": "production",
  "hook": {
    "configEnterpriseId": 0,
    "usedPlatformFallback": true
  },
  "tenant": {
    "enterpriseId": 123,
    "enterpriseName": "示例企业"
  },
  "payload": {
    "display": {
      "title": "用户测评完成（实时推送）",
      "emoji": "📊"
    },
    "testResultId": 8848,
    "userId": 789,
    "userName": "Ming871",
    "phone": "18302257611",
    "testType": "mbti",
    "testTypeLabel": "MBTI 性格测试",
    "resultSummary": "INTJ",
    "completedAt": "2026-04-14 09:15:33"
  }
}
```

实际字段以后端 `OutboundPushHookService` 为准，保证版本升级时可加字段、兼容旧接收端。

- **`hook`（根级，投递元数据）**  
  - `configEnterpriseId`（int）：**实际用于签名与 HTTP POST 的配置行**所属 `system_config.enterprise_id`（`0` = 全平台默认）。  
  - `usedPlatformFallback`（bool）：当业务归属企业 `>0`，但该企业配置**未对该事件生效**（未启用、无合法 URL、或未订阅该事件）而**改用全平台默认**时为 `true`；业务本身归属平台（无企业）且使用 `enterprise_id=0` 时为 `false`。  
  - 说明：**`tenant.enterpriseId` 表示业务数据归属**；**`hook.configEnterpriseId` 表示请求发到哪个 URL 所用的配置**，二者可以不同（例如企业 B 无专属配置时仍推全平台 URL，但 `tenant` 仍带 B 的测评/订单上下文）。

---

## 三、配置模型与权限

### 3.1 存储（按企业分行 + 全平台默认）

**独立配置键**（与飞书键分离）：

| `system_config.key` | `enterprise_id` | 说明 |
|---------------------|-----------------|------|
| `push_hook_outbound` | `0` | **全平台默认**（超管、以及「无企业归属」的企业管理账号维护同一条） |
| `push_hook_outbound` | `N`（`N>0`） | **企业 N 专属**（仅该企业管理员可编辑本行；超管接口不写入此行） |

**单条 JSON value 结构（与行无关，字段相同）：**

```json
{
  "enabled": true,
  "url": "https://example.com/hooks/mbti",
  "secret": "",
  "events": ["lead.order_paid", "lead.phone_bound", "test.result_completed"]
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `enabled` | bool | 是否启用本行配置 |
| `url` | string | 出站 POST 地址；启用时须 `http`/`https` 开头 |
| `secret` | string | 可选；非空则对 body 做 HMAC-SHA256，请求头 `X-MBTI-Signature: sha256=…` |
| `events` | string[] | 订阅的事件编码列表；**空数组或缺省表示订阅全部**（三类事件） |

### 3.2 运行时选用哪一行配置（回落规则）

对每个事件，先取**业务上下文企业 ID** `contextEnterpriseId`：

| 事件 | `contextEnterpriseId` 来源 |
|------|---------------------------|
| `lead.order_paid` | `orders.enterpriseId`，无则 `0` |
| `lead.phone_bound` | `wechat_users.enterpriseId`，无则 `0` |
| `test.result_completed` | `test_results.enterpriseId`，无则 `0` |

**择一推送（同一事件只发一次 HTTP）：**

1. 若 `contextEnterpriseId > 0`，先读 `push_hook_outbound` 且 `enterprise_id = contextEnterpriseId` 的配置；若对该事件 **`isEventEnabled` 为真**（启用 + 合法 URL + 事件订阅命中或 events 为空），则使用**本行**，`hook.configEnterpriseId = contextEnterpriseId`，`hook.usedPlatformFallback = false`。  
2. 否则读 **`enterprise_id = 0` 全平台默认**；若对该事件仍不可用，则**不推送**。若第 1 步未命中而第 2 步命中，则 `hook.usedPlatformFallback = true`（表示「业务归属某企业，但 URL 用的是平台默认」）。

**示例**：超管与 A 企业都配置了 URL；B 企业未配置或关闭。A 用户产生的订单/测评 → 走 A 的 URL；B 用户 → 回落到全平台 URL；纯个人无企业 → 仅全平台。

### 3.3 管理端读写范围

| 角色 | 接口 | 读写 `system_config` 行 |
|------|------|-------------------------|
| **超管** | `GET/PUT /api/v1/superadmin/settings/push-hook` | 仅 **`enterprise_id=0`**（全平台默认） |
| **企业管理员**（`enterprise_admin` 或已绑定企业的 `admin`） | `GET/PUT /api/v1/admin/settings/push-hook` | **`enterprise_id=本企业`**；若账号**无企业**则 **`enterprise_id=0`**（与超管同一条，与海报配置等一致） |

GET 响应会带 `scope`：`platform` | `enterprise`，以及 `configEnterpriseId`、`enterpriseName`（企业专属时），便于前端展示文案。

### 3.4 与飞书获客的关系

- 飞书仍为 **`feishu_lead_webhook` + `enterprise_id=0` 全局一条**（与本 Hook 独立）。  
- 通用 Hook 与飞书可并行：同一业务事件可同时触发飞书卡片 + HTTP JSON（若两者均启用）。

---

## 四、管理端 UI 规划

### 4.1 超管端（`/superadmin/settings`）

- Tab **「出站推送」**：维护 **全平台默认**（`enterprise_id=0`）。  
- 字段：启用、URL、签名密钥（可选）、事件多选。  
- （可选二期）**测试推送**按钮：向 URL POST `ping` 或示例事件。

### 4.2 企业管理端（`/admin/settings`）

- Tab **「出站推送」**：有企业归属时编辑 **本企业专属行**（`enterprise_id=本企业`）；无企业归属时与超管共用 **全平台默认** 行。  
- 界面展示 `scope`（本企业专属 / 全平台默认）及企业名；说明与飞书区别（本 Hook 为 **JSON 通用格式**）。

### 4.3 与飞书的关系

- **并存**：同一事件可同时推飞书（若启用）+ 通用 Hook（若启用），二者独立开关、独立失败重试策略（可选）。  
- **实现顺序**：先通用 Hook 服务类 + 超管/企业 API + 页面；飞书逻辑可逐步改为「内部也是一种 channel」或保持独立（避免大改时可并行调用）。

---

## 五、安全与运维

1. **HTTPS 强制**：与飞书相同，仅允许 `https://`（内网调试可配置白名单或开发环境放宽，生产建议强制）。  
2. **签名校验**（可选）：用 `secret` 对 body 做 HMAC-SHA256，`X-MBTI-Signature` 传递；文档中给出验签示例（Node/PHP）。  
3. **超时与重试**：出站请求建议 3s 超时；失败可记日志 + 可选异步重试（二期）。  
4. **敏感信息**：手机号等是否在 JSON 全量下发，需与合规策略一致；可与飞书当前展示粒度对齐。

---

## 六、后端实现要点（已落地）

1. **`OutboundPushHookService`**：`getConfig(int $enterpriseId)` 按行读取；`getEffectiveConfigForEvent($contextEnterpriseId, $event)` 实现 **企业优先 + 回落全平台**；`dispatch($event, $envelope, $contextEnterpriseId)` 写入根级 **`hook`** 后 POST。  
2. **`lead.order_paid`**：`Payment` 回调与查单路径，在飞书同路径旁调用；`contextEnterpriseId` 来自 **`orders.enterpriseId`**。  
3. **`lead.phone_bound`**：`Auth` 首次绑手机；`contextEnterpriseId` 来自 **`wechat_users.enterpriseId`**。  
4. **`test.result_completed`**：`Test::submit`、`Analyze`（面相 / 简历等写库）；`contextEnterpriseId` 来自 **`test_results.enterpriseId`**。  
5. **管理端**：`admin.Settings` / `superadmin.Settings` 的 `GET/PUT .../push-hook`；超管仅写 `enterprise_id=0`，企业端按账号解析写 `0` 或本企业。  
6. **去重**：与飞书共用表 `delivery_dedup`，用 **`scene` 区分**：飞书 `feishu_lead`，出站 `outbound_hook`；出站库内键为 `_dedupKey` 原值，**同一业务事件只投递一次**（不因回落改变去重键）。  
7. （可选）投递日志表、重试队列为二期。

---

## 七、实施阶段建议

| 阶段 | 内容 |
|------|------|
| **P0（已完成）** | `push_hook_outbound` 多行存储；**企业专属 + 全平台回落**；三类事件 + 超管/企业后台配置页；根级 **`hook` 元数据** |
| **P1** | 管理端「测试推送」、更细粒度事件订阅、投递失败告警 |
| **P2** | 失败重试、投递日志、多 URL 列表 |

---

## 八、验收清单（供测试）

- [ ] 超管保存全平台默认：`system_config` 中 `key=push_hook_outbound` 且 **`enterprise_id=0`**。  
- [ ] 企业 A 管理员保存后存在 **`enterprise_id=A`** 的行；企业 B 未配置时，B 用户事件 **`hook.usedPlatformFallback=true`** 且请求发到全平台 URL。  
- [ ] A 用户产生的事件：若 A 行启用且合法，**`hook.configEnterpriseId=A`** 且 **`usedPlatformFallback=false`**。  
- [ ] 支付成功 / 绑手机 / 测评落库：接收端 JSON 含 **`hook`、`tenant`、`payload`**，且与本文字段一致。  
- [ ] 同一 `testResultId` / 订单不重复推送（去重生效）。  
- [ ] 关闭对应行开关或清空 URL 后，按回落规则不再向该行投递或改投全平台。  
- [ ] 与飞书并行时两者互不干扰。

---

## 九、参考代码位置（现有）

- 飞书：`api/app/common/service/FeishuLeadWebhookService.php`  
- 测评提交：`api/app/controller/api/Test.php`（`submit` 等）、`api/app/controller/api/Analyze.php`（分析写库）  
- 超管配置：`api/app/controller/superadmin/Settings.php`（`getFeishuLeadConfig` / `updateFeishuLeadConfig`）  
- 企业/管理员配置：`api/app/controller/admin/Settings.php`（同上）  
- 前端：`admin/src/views/admin/PushHookConfigPanel.vue`（超管/企业共用，通过 `apiPrefix` 区分接口）

---

*文档版本：2026-04-14（v5：**企业专属 Hook + 全平台回落**、`hook` 元数据、管理端 `scope` 字段；付款/测评字段同 v4）。*
