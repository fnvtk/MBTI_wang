-- 若线上 ai_messages 早于 isDegraded 字段创建，助手消息 INSERT 会失败 → 异步任务 error → 小程序「小神仙这边出了点状况」
-- 将表名前缀改为与 .env database.prefix 一致（示例为 mbti_）

ALTER TABLE `mbti_ai_messages`
  ADD COLUMN `isDegraded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否为降级兜底回答' AFTER `providerId`;
