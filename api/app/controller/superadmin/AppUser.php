<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 超管 - 测试用户（小程序用户）管理
 * 数据来源：wechat_users，测试记录表（物理表名一般为 mbti_test_results，逻辑使用 Db::name('test_results')）
 */
class AppUser extends BaseController
{
    /**
     * 概览：用户统计、卡片、MBTI 分布
     * GET /api/v1/superadmin/app-users/overview
     */
    public function overview()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 用户数按 openid 去重
        try {
            $totalUsers = (int) Db::name('wechat_users')->count('openid', true);
        } catch (\Throwable $e) {
            $totalUsers = (int) Db::name('wechat_users')->count();
        }
        $last30d = time() - 30 * 86400;

        // 全部池：去重后的测试用户 & 近 30 天活跃用户（按 userId 去重）
        // 这里使用逻辑表名 test_results，底层会自动加前缀生成 mbti_test_results
        $testedUserIds = Db::name('test_results')->distinct(true)->column('userId');
        $testedUsers = count(array_filter($testedUserIds));

        $activeUserIds = Db::name('test_results')
            ->where('createdAt', '>=', $last30d)
            ->distinct(true)
            ->column('userId');
        $activeUsers = count(array_filter($activeUserIds));

        $userCards = [
            [
                'type' => 'all',
                'name' => '全部用户',
                'total' => $totalUsers,
                'active' => $activeUsers,
                'tested' => $testedUsers
            ]
        ];

