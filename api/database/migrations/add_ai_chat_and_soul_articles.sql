-- AI 聊天 + Soul 文章推荐 + 欠费预警字段
-- 对应需求：ai功能202600417 -6.md
-- 建议部署时用 source 一次性执行；前缀 mbti_ 按环境 .env DATABASE_PREFIX 调整

-- 1) ai_providers 新增排序权重（故障切换时可手动调优先级）
ALTER TABLE `mbti_ai_providers`
  ADD COLUMN `sortWeight` INT NOT NULL DEFAULT 100 COMMENT '优先级,升序;欠费自动降到999' AFTER `visible`;

-- 2) AI 对话表
CREATE TABLE IF NOT EXISTS `mbti_ai_conversations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `userId` INT UNSIGNED NOT NULL,
  `title` VARCHAR(128) NOT NULL DEFAULT '',
  `mbtiType` VARCHAR(16) NOT NULL DEFAULT '',
  `providerId` VARCHAR(32) NOT NULL DEFAULT '',
  `lastMessageAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `messageCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `createdAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `deletedAt` INT UNSIGNED NULL,
  INDEX `idx_user` (`userId`, `updatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='神仙 AI 对话';

-- 3) AI 消息表
CREATE TABLE IF NOT EXISTS `mbti_ai_messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `conversationId` INT UNSIGNED NOT NULL,
  `role` ENUM('system','user','assistant') NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `tokensIn` INT UNSIGNED NOT NULL DEFAULT 0,
  `tokensOut` INT UNSIGNED NOT NULL DEFAULT 0,
  `providerId` VARCHAR(32) NOT NULL DEFAULT '',
  `isDegraded` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否为降级兜底回答',
  `createdAt` INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX `idx_conv` (`conversationId`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='神仙 AI 消息流';

-- 4) Soul 采集文章表（一场 soul 创业实验 引流内容池）
CREATE TABLE IF NOT EXISTS `mbti_soul_articles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sourceId` VARCHAR(64) NOT NULL COMMENT 'soul 侧文章 id',
  `title` VARCHAR(255) NOT NULL,
  `cover` VARCHAR(512) NOT NULL DEFAULT '',
  `url` VARCHAR(512) NOT NULL,
  `summary` VARCHAR(512) NOT NULL DEFAULT '',
  `author` VARCHAR(64) NOT NULL DEFAULT '',
  `tag` VARCHAR(32) NOT NULL DEFAULT 'MBTI',
  `publishedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `isRecommended` TINYINT(1) NOT NULL DEFAULT 0,
  `recommendedOrder` INT NOT NULL DEFAULT 0,
  `viewCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `createdAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `deletedAt` INT UNSIGNED NULL,
  UNIQUE KEY `uk_source` (`sourceId`),
  INDEX `idx_reco` (`isRecommended`, `recommendedOrder`, `publishedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Soul 采集文章（MBTI 主题）';

-- 5) AI 使用限额（每日）—— 用户层面的限流记录
CREATE TABLE IF NOT EXISTS `mbti_ai_usage_daily` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `userId` INT UNSIGNED NOT NULL,
  `dateStr` CHAR(10) NOT NULL COMMENT '格式 YYYY-MM-DD',
  `messageCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `createdAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uk_user_date` (`userId`, `dateStr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='神仙 AI 每日限流计数';

-- 6) AI 余额预警日志（去重同一阈值每天只发 1 次）
CREATE TABLE IF NOT EXISTS `mbti_ai_balance_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `providerId` VARCHAR(32) NOT NULL,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `threshold` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'CNY',
  `alertedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `dateStr` CHAR(10) NOT NULL,
  UNIQUE KEY `uk_provider_date` (`providerId`, `dateStr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI 欠费预警去重日志';
