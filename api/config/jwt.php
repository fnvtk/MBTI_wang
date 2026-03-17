<?php
// JWT配置
return [
    // JWT密钥（请在生产环境修改）
    'secret' => env('jwt.secret', 'mbti_jwt_secret_key_2024_change_in_production'),
    
    // Token过期时间（秒），默认7天
    'expire' => env('jwt.expire', 86400 * 7),
    
    // Token刷新时间（秒），默认6天
    'refresh_expire' => env('jwt.refresh_expire', 86400 * 6),
];

