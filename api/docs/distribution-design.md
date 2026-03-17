# 分销系统设计文档

> 版本：v1.4  
> 日期：2026-03-06  
> 作者：AI 设计稿（待确认）

---

## 一、功能概述

本系统分销分为两个维度：

| 维度 | 配置来源 | 佣金来源 | 管理入口 |
|------|---------|---------|---------|
| **个人版分销** | 超管后台配置 | 平台微信支付订单收款 | 超管端 |
| **企业版分销** | 企业管理员后台配置 | 企业账户余额 | 管理端（admin） |

---

## 二、核心业务规则

### 2.1 绑定规则

| 规则 | 说明 |
|------|------|
| 绑定方式 | 用户 A 分享专属链接/海报，用户 B 通过链接进入小程序，系统自动建立 A → B 的绑定关系 |
| 有效期 | **30 天**，从绑定时刻起计算，到期自动解除 |
| **续期机制** | B 在绑定有效期内**再次点击 A 的链接**（同一推荐人），有效期从点击时刻**重置为 30 天**；举例：昨天 A→B 绑定剩余 29 天，今天 B 再次点击 A 的链接，有效期重新变为 30 天 |
| 抢绑机制 | 支持覆盖绑定：A 已绑定了 B，C 再分享给 B 后，B 的推荐人变更为 C（新绑定覆盖旧绑定，重新计算 30 天） |
| **禁止互相绑定** | 仅当 A→B 在有效期内时：B 再分享给 A，A 扫码不能绑定 B（静默忽略）；若 A→B 已过期，则 A 扫码可以绑定 B |
| 绑定时机 | 用户点击推广链接进入小程序 `onLoad` 时，识别 `uid`（推荐人ID）写入/更新绑定记录 |
| 例外情况 | 用户已付款订单对应的绑定关系不会因抢绑而撤销（付款时的绑定快照保留） |

### 2.2 佣金规则（付款触发）

| 规则 | 说明 |
|------|------|
| 触发时机 | 被邀请用户**完成付款**时触发佣金计算 |
| 佣金比例 | 优先使用与订单 scope 匹配的配置；回退匹配时使用个人版（超管）配置 |
| 计算基数 | 订单实付金额（分）|
| 佣金归属 | 按付款时刻的绑定关系确认推荐人，快照写入佣金记录，后续抢绑不影响已有佣金 |
| **跨 scope 回退** | 若订单 scope 下无匹配绑定（如企业版订单但推荐人仅有个人版绑定），则回退查找该用户任意有效的 `personal` 绑定；命中后以**个人版佣金配置**结算，但只要命中企业上下文，佣金资金仍优先从该企业余额扣除 |

### 2.3 测试完成佣金（免付款触发）

| 规则 | 说明 |
|------|------|
| 触发时机 | 用户**完成指定类型测试并提交结果**时触发，无需付款 |
| 适用类型 | 人脸分析（face）、MBTI、DISC、PDP，各自独立开关 |
| 佣金金额 | 每种类型单独配置固定金额（元），非比例 |
| 佣金来源 | 无企业上下文时由平台发放；若命中企业上下文，则优先扣减企业余额，余额不足时冻结，待企业补余额后解冻 |
| 防重机制 | 同一 `testResultId` 只结算一次，重复提交不产生多条佣金 |
| 配置入口 | 超管后台 → 分销设置 → 测试完成佣金 |
| 全局开关 | 超管可一键关闭整个「测试完成佣金」功能 |
| 前提条件 | 用户须有有效的 `personal` scope 绑定关系（invitee → inviter），企业版绑定不触发 |

### 2.4 企业版特有规则

| 规则 | 说明 |
|------|------|
| 佣金资金来源 | 只要佣金记录命中**企业上下文**，都扣除企业账户余额（`enterprises.balance`）|
| 余额充足 | 触发佣金时立即从企业余额扣款，佣金状态置为 `paid`，推荐人可提现 |
| 余额不足 | 佣金状态置为 `frozen`（冻结），企业补余额后系统扫描待解冻记录，余额充足则自动解冻并扣款 |
| 解冻顺序 | 按佣金记录的 `createdAt` 升序（先冻先解）|

