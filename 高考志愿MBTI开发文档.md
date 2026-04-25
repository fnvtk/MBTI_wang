# 高考志愿 MBTI 功能开发文档（V1）

## 1. 文档目标

基于 `测高考.txt` 的会议纪要，整理一版可直接执行的开发文档，覆盖：

- 业务目标与范围
- 前后端功能拆解
- AI 分析输入输出规范
- 提示词与规则（AI 可直接理解）
- 里程碑与验收标准

---

## 2. 背景与业务目标

高考季上线「高考志愿分析」专栏，结合考生的性格测评与分数信息，输出可读、可执行、可追溯的志愿建议，支持后续与教育机构合作场景（客资沉淀、加微信、入群转化）。

核心目标：

1. 给考生与家长一个“先有方向”的初版建议（不是最终填报结论）。
2. 让机构老师可基于报告继续人工精细化服务。
3. 保持与现有团队版产品架构一致，支持按公司配置切换首页主入口。

---

## 3. 范围定义

## 3.1 本期（V1）要做

- 新增**独立高考志愿入口**（不是复用原团队入口），首页可配置为高考版优先。
- 点击该入口后进入「高考任务中心页」，集中展示：
  - MBTI 测试入口
  - PDP 测试入口
  - DISC 测试入口
  - 高考信息表单入口
  - 综合分析按钮
- 收集基础信息：姓名、地区、科类/选科、估分/模拟分（可选）、志愿草表（可选文本或图片 OCR）。
- 复用现有性格测评体系，取结果做综合分析：
  - MBTI（必用）
  - PDP（用）
  - DISC（用）
  - 面相分析（如已有能力可融合）
- 生成综合报告：学校梯度（冲/稳/保）+ 专业匹配 + 风险提示 + 下一步动作。
- 支持机构合作链路：报告后提示加微信/进群（已有能力对接）。

## 3.2 本期不做

- 直接替代人工老师完成最终志愿填报。
- 复杂到省级全量规则引擎（如超细颗粒组合政策全自动推演）。
- 多轮深度问答式咨询（先提供单次报告）。

---

## 4. 用户流程（V1）

1. 用户点击首页「高考志愿」新入口，进入任务中心页。
2. 任务中心展示 4 个任务入口（MBTI、PDP、DISC、高考信息表单）+ 1 个综合分析按钮。
3. 用户按任意顺序完成测试与表单。
4. 任务中心实时显示每个任务状态：
   - 未完成：显示“未完成/去测试”提示；
   - 已完成：显示“已完成/查看结果”并可点击查看已有结果。
5. 仅当 MBTI、PDP、DISC 和表单全部完成时，才可点击「AI 综合分析」。
6. 未完成前，综合分析按钮保持灰色禁用状态，并提示缺失项（如“请先完成 DISC 与高考信息表单”）。
7. 全部完成后按钮点亮，点击后进入 AI 分析流程。
8. AI 先联网检索当年权威数据，再生成 JSON 报告并渲染结果页。
9. 报告末尾引导加微信/进群（机构合作链路）。

---

## 5. 产品与交互要求

## 5.1 首页与入口

- 支持“固定首页”能力：按租户/公司配置，首页主入口可切为高考版或团队版。
- 支持可见性配置：个人版入口可显示或隐藏。
- 高考志愿必须为单独入口，命名建议：`高考志愿分析`（可配置文案）。

## 5.2 表单字段（建议）

必填：

- 姓名
- 省份
- 科类（新高考省份可改为选科组合）
- 至少一个有效测评结果（MBTI 必须有）

选填（V1 建议都可选，避免流失）：

- 估分/模拟成绩
- 语数英及综合分项
- 志愿草表文本
- 志愿草表图片 OCR
- 意向地区
- 意向专业大类

## 5.3 任务中心状态与按钮规则（新增）

任务中心卡片状态定义：

- `todo`（未完成）：文案“去完成”，卡片右侧显示提示点。
- `done`（已完成）：文案“查看结果”，卡片右侧显示完成标识。

综合分析按钮状态：

- 默认灰色禁用：存在任一 `todo` 时保持禁用；
- 激活可点击：4 个任务全部 `done` 后点亮；
- 点击禁用按钮时需给出明确提示：列出未完成项名称，不可只提示“请先完成”。

