-- 高考志愿：用户任务与表单档案（定价/订单/报告已统一至 PricingConfig + orders + test_results.testType=gaokao）
-- 执行前请确认表前缀，以下以 mbti_ 为例

CREATE TABLE IF NOT EXISTS `mbti_gaokao_user_profile` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `userId` BIGINT NOT NULL COMMENT '用户 ID',
  `tenantId` BIGINT NOT NULL DEFAULT 0 COMMENT '租户 ID',
  `entryStatus` TINYINT NOT NULL DEFAULT 0 COMMENT '入口/任务流：0未进入 1进行中 2已完成',
  `mbtiStatus` TINYINT NOT NULL DEFAULT 0 COMMENT 'MBTI 完成状态，0 未完成 1 已完成等',
  `pdpStatus` TINYINT NOT NULL DEFAULT 0 COMMENT 'PDP 完成状态',
  `discStatus` TINYINT NOT NULL DEFAULT 0 COMMENT 'DISC 完成状态',
  `formStatus` TINYINT NOT NULL DEFAULT 0 COMMENT '志愿表单，0 未填/未保存 1 已保存等',
  `analyzeStatus` TINYINT NOT NULL DEFAULT 0 COMMENT '综合分析：0未生成 1已生成 2失败',
  `lastAnalyzeAt` INT NULL DEFAULT NULL COMMENT '最近一次分析时间，Unix 时间戳',
  `latestReportId` BIGINT NULL DEFAULT NULL COMMENT '最近一份高考报告对应 mbti_test_results.id（testType=gaokao）',
  `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '学生姓名，冗余自表单',
  `province` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '生源省份，冗余自表单',
  `streamSubjects` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '科类/选科，冗余自表单',
  `estimatedScore` INT NULL DEFAULT NULL COMMENT '估分',
  `formJson` JSON NULL COMMENT '志愿表单全量 JSON',
  `tagsJson` JSON NULL COMMENT '业务标签等 JSON',
  `createdAt` INT NOT NULL DEFAULT 0 COMMENT '创建时间，Unix 时间戳',
  `updatedAt` INT NOT NULL DEFAULT 0 COMMENT '更新时间，Unix 时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`userId`),
  KEY `idx_tenant_status` (`tenantId`, `entryStatus`, `analyzeStatus`),
  KEY `idx_last_analyze_at` (`lastAnalyzeAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='高考-用户业务档案';

-- 已部署旧版迁移、仍存在独立高考表时，请线下执行 DROP 或见 migrate_gaokao_legacy_to_unified.sql / rollback_gaokao_core_tables.sql 说明