---

## 三、数据库设计

### 3.1 新增表：`distribution_bindings`（绑定记录）

```sql
CREATE TABLE `mbti_distribution_bindings` (
  `id`              int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '绑定ID',
  `inviterId`       int(11) NOT NULL COMMENT '当前推荐人ID (wechat_users.id)',
  `inviteeId`       int(11) NOT NULL COMMENT '被推荐人ID (wechat_users.id)',
  `scope`           varchar(20) NOT NULL DEFAULT 'personal' COMMENT '分销维度: personal|enterprise',
  `enterpriseId`    int(11) NULL DEFAULT NULL COMMENT '企业ID（企业版时非空）',
  `expireAt`        int(11) NOT NULL COMMENT '绑定过期时间戳（绑定时间+30天）',
  `status`          varchar(20) NOT NULL DEFAULT 'active' COMMENT '状态: active/expired/overridden（被抢绑覆盖）',
  `prevInviterId`   int(11) NULL DEFAULT NULL COMMENT '被抢绑前的推荐人ID（抢绑时记录上一任推荐人）',
  `overriddenAt`    int(11) NULL DEFAULT NULL COMMENT '被覆盖时间（抢绑时记录）',
  `createdAt`       int(11) NULL DEFAULT NULL COMMENT '绑定创建时间',
  `updatedAt`       int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invitee_scope_ent` (`inviteeId`, `scope`, `enterpriseId`),
  INDEX `idx_inviterId`     (`inviterId`),
  INDEX `idx_inviteeId`     (`inviteeId`),
  INDEX `idx_prevInviterId` (`prevInviterId`),
  INDEX `idx_expireAt`      (`expireAt`),
  INDEX `idx_status`        (`status`)
) ENGINE=InnoDB COMMENT='分销绑定记录表';
```

> **说明**：
> - `(inviteeId, scope, enterpriseId)` 联合唯一索引保证同一被推荐人在同一维度下只有一条 active 记录。
> - 抢绑时：将当前 `inviterId` 写入 `prevInviterId`，再将 `inviterId` 更新为新推荐人，同时记录 `overriddenAt`，保留完整的历史推荐链路，便于管理端溯源。
> - 续期时（同一推荐人再次点击）：仅更新 `expireAt` 和 `updatedAt`，`prevInviterId` 不变。

---

### 3.2 改造表：`distribution_commission_records`（佣金记录）

在现有 `mbti_commission_records` 基础上新增字段：

```sql
ALTER TABLE `mbti_commission_records`
  ADD COLUMN `scope`        varchar(20)  NOT NULL DEFAULT 'personal' COMMENT '分销维度: personal|enterprise',
  ADD COLUMN `enterpriseId` int(11)      NULL DEFAULT NULL COMMENT '企业ID（企业版佣金）',
  ADD COLUMN `inviterId`    int(11)      NOT NULL COMMENT '推荐人 wechat_users.id',
  ADD COLUMN `inviteeId`    int(11)      NOT NULL COMMENT '付款用户 wechat_users.id',
  ADD COLUMN `bindingId`    int(11)      NOT NULL COMMENT '触发此佣金的绑定记录ID',
  ADD COLUMN `orderAmount`  int(11)      NOT NULL DEFAULT 0 COMMENT '订单金额（分）',
  ADD COLUMN `commissionFen` int(11)     NOT NULL DEFAULT 0 COMMENT '佣金金额（分）',
  ADD COLUMN `frozenAt`     int(11)      NULL COMMENT '冻结时间（企业余额不足时）',
  ADD COLUMN `unfrozenAt`   int(11)      NULL COMMENT '解冻时间',
  MODIFY COLUMN `status` varchar(20) DEFAULT 'pending' COMMENT 'pending/paid/frozen/cancelled',
  ADD INDEX `idx_scope`        (`scope`),
  ADD INDEX `idx_enterpriseId` (`enterpriseId`),
  ADD INDEX `idx_inviterId`    (`inviterId`),
  ADD INDEX `idx_status`       (`status`);
