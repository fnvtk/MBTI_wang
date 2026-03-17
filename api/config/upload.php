<?php
// 上传配置（参考 database.php 的 env 读取方式）
return [
    // 存储驱动：local 或 oss（默认使用 OSS）
    'driver' => env('upload.driver', 'oss'),

    // 本地存储配置
    'local'  => [
        // 本地物理路径
        'root' => app()->getRootPath() . 'public/uploads',
        // 访问 URL 前缀（可配置独立静态域名），末尾不要带斜杠
        // 例如：http://localhost:8000 或 https://static.example.com 或 https://api.737270.com
        // 注意：不要在这里再加 /uploads，避免出现 /uploads/uploads/xxx 的情况
        // 优先级：app.asset_url > app.host > api.737270.com（默认）
        'url'  => rtrim(env('app.asset_url', env('app.host', env('API_DOMAIN', 'https://api.737270.com'))), '/'),
    ],

    // 阿里云 OSS 配置（参考 database.php，使用点号分隔的键名）
    'oss'    => [
        // AccessKey ID（对应 .env 中的 OSS_ACCESS_KEY_ID）
        'access_key_id'     => env('oss.access_key_id', ''),
        // AccessKey Secret（对应 .env 中的 OSS_ACCESS_KEY_SECRET）
        'access_key_secret' => env('oss.access_key_secret', ''),
        // OSS Endpoint（对应 .env 中的 OSS_ENDPOINT），如：oss-cn-hangzhou.aliyuncs.com
        'endpoint'          => env('oss.endpoint', ''),
        // OSS Bucket 名称（对应 .env 中的 OSS_BUCKET）
        'bucket'            => env('oss.bucket', ''),
        // 对象存储前缀（对应 .env 中的 OSS_PREFIX），默认：mbti
        'prefix'            => env('oss.prefix', 'mbti'),
        // OSS 访问域名（对应 .env 中的 OSS_URL）
        // 如果未配置，将自动使用 OSS 自带域名：https://{bucket}.{endpoint}
        'url'               => rtrim(env('oss.url', ''), '/'),
    ],
];
