-- 为 wechat_users 表添加 birthday 字段（个人资料页用）
-- 执行: 在数据库中运行此 SQL
ALTER TABLE `mbti_wechat_users` ADD COLUMN `birthday` varchar(20) NULL DEFAULT NULL COMMENT '生日，格式 YYYY-MM-DD' AFTER `city`;