```

**佣金状态流转**：

```
pending → paid          （个人版：订单付款成功后自动结算）
pending → paid          （企业版：企业余额充足，扣款成功）
pending → frozen        （企业版：企业余额不足）
frozen  → paid          （企业充值后，系统解冻扫描）
pending/frozen → cancelled （订单退款时撤销）
```

---

### 3.3 新增：推荐人钱包余额字段（`wechat_users`）

```sql
ALTER TABLE `mbti_wechat_users`
  ADD COLUMN `walletBalance`    int(11) NOT NULL DEFAULT 0 COMMENT '钱包余额（分）',
  ADD COLUMN `walletTotalEarned` int(11) NOT NULL DEFAULT 0 COMMENT '历史累计佣金（分）',
  ADD COLUMN `walletPending`    int(11) NOT NULL DEFAULT 0 COMMENT '待入账佣金（分）';
```

---

### 3.4 新增表：`distribution_withdrawals`（提现记录）

```sql
CREATE TABLE `mbti_distribution_withdrawals` (
  `id`          int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId`      int(11) NOT NULL COMMENT '推荐人 wechat_users.id',
  `amountFen`   int(11) NOT NULL COMMENT '申请提现金额（分）',
  `realNameInfo` varchar(255) NULL COMMENT '收款实名信息（JSON: name+idcard/wechat）',
  `status`      varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected/transferred',
  `auditNote`   varchar(500) NULL COMMENT '审核备注',
  `auditAt`     int(11) NULL COMMENT '审核时间',
  `transferAt`  int(11) NULL COMMENT '打款时间',
  `createdAt`   int(11) NULL,
  `updatedAt`   int(11) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_userId`  (`userId`),
  INDEX `idx_status`  (`status`)
) ENGINE=InnoDB COMMENT='分销提现记录表';
```

---

## 四、核心流程

### 4.1 绑定流程

```
用户点击分享链接进入小程序
    ↓
onLoad(options) 识别 uid=推荐人ID, eid=企业ID（可选）
    ↓
调用 POST /api/distribution/bind  { inviterId: uid, scope, enterpriseId }
    ↓
【前置校验】
    ├── inviterId == 当前登录用户自身 → 忽略，静默返回（不能自绑）
    ├── 企业版（scope=enterprise）：
    │       校验推荐人 wechat_users.enterpriseId === enterpriseId
    │       不符合（推荐人非该企业成员）→ 忽略，静默返回
    ├── 禁止互相绑定（仅有效期内）：存在未过期的 A→B（inviterId=当前用户, inviteeId=本次推荐人, 同 scope, status=active, expireAt>now）
    │       → 忽略，静默返回；若 A→B 已过期则不拦截，允许建立 B→A
    └── 校验通过 → 继续
    ↓
系统查询 distribution_bindings 中该 inviteeId 是否有 active 且未过期的记录
    │
    ├── 无记录（首次绑定）：
    │       新建绑定，status=active，expireAt=now+30天，prevInviterId=NULL
    │
    ├── 有记录 且 inviterId 相同（同一推荐人再次点击 → 续期）：
    │       更新 expireAt = now+30天（重置有效期）
    │       updatedAt = now
    │       ⚠️ prevInviterId 不变，不新建记录
    │
    └── 有记录 且 inviterId 不同（抢绑）：
            记录 prevInviterId = 旧 inviterId（保留上一任推荐人）
            更新 inviterId = 新推荐人，expireAt=now+30天，overriddenAt=now
            ⚠️ 历史绑定快照保留在同一行，不新建记录
    ↓
