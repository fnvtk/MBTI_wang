<?php
namespace app\controller\admin;

use app\BaseController;
use app\controller\admin\concern\ExtractsTestResults;
use think\facade\Db;
use think\facade\Request;

/**
 * 测试用户（小程序用户）管理 - 只读列表与详情
 * 数据来源：wechat_users，测试记录来自 test_results（userId 关联 wechat_users.id）
 */
class AppUser extends BaseController
{
    use ExtractsTestResults;
    /**
     * 测试用户列表：分页、关键词搜索
     * GET /api/v1/admin/app-users?page=1&pageSize=20&keyword=
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'] ?? '', ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $page = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $pageSize = min(max($pageSize, 1), 100);
        $keyword = trim(Request::param('keyword', ''));

        $where = [];
        if ($keyword !== '') {
            $where[] = ['nickname|phone|city|province', 'like', '%' . $keyword . '%'];
        }

        // admin / enterprise_admin 均只能看本企业数据
        $enterpriseId = $user['enterpriseId'] ?? null;
        if (!$enterpriseId) {
            // JWT 未含 enterpriseId 时回退查库（兼容旧 token）
            $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
            $enterpriseId = $adminRow['enterpriseId'] ?? null;
        }

        // 若有企业ID：先从 user_profile 中取出属于本企业的 userId 列表（以画像为主表）
        $profileUserIds = [];
        if ($enterpriseId) {
            $profileUserIds = Db::name('user_profile')
                ->where('enterpriseId', $enterpriseId)
                ->column('userId');
            $profileUserIds = $profileUserIds ? array_values(array_unique(array_filter($profileUserIds))) : [];
            if (empty($profileUserIds)) {
                return paginate_response([], 0, $page, $pageSize);
            }
        }

        // 按 openid 去重：每个 openid 只保留 id 最大的一条；失败则不去重
        try {
            $dedupIds = Db::name('wechat_users')->field('openid, MAX(id) as mid')->group('openid')->column('mid');
            $dedupIds = $dedupIds ? array_values(array_filter($dedupIds)) : [];
        } catch (\Throwable $e) {
            $dedupIds = Db::name('wechat_users')->column('id');
            $dedupIds = $dedupIds ? array_values(array_filter($dedupIds)) : [];
        }
        if (empty($dedupIds)) {
            return paginate_response([], 0, $page, $pageSize);
        }

        $baseQuery = Db::name('wechat_users')->where('id', 'in', $dedupIds);
        // 若从画像表中筛出了当前企业的用户池，则仅保留这些 userId
        if (!empty($profileUserIds)) {
            $baseQuery->whereIn('id', $profileUserIds);
        }
        if ($where) {
            $baseQuery->where($where);
        }

        $total = (int) $baseQuery->count();
        $list = (clone $baseQuery)
            ->field('id,nickname,openid,avatar,phone,gender,country,province,city,status,lastLoginAt,createdAt')
            ->order('createdAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        // 为每条用户附加测试统计（test_results.userId 对应 wechat_users.id）
        $ids = array_column($list, 'id');
        $testCounts = [];
        $lastTestAt = [];
        $testTypes = []; // 每个用户最新几条测试类型，用于展示 MBTI/PDP/DISC
        $payStats = [];
        $enterpriseName = null;
        if ($enterpriseId) {
            $ent = Db::name('enterprises')->where('id', $enterpriseId)->find();
            $enterpriseName = $ent['name'] ?? ('企业' . $enterpriseId);
        }
        if (!empty($ids)) {
            // 测试统计严格按 test_results.enterpriseId 归属企业过滤
            $trBase = Db::name('test_results')->where('userId', 'in', $ids);
            if ($enterpriseId) {
                $trBase->where('enterpriseId', $enterpriseId);
            }
            $counts = (clone $trBase)
                ->group('userId')
                ->column('COUNT(*) as cnt', 'userId');
            $testCounts = $counts ?: [];

            $lastRows = (clone $trBase)
                ->field('id, userId, testType, resultData, createdAt, enterpriseId as testEnterpriseId')
                ->order('createdAt', 'desc')
                ->select();
            foreach ($lastRows as $row) {
                $uid = $row['userId'];
                if (!isset($lastTestAt[$uid])) {
                    $lastTestAt[$uid] = $row['createdAt'];
                }
                if (!isset($testTypes[$uid])) {
                    $testTypes[$uid] = [];
                }
                $testTypes[$uid][] = [
                    'testType'  => $row['testType'],
                    'result'    => is_string($row['resultData'] ?? '') ? ($row['resultData'] ?? '') : json_encode($row['resultData'] ?? '', JSON_UNESCAPED_UNICODE),
                    'createdAt' => $row['createdAt'],
                    'testScope' => !empty($row['testEnterpriseId']) ? 'enterprise' : 'personal',
                ];
            }
            // 付款统计：user_profile（按当前企业过滤）
            try {
                $profilesQuery = Db::name('user_profile')
                    ->where('userId', 'in', $ids);
                if ($enterpriseId) {
                    $profilesQuery->where('enterpriseId', $enterpriseId);
                }
                $profiles = $profilesQuery
                    ->field('userId, SUM(paidOrders) AS paidOrders, SUM(totalPaidAmount) AS totalPaidAmount')
                    ->group('userId')
                    ->select()
                    ->toArray();
                foreach ($profiles as $p) {
                    $uid = (int) ($p['userId'] ?? 0);
                    if ($uid > 0) {
                        $payStats[$uid] = [
                            'paidOrders' => (int) ($p['paidOrders'] ?? 0),
                            'totalPaidAmount' => (int) ($p['totalPaidAmount'] ?? 0),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $payStats = [];
            }
        }

        foreach ($list as &$row) {
            $id = $row['id'];
            $testsForUser = $testTypes[$id] ?? [];
            $row['username'] = $row['nickname'] ?? ('用户' . $id);
            $row['testCount'] = (int) ($testCounts[$id] ?? 0);
            $row['lastTestAt'] = $lastTestAt[$id] ?? null;
            $row['tests'] = $testsForUser;
            $row['mbtiType'] = $this->extractResultType($testsForUser, 'mbti');
            $row['pdpType'] = $this->extractResultType($testsForUser, 'pdp');
            $row['discType'] = $this->extractResultType($testsForUser, 'disc');
            $row['faceType'] = $this->extractResultType($testsForUser, 'face');
            $row['faceMbtiType'] = $this->extractFaceSubType($testsForUser, 'mbti');
            $row['faceDiscType'] = $this->extractFaceSubType($testsForUser, 'disc');
            $row['facePdpType'] = $this->extractFaceSubType($testsForUser, 'pdp');
            $row['enterprise'] = $enterpriseName !== null ? $enterpriseName : '全部';
            $pay = $payStats[$id] ?? null;
            $row['paidOrders'] = $pay ? (int) ($pay['paidOrders'] ?? 0) : 0;
            $row['totalPaidAmount'] = $pay ? (int) ($pay['totalPaidAmount'] ?? 0) : 0;
        }

        return paginate_response($list, $total, $page, $pageSize);
    }

    /**
     * 测试用户详情：基本信息 + 测试记录列表
     * GET /api/v1/admin/app-users/:id
     */
    public function detail($id)
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'] ?? '', ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        // admin / enterprise_admin 均只能查看本企业的用户
        $enterpriseId = $user['enterpriseId'] ?? null;
        if (!$enterpriseId) {
            $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
            $enterpriseId = $adminRow['enterpriseId'] ?? null;
        }
        if ($enterpriseId) {
            // 使用 user_profile 判断该用户是否属于当前企业（以画像为主表）
            $has = Db::name('user_profile')
                ->where('userId', $id)
                ->where('enterpriseId', $enterpriseId)
                ->find();
            if (!$has) {
                return error('无权限查看该用户', 403);
            }
        }

