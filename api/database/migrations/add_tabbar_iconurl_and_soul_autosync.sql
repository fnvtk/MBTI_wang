-- =====================================================
-- plan7 补丁：给 tabBar 配置加 iconUrl（可传图片 URL 覆盖 SVG）
-- 给 soul_articles 自动推送时间戳
-- Date: 2026-04-17
-- =====================================================

ALTER TABLE `mbti_mp_tabbar_items`
  ADD COLUMN `iconUrl` VARCHAR(512) NULL AFTER `iconKey`
  COMMENT '图片图标 URL，若有则优先覆盖 iconKey 的 SVG';

-- 给"神仙AI"tab 默认用团队 logo 图
UPDATE `mbti_mp_tabbar_items`
  SET `iconUrl` = '/images/mbti-team-image.png'
  WHERE `iconKey` = 'ai' AND (`iconUrl` IS NULL OR `iconUrl` = '');

-- Soul 自动采集：last sync 时间戳记录到 system_config
INSERT INTO `mbti_system_config` (`key`, `value`, `description`, `createdAt`, `updatedAt`)
SELECT 'soul_article_auto_sync',
       JSON_OBJECT('enabled', TRUE, 'intervalSec', 3600, 'limit', 10),
       'Soul 文章自动采集配置',
       UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
  SELECT 1 FROM `mbti_system_config` WHERE `key` = 'soul_article_auto_sync'
);
