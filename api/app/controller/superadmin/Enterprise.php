<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\model\Enterprise as EnterpriseModel;
use think\facade\Request;
use think\facade\Db;

/**
 * 企业管理控制器（超管专用）
 */
class Enterprise extends BaseController
{
    /**
     * 获取企业列表
     * @return \think\response\Json
     */
    public function index()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 20);
        $keyword = Request::param('keyword', '');
        $status = Request::param('status', '');

        $where = [];
        
        // 搜索条件
        if ($keyword) {
            $where[] = ['name|contactName|contactPhone|code', 'like', '%' . $keyword . '%'];
        }
        
        // 状态筛选
        if ($status !== '') {
            $where['status'] = $status;
        }

        // 查询企业列表
        $list = EnterpriseModel::where($where)
            ->order('createdAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        // 统计每个企业的用户数和测试用量
        foreach ($list as &$item) {
            // 统计用户数（只统计未删除的用户）
            $item['userCount'] = Db::name('users')
                ->where('enterpriseId', $item['id'])
                ->where('deletedAt', null)
                ->count();
            
            // 统计测试用量（测试结果数）- 通过企业下的用户ID统计（只统计未删除的用户）
            $userIds = Db::name('users')
                ->where('enterpriseId', $item['id'])
                ->where('deletedAt', null)
                ->column('id');
            
            if (!empty($userIds)) {
                $item['testUsage'] = Db::name('test_results')
                    ->where('userId', 'in', $userIds)
                    ->count();
            } else {
                $item['testUsage'] = 0;
            }
        }

        $total = EnterpriseModel::where($where)->count();
        
        // 统计活跃企业数（status为operating）
        $activeCount = EnterpriseModel::where('status', 'operating')->count();

        return success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'activeCount' => $activeCount
        ]);
    }

    /**
     * 获取企业详情
     * @param int $id
     * @return \think\response\Json
     */
    public function detail($id = null)
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 如果路由参数没有传递，尝试从请求参数获取
        if (empty($id)) {
            $id = Request::param('id');
        }
        
        if (empty($id)) {
            return error('企业ID不能为空', 400);
        }

        $enterprise = EnterpriseModel::find($id);
        
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        $data = $enterprise->toArray();
        
        // 获取企业下的所有用户ID（只统计未删除的用户）
        $userIds = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('deletedAt', null)
            ->column('id');
        
        // 统计用户数
        $data['userCount'] = count($userIds);
        
        // 获取管理员账号列表（企业管理员角色，只获取未删除的）
        $adminAccounts = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('role', 'enterprise_admin')
            ->where('deletedAt', null)
            ->field('id,username,email,phone,role,status,createdAt,lastLoginTime')
            ->select()
            ->toArray();
        $data['adminAccounts'] = $adminAccounts;
        
        // 获取用户列表（排除管理员，只获取未删除的）
        $users = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('role', '<>', 'enterprise_admin')
            ->where('deletedAt', null)
            ->field('id,username,email,phone,mbtiType,status,createdAt')
            ->limit(50) // 限制返回数量
            ->select()
            ->toArray();
        $data['users'] = $users;
        
        // 获取测试结果列表
        $testResults = [];
        if (!empty($userIds)) {
            $testResults = Db::name('test_results')
                ->alias('tr')
                ->leftJoin('users u', 'tr.userId = u.id')
                ->where('tr.userId', 'in', $userIds)
                ->field('tr.id,tr.testType,tr.createdAt,u.username')
                ->order('tr.createdAt', 'desc')
                ->limit(50) // 限制返回数量
                ->select()
                ->toArray();
        }
        $data['testResults'] = $testResults;
        
        // 统计测试用量
        if (!empty($userIds)) {
            $data['testUsage'] = Db::name('test_results')
                ->where('userId', 'in', $userIds)
                ->count();
        } else {
            $data['testUsage'] = 0;
        }

        return success($data);
    }

    /**
     * 创建企业
     * @return \think\response\Json
     */
    public function create()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $data = Request::post();
        
        // 验证必填字段
        if (empty($data['name'])) {
            return error('企业名称不能为空', 400);
        }

        // 验证管理员账号信息
        if (empty($data['adminUsername'])) {
            return error('管理员用户名不能为空', 400);
        }

        if (empty($data['adminPassword'])) {
            return error('管理员密码不能为空', 400);
        }

        if (strlen($data['adminPassword']) < 6) {
            return error('密码长度至少6位', 400);
        }

        // 检查企业代码是否重复（如果提供了代码）
        if (!empty($data['code'])) {
            if (EnterpriseModel::where('code', $data['code'])->find()) {
                return error('企业代码已存在', 400);
            }
        }

        // 检查管理员用户名是否已存在
        if (Db::name('users')->where('username', $data['adminUsername'])->find()) {
            return error('管理员用户名已存在', 400);
        }

        // 状态映射（前端使用operating/trial/disabled）
        $status = $data['status'] ?? 'operating';
        if (!in_array($status, ['operating', 'trial', 'disabled'])) {
            $status = 'operating';
        }

        // 验证试用到期时间
        if ($status === 'trial') {
            if (empty($data['trialExpireAt'])) {
                return error('选择试用状态时，必须设置试用到期时间', 400);
            }
            // 确保到期时间大于当前时间
            if ($data['trialExpireAt'] <= time()) {
                return error('试用到期时间必须大于当前时间', 400);
            }
        }

        // 开启事务
        Db::startTrans();
        try {
            // 创建企业
            $enterprise = new EnterpriseModel();
            $enterprise->name = $data['name'];
            $enterprise->code = $data['code'] ?? null;
            $enterprise->contactName = $data['contactName'] ?? null;
            $enterprise->contactPhone = $data['contactPhone'] ?? null;
            $enterprise->contactEmail = $data['contactEmail'] ?? null;
            $enterprise->balance = $data['balance'] ?? 0.00;
            $enterprise->status = $status;
            $enterprise->trialExpireAt = ($status === 'trial' && isset($data['trialExpireAt'])) ? $data['trialExpireAt'] : null;
            $enterprise->save();

            $enterpriseId = $enterprise->id;

            // 创建企业管理员账号
            $adminUser = [
                'username' => $data['adminUsername'],
                'password' => password_hash($data['adminPassword'], PASSWORD_DEFAULT),
                'email' => $data['contactEmail'] ?? null,
                'phone' => $data['contactPhone'] ?? null,
                'role' => 'enterprise_admin',
                'enterpriseId' => $enterpriseId,
                'status' => 1,
                'createdAt' => time(),
                'updatedAt' => time()
            ];

            Db::name('users')->insert($adminUser);

            // 提交事务
            Db::commit();

            $enterpriseData = $enterprise->toArray();
            $enterpriseData['userCount'] = 1; // 刚创建的企业管理员
            $enterpriseData['testUsage'] = 0;

            return success($enterpriseData, '企业创建成功，管理员账号已创建');
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return error('创建失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新企业
     * @param int $id
     * @return \think\response\Json
     */
    public function update($id)
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $enterprise = EnterpriseModel::find($id);
        
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        $data = Request::put();
        $oldBalance = (float) ($enterprise->balance ?? 0);

        // 如果更新企业代码，检查是否重复
        if (isset($data['code']) && $data['code'] != $enterprise->code) {
            if (EnterpriseModel::where('code', $data['code'])->find()) {
                return error('企业代码已存在', 400);
            }
        }

        // 状态验证
        if (isset($data['status']) && !in_array($data['status'], ['operating', 'trial', 'disabled'])) {
            return error('状态值无效', 400);
        }

        // 验证试用到期时间
        $status = $data['status'] ?? $enterprise->status;
        if ($status === 'trial') {
            if (empty($data['trialExpireAt'])) {
                return error('选择试用状态时，必须设置试用到期时间', 400);
            }
            // 确保到期时间大于当前时间
            if ($data['trialExpireAt'] <= time()) {
                return error('试用到期时间必须大于当前时间', 400);
            }
            $enterprise->trialExpireAt = $data['trialExpireAt'];
        } else {
            // 如果不是试用状态，清空到期时间
            $enterprise->trialExpireAt = null;
        }

        $enterprise->save($data);

        $newBalance = (float) ($enterprise->balance ?? 0);
        if ($newBalance > $oldBalance) {
            try {
                \app\controller\api\Distribution::unfreezeCommissions((int) $id);
            } catch (\Throwable $e) {
                // 余额已更新成功，解冻失败不阻断主流程
            }
        }

        $enterpriseData = $enterprise->toArray();
        
        // 统计用户数和测试用量（只统计未删除的用户）
        $enterpriseData['userCount'] = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('deletedAt', null)
            ->count();
        
        $userIds = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('deletedAt', null)
            ->column('id');
        
        if (!empty($userIds)) {
            $enterpriseData['testUsage'] = Db::name('test_results')
                ->where('userId', 'in', $userIds)
                ->count();
        } else {
            $enterpriseData['testUsage'] = 0;
        }

        return success($enterpriseData, '更新成功');
    }

    /**
     * 删除企业（软删除）
     * @param int $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $enterprise = EnterpriseModel::find($id);
        
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        // 检查是否已删除
        if ($enterprise->deletedAt) {
            return error('企业已被删除', 400);
        }

        // 检查是否有用户关联（只检查未删除的用户）
        $userCount = Db::name('users')
            ->where('enterpriseId', $id)
            ->where('deletedAt', null)
            ->count();
        if ($userCount > 0) {
            return error('该企业下还有用户，无法删除', 400);
        }

        // 软删除（设置 deletedAt 时间戳）
        $enterprise->delete();
        
        return success(null, '删除成功');
    }

    /**
     * 启用/禁用企业
     * @param int $id
     * @return \think\response\Json
     */
    public function toggleStatus($id)
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $enterprise = EnterpriseModel::find($id);
        
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        // 切换状态：operating <-> disabled
        if ($enterprise->status === 'operating') {
            $enterprise->status = 'disabled';
        } else {
            $enterprise->status = 'operating';
        }
        
        $enterprise->save();

        return success($enterprise, '操作成功');
    }
}

