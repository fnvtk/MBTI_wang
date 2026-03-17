<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Request;
use think\facade\Db;

/**
 * 财务管理控制器（超管专用）
 * 数据来源：mbti_orders，金额单位：分
 */
class Finance extends BaseController
{
    private const PAID_STATUS = ['paid', 'completed'];

    /**
     * 获取财务概览
     * 金额单位：分
     */
    public function overview()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $currentMonthStart = mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
            $currentMonthEnd = mktime(23, 59, 59, (int) date('n'), (int) date('t'), (int) date('Y'));

            $basePaid = Db::name('orders')->whereIn('status', self::PAID_STATUS);
            $totalRevenue = (int) ((clone $basePaid)->sum('amount') ?? 0);
            $paidOrderCount = (int) ((clone $basePaid)->count());

            $monthRevenue = (int) (Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->where('payTime', '>=', $currentMonthStart)
                ->where('payTime', '<=', $currentMonthEnd)
                ->sum('amount') ?? 0);

            // 成本：无成本表时按收入比例估算（约 30%）
            $totalCost = (int) round($totalRevenue * 0.3);
            $monthCost = (int) round($monthRevenue * 0.3);

            $netProfit = $totalRevenue - $totalCost;
            $monthProfit = $monthRevenue - $monthCost;
            $profitRate = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

            $lastMonthStart = mktime(0, 0, 0, (int) date('n') - 1, 1, (int) date('Y'));
            $lastMonthEnd = mktime(23, 59, 59, (int) date('n') - 1, (int) date('t', $lastMonthStart), (int) date('Y'));
            $lastMonthRevenue = (int) (Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->where('payTime', '>=', $lastMonthStart)
                ->where('payTime', '<=', $lastMonthEnd)
                ->sum('amount') ?? 0);
            $lastMonthCost = (int) round($lastMonthRevenue * 0.3);
            $lastMonthProfit = $lastMonthRevenue - $lastMonthCost;
            $monthGrowth = $lastMonthProfit > 0
                ? round(($monthProfit - $lastMonthProfit) / $lastMonthProfit * 100, 1)
                : ($monthProfit > 0 ? 100 : 0);

