# 平台定价、用户付款与分销逻辑说明

> **说明**：文件名保留 `MBTI…` 仅为历史习惯；本文描述的是 **全平台同一套规则**，适用于 **MBTI、人脸（face）、PDP、DISC、SBTI、简历（resume）、高考（gaokao）** 等在 `Test::saveResult`、`Payment`、`TestProductPricing`、`Distribution`、`EnterpriseBillingService` 中有分支的测评/产品。差异主要在于：`pricing_config` / 管理后台里的 **JSON 键名**（如 `mbti`、`face`、`disc`…）、`test_results.testType`、以及个别白名单（例如企业余额入账 `creditEnterpriseBalanceForOrder` 不含 `gaokao`，见 §5）。
>
> 下文在举例、防混淆时仍常以 **MBTI** 写具体字段名，换其他测评时把 **`mbti` 键 / `mbti` 产品类型** 换成对应键即可。

---

## 1. 项目相关位置（速查）

| 层级 | 路径 / 说明 |
|------|----------------|
| 定价表 | `pricing_config`（模型 `app\model\PricingConfig`），`type` + `enterpriseId` 唯一 |
| 超管定价 API | `api/app/controller/superadmin/Pricing.php` |
| 企业管理定价 API | `api/app/controller/admin/Pricing.php` |
| 超管前端 | `admin/src/views/superadmin/Pricing.vue` |
| 企业管理员前端 | `admin/src/views/admin/Pricing.vue` |
| 提交测评 / 写 `test_results` | `api/app/controller/api/Test.php`（`saveResult` 等） |
| 统一下单与回调 | `api/app/controller/api/Payment.php` |
| 订单金额计算 | `Payment::calculateAmount` → `app\common\service\TestProductPricing::amountFenForTestProduct` |
| 分销 | `api/app/controller/api/Distribution.php`（`settleCommission`、`settleTestCommission`） |
| 企业平台费（与用户付款分列） | `api/app/common/service/EnterpriseBillingService.php` |

---

## 2. `pricing_config` 中的几类「价格」

配置存在 JSON 字段 `config` 里，单项测评单价单位为 **元**（代码里会 `×100` 转为 **分** 落库/支付）。常见键名与 `testType` / 支付 `productType` 对齐，例如：**`mbti`、`face`、`disc`、`pdp`、`sbti`**；企业版里还可能有 **`report`、`teamAnalysis`**（团队分析）等扩展字段，以管理端表单与 `Test.php` 解析为准。

### 2.1 超管后台（全局默认）

| `type` | `enterpriseId` | 含义 |
|--------|------------------|------|
| `personal` | `NULL` | **全局个人版**默认价：无企业管理端覆盖、且用户走个人链路时使用 |
| `enterprise` | `NULL` | **全局企业版**兜底价（用户走「企业档」计价、且该企业未配 `admin_enterprise` 时用）；**同一行 JSON 里各测评键（`mbti`、`face`、`disc`…）也会被 `EnterpriseBillingService` 当作对应类型的「企业平台费」单价**（测评提交成功且 `test_results` 带企业归属时从企业余额扣，与用户实付分列） |
| `enterprise` | 某企业 ID | （可选）超管可为单个企业单独配一条企业版价（仍走 `getByTypeAndEnterprise('enterprise', eid)`，**平台费仍读全局 enterprise 行**，见 §7） |

接口：`GET/PUT /superadmin/pricing`（见 `superadmin/Pricing.php`）。

### 2.2 企业管理后台（企业专属）

仅 **`enterprise_admin`** 会解析出 `enterpriseId`；角色为普通 `admin` 且无企业绑定时，`enterpriseId` 为 `null`，此时个人版配置落在 **`admin_personal` + `enterpriseId = NULL`** 行（与「平台运营」共用一套逻辑，见 `admin/Pricing::resolveEnterpriseId`）。

| `type` | `enterpriseId` | 含义 |
|--------|------------------|------|
| `admin_personal` | 企业 ID 或 NULL | **企业侧「个人版」价**：用户绑定该企业、且走 **个人档** 定价时优先读取（见 `PricingConfig::getByTypeAndEnterprise('personal', eid)`） |
| `admin_enterprise` | 企业 ID | **企业侧「企业版」价**：订单/测评上下文带 **企业测试** 时用（见下文章节 4、5） |

接口：`GET/PUT /api/v1/admin/pricing`（见 `admin/Pricing.php`）。

### 2.3 读取优先级（与模型注释一致）

- **个人档 `personal`**：`admin_personal`（有 eid 则带 eid）→ 否则 `personal` 全局。实现见 `PricingConfig::getByTypeAndEnterprise`。
- **企业档 `enterprise`**：`admin_enterprise` + eid → 否则 `enterprise` 全局。

---

