<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\User as UserModel;
use app\common\service\JwtService;
use think\facade\Request;
use think\facade\Db;

/**
 * 后台管理员认证控制器
 */
class Auth extends BaseController
{
    /**
     * 后台管理员登录（兼容旧版路由）
     * @return \think\response\Json
     */
    public function login()
    {
        $username = Request::param('username', '');
        $password = Request::param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        // 查找用户（使用原生查询获取密码字段）- 只允许普通管理员和企业管理员登录
        $user = Db::name('users')
            ->where('username', $username)
            ->where('role', 'in', ['admin', 'enterprise_admin']) // 只允许普通管理员和企业管理员登录
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

        // 更新登录信息（使用时间戳，驼峰命名）
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
            'role' => $user['role'],
            'enterpriseId' => $user['enterpriseId'] ?? null
        ];

        $token = JwtService::generateToken($payload);

        unset($user['password']);

        return success([
            'token' => $token,
            'expires_in' => config('jwt.expire'),
            'user' => $user
        ], '登录成功');
    }

    /**
     * 管理员登录（新路由：/api/v1/auth/admin/login）
     * @return \think\response\Json
     */
    public function adminLogin()
    {
        $username = Request::param('username', '');
        $password = Request::param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        // 查找用户（使用原生查询获取密码字段）
        $user = Db::name('users')
            ->where('username', $username)
            ->where('role', 'in', ['admin', 'enterprise_admin']) // 只允许普通管理员和企业管理员
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

        // 更新登录信息（使用时间戳，驼峰命名）
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
            'role' => $user['role'],
            'enterpriseId' => $user['enterpriseId'] ?? null
        ];

        $token = JwtService::generateToken($payload);

        unset($user['password']);

        return success([
            'token' => $token,
            'expires_in' => config('jwt.expire'),
            'user' => $user
        ], '登录成功');
    }

    /**
     * 超级管理员登录（新路由：/api/v1/auth/superadmin/login）
     * @return \think\response\Json
     */
    public function superAdminLogin()
    {
        $username = Request::param('username', '');
        $password = Request::param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        // 查找用户（使用原生查询获取密码字段）
        $user = Db::name('users')
            ->where('username', $username)
            ->where('role', 'superadmin') // 只允许超级管理员
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

        // 更新登录信息（使用时间戳，驼峰命名）
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
            'expires_in' => config('jwt.expire'),
            'user' => $user
        ], '登录成功');
    }

    /**
     * 获取当前登录管理员信息（需要认证）
     * @return \think\response\Json
     */
    public function me()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        $userModel = Db::name('users')->where('id', $user['userId'] ?? $user['user_id'] ?? null)->find();
        if (!$userModel) {
            return error('用户不存在', 404);
        }

        // 检查角色（必须是普通管理员或企业管理员，不包括超级管理员）
        if (!in_array($userModel['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问后台', 403);
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
        } elseif ($user && isset($user['user_id'])) {
            JwtService::deleteToken($user['user_id']);
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
            'expires_in' => config('jwt.expire')
        ], '刷新成功');
    }
}