任务入口点击行为：

- 未完成任务：进入对应测试/表单页面；
- 已完成任务：进入对应结果页或回填编辑页（表单可编辑再保存）；
- 测试结果以“最近一次有效提交”为准。

## 5.4 分析结果页

必须包含：

- 总评（120~200 字）
- 性格画像摘要
- 分数定位（区间表达）
- 学校推荐：冲/稳/保（每档建议 3 所）
- 专业推荐（建议 5 个）
- 志愿草表点评（若用户提供）
- 风险与免责声明
- 后续行动建议（学生 3 条 + 家长 3 条）

## 5.5 全局定价（新增）

高考志愿功能需纳入全局定价体系，支持按租户统一配置，不在页面写死价格。

- 定价对象：
  - `gaokao_single_report`（单次综合分析）
  - `gaokao_package_basic`（基础包，可含测评 + 1 次综合分析）
  - `gaokao_package_pro`（进阶包，可含报告解读服务）
- 定价维度：
  - 原价、活动价、渠道价（可选）
  - 生效时间、失效时间
  - 适用租户（平台默认/企业覆盖）
- 价格展示规则：
  - 任务中心和支付确认页展示同一价格源；
  - 若有活动价，显示“划线原价 + 活动价”；
  - 若未配置价格，不允许下单并给出配置缺失提示。

## 5.6 分销能力（新增）

高考志愿功能接入分销体系，支持渠道推广和佣金结算。

- 分销基础：
  - 支持分享入口（海报/链接）携带 `referrerId`；
  - 用户首次进入高考入口时绑定分销关系（按现有平台规则）。
- 佣金规则：
  - 支持按商品维度配置佣金比例或固定金额；
  - 支持一级分销（V1 必做），多级分销后续迭代；
  - 退款后佣金自动冲正。
- 订单归因：
  - 高考相关订单需记录来源渠道、分销员、归因时间；
  - 用户管理后台可按分销员查看转化人数、支付金额、佣金金额。

## 5.7 用户管理展示（新增）

用户管理后台需新增“高考志愿”视图与字段，便于机构跟进。

- 列表新增字段：
  - 高考入口状态（未进入/进行中/已完成）
  - 测试完成状态（MBTI、PDP、DISC）
  - 表单完成状态
  - 综合分析状态（未生成/已生成/生成失败）
  - 最近分析时间
  - 报告摘要标签（如“工科倾向”“省内优先”）
- 详情页新增模块：
  - 最近一次综合分析结果（可查看摘要和完整 JSON）
  - 推荐学校/专业快照
  - 风险提示与免责声明
  - 分销归因与订单记录（来源、成交、佣金）
- 筛选与导出：
  - 支持按“是否完成综合分析”“是否付费”“分销员”筛选；
  - 支持导出高考用户跟进清单（CSV/Excel）。

---

## 6. AI 生成规则（必须执行）

本节是给模型与后端共同遵守的“硬规则”。

1. 命中率只能给区间（如 `10-25%`），不能给点估。
2. 严禁“保录”“稳上”“一定能进”。
3. 所有学校/专业推荐都要有理由，至少包含：分数梯度、性格匹配、地域或就业其一。
4. MBTI 解释至少覆盖四维中的 3 维：I/E、N/S、T/F、J/P。
5. 若有志愿草表，点评需引用用户原文不少于 3 条。
6. 输出必须是严格 JSON（不能返回 Markdown 包裹）。
7. 缺失信息必须留空（空字符串或空数组），不能编造。
8. 先检索再结论：每次调用至少执行 5 组当年检索。
9. 每个关键结论都要附 `sources`（标题、URL、时间、摘要）。
10. 检索失败时必须显式降级，降低置信区间并写明“仅供方向参考”。

---

## 7. AI 可直接使用的提示词模板

以下模板可直接放入后端 `system` / `user` 消息中，已做工程化约束。

## 7.1 System Prompt（精简可执行版）