## 3. 测评提交时：是否需要付费、应付多少分

在 **`Test::saveResult`**（提交问卷 / 人脸等结果）中，对允许的 **`testType`**（如 `mbti`、`face`、`disc`、`pdp`；`ai` 入口通常按人脸处理等，以接口校验为准）：

1. 根据请求是否带 **`enterpriseId`**、以及用户 **`wechat_users.enterpriseId`**，解析出 `pricingEnterpriseId` / `writeEnterpriseId`（与 `Payment::calculateAmount` 注释一致：企业分享带 eid、个人测可仍有绑定企业用于定价）。
2. **`getRequiresPaymentByTestType($testType, …)`**：若解析到的配置里 **该类型对应键**（如 `mbti`、`face`…）单价 **> 0**，则 `requiresPayment = 1`。
3. **`getStandardAmountFenByTestType`**：把配置里的 **元** 转为 **分**，写入 `test_results.paidAmount`（表示「应付标准价」）。

定价配置来源 **`Test::resolvePricingConfig`**（与 `PricingConfig` 一致）：

- 若本次是 **企业测试**（`enterpriseId` 非空）→ 用 **`enterprise`** 档 → `admin_enterprise` / 全局 `enterprise`。
- 否则若用户 **绑定企业** → 用 **`personal`** 档 + 该企业 → `admin_personal` / 全局 `personal`。
- 否则 → 全局 **`personal`**。

代码：`api/app/controller/api/Test.php` 中 `getRequiresPaymentByTestType`、`getStandardAmountFenByTestType`、`resolvePricingConfig` 及 `saveResult` 内 `insertGetId`。

---

## 4. 用户发起微信支付：`Payment::create`

### 4.1 金额从哪里来

1. **优先固定价**：若传入 **`testResultId`**（或未传但自动绑定到最近一条同 `productType` 的 `test_results`），且该记录 **`paidAmount > 0`**，则订单金额 **`fixedAmountFen = paidAmount`**，不再重新算价（与提交测评时写入的标准价一致）。
2. **否则**：调用 **`calculateAmount`**，对测试类产品走 **`TestProductPricing::amountFenForTestProduct`**。

### 4.2 `calculateAmount` 与「个人 / 企业」两档

在 `Payment::calculateAmount` 中：

- 若推断出的 **`$enterpriseId` 非空**（来自 `test_results.enterpriseId` 或请求参数 `enterpriseId`）→ **`pricingType = 'enterprise'`** → 读 **企业版** 配置。
- 否则 → **`pricingType = 'personal'`**；若用户绑定了企业，会把 **`pricingEnterpriseId`** 设为该用户 `wechat_users.enterpriseId`，用于读 **`admin_personal`**。

第五参数传入 `TestProductPricing::amountFenForTestProduct(..., $pricingType)`，避免「只因为有企业 ID 就误用企业版价」的问题（高考 `gaokao` 等场景曾修复过同类逻辑；各 `productType` 与此共用 `TestProductPricing`）。

代码：`api/app/controller/api/Payment.php` 的 `create`、`calculateAmount`；`api/app/common/service/TestProductPricing.php`。

### 4.3 订单与测评绑定

创建 **`orders`** 后，若存在 `test_results`，会把 **`test_results.orderId`** 更新为当前订单 id，便于支付回调按订单反查测评类型、做分销。

---

## 5. 支付成功之后

在 **`Payment::notify`** / **`Payment::query`** 确认支付成功后（非 `recharge`）：

1. **`test_results`**：`isPaid = 1`，写入 `paidAmount`、`paidAt`（与微信 `total_fee` 或本地订单金额一致）。
2. **`creditEnterpriseBalanceForOrder`**：若订单带 **`enterpriseId`** 且 `productType` 属于 `face|mbti|sbti|disc|pdp|resume|recharge`，把 **用户实付金额** 记一条 **`finance_records`（type=`recharge` 命名历史原因）**，**增加** 该企业 `enterprises.balance`（注释：企业测试收入）。
3. **`Distribution::settleCommission($orderId)`**：按分销规则从 **企业余额**（有企业订单时）或平台侧给推荐人结算佣金（见下一节）。

注意：**`gaokao`** 不在 `creditEnterpriseBalanceForOrder` 的白名单内；高考另有业务处理。

---

## 6. 分销（与各测评订单 / 测完事件相关）

### 6.1 订单支付佣金：`settleCommission`

