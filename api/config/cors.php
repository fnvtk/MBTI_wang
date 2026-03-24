<?php
// 跨域配置
return [
    // 允许的跨域来源（生产环境写死具体域名）
    'allow_origin' => '*',

    // 允许的HTTP方法
    'allow_methods' => env('cors.allow_methods', 'GET,POST,PUT,DELETE,OPTIONS'),

    // 允许的请求头
    'allow_headers' => env('cors.allow_headers', 'Content-Type,Authorization,X-Requested-With,Accept'),

    // 是否允许携带凭证（如果以后要带 cookie 再改成 true）
    'allow_credentials' => env('cors.allow_credentials', false),

    // 预检请求缓存时间（秒）
    'max_age' => env('cors.max_age', 86400),
];