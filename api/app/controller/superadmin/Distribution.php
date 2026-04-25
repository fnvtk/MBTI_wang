<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 分销管理控制器（超管端 - 个人版分销）
 * 路由前缀：/api/v1/superadmin/distribution
 */
class Distribution extends BaseController
{
    // ─────────────────────────────────────────────────────────────
    //  GET distribution/overview  全平台分销数据概览
    // ─────────────────────────────────────────────────────────────
    public function overview()
    {
        try {
            $now = time();

            $totalCommission  = Db::name('commission_records')->whereIn('status', ['paid', 'frozen'])->sum('commissionFen') ?: 0;
            $paidCommission   = Db::name('commission_records')->where('status', 'paid')->sum('commissionFen') ?: 0;
            $frozenCommission = Db::name('commission_records')->where('status', 'frozen')->sum('commissionFen') ?: 0;
            $totalOrders      = Db::name('commission_records')->whereIn('status', ['paid', 'frozen'])->count();

            $personalCommission  = Db::name('commission_records')->where('scope', 'personal')
                ->whereIn('status', ['paid', 'frozen'])->sum('commissionFen') ?: 0;
            $enterpriseCommission = Db::name('commission_records')->where('scope', 'enterprise')
                ->whereIn('status', ['paid', 'frozen'])->sum('commissionFen') ?: 0;

            $bindingCount = Db::name('distribution_bindings')
                ->where('status', 'active')
                ->where('expireAt', '>', $now)
                ->count();

            // 待处理提现：status=0 审核中
            $pendingWithdraw = Db::name('distribution_withdrawals')
                ->where('status', 0)
                ->sum('amountFen') ?: 0;

            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $todayCommission = Db::name('commission_records')
                ->where('status', 'paid')
                ->where('paidAt', '>=', $todayStart)
                ->sum('commissionFen') ?: 0;

            return success([
                'totalCommission'     => number_format($totalCommission / 100, 2, '.', ''),
                'paidCommission'      => number_format($paidCommission / 100, 2, '.', ''),
                'frozenCommission'    => number_format($frozenCommission / 100, 2, '.', ''),
                'personalCommission'  => number_format($personalCommission / 100, 2, '.', ''),
                'enterpriseCommission'=> number_format($enterpriseCommission / 100, 2, '.', ''),
                'totalOrders'         => $totalOrders,
                'bindingCount'        => $bindingCount,
                'pendingWithdraw'     => number_format($pendingWithdraw / 100, 2, '.', ''),
                'todayCommission'     => number_format($todayCommission / 100, 2, '.', ''),
            ]);
        } catch (\Exception $e) {
            return error('获取数据失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/bindings  全平台绑定记录
    // ─────────────────────────────────────────────────────────────
    public function bindings()
    {
        $page         = max(1, (int) Request::param('page', 1));
        $pageSize     = min(100, (int) Request::param('pageSize', 20));
        $scope        = Request::param('scope', '');
        $status       = Request::param('status', '');
        $enterpriseId = (int) Request::param('enterpriseId', 0);

        try {
            $query = Db::name('distribution_bindings')
                ->alias('b')
                ->leftJoin('wechat_users inv', 'b.inviterId = inv.id')
                ->leftJoin('wechat_users invt', 'b.inviteeId = invt.id')
                ->leftJoin('enterprises e', 'b.enterpriseId = e.id')
                ->field('b.*, inv.nickname as inviterName, invt.nickname as inviteeName, e.name as enterpriseName');

            if ($scope) $query->where('b.scope', $scope);
            if ($status) $query->where('b.status', $status);
            if ($enterpriseId) $query->where('b.enterpriseId', $enterpriseId);

            $total = (clone $query)->count();
            $list  = $query->order('b.updatedAt', 'desc')->page($page, $pageSize)->select()->toArray();

            $now = time();
            foreach ($list as &$row) {
                $row['remainDays']  = max(0, (int) ceil(($row['expireAt'] - $now) / 86400));
                $row['inviterName'] = $row['inviterName'] ?: '未知';
                $row['inviteeName'] = $row['inviteeName'] ?: '未知';
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取绑定记录失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/commissions  全平台佣金记录
    // ─────────────────────────────────────────────────────────────
    public function commissions()
    {
        $page      = max(1, (int) Request::param('page', 1));
        $pageSize  = min(100, (int) Request::param('pageSize', 20));
        $scope     = Request::param('scope', '');
        $status    = Request::param('status', '');

        try {
            $query = Db::name('commission_records')
                ->alias('c')
                ->leftJoin('wechat_users inv', 'c.inviterId = inv.id')
                ->leftJoin('wechat_users invt', 'c.inviteeId = invt.id')
                ->leftJoin('enterprises e', 'c.enterpriseId = e.id')
                ->field('c.*, inv.nickname as inviterName, invt.nickname as inviteeName, e.name as enterpriseName');

            if ($scope)  $query->where('c.scope', $scope);
            if ($status) $query->where('c.status', $status);

            $total = (clone $query)->count();
            $list  = $query->order('c.createdAt', 'desc')->page($page, $pageSize)->select()->toArray();

            foreach ($list as &$row) {
                $row['commissionYuan'] = number_format($row['commissionFen'] / 100, 2, '.', '');
                $row['orderYuan']      = number_format($row['orderAmount'] / 100, 2, '.', '');
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取佣金记录失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/withdrawals  全平台提现申请
    // ─────────────────────────────────────────────────────────────
    public function withdrawals()
    {
        $page     = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, (int) Request::param('pageSize', 20));
        $status   = Request::param('status', '');

        try {
            $query = Db::name('distribution_withdrawals')
                ->alias('w')
                ->leftJoin('wechat_users u', 'w.userId = u.id')
                ->field('w.*, u.nickname, u.avatar');

            if ($status !== '') {
                // 支持字符串或数字，统一转 int
                $query->where('w.status', (int)$status);
            }

            $total = (clone $query)->count();
            $list  = $query->order('w.createdAt', 'desc')->page($page, $pageSize)->select()->toArray();

            foreach ($list as &$row) {
                $row['amountYuan'] = number_format($row['amountFen'] / 100, 2, '.', '');
                $row['nickname']   = $row['nickname'] ?: '未知用户';

                // 确保前端拿到的是数字 status（避免 '0' 和 0 比较异常）
                $code = (int) ($row['status'] ?? 0);
                $row['status'] = $code;

                // 统一后台状态文案：0审核中、1已驳回、2待收款、3已收款、4已过期
                switch ($code) {
                    case 0:
                        $row['statusLabel'] = '审核中';
                        break;
                    case 1:
                        $row['statusLabel'] = '已驳回';
                        break;
                    case 2:
                        $row['statusLabel'] = '待收款';
                        break;
                    case 3:
                        $row['statusLabel'] = '已收款';
                        break;
                    case 4:
                        $row['statusLabel'] = '已过期';
                        break;
                    default:
                        $row['statusLabel'] = '未知';
                        break;
                }
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取提现记录失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  POST distribution/withdrawals/:id/approve  审核通过提现
    // ─────────────────────────────────────────────────────────────
    public function approveWithdrawal(int $id)
    {
        $note = Request::param('note', '');
        $now  = time();

        $record = Db::name('distribution_withdrawals')
            ->alias('w')
            ->leftJoin('wechat_users u', 'w.userId = u.id')
            ->field('w.*, u.openid')
            ->where('w.id', $id)
            ->find();
        // 仅允许处理审核中（status=0）的记录
        if (!$record || (int)$record['status'] !== 0) {
            return error('提现申请不存在或已处理', 400);
        }

        try {
            // 生成商户单号：TX + 时间戳 + 随机数 + 提现ID（示例：TX202603121526520005123）
            $outBillNo = 'TX' . date('YmdHis') . mt_rand(1000, 9999) . $record['id'];

            // 调用微信商家转账到零钱接口（参数对齐 ckb-admin Withdrawal::handleWechatPay）
            $service = new \app\common\service\WechatTransferService();
            $result  = $service->createTransfer([
                'out_bill_no'     => $outBillNo,
                'openid'          => $record['openid'],
                'transfer_amount' => (int) $record['amountFen'], // 单位：分
                'transfer_remark' => '推广佣金提现',
                'transfer_scene_id' => env('TRANSFER_SCENE_ID', '1005'),
                'transfer_scene_report_infos' => [
                    [
                        'info_type'    => '岗位类型',
                        'info_content' => '推广人员',
                    ],
                    [
                        'info_type'    => '报酬说明',
                        'info_content' => '推广佣金提现',
                    ],
                ],
                'notify_url'      => env('WITHDRAW_NOTIFY_URL', ''), // 可选：提现专用回调
            ]);

            if ($result['success'] !== true) {
                $err  = $result['error'] ?? [];
                $code = $err['code']    ?? 'UNKNOWN';
                $msg  = $err['message'] ?? '微信转账接口调用失败';
                return error("微信转账发起失败（{$code}）：{$msg}", 500);
            }

            $wechatData = $result['data'] ?? [];
            Db::name('distribution_withdrawals')->where('id', $id)->update([
                // 2=待收款（已发起转账，等待用户确认）
                'status'           => 2,
                'auditNote'        => $note,
                'auditAt'          => $now,
                'updatedAt'        => $now,
                'pay_type'         => 'wechat',
                'out_bill_no'      => $outBillNo,
                'transfer_bill_no' => $wechatData['transfer_bill_no'] ?? null,
                'wechat_pay_state' => $wechatData['state'] ?? 'PROCESSING',
                'transfer_scene_id'=> $wechatData['transfer_scene_id'] ?? env('TRANSFER_SCENE_ID', '1005'),
                'package_info'     => $wechatData['package_info'] ?? '',
                'mch_id'           => env('MCH_ID', null),
            ]);
            return success(null, '审核通过，已发起微信转账');
        } catch (\Exception $e) {
            return error('操作失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  POST distribution/withdrawals/:id/reject  拒绝提现
    // ─────────────────────────────────────────────────────────────
    public function rejectWithdrawal(int $id)
    {
        $note = Request::param('note', '');
        $now  = time();

        $record = Db::name('distribution_withdrawals')->where('id', $id)->find();
        // 仅允许处理审核中（status=0）的记录
        if (!$record || (int)$record['status'] !== 0) {
            return error('提现申请不存在或已处理', 400);
        }

        Db::startTrans();
        try {
            Db::name('wechat_users')
                ->where('id', $record['userId'])
                ->inc('walletBalance', $record['amountFen'])
                ->update(['updatedAt' => $now]);

            Db::name('distribution_withdrawals')->where('id', $id)->update([
                // 1=已驳回
                'status'    => 1,
                'auditNote' => $note,
                'auditAt'   => $now,
                'updatedAt' => $now,
            ]);

            Db::commit();
            return success(null, '已拒绝，余额已退回');
        } catch (\Exception $e) {
            Db::rollback();
            return error('操作失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/settings  个人版分销全局配置
    // ─────────────────────────────────────────────────────────────
    public function settings()
    {
        try {
            $config = Db::name('system_config')->where('key', 'distribution')->where('enterprise_id', 0)->find();
            $default = [
                'enabled'          => true,
                'promoCenterTitle' => '推广中心',
                'bindingDays'      => 30,
                'minWithdrawFen'   => 1,
                'maxWithdrawFen'   => 0,
                'requireAudit'     => true,
                'withdrawFee'      => 0,
                'testSettings'     => self::defaultTestSettings(),
            ];
            if ($config && $config['value']) {
                $settings = is_string($config['value']) ? json_decode($config['value'], true) : $config['value'];
                $settings = array_merge($default, $settings ?? []);
            } else {
                $settings = $default;
            }
            $settings['minWithdraw'] = round((float)($settings['minWithdrawFen'] ?? 1) / 100, 2);
            $settings['maxWithdraw'] = ($max = (int)($settings['maxWithdrawFen'] ?? 0)) > 0 ? round($max / 100, 2) : 0;
            $settings['testSettings'] = self::appendTestSettingsAmount(
                array_merge(self::defaultTestSettings(), $settings['testSettings'] ?? [])
            );
            return success($settings);
        } catch (\Exception $e) {
            return error('获取配置失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  PUT distribution/settings  更新个人版分销全局配置
    // ─────────────────────────────────────────────────────────────
    public function updateSettings()
    {
        $settings = Request::only([
            'enabled', 'promoCenterTitle', 'bindingDays',
            'minWithdrawFen', 'minWithdraw', 'maxWithdrawFen', 'maxWithdraw',
            'requireAudit', 'withdrawFee', 'testSettings'
        ]);

        $minWithdrawFen = isset($settings['minWithdraw'])
            ? (int) round((float)$settings['minWithdraw'] * 100)
            : (int)($settings['minWithdrawFen'] ?? 1);
        $maxWithdrawFen = isset($settings['maxWithdraw'])
            ? (int) round((float)$settings['maxWithdraw'] * 100)
            : (int)($settings['maxWithdrawFen'] ?? 0);
        $minWithdrawFen = max(1, min(20000, $minWithdrawFen));
        $maxWithdrawFen = $maxWithdrawFen > 0 ? min(20000, max(100, $maxWithdrawFen)) : 0;

        $promoTitle = trim((string)($settings['promoCenterTitle'] ?? ''));
        $toSave = [
            'enabled'          => (bool)($settings['enabled'] ?? true),
            'promoCenterTitle' => $promoTitle !== '' ? $promoTitle : '推广中心',
            'bindingDays'      => (int)($settings['bindingDays'] ?? 30),
            'minWithdrawFen'   => $minWithdrawFen,
            'maxWithdrawFen'   => $maxWithdrawFen,
            'requireAudit'     => isset($settings['requireAudit']) ? (bool)$settings['requireAudit'] : true,
            'withdrawFee'      => max(0, min(100, (float)($settings['withdrawFee'] ?? 0))),
            'testSettings'     => self::sanitizeTestSettings($settings['testSettings'] ?? null),
        ];

        try {
            $now      = time();
            $existing = Db::name('system_config')->where('key', 'distribution')->where('enterprise_id', 0)->find();
            if ($existing) {
                Db::name('system_config')
                    ->where('key', 'distribution')
                    ->where('enterprise_id', 0)
                    ->update(['value' => json_encode($toSave, JSON_UNESCAPED_UNICODE), 'updatedAt' => $now]);
            } else {
                Db::name('system_config')->insert([
                    'key'           => 'distribution',
                    'enterprise_id' => 0,
                    'value'         => json_encode($toSave, JSON_UNESCAPED_UNICODE),
                    'createdAt'     => $now,
                    'updatedAt'     => $now,
                ]);
            }
            $toSave['minWithdraw'] = $toSave['minWithdrawFen'] / 100;
            $toSave['maxWithdraw'] = $toSave['maxWithdrawFen'] > 0 ? $toSave['maxWithdrawFen'] / 100 : 0;
            $toSave['testSettings'] = self::appendTestSettingsAmount($toSave['testSettings']);
            return success($toSave, '配置已保存');
        } catch (\Exception $e) {
            return error('保存配置失败：' . $e->getMessage(), 500);
        }
    }

    private static function defaultTestSettings(): array
    {
        $item = ['enabled' => true, 'commissionType' => 'ratio', 'commissionRate' => 90, 'commissionAmountFen' => 0, 'noPayment' => false];
        return ['face' => $item, 'mbti' => $item, 'sbti' => $item, 'disc' => $item, 'pdp' => $item, 'gaokao' => $item];
    }

    private static function sanitizeTestSettings($raw): array
    {
        $default = self::defaultTestSettings();
        if (!is_array($raw)) return $default;
        $result = [];
        foreach ($default as $type => $def) {
            $s = $raw[$type] ?? [];
            $commissionType = in_array($s['commissionType'] ?? '', ['ratio', 'amount']) ? $s['commissionType'] : 'ratio';
            $amountFen = isset($s['commissionAmount'])
                ? (int) round((float)$s['commissionAmount'] * 100)
                : (int)($s['commissionAmountFen'] ?? 0);
            $rate = max(0, min(100, (int)($s['commissionRate'] ?? 90)));
            $result[$type] = [
                'enabled'            => ($s['enabled'] ?? true) !== false,
                'commissionType'     => $commissionType,
                'commissionRate'     => $commissionType === 'ratio' ? $rate : 0,
                'commissionAmountFen'=> $commissionType === 'amount' ? max(0, $amountFen) : 0,
                'noPayment'          => !empty($s['noPayment']),
            ];
        }
        return $result;
    }

    private static function appendTestSettingsAmount(array $ts): array
    {
        foreach ($ts as $k => $v) {
            $ts[$k]['commissionAmount'] = round(($v['commissionAmountFen'] ?? 0) / 100, 2);
        }
        return $ts;
    }
}
