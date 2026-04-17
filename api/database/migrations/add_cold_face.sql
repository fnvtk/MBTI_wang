-- 冷脸分析字段：扩展 user_profile，用于列表/详情展示
-- 说明：
--   coldFaceScore 0-100；<35=暖(warm), 35-65=中(neutral), >65=冷(cold)
--   coldFaceLevel 字符串枚举 cold/neutral/warm
--   coldFaceUpdatedAt 最近一次测评更新时间戳（秒）
-- 执行: 在 phpMyAdmin 或 CLI 运行

ALTER TABLE `mbti_user_profile`
  ADD COLUMN `coldFaceScore` tinyint(3) UNSIGNED NULL DEFAULT NULL COMMENT '冷脸分(0-100)' AFTER `lastFaceResultId`,
  ADD COLUMN `coldFaceLevel` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '冷脸等级: cold/neutral/warm' AFTER `coldFaceScore`,
  ADD COLUMN `coldFaceUpdatedAt` int(11) NULL DEFAULT NULL COMMENT '冷脸更新时间(时间戳)' AFTER `coldFaceLevel`,
  ADD INDEX `idx_coldFaceLevel` (`coldFaceLevel`) USING BTREE;