```text
你是高考志愿AI分析师。你的任务是结合考生测评结果、分数信息和志愿草表，输出一份严格JSON格式的志愿建议。

硬性规则：
1) 任何录取概率都使用区间，如10-25%，禁止点估；
2) 禁止“保录取/一定录取/稳上”等承诺语；
3) 学校与专业推荐必须说明理由（分数梯度+性格匹配+就业/地域）；
4) MBTI解释至少覆盖I/E、N/S、T/F、J/P中的3个维度；
5) 若用户提供志愿草表，wishReview.evidence至少3条用户原文；
6) 输出必须是严格JSON，不要Markdown、不要注释；
7) 缺失数据留空，禁止编造；
8) 生成结论前必须联网检索当年高考/招生/专业/就业数据，至少5组query；
9) 关键结论必须带sources（title,url,publishedAt,snippet）；
10) 检索失败时，必须降级：schoolRecommend置空或降档，并在sources中写note说明。

语气要求：理性、克制、有温度；结论是“建议”而非“承诺”。
```

## 7.2 User Prompt（模板）

```text
请基于以下考生数据生成高考志愿分析：

【性格测评】
MBTI: {{mbti}}
PDP: {{pdp}}
DISC: {{disc}}
面相分析: {{faceText}}

【基础信息】
姓名: {{name}}
省份: {{province}}
科类/选科: {{streamOrSubjects}}
目标层次: {{targetTier}}
意向地区: {{preferredRegions}}
意向专业: {{preferredFields}}

【成绩信息】
估分: {{estimatedScore}}
分数文本: {{scoreText}}
分数OCR: {{scoreImagesOcr}}

【志愿草表】
文本: {{wishListText}}
OCR: {{wishListOcr}}

请严格输出JSON，结构必须完整，缺失字段用空值，不要编造数据。
```

---

## 8. 输出 JSON 结构（建议标准）

```json
{
  "overview": "",
  "personalityProfile": {
    "mbti": "",
    "pdp": "",
    "disc": "",
    "face": ""
  },
  "scoreProfile": {
    "estimated": null,
    "tierFit": "",
    "percentileGuess": "",
    "sources": []
  },
  "schoolRecommend": {
    "chong": [],
    "wen": [],
    "bao": []
  },
  "majorRecommend": [],
  "wishReview": {
    "strengths": [],
    "risks": [],
    "rebalance": [],
    "evidence": []
  },
  "personalityReason": "",
  "nextSteps": [],
  "disclaimers": "",
  "searchMeta": {
    "queryCount": 0,
    "queries": [],
    "fetchedAt": "",
    "coverage": "full"
  }
}
```

---

## 9. 检索策略（后端/Agent）

每次分析必须新检索，不复用历史。

最低 5 组 query（示例）：

1. `{{year}} {{province}} 高考 一分一段 投档线`
2. `{{schoolName}} {{province}} 近三年 投档线 位次`
3. `{{majorName}} 就业率 学科评估 {{year}}`
4. `{{subjects}} 选科要求 专业目录 {{year}}`
5. `{{targetTier}} {{preferredRegions}} 大学名单 {{year}}`

信源优先级：

- 高：`gov.cn`、`edu.cn`、阳光高考、学校招生网
- 中：主流媒体教育频道、官方报告
- 低：聚合站/转载站（仅兜底，不作为主依据）

---

## 10. 接口与工程建议

## 10.1 后端接口（建议）

- `POST /api/gaokao/analyze`
  - 入参：表单 + 测评结果 + 可选 OCR 文本
  - 出参：标准 JSON 报告
- `GET /api/gaokao/pricing`
  - 入参：租户、渠道、用户身份
  - 出参：高考功能可售商品与当前生效价格
- `POST /api/gaokao/order/create`
  - 入参：商品、支付方式、分销归因参数
  - 出参：订单信息与支付参数
- `GET /api/admin/gaokao/users`
  - 入参：状态筛选（分析状态/付费状态/分销员）
  - 出参：高考用户列表及任务完成状态
- `GET /api/admin/gaokao/users/{id}`
  - 出参：用户高考档案、报告摘要、分销与订单信息

## 10.2 关键工程约束

- 开启模型工具调用（`web_search` 或自建搜索代理）。
- 强制 `response_format = json_object`（按服务商能力适配）。
- 返回前执行结构校验与规则校验，不通过则重试或降级。
- 定价与分销统一走平台全局配置中心，禁止各端写死。
- 价格读取、下单、支付回调、佣金结算使用同一商品编码，避免账务不一致。

