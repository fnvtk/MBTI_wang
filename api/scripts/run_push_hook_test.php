<?php
/**
 * CLI：执行一次出站 Hook 测试推送（与后台「发送测试推送」等价）
 * 用法：php scripts/run_push_hook_test.php [contextEnterpriseId]
 * 示例：php scripts/run_push_hook_test.php 0
 */
declare(strict_types=1);

namespace think;

use app\common\service\OutboundPushHookService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = new App($root);
$app->initialize();

$ctx = 0;
if (isset($argv[1]) && $argv[1] !== '' && ctype_digit((string) $argv[1])) {
    $ctx = (int) $argv[1];
}

$r = OutboundPushHookService::sendTestPing($ctx);

echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

exit(!empty($r['ok']) ? 0 : 2);
