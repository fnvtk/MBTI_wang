-- 企业财务流水表
-- 用于记录企业余额充值、测试收入入账、佣金扣减等变动

CREATE TABLE IF NOT EXISTS `mbti_finance_records` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `type` varchar(20) NOT NULL COMMENT '类型: recharge/consume/refund',
  `amount` int(11) NOT NULL DEFAULT 0 COMMENT '金额（分）',
  `balanceBefore` int(11) NULL DEFAULT 0 COMMENT '操作前余额（分）',
  `balanceAfter` int(11) NULL DEFAULT 0 COMMENT '操作后余额（分）',
  `description` varchar(255) NULL DEFAULT NULL COMMENT '描述',
  `orderId` int(11) NULL DEFAULT NULL COMMENT '关联订单ID',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间（时间戳）',
  PRIMARY KEY (`id`),
  KEY `idx_enterpriseId` (`enterpriseId`),
  KEY `idx_type` (`type`),
  KEY `idx_createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务记录表';
