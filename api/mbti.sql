/*
 Navicat Premium Data Transfer

 Source Server         : kr_存客宝
 Source Server Type    : MySQL
 Source Server Version : 50736
 Source Host           : 56b4c23f6853c.gz.cdb.myqcloud.com:14413
 Source Schema         : mbti

 Target Server Type    : MySQL
 Target Server Version : 50736
 File Encoding         : 65001

 Date: 12/03/2026 17:13:09
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for mbti_activities
-- ----------------------------
DROP TABLE IF EXISTS `mbti_activities`;
CREATE TABLE `mbti_activities`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '活动ID',
  `userId` int(11) NULL DEFAULT NULL COMMENT '用户ID',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '活动类型',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作描述',
  `relatedId` int(11) NULL DEFAULT NULL COMMENT '关联ID',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_userId`(`userId`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '活动记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_ai_providers
-- ----------------------------
DROP TABLE IF EXISTS `mbti_ai_providers`;
CREATE TABLE `mbti_ai_providers`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `providerId` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '服务商ID:openai/anthropic/deepseek等',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '服务商名称',
  `enabled` tinyint(1) NULL DEFAULT 0 COMMENT '是否启用:1启用,0禁用',
  `visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否在列表中显示:1显示,0隐藏',
  `apiKey` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'API密钥(加密存储)',
  `apiEndpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'API端点',
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '默认模型',
  `organizationId` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Organization ID(OpenAI专用)',
  `maxTokens` int(11) NULL DEFAULT 4096 COMMENT '最大Token数',
  `balanceAlertEnabled` tinyint(1) NULL DEFAULT 0 COMMENT '余额告警是否启用',
  `balanceAlertThreshold` decimal(10, 2) NULL DEFAULT 10.00 COMMENT '余额告警阈值',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
  `docUrl` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文档/API Key申请链接',
  `isFree` tinyint(1) NULL DEFAULT 0 COMMENT '是否免费额度服务商',
  `supportsBalance` tinyint(1) NULL DEFAULT 1 COMMENT '是否支持余额查询',
  `lastBalance` decimal(10, 2) NULL DEFAULT NULL COMMENT '最后查询的余额',
  `lastBalanceCurrency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '余额币种:CNY/USD',
  `lastBalanceCheckedAt` int(11) NULL DEFAULT NULL COMMENT '最后余额查询时间(时间戳)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  `deletedAt` int(11) NULL DEFAULT NULL COMMENT '软删除时间戳，有值表示已删除',
  `extraConfig` json NULL COMMENT '其他配置参数(JSON)，方便扩展接收其他参数',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_providerId`(`providerId`) USING BTREE,
  INDEX `idx_enabled`(`enabled`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'AI服务商配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_backup_records
-- ----------------------------
DROP TABLE IF EXISTS `mbti_backup_records`;
CREATE TABLE `mbti_backup_records`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '备份文件名',
  `filepath` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '本地文件路径',
  `fileSize` bigint(20) NULL DEFAULT 0 COMMENT '文件大小(字节)',
  `ossUrl` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'OSS访问URL',
  `ossPath` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'OSS对象路径',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'success' COMMENT '状态:success成功/failed失败',
  `deletedAt` int(11) NULL DEFAULT NULL COMMENT '删除时间(时间戳，NULL表示未删除)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_filename`(`filename`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_deletedAt`(`deletedAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数据库备份记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_commission_records
-- ----------------------------
DROP TABLE IF EXISTS `mbti_commission_records`;
CREATE TABLE `mbti_commission_records`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `agentId` int(11) NOT NULL COMMENT '分销商ID',
  `orderId` int(11) NULL DEFAULT NULL COMMENT '订单ID',
  `commissionRate` decimal(5, 2) NULL DEFAULT 0.00 COMMENT '佣金比例(%)',
  `commissionAmount` decimal(10, 2) NOT NULL COMMENT '佣金金额',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/paid/frozen/cancelled',
  `paidAt` int(11) NULL DEFAULT NULL COMMENT '支付时间(时间戳)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  `scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal' COMMENT '分销维度',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `inviterId` int(11) NULL DEFAULT NULL COMMENT '推荐人ID',
  `inviteeId` int(11) NULL DEFAULT NULL COMMENT '付款用户ID',
  `bindingId` int(11) NULL DEFAULT NULL COMMENT '绑定记录ID',
  `orderAmount` int(11) NOT NULL DEFAULT 0 COMMENT '订单金额（分）',
  `commissionFen` int(11) NOT NULL DEFAULT 0 COMMENT '佣金金额（分）',
  `frozenAt` int(11) NULL DEFAULT NULL COMMENT '冻结时间',
  `unfrozenAt` int(11) NULL DEFAULT NULL COMMENT '解冻时间',
  `testResultId` int(11) NULL DEFAULT NULL,
  `commissionSource` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_test_commission`(`testResultId`, `commissionSource`) USING BTREE,
  INDEX `idx_agentId`(`agentId`) USING BTREE,
  INDEX `idx_orderId`(`orderId`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_scope`(`scope`) USING BTREE,
  INDEX `idx_dist_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_inviterId`(`inviterId`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 46 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '佣金记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_distribution_agents
-- ----------------------------
DROP TABLE IF EXISTS `mbti_distribution_agents`;
CREATE TABLE `mbti_distribution_agents`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分销商ID',
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `agentName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分销商名称',
  `contactPhone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系电话',
  `contactEmail` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系邮箱',
  `totalOrders` int(11) NULL DEFAULT 0 COMMENT '总订单数',
  `totalCommission` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '总佣金',
  `availableCommission` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '可提现佣金',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:1正常,0禁用',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_userId`(`userId`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '分销商表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_distribution_bindings
-- ----------------------------
DROP TABLE IF EXISTS `mbti_distribution_bindings`;
CREATE TABLE `mbti_distribution_bindings`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '绑定ID',
  `inviterId` int(11) NOT NULL COMMENT '当前推荐人ID (wechat_users.id)',
  `inviteeId` int(11) NOT NULL COMMENT '被推荐人ID (wechat_users.id)',
  `scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal' COMMENT '分销维度: personal|enterprise',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID（企业版时非空）',
  `expireAt` int(11) NOT NULL COMMENT '绑定过期时间戳（绑定时间+30天）',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态: active/expired/overridden',
  `prevInviterId` int(11) NULL DEFAULT NULL COMMENT '被抢绑前的推荐人ID',
  `overriddenAt` int(11) NULL DEFAULT NULL COMMENT '被覆盖时间（抢绑时记录）',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '绑定创建时间',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_invitee_scope_ent`(`inviteeId`, `scope`, `enterpriseId`) USING BTREE,
  INDEX `idx_inviterId`(`inviterId`) USING BTREE,
  INDEX `idx_inviteeId`(`inviteeId`) USING BTREE,
  INDEX `idx_prevInviterId`(`prevInviterId`) USING BTREE,
  INDEX `idx_expireAt`(`expireAt`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '分销绑定记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_distribution_withdrawals
-- ----------------------------
DROP TABLE IF EXISTS `mbti_distribution_withdrawals`;
CREATE TABLE `mbti_distribution_withdrawals`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL COMMENT '推荐人 wechat_users.id',
  `amountFen` int(11) NOT NULL COMMENT '申请提现金额（分）',
  `feeFen` int(11) NOT NULL DEFAULT 0 COMMENT '手续费（分）',
  `realNameInfo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '收款实名信息 JSON',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected/transferred',
  `auditNote` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '审核备注',
  `auditAt` int(11) NULL DEFAULT NULL COMMENT '审核时间',
  `transferAt` int(11) NULL DEFAULT NULL COMMENT '打款时间',
  `createdAt` int(11) NULL DEFAULT NULL,
  `updatedAt` int(11) NULL DEFAULT NULL,
  `pay_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'wechat' COMMENT '支付方式（wechat=微信支付，offline=线下）',
  `out_bill_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '商户明细单号（TX+id）',
  `transfer_bill_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信转账单号',
  `wechat_pay_state` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信转账状态（SUCCESS/PROCESSING/FAIL 等）',
  `transfer_scene_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '1005' COMMENT '转账场景ID',
  `mch_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '付款商户号',
  `package_info` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '微信支付package信息（用于调起用户确认收款）',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_userId`(`userId`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_out_bill_no`(`out_bill_no`) USING BTREE,
  INDEX `idx_transfer_bill_no`(`transfer_bill_no`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '分销提现记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_enterprises
-- ----------------------------
DROP TABLE IF EXISTS `mbti_enterprises`;
CREATE TABLE `mbti_enterprises`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '企业ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '企业名称',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '企业代码',
  `contactName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系人姓名',
  `contactPhone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系电话',
  `contactEmail` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系邮箱',
  `balance` int(11) NULL DEFAULT 0 COMMENT '余额',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'operating' COMMENT '状态:operating运营中/trial试用/disabled已停用',
  `trialExpireAt` int(11) NULL DEFAULT NULL COMMENT '试用到期时间(时间戳，仅当status为trial时有效)',
  `deletedAt` int(11) NULL DEFAULT NULL COMMENT '删除时间(时间戳，NULL表示未删除)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_code`(`code`) USING BTREE,
  INDEX `idx_deletedAt`(`deletedAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '企业表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_finance_records
-- ----------------------------
DROP TABLE IF EXISTS `mbti_finance_records`;
CREATE TABLE `mbti_finance_records`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型:recharge/consume/refund',
  `amount` int(11) NOT NULL DEFAULT 0 COMMENT '金额（分）',
  `balanceBefore` int(11) NULL DEFAULT 0 COMMENT '操作前余额（分）',
  `balanceAfter` int(11) NULL DEFAULT 0 COMMENT '操作后余额（分）',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
  `orderId` int(11) NULL DEFAULT NULL COMMENT '关联订单ID',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 31 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '财务记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_orders
-- ----------------------------
DROP TABLE IF EXISTS `mbti_orders`;
CREATE TABLE `mbti_orders`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `orderNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '订单号',
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `productType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '产品类型:face/mbti/disc/pdp/report',
  `productTitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '商品标题（如个人深度洞察测试版、AI人脸完整报告）',
  `amount` int(11) NOT NULL DEFAULT 0 COMMENT '金额(分)',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pending' COMMENT '状态:pending/paid/completed/cancelled',
  `payMethod` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '支付方式',
  `payTime` int(11) NULL DEFAULT NULL COMMENT '支付时间(时间戳)',
  `wechatTransactionId` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信支付订单号(transaction_id)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `orderNo`(`orderNo`) USING BTREE,
  INDEX `idx_userId`(`userId`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_productType`(`productType`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 84 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '订单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_pricing_config
-- ----------------------------
DROP TABLE IF EXISTS `mbti_pricing_config`;
CREATE TABLE `mbti_pricing_config`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '定价类型:personal个人版/enterprise企业版/deep深度服务',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID，NULL=全局默认',
  `config` json NULL COMMENT '定价配置(JSON格式)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_type_enterpriseId`(`type`, `enterpriseId`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '全局定价配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_questions
-- ----------------------------
DROP TABLE IF EXISTS `mbti_questions`;
CREATE TABLE `mbti_questions`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '题目ID',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '题目类型:mbti/disc/pdp',
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '题目内容',
  `options` json NULL COMMENT '选项(JSON格式)',
  `dimension` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '维度(仅MBTI类型使用:EI/SN/TF/JP)',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID(NULL表示超管题库)',
  `sort` int(11) NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:1启用,0禁用',
  `deletedAt` int(11) NULL DEFAULT NULL COMMENT '删除时间(时间戳，NULL表示未删除)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_type`(`type`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_sort`(`sort`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_deletedAt`(`deletedAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 131 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '题目表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_system_config
-- ----------------------------
DROP TABLE IF EXISTS `mbti_system_config`;
CREATE TABLE `mbti_system_config`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键',
  `enterprise_id` int(11) NOT NULL DEFAULT 0 COMMENT '企业ID，0=全局/个人版',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '配置值(JSON格式)',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '配置说明',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_key_eid`(`key`, `enterprise_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_system_configs
-- ----------------------------
DROP TABLE IF EXISTS `mbti_system_configs`;
CREATE TABLE `mbti_system_configs`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `configKey` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键',
  `configValue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '配置值(JSON格式)',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '描述',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `configKey`(`configKey`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_test_results
-- ----------------------------
DROP TABLE IF EXISTS `mbti_test_results`;
CREATE TABLE `mbti_test_results`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '结果ID',
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `testType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '测试类型:mbti/disc/pdp/face',
  `resultData` json NULL COMMENT '测试结果(JSON格式)',
  `score` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '得分详情',
  `orderId` int(11) NULL DEFAULT NULL COMMENT '关联订单ID(mbti_orders.id)',
  `requiresPayment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要付款:0否1是',
  `isPaid` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已付款:0否1是',
  `paidAmount` int(11) NULL DEFAULT 0 COMMENT '付款金额(分，冗余存储，避免改价影响历史)',
  `paidAt` int(11) NULL DEFAULT NULL COMMENT '付款时间(时间戳)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID，NULL表示个人用户',
  `testScope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal' COMMENT '测试来源版本: personal=个人版 enterprise=企业版',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_userId`(`userId`) USING BTREE,
  INDEX `idx_testType`(`testType`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_orderId`(`orderId`) USING BTREE,
  INDEX `idx_isPaid`(`isPaid`) USING BTREE,
  INDEX `idx_requiresPayment`(`requiresPayment`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 146 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '测试结果表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_upload_files
-- ----------------------------
DROP TABLE IF EXISTS `mbti_upload_files`;
CREATE TABLE `mbti_upload_files`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '存储路径',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '访问 URL',
  `driver` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'oss' COMMENT '驱动: oss/local',
  `hash` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件 MD5',
  `size` int(11) NULL DEFAULT NULL COMMENT '文件大小(字节)',
  `mimeType` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'MIME 类型',
  `extension` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '扩展名',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_driver_hash`(`driver`, `hash`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 124 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '上传文件记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_user_profile
-- ----------------------------
DROP TABLE IF EXISTS `mbti_user_profile`;
CREATE TABLE `mbti_user_profile`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `userId` int(11) NOT NULL COMMENT '用户ID（关联总用户表主键）',
  `userType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'personal' COMMENT '用户类型: personal个人 / enterprise企业',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID（企业用户所属企业，个人为空）',
  `testsTotal` int(11) NOT NULL DEFAULT 0 COMMENT '总测试次数（所有类型之和）',
  `testsMbti` int(11) NOT NULL DEFAULT 0 COMMENT 'MBTI测试次数',
  `testsDisc` int(11) NOT NULL DEFAULT 0 COMMENT 'DISC测试次数',
  `testsPdp` int(11) NOT NULL DEFAULT 0 COMMENT 'PDP测试次数',
  `testsFace` int(11) NOT NULL DEFAULT 0 COMMENT 'AI面相测试次数',
  `ordersTotal` int(11) NOT NULL DEFAULT 0 COMMENT '总订单数',
  `paidOrders` int(11) NOT NULL DEFAULT 0 COMMENT '已付款订单数',
  `totalPaidAmount` int(11) NOT NULL DEFAULT 0 COMMENT '总支付金额（分）',
  `lastTestResultId` int(11) NULL DEFAULT NULL COMMENT '最近一条测试结果ID（mbti_test_results.id）',
  `lastTestType` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '最近测试类型: mbti/disc/pdp/face/ai',
  `lastTestAt` int(11) NULL DEFAULT NULL COMMENT '最近测试时间',
  `lastMbtiResultId` int(11) NULL DEFAULT NULL COMMENT '最近一次MBTI结果ID',
  `lastDiscResultId` int(11) NULL DEFAULT NULL COMMENT '最近一次DISC结果ID',
  `lastPdpResultId` int(11) NULL DEFAULT NULL COMMENT '最近一次PDP结果ID',
  `lastFaceResultId` int(11) NULL DEFAULT NULL COMMENT '最近一次AI面相结果ID',
  `createdAt` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `updatedAt` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_user`(`userId`, `userType`, `enterpriseId`) USING BTREE,
  INDEX `idx_enterprise`(`enterpriseId`) USING BTREE,
  INDEX `idx_lastTestAt`(`lastTestAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '用户画像汇总表（个人+企业，含最近测试结果ID）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_users
-- ----------------------------
DROP TABLE IF EXISTS `mbti_users`;
CREATE TABLE `mbti_users`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码(加密)',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱',
  `role` enum('enterprise_admin','admin','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '角色:enterprise_admin企业管理员/admin普通管理员/superadmin超级管理员',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '企业ID',
  `mbtiType` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'MBTI类型',
  `region` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '地区',
  `industry` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '行业',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态:1正常,0禁用',
  `lastLoginTime` int(11) NULL DEFAULT NULL COMMENT '最后登录时间(时间戳)',
  `lastLoginIp` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '最后登录IP',
  `deletedAt` int(11) NULL DEFAULT NULL COMMENT '删除时间(时间戳，NULL表示未删除)',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE,
  INDEX `idx_role`(`role`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_lastLoginTime`(`lastLoginTime`) USING BTREE,
  INDEX `idx_deletedAt`(`deletedAt`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员用户表(仅存储管理员和超管)' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for mbti_wechat_users
-- ----------------------------
DROP TABLE IF EXISTS `mbti_wechat_users`;
CREATE TABLE `mbti_wechat_users`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '微信 openid',
  `unionid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信 unionid（开放平台）',
  `sessionKey` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '会话密钥',
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像 URL',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号',
  `gender` tinyint(1) NULL DEFAULT 0 COMMENT '性别：0未知 1男 2女',
  `country` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '国家',
  `province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '省份',
  `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '城市',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态：1正常 0禁用',
  `lastLoginAt` int(11) NULL DEFAULT NULL COMMENT '最后登录时间(时间戳)',
  `lastLoginIp` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '最后登录 IP',
  `enterpriseId` int(11) NULL DEFAULT NULL COMMENT '当前绑定企业ID：通过企业分享测试链接进入时更新，个人分享不更新',
  `createdAt` int(11) NULL DEFAULT NULL COMMENT '创建时间(时间戳)',
  `updatedAt` int(11) NULL DEFAULT NULL COMMENT '更新时间(时间戳)',
  `walletBalance` int(11) NOT NULL DEFAULT 0 COMMENT '钱包余额（分）',
  `walletTotalEarned` int(11) NOT NULL DEFAULT 0 COMMENT '历史累计佣金（分）',
  `walletPending` int(11) NOT NULL DEFAULT 0 COMMENT '待入账佣金（分）',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_openid`(`openid`) USING BTREE,
  INDEX `idx_unionid`(`unionid`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_lastLoginAt`(`lastLoginAt`) USING BTREE,
  INDEX `idx_createdAt`(`createdAt`) USING BTREE,
  INDEX `idx_enterpriseId`(`enterpriseId`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 42 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '微信小程序用户表' ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
