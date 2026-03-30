-- 企业权限字段：JSON 存储各功能开关（face/mbti/pdp/disc/distribution）
-- 默认全开，未设置时后端视为全部允许
ALTER TABLE `mbti_enterprises`
  ADD COLUMN `permissions` json NULL COMMENT '功能权限开关 {"face":true,"mbti":true,"pdp":true,"disc":true,"distribution":true}'
  AFTER `status`;