- **触发**：测试类订单（`productType` 与 `testType` 映射一致，如 **`mbti`、`face`、`disc`、`pdp`、`sbti`、`resume`、`gaokao`** 等）支付成功，`commission_records` 按 **`orderId` 防重**。
- **绑定查找**：根据订单的 `enterpriseId` 决定 `scope`（`enterprise` / `personal`），在 **`distribution_bindings`** 中匹配邀请关系；企业单未命中时可 **回退** 到 personal 绑定（代码注释：扣款企业仍以订单为准）。
- **佣金数值**：从 **`getTestCommissionConfig($testType, $scope, $configEnterpriseId)`** 读取：
  - 优先 **`distribution` 配置里的 `testSettings[$testType]`**（如 `testSettings.mbti`、`testSettings.face`…里的 `enabled`、比例或固定分）；
  - 否则回退全局 **`commissionRate` / `commissionAmountFen`**。
- **企业订单**：优先从 **`billingEnterpriseId = 订单 enterpriseId`** 的余额扣佣金；不足则记入推荐人 **`walletPending`（冻结）**；无企业则平台直接给推荐人钱包入账。

代码：`Distribution::settleCommission` 及 `getTestCommissionConfig` / `resolveTestSetting`。

### 6.2 测评完成佣金（免单 / 未付款场景）：`settleTestCommission`

- **触发**：`Test::saveResult` 在写入 `test_results` 成功后调用（与各问卷 / 人脸提交同源）。
- **适用类型**：代码里 **`$allowedTypes = ['face','mbti','sbti','disc','pdp']`**（`ai` 会归一成 `face`），需 **`testSettings` 里开启 `noPayment`** 等条件；金额来自分销配置。
- **与订单佣金区别**：无 `orderId`，防重维度为 **`testResultId` + `commissionSource = test_completion`**；仍可能从企业余额扣款给推荐人。

代码：`Distribution::settleTestCommission`。

---

## 7. 企业平台费（与用户付款、分销并列的另一条线）

**`EnterpriseBillingService::chargePlatformFeeForTestResult`**

- **时机**：`Test::saveResult` 成功插入 `test_results` 后（各 `testType`），与是否已微信支付 **无关**。
- **金额**：读 **超管全局** `PricingConfig` 中 **`type = enterprise` 且 `enterpriseId = NULL`** 的 JSON 里 **各测评键** 的 **平台单价（元）→ 分**；描述里带 `testResultId` **幂等**。
- **作用**：从 **`enterprises.balance` 扣减**（`finance_records` type=`consume`），表示 **平台向企业收的单次测评费**；与用户微信实付、分销佣金是 **不同科目**。

代码：`api/app/common/service/EnterpriseBillingService.php`，调用处 `api/app/controller/api/Test.php`（`saveResult` 内）。

---

## 8. 心智模型小结（避免混淆）

| 概念 | 谁配置 | 影响什么 |
|------|--------|----------|
| 个人版价 / 企业版价（用户侧售价） | 超管 `personal`/`enterprise` + 企业 `admin_personal`/`admin_enterprise` | `requiresPayment`、`paidAmount`、微信支付金额 |
| 分销佣金比例或固定分 | 分销配置 **`testSettings` 下按类型分块**（如 `mbti`、`face`、`disc`…） | 支付成功后 `settleCommission`；免单完成时 `settleTestCommission` |
| 企业平台费单价 | 超管 **全局 enterprise**（`enterpriseId = NULL`）配置里 **与各测评键同名** 的单价 | 测评提交成功即从 **企业余额** 扣平台费（`test_results` 写入企业归属时）；**与上表「企业版用户价」共用同一配置源，账目语义不同** |
| 企业余额「加一笔」 | 用户支付成功且订单有 `enterpriseId` | `creditEnterpriseBalanceForOrder`：用户实付进企业余额（测试类产品白名单） |

---

## 9. 业务场景示例（与你描述的逻辑对齐）

以下以 **MBTI**（配置键 **`mbti`**）举例；**人脸 / PDP / DISC / SBTI** 等仅把键名与 `testType` 替换即可，**规则相同**。数值均为说明用。约定：

- **超管**：`personal` 全局 `mbti = 1` 元；`enterprise` 全局 `mbti = 2` 元（该 2 元在代码里同时作为 **企业版用户价兜底** 与 **MBTI 平台费单价** 的数据来源，见 §2.1、§7）。
- **A 企业管理员**：`admin_personal` 里 `mbti = 0.5` 元；`admin_enterprise` 里 `mbti = 0` 元。

### 9.1 小王（已绑定 A 企业）— 做「个人版」链路测评

- **用户侧应付**：走 **`personal` 档** → 优先 A 的 **`admin_personal`** → **0.5 元**（写入 `test_results.paidAmount` / 微信支付按此，除非另有逻辑）。
- **企业被平台扣费（平台费）**：测评提交写库成功后，`EnterpriseBillingService` 按超管全局 **`enterprise.mbti = 2` 元** 从 **A 企业余额** 扣 **2 元**（`finance_records` consume，幂等按 `testResultId`）。
- **企业「测试收入」**：用户付完款后，`creditEnterpriseBalanceForOrder` 把 **用户实付 0.5 元** 记入 A 企业余额（与平台费不同科目）。你描述的 **「企业收入 0.5」** 指这笔 **用户实付入账**；**「企业需要扣费 2 元」** 指 **平台费** 另扣。

