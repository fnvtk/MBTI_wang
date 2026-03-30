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

            if ($keyword !== '') {
                $kw = '%' . $keyword . '%';
                $baseQuery->where(function ($q) use ($kw) {
                    $q->whereLike('w.nickname', $kw)
                        ->whereOr('w.phone', 'like', $kw)
                        ->whereOr('w.city', 'like', $kw)
                        ->whereOr('w.province', 'like', $kw);
                });
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
            $list = (clone $baseQuery)
                ->field('w.id,w.nickname,w.openid,w.avatar,w.phone,w.gender,w.country,w.province,w.city,w.status,w.lastLoginAt,w.createdAt')
                ->order('w.createdAt', 'desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();
        } else {
            $total = (int) (clone $baseQuery)->count();
            $list = (clone $baseQuery)
                ->field('id,nickname,openid,avatar,phone,gender,country,province,city,status,lastLoginAt,createdAt')
                ->order('createdAt', 'desc')
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
