-- 将 mbti_finance_records 的金额字段统一为分
-- 适用于历史上 amount / balanceBefore / balanceAfter 按元(decimal)存储的场景

UPDATE `mbti_finance_records`
SET
  `amount` = ROUND(`amount` * 100),
  `balanceBefore` = ROUND(`balanceBefore` * 100),
  `balanceAfter` = ROUND(`balanceAfter` * 100)
WHERE `amount` < 1000000 OR `balanceBefore` < 1000000 OR `balanceAfter` < 1000000;

ALTER TABLE `mbti_finance_records`
  MODIFY COLUMN `amount` int(11) NOT NULL DEFAULT 0 COMMENT '金额（分）',
  MODIFY COLUMN `balanceBefore` int(11) NULL DEFAULT 0 COMMENT '操作前余额（分）',
  MODIFY COLUMN `balanceAfter` int(11) NULL DEFAULT 0 COMMENT '操作后余额（分）';