返回绑定成功（含新的 expireAt 供前端展示倒计时）
```

### 4.2 佣金结算流程（付款成功后触发）

```
订单支付成功回调 (Payment/notify)
    ↓
确定订单 scope：订单有 enterpriseId → enterprise，否则 → personal
    ↓
【第一步：精确匹配】
查询付款用户 (inviteeId) 与订单 scope + enterpriseId 完全匹配的 active 未过期绑定
    ├── 命中 → 使用该绑定，进入佣金计算（scope 保持订单 scope）
    └── 未命中 → 进入【第二步：回退匹配】
    ↓
【第二步：回退匹配（跨 scope）】
查询付款用户任意 active 未过期的 scope='personal' 绑定
    ├── 命中 → 使用该绑定，强制 scope='personal'（以个人版配置+平台资金结算）
    └── 未命中 → 跳过，无佣金产生
    ↓
读取对应 scope 佣金比例配置，计算 commissionFen = orderAmount * rate
    ↓
写入 commission_records（记录实际使用的 scope 与 enterpriseId）
    ↓
if scope == 'personal':
    直接结算 → 推荐人 walletBalance += commissionFen
    commission status → paid
if scope == 'enterprise':
    查询 enterprises.balance
    ├── 余额 >= commissionFen:
    │       enterprises.balance -= commissionFen
    │       推荐人 walletBalance += commissionFen
    │       commission status → paid
    └── 余额不足:
            commission status → frozen，frozenAt=now
```

### 4.3 企业充值后解冻流程

```
企业补余额成功（充值接口回调 / 超管上调余额）
    ↓
enterprises.balance += 充值金额
    ↓
查询该企业所有 status=frozen 的 commission_records（按 createdAt ASC）
    ↓
循环：
    if 当前 balance >= commissionFen:
        balance -= commissionFen
        commission status → paid，unfrozenAt=now
        推荐人 walletBalance += commissionFen
    else:
        break（余额耗尽，剩余记录保持冻结）
    ↓
更新 enterprises.balance
```

---

## 五、API 接口规划

### 5.1 小程序端（需登录）

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/distribution/bind` | 绑定推荐人（进入小程序时调用） |
| GET  | `/api/distribution/stats` | 获取我的推广中心统计（余额、绑定数、佣金等） |
| GET  | `/api/distribution/bindings` | 我邀请的用户列表（分页，支持 tab：绑定中/已付款/已过期） |
| GET  | `/api/distribution/commissions` | 我的佣金记录（分页） |
| POST | `/api/distribution/withdraw` | 申请提现 |
| GET  | `/api/distribution/withdrawals` | 我的提现记录 |

### 5.2 管理端（admin/enterprise_admin）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET  | `/api/v1/admin/distribution/overview` | 分销数据概览 |
| GET  | `/api/v1/admin/distribution/bindings` | 所有绑定记录（管理员查看） |
| GET  | `/api/v1/admin/distribution/commissions` | 所有佣金记录 |
| GET  | `/api/v1/admin/distribution/withdrawals` | 提现申请列表 |
| POST | `/api/v1/admin/distribution/withdrawals/:id/approve` | 审核通过提现 |
| POST | `/api/v1/admin/distribution/withdrawals/:id/reject` | 拒绝提现 |
| GET  | `/api/v1/admin/distribution/settings` | 获取分销配置 |
| PUT  | `/api/v1/admin/distribution/settings` | 更新分销配置 |

### 5.3 超管端（superadmin）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET  | `/api/v1/superadmin/distribution/settings` | 获取个人版分销全局配置 |
| PUT  | `/api/v1/superadmin/distribution/settings` | 更新个人版分销全局配置 |
| GET  | `/api/v1/superadmin/distribution/overview` | 全平台分销数据 |

---

## 六、分销配置结构

### 个人版（超管配置，存 system_config 表）

```json
{
  "enabled": true,
  "commissionRate": 90,
  "bindingDays": 30,
  "minWithdrawFen": 1000,
  "withdrawFee": 0
}
```

