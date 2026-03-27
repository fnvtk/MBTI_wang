-- 飞书获客 Webhook 去重表（前缀 mbti_ 与 .env DATABASE_PREFIX 一致）
CREATE TABLE IF NOT EXISTS `mbti_feishu_lead_dedup` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dedupKey` varchar(160) NOT NULL COMMENT '如 order_paid:123、phone_bind:456',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dedup_key` (`dedupKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='飞书获客推送幂等';

-- 可选：加速按用户查最近行为（若 idx_user_id 已存在会报错，忽略即可）
-- ALTER TABLE `mbti_analytics_events` ADD INDEX `idx_user_id` (`userId`);