        try {
            // 个人池：enterpriseId 为空的测试用户，按 userId 去重
            $individualIds = Db::name('test_results')
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', '');
                })
                ->distinct(true)
                ->column('userId');
            $individualTotal = count(array_filter($individualIds));

            $individualActiveIds = Db::name('test_results')
                ->where('createdAt', '>=', $last30d)
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', '');
                })
                ->distinct(true)
                ->column('userId');
            $individualActive = count(array_filter($individualActiveIds));
            $userCards[] = [
                'type' => 'individual',
                'name' => '个人用户(无企业)',
                'total' => $individualTotal,
                'active' => $individualActive,
                'tested' => $individualTotal
            ];
        } catch (\Throwable $e) {
            $userCards[] = [
                'type' => 'individual',
                'name' => '个人用户(无企业)',
                'total' => 0,
                'active' => 0,
                'tested' => 0
            ];
        }

        $enterprises = Db::name('enterprises')->field('id,name')->select()->toArray();
        foreach ($enterprises as $e) {
            $eid = $e['id'];
            try {
                $ids = Db::name('test_results')
                    ->where('enterpriseId', $eid)
                    ->distinct(true)
                    ->column('userId');
                $total = count(array_filter($ids));

                $activeIds = Db::name('test_results')
                    ->where('enterpriseId', $eid)
                    ->where('createdAt', '>=', $last30d)
                    ->distinct(true)
                    ->column('userId');
                $active = count(array_filter($activeIds));
            } catch (\Throwable $ex) {
                $total = 0;
                $active = 0;
            }
            $userCards[] = [
                'type' => 'enterprise',
                'enterpriseId' => $eid,
                'name' => $e['name'] ?? ('企业' . $eid),
                'total' => $total,
                'active' => $active,
                'tested' => $total
            ];
        }

        // MBTI 类型分布：按用户去重，每人只计其最新一次 MBTI 结果
        $mbtiTypes = [];
        try {
            $rows = Db::name('test_results')
                ->where('testType', 'mbti')
                ->field('userId, resultData, createdAt')
                ->order('createdAt', 'desc')
                ->select()
                ->toArray();
            $seenUserIds = [];
            foreach ($rows as $r) {
                $uid = (int) ($r['userId'] ?? 0);
                if ($uid <= 0 || isset($seenUserIds[$uid])) {
                    continue;
                }
                $raw = $r['resultData'] ?? '';
                $dec = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
                if (!is_array($dec)) {
                    $seenUserIds[$uid] = true;
                    continue;
                }
                $type = '';
                if (isset($dec['mbtiType'])) {
                    $type = $dec['mbtiType'];
                } elseif (isset($dec['mbti']['type'])) {
                    $type = $dec['mbti']['type'];
                } elseif (isset($dec['type'])) {
                    $type = $dec['type'];
                }
                $type = strtoupper(trim((string) $type));
                $seenUserIds[$uid] = true;
                if ($type === '') {
                    continue;
                }
                $mbtiTypes[$type] = ($mbtiTypes[$type] ?? 0) + 1;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $mbtiDistribution = [];
        foreach ($mbtiTypes as $type => $count) {
            $mbtiDistribution[] = ['type' => $type, 'count' => $count];
        }

        return success([
            'totalUsers' => $totalUsers,
            'testedUsers' => $testedUsers,
            'activeUsers' => $activeUsers,
            'userCards' => $userCards,
            'mbtiDistribution' => $mbtiDistribution
        ]);
    }

    /**
     * 测试用户列表：分页、关键词、池筛选、MBTI 筛选
     * GET /api/v1/superadmin/app-users?page=1&pageSize=20&keyword=&pool=all|individual|enterprise&enterpriseId=&mbti=
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $page = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $pageSize = min(max($pageSize, 1), 100);
        $keyword = trim(Request::param('keyword', ''));
        $pool = Request::param('pool', 'all');
        $enterpriseId = Request::param('enterpriseId', '');
        $mbti = trim(Request::param('mbti', ''));

        $where = [];
        if ($keyword !== '') {
            $where[] = ['nickname|phone|city|province', 'like', '%' . $keyword . '%'];
        }

        $wechatIds = null;
        if ($pool === 'individual' || ($pool === 'enterprise' && $enterpriseId !== '')) {
            try {
                $trQuery = Db::name('test_results');
                if ($pool === 'individual') {
                    $trQuery->where(function ($q) {
                        $q->whereNull('enterpriseId')->whereOr('enterpriseId', '');
                    });
                } else {
                    $trQuery->where('enterpriseId', $enterpriseId);
                }
                $wechatIds = $trQuery->distinct(true)->column('userId');
                $wechatIds = array_values(array_unique(array_filter($wechatIds)));
            } catch (\Throwable $e) {
                $wechatIds = null;
            }
        }
        if ($mbti !== '') {
            $mbtiUserIds = Db::name('test_results')->where('testType', 'mbti')->distinct(true)->column('userId');
            $mbtiUserIds = array_values(array_unique(array_filter($mbtiUserIds)));
            if ($wechatIds !== null) {
                $wechatIds = array_values(array_intersect($wechatIds, $mbtiUserIds));
            } else {
                $wechatIds = $mbtiUserIds;
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
        if ($where) {
            $baseQuery->where($where);
        }
        if ($wechatIds !== null && !empty($wechatIds)) {
            $baseQuery->where('id', 'in', array_intersect($dedupIds, $wechatIds));
        } elseif ($wechatIds !== null && empty($wechatIds)) {
            return paginate_response([], 0, $page, $pageSize);
        }

        $total = $baseQuery->count();
        $list = (clone $baseQuery)
            ->field('id,openid,nickname,avatar,phone,gender,country,province,city,status,lastLoginAt,createdAt')
            ->order('createdAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $ids = array_column($list, 'id');
        $testCounts = [];
        $lastTestAt = [];
        $testTypes = [];
        $userEnterprise = [];
        $payStats = [];
        if (!empty($ids)) {
            $counts = Db::name('test_results')->where('userId', 'in', $ids)->group('userId')->column('COUNT(*) as cnt', 'userId');
            $testCounts = $counts ?: [];
            $lastRows = Db::name('test_results')
                ->where('userId', 'in', $ids)
                ->field('id, userId, testType, resultData, createdAt')
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
                    'testType'   => $row['testType'],
                    'result'     => is_string($row['resultData'] ?? '') ? ($row['resultData'] ?? '') : json_encode($row['resultData'] ?? '', JSON_UNESCAPED_UNICODE),
                    'createdAt'  => $row['createdAt'],
                ];
            }
            try {
                $trWithE = Db::name('test_results')
                    ->where('userId', 'in', $ids)
                    ->where('enterpriseId', '<>', null)
                    ->where('enterpriseId', '<>', '')
                    ->field('userId, enterpriseId')
                    ->select();
                $eids = array_unique(array_filter(array_column($trWithE, 'enterpriseId')));
                $enterpriseNames = [];
                if (!empty($eids)) {
                    $enterpriseNames = Db::name('enterprises')->where('id', 'in', $eids)->column('name', 'id');
                }
                foreach ($trWithE as $r) {
                    if (!isset($userEnterprise[$r['userId']])) {
                        $userEnterprise[$r['userId']] = $enterpriseNames[$r['enterpriseId']] ?? ('企业' . $r['enterpriseId']);
                    }
                }
            } catch (\Throwable $e) {
                // test_results 可能无 enterpriseId 列
            }
            foreach ($ids as $uid) {
                if (!isset($userEnterprise[$uid])) {
                    $userEnterprise[$uid] = '个人用户(无企业)';
                }
            }

            // 从用户画像表汇总支付统计（付款次数与总金额）
            try {
                $profiles = Db::name('user_profile')
                    ->where('userId', 'in', $ids)
                    ->field('userId, SUM(paidOrders) AS paidOrders, SUM(totalPaidAmount) AS totalPaidAmount')
                    ->group('userId')
                    ->select()
                    ->toArray();
                foreach ($profiles as $p) {
                    $uid = (int) ($p['userId'] ?? 0);
                    if ($uid <= 0) {
                        continue;
                    }
                    $payStats[$uid] = [
                        'paidOrders'      => (int) ($p['paidOrders'] ?? 0),
                        'totalPaidAmount' => (int) ($p['totalPaidAmount'] ?? 0),
                    ];
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
            $row['enterprise'] = $userEnterprise[$id] ?? '个人用户(无企业)';

            $pay = $payStats[$id] ?? null;
            $totalPaidFen = $pay ? (int) ($pay['totalPaidAmount'] ?? 0) : 0;
            $row['paidOrders'] = $pay ? (int) ($pay['paidOrders'] ?? 0) : 0;
            $row['totalPaidAmount'] = $totalPaidFen;
            $row['totalPaidAmountYuan'] = $totalPaidFen > 0 ? round($totalPaidFen / 100, 2) : 0;
        }

        return paginate_response($list, $total, $page, $pageSize);
    }

    /**
     * 测试用户详情
     * GET /api/v1/superadmin/app-users/:id
     */
    public function detail($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
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

        $tests = Db::name('test_results')
            ->where('userId', $id)
            ->field('id, testType, resultData, createdAt, requiresPayment, isPaid, paidAmount, paidAt, orderId')
            ->order('createdAt', 'desc')
            ->select()
            ->toArray();
        foreach ($tests as &$t) {
            $raw = $t['resultData'] ?? '';
            $t['result'] = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
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

    private function parseMbtiFromResult($result): string
    {
        if (!is_string($result)) return '';
        $dec = json_decode($result, true);
        if (is_array($dec)) {
            return (string) ($dec['type'] ?? $dec['result'] ?? $dec['mbtiType'] ?? '');
        }
        return trim($result);
    }

    private function extractResultType(array $tests, string $type): string
    {
        $targetType = strtolower($type);
        foreach ($tests as $t) {
            if (strtolower($t['testType'] ?? '') !== $targetType) {
                continue;
            }
            $result = $t['result'] ?? '';
            if (!is_string($result)) {
                continue;
            }
            $dec = json_decode($result, true);
            if (!is_array($dec)) {
                // 无法解析 JSON 时，直接返回原始字符串
                return $targetType === 'face' ? '人脸分析' : trim($result);
            }

            // 人脸分析：有记录就返回固定标签
            if ($targetType === 'face') {
                return '人脸分析';
            }

            // MBTI：直接读 mbtiType/type
            if ($targetType === 'mbti') {
                return (string) ($dec['mbtiType'] ?? $dec['type'] ?? $dec['result'] ?? '');
            }

            // DISC：优先 description.type，然后 dominantType
            if ($targetType === 'disc') {
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                if (!empty($dec['dominantType'])) {
                    return (string) $dec['dominantType'];
                }
                return (string) ($dec['disc'] ?? '');
            }

            // PDP：优先 description.type，然后 dominantType
            if ($targetType === 'pdp') {
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                if (!empty($dec['dominantType'])) {
                    return (string) $dec['dominantType'];
                }
                return (string) ($dec['pdp'] ?? '');
            }

            // 兜底：尝试常见字段
            return (string) ($dec['type'] ?? $dec['result'] ?? '');
        }
        return '';
    }

    /**
     * 从人脸分析结果中提取对应的 MBTI / DISC / PDP 文本
     */
    private function extractFaceSubType(array $tests, string $subType): string
    {
        $target = strtolower($subType);
        foreach ($tests as $t) {
            if (strtolower($t['testType'] ?? '') !== 'face') {
                continue;
            }
            $result = $t['result'] ?? '';
            if (!is_string($result)) {
                continue;
            }
            $dec = json_decode($result, true);
            if (!is_array($dec)) {
                continue;
            }

            if ($target === 'mbti') {
                if (!empty($dec['mbti']['type'])) {
                    return (string) $dec['mbti']['type'];
                }
                if (!empty($dec['mbtiType'])) {
                    return (string) $dec['mbtiType'];
                }
            } elseif ($target === 'disc') {
                if (!empty($dec['disc']['primary'])) {
                    return (string) $dec['disc']['primary'];
                }
                if (!empty($dec['disc'])) {
                    return (string) $dec['disc'];
                }
            } elseif ($target === 'pdp') {
                if (!empty($dec['pdp']['primary'])) {
                    return (string) $dec['pdp']['primary'];
                }
                if (!empty($dec['pdp'])) {
                    return (string) $dec['pdp'];
                }
            }
        }
        return '';
    }

}
