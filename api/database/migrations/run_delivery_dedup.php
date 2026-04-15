<?php
/**
 * 创建 delivery_dedup 去重表（OutboundPushHook / FeishuLeadWebhook 等共用，按 scene 区分业务）
 *
 * 在 api 目录下执行：
 *   php database/migrations/run_delivery_dedup.php
 */
declare(strict_types=1);

chdir(__DIR__ . '/../..');

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

$prefix = (string) config('database.connections.mysql.prefix', '');
$table = $prefix . 'delivery_dedup';

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$table}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scene` varchar(32) NOT NULL COMMENT '场景：feishu_lead=飞书获客；outbound_hook=通用出站 Webhook；扩展时新增枚举值',
  `dedupKey` varchar(255) NOT NULL COMMENT '该场景下幂等键（与 scene 联合唯一）；出站为 _dedupKey 原值',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scene_dedup` (`scene`, `dedupKey`),
  KEY `idx_scene` (`scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多业务推送幂等去重（飞书获客、出站 Webhook 等共用）'
SQL;

try {
    \think\facade\Db::execute($sql);
    fwrite(STDOUT, "OK: 表 `{$table}` 已就绪\n");
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
