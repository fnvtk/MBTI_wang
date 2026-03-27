<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 小程序埋点统计（仅超级管理员）
 */
class Analytics extends BaseController
{
    /**
     * GET /api/v1/superadmin/analytics/summary?days=7
     */
    public function summary()
    {
        $days = min(90, max(1, (int) Request::param('days', 7)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);

        try {
            $list = Db::name('analytics_events')
                ->field('eventName, COUNT(*) AS cnt')
                ->where('createdAt', '>=', $since)
                ->group('eventName')
                ->order('cnt', 'desc')
                ->select()
                ->toArray();

            $total = Db::name('analytics_events')
                ->where('createdAt', '>=', $since)
                ->count();

            return success([
                'days'         => $days,
                'total'        => (int) $total,
                'list'         => $list,
                'tableMissing' => false,
            ]);
        } catch (\Throwable $e) {
            return success([
                'days'         => $days,
                'total'        => 0,
                'list'         => [],
                'tableMissing' => true,
            ]);
        }
    }

    /**
     * GET /api/v1/superadmin/analytics/events?days=7&page=1&pageSize=50
     */
    public function events()
    {
        $days = min(90, max(1, (int) Request::param('days', 7)));
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(10, (int) Request::param('pageSize', 50)));

        try {
            $total = (int) Db::name('analytics_events')
                ->where('createdAt', '>=', $since)
                ->count();
            $offset = ($page - 1) * $pageSize;
            $rows = Db::name('analytics_events')
                ->where('createdAt', '>=', $since)
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
}
