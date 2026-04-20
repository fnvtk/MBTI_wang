<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\WechatService;
use app\common\service\PosterService;
use think\facade\Request;
use think\facade\Db;
use think\facade\Log;

/**
 * 分销控制器（小程序端）
 *
 * 路由前缀：/api/distribution
 * 所有接口需登录（JWT）
 */
class Distribution extends BaseController
{
    /** 绑定有效期（秒），30天 */
    const BINDING_DAYS = 30;
    const BINDING_TTL  = 30 * 86400;

    /** 提现金额下限（分），1 分（0.01 元）；具体规则以超管 minWithdrawFen 为准，不低于本值 */
    const MIN_WITHDRAW_FEN = 1;
    /** 提现金额上限（分），200元 */
    const MAX_WITHDRAW_FEN = 20000;
    /** 待收款过期时间（秒），24小时 */
    const WITHDRAW_WAIT_EXPIRE_SEC = 86400;

    // ─────────────────────────────────────────────────────────────
    //  POST /api/distribution/bind
    //  用户点击分享链接进入小程序后调用，建立/续期/抢绑 推荐关系
    // ─────────────────────────────────────────────────────────────
    public function bind()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $inviteeId   = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $inviterId   = (int) Request::param('inviterId', 0);
        $enterpriseId = Request::param('eid', null);
        $enterpriseId = $enterpriseId !== null ? (int) $enterpriseId : null;
        $scope       = $enterpriseId ? 'enterprise' : 'personal';

        // 自绑校验
        if ($inviterId <= 0 || $inviterId === $inviteeId) {
            return success(null, '无需绑定');
        }

        // 企业版：推荐人必须是该企业成员
        if ($scope === 'enterprise') {
            $inviter = Db::name('wechat_users')
                ->where('id', $inviterId)
                ->field('id, enterpriseId')
                ->find();
            if (!$inviter || (int) $inviter['enterpriseId'] !== $enterpriseId) {
                return success(null, '无需绑定');
            }
        }

        $now = time();

        // 禁止互相绑定（仅有效期内）：A 曾邀请过 B 且 A→B 未过期时，B 不能再成为 A 的推荐人；若 A→B 已过期则允许 A 绑定 B
        $reverseExists = Db::name('distribution_bindings')
            ->where('inviterId', $inviteeId)
            ->where('inviteeId', $inviterId)
            ->where('scope', $scope)
            ->where('status', 'active')
            ->where('expireAt', '>', $now)
            ->where(function ($query) use ($enterpriseId) {
                if ($enterpriseId) {
                    $query->where('enterpriseId', $enterpriseId);
                } else {
                    $query->whereNull('enterpriseId');
                }
            })
            ->find();
        if ($reverseExists) {
            return success(null, '无需绑定');
        }
        $expireAt = $now + self::BINDING_TTL;

        // 查询当前是否存在有效绑定（包含已过期，因为唯一索引覆盖所有状态）
        $existing = Db::name('distribution_bindings')
            ->where('inviteeId', $inviteeId)
            ->where('scope', $scope)
            ->where(function ($query) use ($enterpriseId) {
                if ($enterpriseId) {
                    $query->where('enterpriseId', $enterpriseId);
                } else {
                    $query->whereNull('enterpriseId');
                }
            })
            ->find();

        if (!$existing) {
            // ── 首次绑定
            Db::name('distribution_bindings')->insert([
                'inviterId'    => $inviterId,
                'inviteeId'    => $inviteeId,
                'scope'        => $scope,
                'enterpriseId' => $enterpriseId,
                'expireAt'     => $expireAt,
                'status'       => 'active',
                'prevInviterId'=> null,
                'overriddenAt' => null,
                'createdAt'    => $now,
                'updatedAt'    => $now,
            ]);
        } elseif ((int) $existing['inviterId'] === $inviterId) {
            // ── 同一推荐人再次点击 → 续期
            Db::name('distribution_bindings')
                ->where('id', $existing['id'])
                ->update([
                    'expireAt'  => $expireAt,
                    'status'    => 'active',
                    'updatedAt' => $now,
                ]);
        } else {
            // ── 不同推荐人：有效期内禁止更换（对齐「一级一月 / 30 天锁定」策略，参考 Soul 分销规则）
            $exExpire = (int) ($existing['expireAt'] ?? 0);
            $exStatus = (string) ($existing['status'] ?? '');
            if ($exStatus === 'active' && $exExpire > $now) {
                return success(null, '绑定有效期内暂不可更换推荐人，到期后可重新绑定');
            }
            // ── 已过期或非 active → 允许抢绑（覆盖，记录旧推荐人）
            Db::name('distribution_bindings')
                ->where('id', $existing['id'])
                ->update([
                    'prevInviterId' => (int) $existing['inviterId'],
                    'inviterId'     => $inviterId,
                    'expireAt'      => $expireAt,
                    'status'        => 'active',
                    'overriddenAt'  => $now,
                    'updatedAt'     => $now,
                ]);
        }

