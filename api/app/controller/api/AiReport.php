<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\AiReportService;
use think\facade\Request;

/**
 * AI 深度画像报告 · 小程序 API
 *
 *  POST /api/ai/report/create     创建或返回现有 pending 报告
 *  GET  /api/ai/report/my-latest  我最近的一份报告（判断是否已买）
 *  GET  /api/ai/report/:id        获取报告正文（必须属于当前用户）
 *  POST /api/ai/report/:id/mark-paid-dev  调试用：跳过支付直接置已付
 *  POST /api/ai/report/:id/regenerate    失败后重试生成（仅管理员/作者）
 */
class AiReport extends BaseController
{
    public function create()
    {
        $user = $this->request->user ?? null;
        if (!$user || empty($user['id'])) return error('请先登录', 401);
        $conversationId = (int) Request::param('conversationId', 0);
        $mbtiType = trim((string) Request::param('mbtiType', ''));

        $r = AiReportService::createOrGetPending((int) $user['id'], $conversationId, $mbtiType);
        return success($r);
    }

    public function myLatest()
    {
        $user = $this->request->user ?? null;
        if (!$user || empty($user['id'])) return success(['status' => '']);
        $r = AiReportService::myLatest((int) $user['id']);
        if (!$r) return success(['status' => '']);
        return success($r);
    }

    public function show($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || empty($user['id'])) return error('请先登录', 401);
        $r = AiReportService::get((int) $id, (int) $user['id']);
        if (!$r) return error('报告不存在', 404);

        // 未付费时，content 不下发，只给 summary + 解锁引导
        if ($r['status'] !== 'done') {
            unset($r['content']);
        }
        return success($r);
    }

    public function markPaidDev($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || empty($user['id'])) return error('请先登录', 401);
        $r = AiReportService::get((int) $id, (int) $user['id']);
        if (!$r) return error('报告不存在', 404);

        // 仅超管或本地 debug 开关允许
        $role = $user['role'] ?? '';
        $isSuper = $role === 'superadmin';
        $isDebug = function_exists('env') ? (bool) env('app.ai_report_paid_dev', false) : false;
        if (!$isSuper && !$isDebug) {
            return error('仅测试模式可用', 403);
        }
        $ret = AiReportService::markPaidDev((string) $r['orderSn']);
        return success($ret);
    }

    public function regenerate($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || empty($user['id'])) return error('请先登录', 401);
        $r = AiReportService::get((int) $id, (int) $user['id']);
        if (!$r) return error('报告不存在', 404);
        if (!in_array($r['status'], ['failed', 'paid'])) {
            return error('当前状态不支持重试：' . $r['status']);
        }
        AiReportService::generate((int) $r['id']);
        $r2 = AiReportService::get((int) $id, (int) $user['id']);
        return success($r2);
    }
}
