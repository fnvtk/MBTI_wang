-- ============================================================
-- 分销系统数据库迁移（兼容 MySQL 5.7+）
-- 版本: v1.1  日期: 2026-03-04
-- 执行方式：直接在数据库客户端或命令行运行此文件
-- ============================================================

-- ----------------------------
-- 1. 新建分销绑定记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `mbti_distribution_bindings` (
  `id`            int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '绑定ID',
  `inviterId`     int(11) NOT NULL COMMENT '当前推荐人ID (wechat_users.id)',
  `inviteeId`     int(11) NOT NULL COMMENT '被推荐人ID (wechat_users.id)',
  `scope`         varchar(20) NOT NULL DEFAULT 'personal' COMMENT '分销维度: personal|enterprise',
  `enterpriseId`  int(11) NULL DEFAULT NULL COMMENT '企业ID（企业版时非空）',
  `expireAt`      int(11) NOT NULL COMMENT '绑定过期时间戳（绑定时间+30天）',
  `status`        varchar(20) NOT NULL DEFAULT 'active' COMMENT '状态: active/expired/overridden',
  `prevInviterId` int(11) NULL DEFAULT NULL COMMENT '被抢绑前的推荐人ID',
  `overriddenAt`  int(11) NULL DEFAULT NULL COMMENT '被覆盖时间（抢绑时记录）',
  `createdAt`     int(11) NULL DEFAULT NULL COMMENT '绑定创建时间',
  `updatedAt`     int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invitee_scope_ent` (`inviteeId`, `scope`, `enterpriseId`),
  INDEX `idx_inviterId`     (`inviterId`),
  INDEX `idx_inviteeId`     (`inviteeId`),
  INDEX `idx_prevInviterId` (`prevInviterId`),
  INDEX `idx_expireAt`      (`expireAt`),
  INDEX `idx_status`        (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销绑定记录表';

-- ----------------------------
-- 2. 新建分销提现记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `mbti_distribution_withdrawals` (
  `id`            int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId`        int(11) NOT NULL COMMENT '推荐人 wechat_users.id',
  `amountFen`     int(11) NOT NULL COMMENT '申请提现金额（分）',
  `realNameInfo`  varchar(500) NULL DEFAULT NULL COMMENT '收款实名信息 JSON',
  `status`        varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected/transferred',
  `auditNote`     varchar(500) NULL DEFAULT NULL COMMENT '审核备注',
  `auditAt`       int(11) NULL DEFAULT NULL COMMENT '审核时间',
  `transferAt`    int(11) NULL DEFAULT NULL COMMENT '打款时间',
  `createdAt`     int(11) NULL DEFAULT NULL,
  `updatedAt`     int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_userId`  (`userId`),
  INDEX `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分销提现记录表';

-- ----------------------------
-- 3. wechat_users 增加钱包字段（逐列判断，MySQL 5.7 兼容）
-- ----------------------------
SET @dbname = DATABASE();

-- walletBalance
SET @col = 'walletBalance';
SET @tbl = 'mbti_wechat_users';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `walletBalance` int(11) NOT NULL DEFAULT 0 COMMENT ''钱包余额（分）'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- walletTotalEarned
SET @col = 'walletTotalEarned';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `walletTotalEarned` int(11) NOT NULL DEFAULT 0 COMMENT ''历史累计佣金（分）'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- walletPending
SET @col = 'walletPending';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `walletPending` int(11) NOT NULL DEFAULT 0 COMMENT ''待入账佣金（分）'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------
-- 4. commission_records 增加分销字段（逐列判断）
-- ----------------------------
SET @tbl = 'mbti_commission_records';

SET @col = 'scope';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `scope` varchar(20) NOT NULL DEFAULT ''personal'' COMMENT ''分销维度'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'enterpriseId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `enterpriseId` int(11) NULL DEFAULT NULL COMMENT ''企业ID'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'inviterId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `inviterId` int(11) NULL DEFAULT NULL COMMENT ''推荐人ID'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'inviteeId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `inviteeId` int(11) NULL DEFAULT NULL COMMENT ''付款用户ID'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'bindingId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `bindingId` int(11) NULL DEFAULT NULL COMMENT ''绑定记录ID'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'orderAmount';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `orderAmount` int(11) NOT NULL DEFAULT 0 COMMENT ''订单金额（分）'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'commissionFen';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `commissionFen` int(11) NOT NULL DEFAULT 0 COMMENT ''佣金金额（分）'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'frozenAt';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `frozenAt` int(11) NULL DEFAULT NULL COMMENT ''冻结时间'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = 'unfrozenAt';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `unfrozenAt` int(11) NULL DEFAULT NULL COMMENT ''解冻时间'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status 字段类型修改
ALTER TABLE `mbti_commission_records`
  MODIFY COLUMN `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/paid/frozen/cancelled';

-- ----------------------------
-- 5. 补充索引（忽略已存在的，用 CREATE INDEX 方式）
-- ----------------------------
SET @tbl = 'mbti_commission_records';

SET @idx = 'idx_scope';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = @idx) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `idx_scope` (`scope`)'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_dist_enterpriseId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = @idx) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `idx_dist_enterpriseId` (`enterpriseId`)'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = 'idx_inviterId';
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = @idx) = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD INDEX `idx_inviterId` (`inviterId`)'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
