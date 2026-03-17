-- 迁移：system_config 新增 enterprise_id 列
-- enterprise_id = 0 表示全局/超管配置，> 0 表示对应企业的配置
-- 执行前请先备份数据库

-- 1. 新增 enterprise_id 列（默认 0 = 全局）
ALTER TABLE `mbti_system_config`
  ADD COLUMN `enterprise_id` INT NOT NULL DEFAULT 0 COMMENT '企业ID，0=全局/个人版' AFTER `key`;

-- 2. 删除旧的单字段唯一索引，改为 (key, enterprise_id) 联合唯一索引
ALTER TABLE `mbti_system_config` DROP INDEX `idx_key`;
ALTER TABLE `mbti_system_config` ADD UNIQUE INDEX `idx_key_eid` (`key`, `enterprise_id`);

-- 3. 迁移旧数据：poster_config_eid_N → key='poster_config', enterprise_id=N
UPDATE `mbti_system_config`
SET `enterprise_id` = CAST(SUBSTRING_INDEX(`key`, '_eid_', -1) AS UNSIGNED),
    `key` = 'poster_config'
WHERE `key` LIKE 'poster_config_eid_%';

-- 4. 迁移旧数据：distribution_enterprise_N → key='distribution', enterprise_id=N
UPDATE `mbti_system_config`
SET `enterprise_id` = CAST(SUBSTRING_INDEX(`key`, 'distribution_enterprise_', -1) AS UNSIGNED),
    `key` = 'distribution'
WHERE `key` LIKE 'distribution_enterprise_%';

-- 5. 迁移旧数据：distribution_personal → key='distribution', enterprise_id=0
UPDATE `mbti_system_config`
SET `key` = 'distribution'
WHERE `key` = 'distribution_personal';

-- 6. 迁移旧数据：miniprogram_config_eid_N → key='text_config', enterprise_id=N
UPDATE `mbti_system_config`
SET `enterprise_id` = CAST(SUBSTRING_INDEX(`key`, '_eid_', -1) AS UNSIGNED),
    `key` = 'text_config'
WHERE `key` LIKE 'miniprogram_config_eid_%';