---

## 11. 验收标准（UAT）

功能验收：

- 能完整跑通“新入口 -> 任务中心 -> 测试/表单 -> 分析 -> 报告 -> 加微信引导”链路。
- 在信息不全场景下仍能返回可读报告，不报错。
- 任务中心状态正确：未完成任务有提示，已完成任务可查看结果。
- 综合分析按钮规则正确：未完成时灰色禁用，全部完成后可点击。
- 全局定价生效正确：不同租户/渠道命中对应价格，前后端展示一致。
- 分销归因正确：分享进入、下单、退款、佣金冲正链路可核对。
- 用户管理可见高考结果：列表与详情可查看任务状态和分析结果摘要。

质量验收：

- JSON 100% 可解析。
- 检索覆盖：`searchMeta.queryCount >= 5`（或 `coverage=none` 且走降级）。
- 概率全部为区间，且无承诺词。
- 学校与专业条目均可追溯到来源。

业务验收：

- 报告可供机构老师二次解读，不与人工流程冲突。
- 能体现“初版可用，后续可迭代”。

---

## 12. 版本与迭代建议

- V1：先上线可用版（本文件范围）。
- V1.1：补省份规则细化（3+1+2 / 3+3 全量映射）。
- V1.2：加入“按机构策略模板”输出（不同机构不同话术与重点）。

---

## 13. 数据库表结构草案（可直接给研发）

以下为 V1 建议的最小可用表结构，命名可按现有项目规范调整。

## 13.1 高考商品定价表 `gaokao_pricing`

用途：高考相关商品的全局定价配置（支持租户覆盖、时间生效）。

核心字段：

- `id` bigint PK
- `tenant_id` bigint，租户 ID（`0` 表示平台默认）
- `product_code` varchar(64)，如 `gaokao_single_report`
- `product_name` varchar(100)
- `price_original` decimal(10,2)
- `price_sale` decimal(10,2)
- `price_channel` decimal(10,2) NULL
- `currency` varchar(16) DEFAULT `CNY`
- `status` tinyint（0=停用，1=启用）
- `effective_at` datetime
- `expired_at` datetime NULL
- `extra_json` json NULL（活动标签、展示文案等）
- `created_at` datetime
- `updated_at` datetime

索引建议：

- `idx_tenant_product_status` (`tenant_id`, `product_code`, `status`)
- `idx_effective_time` (`effective_at`, `expired_at`)
- 唯一约束（可选）：同租户同商品同时间段不允许重叠生效

## 13.2 高考用户档案表 `gaokao_user_profile`

用途：存储用户在高考功能内的进度状态、表单信息和最新报告指针。

核心字段：

- `id` bigint PK
- `user_id` bigint UNIQUE
- `tenant_id` bigint
- `entry_status` tinyint（0=未进入，1=进行中，2=已完成）
- `mbti_status` tinyint（0=未测，1=已测）
- `pdp_status` tinyint（0=未测，1=已测）
- `disc_status` tinyint（0=未测，1=已测）
- `form_status` tinyint（0=未填，1=已填）
- `analyze_status` tinyint（0=未生成，1=已生成，2=失败）
- `last_analyze_at` datetime NULL
- `latest_report_id` bigint NULL（关联报告表）
- `name` varchar(64)
- `province` varchar(32)
- `stream_or_subjects` varchar(128)
- `estimated_score` int NULL
- `form_json` json NULL（完整表单回填）
- `tags_json` json NULL（如“工科倾向”“省内优先”）
- `created_at` datetime
- `updated_at` datetime

索引建议：

- `uk_user_id` (`user_id`)
- `idx_tenant_status` (`tenant_id`, `entry_status`, `analyze_status`)
- `idx_last_analyze_at` (`last_analyze_at`)

## 13.3 高考分析报告表 `gaokao_report`

用途：保存每次 AI 分析结果与来源信息，支持后台查看和审计追溯。

核心字段：

