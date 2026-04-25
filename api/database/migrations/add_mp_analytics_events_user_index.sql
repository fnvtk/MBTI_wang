-- 埋点表补充：userId + createdAt 复合索引（用于用户旅程、分享统计）
-- 幂等写法：若索引已存在会报错，可忽略（或手工 SHOW INDEX 检查后再执行）
ALTER TABLE `mbti_analytics_events`
  ADD INDEX `idx_user_created` (`userId`, `createdAt`);
