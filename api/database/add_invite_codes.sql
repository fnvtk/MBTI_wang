-- 邀请码 → 推广员（inviterId），供小程序填码绑定分销关系
-- 执行前请将表名前缀与 api/config/database.php 中 prefix 对齐（ThinkPHP Db::name('invite_codes') → {prefix}invite_codes）

CREATE TABLE IF NOT EXISTS `mbti_invite_codes` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `code`           varchar(64)  NOT NULL COMMENT '邀请码（仅存大写字母数字）',
  `inviterId`      int unsigned NOT NULL COMMENT '推广员 wechat_users.id',
  `enterpriseId`   int unsigned NULL DEFAULT NULL COMMENT '企业上下文，与个人版并行时可空',
  `status`         varchar(20)  NOT NULL DEFAULT 'active' COMMENT 'active|disabled',
  `expiresAt`      int unsigned NULL DEFAULT NULL COMMENT '过期unix时间戳，NULL=不限',
  `remark`         varchar(255) NULL DEFAULT NULL COMMENT '备注',
  `createdAt`      int unsigned NULL DEFAULT NULL,
  `updatedAt`      int unsigned NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_inviterId` (`inviterId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小程序填写邀请码解析用';
