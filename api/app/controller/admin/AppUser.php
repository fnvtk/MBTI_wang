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
        $coldFaceLevelRaw = Request::param('coldFaceLevel', '');
        $coldFaceLevels = [];
        if (is_array($coldFaceLevelRaw)) {
            $coldFaceLevels = array_values(array_filter(array_map('strval', $coldFaceLevelRaw)));
        } elseif (is_string($coldFaceLevelRaw) && $coldFaceLevelRaw !== '') {
            $coldFaceLevels = array_values(array_filter(array_map('trim', explode(',', $coldFaceLevelRaw))));
        }
        $coldFaceLevels = array_values(array_intersect($coldFaceLevels, ['cold', 'neutral', 'warm']));

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

        $baseQuery = null;

        // 企业场景：禁止 column 全量 userId + 超大 whereIn，改为「画像池子 + openid 去重」子查询 JOIN（全在库内完成）
        if ($enterpriseId) {
            $eid = (int) $enterpriseId;
            // 子查询会在 SQL 中出现两次，条件用字面量避免占位符绑定重复/错位的风险
            $poolSql = Db::name('user_profile')
                ->whereRaw('enterpriseId = ' . $eid)
                ->group('userId')
                ->field('userId')
                ->buildSql(true);

            try {
                $dedupSql = Db::name('wechat_users')
                    ->alias('w2')
                    ->join([$poolSql => 'p2'], 'w2.id = p2.userId')
                    ->field('w2.openid, MAX(w2.id) AS mid')
                    ->group('w2.openid')
                    ->buildSql(true);
            } catch (\Throwable $e) {
                return paginate_response([], 0, $page, $pageSize);
            }

            $baseQuery = Db::name('wechat_users')->alias('w')
                ->join([$poolSql => 'p'], 'w.id = p.userId')
                ->join([$dedupSql => 'd'], 'w.id = d.mid');

            if (!empty($coldFaceLevels)) {
                try {
                    $cfSql = Db::name('user_profile')
                        ->whereRaw('enterpriseId = ' . $eid)
                        ->whereIn('coldFaceLevel', $coldFaceLevels)
                        ->field('userId')
                        ->buildSql(true);
                    $baseQuery->join([$cfSql => 'cf'], 'w.id = cf.userId');
                } catch (\Throwable $e) {
                    // coldFace 字段不存在时忽略筛选
                }
            }

            if ($keyword !== '') {
                $like = '%' . addcslashes($keyword, '%_\\') . '%';
                $baseQuery->whereRaw(
                    '(w.nickname LIKE ? OR w.phone LIKE ? OR w.city LIKE ? OR w.province LIKE ?)',
                    [$like, $like, $like, $like]
                );
            }
        } else {
            // 无企业归属（极少）：沿用全表 openid 去重 + IN 列表
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
        }

        if ($enterpriseId) {
            $total = (int) (clone $baseQuery)->distinct(true)->count('w.id');
            // 先按「去重后的 w.id」分页，再拉全字段；避免 JOIN 放大行数导致 LIMIT 作用在重复用户上，
            // 进而出现「一页里混入全库统计感」或本页人数与 pageSize 不一致。
            $idRows = (clone $baseQuery)
                ->field('w.id')
                ->group('w.id')
                ->order('w.id', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
            $orderedIds = array_values(array_filter(array_map('intval', array_column($idRows, 'id'))));
            if ($orderedIds === []) {
                $list = [];
            } else {
                $rows = Db::name('wechat_users')->alias('w')
                    ->whereIn('w.id', $orderedIds)
                    ->field('w.id,w.nickname,w.openid,w.avatar,w.phone,w.gender,w.country,w.province,w.city,w.status,w.lastLoginAt,w.createdAt')
                    ->select()
                    ->toArray();
                $byId = [];
                foreach ($rows as $r) {
                    $byId[(int) ($r['id'] ?? 0)] = $r;
                }
                $list = [];
                foreach ($orderedIds as $oid) {
                    if (isset($byId[$oid])) {
                        $list[] = $byId[$oid];
                    }
                }
            }
        } else {
            $total = (int) (clone $baseQuery)->count();
            $list = (clone $baseQuery)
                ->field('id,nickname,openid,avatar,phone,gender,country,province,city,status,lastLoginAt,createdAt')
                ->order('id', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
        }

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
        $coopMap = [];
        if (!empty($ids)) {
            // 测试统计严格按 test_results.enterpriseId 归属企业过滤
            $trBase = Db::name('test_results')->where('userId', 'in', $ids);
            if ($enterpriseId) {
                $trBase->where('enterpriseId', $enterpriseId);
            }
            // 次数与最近测试时间一次 GROUP 查完，减少往返
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

            // 每人每种 testType 仅取最新一条（含 MAX(id) 消解同一时间戳多行），禁止 select 全量记录
            try {
                $aggSub = Db::name('test_results')->where('userId', 'in', $ids);
                if ($enterpriseId) {
                    $aggSub->where('enterpriseId', $enterpriseId);
                }
                $aggSql = $aggSub
                    ->field('userId, testType, MAX(createdAt) as mc, MAX(id) as mid')
                    ->group('userId, testType')
                    ->buildSql(true);

                $lastDetailQuery = Db::name('test_results')->alias('t')
                    ->join([$aggSql => 'agg'], 't.userId = agg.userId AND t.testType = agg.testType AND t.id = agg.mid')
                    ->field('t.id, t.userId, t.testType, t.resultData, t.createdAt, t.enterpriseId as testEnterpriseId');
                if ($enterpriseId) {
                    $lastDetailQuery->where('t.enterpriseId', $enterpriseId);
                }
                $lastRows = $lastDetailQuery->select()->toArray();
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

            // 冷脸分字段（容错：迁移未执行时 coldFace* 字段缺失，查询异常则视为全空）
            $coldFaceMap = [];
            try {
                $cfQuery = Db::name('user_profile')->where('userId', 'in', $ids);
                if ($enterpriseId) {
                    $cfQuery->where('enterpriseId', $enterpriseId);
                }
                $cfRows = $cfQuery
                    ->field('userId, coldFaceScore, coldFaceLevel, coldFaceUpdatedAt')
                    ->select()
                    ->toArray();
                foreach ($cfRows as $r) {
                    $uid = (int) ($r['userId'] ?? 0);
                    if ($uid > 0) {
                        $coldFaceMap[$uid] = [
                            'score' => isset($r['coldFaceScore']) && $r['coldFaceScore'] !== null ? (int) $r['coldFaceScore'] : null,
                            'level' => $r['coldFaceLevel'] ?? null,
                            'updatedAt' => isset($r['coldFaceUpdatedAt']) ? (int) $r['coldFaceUpdatedAt'] : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $coldFaceMap = [];
            }

            // 本企业下用户合作意向（user_cooperation_choices 唯一 key：userId + enterpriseId）
            if ($enterpriseId) {
                try {
                    $eid = (int) $enterpriseId;
                    $coopRows = Db::name('user_cooperation_choices')->alias('uc')
                        ->leftJoin('enterprise_cooperation_modes ecm', 'ecm.enterpriseId = uc.enterpriseId AND ecm.modeCode = uc.modeCode')
                        ->whereIn('uc.userId', $ids)
                        ->where('uc.enterpriseId', $eid)
                        ->field('uc.userId, uc.modeCode, uc.chosenAt, uc.updatedAt, ecm.title as modeTitle')
                        ->select()
                        ->toArray();
                    foreach ($coopRows as $cr) {
                        $uCo = (int) ($cr['userId'] ?? 0);
                        if ($uCo > 0) {
                            $coopMap[$uCo] = [
                                'modeCode'  => (string) ($cr['modeCode'] ?? ''),
                                'modeTitle' => (string) ($cr['modeTitle'] ?? ''),
                                'chosenAt'  => (int) ($cr['chosenAt'] ?? 0),
                                'updatedAt' => (int) ($cr['updatedAt'] ?? 0),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $coopMap = [];
                }
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
            $row['sbtiType'] = $this->extractResultType($testsForUser, 'sbti');
            $row['faceType'] = $this->extractResultType($testsForUser, 'face');
            $row['faceMbtiType'] = $this->extractFaceSubType($testsForUser, 'mbti');
            $row['faceDiscType'] = $this->extractFaceSubType($testsForUser, 'disc');
            $row['facePdpType'] = $this->extractFaceSubType($testsForUser, 'pdp');
            $row['enterprise'] = $enterpriseName !== null ? $enterpriseName : '全部';
            $pay = $payStats[$id] ?? null;
            $row['paidOrders'] = $pay ? (int) ($pay['paidOrders'] ?? 0) : 0;
            $row['totalPaidAmount'] = $pay ? (int) ($pay['totalPaidAmount'] ?? 0) : 0;

            $cf = $coldFaceMap[$id] ?? null;
            if ((!$cf || $cf['score'] === null) && !empty($testsForUser)) {
                $calc = $this->calcColdFace($testsForUser);
                if ($calc) {
                    $this->writeColdFace((int) $id, $enterpriseId ? (int) $enterpriseId : null, $calc);
                    $cf = ['score' => $calc['score'], 'level' => $calc['level'], 'updatedAt' => time()];
                }
            }
            $row['coldFaceScore'] = $cf && $cf['score'] !== null ? (int) $cf['score'] : null;
            $row['coldFaceLevel'] = $cf && !empty($cf['level']) ? (string) $cf['level'] : null;
            $row['coldFaceUpdatedAt'] = $cf ? ($cf['updatedAt'] ?? null) : null;

            $co = $coopMap[$id] ?? null;
            if ($co && ($co['modeCode'] !== '' || $co['modeTitle'] !== '' || (int) ($co['chosenAt'] ?? 0) > 0)) {
                $row['cooperationModeCode']  = $co['modeCode'] !== '' ? $co['modeCode'] : null;
                $row['cooperationModeTitle'] = $co['modeTitle'] !== '' ? $co['modeTitle'] : null;
                $chAt                        = (int) ($co['chosenAt'] ?? 0);
                $row['cooperationChosenAt']  = $chAt > 0 ? $chAt : null;
            } else {
                $row['cooperationModeCode']  = null;
                $row['cooperationModeTitle'] = null;
                $row['cooperationChosenAt']  = null;
            }
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
        $data['sbtiType'] = $this->extractResultType($tests, 'sbti');
        $data['faceType'] = $this->extractResultType($tests, 'face');
        $data['faceMbtiType'] = $this->extractFaceSubType($tests, 'mbti');
        $data['faceDiscType'] = $this->extractFaceSubType($tests, 'disc');
        $data['facePdpType'] = $this->extractFaceSubType($tests, 'pdp');

        // 冷脸分字段（详情）
        $coldFace = null;
        try {
            $cfQuery = Db::name('user_profile')->where('userId', $id);
            if ($enterpriseId) {
                $cfQuery->where('enterpriseId', $enterpriseId);
            }
            $cfRow = $cfQuery->field('coldFaceScore, coldFaceLevel, coldFaceUpdatedAt')->find();
            if ($cfRow) {
                $coldFace = [
                    'score' => $cfRow['coldFaceScore'] !== null ? (int) $cfRow['coldFaceScore'] : null,
                    'level' => $cfRow['coldFaceLevel'] ?? null,
                    'updatedAt' => isset($cfRow['coldFaceUpdatedAt']) ? (int) $cfRow['coldFaceUpdatedAt'] : null,
                ];
            }
        } catch (\Throwable $e) {
            $coldFace = null;
        }
        if ((!$coldFace || $coldFace['score'] === null) && !empty($tests)) {
            $calc = $this->calcColdFace($tests);
            if ($calc) {
                $this->writeColdFace((int) $id, $enterpriseId ? (int) $enterpriseId : null, $calc);
                $coldFace = ['score' => $calc['score'], 'level' => $calc['level'], 'updatedAt' => time()];
            }
        }
        $data['coldFaceScore'] = $coldFace['score'] ?? null;
        $data['coldFaceLevel'] = $coldFace['level'] ?? null;
        $data['coldFaceUpdatedAt'] = $coldFace['updatedAt'] ?? null;

        $data['cooperationModeCode'] = null;
        $data['cooperationModeTitle'] = null;
        $data['cooperationChosenAt'] = null;
        if ($enterpriseId) {
            try {
                $cr = Db::name('user_cooperation_choices')->alias('uc')
                    ->leftJoin('enterprise_cooperation_modes ecm', 'ecm.enterpriseId = uc.enterpriseId AND ecm.modeCode = uc.modeCode')
                    ->where('uc.userId', $id)
                    ->where('uc.enterpriseId', (int) $enterpriseId)
                    ->field('uc.modeCode, uc.chosenAt, ecm.title as modeTitle')
                    ->find();
                if ($cr) {
                    $mc  = trim((string) ($cr['modeCode'] ?? ''));
                    $mt  = trim((string) ($cr['modeTitle'] ?? ''));
                    $data['cooperationModeCode']  = $mc !== '' ? $mc : null;
                    $data['cooperationModeTitle'] = $mt !== '' ? $mt : null;
                    $chAt = (int) ($cr['chosenAt'] ?? 0);
                    $data['cooperationChosenAt'] = $chAt > 0 ? $chAt : null;
                }
            } catch (\Throwable $e) {
                // 表未迁移时忽略
            }
        }

        return success($data);
    }

    /**
     * 单条测试记录详情（与 testList 中单条结构一致，供后台「测试记录 → 详情」）
     * GET /api/v1/admin/test-records/:id
     */
    public function testRecord($id)
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'] ?? '', ['admin', 'enterprise_admin'], true)) {
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

        $enterpriseId = $user['enterpriseId'] ?? null;
        if (!$enterpriseId) {
            $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
            $enterpriseId = $adminRow['enterpriseId'] ?? null;
        }

        if ($enterpriseId) {
            $has = Db::name('user_profile')
                ->where('userId', $wechatUserId)
                ->where('enterpriseId', $enterpriseId)
                ->find();
            if (!$has) {
                return error('无权限查看', 403);
            }
            $tid = isset($tr['enterpriseId']) ? (int) $tr['enterpriseId'] : 0;
            if ($tid !== (int) $enterpriseId) {
                return error('无权限查看该测试记录', 403);
            }
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
}
