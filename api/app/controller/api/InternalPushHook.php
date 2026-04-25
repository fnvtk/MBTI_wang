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
                case 'ai.chat_turn':
                    $uid = (int) ($payload['userId'] ?? 0);
                    $cid = (int) ($payload['conversationId'] ?? 0);
                    $jid = trim((string) ($payload['jobId'] ?? ''));
                    if ($uid <= 0 || $cid <= 0 || $jid === '' || strlen($jid) > 64 || !preg_match('/^[a-f0-9]+$/', $jid)) {
                        return error('invalid ai chat job', 400);
                    }
                    // 大模型耗时长：须先结束本 HTTP 响应，再在 shutdown 中跑任务；否则主请求里 postJsonAsyncNoWait 无法读到 2xx，且易误判投递失败
                    register_shutdown_function(static function () use ($uid, $cid, $jid) {
                        try {
                            \app\controller\api\AiChat::runDeferredChatJob($uid, $cid, $jid);
                        } catch (\Throwable $e) {
                            Log::error('InternalPushHook ai.chat_turn deferred: ' . $e->getMessage());
                        }
                    });
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
