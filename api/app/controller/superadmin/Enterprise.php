<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\model\Enterprise as EnterpriseModel;
use app\model\SystemConfig as SystemConfigModel;
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

        $wechatPage      = max(1, (int) Request::param('wechatPage', 1));
        $wechatPageSize  = min(100, max(1, (int) Request::param('wechatPageSize', 10)));
        $testPage        = max(1, (int) Request::param('testPage', 1));
        $testPageSize    = min(100, max(1, (int) Request::param('testPageSize', 10)));
        $orderPage       = max(1, (int) Request::param('orderPage', 1));
        $orderPageSize   = min(100, max(1, (int) Request::param('orderPageSize', 10)));

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
                ->field('tr.id,tr.testType,tr.createdAt,tr.userId,tr.resultData,u.username')
                ->order('tr.createdAt', 'desc')
                ->limit(50) // 限制返回数量
                ->select()
                ->toArray();
        }
        $data['testResults'] = $testResults;
        $this->attachResultSummaries($data['testResults']);
        
        // 统计测试用量
        if (!empty($userIds)) {
            $data['testUsage'] = Db::name('test_results')
                ->where('userId', 'in', $userIds)
                ->count();
        } else {
            $data['testUsage'] = 0;
        }

        // —— 小程序侧用户（wechat_users.enterpriseId）——
        $wechatIds = [];
        try {
            $wechatIds = Db::name('wechat_users')->where('enterpriseId', $id)->column('id');
            $wechatIds = array_values(array_filter($wechatIds));
        } catch (\Throwable $e) {
            $wechatIds = [];
        }
        $wechatUserTotalCount = 0;
        try {
            $wechatUserTotalCount = (int) Db::name('wechat_users')->where('enterpriseId', $id)->count();
        } catch (\Throwable $e) {
            $wechatUserTotalCount = 0;
        }
        $data['wechatUserCount']      = $wechatUserTotalCount;
        $data['wechatUsersTotal']     = $wechatUserTotalCount;
        $data['wechatUsers']          = [];
        try {
            $data['wechatUsers'] = Db::name('wechat_users')
                ->where('enterpriseId', $id)
                ->field('id,openid,nickname,phone,avatar,status,lastLoginAt,createdAt')
                ->order('createdAt', 'desc')
                ->page($wechatPage, $wechatPageSize)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $data['wechatUsers'] = [];
        }

        // 该企业下、带 enterpriseId 的小程序测试记录
        $data['miniprogramTestResults']      = [];
        $data['miniprogramTestResultsTotal'] = 0;
        try {
            $data['miniprogramTestResultsTotal'] = (int) Db::name('test_results')->where('enterpriseId', $id)->count();
            $data['miniprogramTestResults'] = Db::name('test_results')
                ->alias('tr')
                ->leftJoin('wechat_users w', 'tr.userId = w.id')
                ->where('tr.enterpriseId', $id)
                ->field('tr.id,tr.testType,tr.createdAt,tr.userId,tr.resultData,w.nickname as wechatNickname')
                ->order('tr.createdAt', 'desc')
                ->page($testPage, $testPageSize)
                ->select()
                ->toArray();
            $this->attachResultSummaries($data['miniprogramTestResults']);
        } catch (\Throwable $e) {
            $data['miniprogramTestResults'] = [];
        }
