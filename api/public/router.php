<?php
// PHP 内置服务器用：非真实文件请求一律进 ThinkPHP 入口
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($uri !== '/' && $uri !== '' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}
require __DIR__ . '/index.php';
