-- 小程序埋点表（与 mbti_ 表前缀一致；若 .env 中 DATABASE_PREFIX 不同，请同步改表名）
-- ThinkPHP：Db::name('analytics_events') + 前缀 mbti_ => mbti_analytics_events
CREATE TABLE IF NOT EXISTS `mbti_analytics_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(10) unsigned DEFAULT NULL COMMENT 'wechat_users.id / douyin_users.id',
  `openid` varchar(64) DEFAULT NULL,
  `eventName` varchar(128) NOT NULL DEFAULT '',
  `pagePath` varchar(255) DEFAULT NULL,
  `propsJson` text COMMENT 'JSON 字符串（含 _device/_network/_scene）',
  `clientTs` bigint(20) DEFAULT NULL COMMENT '客户端毫秒时间戳',
  `platform` varchar(16) DEFAULT NULL COMMENT 'wechat / douyin',
  `sessionId` varchar(64) DEFAULT NULL COMMENT '单次启动的会话标识',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_created` (`eventName`,`createdAt`),
  KEY `idx_created` (`createdAt`),
  KEY `idx_platform` (`platform`,`createdAt`),
  KEY `idx_session` (`sessionId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小程序行为埋点';

-- 若已有表需加列，执行以下 ALTER（幂等写法）：
-- ALTER TABLE `mbti_analytics_events` ADD COLUMN `platform` varchar(16) DEFAULT NULL COMMENT 'wechat / douyin' AFTER `clientTs`;
-- ALTER TABLE `mbti_analytics_events` ADD COLUMN `sessionId` varchar(64) DEFAULT NULL COMMENT '单次启动的会话标识' AFTER `platform`;
-- ALTER TABLE `mbti_analytics_events` ADD INDEX `idx_platform` (`platform`,`createdAt`);
-- ALTER TABLE `mbti_analytics_events` ADD INDEX `idx_session` (`sessionId`);