        $row = Db::name('wechat_users')->where('id', $id)->find();
        if (!$row) {
            return error('用户不存在', 404);
        }

        $data = [
            'id' => (int) $row['id'],
            'username' => $row['nickname'] ?? ('用户' . $row['id']),
            'nickname' => $row['nickname'] ?? '',
            'avatar' => $row['avatar'] ?? '',
            'phone' => $row['phone'] ?? '',
            'email' => '',
            'gender' => (int) ($row['gender'] ?? 0),
            'country' => $row['country'] ?? '',
            'province' => $row['province'] ?? '',
            'city' => $row['city'] ?? '',
            'status' => (int) ($row['status'] ?? 1),
            'lastLoginAt' => isset($row['lastLoginAt']) ? (int) $row['lastLoginAt'] : null,
            'createdAt' => isset($row['createdAt']) ? (int) $row['createdAt'] : null,
            'updatedAt' => isset($row['updatedAt']) ? (int) $row['updatedAt'] : null,
        ];

        // 测试列表：严格按 test_results.enterpriseId 归属本企业过滤
        $testQuery = Db::name('test_results')->where('userId', $id);
        if ($enterpriseId) {
            $testQuery->where('enterpriseId', $enterpriseId);
        }
        $tests = $testQuery
            ->field('id, testType, resultData, enterpriseId as testEnterpriseId, createdAt, requiresPayment, isPaid, paidAmount, paidAt, orderId')
            ->order('createdAt', 'desc')
            ->select()
            ->toArray();
        foreach ($tests as &$t) {
            $raw = $t['resultData'] ?? '';
            $t['result']     = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
            $t['testScope']  = !empty($t['testEnterpriseId']) ? 'enterprise' : 'personal';
            unset($t['testEnterpriseId']);
        }

        $data['testCount'] = count($tests);
        $data['testList'] = $tests;
        $data['mbtiType'] = $this->extractResultType($tests, 'mbti');
        $data['pdpType'] = $this->extractResultType($tests, 'pdp');
        $data['discType'] = $this->extractResultType($tests, 'disc');
        $data['faceType'] = $this->extractResultType($tests, 'face');
        $data['faceMbtiType'] = $this->extractFaceSubType($tests, 'mbti');
        $data['faceDiscType'] = $this->extractFaceSubType($tests, 'disc');
        $data['facePdpType'] = $this->extractFaceSubType($tests, 'pdp');

        return success($data);
    }
}
