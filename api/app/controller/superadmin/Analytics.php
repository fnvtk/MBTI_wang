<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\common\AnalyticsEventLabels;
use think\facade\Db;
use think\facade\Request;

/**
 * 小程序埋点统计（仅超级管理员）
 */
class Analytics extends BaseController
{
    /**
     * GET /api/v1/superadmin/analytics/summary?days=7&eventName=可选
     * 返回字段附加 eventNameCn（中文名）
     */
    public function summary()
    {
        $days = min(90, max(1, (int) Request::param('days', 7)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $eventFilter = trim((string) Request::param('eventName', ''));

        try {
            $q = Db::name('analytics_events')
                ->field('eventName, COUNT(*) AS cnt')
                ->where('createdAt', '>=', $since);
            if ($eventFilter !== '') {
                $q->where('eventName', $eventFilter);
            }
            $list = $q
                ->group('eventName')
                ->order('cnt', 'desc')
                ->select()
                ->toArray();

            $list = AnalyticsEventLabels::withCn($list);

            $cntQuery = Db::name('analytics_events')
                ->where('createdAt', '>=', $since);
            if ($eventFilter !== '') {
                $cntQuery->where('eventName', $eventFilter);
            }
            $total = $cntQuery->count();

            return success([
                'days'         => $days,
                'total'        => (int) $total,
                'list'         => $list,
                'labels'       => AnalyticsEventLabels::all(),
                'tableMissing' => false,
            ]);
        } catch (\Throwable $e) {
            return success([
                'days'         => $days,
                'total'        => 0,
                'list'         => [],
                'labels'       => AnalyticsEventLabels::all(),
                'tableMissing' => true,
            ]);
        }
    }

    /**
     * GET /api/v1/superadmin/analytics/events?days=7&page=1&pageSize=50&eventName=&userId=
     */
    public function events()
    {
        $days = min(90, max(1, (int) Request::param('days', 7)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(10, (int) Request::param('pageSize', 50)));
        $eventFilter = trim((string) Request::param('eventName', ''));
        $userIdFilter = (int) Request::param('userId', 0);

        try {
            $baseQ = Db::name('analytics_events')->where('createdAt', '>=', $since);
            if ($eventFilter !== '') {
                $baseQ->where('eventName', $eventFilter);
            }
            if ($userIdFilter > 0) {
                $baseQ->where('userId', $userIdFilter);
            }

            $total = (int) (clone $baseQ)->count();
            $offset = ($page - 1) * $pageSize;
            $rows = $baseQ
                ->order('id', 'desc')
                ->limit($offset, $pageSize)
                ->select()
                ->toArray();

            foreach ($rows as &$r) {
                if (!empty($r['propsJson'])) {
                    $decoded = json_decode($r['propsJson'], true);
                    $r['props'] = is_array($decoded) ? $decoded : null;
                } else {
                    $r['props'] = null;
                }
                unset($r['propsJson']);
            }
            unset($r);

            $rows = AnalyticsEventLabels::withCn($rows);

            return success([
                'list'         => $rows,
                'total'        => $total,
                'page'         => $page,
                'pageSize'     => $pageSize,
                'tableMissing' => false,
            ]);
        } catch (\Throwable $e) {
            return success([
                'list'         => [],
                'total'        => 0,
                'page'         => $page,
                'pageSize'     => $pageSize,
                'tableMissing' => true,
            ]);
        }
    }

    /**
     * GET /api/v1/superadmin/analytics/user-journey?userId=&days=30
     * 单个用户旅程：按时间倒序展示最近 200 条事件（中文名）
     */
    public function userJourney()
    {
        $userId = (int) Request::param('userId', 0);
        if ($userId <= 0) {
            return error('userId 不能为空', 400);
        }
        $days = min(180, max(1, (int) Request::param('days', 30)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);

        try {
            $rows = Db::name('analytics_events')
                ->where('userId', $userId)
                ->where('createdAt', '>=', $since)
                ->order('id', 'desc')
                ->limit(200)
                ->select()
                ->toArray();

            foreach ($rows as &$r) {
                if (!empty($r['propsJson'])) {
                    $decoded = json_decode($r['propsJson'], true);
                    $r['props'] = is_array($decoded) ? $decoded : null;
                } else {
                    $r['props'] = null;
                }
                unset($r['propsJson']);
            }
            unset($r);
            $rows = AnalyticsEventLabels::withCn($rows);

            return success([
                'userId' => $userId,
                'days'   => $days,
                'list'   => $rows,
                'total'  => count($rows),
            ]);
        } catch (\Throwable $e) {
            return success([
                'userId'       => $userId,
                'days'         => $days,
                'list'         => [],
                'total'        => 0,
                'tableMissing' => true,
            ]);
        }
    }

    /**
     * GET /api/v1/superadmin/analytics/share-stats?days=30
     * 分享与邀请统计：
     * - 每位用户累计分享次数
     * - 每位用户邀请绑定人数（distribution_bindings）
     * - 每位用户累计产生分润（distribution_commissions）
     */
    public function shareStats()
    {
        $days = min(180, max(1, (int) Request::param('days', 30)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(10, (int) Request::param('pageSize', 30)));

        $out = [];
        $total = 0;
        $tableMissing = false;

        try {
            // 分享次数：share / tap_share_moment / tap_share_friend 都算
            $shareEvents = ['share', 'tap_share_moment', 'tap_share_friend'];
            $shareRows = Db::name('analytics_events')
                ->field('userId, COUNT(*) AS shareCount')
                ->whereIn('eventName', $shareEvents)
                ->where('createdAt', '>=', $since)
                ->where('userId', '>', 0)
                ->group('userId')
                ->order('shareCount', 'desc')
                ->select()
                ->toArray();
            foreach ($shareRows as $row) {
                $uid = (int) ($row['userId'] ?? 0);
                if ($uid <= 0) continue;
                $out[$uid] = [
                    'userId' => $uid,
                    'shareCount' => (int) ($row['shareCount'] ?? 0),
                    'inviteBound' => 0,
                    'totalCommissionFen' => 0,
                ];
            }
        } catch (\Throwable $e) {
            $tableMissing = true;
        }

        // 邀请绑定人数（按 inviteeId 去重，只统计当前 active）
        try {
            $bindRows = Db::name('distribution_bindings')
                ->field('inviterId AS userId, COUNT(DISTINCT inviteeId) AS cnt')
                ->where('status', 'active')
                ->group('inviterId')
                ->select()
                ->toArray();
            foreach ($bindRows as $row) {
                $uid = (int) ($row['userId'] ?? 0);
                if ($uid <= 0) continue;
                if (!isset($out[$uid])) {
                    $out[$uid] = [
                        'userId' => $uid,
                        'shareCount' => 0,
                        'inviteBound' => 0,
                        'totalCommissionFen' => 0,
                    ];
                }
                $out[$uid]['inviteBound'] = (int) ($row['cnt'] ?? 0);
            }
        } catch (\Throwable $e) {}

        // 分润金额（从 commission_records 聚合）
        try {
            $commRows = Db::name('commission_records')
                ->field('inviterId AS userId, SUM(commissionFen) AS sumFen')
                ->where('commissionFen', '>', 0)
                ->group('inviterId')
                ->select()
                ->toArray();
            foreach ($commRows as $row) {
                $uid = (int) ($row['userId'] ?? 0);
                if ($uid <= 0) continue;
                if (!isset($out[$uid])) {
                    $out[$uid] = [
                        'userId' => $uid,
                        'shareCount' => 0,
                        'inviteBound' => 0,
                        'totalCommissionFen' => 0,
                    ];
                }
                $out[$uid]['totalCommissionFen'] = (int) ($row['sumFen'] ?? 0);
            }
        } catch (\Throwable $e) {}

        // 合并后按邀请人数、分润、分享次数排序
        $list = array_values($out);
        usort($list, function ($a, $b) {
            if ($a['totalCommissionFen'] !== $b['totalCommissionFen']) {
                return $b['totalCommissionFen'] <=> $a['totalCommissionFen'];
            }
            if ($a['inviteBound'] !== $b['inviteBound']) {
                return $b['inviteBound'] <=> $a['inviteBound'];
            }
            return $b['shareCount'] <=> $a['shareCount'];
        });

        $total = count($list);
        $offset = ($page - 1) * $pageSize;
        $pageList = array_slice($list, $offset, $pageSize);

        // 补用户资料
        $uids = array_column($pageList, 'userId');
        if (!empty($uids)) {
            try {
                $users = Db::name('wechat_users')
                    ->whereIn('id', $uids)
                    ->field('id, nickname, avatar, phone, enterpriseId, createdAt')
                    ->select()
                    ->toArray();
                $byId = [];
                foreach ($users as $u) { $byId[(int) $u['id']] = $u; }
                foreach ($pageList as &$row) {
                    $u = $byId[$row['userId']] ?? null;
                    $row['nickname'] = $u['nickname'] ?? '';
                    $row['avatar']   = $u['avatar'] ?? '';
                    $row['phone']    = $u['phone'] ?? '';
                    $row['enterpriseId'] = $u['enterpriseId'] ?? null;
                    $row['createdAt'] = $u['createdAt'] ?? null;
                }
                unset($row);
            } catch (\Throwable $e) {}
        }

        return success([
            'days'         => $days,
            'total'        => $total,
            'list'         => $pageList,
            'page'         => $page,
            'pageSize'     => $pageSize,
            'tableMissing' => $tableMissing,
        ]);
    }

    /**
     * GET /api/v1/superadmin/analytics/share-funnel?days=14
     * 分享漏斗：
     *   1) 结果页访问（pages/result/* page_view）
     *   2) 分享动作（share / tap_share_moment / tap_share_friend）
     *   3) 好友登录（login_silent_success）
     *   4) 好友付费（pay_success_attribution 或 pay_success）
     *   5) 累计分润（distribution_commissions）
     */
    public function shareFunnel()
    {
        $days = min(180, max(1, (int) Request::param('days', 14)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);

        $out = [
            ['stage' => '结果页访问', 'value' => 0],
            ['stage' => '分享动作',   'value' => 0],
            ['stage' => '好友登录',   'value' => 0],
            ['stage' => '好友付费',   'value' => 0],
            ['stage' => '累计分润(元)', 'value' => 0],
        ];
        $tableMissing = false;

        try {
            $out[0]['value'] = (int) Db::name('analytics_events')
                ->where('eventName', 'page_view')
                ->where('pagePath', 'like', 'pages/result/%')
                ->where('createdAt', '>=', $since)
                ->count();

            $out[1]['value'] = (int) Db::name('analytics_events')
                ->whereIn('eventName', ['share', 'tap_share_moment', 'tap_share_friend'])
                ->where('createdAt', '>=', $since)
                ->count();

            $out[2]['value'] = (int) Db::name('analytics_events')
                ->where('eventName', 'login_silent_success')
                ->where('createdAt', '>=', $since)
                ->count();

            $out[3]['value'] = (int) Db::name('analytics_events')
                ->whereIn('eventName', ['pay_success_attribution', 'pay_success'])
                ->where('createdAt', '>=', $since)
                ->count();
        } catch (\Throwable $e) {
            $tableMissing = true;
        }

        // 分润金额（元）— commission_records.commissionFen
        // createdAt 在该表为整数秒时间戳，而不是 datetime；需要改成整数比较
        try {
            $sinceTs = time() - $days * 86400;
            $totalFen = (int) Db::name('commission_records')
                ->where('createdAt', '>=', $sinceTs)
                ->where('commissionFen', '>', 0)
                ->sum('commissionFen');
            $out[4]['value'] = round($totalFen / 100, 2);
        } catch (\Throwable $e) {}

        return success([
            'days'         => $days,
            'funnel'       => $out,
            'tableMissing' => $tableMissing,
        ]);
    }
}
