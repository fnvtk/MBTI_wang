-- 推广提现：最低金额改为 1 分（0.01 元）。需 MySQL 5.7+ 且 value 为合法 JSON。
-- 也可直接在超管「分销设置」将最低提现改为 0.01 并保存。
UPDATE `mbti_system_config`
SET `value` = JSON_SET(CAST(`value` AS JSON), '$.minWithdrawFen', 1),
    `updatedAt` = UNIX_TIMESTAMP()
WHERE `key` = 'distribution'
  AND `enterprise_id` = 0
  AND JSON_EXTRACT(CAST(`value` AS JSON), '$.minWithdrawFen') = 100;
