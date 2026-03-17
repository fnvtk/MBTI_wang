-- 为提现记录表增加手续费字段（已有库执行）
-- 表名以实际前缀为准，若为 mbti_ 则表名为 mbti_distribution_withdrawals
ALTER TABLE `mbti_distribution_withdrawals`
  ADD COLUMN `feeFen` int(11) NOT NULL DEFAULT 0 COMMENT '手续费（分）' AFTER `amountFen`;
