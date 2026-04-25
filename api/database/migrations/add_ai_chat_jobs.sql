-- 神仙 AI 异步对话任务表（多机/负载均衡下文件 Cache 不共享会导致轮询 404 或任务丢失）
-- 表名前缀请与 .env database.prefix 一致（示例为 mbti_）

CREATE TABLE IF NOT EXISTS `mbti_ai_chat_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `userId` INT UNSIGNED NOT NULL,
  `jobId` CHAR(32) NOT NULL COMMENT 'hex 任务 id',
  `conversationId` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(16) NOT NULL DEFAULT 'running' COMMENT 'running|done|error',
  `resultJson` MEDIUMTEXT NULL COMMENT 'done 时存接口 data 的 JSON',
  `errorMessage` VARCHAR(512) NULL,
  `createdAt` INT UNSIGNED NOT NULL DEFAULT 0,
  `updatedAt` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_job` (`userId`, `jobId`),
  KEY `idx_updated` (`updatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='神仙 AI 异步 chat 任务';