// 订单与消耗（金额分）
        $paidStatuses = ['paid', 'completed'];
        try {
            $data['orderStats'] = [
                'totalCount'    => (int) Db::name('orders')->where('enterpriseId', $id)->count(),
                'paidCount'     => (int) Db::name('orders')->where('enterpriseId', $id)->whereIn('status', $paidStatuses)->count(),
                'paidAmountFen' => (int) (Db::name('orders')->where('enterpriseId', $id)->whereIn('status', $paidStatuses)->sum('amount') ?? 0),
            ];
            $data['recentOrdersTotal'] = (int) Db::name('orders')->where('enterpriseId', $id)->count();
            $data['recentOrders'] = Db::name('orders')
                ->where('enterpriseId', $id)
                ->order('createdAt', 'desc')
                ->page($orderPage, $orderPageSize)
                ->field('id,orderNo,status,amount,productType,userId,createdAt')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $data['orderStats'] = [
                'totalCount'    => 0,
                'paidCount'     => 0,
                'paidAmountFen' => 0,
            ];
            $data['recentOrders']      = [];
            $data['recentOrdersTotal'] = 0;
        }

        // 埋点：近 30 天，归属该企业的小程序用户
        $data['analyticsStats'] = [
            'eventTotal'     => 0,
            'pageViewCount'  => 0,
            'byEvent'        => [],
            'hint'           => null,
            'windowDays'     => 30,
        ];
        if (empty($wechatIds)) {
            $data['analyticsStats']['hint'] = '暂无 enterpriseId 归属该企业的微信小程序用户，无法按企业聚合埋点';
        } else {
            try {
                $since = date('Y-m-d H:i:s', time() - 30 * 86400);
                $data['analyticsStats']['eventTotal'] = (int) Db::name('analytics_events')
                    ->where('userId', 'in', $wechatIds)
                    ->where('createdAt', '>=', $since)
                    ->count();
                $data['analyticsStats']['pageViewCount'] = (int) Db::name('analytics_events')
                    ->where('userId', 'in', $wechatIds)
                    ->where('createdAt', '>=', $since)
                    ->where('eventName', 'page_view')
                    ->count();
                $byEvent = Db::name('analytics_events')
                    ->where('userId', 'in', $wechatIds)
                    ->where('createdAt', '>=', $since)
                    ->field('eventName, COUNT(*) AS cnt')
                    ->group('eventName')
                    ->order('cnt', 'desc')
                    ->limit(20)
                    ->select()
                    ->toArray();
                $data['analyticsStats']['byEvent'] = $byEvent ?: [];
            } catch (\Throwable $e) {
                $data['analyticsStats']['hint'] = '埋点表未就绪或查询失败（请确认已建 analytics_events 表）';
            }
        }

        // 全局通知策略（超管在系统设置中配置，影响余额类提醒等）
        $data['notificationPolicy'] = null;
        try {
            $nc = SystemConfigModel::where('key', 'notification')->where('enterprise_id', 0)->find();
            if ($nc) {
                $val = $nc->getAttr('value');
                $data['notificationPolicy'] = is_array($val) ? $val : null;
            }
        } catch (\Throwable $e) {
            $data['notificationPolicy'] = null;
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

    /**
     * 为列表行附加 resultSummary，并移除原始 resultData（减小响应体积）
     */
    private function attachResultSummaries(array &$rows): void
    {
        foreach ($rows as &$r) {
            $type = (string) ($r['testType'] ?? '');
            $r['resultSummary'] = $this->summarizeTestResultForAdmin($type, $r['resultData'] ?? null);
            unset($r['resultData']);
        }
        unset($r);
    }

    /**
     * 从 test_results.resultData 解析超管可读短摘要
     */
    private function summarizeTestResultForAdmin(string $testType, $resultData): string
    {
        if ($resultData === null || $resultData === '') {
            return '—';
        }
        $decoded = is_string($resultData) ? json_decode($resultData, true) : $resultData;
        if (!is_array($decoded)) {
            return '—';
        }
        $testType = strtolower($testType);
        switch ($testType) {
            case 'mbti':
                if (!empty($decoded['mbtiType'])) {
                    return (string) $decoded['mbtiType'];
                }
                if (!empty($decoded['mbti']['type'])) {
                    return (string) $decoded['mbti']['type'];
                }
                return '—';
            case 'disc':
                $d = trim((string) ($decoded['dominantType'] ?? ''));
                $s = trim((string) ($decoded['secondaryType'] ?? ''));
                $line = $d . ($d !== '' && $s !== '' ? ' + ' : '') . $s;
                return $line !== '' ? $line : '—';
            case 'pdp':
                $d = trim((string) ($decoded['dominantType'] ?? ''));
                return $d !== '' ? $d : '—';
            case 'face':
            case 'ai':
                $parts = [];
                if (!empty($decoded['mbti']['type'])) {
                    $parts[] = 'MBTI ' . $decoded['mbti']['type'];
                }
                if (!empty($decoded['pdp']['primary'])) {
                    $parts[] = 'PDP ' . $decoded['pdp']['primary'];
                }
                if (!empty($decoded['disc']['primary'])) {
                    $parts[] = 'DISC ' . $decoded['disc']['primary'];
                }
                if ($parts !== []) {
                    return implode(' · ', $parts);
                }
                $sum = $decoded['personalitySummary'] ?? $decoded['overview'] ?? '';
                $sum = is_string($sum) ? trim($sum) : '';
                if ($sum !== '') {
                    return mb_strlen($sum) > 48 ? mb_substr($sum, 0, 48) . '…' : $sum;
                }
                return '面相/智能分析';
            default:
                return '—';
        }
    }
}