        return success(['expireAt' => $expireAt], '绑定成功');
    }

    /**
     * 过期待收款提现：status=2 超过24小时未确认收款的，自动退回余额并标记为已过期
     */
    private function expirePendingWithdrawals()
    {
        $now   = time();
        $limit = $now - self::WITHDRAW_WAIT_EXPIRE_SEC;
        $list  = Db::name('distribution_withdrawals')
            ->where('status', 2)
            ->select()
            ->toArray();
        foreach ($list as $row) {
            $ts = (int) ($row['auditAt'] ?? $row['updatedAt'] ?? $row['createdAt'] ?? 0);
            if ($ts <= 0 || $ts >= $limit) {
                continue;
            }
            $id        = (int) $row['id'];
            $userId    = (int) $row['userId'];
            $amountFen = (int) $row['amountFen'];
            if ($amountFen <= 0) {
                continue;
            }
            Db::startTrans();
            try {
                Db::name('wechat_users')
                    ->where('id', $userId)
                    ->inc('walletBalance', $amountFen)
                    ->update(['updatedAt' => $now]);
                Db::name('distribution_withdrawals')->where('id', $id)->update([
                    'status'    => 4,
                    'auditNote' => '超时未确认收款，已自动退回余额',
                    'auditAt'   => $now,
                    'updatedAt' => $now,
                ]);
                Db::commit();
                Log::info('提现过期自动退回', ['id' => $id, 'userId' => $userId, 'amountFen' => $amountFen]);
            } catch (\Throwable $e) {
                Db::rollback();
                Log::error('expirePendingWithdrawals error: ' . $e->getMessage());
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/stats
    //  推广中心统计数据（余额、总收益、待入账、绑定数、付款数）
    // ─────────────────────────────────────────────────────────────
    public function stats()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $inviterId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $now       = time();

        $this->expirePendingWithdrawals();

        // 直接读取 wechat_users 中的钱包字段（持久化数据）
        $walletBalanceFen = 0;
        $totalEarnedFen   = 0;
        $pendingFen       = 0;
        try {
            $walletRow = Db::name('wechat_users')
                ->where('id', $inviterId)
                ->field('walletBalance, walletTotalEarned, walletPending')
                ->find();
            if ($walletRow) {
                $walletBalanceFen = (int) ($walletRow['walletBalance'] ?? 0);
                $totalEarnedFen   = (int) ($walletRow['walletTotalEarned'] ?? 0);
                $pendingFen       = (int) ($walletRow['walletPending'] ?? 0);
            }
        } catch (\Exception $e) {
            // 表结构异常时静默降级为 0
        }

        // 绑定中人数（active 且未过期）
        $bindingCount = 0;
        try {
            $bindingCount = Db::name('distribution_bindings')
                ->where('inviterId', $inviterId)
                ->where('status', 'active')
                ->where('expireAt', '>', $now)
                ->count();
        } catch (\Exception $e) {}

        // 已付款人数
        $paidCount = 0;
        try {
            $paidCount = Db::name('commission_records')
                ->where('inviterId', $inviterId)
                ->whereIn('status', ['paid', 'frozen'])
                ->distinct(true)
                ->count('inviteeId');
        } catch (\Exception $e) {}

        // 即将到期（7天内过期）
        $expiringCount = 0;
        try {
            $expiringCount = Db::name('distribution_bindings')
                ->where('inviterId', $inviterId)
                ->where('status', 'active')
                ->where('expireAt', '>', $now)
                ->where('expireAt', '<=', $now + 7 * 86400)
                ->count();
        } catch (\Exception $e) {}

        // 总邀请人数（历史所有绑定过的唯一用户数）
        $totalInvite = 0;
        try {
            $totalInvite = Db::name('distribution_bindings')
                ->where('inviterId', $inviterId)
                ->distinct(true)
                ->count('inviteeId');
        } catch (\Exception $e) {}

        // 根据用户所属企业读取分销配置（显示开关 + 推广中心标题）
        $enterpriseId = 0;
        try {
            $wu = Db::name('wechat_users')->where('id', $inviterId)->field('enterpriseId')->find();
            $enterpriseId = $wu && isset($wu['enterpriseId']) ? (int) $wu['enterpriseId'] : 0;
        } catch (\Exception $e) {}
        $distConfig = null;
        try {
            $distRow = Db::name('system_config')
                ->where('key', 'distribution')
                ->where('enterprise_id', $enterpriseId)
                ->find();
            if (!$distRow && $enterpriseId > 0) {
                $distRow = Db::name('system_config')
                    ->where('key', 'distribution')
                    ->where('enterprise_id', 0)
                    ->find();
            }
            if ($distRow && $distRow['value']) {
                $distConfig = is_string($distRow['value']) ? json_decode($distRow['value'], true) : $distRow['value'];
            }
        } catch (\Exception $e) {}
        $distributionEnabled = ($distConfig['enabled'] ?? true);
        $promoCenterTitle    = trim((string)($distConfig['promoCenterTitle'] ?? '推广中心')) ?: '推广中心';
        $commissionRate      = (int)($distConfig['commissionRate'] ?? 90);
        $bindingDays         = (int)($distConfig['bindingDays'] ?? 30);

        // 提现规则仅超管可配置，只读 enterprise_id=0 的全局配置
        $globalDistConfig = null;
        try {
            $globalRow = Db::name('system_config')
                ->where('key', 'distribution')
                ->where('enterprise_id', 0)
                ->find();
            if ($globalRow && $globalRow['value']) {
                $globalDistConfig = is_string($globalRow['value']) ? json_decode($globalRow['value'], true) : $globalRow['value'];
            }
        } catch (\Exception $e) {}
        $cfg = is_array($globalDistConfig) ? $globalDistConfig : [];
        $minWithdrawFen  = (int)($cfg['minWithdrawFen'] ?? 1);
        $minWithdrawFen  = max(1, min(self::MAX_WITHDRAW_FEN, $minWithdrawFen));
        $maxWithdrawFen  = (int)($cfg['maxWithdrawFen'] ?? 0);
        $withdrawFee     = (float)($cfg['withdrawFee'] ?? 0);
        $requireAudit    = (isset($cfg['requireAudit']) ? $cfg['requireAudit'] : true) !== false;
        $withdrawMinYuan = number_format($minWithdrawFen / 100, 2, '.', '');
        $withdrawMaxYuan = $maxWithdrawFen > 0 ? number_format($maxWithdrawFen / 100, 2, '.', '') : null;
        $withdrawFeePct  = round($withdrawFee, 1);

        // 读取「人脸分析」测试佣金配置，用于前端展示规则说明
        $faceSetting = null;
        try {
            $faceSetting = self::resolveTestSetting('face', $enterpriseId > 0 ? $enterpriseId : null);
        } catch (\Throwable $e) {
            $faceSetting = null;
        }
        $faceType        = $faceSetting['commissionType'] ?? null;
        $faceRate        = isset($faceSetting['commissionRate']) ? (int)$faceSetting['commissionRate'] : null;
        $faceAmountFen   = isset($faceSetting['commissionAmountFen']) ? (int)$faceSetting['commissionAmountFen'] : null;
        $faceAmountYuan  = $faceAmountFen !== null ? number_format($faceAmountFen / 100, 2, '.', '') : null;
        $faceNoPayment   = !empty($faceSetting['noPayment']);

        return success([
            'walletBalance'       => number_format($walletBalanceFen / 100, 2, '.', ''),
            'totalEarned'         => number_format($totalEarnedFen / 100, 2, '.', ''),
            'pendingAmount'       => number_format($pendingFen / 100, 2, '.', ''),
            'bindingCount'        => $bindingCount,
            'paidCount'           => $paidCount,
            'expiringCount'       => $expiringCount,
            'totalInvite'         => $totalInvite,
            'distributionEnabled' => $distributionEnabled,
            'promoCenterTitle'    => $promoCenterTitle,
            'commissionRate'      => $commissionRate,
            'bindingDays'         => $bindingDays,
            'testCommissionType'  => $faceType,
            'testCommissionRate'  => $faceRate,
            'testCommissionAmount'=> $faceAmountYuan,
            'testNoPayment'       => $faceNoPayment,
            'withdrawMinYuan'     => $withdrawMinYuan,
            'withdrawMaxYuan'     => $withdrawMaxYuan,
            'withdrawFeePct'      => $withdrawFeePct,
            'requireWithdrawAudit'=> $requireAudit,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/bindings
    //  我邀请的用户列表（分页，tab: 0=绑定中 1=已付款 2=已过期）
    // ─────────────────────────────────────────────────────────────
    public function bindings()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $inviterId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $tab       = (int) Request::param('tab', 0);
        $page      = max(1, (int) Request::param('page', 1));
        $pageSize  = min(50, (int) Request::param('pageSize', 20));
        $now       = time();

        $query = Db::name('distribution_bindings')
            ->alias('b')
            ->leftJoin('wechat_users u', 'b.inviteeId = u.id')
            ->field('b.id, b.inviteeId, b.expireAt, b.status, b.createdAt, b.overriddenAt,
                     u.nickname, u.avatar')
            ->where('b.inviterId', $inviterId);

        switch ($tab) {
            case 1: // 已付款
                $paidInviteeIds = Db::name('commission_records')
                    ->where('inviterId', $inviterId)
                    ->whereIn('status', ['paid', 'frozen'])
                    ->column('inviteeId');
                if (empty($paidInviteeIds)) {
                    return success(['list' => [], 'total' => 0, 'page' => $page, 'pageSize' => $pageSize]);
                }
                $query->whereIn('b.inviteeId', array_unique($paidInviteeIds));
                break;
            case 2: // 已过期
                $query->where(function ($q) use ($now) {
                    $q->where('b.status', 'overridden')
                      ->whereOr(function ($q2) use ($now) {
                          $q2->where('b.status', 'active')->where('b.expireAt', '<=', $now);
                      });
                });
                break;
            default: // 绑定中
                $query->where('b.status', 'active')->where('b.expireAt', '>', $now);
                break;
        }

        $total = (clone $query)->count();
        $list  = $query->order('b.updatedAt', 'desc')
                       ->page($page, $pageSize)
                       ->select()
                       ->toArray();

        foreach ($list as &$row) {
            $row['expireAt']    = (int) $row['expireAt'];
            $row['remainDays']  = max(0, (int) ceil(($row['expireAt'] - $now) / 86400));
            $row['avatar']      = $row['avatar'] ?: '';
            $row['nickname']    = $row['nickname'] ?: '微信用户';
        }

        return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/commissions
    //  我的佣金记录（分页）
    // ─────────────────────────────────────────────────────────────
    public function commissions()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $inviterId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $page      = max(1, (int) Request::param('page', 1));
        $pageSize  = min(50, (int) Request::param('pageSize', 20));

        $list = Db::name('commission_records')
            ->alias('c')
            ->leftJoin('wechat_users u', 'c.inviteeId = u.id')
            ->field('c.id, c.commissionFen, c.orderAmount, c.status, c.scope, c.createdAt,
                     c.frozenAt, c.unfrozenAt, u.nickname, u.avatar')
            ->where('c.inviterId', $inviterId)
            ->order('c.createdAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $total = Db::name('commission_records')
            ->where('inviterId', $inviterId)
            ->count();

        foreach ($list as &$row) {
            $row['commissionYuan'] = number_format($row['commissionFen'] / 100, 2, '.', '');
            $row['orderYuan']      = number_format($row['orderAmount'] / 100, 2, '.', '');
            $row['nickname']       = $row['nickname'] ?: '微信用户';
            $row['avatar']         = $row['avatar'] ?: '';
        }

        return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    // ─────────────────────────────────────────────────────────────
    //  POST /api/distribution/withdraw
    //  申请提现
    // ─────────────────────────────────────────────────────────────
    public function withdraw()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $this->expirePendingWithdrawals();

        $userId    = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $amountFen = (int) Request::param('amountFen', 0);
        $scope     = Request::param('scope', 'personal');
        $eid       = $scope === 'enterprise' ? (int) Request::param('eid', 0) : null;

        list($minFen, $maxFen) = self::getWithdrawLimits($scope, $eid);
        if ($amountFen < $minFen) {
            return error('最低提现金额为 ' . round($minFen / 100, 2) . ' 元', 400);
        }
        if ($maxFen > 0 && $amountFen > $maxFen) {
            return error('最高提现金额为 ' . round($maxFen / 100, 2) . ' 元', 400);
        }

        $wallet = Db::name('wechat_users')
            ->where('id', $userId)
            ->field('walletBalance')
            ->find();

        if (!$wallet || (int) $wallet['walletBalance'] < $amountFen) {
            return error('余额不足', 400);
        }

        // 检查是否有待审核的提现申请（status=0 审核中）
        $pending = Db::name('distribution_withdrawals')
            ->where('userId', $userId)
            ->where('status', 0)
            ->count();
        if ($pending > 0) {
            return error('您有待处理的提现申请，请等待审核完成后再次申请', 400);
        }

        // 手续费（分）：按全局配置 enterprise_id=0 的 withdrawFee 比例计算
        $cfg       = self::getDistributionConfig($scope ?: 'personal', $eid);
        $feePct    = (float)($cfg['withdrawFee'] ?? 0);
        $feeFen    = (int) round($amountFen * $feePct / 100);
        $actualFen = $amountFen - $feeFen;
        if ($actualFen < $minFen) {
            return error('实际到账金额不得低于最低提现金额 ¥' . number_format($minFen / 100, 2, '.', ''), 400);
        }

        $requireAudit = (isset($cfg['requireAudit']) ? $cfg['requireAudit'] : true) !== false;

        $now = time();
        Db::startTrans();
        try {
            // 冻结余额
            Db::name('wechat_users')
                ->where('id', $userId)
                ->dec('walletBalance', $amountFen)
                ->update(['updatedAt' => $now]);

            // 写入提现申请：status=0 审核中，并记录手续费（需 insertGetId 以便免审核时更新）
            $withdrawId = Db::name('distribution_withdrawals')->insertGetId([
                'userId'    => $userId,
                'amountFen' => $amountFen,
                'feeFen'    => $feeFen,
                'status'    => 0,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error('申请提现失败：' . $e->getMessage(), 500);
        }

        // 免审核：自动发起微信转账
        if (!$requireAudit && $withdrawId > 0) {
            $wechatUser = Db::name('wechat_users')->where('id', $userId)->field('openid')->find();
            $openid     = $wechatUser['openid'] ?? '';
            if (empty($openid)) {
                Db::startTrans();
                try {
                    Db::name('wechat_users')->where('id', $userId)->inc('walletBalance', $amountFen)->update(['updatedAt' => time()]);
                    Db::name('distribution_withdrawals')->where('id', $withdrawId)->update([
                        'status'    => 1,
                        'auditNote' => '无 openid 无法自动打款，请联系管理员',
                        'auditAt'   => time(),
                        'updatedAt' => time(),
                    ]);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                }
                return error('无法自动打款：未绑定微信 openid，请联系管理员', 400);
            }

            try {
                $outBillNo = 'TX' . date('YmdHis') . mt_rand(1000, 9999) . $withdrawId;
                $service   = new \app\common\service\WechatTransferService();
                $result    = $service->createTransfer([
                    'out_bill_no'                  => $outBillNo,
                    'openid'                       => $openid,
                    'transfer_amount'              => $amountFen,
                    'transfer_remark'              => '推广佣金提现',
                    'transfer_scene_id'            => env('TRANSFER_SCENE_ID', '1005'),
                    'transfer_scene_report_infos'   => [
                        ['info_type' => '岗位类型', 'info_content' => '推广人员'],
                        ['info_type' => '报酬说明', 'info_content' => '推广佣金提现'],
                    ],
                    'notify_url'                   => env('WITHDRAW_NOTIFY_URL', ''),
                ]);

                if ($result['success'] === true) {
                    $wechatData = $result['data'] ?? [];
                    $transferBillNo = $wechatData['transfer_bill_no'] ?? $wechatData['batch_id'] ?? null;
                    $wechatState    = $wechatData['state'] ?? $wechatData['batch_status'] ?? 'PROCESSING';
                    Db::name('distribution_withdrawals')->where('id', $withdrawId)->update([
                        'status'            => 2,
                        'auditAt'           => $now,
                        'updatedAt'         => $now,
                        'pay_type'          => 'wechat',
                        'out_bill_no'       => $outBillNo,
                        'transfer_bill_no'  => $transferBillNo,
                        'wechat_pay_state'  => $wechatState,
                        'transfer_scene_id' => $wechatData['transfer_scene_id'] ?? env('TRANSFER_SCENE_ID', '1005'),
                        'package_info'      => $wechatData['package_info'] ?? '',
                        'mch_id'            => env('MCH_ID', null),
                    ]);
                    return success(null, '提现申请已提交，已自动发起微信转账');
                }

                $err   = $result['error'] ?? [];
                $code  = $err['code'] ?? 'UNKNOWN';
                $msg   = $err['message'] ?? '微信转账接口调用失败';
                Db::startTrans();
                try {
                    Db::name('wechat_users')->where('id', $userId)->inc('walletBalance', $amountFen)->update(['updatedAt' => time()]);
                    Db::name('distribution_withdrawals')->where('id', $withdrawId)->update([
                        'status'    => 1,
                        'auditNote' => "微信转账发起失败（{$code}）：{$msg}",
                        'auditAt'   => time(),
                        'updatedAt' => time(),
                    ]);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                }
                return error("自动打款失败（{$code}）：{$msg}", 500);
            } catch (\Exception $e) {
                Db::startTrans();
                try {
                    Db::name('wechat_users')->where('id', $userId)->inc('walletBalance', $amountFen)->update(['updatedAt' => time()]);
                    Db::name('distribution_withdrawals')->where('id', $withdrawId)->update([
                        'status'    => 1,
                        'auditNote' => '自动打款异常：' . $e->getMessage(),
                        'auditAt'   => time(),
                        'updatedAt' => time(),
                    ]);
                    Db::commit();
                } catch (\Exception $ex) {
                    Db::rollback();
                }
                return error('自动打款异常：' . $e->getMessage(), 500);
            }
        }

        return success(null, '提现申请已提交，请等待审核');
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/withdrawals
    //  我的提现记录
    // ─────────────────────────────────────────────────────────────
    public function withdrawals()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $userId   = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $page     = max(1, (int) Request::param('page', 1));
        $pageSize = min(50, (int) Request::param('pageSize', 20));

        $this->expirePendingWithdrawals();

        $list = Db::name('distribution_withdrawals')
            ->where('userId', $userId)
            ->order('createdAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $total = Db::name('distribution_withdrawals')
            ->where('userId', $userId)
            ->count();

        foreach ($list as &$row) {
            $row['amountYuan'] = number_format($row['amountFen'] / 100, 2, '.', '');
            $feeFen = (int)($row['feeFen'] ?? 0);
            $row['feeYuan']   = number_format($feeFen / 100, 2, '.', '');
        }

        return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
    }

    // ─────────────────────────────────────────────────────────────
    //  POST /api/distribution/withdrawals/query-transfer
    //  用户确认收款后，主动查询微信转账单状态并更新本地订单（及时刷新）
    //  参考：https://pay.weixin.qq.com/doc/v3/merchant/4012716437
    // ─────────────────────────────────────────────────────────────
    public function queryTransfer()
    {
        $user = $this->resolveUser();
        if (!$user) {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $id     = (int) Request::param('id', 0);
        if ($id <= 0) {
            return error('参数错误', 400);
        }

        $record = Db::name('distribution_withdrawals')
            ->where('id', $id)
            ->where('userId', $userId)
            ->find();
        if (!$record) {
            return error('提现记录不存在或无权限', 404);
        }

        $outBillNo = trim((string) ($record['out_bill_no'] ?? ''));
        if (!$outBillNo) {
            return error('该提现单暂无商户单号，无法查询', 400);
        }

        try {
            $service = new \app\common\service\WechatTransferService();
            $result  = $service->queryByOutBillNo($outBillNo);
        } catch (\Throwable $e) {
            Log::error('queryTransfer WechatTransferService error: ' . $e->getMessage());
            return error('查询转账状态失败：' . $e->getMessage(), 500);
        }

        if ($result['success'] !== true || empty($result['data'])) {
            $err = $result['error'] ?? [];
            return error('查询失败：' . ($err['message'] ?? '未知错误'), 500);
        }

        $data   = $result['data'];
        $state  = trim((string) ($data['state'] ?? ''));
        $now    = time();
        $billNo = $data['transfer_bill_no'] ?? null;

        if ($state === 'SUCCESS') {
            Db::name('distribution_withdrawals')->where('id', $id)->update([
                'status'            => 3,
                'wechat_pay_state'  => $state,
                'transfer_bill_no'  => $billNo,
                'transferAt'        => $now,
                'updatedAt'         => $now,
            ]);
            return success(['status' => 3, 'statusLabel' => '已收款'], '已收款');
        }

        if ($state === 'FAIL') {
            Db::startTrans();
            try {
                Db::name('wechat_users')
                    ->where('id', $record['userId'])
                    ->inc('walletBalance', (int) $record['amountFen'])
                    ->update(['updatedAt' => $now]);
                Db::name('distribution_withdrawals')->where('id', $id)->update([
                    'status'           => 1,
                    'auditNote'        => $data['fail_reason'] ?? '微信转账失败',
                    'wechat_pay_state' => $state,
                    'transfer_bill_no' => $billNo,
                    'updatedAt'        => $now,
                ]);
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                Log::error('queryTransfer FAIL rollback: ' . $e->getMessage());
                return error('更新失败', 500);
            }
            return success(['status' => 1, 'statusLabel' => '已驳回'], '转账失败，余额已退回');
        }

        return success(['status' => (int) $record['status'], 'state' => $state], '状态未变更');
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/qrcode
    //  生成当前用户专属小程序推广码，直接输出 PNG 二进制流
    //  可选参数：scope=personal|enterprise；eid=企业ID（仅 scope=enterprise 时有效）
    // ─────────────────────────────────────────────────────────────
    public function qrcode()
    {
        $user = $this->resolveUser();
        if (!$user) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $scope  = Request::param('scope', '');
        // 显式传 scope=personal 时强制个人版，不认 eid
        $enterpriseId = ($scope === 'personal') ? null : Request::param('eid', null);
        $enterpriseId = $enterpriseId !== null ? (int) $enterpriseId : null;

        // scene 参数：uid=用户ID（+eid=企业ID），最长 32 字符
        if ($enterpriseId) {
            $scene = "uid={$userId}&eid={$enterpriseId}";
            $page  = 'pages/enterprise/index';
        } else {
            $scene = "uid={$userId}";
            $page  = 'pages/index/index';
        }

        // 调用微信接口生成小程序码
        $result = WechatService::getWxacodeUnlimited($scene, $page, 280);

        if (isset($result['errcode'])) {
            http_response_code(500);
            exit(json_encode(['code' => 500, 'msg' => '生成小程序码失败：' . ($result['errmsg'] ?? '未知错误')]));
        }

        // 直接输出 PNG 二进制流，供 wx.downloadFile 使用
        header('Content-Type: image/png');
        header('Cache-Control: max-age=3600');
        echo $result['binary'];
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /api/distribution/poster
    //  生成完整海报（后端合成头像+二维码），直接输出 PNG
    // ─────────────────────────────────────────────────────────────
    public function poster()
    {
        $user = $this->resolveUser();
        if (!$user) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        $scope  = Request::param('scope', '');
        // scope=personal 时不读 eid 参数
        $eidParam = ($scope === 'personal') ? null : Request::param('eid', null);
        $enterpriseId = $eidParam !== null ? (int) $eidParam : null;

        if ($userId <= 0) {
            http_response_code(400);
            exit(json_encode(['code' => 400, 'msg' => '用户信息异常']));
        }

        $wechatUser = Db::name('wechat_users')
            ->where('id', $userId)
            ->field('id, nickname, avatar, enterpriseId')
            ->find();

        // 未显式传 eid 且非强制个人版时，从用户 DB 记录自动取企业 ID
        if ($enterpriseId === null && $scope !== 'personal' && !empty($wechatUser['enterpriseId'])) {
            $enterpriseId = (int) $wechatUser['enterpriseId'];
        }
        $userData = [
            'id'       => $userId,
            'nickname' => $wechatUser['nickname'] ?? '好友',
            'avatar'   => $wechatUser['avatar'] ?? '',
        ];

        $scene     = $enterpriseId ? "uid={$userId}&eid={$enterpriseId}" : "uid={$userId}";
        $page      = $enterpriseId ? 'pages/enterprise/index' : 'pages/index/index';
        $qrResult  = WechatService::getWxacodeUnlimited($scene, $page, 280);

        if (isset($qrResult['errcode'])) {
            http_response_code(500);
            exit(json_encode(['code' => 500, 'msg' => '生成小程序码失败：' . ($qrResult['errmsg'] ?? '')]));
        }

        $avatarBinary = null;
        if (!empty($userData['avatar'])) {
            $avatarBinary = PosterService::fetchImage($userData['avatar']);
        }

        try {
            $png = PosterService::buildFromConfig($userData, $qrResult['binary'], $avatarBinary, $enterpriseId);
        } catch (\Throwable $e) {
            http_response_code(500);
            exit(json_encode(['code' => 500, 'msg' => '海报合成失败：' . $e->getMessage()]));
        }

        header('Content-Type: image/png');
        header('Cache-Control: max-age=3600');
        echo $png;
        exit();
    }

    // ─────────────────────────────────────────────────────────────
    //  内部方法：订单付款成功后结算佣金（由 Payment/notify 调用）
    //  $orderId: orders.id (整数)
    // ─────────────────────────────────────────────────────────────
    public static function settleCommission(int $orderId): void
    {
        $order = Db::name('orders')
            ->where('id', $orderId)
            ->field('id, userId, enterpriseId, amount, status')
            ->find();

        if (!$order || $order['status'] !== 'paid') {
            return;
        }

        $inviteeId    = (int) $order['userId'];
        $orderAmount  = (int) $order['amount'];
        // 订单所属企业（资金应从该账户扣，不因绑定回退 personal 而丢失）
        $orderEnterpriseId = !empty($order['enterpriseId']) ? (int) $order['enterpriseId'] : null;
        $enterpriseId = $orderEnterpriseId;
        $scope        = $enterpriseId ? 'enterprise' : 'personal';
        $now          = time();

        // 【第一步】精确匹配：scope + enterpriseId 与订单完全一致
        $binding = Db::name('distribution_bindings')
            ->where('inviteeId', $inviteeId)
            ->where('scope', $scope)
            ->where(function ($q) use ($enterpriseId) {
                if ($enterpriseId) {
                    $q->where('enterpriseId', $enterpriseId);
                } else {
                    $q->whereNull('enterpriseId');
                }
            })
            ->where('status', 'active')
            ->where('expireAt', '>', time())
            ->find();

        // 【第二步】回退匹配：精确未命中时，查找任意有效的 personal 绑定
        // 跨 scope 时仍用 personal 佣金配置，但资金仍从订单 enterpriseId 扣（见 distribution-design 2.2）
        $fallbackScope        = $scope;
        $fallbackEnterpriseId = $enterpriseId;
        if (!$binding && $scope === 'enterprise') {
            $binding = self::findActivePersonalBinding($inviteeId, $enterpriseId);
            if ($binding) {
                $fallbackScope        = 'personal';
                $fallbackEnterpriseId = !empty($binding['enterpriseId']) ? (int) $binding['enterpriseId'] : null;
            }
        }

        if (!$binding) {
            return;
        }

        // 实际用于结算的 scope/绑定侧 enterprise（可能已回退为 personal 且为 null）
        $scope        = $fallbackScope;
        $enterpriseId = $fallbackEnterpriseId;
        // 扣款企业：始终为订单上的企业；佣金配置优先用订单企业读 testSettings
        $billingEnterpriseId = $orderEnterpriseId;
        $configEnterpriseId  = $orderEnterpriseId !== null ? $orderEnterpriseId : $enterpriseId;

        $inviterId = (int) $binding['inviterId'];

        // 从订单关联的 test_results 中取 testType，用于读取 per-test 佣金配置
        $testType = null;
        try {
            $tr = Db::name('test_results')->where('orderId', $orderId)->field('testType')->find();
            if ($tr) $testType = $tr['testType'] === 'ai' ? 'face' : ($tr['testType'] ?? null);
        } catch (\Throwable $e) {}

        // 读取佣金配置（优先 per-test testSettings，回退全局）
        list($rate, $amountFen) = self::getTestCommissionConfig($testType, $scope, $configEnterpriseId);
        $commissionFen = 0;
        if ($amountFen > 0) {
            $commissionFen = $amountFen;
            $rate = 0;
        } elseif ($rate > 0) {
            $commissionFen = (int) floor($orderAmount * $rate / 100);
        }
        if ($commissionFen <= 0) {
            return;
        }

        // 避免同一订单重复结算
        $exists = Db::name('commission_records')
            ->where('orderId', $orderId)
            ->find();
        if ($exists) {
            return;
        }

        Db::startTrans();
        try {
            $commissionStatus = 'pending';

            if ($billingEnterpriseId) {
                // 企业订单：从订单所属企业余额扣款，余额不足则冻结
                $enterprise = Db::name('enterprises')
                    ->where('id', $billingEnterpriseId)
                    ->field('id, balance')
                    ->lock(true)
                    ->find();

                $balanceFen = (int) ($enterprise['balance'] ?? 0);

                if ($enterprise && $balanceFen >= $commissionFen) {
                    // 余额充足，直接结算
                    $newBalanceFen = $balanceFen - $commissionFen;
                    Db::name('enterprises')
                        ->where('id', $billingEnterpriseId)
                        ->update([
                            'balance'   => $newBalanceFen,
                            'updatedAt' => $now,
                        ]);

                    Db::name('finance_records')->insert([
                        'enterpriseId'  => $billingEnterpriseId,
                        'type'          => 'consume',
                        'amount'        => $commissionFen,
                        'balanceBefore' => $balanceFen,
                        'balanceAfter'  => $newBalanceFen,
                        'description'   => self::buildCommissionFinanceDescription($inviteeId, $testType, 'order_paid'),
                        'orderId'       => $orderId,
                        'createdAt'     => $now,
                    ]);

                    // 推荐人钱包入账
                    Db::name('wechat_users')
                        ->where('id', $inviterId)
                        ->inc('walletBalance', $commissionFen)
                        ->inc('walletTotalEarned', $commissionFen)
                        ->update(['updatedAt' => $now]);

                    $commissionStatus = 'paid';
                } else {
                    // 余额不足，冻结
                    Db::name('wechat_users')
                        ->where('id', $inviterId)
                        ->inc('walletPending', $commissionFen)
                        ->update(['updatedAt' => $now]);

                    $commissionStatus = 'frozen';
                }
            } else {
                // 无企业上下文：平台直接发放（入账钱包）
                Db::name('wechat_users')
                    ->where('id', $inviterId)
                    ->inc('walletBalance', $commissionFen)
                    ->inc('walletTotalEarned', $commissionFen)
                    ->update(['updatedAt' => $now]);

                $commissionStatus = 'paid';
            }

            // 写佣金记录
            Db::name('commission_records')->insert([
                'agentId'       => $inviterId,
                'orderId'       => $orderId,
                'scope'         => $scope,
                'enterpriseId'  => $billingEnterpriseId,
                'inviterId'     => $inviterId,
                'inviteeId'     => $inviteeId,
                'bindingId'     => (int) $binding['id'],
                'commissionRate'=> $rate,
                'orderAmount'   => $orderAmount,
                'commissionFen' => $commissionFen,
                'commissionAmount' => number_format($commissionFen / 100, 2, '.', ''),
                'status'        => $commissionStatus,
                'frozenAt'      => $commissionStatus === 'frozen' ? $now : null,
                'paidAt'        => $commissionStatus === 'paid' ? $now : null,
                'createdAt'     => $now,
                'updatedAt'     => $now,
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  内部方法：企业充值后解冻冻结中的佣金
    //  $enterpriseId: 企业ID
    // ─────────────────────────────────────────────────────────────
    public static function unfreezeCommissions(int $enterpriseId): void
    {
        $now = time();

        // 找出该企业所有冻结中的佣金（按时间升序，先冻先解）
        $frozenList = Db::name('commission_records')
            ->where('enterpriseId', $enterpriseId)
            ->where('status', 'frozen')
            ->order('createdAt', 'asc')
            ->select()
            ->toArray();

        if (empty($frozenList)) {
            return;
        }

        $enterprise = Db::name('enterprises')
            ->where('id', $enterpriseId)
            ->field('id, balance')
            ->lock(true)
            ->find();

        if (!$enterprise) {
            return;
        }

        $balanceFen = (int) ($enterprise['balance'] ?? 0);

        Db::startTrans();
        try {
            foreach ($frozenList as $record) {
                $commissionFen = (int) $record['commissionFen'];
                if ($balanceFen < $commissionFen) {
                    break;
                }

                $balanceFen -= $commissionFen;
                $inviterId   = (int) $record['inviterId'];

                // 更新佣金记录状态
                Db::name('commission_records')
                    ->where('id', $record['id'])
                    ->update([
                        'status'     => 'paid',
                        'paidAt'     => $now,
                        'unfrozenAt' => $now,
                        'updatedAt'  => $now,
                    ]);

                $recordTestType = null;
                if (($record['commissionSource'] ?? '') === 'test_completion') {
                    $recordTestType = Db::name('test_results')
                        ->where('id', (int) ($record['testResultId'] ?? 0))
                        ->value('testType');
                } elseif (!empty($record['orderId'])) {
                    $recordTestType = self::getOrderTestType((int) $record['orderId']);
                }

                Db::name('finance_records')->insert([
                    'enterpriseId'  => $enterpriseId,
                    'type'          => 'consume',
                    'amount'        => $commissionFen,
                    'balanceBefore' => $balanceFen + $commissionFen,
                    'balanceAfter'  => $balanceFen,
                    'description'   => self::buildCommissionFinanceDescription((int) ($record['inviteeId'] ?? 0), $recordTestType, 'unfrozen'),
                    'orderId'       => !empty($record['orderId']) ? (int) $record['orderId'] : null,
                    'createdAt'     => $now,
                ]);

                // 推荐人钱包：pending 转 balance
                Db::name('wechat_users')
                    ->where('id', $inviterId)
                    ->inc('walletBalance', $commissionFen)
                    ->dec('walletPending', $commissionFen)
                    ->inc('walletTotalEarned', $commissionFen)
                    ->update(['updatedAt' => $now]);
            }

            // 更新企业余额
            Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->update([
                    'balance'   => $balanceFen,
                    'updatedAt' => $now,
                ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 读取提现上下限（分）
     * @return array{int,int} [minFen, maxFen] maxFen=0 表示不限制
     */
    private static function getWithdrawLimits(string $scope, ?int $enterpriseId): array
    {
        $cfg    = self::getDistributionConfig($scope, $enterpriseId);
        $rawMin = (int)($cfg['minWithdrawFen'] ?? self::MIN_WITHDRAW_FEN);
        $minFen = max(self::MIN_WITHDRAW_FEN, min(self::MAX_WITHDRAW_FEN, $rawMin));
        $maxFen = (int)($cfg['maxWithdrawFen'] ?? 0);
        if ($maxFen > 0) {
            $maxFen = min(self::MAX_WITHDRAW_FEN, $maxFen);
        }
        return [$minFen, $maxFen];
    }

    /**
     * 读取分销配置
     * 企业模式：enterprise_id={eid} 行，不存在则降级到 enterprise_id=0 全局行
     * 个人版：enterprise_id=0 行
     */
    private static function getDistributionConfig(string $scope, ?int $enterpriseId): array
    {
        if ($enterpriseId > 0) {
            $config = Db::name('system_config')
                ->where('key', 'distribution')
                ->where('enterprise_id', $enterpriseId)
                ->find();
            if ($config && $config['value']) {
                $cfg = is_string($config['value']) ? json_decode($config['value'], true) : $config['value'];
                if (is_array($cfg)) return $cfg;
            }
        }
        // 全局/个人版配置（enterprise_id=0）
        $config = Db::name('system_config')
            ->where('key', 'distribution')
            ->where('enterprise_id', 0)
            ->find();
        if ($config && $config['value']) {
            $cfg = is_string($config['value']) ? json_decode($config['value'], true) : $config['value'];
            if (is_array($cfg)) return $cfg;
        }
        return [];
    }

    /**
     * 读取佣金配置，返回 [rate, amountFen]；比例模式 rate>0 amountFen=0，金额模式 amountFen>0 rate=0
     * @deprecated 用 getTestCommissionConfig 替代
     */
    private static function getCommissionConfig(string $scope, ?int $enterpriseId): array
    {
        $cfg = self::getDistributionConfig($scope, $enterpriseId);
        $type = $cfg['commissionType'] ?? 'ratio';
        if ($type === 'amount') {
            $amountFen = (int)($cfg['commissionAmountFen'] ?? 0);
            return [0, $amountFen];
        }
        $rate = (int)($cfg['commissionRate'] ?? 90);
        return [$rate, 0];
    }

    /**
     * 读取指定测试类型的佣金配置 [rate, amountFen]
     * 优先使用 testSettings[testType]，若无则回退全局 commissionRate/commissionAmountFen
     */
    private static function getTestCommissionConfig(?string $testType, string $scope, ?int $enterpriseId): array
    {
        $ts = self::resolveTestSetting($testType, $enterpriseId);
        if ($ts) {
            $commType = $ts['commissionType'] ?? 'ratio';
            if ($commType === 'amount') {
                return [0, (int)($ts['commissionAmountFen'] ?? 0)];
            }
            return [(int)($ts['commissionRate'] ?? 90), 0];
        }
        return self::getCommissionConfig($scope, $enterpriseId);
    }

    /**
     * 解析某测试类型的 testSettings 配置（企业优先，回退全局），返回 null 表示未启用
     */
    private static function resolveTestSetting(?string $testType, ?int $enterpriseId): ?array
    {
        if (!$testType) return null;
        $tryEids = array_filter([$enterpriseId > 0 ? $enterpriseId : null, null], fn($v) => $v !== false);
        foreach ($tryEids as $eid) {
            $cfg = self::getDistributionConfig('personal', $eid);
            $ts  = $cfg['testSettings'][$testType] ?? null;
            if ($ts && !empty($ts['enabled'])) {
                return $ts;
            }
        }
        return null;
    }

    /**
     * 查找 personal 维度有效绑定：
     * 1. 优先 `enterpriseId = 当前企业`
     * 2. 其次 `enterpriseId IS NULL`
     */
    private static function findActivePersonalBinding(int $inviteeId, ?int $enterpriseId): ?array
    {
        if ($enterpriseId > 0) {
            $binding = Db::name('distribution_bindings')
                ->where('inviteeId', $inviteeId)
                ->where('scope', 'personal')
                ->where('enterpriseId', $enterpriseId)
                ->where('status', 'active')
                ->where('expireAt', '>', time())
                ->find();
            if ($binding) {
                return $binding;
            }
        }

        $binding = Db::name('distribution_bindings')
            ->where('inviteeId', $inviteeId)
            ->where('scope', 'personal')
            ->whereNull('enterpriseId')
            ->where('status', 'active')
            ->where('expireAt', '>', time())
            ->find();

        return $binding ?: null;
    }

    /**
     * 读取佣金比例配置（百分比整数，兼容旧逻辑）
     */
    private static function getCommissionRate(string $scope, ?int $enterpriseId): int
    {
        list($rate, $amountFen) = self::getCommissionConfig($scope, $enterpriseId);
        return $rate;
    }

    /**
     * 根据订单读取测试类型
     */
    private static function getOrderTestType(int $orderId): ?string
    {
        if ($orderId <= 0) {
            return null;
        }

        $testType = Db::name('test_results')
            ->where('orderId', $orderId)
            ->value('testType');

        if (!$testType) {
            return null;
        }

        return $testType === 'ai' ? 'face' : $testType;
    }

    /**
     * 获取用户展示名称
     */
    private static function getUserDisplayName(int $userId): string
    {
        if ($userId <= 0) {
            return '未知用户';
        }

        $nickname = Db::name('wechat_users')
            ->where('id', $userId)
            ->value('nickname');

        return $nickname ? (string) $nickname : ('用户' . $userId);
    }

    /**
     * 测试类型文案
     */
    private static function getTestTypeLabel(?string $testType): string
    {
        $normalized = $testType === 'ai' ? 'face' : (string) $testType;
        $map = [
            'face' => '人脸',
            'mbti' => 'MBTI',
            'sbti' => 'SBTI',
            'disc' => 'DISC',
            'pdp' => 'PDP',
        ];

        return $map[$normalized] ?? strtoupper($normalized ?: '未知测试');
    }

    /**
     * 企业财务流水中的佣金支出说明
     */
    private static function buildCommissionFinanceDescription(int $inviteeId, ?string $testType, string $scene): string
    {
        $userName = self::getUserDisplayName($inviteeId);
        $testLabel = self::getTestTypeLabel($testType);

        if ($scene === 'unfrozen') {
            return '佣金支出：用户' . $userName . '测试' . $testLabel . '完成分销（冻结后解冻）';
        }

        return '佣金支出：用户' . $userName . '测试' . $testLabel . '完成分销';
    }

    // ─────────────────────────────────────────────────────────────
    //  内部方法：测试完成后结算「测试完成佣金」（由 Test/submit 调用）
    //  仅适用于 personal scope；只要有有效绑定即可，无需付款。
    //  防重：同一 testResultId 只结算一次。
    // ─────────────────────────────────────────────────────────────
    public static function settleTestCommission(int $testResultId, int $inviteeId, string $testType): void
    {
        // 仅支持指定测试类型
        $allowedTypes = ['face', 'mbti', 'sbti', 'disc', 'pdp'];
        // face/ai 统一归类为 face
        $normalizedType = ($testType === 'ai') ? 'face' : $testType;
        if (!in_array($normalizedType, $allowedTypes, true)) {
            return;
        }

        // 读取 testSettings 配置：优先读用户所属企业配置，未配置则回退全局
        $userEid  = (int)(Db::name('wechat_users')->where('id', $inviteeId)->value('enterpriseId') ?? 0);
        $tsConfig = self::resolveTestSetting($normalizedType, $userEid);
        if (!$tsConfig || empty($tsConfig['enabled']) || empty($tsConfig['noPayment'])) {
            return;
        }
        list($rate, $amountFen) = self::getTestCommissionConfig($normalizedType, 'personal', $userEid > 0 ? $userEid : null);
        $commissionFen = 0;
        if ($amountFen > 0) {
            $commissionFen = $amountFen;
        } elseif ($rate > 0) {
            // noPayment 场景无订单金额，若为比例则跳过（无金额可算）
            return;
        }
        if ($commissionFen <= 0) {
            return;
        }

        // 查找该用户 personal scope 的有效绑定：优先企业 personal 绑定，再回退全局 personal 绑定
        $binding = self::findActivePersonalBinding($inviteeId, $userEid > 0 ? $userEid : null);
        if (!$binding) {
            return;
        }

        $inviterId = (int) $binding['inviterId'];
        $recordEnterpriseId = $userEid > 0
            ? $userEid
            : (((int)($binding['enterpriseId'] ?? 0)) > 0 ? (int)$binding['enterpriseId'] : null);

        // 防重：同一 testResultId 只允许一条 test_completion 佣金
        $exists = Db::name('commission_records')
            ->where('testResultId', $testResultId)
            ->where('commissionSource', 'test_completion')
            ->find();
        if ($exists) {
            return;
        }

        $now = time();
        Db::startTrans();
        try {
            $commissionStatus = 'paid';

            if ($recordEnterpriseId) {
                // 企业上下文：先扣企业余额；不足则冻结到后续补余额再解冻
                $enterprise = Db::name('enterprises')
                    ->where('id', $recordEnterpriseId)
                    ->field('id, balance')
                    ->lock(true)
                    ->find();

                $balanceFen = (int) ($enterprise['balance'] ?? 0);
                if ($enterprise && $balanceFen >= $commissionFen) {
                    $newBalanceFen = $balanceFen - $commissionFen;
                    Db::name('enterprises')
                        ->where('id', $recordEnterpriseId)
                        ->update([
                            'balance'   => $newBalanceFen,
                            'updatedAt' => $now,
                        ]);

                    Db::name('finance_records')->insert([
                        'enterpriseId'  => $recordEnterpriseId,
                        'type'          => 'consume',
                        'amount'        => $commissionFen,
                        'balanceBefore' => $balanceFen,
                        'balanceAfter'  => $newBalanceFen,
                        'description'   => self::buildCommissionFinanceDescription($inviteeId, $normalizedType, 'test_completion'),
                        'orderId'       => null,
                        'createdAt'     => $now,
                    ]);

                    Db::name('wechat_users')
                        ->where('id', $inviterId)
                        ->inc('walletBalance', $commissionFen)
                        ->inc('walletTotalEarned', $commissionFen)
                        ->update(['updatedAt' => $now]);
                } else {
                    Db::name('wechat_users')
                        ->where('id', $inviterId)
                        ->inc('walletPending', $commissionFen)
                        ->update(['updatedAt' => $now]);

                    $commissionStatus = 'frozen';
                }
            } else {
                // 无企业上下文时仍由平台直接发放
                Db::name('wechat_users')
                    ->where('id', $inviterId)
                    ->inc('walletBalance', $commissionFen)
                    ->inc('walletTotalEarned', $commissionFen)
                    ->update(['updatedAt' => $now]);
            }

            // 写佣金记录
            Db::name('commission_records')->insert([
                'agentId'          => $inviterId,
                'orderId'          => null,
                'testResultId'     => $testResultId,
                'commissionSource' => 'test_completion',
                'scope'            => 'personal',
                'enterpriseId'     => $recordEnterpriseId,
                'inviterId'        => $inviterId,
                'inviteeId'        => $inviteeId,
                'bindingId'        => (int) $binding['id'],
                'commissionRate'   => $rate,
                'orderAmount'      => 0,
                'commissionFen'    => $commissionFen,
                'commissionAmount' => number_format($commissionFen / 100, 2, '.', ''),
                'status'           => $commissionStatus,
                'frozenAt'         => $commissionStatus === 'frozen' ? $now : null,
                'paidAt'           => $commissionStatus === 'paid' ? $now : null,
                'createdAt'        => $now,
                'updatedAt'        => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('settleTestCommission failed', [
                'testResultId' => $testResultId,
                'inviteeId' => $inviteeId,
                'testType' => $testType,
                'normalizedType' => $normalizedType,
                'userEnterpriseId' => $userEid,
                'bindingId' => (int)($binding['id'] ?? 0),
                'inviterId' => $inviterId,
                'commissionFen' => $commissionFen,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
