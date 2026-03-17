<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\User as UserModel;
use think\facade\Request;

/**
 * 后台用户管理控制器
 */
class User extends BaseController
{
    /**
     * 获取用户列表（普通管理员和企业管理员）
     * @return \think\response\Json
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }

        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 10);
        $keyword = Request::param('keyword', '');
        $role = Request::param('role', '');
        $status = Request::param('status', '');

        $where = [];
        if ($keyword) {
            $where[] = ['username|email|phone', 'like', '%' . $keyword . '%'];
        }
        if ($role) {
            $where['role'] = $role;
        }
        if ($status !== '') {
            $where['status'] = $status;
        }

        // 根据角色过滤
        if (($user['role'] ?? '') === 'enterprise_admin') {
            // 企业管理员只能查看自己企业的用户
            $where['enterpriseId'] = $user['enterpriseId'] ?? null;
        } else {
            // 普通管理员可以查看所有管理员（不包括超级管理员）
            $where[] = ['role', 'in', ['admin', 'enterprise_admin']];
        }

        $list = UserModel::where($where)
            ->order('createdAt', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = UserModel::where($where)->count();

        return paginate_response($list, $total, $page, $pageSize);
    }

    /**
     * 获取用户详情
     * @param int $id
     * @return \think\response\Json
     */
    public function detail($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return error('用户不存在', 404);
        }

        $userData = $user->toArray();
        unset($userData['password']);

        return success($userData);
    }

    /**
     * 创建用户
     * @return \think\response\Json
     */
    public function create()
    {
        $data = Request::post();
        
        // 检查用户名是否已存在
        if (UserModel::where('username', $data['username'])->find()) {
            return error('用户名已存在');
        }

        // 检查邮箱是否已存在
        if (!empty($data['email']) && UserModel::where('email', $data['email'])->find()) {
            return error('邮箱已被注册');
        }

        // 验证角色（只允许管理员角色）
        $allowedRoles = ['admin', 'enterprise_admin', 'superadmin'];
        $role = $data['role'] ?? 'admin';
        if (!in_array($role, $allowedRoles)) {
            return error('角色必须是管理员类型', 400);
        }

        $user = new UserModel();
        $user->username = $data['username'];
        $user->password = $data['password'] ?? '123456'; // 默认密码
        $user->email = $data['email'] ?? '';
        $user->phone = $data['phone'] ?? '';
        $user->role = $role;
        $user->enterpriseId = $data['enterpriseId'] ?? $data['enterprise_id'] ?? null;
        $user->status = $data['status'] ?? 1;
        $user->save();

        $userData = $user->toArray();
        unset($userData['password']);

        return success($userData, '创建成功');
    }

    /**
     * 更新用户
     * @param int $id
     * @return \think\response\Json
     */
    public function update($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return error('用户不存在', 404);
        }

        $data = Request::put();

        // 如果更新用户名，检查是否重复
        if (isset($data['username']) && $data['username'] != $user->username) {
            if (UserModel::where('username', $data['username'])->find()) {
                return error('用户名已存在');
            }
        }

        // 如果更新邮箱，检查是否重复
        if (isset($data['email']) && $data['email'] != $user->email) {
            if (!empty($data['email']) && UserModel::where('email', $data['email'])->find()) {
                return error('邮箱已被注册');
            }
        }

        // 如果更新密码
        if (isset($data['password'])) {
            $user->password = $data['password']; // 会自动加密
        }

        $user->save($data);

        $userData = $user->toArray();
        unset($userData['password']);

        return success($userData, '更新成功');
    }

    /**
     * 删除用户（软删除）
     * @param int $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return error('用户不存在', 404);
        }

        // 检查是否已删除
        if ($user->deletedAt) {
            return error('用户已被删除', 400);
        }

        // 不能删除自己
        $currentUser = $this->request->user ?? null;
        if ($currentUser && ($currentUser['userId'] ?? $currentUser['user_id'] ?? null) == $id) {
            return error('不能删除自己', 400);
        }

        // 软删除（设置 deletedAt 时间戳）
        $user->delete();
        return success(null, '删除成功');
    }

    /**
     * 启用/禁用用户
     * @param int $id
     * @return \think\response\Json
     */
    public function toggleStatus($id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return error('用户不存在', 404);
        }

        $user->status = $user->status == 1 ? 0 : 1;
        $user->save();

        return success($user, '操作成功');
    }
}

