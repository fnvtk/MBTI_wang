<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\OutboundPushHookService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * 小程序主动触发出站推送。
 * 目的：将第三方 Webhook 推送从主业务链路解耦，改由前端在关键节点显式调用。
 */
class PushHook extends BaseController
{
    /**
     * POST /api/push-hook/trigger
     */
    public function trigger()
    {
        $user = $this->request->user ?? null;
        $user = is_array($user) ? $user : (array) $user;
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $source = (string) ($user['source'] ?? '');

        if ($userId <= 0 || $source !== 'wechat') {
            return error('未登录', 401);
        }

        $event = trim((string) Request::post('event', ''));
        if ($event === '') {
            return error('event 不能为空', 400);
        }
  
        try {
            switch ($event) {
                case 'test.result_completed':
                    return $this->triggerTestResultCompleted($userId);
                case 'lead.order_paid':
                    return $this->triggerOrderPaid($userId);
                default:
                    return error('暂不支持的事件类型', 400);
            }
        } catch (\Throwable $e) {
            Log::error('PushHook trigger failed', [
                'event'  => $event,
                'userId' => $userId,
                'error'  => $e->getMessage(),
            ]);

            return error('触发推送失败', 500);
        }
    }

    private function triggerTestResultCompleted(int $userId)
    {
        $testResultId = (int) Request::post('testResultId', 0);
        if ($testResultId <= 0) {
            return error('testResultId 不能为空', 400);
        }

        $row = Db::name('test_results')
            ->where('id', $testResultId)
            ->field('id,userId,testType')
            ->find();
        if (!$row) {
            return error('测试记录不存在', 404);
        }
        if ((int) ($row['userId'] ?? 0) !== $userId) {
            return error('无权触发该测试记录推送', 403);
        }

        $rawDedupKey = 'test.result_completed:' . $testResultId;
        if (OutboundPushHookService::hasPushHookDedup($rawDedupKey)) {
            return success([
                'accepted'     => false,
                'event'        => 'test.result_completed',
                'testResultId' => $testResultId,
                'status'       => 'duplicate',
                'dedupKey'     => 'push_hook:' . $rawDedupKey,
            ], '该测试记录已推送过，已按去重规则跳过');
        }

        OutboundPushHookService::onTestResultCompleted($testResultId);

        return success([
            'accepted'     => true,
            'event'        => 'test.result_completed',
            'status'       => 'dispatched',
            'testResultId' => $testResultId,
        ], '已触发推送');
    }

    private function triggerOrderPaid(int $userId)
    {
        $orderNo = trim((string) Request::post('orderId', ''));
        if ($orderNo === '') {
            return error('orderId 不能为空', 400);
        }

        $order = Db::name('orders')
            ->where('orderNo', $orderNo)
            ->where('userId', $userId)
            ->field('id,userId,status')
            ->find();
        if (!$order) {
            return error('订单不存在', 404);
        }
        if (!in_array((string) ($order['status'] ?? ''), ['paid', 'completed'], true)) {
            return error('订单尚未支付成功', 409);
        }

        $rawDedupKey = 'lead.order_paid:' . (int) $order['id'];
        if (OutboundPushHookService::hasPushHookDedup($rawDedupKey)) {
            return success([
                'accepted' => false,
                'event'    => 'lead.order_paid',
                'orderId'  => $orderNo,
                'status'   => 'duplicate',
                'dedupKey' => 'push_hook:' . $rawDedupKey,
            ], '该订单已推送过，已按去重规则跳过');
        }

        OutboundPushHookService::onOrderPaid((int) $order['id'], $userId);

        return success([
            'accepted' => true,
            'event'    => 'lead.order_paid',
            'status'   => 'dispatched',
            'orderId'  => $orderNo,
        ], '已触发推送');
    }
}
