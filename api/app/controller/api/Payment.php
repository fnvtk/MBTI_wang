<?php
namespace app\controller\api;

use app\BaseController;
use app\model\PricingConfig as PricingConfigModel;
use app\model\UserProfile as UserProfileModel;
use app\common\service\JwtService;
use app\common\service\FeishuLeadWebhookService;
use app\common\service\AiReportService;
use think\facade\Request;
use think\facade\Db;

/**
 * 支付与订单控制器（小程序/前端）
 *
 * 目标：
 * - 统一创建本地订单（mbti_orders），记录用户、企业、产品类型与金额
 * - 对接小程序 payment.js 的 create/notify/query 三个接口
 * - 支持人脸/MBTI/DISC/PDP/完整报告/团队分析/充值/深度服务等多种产品类型
 * - 系统所有金额均以「分」为单位：入参、落库、出参均为分。
 */
class Payment extends BaseController
{
    /**
     * POST /api/payment/create
     * 小程序发起支付前调用：创建本地订单并返回调起微信支付所需参数
     * 入参 amount 为分；订单表 amount 存分；返回 amount 为分。
     */
    public function create()
    {
        try {
            $user = $this->resolveUser();
            if (!$user) {
                return error('未登录', 401);
            }

            $orderId       = Request::param('orderId', '');
            $amountFen     = (int) Request::param('amount', 0); // 单位：分
            $description   = Request::param('description', '');
            $productType   = Request::param('productType', '');
            $paymentMethod = Request::param('paymentMethod', 'wechat');
            $openId        = Request::param('openId', '');
            $quantity      = (int) Request::param('quantity', 1);
            $testResultId  = (int) Request::param('testResultId', 0); // 可选，关联 mbti_test_results.id
            $deepProductId = (string) Request::param('deepProductId', ''); // 深度服务套餐ID/产品Key（来自 deep-pricing.categories）
            $enterpriseIdParam = (int) Request::param('enterpriseId', 0);

            if (empty($orderId)) {
                return error('订单ID不能为空', 400);
            }
            if (empty($productType)) {
                return error('产品类型不能为空', 400);
            }
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
            if ($userId <= 0) {
                return error('用户信息异常', 400);
            }

            if (miniprogram_audit_mode_on()) {
                return error('小程序版本审核期间暂不可发起虚拟商品支付，请审核结束后再试', 400);
            }

            // 企业ID 与金额优先从 test_results 读取（历史记录进入：enterpriseId 为空则按个人价；金额用 paidAmount）
            $enterpriseId = null;
            $fixedAmountFen = null;

            // 未显式传 testResultId 时：优先绑定到“最近一条同类型测试”，并以该记录的 enterpriseId/paidAmount 定价
            if ($testResultId <= 0) {
                $testTypeMap = [
                    'face'   => 'face',
                    'mbti'   => 'mbti',
                    'sbti'   => 'sbti',
                    'disc'   => 'disc',
                    'pdp'    => 'pdp',
                    'resume' => 'resume',
                ];
                if (isset($testTypeMap[$productType])) {
                    $latestTest = Db::name('test_results')
                        ->where('userId', $userId)
                        ->where('testType', $testTypeMap[$productType])
                        ->order('createdAt', 'desc')
                        ->find();
                    if ($latestTest && !empty($latestTest['id'])) {
                        $testResultId = (int) $latestTest['id'];
                    }
                }
            }

            if ($testResultId > 0) {
                $tr = Db::name('test_results')
                    ->where('id', $testResultId)
                    ->where('userId', $userId)
                    ->field('enterpriseId,paidAmount,requiresPayment,testType')
                    ->find();
                if ($tr) {
                    $enterpriseId = !empty($tr['enterpriseId']) ? (int) $tr['enterpriseId'] : null;
                    $paidAmount = isset($tr['paidAmount']) ? (int) $tr['paidAmount'] : 0;
                    if ($paidAmount > 0) {
                        $fixedAmountFen = $paidAmount;
                    }
                }
            }

            // 测试结果未带 enterpriseId 时，使用小程序请求中的企业上下文（scene/绑定/超管默认企业）
            if (empty($enterpriseId) && $enterpriseIdParam > 0) {
                $enterpriseId = $enterpriseIdParam;
            }

            // 充值场景：优先使用显式传入的企业ID，否则回退到当前用户已绑定企业
            if ($productType === 'recharge') {
                if ($enterpriseIdParam > 0) {
                    $enterpriseId = $enterpriseIdParam;
                } elseif (empty($enterpriseId)) {
                    $enterpriseId = $this->resolveEnterpriseId($userId);
                }

                if (empty($enterpriseId)) {
                    return error('充值必须指定企业', 400);
                }
            }

            // 计算订单金额（分）与定价类型（personal/enterprise）
            if ($fixedAmountFen !== null) {
                $pricingType = $enterpriseId ? 'enterprise' : 'personal';
                $amountFenCalculated = $fixedAmountFen;
            } else {
                [$amountFenCalculated, $pricingType] = $this->calculateAmount(
                    $productType,
                    $quantity,
                    $amountFen,
                    $user,
                    $enterpriseId,
                    $deepProductId
                );
            }

            if ($amountFenCalculated <= 0) {
                return error('订单金额无效，请检查定价配置或请求参数', 400);
            }

            // 检查订单是否已存在，避免重复创建
            $existing = Db::name('orders')
                ->where('orderNo', $orderId)
                ->find();

            $now = time();

            if ($existing) {
                // 若已存在且已支付/关闭，则不允许重新创建
                if (in_array($existing['status'], ['paid', 'completed', 'cancelled', 'refunded', 'failed'])) {
                    return error('订单已存在且状态为 ' . $existing['status'], 400);
                }

                // 待支付订单允许覆盖部分字段（金额/描述/支付方式），金额为分
                Db::name('orders')
                    ->where('id', $existing['id'])
                    ->update([
                        'amount'       => $amountFenCalculated,
                        'productType'  => $productType,
                        'productTitle' => $description,
                        'payMethod'    => $paymentMethod,
                        'updatedAt'    => $now,
                    ]);
                $orderIdDb = (int) $existing['id'];
            } else {
                $orderIdDb = Db::name('orders')->insertGetId([
                    'orderNo'      => $orderId,
                    'userId'       => $userId,
                    'enterpriseId' => $enterpriseId,
                    'productType'  => $productType,
                    'productTitle' => $description,
                    'amount'       => $amountFenCalculated,
                    'status'       => 'pending',
                    'payMethod'    => $paymentMethod,
                    'payTime'      => null,
                    'createdAt'    => $now,
                    'updatedAt'    => $now,
                ]);
            }

            // 若传入 testResultId，关联该测试结果到本订单（仅更新属于当前用户的记录）
            if ($testResultId > 0) {
                Db::name('test_results')
                    ->where('id', $testResultId)
                    ->where('userId', $userId)
                    ->update([
                        'orderId' => $orderIdDb,
                        'updatedAt' => $now,
                    ]);
            } else {
                // 未显式传 testResultId 时：自动将当前用户最近一次相关测试记录绑定到本订单
                // 例如：人脸报告 → 绑定最近一条 testType=face 的记录
                $testTypeMap = [
                    'face'   => 'face',
                    'mbti'   => 'mbti',
                    'sbti'   => 'sbti',
                    'disc'   => 'disc',
                    'pdp'    => 'pdp',
                    'resume' => 'resume',
                ];
                if (isset($testTypeMap[$productType])) {
                    $testType = $testTypeMap[$productType];
                    $latestTest = Db::name('test_results')
                        ->where('userId', $userId)
                        ->where('testType', $testType)
                        ->order('createdAt', 'desc')
                        ->find();

                    if ($latestTest) {
                        Db::name('test_results')
                            ->where('id', $latestTest['id'])
                            ->update([
                                'orderId'  => $orderIdDb,
                                'updatedAt'=> $now,
                            ]);
                    }
                }
            }

            // 真实对接微信统一下单，生成 prepay_id 等参数
            $wechatConfig = [
                'appid'      => env('WECHAT_APPID', ''),          // 小程序 AppID
                'mch_id'     => env('MCH_ID', ''),                // 商户号
                'api_key'    => env('API_KEY', ''),               // API 密钥（MD5）
                'notify_url' => env('NOTIFY_URL', ''),            // 支付结果通知回调
            ];

            if (
                empty($wechatConfig['appid']) ||
                empty($wechatConfig['mch_id']) ||
                empty($wechatConfig['api_key']) ||
                empty($wechatConfig['notify_url'])
            ) {
                return error('微信支付配置缺失，请联系管理员检查 .env', 500);
            }

            if (empty($openId)) {
                return error('缺少微信 openId，无法发起支付', 400);
            }

            // 微信 out_trade_no 最长 32 字节，这里做一次截断适配
            $outTradeNo = strlen($orderId) > 32 ? substr($orderId, 0, 32) : $orderId;

            $unifiedOrder = $this->createWechatUnifiedOrder(
                $wechatConfig,
                $outTradeNo,
                $amountFenCalculated,
                $description ?: 'AI性格测试-' . $productType,
                $openId
            );

            if (empty($unifiedOrder['prepay_id'])) {
                $msg = $unifiedOrder['message'] ?? '微信统一下单失败';
                return error($msg, 500);
            }

            // 组装前端 wx.requestPayment 所需参数
            $timeStamp = (string) time();
            $nonceStr  = md5(uniqid('wxpay_', true));
            $pkg       = 'prepay_id=' . $unifiedOrder['prepay_id'];
            $signType  = 'MD5';

            $payParams = [
                'appId'     => $wechatConfig['appid'],
                'timeStamp' => $timeStamp,
                'nonceStr'  => $nonceStr,
                'package'   => $pkg,
                'signType'  => $signType,
            ];
            $paySign = $this->buildWechatSign($payParams, $wechatConfig['api_key']);

            $paymentData = [
                'timeStamp'  => $timeStamp,
                'nonceStr'   => $nonceStr,
                'package'    => $pkg,
                'signType'   => $signType,
                'paySign'    => $paySign,
                'prepayId'   => $unifiedOrder['prepay_id'],
            ];

            // 与小程序 payment.js 兼容；系统统一：金额均为分
            return success(array_merge($paymentData, [
                'orderId'      => $orderId,
                'orderDbId'    => $orderIdDb,
                'amount'       => $amountFenCalculated,
                'productType'  => $productType,
                'pricingType'  => $pricingType,
                'description'  => $description,
                'enterpriseId' => $enterpriseId,
            ]), '订单创建成功');
        } catch (\Exception $e) {
            return error('创建订单失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/payment/notify
     * 小程序在 wx.requestPayment 成功回调后调用，用于通知后端更新订单状态。
     * 当前实现为“前端通知模式”，后续可扩展为接收微信服务端回调。
     */
    public function notify()
    {
        try {
            $orderId  = Request::param('orderId', '');
            $prepayId = Request::param('prepayId', '');
            $status   = Request::param('status', 'success'); // success/failed/cancelled 等

            if (empty($orderId)) {
                return error('订单ID不能为空', 400);
            }

            $order = Db::name('orders')
                ->where('orderNo', $orderId)
                ->find();

            if (!$order) {
                return error('订单不存在', 404);
            }

            $prevStatus = (string) ($order['status'] ?? '');

            // 仅允许从 pending → 其他状态，避免重复更新已完成订单
            if ($order['status'] !== 'pending' && $order['status'] !== 'paid') {
                return success(null, '订单状态已更新，无需重复通知');
            }

            $now = time();
            $newStatus = $order['status'];

            if ($status === 'success') {
                $newStatus = 'paid';
            } elseif ($status === 'cancelled') {
                $newStatus = 'cancelled';
            } elseif ($status === 'failed') {
                $newStatus = 'failed';
            }

            Db::name('orders')
                ->where('id', $order['id'])
                ->update([
                    'status'   => $newStatus,
                    'payTime'  => $status === 'success' ? ($order['payTime'] ?: $now) : $order['payTime'],
                    'updatedAt'=> $now,
                ]);

            // 支付成功时：将关联该订单的测试结果标记为已付款，并记录当时付款金额（分）
            if ($status === 'success') {
                if ($prevStatus === 'pending') {
                    try {
                        FeishuLeadWebhookService::onOrderPaid((int) $order['id'], (int) ($order['userId'] ?? 0));
                    } catch (\Throwable $e) {
                    }
                }
                $paidAmountFen = isset($order['amount']) ? (int) $order['amount'] : 0;

                Db::name('test_results')
                    ->where('orderId', $order['id'])
                    ->update([
                        'isPaid'    => 1,
                        'paidAmount'=> $paidAmountFen ?: null,
                        'paidAt'    => $now,
                        'updatedAt' => $now,
                    ]);

                // 企业四项测试支付后，订单金额进入企业余额
                $this->creditEnterpriseBalanceForOrder($order, $paidAmountFen, $now);

                // AI 深度报告：支付成功后置 paid 并触发报告生成（幂等）
                if (($order['productType'] ?? '') === 'ai_deep_report' && !empty($order['orderNo'])) {
                    try {
                        AiReportService::markPaid((string) $order['orderNo']);
                    } catch (\Throwable $e) {
                    }
                }

                if (($order['productType'] ?? '') !== 'recharge') {
                    // 触发分销佣金结算
                    try {
                        \app\controller\api\Distribution::settleCommission((int) $order['id']);
                    } catch (\Exception $e) {
                        // 佣金结算失败不影响主流程
                    }
                }

                // 成交归因：写入一条 analytics_events，便于漏斗统计
                try {
                    $userId = (int) ($order['userId'] ?? 0);
                    $inviterRow = null;
                    if ($userId > 0) {
                        $inviterRow = Db::name('distribution_bindings')
                            ->where('inviteeId', $userId)
                            ->where('status', 'active')
                            ->where('expireAt', '>', time())
                            ->order('id', 'desc')
                            ->field('inviterId')
                            ->find();
                    }
                    $amountFenForEvent = (int) ($order['amount'] ?? 0);
                    $props = [
                        'orderId'   => (int) $order['id'],
                        'orderNo'   => $order['orderNo'] ?? '',
                        'amountFen' => $amountFenForEvent,
                        'amountYuan'=> $amountFenForEvent / 100,
                        'productType' => $order['productType'] ?? '',
                        'testType'  => $order['testType'] ?? '',
                        'inviterId' => $inviterRow ? (int) ($inviterRow['inviterId'] ?? 0) : 0,
                    ];
                    Db::name('analytics_events')->insert([
                        'userId'    => $userId ?: null,
                        'eventName' => 'pay_success_attribution',
                        'pagePath'  => 'server/payment/notify',
                        'propsJson' => json_encode($props, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                        'clientTs'  => (int) ($now * 1000),
                        'platform'  => 'server',
                        'sessionId' => null,
                        'createdAt' => date('Y-m-d H:i:s', $now),
                    ]);
                } catch (\Throwable $e) {
                    // 埋点失败不影响主流程
                }
            }

            return success([
                'orderId'  => $orderId,
                'status'   => $newStatus,
                'prepayId' => $prepayId,
            ], '订单状态已更新');
        } catch (\Exception $e) {
            return error('更新订单状态失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/payment/query
     * 小程序查询订单状态：实时通过商户订单号调用微信 v3 查询接口（不依赖本地状态）。
     */
    public function query()
    {
        try {
            $orderId = Request::param('orderId', '');
            if (empty($orderId)) {
                return error('订单ID不能为空', 400);
            }

            // 本地订单（可选，只用于补充非微信字段；真实支付状态以微信返回为准）
            $localOrder = Db::name('orders')
                ->where('orderNo', $orderId)
                ->find();

            $wechat = $this->queryWechatOrderByOutTradeNo($orderId);
            if (!$wechat['success']) {
                return error($wechat['message'] ?? '查询微信订单失败', 500);
            }

            $data = $wechat['data'] ?? [];
            $tradeState = $data['trade_state'] ?? 'UNKNOWN';
            $status = $this->mapTradeStateToStatus($tradeState);

            // 若本地有订单，顺带同步一次状态（不作为查询前置条件）
            $now = time();
            if ($localOrder && in_array($status, ['paid', 'completed', 'cancelled', 'refunded', 'failed'], true)) {
                $payTime = $localOrder['payTime'] ?? null;
                if (isset($data['time_end'])) {
                    $dt = \DateTime::createFromFormat('YmdHis', $data['time_end']);
                    if ($dt) {
                        $payTime = $dt->getTimestamp();
                    }
                }

                // 记录旧状态，用于后续判断是否从未支付 -> 已支付，避免重复统计
                $oldStatus = $localOrder['status'] ?? null;

                Db::name('orders')
                    ->where('id', $localOrder['id'])
                    ->update([
                        'status'              => $status,
                        'payTime'             => $payTime,
                        'wechatTransactionId' => $data['transaction_id'] ?? ($localOrder['wechatTransactionId'] ?? null),
                        'updatedAt'           => $now,
                    ]);

                // 同步更新关联的测试结果（按 orderId 关联），写入付款金额与时间
                $amountFromWechat = null;
                if (isset($data['total_fee'])) {
                    $amountFromWechat = (int) $data['total_fee'];
                }
                if (in_array($status, ['paid', 'completed', 'refunded'], true)) {
                    $finalAmount = $amountFromWechat ?? (int) $localOrder['amount'];
                    Db::name('test_results')
                        ->where('orderId', $localOrder['id'])
                        ->update([
                            'isPaid'     => $status === 'refunded' ? 0 : 1,
                            'paidAmount' => $finalAmount,
                            'paidAt'     => $payTime ?: $now,
                            'updatedAt'  => $now,
                        ]);

                    // 仅当本地原状态不是已支付/已完成/已退款时，才认为是「首次确认支付」，用于统计画像
                    $paidSet = ['paid', 'completed', 'refunded'];
                    if ($status !== 'refunded' && !in_array($oldStatus, $paidSet, true)) {
                        $userId = (int) ($localOrder['userId'] ?? 0);
                        $enterpriseId = isset($localOrder['enterpriseId']) ? (int) $localOrder['enterpriseId'] : null;
                        if (($localOrder['productType'] ?? '') !== 'recharge' && $userId > 0 && $finalAmount > 0) {
                            UserProfileModel::recordPayment($userId, $enterpriseId, $finalAmount);
                        }

                        // 企业四项测试支付后，订单金额进入企业余额
                        $this->creditEnterpriseBalanceForOrder($localOrder, $finalAmount, $now);

                        // AI 深度报告：查询确认支付时补偿置 paid（幂等）
                        if (($localOrder['productType'] ?? '') === 'ai_deep_report' && !empty($localOrder['orderNo'])) {
                            try {
                                AiReportService::markPaid((string) $localOrder['orderNo']);
                            } catch (\Throwable $e) {
                            }
                        }

                        if (($localOrder['productType'] ?? '') !== 'recharge') {
                            // 触发分销佣金结算
                            try {
                                \app\controller\api\Distribution::settleCommission((int) $localOrder['id']);
                            } catch (\Exception $e) {
                                // 佣金结算失败不影响主流程
                            }
                        }

                        try {
                            FeishuLeadWebhookService::onOrderPaid((int) $localOrder['id'], (int) ($localOrder['userId'] ?? 0));
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }

            // V2: 优先使用 total_fee，退回用本地金额
            $amountTotal = null;
            if (isset($data['total_fee'])) {
                $amountTotal = (int) $data['total_fee'];
            } elseif ($localOrder) {
                $amountTotal = (int) $localOrder['amount'];
            }

            // V2: 支付完成时间 time_end，格式 yyyyMMddHHmmss
            $payTimeTs = null;
            if (isset($data['time_end'])) {
                $dt = \DateTime::createFromFormat('YmdHis', $data['time_end']);
                if ($dt) {
                    $payTimeTs = $dt->getTimestamp();
                }
            } elseif ($localOrder) {
                $payTimeTs = $localOrder['payTime'] ?? null;
            }

            return success([
                'orderId'            => $orderId,
                'wechatTransactionId'=> $data['transaction_id'] ?? null,
                'tradeState'         => $tradeState,
                'tradeStateDesc'     => $data['trade_state_desc'] ?? null,
                'amount'             => $amountTotal,
                'status'             => $status,
                'payMethod'          => 'wechat',
                'payTime'            => $payTimeTs,
                'userId'             => $localOrder['userId']      ?? null,
                'enterpriseId'       => $localOrder['enterpriseId']?? null,
                'productType'        => $localOrder['productType'] ?? null,
                'createdAt'          => $localOrder['createdAt']   ?? null,
            ]);
        } catch (\Exception $e) {
            return error('查询订单失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 解析当前请求中的用户信息（优先使用中间件注入的 user，其次从 JWT 中解析）
     */
    protected function resolveUser(): ?array
    {
        $user = $this->request->user ?? null;
        if ($user) {
            return is_array($user) ? $user : (array) $user;
        }

        $token = JwtService::getTokenFromRequest($this->request);
        if (!$token) {
            return null;
        }

        $payload = JwtService::verifyToken($token);
        if (!$payload) {
            return null;
        }

        return [
            'source' => $payload['source'] ?? '',
            'user_id'=> $payload['user_id'] ?? $payload['userId'] ?? null,
            'userId' => $payload['user_id'] ?? $payload['userId'] ?? null,
        ];
    }

    /**
     * 根据用户最近一次测试记录推断企业ID（若存在）
     */
    protected function resolveEnterpriseId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $row = Db::name('wechat_users')->where('id', $userId)->field('enterpriseId')->find();
        if (empty($row['enterpriseId'])) {
            return null;
        }
        return (int) $row['enterpriseId'];
    }

    /**
     * 企业四项测试支付成功后，将订单金额计入企业余额。
     * 使用 finance_records + orderId 做幂等，避免 notify/query 重复入账。
     */
    protected function creditEnterpriseBalanceForOrder(array $order, int $amountFen, int $now): void
    {
        $enterpriseId = (int) ($order['enterpriseId'] ?? 0);
        $productType = (string) ($order['productType'] ?? '');
        $orderDbId = (int) ($order['id'] ?? 0);

        if ($enterpriseId <= 0 || $orderDbId <= 0 || $amountFen <= 0) {
            return;
        }

        if (!in_array($productType, ['face', 'mbti', 'sbti', 'disc', 'pdp', 'resume', 'recharge'], true)) {
            return;
        }

        $exists = Db::name('finance_records')
            ->where('enterpriseId', $enterpriseId)
            ->where('orderId', $orderDbId)
            ->where('type', 'recharge')
            ->find();
        if ($exists) {
            return;
        }

        Db::startTrans();
        try {
            $enterprise = Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->field('id, name, balance')
                ->lock(true)
                ->find();
            if (!$enterprise) {
                Db::rollback();
                return;
            }

            $beforeFen = (int) ($enterprise['balance'] ?? 0);
            $afterFen = $beforeFen + $amountFen;

            Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->update([
                    'balance' => $afterFen,
                    'updatedAt' => $now,
                ]);

            Db::name('finance_records')->insert([
                'enterpriseId' => $enterpriseId,
                'type' => 'recharge',
                'amount' => $amountFen,
                'balanceBefore' => $beforeFen,
                'balanceAfter' => $afterFen,
                'description' => $productType === 'recharge'
                    ? '企业余额充值'
                    : ('企业测试收入：' . strtoupper($productType)),
                'orderId' => $orderDbId,
                'createdAt' => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
        }
    }

    /**
     * 计算订单金额（分）和定价类型
     * 定价配置中单价为「元」时，在此处乘以 100 转为分；前端传入的 requestAmountFen 已是分。
     *
     * @param string   $productType        产品类型
     * @param int        $quantity         购买数量
     * @param int        $requestAmountFen 前端传入金额（分），部分类型作为兜底
     * @param array|null $user             当前用户信息
     * @param int|null   $enterpriseId     推断出的企业ID
     * @param string     $deepProductId    深度服务套餐ID/产品Key（deep-pricing.categories.id/productKey）
     * @return array [amountFen, pricingType]
     */
    protected function calculateAmount(
        string $productType,
        int $quantity,
        int $requestAmountFen,
        ?array $user,
        ?int $enterpriseId,
        string $deepProductId = ''
    ): array {
        $pricingType = 'personal';
        $pricingEnterpriseId = null;  // 定价用企业 ID（个人测试但有归属企业时也传入）

        if ($user && ($user['source'] ?? '') === 'wechat') {
            $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
            if ($userId > 0 && !empty($enterpriseId)) {
                $pricingType = 'enterprise';
                $pricingEnterpriseId = $enterpriseId;
            } elseif ($userId > 0 && empty($enterpriseId)) {
                // 个人测试：查 wechat_users.enterpriseId，若有则使用企业专属个人定价
                $userEid = Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
                if (!empty($userEid)) {
                    $pricingEnterpriseId = (int) $userEid;
                }
            }
        }

        $quantity = $quantity > 0 ? $quantity : 1;

        // 1）测试类产品：定价配置中为元，转为分（企业用户按企业ID取价）
        $testProductTypes = ['face', 'mbti', 'sbti', 'disc', 'pdp', 'resume', 'report', 'team_analysis'];
        if (in_array($productType, $testProductTypes, true)) {
            $pricingConfig = PricingConfigModel::getByTypeAndEnterprise($pricingType, $pricingEnterpriseId ?? $enterpriseId);
            $config = [];
            if ($pricingConfig && !empty($pricingConfig->config)) {
                $raw = $pricingConfig->config;
                $config = is_array($raw) ? $raw : (array) $raw;
            }

            $keyMap = ['team_analysis' => 'teamAnalysis'];
            $key = $keyMap[$productType] ?? $productType;
            $unitPriceYuan = isset($config[$key]) ? (float) $config[$key] : 0.0;
            $amountFen = (int) round($unitPriceYuan * 100 * $quantity);
            return [$amountFen, $pricingType];
        }

        // 2）深度服务：定价配置为元，转为分
        //    与 AppConfig::deepPricing 使用同一套配置：
        //    - 个人版：type=deep_personal，config.categories[].price
        //    - 企业版：type=deep_enterprise，config.categories[].price
        if (in_array($productType, ['deep_personal', 'deep_team'], true)) {
            $type = $productType === 'deep_team' ? 'deep_enterprise' : 'deep_personal';
            $configModel = PricingConfigModel::where('type', $type)->whereNull('enterpriseId')->find();
            $unitPriceYuan = 0.0;

            if ($configModel && !empty($configModel->config)) {
                $raw = $configModel->config;
                $data = is_array($raw) ? $raw : (array) $raw;
                $categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];

                if (!empty($categories)) {
                    // 若传入 deepProductId，则优先根据 id 或 productKey 精确匹配对应套餐
                    if ($deepProductId !== '') {
                        foreach ($categories as $cat) {
                            $cid = (string) ($cat['id'] ?? '');
                            $ckey = (string) ($cat['productKey'] ?? '');
                            if ($deepProductId === $cid || $deepProductId === $ckey) {
                                $unitPriceYuan = isset($cat['price']) ? (float) $cat['price'] : 0.0;
                                break;
                            }
                        }
                    }
                    // 未指定或未匹配到时，回退到第一项价格
                    if ($unitPriceYuan <= 0.0) {
                        $first = $categories[0];
                        $unitPriceYuan = isset($first['price']) ? (float) $first['price'] : 0.0;
                    }
                }
            }

            // 兼容旧版 deep 配置：若 categories 为空，则回退到 type=deep 的 personal/team 字段
            if ($unitPriceYuan <= 0.0) {
                $deepModel = PricingConfigModel::getByTypeAndEnterprise('deep', null);
                if ($deepModel && !empty($deepModel->config)) {
                    $rawDeep = $deepModel->config;
                    $deepConfig = is_array($rawDeep) ? $rawDeep : (array) $rawDeep;
                    $key = $productType === 'deep_team' ? 'team' : 'personal';
                    if (isset($deepConfig[$key])) {
                        $unitPriceYuan = (float) $deepConfig[$key];
                    }
                }
            }

            $amountFen = (int) round($unitPriceYuan * 100 * $quantity);
            return [$amountFen, $pricingType];
        }

        // 3）充值 / 4）VIP 等 / 5）未知：直接使用前端传入的金额（分）
        $amountFen = $requestAmountFen > 0 ? $requestAmountFen : 0;
        return [$amountFen, $pricingType];
    }

    /**
     * 调用微信 V2：根据商户订单号查询订单（JSAPI/小程序支付）
     * 文档：https://pay.weixin.qq.com/doc/v2/merchant/4011941128
     */
    protected function queryWechatOrderByOutTradeNo(string $outTradeNo): array
    {
        $appid  = env('WECHAT_APPID', '');
        $mchid  = env('MCH_ID', '');
        $apiKey = env('API_KEY', '');

        if (!$appid || !$mchid || !$apiKey) {
            return [
                'success' => false,
                'message' => '微信支付 V2 查询配置缺失，请检查 WECHAT_APPID / MCH_ID / API_KEY',
            ];
        }

        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';

        $params = [
            'appid'        => $appid,
            'mch_id'       => $mchid,
            'nonce_str'    => md5(uniqid('orderquery_', true)),
            'out_trade_no' => $outTradeNo,
        ];
        $params['sign'] = $this->buildWechatSign($params, $apiKey);

        $xml = $this->arrayToXml($params);
        $response = $this->postXml($url, $xml, 10);
        if ($response === false) {
            return ['success' => false, 'message' => '调用微信 V2 查询接口失败'];
        }

        $data = $this->xmlToArray($response);
        if (!is_array($data) || ($data['return_code'] ?? '') !== 'SUCCESS') {
            $msg = $data['return_msg'] ?? '微信 V2 返回失败';
            return ['success' => false, 'message' => $msg, 'raw' => $data];
        }

        if (($data['result_code'] ?? '') !== 'SUCCESS') {
            $err = $data['err_code_des'] ?? $data['err_code'] ?? '微信 V2 查询失败';
            return ['success' => false, 'message' => $err, 'raw' => $data];
        }

        // V2 返回字段：trade_state / trade_state_desc / total_fee / transaction_id / time_end 等
        return ['success' => true, 'data' => $data];
    }

    /**
     * 将微信 trade_state 映射为本地订单状态
     */
    protected function mapTradeStateToStatus(string $tradeState): string
    {
        $tradeState = strtoupper($tradeState);
        switch ($tradeState) {
            case 'SUCCESS':
                return 'paid';
            case 'REFUND':
                return 'refunded';
            case 'NOTPAY':
            case 'USERPAYING':
                return 'pending';
            case 'CLOSED':
            case 'REVOKED':
                return 'cancelled';
            case 'PAYERROR':
                return 'failed';
            default:
                return 'pending';
        }
    }

    /**
     * 调用微信统一下单接口（JSAPI）
     * 文档：https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     */
    protected function createWechatUnifiedOrder(
        array $config,
        string $orderNo,
        int $amountFen,
        string $body,
        string $openId
    ): array {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

        $params = [
            'appid'            => $config['appid'],
            'mch_id'           => $config['mch_id'],
            'nonce_str'        => md5(uniqid('wxpay_unified_', true)),
            'body'             => mb_substr($body, 0, 40),
            'out_trade_no'     => $orderNo,
            'total_fee'        => $amountFen,
            'spbill_create_ip' => $this->request ? $this->request->ip() : '127.0.0.1',
            'notify_url'       => $config['notify_url'],
            'trade_type'       => 'JSAPI',
            'openid'           => $openId,
        ];

        $params['sign'] = $this->buildWechatSign($params, $config['api_key']);

        $xml = $this->arrayToXml($params);
        $response = $this->postXml($url, $xml, 30);

        if ($response === false) {
            return ['success' => false, 'message' => '请求微信支付接口失败'];
        }

        $data = $this->xmlToArray($response);
        if (!is_array($data)) {
            return ['success' => false, 'message' => '解析微信支付返回失败'];
        }

        if (($data['return_code'] ?? '') !== 'SUCCESS') {
            return ['success' => false, 'message' => ($data['return_msg'] ?? '微信返回失败')];
        }

        if (($data['result_code'] ?? '') !== 'SUCCESS') {
            $err = ($data['err_code_des'] ?? $data['err_code'] ?? '微信下单失败');
            return ['success' => false, 'message' => $err];
        }

        return [
            'success'    => true,
            'prepay_id'  => $data['prepay_id'] ?? '',
            'raw'        => $data,
        ];
    }

    /**
     * 构造微信支付签名（MD5，参数 ASCII 排序后拼接 &key=API_KEY）
     */
    protected function buildWechatSign(array $params, string $apiKey): string
    {
        ksort($params);
        $buff = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null || $k === 'sign') {
                continue;
            }
            $buff[] = $k . '=' . $v;
        }
        $string = implode('&', $buff) . '&key=' . $apiKey;
        return strtoupper(md5($string));
    }

    protected function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<{$key}>{$val}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    protected function xmlToArray(string $xml)
    {
        $data = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($data === false) {
            return null;
        }
        return json_decode(json_encode($data), true);
    }

    protected function postXml(string $url, string $xml, int $timeout = 30)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $response;
    }

}

