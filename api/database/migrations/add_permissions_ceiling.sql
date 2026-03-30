-- 企业：permissions = 终端生效；permissionsCeiling = 仅超管可改的上限
ALTER TABLE mbti_enterprises
  ADD COLUMN permissionsCeiling json NULL COMMENT '超管功能授权上限' AFTER permissions;

UPDATE mbti_enterprises
SET permissionsCeiling = permissions
WHERE permissionsCeiling IS NULL AND permissions IS NOT NULL;
