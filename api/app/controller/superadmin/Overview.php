<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Request;
use think\facade\Db;

/**
 * 数据概览控制器（超管专用）
 * 数据来源：mbti_orders、wechat_users、test_results、enterprises；金额单位：分
 */
class Overview extends BaseController
{
    private const PAID_STATUS = ['paid', 'completed'];

    /**
     * 获取数据概览
     * 金额单位：分
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $currentMonthStart = mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
            $currentMonthEnd = mktime(23, 59, 59, (int) date('n'), (int) date('t'), (int) date('Y'));

            // 企业统计
            $totalEnterprises = (int) Db::name('enterprises')->count();
            $newEnterprises = (int) Db::name('enterprises')
                ->where('createdAt', '>=', $currentMonthStart)
                ->where('createdAt', '<=', $currentMonthEnd)
                ->count();

            // 注册用户数（wechat_users 按 openid 去重，无 openid 则按行数）
            try {
                $totalRegisteredUsers = (int) Db::name('wechat_users')->count('openid', true);
            } catch (\Throwable $e) {
                $totalRegisteredUsers = (int) Db::name('wechat_users')->count();
            }

            // 有测试记录的用户数（按 wechat_users.openid 去重）；本月新增 = 本月首次测试的 openid 数
            $totalUsers = 0;
            $newUsers = 0;
            try {
                $totalUsers = (int) Db::name('test_results')->distinct(true)->count('userId');
                $newUsers = (int) Db::name('test_results')
                    ->where('createdAt', '>=', $currentMonthStart)
                    ->where('createdAt', '<=', $currentMonthEnd)
                    ->distinct(true)
                    ->count('userId');
                // 按 openid 去重：tr 关联 wechat_users，统计 distinct openid
                $hasOpenid = false;
                try {
                    $openids = Db::name('test_results')->alias('tr')
                        ->join('wechat_users w', 'tr.userId = w.id')
                        ->distinct(true)
                        ->column('w.openid');
                    if (is_array($openids)) {
                        $openids = array_filter(array_unique($openids));
                        $totalUsers = count($openids);
                        $hasOpenid = true;
                    }
                } catch (\Throwable $e) {
                }
                if ($hasOpenid) {
                    $openidsBeforeMonth = Db::name('test_results')->alias('tr')
                        ->join('wechat_users w', 'tr.userId = w.id')
                        ->where('tr.createdAt', '<', $currentMonthStart)
                        ->distinct(true)
                        ->column('w.openid');
                    $openidsBeforeMonth = is_array($openidsBeforeMonth) ? array_filter(array_unique($openidsBeforeMonth)) : [];
                    $openidsInMonth = Db::name('test_results')->alias('tr')
                        ->join('wechat_users w', 'tr.userId = w.id')
                        ->where('tr.createdAt', '>=', $currentMonthStart)
                        ->where('tr.createdAt', '<=', $currentMonthEnd)
                        ->distinct(true)
                        ->column('w.openid');
                    $openidsInMonth = is_array($openidsInMonth) ? array_filter(array_unique($openidsInMonth)) : [];
                    $newUsers = count(array_diff($openidsInMonth, $openidsBeforeMonth));
                }
            } catch (\Throwable $e) {
                $newUsers = 0;
            }

            // 收入与订单（仅 orders，金额分）
            $totalRevenue = (int) (Db::name('orders')->whereIn('status', self::PAID_STATUS)->sum('amount') ?? 0);
            $monthRevenue = (int) (Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->where('payTime', '>=', $currentMonthStart)
                ->where('payTime', '<=', $currentMonthEnd)
                ->sum('amount') ?? 0);
            $paidOrderCount = (int) Db::name('orders')->whereIn('status', self::PAID_STATUS)->count();

            $lastMonthStart = mktime(0, 0, 0, (int) date('n') - 1, 1, (int) date('Y'));
            $lastMonthEnd = mktime(23, 59, 59, (int) date('n') - 1, (int) date('t', $lastMonthStart), (int) date('Y'));
            $lastMonthRevenue = (int) (Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->where('payTime', '>=', $lastMonthStart)
                ->where('payTime', '<=', $lastMonthEnd)
                ->sum('amount') ?? 0);
            $revenueGrowth = $lastMonthRevenue > 0
                ? round(($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100, 1)
                : ($monthRevenue > 0 ? 100.0 : 0);

            // 测试统计
            $totalTests = 0;
            $newTests = 0;
            try {
                $totalTests = (int) Db::name('test_results')->count();
                $newTests = (int) Db::name('test_results')
                    ->where('createdAt', '>=', $currentMonthStart)
                    ->where('createdAt', '<=', $currentMonthEnd)
                    ->count();
            } catch (\Throwable $e) {
            }

            return success([
                'totalEnterprises' => $totalEnterprises,
                'newEnterprises' => $newEnterprises,
                'totalRegisteredUsers' => $totalRegisteredUsers,
                'totalUsers' => $totalUsers,
                'newUsers' => $newUsers,
                'totalRevenue' => $totalRevenue,
                'monthRevenue' => $monthRevenue,
                'revenueGrowth' => $revenueGrowth,
                'paidOrderCount' => $paidOrderCount,
                'totalTests' => $totalTests,
                'newTests' => $newTests,
            ]);
        } catch (\Throwable $e) {
            return error('获取数据概览失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 最近动态：支付订单、新企业、今日测试等；金额接口为分，文案中转为元
     */
    public function recentDynamics()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $limit = min(20, max(5, (int) Request::param('limit', 10)));
            $dynamics = [];

