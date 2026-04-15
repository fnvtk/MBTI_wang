-- 多业务推送幂等去重表（前缀须与 .env DATABASE_PREFIX 一致，默认 mbti_）
-- 若库中尚无此表，可任选其一：
--   1）在 api 目录执行：php database/migrations/run_delivery_dedup.php（自动读前缀建表）
--   2）在数据库控制台执行本文件（或按需把表名前缀改成你的 DATABASE_PREFIX）
CREATE TABLE IF NOT EXISTS `mbti_delivery_dedup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) NOT NULL COMMENT '场景：feishu_lead=飞书获客；outbound_hook=通用出站 Webhook；扩展时新增枚举值',
  `dedupKey` varchar(255) NOT NULL COMMENT '该场景下幂等键（与 scene 联合唯一）；出站场景为 envelope._dedupKey 原值，不含 push_hook: 前缀',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scene_dedup` (`scene`, `dedupKey`),
  KEY `idx_scene` (`scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多业务推送幂等去重（飞书获客、出站 Webhook 等共用）';

-- 若已从旧版建过 `mbti_feishu_lead_dedup`（仅 dedupKey 无 scene），可迁移后删旧表（执行前请备份）：
-- INSERT IGNORE INTO `mbti_delivery_dedup` (`scene`, `dedupKey`, `createdAt`)
-- SELECT
--   CASE WHEN `dedupKey` LIKE 'push_hook:%' THEN 'outbound_hook' ELSE 'feishu_lead' END,
--   CASE WHEN `dedupKey` LIKE 'push_hook:%' THEN SUBSTRING(`dedupKey`, 11) ELSE `dedupKey` END,
--   `createdAt`
-- FROM `mbti_feishu_lead_dedup`;
-- DROP TABLE `mbti_feishu_lead_dedup`;

-- 若旧表仅有 dedupKey 160 字符等，可先 ALTER 再迁移；或先建新表再 INSERT IGNORE 如上。
