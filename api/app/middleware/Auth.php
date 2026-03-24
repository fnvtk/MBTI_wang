<?php
namespace app\middleware;

use app\common\service\JwtService;

/**
 * 认证中间件
 */
class Auth
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        $token = JwtService::getTokenFromRequest($request);

        if (empty($token)) {
            return json([
                'code' => 401,
                'message' => '未登录或Token无效',
                'data' => null
            ])->code(401);
        }

        // 验证Token
        $payload = JwtService::verifyToken($token);
        
        if (!$payload) {
            return json([
                'code' => 401,
                'message' => 'Token无效或已过期',
                'data' => null
            ])->code(401);
        }

        // 将用户信息存储到请求中，供控制器使用
        $request->user = $payload;
        $request->userId = $payload['userId'] ?? $payload['user_id'] ?? null;

        return $next($request);
    }
}