            // 1. 最近已支付订单（含个人与企业，金额分）
            try {
                $orders = Db::name('orders')
                    ->whereIn('status', self::PAID_STATUS)
                    ->field('id, orderNo, userId, enterpriseId, productType, amount, payTime')
                    ->order('payTime', 'desc')
                    ->limit($limit)
                    ->select()
                    ->toArray();
                $orders = is_array($orders) ? $orders : [];

                $eids = array_values(array_unique(array_filter(array_column($orders, 'enterpriseId'))));
                $uids = array_values(array_unique(array_filter(array_column($orders, 'userId'))));
                $entMap = [];
                $userMap = [];
                if (!empty($eids)) {
                    $entMap = Db::name('enterprises')->where('id', 'in', $eids)->column('name', 'id') ?: [];
                }
                if (!empty($uids)) {
                    $users = Db::name('wechat_users')->where('id', 'in', $uids)->field('id, nickname')->select()->toArray();
                    foreach (is_array($users) ? $users : [] as $u) {
                        $userMap[(int) ($u['id'] ?? 0)] = $u['nickname'] ?? ('用户' . ($u['id'] ?? ''));
                    }
                }

                $productLabel = ['face' => 'AI人脸', 'mbti' => 'MBTI', 'disc' => 'DISC', 'pdp' => 'PDP', 'report' => '报告'];
                foreach ($orders as $o) {
                    $amountYuan = isset($o['amount']) ? round((int) $o['amount'] / 100, 2) : 0;
                    $who = '未知';
                    if (!empty($o['enterpriseId']) && isset($entMap[$o['enterpriseId']])) {
                        $who = $entMap[$o['enterpriseId']];
                    } else {
                        $who = $userMap[(int) ($o['userId'] ?? 0)] ?? ('用户' . ($o['userId'] ?? ''));
                    }
                    $product = $productLabel[$o['productType'] ?? ''] ?? ($o['productType'] ?? '');
                    $dynamics[] = [
                        'type' => 'payment',
                        'icon' => 'TrendCharts',
                        'text' => $who . ' 支付 ¥' . number_format($amountYuan, 2) . ($product ? '（' . $product . '）' : ''),
                        'time' => $this->formatTime($o['payTime'] ?? null),
                        'sortTime' => (int) ($o['payTime'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                // 订单数据异常不影响其他动态
            }

            // 2. 最近入驻企业
            try {
                $enterprises = Db::name('enterprises')
                    ->field('name, createdAt')
                    ->order('createdAt', 'desc')
                    ->limit(5)
                    ->select()
                    ->toArray();
                foreach (is_array($enterprises) ? $enterprises : [] as $e) {
                    $dynamics[] = [
                        'type' => 'enterprise',
                        'icon' => 'Document',
                        'text' => ($e['name'] ?? '') . ' 完成企业入驻',
                        'time' => $this->formatTime($e['createdAt'] ?? null),
                        'sortTime' => (int) ($e['createdAt'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
            }

            // 3. 今日测试量（按企业/个人分组，文案里带企业名称）
            try {
                $todayStart = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
                $rows = Db::name('test_results')
                    ->alias('tr')
                    ->leftJoin('enterprises e', 'tr.enterpriseId = e.id')
                    ->where('tr.createdAt', '>=', $todayStart)
                    ->field('tr.enterpriseId, e.name as enterpriseName, COUNT(*) as cnt')
                    ->group('tr.enterpriseId')
                    ->order('cnt', 'desc')
                    ->limit(5)
                    ->select()
                    ->toArray();

                $totalToday = 0;
                foreach (is_array($rows) ? $rows : [] as $row) {
                    $cnt = (int) ($row['cnt'] ?? 0);
                    if ($cnt <= 0) {
                        continue;
                    }
                    $totalToday += $cnt;
                    $eid = $row['enterpriseId'] ?? null;
                    $name = $row['enterpriseName'] ?? '';
                    if ($eid && !$name) {
                        $name = '企业' . $eid;
                    }
                    if (!$eid) {
                        $name = $name ?: '个人用户(无企业)';
                    }
                    $dynamics[] = [
                        'type' => 'test',
                        'icon' => 'TrendCharts',
                        'text' => $name . ' 今日完成 ' . $cnt . ' 次测试',
                        'time' => '今日',
                        'sortTime' => $todayStart + 1,
                    ];
                }

                // 追加一条全局汇总（放在企业之后）
                if ($totalToday > 0) {
                    $dynamics[] = [
                        'type' => 'test-total',
                        'icon' => 'TrendCharts',
                        'text' => '全站今日共完成 ' . $totalToday . ' 次测试',
                        'time' => '今日',
                        'sortTime' => $todayStart,
                    ];
                }
            } catch (\Throwable $e) {
            }

            usort($dynamics, function ($a, $b) {
                return ($b['sortTime'] ?? 0) - ($a['sortTime'] ?? 0);
            });
            $dynamics = array_slice($dynamics, 0, $limit);

            return success($dynamics);
        } catch (\Throwable $e) {
            return error('获取最近动态失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 最近 N 天测试趋势（按日期 & 测试类型统计）
     * GET /superadmin/overview/test-trends?days=14
     */
    public function testTrends()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $days = (int) Request::param('days', 14);
            $days = min(60, max(7, $days));

            $startDate = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')));

            $rows = Db::name('test_results')
                ->where('createdAt', '>=', $startDate)
                ->whereIn('testType', ['face', 'mbti', 'disc', 'pdp'])
                ->field("FROM_UNIXTIME(createdAt, '%Y-%m-%d') as d, testType, COUNT(*) as c")
                ->group('d,testType')
                ->order('d', 'asc')
                ->select()
                ->toArray();

            $trendMap = [];
            foreach (is_array($rows) ? $rows : [] as $row) {
                $d = $row['d'];
                $type = $row['testType'];
                $cnt = (int) ($row['c'] ?? 0);
                if (!isset($trendMap[$d])) {
                    $trendMap[$d] = [
                        'date'  => $d,
                        'face'  => 0,
                        'mbti'  => 0,
                        'disc'  => 0,
                        'pdp'   => 0,
                        'total' => 0,
                    ];
                }
                if (in_array($type, ['face', 'mbti', 'disc', 'pdp'], true)) {
                    $trendMap[$d][$type] += $cnt;
                    $trendMap[$d]['total'] += $cnt;
                }
            }

            $trendData = [];
            for ($i = 0; $i < $days; $i++) {
                $d = date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days'));
                if (isset($trendMap[$d])) {
                    $trendData[] = $trendMap[$d];
                } else {
                    $trendData[] = [
                        'date'  => $d,
                        'face'  => 0,
                        'mbti'  => 0,
                        'disc'  => 0,
                        'pdp'   => 0,
                        'total' => 0,
                    ];
                }
            }

            return success($trendData);
        } catch (\Throwable $e) {
            return error('获取测试趋势失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 企业活跃排行（按测试次数、支付金额）；金额单位：分
     */
    public function enterpriseRanking()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $limit = min(20, max(5, (int) Request::param('limit', 10)));
            $result = [];

            try {
                // 企业表 left join 测试与订单，保证无测试/无订单的企业也出现（测试数、金额为 0）
                $list = Db::name('enterprises')
                    ->alias('e')
                    ->leftJoin('test_results tr', 'tr.enterpriseId = e.id')
                    ->leftJoin('orders o', 'o.enterpriseId = e.id AND o.status IN (\'paid\',\'completed\')')
                    ->field('e.id, e.name, COUNT(DISTINCT tr.id) as testCount, COALESCE(SUM(o.amount), 0) as totalAmount')
                    ->group('e.id')
                    ->order('testCount', 'desc')
                    ->order('totalAmount', 'desc')
                    ->limit($limit)
                    ->select()
                    ->toArray();

                foreach (is_array($list) ? $list : [] as $item) {
                    $result[] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'name' => $item['name'] ?? '',
                        'tests' => (int) ($item['testCount'] ?? 0),
                        'amount' => (int) ($item['totalAmount'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                // 若 join 报错（如表/字段不一致），降级为只查企业列表，测试与金额为 0
                $list = Db::name('enterprises')->field('id, name')->order('id', 'desc')->limit($limit)->select()->toArray();
                foreach (is_array($list) ? $list : [] as $item) {
                    $result[] = [
                        'id' => (int) ($item['id'] ?? 0),
                        'name' => $item['name'] ?? '',
                        'tests' => 0,
                        'amount' => 0,
                    ];
                }
            }

            return success($result);
        } catch (\Throwable $e) {
            return error('获取企业排行失败：' . $e->getMessage(), 500);
        }
    }

    private function formatTime($timestamp)
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }
        $ts = is_numeric($timestamp) ? (int) $timestamp : strtotime($timestamp);
        if ($ts <= 0) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        }
        if ($diff < 604800) {
            return floor($diff / 86400) . '天前';
        }
        return date('Y-m-d H:i', $ts);
    }
}
