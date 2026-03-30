<?php
$pdo = new PDO(
    'mysql:host=56b4c23f6853c.gz.cdb.myqcloud.com;port=14413;dbname=mbti;charset=utf8mb4',
    'mbti',
    'Zhiqun1984',
    [PDO::ATTR_TIMEOUT => 10]
);

$r = $pdo->query("SHOW COLUMNS FROM mbti_enterprises LIKE 'permissions'");
if ($r->rowCount() > 0) {
    echo "Column 'permissions' already exists.\n";
} else {
    $pdo->exec("ALTER TABLE mbti_enterprises ADD COLUMN permissions json NULL COMMENT '功能权限开关' AFTER status");
    echo "Column 'permissions' added.\n";
}
