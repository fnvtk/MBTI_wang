-- 已有库迁移：底部 Tab 顺序改为「首页 · 拍摄(第2项凸起) · 神仙AI · 我」
-- 在已存在 mbti_mp_tabbar_items 数据时执行；按 pagePath 更新，不依赖自增 id

UPDATE `mbti_mp_tabbar_items` SET `sortOrder` = 10, `highlight` = 0 WHERE `pagePath` = 'pages/index/index';
UPDATE `mbti_mp_tabbar_items` SET `sortOrder` = 20, `highlight` = 1 WHERE `pagePath` = 'pages/index/camera';
UPDATE `mbti_mp_tabbar_items` SET `sortOrder` = 30, `highlight` = 0 WHERE `pagePath` = 'pages/ai-chat/index';
UPDATE `mbti_mp_tabbar_items` SET `sortOrder` = 40, `highlight` = 0 WHERE `pagePath` = 'pages/profile/index';
