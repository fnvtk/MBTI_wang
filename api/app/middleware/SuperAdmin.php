<?php
namespace app\middleware;

use app\common\service\JwtService;

/**
 * 超级管理员权限中间件
 */
class SuperAdmin
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

        // 验证是否为超级管理员
        if ($payload['role'] !== 'superadmin') {
            return json([
                'code' => 403,
                'message' => '无权限访问，需要超级管理员权限',
                'data' => null
            ])->code(403);
        }

        // 将用户信息存储到请求中，供控制器使用
        $request->user = $payload;
        $request->userId = $payload['userId'] ?? null;

        return $next($request);
    }
}

