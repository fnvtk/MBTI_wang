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
 *  GET  /api/ai/my-report/latest  同上（无歧义别名）
 *  GET  /api/ai/report/:id        获取报告正文（必须属于当前用户）
 *  POST /api/ai/report/:id/mark-paid-dev  调试用：跳过支付直接置已付
 *  POST /api/ai/report/:id/regenerate    失败后重试生成（仅管理员/作者）
 */
class AiReport extends BaseController
{
    public function create()
    {
        $userId = $this->jwtSubjectUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('功能升级中', 503);
        }
        $conversationId = (int) Request::param('conversationId', 0);
        $mbtiType = trim((string) Request::param('mbtiType', ''));

        $r = AiReportService::createOrGetPending($userId, $conversationId, $mbtiType);
        return success($r);
    }

    public function myLatest()
    {
        try {
            if (miniprogram_audit_mode_on()) {
                return success(['status' => '']);
            }
            $userId = $this->jwtSubjectUserId();
            if ($userId <= 0) {
                return success(['status' => '']);
            }
            $r = AiReportService::myLatest($userId);
            if (!$r) {
                return success(['status' => '']);
            }

            return success($r);
        } catch (\Throwable $e) {
            return success(['status' => '']);
        }
    }

    public function show($id)
    {
        $userId = $this->jwtSubjectUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('报告不存在', 404);
        }
        $r = AiReportService::get((int) $id, $userId);
        if (!$r) return error('报告不存在', 404);

        // 未付费时，content 不下发，只给 summary + 解锁引导
        if ($r['status'] !== 'done') {
            unset($r['content']);
        }
        return success($r);
    }

    public function markPaidDev($id)
    {
        $userId = $this->jwtSubjectUserId();
        if ($userId <= 0) return error('请先登录', 401);
        $user = $this->request->user ?? [];
        $r = AiReportService::get((int) $id, $userId);
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
        $userId = $this->jwtSubjectUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('功能升级中', 503);
        }
        $r = AiReportService::get((int) $id, $userId);
        if (!$r) return error('报告不存在', 404);
        if (!in_array($r['status'], ['failed', 'paid'])) {
            return error('当前状态不支持重试：' . $r['status']);
        }
        AiReportService::generate((int) $r['id']);
        $r2 = AiReportService::get((int) $id, $userId);
        return success($r2);
    }
}
