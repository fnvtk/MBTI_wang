<?php
// [ 应用入口文件 ]
namespace think;

////处理跨域预检请求
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
    header("Access-Control-Allow-Origin: " . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Cookie");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header("Access-Control-Allow-Credentials: true");
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);


