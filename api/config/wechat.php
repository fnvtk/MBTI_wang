<?php
// 微信小程序配置
// 这里直接读取 .env 中的 WECHAT_APP_ID / WECHAT_APP_SECRET，和现有配置保持一致
return [
    'app_id'     => env('WECHAT_APP_ID', ''),
    'app_secret' => env('WECHAT_APP_SECRET', ''),
];