            return success([
                'totalRevenue' => $totalRevenue,
                'totalCost' => $totalCost,
                'netProfit' => $netProfit,
                'profitRate' => $profitRate,
                'monthRevenue' => $monthRevenue,
                'monthCost' => $monthCost,
                'monthProfit' => $monthProfit,
                'monthGrowth' => $monthGrowth,
                'paidOrderCount' => $paidOrderCount,
            ]);
        } catch (\Throwable $e) {
            return error('获取财务概览失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 收入明细：按产品类型汇总（已支付订单），金额单位：分
     */
    public function revenueDetails()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $rows = Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->field('productType, SUM(amount) as total')
                ->group('productType')
                ->select()
                ->toArray();

            $typeLabel = [
                'face'   => 'AI人脸分析',
                'mbti'   => 'MBTI',
                'disc'   => 'DISC',
                'pdp'    => 'PDP',
                'resume' => '简历综合分析',
                'report' => '完整报告',
            ];
            $totalSum = 0;
            $byType = [];
            foreach ($rows as $r) {
                $type = $r['productType'] ?? 'other';
                $amount = (int) ($r['total'] ?? 0);
                $totalSum += $amount;
                $byType[$type] = $amount;
            }

            $details = [];
            foreach ($typeLabel as $key => $label) {
                $amount = $byType[$key] ?? 0;
                $details[] = [
                    'type' => $label,
                    'amount' => $amount,
                    'percent' => $totalSum > 0 ? round($amount / $totalSum * 100, 1) : 0,
                ];
            }
            $otherAmount = 0;
            foreach ($byType as $key => $amount) {
                if (!isset($typeLabel[$key])) {
                    $otherAmount += $amount;
                }
            }
            if ($otherAmount > 0) {
                $details[] = [
                    'type' => '其他',
                    'amount' => $otherAmount,
                    'percent' => $totalSum > 0 ? round($otherAmount / $totalSum * 100, 1) : 0,
                ];
            }

            return success($details);
        } catch (\Throwable $e) {
            return error('获取收入明细失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 成本明细：当前为估算（基于收入的 30% 拆分），金额单位：分
     */
    public function costDetails()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $totalRevenue = (int) Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->sum('amount');
            $totalCost = (int) round($totalRevenue * 0.3);

            $items = [
                ['type' => 'AI 调用（人脸/分析等）', 'ratio' => 0.15],
                ['type' => '服务器及运维', 'ratio' => 0.08],
                ['type' => '其他支出', 'ratio' => 0.07],
            ];
            $details = [];
            foreach ($items as $item) {
                $amount = (int) round($totalRevenue * $item['ratio']);
                $details[] = [
                    'type' => $item['type'],
                    'amount' => $amount,
                    'percent' => $totalCost > 0 ? round($amount / $totalCost * 100, 1) : 0,
                ];
            }

            return success($details);
        } catch (\Throwable $e) {
            return error('获取成本明细失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 企业支付记录（已支付且 enterpriseId 不为空的订单），金额单位：分
     */
    public function rechargeRecords()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $page = max(1, (int) Request::param('page', 1));
            $pageSize = min(100, max(1, (int) Request::param('pageSize', 20)));

            $query = Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->whereNotNull('enterpriseId')
                ->where('enterpriseId', '<>', '')
                ->order('payTime', 'desc');
            $total = (int) (clone $query)->count();
            $list = (clone $query)->page($page, $pageSize)
                ->field('id, orderNo, enterpriseId, amount, payMethod, payTime')
                ->select()
                ->toArray();

            $eids = array_values(array_unique(array_filter(array_column($list, 'enterpriseId'))));
            $enterprises = [];
            if (!empty($eids)) {
                $entList = Db::name('enterprises')->where('id', 'in', $eids)->column('name', 'id');
                $enterprises = $entList ?: [];
            }

            $result = [];
            foreach ($list as $r) {
                $eid = $r['enterpriseId'] ?? null;
                $result[] = [
                    'orderNo' => $r['orderNo'] ?? '',
                    'enterprise' => $eid ? ($enterprises[$eid] ?? '企业#' . $eid) : '—',
                    'amount' => (int) ($r['amount'] ?? 0),
                    'method' => $r['payMethod'] === 'wechat' ? '微信支付' : ($r['payMethod'] ?? '—'),
                    'date' => !empty($r['payTime']) ? date('Y-m-d H:i', $r['payTime']) : '—',
                ];
            }

            return success([
                'list' => $result,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ]);
        } catch (\Throwable $e) {
            return error('获取企业支付记录失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 支付记录（全部已支付订单，分页），金额单位：分
     */
    public function paymentRecords()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $page = max(1, (int) Request::param('page', 1));
            $pageSize = min(100, max(1, (int) Request::param('pageSize', 20)));
            $keyword = trim(Request::param('keyword', ''));

            $query = Db::name('orders')
                ->whereIn('status', self::PAID_STATUS)
                ->order('payTime', 'desc');

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('orderNo', '%' . $keyword . '%');
                    if (is_numeric($keyword)) {
                        $q->whereOr('userId', (int) $keyword);
                    }
                });
            }

            $total = (int) (clone $query)->count();
            $list = (clone $query)->page($page, $pageSize)
                ->field('id, orderNo, userId, enterpriseId, productType, productTitle, amount, payMethod, payTime')
                ->select()
                ->toArray();

            $userIds = array_values(array_unique(array_filter(array_column($list, 'userId'))));
            $eids = array_values(array_unique(array_filter(array_column($list, 'enterpriseId'))));
            $usersMap = [];
            $entMap = [];
            if (!empty($userIds)) {
                $users = Db::name('wechat_users')->where('id', 'in', $userIds)->field('id, nickname, phone')->select()->toArray();
                foreach ($users as $u) {
                    $usersMap[(int) $u['id']] = $u;
                }
            }
            if (!empty($eids)) {
                $entList = Db::name('enterprises')->where('id', 'in', $eids)->column('name', 'id');
                $entMap = $entList ?: [];
            }

            $productTypeLabel = [
                'face'          => 'AI人脸分析',
                'mbti'          => 'MBTI',
                'disc'          => 'DISC',
                'pdp'           => 'PDP',
                'report'        => '完整报告',
                'deep_personal' => '个人深度服务',
                'deep_team'     => '团队深度服务',
            ];

            $result = [];
            foreach ($list as $r) {
                $uid = (int) ($r['userId'] ?? 0);
                $eid = isset($r['enterpriseId']) && $r['enterpriseId'] !== '' ? (int) $r['enterpriseId'] : null;
                if ($eid === 0) {
                    $eid = null;
                }
                $u = $usersMap[$uid] ?? null;
                $enterpriseName = $eid ? ($entMap[$eid] ?? '企业#' . $eid) : '个人';
                $result[] = [
                    'orderNo'      => $r['orderNo'] ?? '',
                    'userName'     => $u ? ($u['nickname'] ?? ('用户' . $uid)) : ('用户' . $uid),
                    'enterprise'   => $enterpriseName,
                    'enterpriseId' => $eid,
                    'productType'  => $productTypeLabel[$r['productType'] ?? ''] ?? ($r['productType'] ?? '—'),
                    'productTitle' => $r['productTitle'] ?? '',
                    'amount'       => (int) ($r['amount'] ?? 0),
                    'method'       => $r['payMethod'] === 'wechat' ? '微信支付' : ($r['payMethod'] ?? '—'),
                    'date'         => !empty($r['payTime']) ? date('Y-m-d H:i', $r['payTime']) : '—',
                ];
            }

            return success([
                'list' => $result,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ]);
        } catch (\Throwable $e) {
            return error('获取支付记录失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 导出财务报表
     */
    public function export()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }
        return success(null, '财务报表导出功能开发中');
    }
}
