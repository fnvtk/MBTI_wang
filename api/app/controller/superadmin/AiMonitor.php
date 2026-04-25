<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\common\service\AiBalanceAlertService;

/**
 * 超管 · 神仙 AI 监控面板
 *
 * - POST /api/v1/superadmin/ai/balance-check   手动触发余额预警扫描
 *   可直接由宝塔 cron 每 12 小时调用一次
 */
class AiMonitor extends BaseController
{
    public function balanceCheck()
    {
        $this->ensureSuperadmin();
        $r = AiBalanceAlertService::scanAndAlert();
        return success($r, "扫描完成：推送 {$r['alerted']} 条，跳过 {$r['skipped']} 条（当日去重）");
    }

    private function ensureSuperadmin()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            abort(403, '无权限访问');
        }
    }
}
