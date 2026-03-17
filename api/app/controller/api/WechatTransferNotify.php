<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\WechatTransferService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * 微信商家转账结果回调
 *
 * 回调地址示例：/api/wechat/transfer/notify
 */
class WechatTransferNotify extends BaseController
{
    public function notify()
    {
        $body = file_get_contents('php://input');
        $headers = [
            'wechatpay-signature' => Request::header('wechatpay-signature'),
            'wechatpay-timestamp' => Request::header('wechatpay-timestamp'),
            'wechatpay-nonce'     => Request::header('wechatpay-nonce'),
            'wechatpay-serial'    => Request::header('wechatpay-serial'),
        ];

        Log::info('[WechatTransferNotify] raw body: ' . $body);

        // 这里只做最小实现：直接解密 resource，按 out_bill_no 匹配提现记录
        try {
            $data = json_decode($body, true) ?: [];
            if (empty($data['resource'])) {
                throw new \Exception('missing resource');
            }

            $service  = new WechatTransferService();
            $resource = $data['resource'];

            // 复用文档中的解密逻辑
            $decrypted = $service->decryptCallbackResource($resource);

            $outBillNo       = $decrypted['out_bill_no'] ?? '';
            $state           = $decrypted['state'] ?? '';
            $transferBillNo  = $decrypted['transfer_bill_no'] ?? null;

            if (!preg_match('/^TX(\d+)$/', (string) $outBillNo, $m)) {
                throw new \Exception('invalid out_bill_no: ' . $outBillNo);
            }
            $withdrawId = (int) $m[1];

            $now = time();
            if ($state === 'SUCCESS') {
                // 微信转账成功：仅允许从「待收款 status=2」更新为「已收款 status=3」
                Db::name('distribution_withdrawals')
                    ->where('id', $withdrawId)
                    ->where('status', 2)
                    ->update([
                        'status'           => 3,
                        'wechat_pay_state' => $state,
                        'transfer_bill_no' => $transferBillNo,
                        'transferAt'       => $now,
                        'updatedAt'        => $now,
                    ]);
            } elseif ($state === 'FAIL') {
                // 转账失败：退回余额
                $record = Db::name('distribution_withdrawals')->where('id', $withdrawId)->find();
                if ($record && (int)$record['status'] !== 3) {
                    Db::startTrans();
                    try {
                        Db::name('wechat_users')
                            ->where('id', $record['userId'])
                            ->inc('walletBalance', (int) $record['amountFen'])
                            ->update(['updatedAt' => $now]);

                        Db::name('distribution_withdrawals')
                            ->where('id', $withdrawId)
                            ->update([
                                // 1=已驳回
                                'status'           => 1,
                                'auditNote'        => '微信转账失败自动退回',
                                'wechat_pay_state' => $state,
                                'transfer_bill_no' => $transferBillNo,
                                'updatedAt'        => $now,
                            ]);

                        Db::commit();
                    } catch (\Throwable $e) {
                        Db::rollback();
                        Log::error('[WechatTransferNotify] fail rollback error: ' . $e->getMessage());
                    }
                }
            }

            return json(['code' => 'SUCCESS']);
        } catch (\Throwable $e) {
            Log::error('[WechatTransferNotify] error: ' . $e->getMessage());
            return json(['code' => 'FAIL', 'message' => '处理失败'])->code(500);
        }
    }
}

