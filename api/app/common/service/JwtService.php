<?php
namespace app\common\service;

use think\facade\Cache;

/**
 * JWT Token 服务类
 */
class JwtService
{
    /**
     * 生成Token
     * @param array $payload 载荷数据
     * @return string
     */
    public static function generateToken(array $payload): string
    {
        $secret = config('jwt.secret', 'mbti_jwt_secret_key_2024');
        $expire = config('jwt.expire', 86400 * 7); // 默认7天

        // 添加过期时间
        $payload['exp'] = time() + $expire;
        $payload['iat'] = time();

        // 生成Token（简单方案：base64编码 + 签名）
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payloadStr = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $header . '.' . $payloadStr, $secret);

        $token = $header . '.' . $payloadStr . '.' . $signature;

        // 将Token存储到缓存（用于刷新和注销），带 source 区分小程序用户与管理员
        $userId = $payload['userId'] ?? $payload['user_id'] ?? null;
        if ($userId !== null) {
            $key = self::tokenCacheKey($userId, $payload['source'] ?? null);
            Cache::set($key, $token, $expire);
        }

        return $token;
    }

    /**
     * 验证Token
     * @param string $token
     * @return array|false 返回载荷数据或false
     */
    public static function verifyToken(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payloadStr, $signature] = $parts;

        // 验证签名
        $secret = config('jwt.secret', 'mbti_jwt_secret_key_2024');
        $expectedSignature = hash_hmac('sha256', $header . '.' . $payloadStr, $secret);

        if ($signature !== $expectedSignature) {
            return false;
        }

        // 解析载荷
        $payload = json_decode(base64_decode($payloadStr), true);
        if (!$payload) {
            return false;
        }

        // 检查过期时间
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    /**
     * 刷新Token
     * @param string $token
     * @return string|false 返回新Token或false
     */
    public static function refreshToken(string $token)
    {
        $payload = self::verifyToken($token);
        if (!$payload) {
            return false;
        }

        // 移除过期时间字段，重新生成
        unset($payload['exp'], $payload['iat']);

        return self::generateToken($payload);
    }

    /**
     * Token 缓存键（区分来源，避免与管理员同 id 冲突）
     */
    public static function tokenCacheKey($userId, ?string $source = null): string
    {
        $prefix = $source ? 'jwt_token_' . $source . '_' : 'jwt_token_';
        return $prefix . $userId;
    }

    /**
     * 删除Token（注销）
     * @param int $userId
     * @param string|null $source 来源，如 wechat，不传则按管理员 token 键删除
     * @return bool
     */
    public static function deleteToken(int $userId, ?string $source = null): bool
    {
        return Cache::delete(self::tokenCacheKey($userId, $source));
    }

    /**
     * 从请求头获取Token
     * @param \think\Request $request
     * @return string|null
     */
    public static function getTokenFromRequest($request): ?string
    {
        $authorization = $request->header('Authorization', '');
        
        if ($authorization && preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}

