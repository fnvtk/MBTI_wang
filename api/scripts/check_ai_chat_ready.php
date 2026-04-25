<?php
/**
 * 神仙 AI 对话上线自检（在服务器 api 目录执行）
 *   php scripts/check_ai_chat_ready.php
 *
 * 检查：ai_chat_jobs 表是否存在、是否有已启用的 AI 服务商、InternalPushHook 路由是否可达（仅提示）
 */
declare(strict_types=1);

use app\model\AiProvider as AiProviderModel;
use think\App;
use think\facade\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App();
$app->initialize();

$errors = [];
$ok = [];

$prefix = (string) config('database.connections.mysql.prefix', '');
$tableFull = $prefix . 'ai_chat_jobs';

try {
    Db::name('ai_chat_jobs')->limit(1)->select();
    $ok[] = '数据表 ' . $tableFull . ' 可访问';
} catch (\Throwable $e) {
    $errors[] = '数据表 ai_chat_jobs 不可用（请执行 database/migrations/add_ai_chat_jobs.sql，前缀与 database.prefix 一致）: ' . $e->getMessage();
}

try {
    $n = AiProviderModel::where('enabled', 1)
        ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
        ->count();
    if ($n > 0) {
        $ok[] = '已启用且含 apiKey 的 AI 服务商: ' . $n . ' 条';
    } else {
        $errors[] = '无可用 AI 服务商：请在超管启用至少一条 provider 并填写 apiKey';
    }
} catch (\Throwable $e) {
    $errors[] = '读取 ai_providers 失败: ' . $e->getMessage();
}

try {
    $cfg      = config('database.connections.mysql');
    $dbName   = (string) ($cfg['database'] ?? '');
    $msgTable = $prefix . 'ai_messages';
    if ($dbName !== '') {
        $hit = Db::query(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$dbName, $msgTable, 'isDegraded']
        );
        $c = (int) (($hit[0]['c'] ?? $hit[0]['C'] ?? 0));
        if ($c > 0) {
            $ok[] = '数据表 ' . $msgTable . ' 含 isDegraded 字段（与 AiChat 写入一致）';
        } else {
            $errors[] = '数据表 ' . $msgTable . ' 缺少 isDegraded 列：请执行 database/migrations/add_ai_chat_and_soul_articles.sql 或 patch_ai_messages_isdegraded.sql（前缀与 database.prefix 一致），否则助手消息无法落库';
        }
    }
} catch (\Throwable $e) {
    $errors[] = '检查 ai_messages.isDegraded 失败: ' . $e->getMessage();
}

$ok[] = '异步投递：确保已部署 InternalPushHook.php，且 POST /api/internal/outbound-push/dispatch 不被 Nginx 拦截';
$ok[] = '小程序：上传含 ai-chat 轮询与超时逻辑的最新代码包；request 合法域名包含 API 域名';

foreach ($ok as $line) {
    echo '[OK] ' . $line . "\n";
}
foreach ($errors as $line) {
    echo '[!!] ' . $line . "\n";
}

exit($errors ? 1 : 0);
