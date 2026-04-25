-- 回滚：仅删除高考用户档案表（新版迁移仅创建此表）
-- 若库中仍有旧版 mbti_gaokao_report / mbti_gaokao_order 等表，请按需手动 DROP

DROP TABLE IF EXISTS `mbti_gaokao_user_profile`;
