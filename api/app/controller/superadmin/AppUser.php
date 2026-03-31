<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\controller\admin\concern\ExtractsTestResults;
use think\facade\Db;
use think\facade\Request;

/**
 * 超管 - 测试用户（小程序用户）管理
 * 数据来源：wechat_users，测试记录表（物理表名一般为 mbti_test_results，逻辑使用 Db::name('test_results')）
 */
class AppUser extends BaseController
{
    use ExtractsTestResults;

    /**
     * 名称包含「存客宝」的企业（取 id 最小的一条），用于合并个人侧无归属测试数据
     */
    private function resolveCunkbaoEnterpriseId(): ?int
    {
        try {
            $row = Db::name('enterprises')->where('name', 'like', '%存客宝%')->order('id', 'asc')->find();
            return $row ? (int) $row['id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 从 test_results.resultData 中解析 MBTI 四字母（与概览统计口径一致）
     */
    private function parseOverviewMbtiTypeFromResultData($raw): string
    {
        $dec = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);
        if (!is_array($dec)) {
            return '';
        }
        $type = '';
        if (isset($dec['mbtiType'])) {
            $type = $dec['mbtiType'];
        } elseif (isset($dec['mbti']['type'])) {
            $type = $dec['mbti']['type'];
        } elseif (isset($dec['type'])) {
            $type = $dec['type'];
        }

        return strtoupper(trim((string) $type));
    }

    /**
     * 概览：用户统计、卡片、MBTI 分布
     * GET /api/v1/superadmin/app-users/overview
     *
     * 性能：openid 去重与各统计均在库内 JOIN/聚合完成，禁止拉全表 mid、全表 distinct userId、全量 MBTI 行。
     */
    public function overview()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $last30d = time() - 30 * 86400;

        // ========== 统计全部基于 wechat_users.enterpriseId（而非 test_results.enterpriseId） ==========
        try {
            $dedupSql = Db::name('wechat_users')
                ->alias('w2')
                ->field('w2.openid, MAX(w2.id) AS mid')
                ->group('w2.openid')
                ->buildSql(true);

            $trTestedSql = Db::name('test_results')->distinct(true)->field('userId')->buildSql(true);
            $trActiveSql = Db::name('test_results')
                ->where('createdAt', '>=', $last30d)
                ->distinct(true)
                ->field('userId')
                ->buildSql(true);

            $totalUsers = (int) Db::name('wechat_users')->alias('w')
                ->join([$dedupSql => 'd'], 'w.id = d.mid')
                ->count();

            $testedRow = Db::name('test_results')->alias('tr')
                ->join([$dedupSql => 'd'], 'tr.userId = d.mid')
                ->field('COUNT(DISTINCT tr.userId) AS c')
                ->find();
            $testedUsers = (int) ($testedRow['c'] ?? 0);

            $activeRow = Db::name('test_results')->alias('tr')
                ->join([$dedupSql => 'd'], 'tr.userId = d.mid')
                ->where('tr.createdAt', '>=', $last30d)
                ->field('COUNT(DISTINCT tr.userId) AS c')
                ->find();
            $activeUsers = (int) ($activeRow['c'] ?? 0);

            $eidExpr = "CASE WHEN w.enterpriseId IS NULL OR w.enterpriseId = '' OR w.enterpriseId = 0 THEN 0 ELSE w.enterpriseId END";

            $aggRows = Db::name('wechat_users')->alias('w')
                ->join([$dedupSql => 'ded'], 'w.id = ded.mid')
                ->leftJoin([$trTestedSql => 'tt'], 'tt.userId = w.id')
                ->leftJoin([$trActiveSql => 'ta'], 'ta.userId = w.id')
                ->field($eidExpr . ' AS eid, COUNT(*) AS total, SUM(IF(tt.userId IS NOT NULL, 1, 0)) AS tested, SUM(IF(ta.userId IS NOT NULL, 1, 0)) AS active')
                ->group($eidExpr)
                ->select()
                ->toArray();

            $byEid = [];
            foreach ($aggRows as $row) {
                $byEid[(int) ($row['eid'] ?? 0)] = [
                    'total'  => (int) ($row['total'] ?? 0),
                    'tested' => (int) ($row['tested'] ?? 0),
                    'active' => (int) ($row['active'] ?? 0),
                ];
            }

            $userCards = [
                [
                    'type'   => 'all',
                    'name'   => '全部用户',
                    'total'  => $totalUsers,
                    'active' => $activeUsers,
                    'tested' => $testedUsers,
                ],
            ];

            $enterprises = Db::name('enterprises')->field('id,name')->select()->toArray();
            foreach ($enterprises as $e) {
                $eid = (int) $e['id'];
                $st = $byEid[$eid] ?? ['total' => 0, 'active' => 0, 'tested' => 0];
                $userCards[] = [
                    'type'         => 'enterprise',
                    'enterpriseId' => $eid,
                    'name'         => $e['name'] ?? ('企业' . $eid),
                    'total'        => $st['total'],
                    'active'       => $st['active'],
                    'tested'       => $st['tested'],
                ];
            }

            $ind = $byEid[0] ?? null;
            if ($ind !== null && ($ind['total'] ?? 0) > 0) {
                $userCards[] = [
                    'type'   => 'individual',
                    'name'   => '个人用户(无企业)',
                    'total'  => $ind['total'],
                    'active' => $ind['active'],
                    'tested' => $ind['tested'],
                ];
            }

            // MBTI：每人仅最新一条（MAX(id)），且须在 openid 去重后的用户集合内
            $mbtiTypes = [];
            try {
                $mbtiLatestSub = Db::name('test_results')
                    ->where('testType', 'mbti')
                    ->field('userId, MAX(id) AS mid')
                    ->group('userId')
                    ->buildSql(true);

                $mbtiRows = Db::name('test_results')->alias('t')
                    ->join([$mbtiLatestSub => 'lm'], 't.userId = lm.userId AND t.id = lm.mid')
                    ->join([$dedupSql => 'd'], 't.userId = d.mid')
                    ->column('t.resultData');

                foreach ($mbtiRows as $raw) {
                    $type = $this->parseOverviewMbtiTypeFromResultData($raw);
                    if ($type === '') {
                        continue;
                    }
                    $mbtiTypes[$type] = ($mbtiTypes[$type] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $mbtiTypes = [];
            }

            $mbtiDistribution = [];
            foreach ($mbtiTypes as $type => $count) {
                $mbtiDistribution[] = ['type' => $type, 'count' => $count];
            }

            return success([
                'totalUsers'        => $totalUsers,
                'testedUsers'       => $testedUsers,
                'activeUsers'       => $activeUsers,
                'userCards'         => $userCards,
                'mbtiDistribution'  => $mbtiDistribution,
            ]);
        } catch (\Throwable $e) {
            // 极少数环境无法 buildSql / JOIN 时降级（仍可能较慢，但保证有数据）
        }

        try {
            $dedupIds = Db::name('wechat_users')->field('openid, MAX(id) as mid')->group('openid')->column('mid');
            $dedupIds = $dedupIds ? array_values(array_filter($dedupIds)) : [];
        } catch (\Throwable $e) {
            $dedupIds = Db::name('wechat_users')->column('id');
            $dedupIds = $dedupIds ? array_values(array_filter($dedupIds)) : [];
        }
        $totalUsers = count($dedupIds);

        $testedUserIds = Db::name('test_results')->distinct(true)->column('userId');
        $testedUserIds = array_values(array_unique(array_filter(array_map('intval', $testedUserIds))));
        $testedUsers = count(array_intersect($testedUserIds, $dedupIds));

        $activeUserIds = Db::name('test_results')
            ->where('createdAt', '>=', $last30d)
            ->distinct(true)
            ->column('userId');
        $activeUserIds = array_values(array_unique(array_filter(array_map('intval', $activeUserIds))));
        $activeUsers = count(array_intersect($activeUserIds, $dedupIds));

        $userCards = [
            [
                'type' => 'all',
                'name' => '全部用户',
                'total' => $totalUsers,
                'active' => $activeUsers,
                'tested' => $testedUsers
            ]
        ];

        $userEidMap = Db::name('wechat_users')->where('id', 'in', $dedupIds)->column('enterpriseId', 'id');

        $enterprises = Db::name('enterprises')->field('id,name')->select()->toArray();
        foreach ($enterprises as $e) {
            $eid = (int) $e['id'];
            $eidUsers = array_keys(array_filter($userEidMap, function ($v) use ($eid) {
                return (int) $v === $eid;
            }));
            $total = count($eidUsers);
            $active = empty($eidUsers) ? 0 : count(array_intersect($activeUserIds, $eidUsers));
            $tested = empty($eidUsers) ? 0 : count(array_intersect($testedUserIds, $eidUsers));

            $userCards[] = [
                'type' => 'enterprise',
                'enterpriseId' => $eid,
                'name' => $e['name'] ?? ('企业' . $eid),
                'total' => $total,
                'active' => $active,
                'tested' => $tested
            ];
        }

        $individualUsers = array_keys(array_filter($userEidMap, function ($v) {
            return $v === null || $v === '' || (int) $v === 0;
        }));
        $individualTotal = count($individualUsers);
        if ($individualTotal > 0) {
            $individualActive = count(array_intersect($activeUserIds, $individualUsers));
            $individualTested = count(array_intersect($testedUserIds, $individualUsers));
            $userCards[] = [
                'type' => 'individual',
                'name' => '个人用户(无企业)',
                'total' => $individualTotal,
                'active' => $individualActive,
                'tested' => $individualTested
            ];
        }

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
                $type = $this->parseOverviewMbtiTypeFromResultData($r['resultData'] ?? '');
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

        // 与管理后台一致：openid 去重在库内用子查询 JOIN，禁止把全表 mid 拉进 PHP 再 whereIn
        try {
            $dedupSql = Db::name('wechat_users')
                ->alias('w2')
                ->field('w2.openid, MAX(w2.id) AS mid')
                ->group('w2.openid')
                ->buildSql(true);
        } catch (\Throwable $e) {
            return paginate_response([], 0, $page, $pageSize);
        }

        $baseQuery = Db::name('wechat_users')->alias('w')
            ->join([$dedupSql => 'd'], 'w.id = d.mid');

        // 含表别名时用 whereLike+whereOr 链式会在部分 ThinkPHP 版本报「查询表达式错误:W.PHONE」，改 whereRaw 绑定
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $baseQuery->whereRaw(
                '(w.nickname LIKE ? OR w.phone LIKE ? OR w.city LIKE ? OR w.province LIKE ?)',
                [$like, $like, $like, $like]
            );
        }

        // 池筛选：直接基于 wechat_users.enterpriseId
        if ($pool === 'individual') {
            $baseQuery->where(function ($q) {
                $q->whereNull('w.enterpriseId')->whereOr('w.enterpriseId', '')->whereOr('w.enterpriseId', 0);
            });
        } elseif ($pool === 'enterprise' && $enterpriseId !== '') {
            $baseQuery->where('w.enterpriseId', (int) $enterpriseId);
        }

        // MBTI 筛选（与历史行为一致：params.mbti 非空则只保留「有过 MBTI 测评」的用户；在库内 JOIN 替代全表 distinct userId）
        if ($mbti !== '') {
            try {
                $hasMbtiSql = Db::name('test_results')
                    ->where('testType', 'mbti')
                    ->field('userId')
                    ->group('userId')
                    ->buildSql(true);
                $baseQuery->join([$hasMbtiSql => 'hm'], 'w.id = hm.userId');
            } catch (\Throwable $e) {
                return paginate_response([], 0, $page, $pageSize);
            }
        }

        $total = (int) (clone $baseQuery)->count();
        $list = (clone $baseQuery)
            ->field('w.id,w.openid,w.nickname,w.avatar,w.phone,w.gender,w.country,w.province,w.city,w.status,w.lastLoginAt,w.createdAt,w.enterpriseId')
            ->order('w.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $ids = array_column($list, 'id');
        $testCounts = [];
        $lastTestAt = [];
        $testTypes = [];
        $payStats = [];
        $enterpriseNames = [];
        if (!empty($ids)) {
            $trBase = Db::name('test_results')->where('userId', 'in', $ids);
            $trAggRows = (clone $trBase)
                ->field('userId, COUNT(*) AS cnt, MAX(createdAt) AS lastAt')
                ->group('userId')
                ->select()
                ->toArray();
            foreach ($trAggRows as $r) {
                $uid = (int) ($r['userId'] ?? 0);
                if ($uid > 0) {
                    $testCounts[$uid] = (int) ($r['cnt'] ?? 0);
                    $lastTestAt[$uid] = $r['lastAt'];
                }
            }

            // 每人每种 testType 仅取最新一条（含 MAX(id)），与管理端一致，禁止拉全量 test_results
            try {
                $aggSub = Db::name('test_results')->where('userId', 'in', $ids);
                $aggSql = $aggSub
                    ->field('userId, testType, MAX(createdAt) as mc, MAX(id) as mid')
                    ->group('userId, testType')
                    ->buildSql(true);

                $lastRows = Db::name('test_results')->alias('t')
                    ->join([$aggSql => 'agg'], 't.userId = agg.userId AND t.testType = agg.testType AND t.id = agg.mid')
                    ->field('t.id, t.userId, t.testType, t.resultData, t.createdAt, t.enterpriseId as testEnterpriseId')
                    ->select()
                    ->toArray();
            } catch (\Throwable $e) {
                $lastRows = (clone $trBase)
                    ->field('id, userId, testType, resultData, createdAt, enterpriseId as testEnterpriseId')
                    ->order('createdAt', 'desc')
                    ->limit(2000)
                    ->select()
                    ->toArray();
            }

            foreach ($lastRows as $row) {
                $uid = $row['userId'];
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
            foreach ($testTypes as $uid => &$tlist) {
                usort($tlist, static function ($a, $b) {
                    return (int) ($b['createdAt'] ?? 0) <=> (int) ($a['createdAt'] ?? 0);
                });
            }
            unset($tlist);

            $allEids = [];
            foreach ($list as $lr) {
                $eid = isset($lr['enterpriseId']) ? (int) $lr['enterpriseId'] : 0;
                if ($eid > 0) {
                    $allEids[] = $eid;
                }
            }
            $allEids = array_values(array_unique($allEids));
            if (!empty($allEids)) {
                $enterpriseNames = Db::name('enterprises')->where('id', 'in', $allEids)->column('name', 'id');
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
            $eidRow = isset($row['enterpriseId']) ? (int) $row['enterpriseId'] : 0;
            $row['enterprise'] = ($eidRow > 0 && isset($enterpriseNames[$eidRow]))
                ? $enterpriseNames[$eidRow]
                : '个人用户(无企业)';

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
            ->field('id, testType, resultData, enterpriseId as testEnterpriseId, createdAt, requiresPayment, isPaid, paidAmount, paidAt, orderId')
            ->order('createdAt', 'desc')
            ->select()
            ->toArray();
        foreach ($tests as &$t) {
            $raw = $t['resultData'] ?? '';
            $t['result'] = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
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

        $data['matchingEnterprises'] = $this->buildMatchingEnterprises(
            (int) $id,
            (string) ($data['mbtiType'] ?? ''),
            (string) ($data['pdpType'] ?? ''),
            (string) ($data['discType'] ?? '')
        );

        return success($data);
    }

    /**
     * 单条测试记录详情（与 testList 中单条结构一致）
     * GET /api/v1/superadmin/test-records/:id
     */
    public function testRecord($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $testId = (int) $id;
        if ($testId <= 0) {
            return error('记录ID无效', 400);
        }

        $tr = Db::name('test_results')->where('id', $testId)->find();
        if (!$tr) {
            return error('记录不存在', 404);
        }

        $wechatUserId = (int) ($tr['userId'] ?? 0);
        if ($wechatUserId <= 0) {
            return error('记录数据异常', 400);
        }

        $raw = $tr['resultData'] ?? '';
        $out = [
            'id'                => $testId,
            'userId'            => $wechatUserId,
            'testType'          => $tr['testType'] ?? '',
            'result'            => is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE),
            'createdAt'         => $tr['createdAt'] ?? null,
            'requiresPayment'   => (int) ($tr['requiresPayment'] ?? 0),
            'isPaid'            => (int) ($tr['isPaid'] ?? 0),
            'paidAmount'        => isset($tr['paidAmount']) ? (int) $tr['paidAmount'] : null,
            'paidAt'            => $tr['paidAt'] ?? null,
            'orderId'           => isset($tr['orderId']) ? (int) $tr['orderId'] : null,
            'testScope'         => !empty($tr['enterpriseId']) ? 'enterprise' : 'personal',
        ];

        return success($out);
    }

    /**
     * 按企业测评池内与用户 MBTI/PDP/DISC 的同质比例推荐企业，并附带登记负责人联系方式。
     * 仅超级管理后台使用；无测评维度时按池内活跃人数近似排序。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMatchingEnterprises(int $userId, string $userMbti, string $userPdp, string $userDisc): array
    {
        $userMbtiU = strtoupper(preg_replace('/[^A-Z]/', '', $userMbti));
        $userPdpN = $this->normalizePoolTypeKey($userPdp);
        $userDiscN = $this->normalizePoolTypeKey($userDisc);

        try {
            $entRows = Db::name('enterprises')
                ->whereNull('deletedAt')
                ->whereIn('status', ['operating', 'trial'])
                ->field('id,name,code,contactName,contactPhone,contactEmail,status')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        if (!$entRows) {
            return [];
        }

        $scored = [];
        foreach ($entRows as $e) {
            $eid = (int) ($e['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }

            $mbtiS = $this->enterprisePoolTypeHistogram($eid, 'mbti');
            $pdpS = $this->enterprisePoolTypeHistogram($eid, 'pdp');
            $discS = $this->enterprisePoolTypeHistogram($eid, 'disc');

            $score = 36;
            $reasons = [];

            if ($userMbtiU !== '' && $mbtiS['total'] > 0) {
                $hit = (int) ($mbtiS['byKey'][$userMbtiU] ?? 0);
                $ratio = $hit / $mbtiS['total'];
                $part = (int) round(44 * $ratio);
                $score += $part;
                $pct = (int) round($ratio * 100);
                $reasons[] = 'MBTI 同质 ' . $pct . '%（池内 ' . $mbtiS['total'] . ' 人有效结果）';
            }

            if ($userPdpN !== '' && $pdpS['total'] > 0) {
                $hit = (int) ($pdpS['byKey'][$userPdpN] ?? 0);
                $ratio = $hit / $pdpS['total'];
                $score += (int) round(12 * $ratio);
                if ($ratio > 0) {
                    $reasons[] = 'PDP 同质 ' . (int) round($ratio * 100) . '%';
                }
            }

            if ($userDiscN !== '' && $discS['total'] > 0) {
                $hit = (int) ($discS['byKey'][$userDiscN] ?? 0);
                $ratio = $hit / $discS['total'];
                $score += (int) round(12 * $ratio);
                if ($ratio > 0) {
                    $reasons[] = 'DISC 同质 ' . (int) round($ratio * 100) . '%';
                }
            }

            $tested = max($mbtiS['total'], $pdpS['total'], $discS['total']);
            if ($userMbtiU === '' && $userPdpN === '' && $userDiscN === '') {
                $score = 40 + (int) min(38, $tested * 2);
                $reasons[] = $tested > 0 ? '按池内测评活跃度推荐' : '暂无同质维度，展示登记企业';
            }

            $score = max(30, min(99, $score));

            $typeLabel = '综合型';
            if ($userMbtiU !== '' && $mbtiS['total'] > 0 && (($mbtiS['byKey'][$userMbtiU] ?? 0) / $mbtiS['total']) >= 0.25) {
                $typeLabel = '文化相近（MBTI 分布）';
            } elseif ($userPdpN !== '' && $pdpS['total'] > 0) {
                $typeLabel = '行为风格相近（PDP 分布）';
            } elseif ($userDiscN !== '' && $discS['total'] > 0) {
                $typeLabel = '协作风格相近（DISC 分布）';
            }

            $scored[] = [
                'id' => $eid,
                'name' => (string) ($e['name'] ?? ''),
                'code' => (string) ($e['code'] ?? ''),
                'contactName' => (string) ($e['contactName'] ?? ''),
                'contactPhone' => (string) ($e['contactPhone'] ?? ''),
                'contactEmail' => (string) ($e['contactEmail'] ?? ''),
                'status' => (string) ($e['status'] ?? ''),
                'matchScore' => $score,
                'matchTypeLabel' => $typeLabel,
                'matchReason' => $reasons ? implode('；', $reasons) : '可与负责人沟通用人匹配',
                'poolTestedUsers' => $tested,
            ];
        }

        usort($scored, static function ($a, $b) {
            return ($b['matchScore'] ?? 0) <=> ($a['matchScore'] ?? 0);
        });

        return array_slice($scored, 0, 10);
    }

    /**
     * 企业池内各用户对某测评类型的「最新一条」结果类型分布
     *
     * @return array{total:int,byKey:array<string,int>}
     */
    private function enterprisePoolTypeHistogram(int $enterpriseId, string $testType): array
    {
        $targetType = strtolower($testType);
        try {
            $rows = Db::name('test_results')
                ->where('enterpriseId', $enterpriseId)
                ->where('testType', $testType)
                ->order('createdAt', 'desc')
                ->field('userId,resultData')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return ['total' => 0, 'byKey' => []];
        }

        $seen = [];
        $byKey = [];
        foreach ($rows as $r) {
            $uid = (int) ($r['userId'] ?? 0);
            if ($uid <= 0 || isset($seen[$uid])) {
                continue;
            }

            $raw = $r['resultData'] ?? '';
            $result = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
            $mock = [['result' => $result, 'testType' => $testType]];
            $label = $this->extractResultType($mock, $testType);
            if ($label === '' || ($targetType === 'face' && $label === '人脸分析')) {
                continue;
            }

            $key = $targetType === 'mbti'
                ? strtoupper(preg_replace('/[^A-Z]/', '', $label))
                : $this->normalizePoolTypeKey($label);
            if ($key === '') {
                continue;
            }
            $seen[$uid] = true;
            $byKey[$key] = ($byKey[$key] ?? 0) + 1;
        }

        $totalTyped = array_sum($byKey);

        return ['total' => $totalTyped, 'byKey' => $byKey];
    }

    private function normalizePoolTypeKey(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $s = str_replace([' ', '　'], '', $s);

        return mb_strtolower($s, 'UTF-8');
    }

}
