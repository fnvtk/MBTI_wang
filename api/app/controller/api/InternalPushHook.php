<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\OutboundPushHookService;
use think\facade\Log;
use think\facade\Request;

/**
 * 服务内部异步推送入口：主业务只投递，不等待外部 Webhook 完成。
 */
class InternalPushHook extends BaseController
{
    /**
     * POST /api/internal/outbound-push/dispatch
     */
    public function dispatch()
    {
        $body = (string) file_get_contents('php://input');
        $timestamp = trim((string) Request::header('X-MBTI-Internal-Timestamp', ''));
        $signature = trim((string) Request::header('X-MBTI-Internal-Signature', ''));

        if (!OutboundPushHookService::verifyAsyncInternalDispatch($body, $timestamp, $signature)) {
            Log::warning('OutboundPushHook async dispatch rejected', [
                'ip'           => Request::ip(),
                'hasSignature' => $signature !== '',
                'timestamp'    => $timestamp,
            ]);

            return error('forbidden', 403);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return error('invalid payload', 400);
        }

        $job = (string) ($payload['job'] ?? '');

        try {
            switch ($job) {
                case 'lead.order_paid':
                    OutboundPushHookService::onOrderPaid(
                        (int) ($payload['orderId'] ?? 0),
                        (int) ($payload['userId'] ?? 0)
                    );
                    break;
                case 'test.result_completed':
                    OutboundPushHookService::onTestResultCompleted((int) ($payload['testResultId'] ?? 0));
                    break;
                default:
                    return error('unsupported job', 400);
            }
        } catch (\Throwable $e) {
            Log::error('OutboundPushHook async dispatch failed', [
                'job'     => $job,
                'payload' => $payload,
                'error'   => $e->getMessage(),
            ]);

            return error('dispatch failed', 500);
        }

        return success(['accepted' => true], 'accepted');
    }
}
