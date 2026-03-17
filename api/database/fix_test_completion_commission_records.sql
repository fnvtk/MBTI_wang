-- 修复测试完成佣金历史脏数据
-- 执行前请先确认表前缀是否为 mbti_

UPDATE `mbti_commission_records` `cr`
LEFT JOIN `mbti_test_results` `tr` ON `tr`.`id` = `cr`.`testResultId`
LEFT JOIN `mbti_distribution_bindings` `db` ON `db`.`id` = `cr`.`bindingId`
SET
  `cr`.`commissionAmount` = ROUND(`cr`.`commissionFen` / 100, 2),
  `cr`.`paidAt` = CASE
    WHEN `cr`.`status` = 'paid' AND `cr`.`paidAt` IS NULL THEN `cr`.`createdAt`
    ELSE `cr`.`paidAt`
  END,
  `cr`.`enterpriseId` = CASE
    WHEN (`cr`.`enterpriseId` IS NULL OR `cr`.`enterpriseId` = 0)
      THEN NULLIF(COALESCE(NULLIF(`tr`.`enterpriseId`, 0), NULLIF(`db`.`enterpriseId`, 0)), 0)
    ELSE `cr`.`enterpriseId`
  END
WHERE `cr`.`commissionSource` = 'test_completion'
  AND (
    `cr`.`commissionAmount` IS NULL
    OR `cr`.`commissionAmount` = 0
    OR (`cr`.`status` = 'paid' AND `cr`.`paidAt` IS NULL)
    OR ((`cr`.`enterpriseId` IS NULL OR `cr`.`enterpriseId` = 0) AND ((`tr`.`enterpriseId` > 0) OR (`db`.`enterpriseId` > 0)))
  );
