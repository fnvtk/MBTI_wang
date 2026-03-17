<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\common\service\JwtService;
use think\facade\Request;
use think\facade\Db;

/**
 * 超级管理员认证控制器
 */
class Auth extends BaseController
{
    /**
     * 超级管理员登录
     * @return \think\response\Json
     */
    public function login()
    {
        $username = Request::param('username', '');
        $password = Request::param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        // 查找用户（只允许超级管理员登录）
        $user = Db::name('users')
            ->where('username', $username)
            ->where('role', 'superadmin')
            ->find();

        if (!$user) {
            return error('用户名或密码错误', 401);
        }

        // 验证密码
        if (!password_verify($password, $user['password'])) {
            return error('用户名或密码错误', 401);
        }

        // 检查账号状态
        if ($user['status'] != 1) {
            return error('账号已被禁用', 403);
        }

        // 更新登录信息
        Db::name('users')
            ->where('id', $user['id'])
            ->update([
                'lastLoginTime' => time(),
                'lastLoginIp' => Request::ip(),
                'updatedAt' => time()
            ]);

        // 生成Token
        $payload = [
            'userId' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        $token = JwtService::generateToken($payload);

        unset($user['password']);

        return success([
            'token' => $token,
            'expiresIn' => config('jwt.expire'),
            'user' => $user
        ], '登录成功');
    }

    /**
     * 获取当前登录超级管理员信息（需要认证）
     * @return \think\response\Json
     */
    public function me()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 验证是否为超级管理员
        if ($user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        if (!$userModel) {
            return error('用户不存在', 404);
        }

        unset($userModel['password']);

        return success($userModel);
    }

    /**
     * 退出登录（需要认证）
     * @return \think\response\Json
     */
    public function logout()
    {
        $user = $this->request->user ?? null;
        
        if ($user && isset($user['userId'])) {
            JwtService::deleteToken($user['userId']);
        }

        return success(null, '退出成功');
    }

    /**
     * 刷新Token
     * @return \think\response\Json
     */
    public function refresh()
    {
        $token = JwtService::getTokenFromRequest($this->request);
        
        if (!$token) {
            return error('未提供Token', 401);
        }

        $newToken = JwtService::refreshToken($token);
        
        if (!$newToken) {
            return error('Token无效或已过期', 401);
        }

        return success([
            'token' => $newToken,
            'expiresIn' => config('jwt.expire')
        ], '刷新成功');
    }
}