### 9.2 小张（已绑定 A 企业）— 做「企业版」链路测评

- **用户侧应付**：走 **`enterprise` 档** → 优先 A 的 **`admin_enterprise`** → **0 元**（可能 `requiresPayment = 0`，无需微信付）。
- **企业平台费**：同上，提交测评成功后仍按全局 **`enterprise.mbti = 2` 元** 从 A 余额扣 **2 元**（只要写入了企业归属的 `test_results` 且平台费单价大于 0）。
- **企业测试收入**：用户实付 **0** → **`creditEnterpriseBalanceForOrder` 入账 0**。你描述的 **「企业收入 0」** 即无用户实付进账。

### 9.3 小李（未绑定任何企业）— 仅个人版

- **用户侧应付**：无企业 → **`personal` 全局** → **1 元**（超管个人版默认）。
- **企业平台费 / 企业测试收入**：无 `test_results.enterpriseId`（或企业 ID 为 0）时，**不按企业扣平台费**（`EnterpriseBillingService` 直接 return）；也无企业余额入账。

---

## 10. 前端管理端对应关系

| 后台 | 页面 | 保存到后端的含义（简写） |
|------|------|---------------------------|
| 超级管理 | `admin/src/views/superadmin/Pricing.vue` | 全局 `personal` / `enterprise`（及 deep 等） |
| 企业管理 | `admin/src/views/admin/Pricing.vue` | `admin_personal`、（企业管理员）`admin_enterprise` |

---

## 11. 分销两种结佣（对话整理）

管理后台里 **各测评类型**（MBTI、人脸、PDP、DISC、SBTI…）在分销配置中均可单独一块 **`testSettings[testType]`**：可配 **佣金类型（比例 / 固定金额）**、**固定金额（元）**，以及 **「无需付款触发」**（用户完成测试即发放佣金，无需付款）。对应代码里两条独立链路如下（与具体是哪一种测评无关，仅 `testType` 不同）。

### 11.1 两种情况对照

| 情况 | 名称（口语） | 什么时候结佣 | 主要代码入口 |
|------|----------------|-------------|----------------|
| **①** | **跟单结佣**（有微信支付） | 用户 **订单已支付**（微信回调 / 查询确认） | `Distribution::settleCommission(orderId)`，由 `Payment` 支付成功后调用 |
| **②** | **测完结佣**（可不依赖付款） | 用户 **测评结果已提交落库**（`Test::saveResult` 成功），且 `testSettings` 中 **`enabled` + `noPayment`** 等条件满足 | `Distribution::settleTestCommission(testResultId, …)`，由 `Test::saveResult` 内调用 |

**「无需付款触发」** 打开时，对应 **②**：不要求用户先微信付款，只要 **测完** 且邀请绑定、佣金规则满足，即可尝试结佣。

### 11.2 对话摘要

**运营**：两种结佣怎么区分？

**产品**：**①** 永远跟 **微信已付订单** 走；**②** 跟 **测评提交成功** 走，和是否付款脱钩（故叫「无需付款触发」）。

**运营**：小王付 0.5 元、小张 0 元，和 ①② 怎么叠？

**产品**（与 §9 场景对齐，实际仍受绑定、`enabled`、防重等约束）：

| 用户 | 用户侧 | ① 跟单结佣 | ② 测完结佣 |
|------|--------|------------|------------|
| 小王 | 个人链路付 0.5 元 | 付完款可触发：订单金额参与 **比例**；或按配置 **固定分** | 若打开「无需付款触发」：**测完** 也可能触发 **②**（防重用 `testResultId`，与订单防重不同） |
| 小张 | 企业链路 0 元 | 订单金额为 0：**比例佣金为 0**；若 ① 为 **固定金额且大于 0** 仍可能有一笔（看配置） | **测完** 若满足 `noPayment`：**② 仍可发**（适合「用户没付钱也要给邀请人记一笔」） |
| 小李 | 未绑企业、个人 1 元 | 通常以 **①** 为主（有付款才有订单） | **②** 若绑定/企业上下文不满足则可能不触发 |

**运营**：两种会重复拿两次吗？

**产品**：**不是同一笔账**：① 按 **`orderId`** 防重；② 按 **`testResultId` + `commissionSource = test_completion`** 防重。若业务上 **同时** 依赖跟单与测完，可能出现 **两笔不同记录**，是否都要由运营在后台把开关与金额想清楚。

---

*文档根据当前仓库代码整理；若路由前缀以实际 `api/route` 部署为准。*