### 企业版（企业管理员配置，存 enterprise_distribution_config 表 或 system_config 带 enterpriseId）

```json
{
  "enabled": true,
  "commissionRate": 80,
  "bindingDays": 30,
  "minWithdrawFen": 1000
}
```

---

## 七、已确认问题

| # | 问题 | 确认结论 |
|---|------|---------|
| 1 | 被抢绑的旧推荐人已产生的佣金是否撤销？ | **已确认**：不撤销，已产生佣金归旧推荐人 |
| 2 | 企业版佣金冻结期间，推荐人的 `walletPending` 是否展示冻结金额？ | **已确认**：展示 pending+frozen 合计 |
| 3 | 个人版提现是否需要人工审核，还是自动打款？ | **已确认**：人工审核 |
| 4 | 企业版的被邀请用户（invitee）是否需要是该企业成员？ | **已确认**：不限制，任何用户均可被企业推广链接绑定 |
| 5 | 推广分享链接的落地页是否区分个人版和企业版？ | **已确认**：企业版链接携带 `eid`，个人版不带，系统根据是否携带 `eid` 自动判断 scope |
| 6 | 企业版分销的推荐人是否必须是该企业内部成员？ | **已确认**：必须限制。绑定接口需校验推荐人的 `wechat_users.enterpriseId === 请求中的 enterpriseId`，非该企业成员的分享链接不产生绑定关系，静默跳过 |
| 7 | A 分享给 B，B 购买个人版或企业版，A 都能得佣金吗？ | **已确认（v1.3）**：是。结算时先精确匹配订单 scope，未命中则回退查找 personal 绑定。无论 B 购买哪个版本，只要存在 A→B 的有效绑定（任意 scope），A 均可获得佣金。跨 scope 回退时使用个人版配置，佣金来源为平台 |
| 8 | 测试完成佣金适用于企业版测试吗？ | **已确认（v1.4）**：不适用。测试完成佣金仅适用于 personal scope 绑定，企业版测试（`testScope=enterprise`）不触发此规则 |
| 9 | commission_records 需要哪些新字段支持测试完成佣金？ | **已确认（v1.4）**：需新增 `testResultId INT NULL`（关联 test_results.id）和 `commissionSource VARCHAR(20) DEFAULT 'payment'`（payment \| test_completion）字段，并在 `testResultId` + `commissionSource` 上加唯一防重索引 |

---

## 八、数据库迁移（v1.4）

测试完成佣金需要对 `commission_records` 表新增两个字段，**上线前需执行**：

```sql
ALTER TABLE `mbti_commission_records`
  ADD COLUMN `testResultId`     int(11)      NULL DEFAULT NULL COMMENT '测试结果ID（测试完成佣金使用，关联 test_results.id）',
  ADD COLUMN `commissionSource` varchar(20)  NOT NULL DEFAULT 'payment' COMMENT '佣金来源: payment|test_completion',
  ADD UNIQUE KEY `uk_test_commission` (`testResultId`, `commissionSource`);
```

---

## 九、现有代码与新功能的关系

| 现有内容 | 状态 | 处理方式 |
|---------|------|---------|
| `mbti_distribution_agents` 表 | 旧设计，基于 `users` 表 | 废弃，改为直接用 `wechat_users` |
| `mbti_commission_records` 表 | 部分字段可复用 | 按第三节 ALTER 改造 |
| `admin/Distribution.php` | 骨架存在，逻辑空 | 在新设计基础上重写 |
| `miniprogram/pages/promo/index` | 推广中心UI已完成 | 对接 `/api/distribution/stats` 和 `/api/distribution/bindings` |
| `utils/share.js` 中携带 `uid` 参数 | 已实现 | 直接复用，`uid` 即推荐人ID |
| 小程序 `onLoad` 解析 `uid` 参数 | 尚未对接绑定 API | 需在各落地页调用 `POST /api/distribution/bind` |
