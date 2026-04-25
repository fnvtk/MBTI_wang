-- 企业合作模式配置 + 用户选择（ThinkPHP Db::name 无表前缀写法 → mbti_*）
-- 执行前确认 database.prefix 为 mbti_

CREATE TABLE IF NOT EXISTS `mbti_enterprise_cooperation_modes` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `enterpriseId`   int unsigned NOT NULL COMMENT '企业 ID',
  `modeCode`       varchar(32)  NOT NULL COMMENT 'salary|startup_equity|knowledge_pay',
  `enabled`        tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否对用户展示',
  `sortOrder`      int          NOT NULL DEFAULT 0,
  `title`          varchar(128) NOT NULL DEFAULT '',
  `description`    varchar(512) NULL DEFAULT NULL,
  `createdAt`      int unsigned NULL DEFAULT NULL,
  `updatedAt`      int unsigned NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ent_mode` (`enterpriseId`, `modeCode`),
  KEY `idx_enterpriseId` (`enterpriseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='企业合作模式配置';

CREATE TABLE IF NOT EXISTS `mbti_user_cooperation_choices` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `userId`         int unsigned NOT NULL COMMENT 'wechat_users.id',
  `enterpriseId`   int unsigned NOT NULL,
  `modeCode`       varchar(32)  NOT NULL,
  `chosenAt`       int unsigned NOT NULL,
  `updatedAt`      int unsigned NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_ent` (`userId`, `enterpriseId`),
  KEY `idx_enterpriseId` (`enterpriseId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户在企业下的合作模式选择';