- `id` bigint PK
- `user_id` bigint
- `tenant_id` bigint
- `version` varchar(20)（prompt/version）
- `input_snapshot_json` json（入参快照）
- `report_json` json（完整输出）
- `overview` text
- `search_meta_json` json
- `status` tinyint（0=失败，1=成功）
- `error_msg` varchar(500) NULL
- `created_at` datetime

索引建议：

- `idx_user_created` (`user_id`, `created_at`)
- `idx_tenant_created` (`tenant_id`, `created_at`)

## 13.4 高考订单表 `gaokao_order`

用途：记录高考功能付费订单，与平台支付及分销结算对齐。

核心字段：

- `id` bigint PK
- `order_no` varchar(64) UNIQUE
- `user_id` bigint
- `tenant_id` bigint
- `product_code` varchar(64)
- `pricing_id` bigint
- `amount_original` decimal(10,2)
- `amount_payable` decimal(10,2)
- `amount_paid` decimal(10,2) NULL
- `currency` varchar(16)
- `pay_status` tinyint（0=待支付，1=已支付，2=已退款，3=关闭）
- `pay_channel` varchar(32)
- `paid_at` datetime NULL
- `refund_at` datetime NULL
- `ext_json` json NULL
- `created_at` datetime
- `updated_at` datetime

索引建议：

- `uk_order_no` (`order_no`)
- `idx_user_pay_status` (`user_id`, `pay_status`)
- `idx_tenant_created` (`tenant_id`, `created_at`)

## 13.5 分销归因表 `gaokao_distribution_attribution`

用途：记录用户来源、分销员关系、归因窗口，服务佣金计算。

核心字段：

- `id` bigint PK
- `user_id` bigint
- `tenant_id` bigint
- `referrer_user_id` bigint（分销员）
- `channel_code` varchar(64)（海报/链接/机构码）
- `scene` varchar(64)（share_link/poster/qr）
- `attributed_at` datetime
- `expire_at` datetime NULL（归因窗口）
- `is_locked` tinyint（0=可变更，1=锁定）
- `created_at` datetime
- `updated_at` datetime

索引建议：

- `idx_user_tenant` (`user_id`, `tenant_id`)
- `idx_referrer` (`referrer_user_id`, `created_at`)
- 唯一约束（建议）：同 `user_id + tenant_id` 仅保留 1 条生效归因

## 13.6 分销佣金流水表 `gaokao_distribution_commission`

用途：订单成交后记录应结/已结/冲正的佣金流水。

核心字段：

- `id` bigint PK
- `tenant_id` bigint
- `order_id` bigint
- `order_no` varchar(64)
- `user_id` bigint（购买用户）
- `referrer_user_id` bigint（分销员）
- `commission_rule_type` varchar(20)（ratio/fixed）
- `commission_rule_value` decimal(10,4)
- `commission_amount` decimal(10,2)
- `status` tinyint（0=待结算，1=已结算，2=已冲正）
- `settled_at` datetime NULL
- `reversed_at` datetime NULL
- `remark` varchar(255) NULL
- `created_at` datetime
- `updated_at` datetime

索引建议：

- `idx_referrer_status` (`referrer_user_id`, `status`)
- `idx_order_id` (`order_id`)
- `idx_tenant_created` (`tenant_id`, `created_at`)

## 13.7 用户管理聚合查询建议（非新表）

后台“高考用户管理”建议通过以下聚合视图/查询实现：

- 主表：`gaokao_user_profile`
- 左连接：`gaokao_report`（最新一条）
- 左连接：`gaokao_order`（最近支付状态）
- 左连接：`gaokao_distribution_attribution` + `gaokao_distribution_commission`

建议输出字段：

- 用户基础信息 + 任务完成状态 + 最近分析时间
- 报告摘要（overview）与标签（tags_json）
- 订单金额/支付状态
- 分销员/渠道/累计佣金

---

## 14. 与现有提示词文件关系

你提供的 `高考志愿MBTI推荐_prompt.md` 已非常完整，建议作为“详细版 Prompt 规范”；本文件作为“开发执行版 PRD + Prompt 摘要规范”。

推荐落地方式：

1. 本文件给产品/前后端/测试对齐需求。
2. `高考志愿MBTI推荐_prompt.md` 作为 AI 服务最终系统提示词来源。
3. 后端将两者版本号写入 runtime 配置，便于追踪效果。
