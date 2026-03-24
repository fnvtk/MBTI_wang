-- 为 test_results 新增 testScope 字段，明确区分企业版与个人版
-- personal：用户从个人链接/个人页发起的测试（定价走 admin_personal）
-- enterprise：用户从企业分享链接发起的测试（定价走 admin_enterprise）
-- 执行前请确认表前缀，默认为 mbti_

ALTER TABLE `mbti_test_results`
  ADD COLUMN `testScope` varchar(20) NOT NULL DEFAULT 'personal'
    COMMENT '测试来源版本: personal=个人版 enterprise=企业版'
    AFTER `enterpriseId`;

-- 将已有记录中 enterpriseId 非空的补标为 enterprise（兼容历史数据）
UPDATE `mbti_test_results` SET `testScope` = 'enterprise' WHERE `enterpriseId` IS NOT NULL;
