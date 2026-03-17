-- 为测试完成佣金补充 commission_records 新字段
-- 执行前请先确认表前缀是否为 mbti_

ALTER TABLE `mbti_commission_records`
  ADD COLUMN `testResultId` int(11) NULL DEFAULT NULL COMMENT '测试结果ID（测试完成佣金使用，关联 test_results.id）' AFTER `orderId`,
  ADD COLUMN `commissionSource` varchar(20) NOT NULL DEFAULT 'payment' COMMENT '佣金来源: payment|test_completion' AFTER `testResultId`;

ALTER TABLE `mbti_commission_records`
  ADD UNIQUE KEY `uk_test_commission` (`testResultId`, `commissionSource`);
