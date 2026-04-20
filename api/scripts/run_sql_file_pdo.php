<?php
/**
 * 用 PDO 执行 SQL 文件（去掉行注释 -- 后整段 exec，适配多行 UPDATE）
 */
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "用法: php scripts/run_sql_file_pdo.php <相对api根目录的sql路径>\n");
    exit(1);
}

$root = dirname(__DIR__);
$sqlPath = $root . '/' . ltrim($argv[1], '/');
if (!is_readable($sqlPath)) {
    fwrite(STDERR, "找不到文件: {$sqlPath}\n");
    exit(1);
}

$envFile = $root . '/.env';
$env = [];
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim(str_replace("\r", '', $line));
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }
        if (!preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            continue;
        }
        $env[$m[1]] = trim($m[2], " \t\"'");
    }
}

$host = $env['DATABASE_HOSTNAME'] ?? '127.0.0.1';
$port = (int) ($env['DATABASE_HOSTPORT'] ?? 3306);
$db = $env['DATABASE_DATABASE'] ?? '';
$user = $env['DATABASE_USERNAME'] ?? '';
$pass = $env['DATABASE_PASSWORD'] ?? '';
if ($db === '' || $user === '') {
    fwrite(STDERR, ".env 缺少 DATABASE_DATABASE / DATABASE_USERNAME\n");
    exit(1);
}

$raw = file_get_contents($sqlPath);
if ($raw === false || trim($raw) === '') {
    fwrite(STDERR, "SQL 为空\n");
    exit(1);
}

// 去掉整行 -- 注释（本仓库迁移文件常用）
$sql = preg_replace('/^\s*--.*$/m', '', $raw);
$sql = trim(preg_replace("/\n{3,}/", "\n\n", $sql));
if ($sql === '') {
    fwrite(STDERR, "去掉注释后无 SQL\n");
    exit(1);
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec($sql);

echo "OK\n";
