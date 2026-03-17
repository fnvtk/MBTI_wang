<?php
// 中间件配置
return [
    // 默认中间件命名空间
    'default_namespace' => 'app\\middleware',
    // 全局中间件
    'alias'    => [
        'cors' => \app\middleware\Cors::class,
        'auth' => \app\middleware\Auth::class,
        'superadmin' => \app\middleware\SuperAdmin::class,
    ],
];


