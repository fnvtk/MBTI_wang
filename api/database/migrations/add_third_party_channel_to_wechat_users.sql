-- 第三方跳转渠道字段（仅存 wechat_users）
-- 执行前请确认表前缀：ThinkPHP 配置为 mbti_ 时物理表为 mbti_wechat_users

ALTER TABLE `mbti_wechat_users`
  ADD COLUMN `ext_uid` varchar(191) NULL DEFAULT NULL COMMENT '合作方用户ID（入参userid）' AFTER `phone`,
  ADD COLUMN `third_party_phone` varchar(32) NULL DEFAULT NULL COMMENT '渠道透传手机号（不落主.phone时记录）' AFTER `ext_uid`,
  ADD COLUMN `third_party_tid` varchar(191) NULL DEFAULT NULL COMMENT '合作方任务/活动ID' AFTER `third_party_phone`,
  ADD INDEX `idx_ext_uid` (`ext_uid`(64)),
  ADD INDEX `idx_phone_lookup` (`phone`);
