<?php
namespace app\controller\admin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 分销管理控制器（企业管理端）
 * 路由前缀：/api/v1/admin/distribution
 */
class Distribution extends BaseController
{
    /**
     * 企业管理员 enterpriseId：JWT 可能为空，需与 users 表对齐（与订单等接口一致）
     */
    private function resolveEnterpriseIdForAdmin(?array $user): int
    {
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'enterprise_admin'], true)) {
            return 0;
        }
        $eid = $user['enterpriseId'] ?? null;
        if (is_array($eid)) {
            $eid = null;
        }
        $enterpriseId = $eid !== null && $eid !== '' ? (int) $eid : 0;
        if ($enterpriseId <= 0) {
            $adminRow = Db::name('users')->where('id', (int) ($user['userId'] ?? 0))->find();
            $e2 = $adminRow['enterpriseId'] ?? null;
            $enterpriseId = ($e2 !== null && $e2 !== '') ? (int) $e2 : 0;
        }

        return $enterpriseId > 0 ? $enterpriseId : 0;
    }

    /**
     * 本企业维度的「推荐人 id」集合：佣金表 + 企业绑定表 + 历史仅填 agentId 的佣金行
     *
     * @return int[]
     */
    private function distributorUserIdsForEnterprise(int $enterpriseId): array
    {
        if ($enterpriseId <= 0) {
            return [];
        }

        $a = Db::name('commission_records')
            ->where('enterpriseId', $enterpriseId)
            ->where('inviterId', '>', 0)
            ->distinct(true)
            ->column('inviterId');
        $b = Db::name('distribution_bindings')
            ->where('enterpriseId', $enterpriseId)
            ->where('inviterId', '>', 0)
            ->distinct(true)
            ->column('inviterId');
        $c = Db::name('commission_records')
            ->where('enterpriseId', $enterpriseId)
            ->where(function ($q) {
                $q->whereNull('inviterId')->whereOr('inviterId', 0);
            })
            ->where('agentId', '>', 0)
            ->distinct(true)
            ->column('agentId');

        $merged = array_merge($a ?: [], $b ?: [], $c ?: []);

        return array_values(array_unique(array_filter(array_map('intval', $merged), static function ($v) {
            return $v > 0;
        })));
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/overview
    // ─────────────────────────────────────────────────────────────
    public function overview()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $days = 7;
        $trendStart = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')));

        try {
            $query = Db::name('commission_records')
                ->where('enterpriseId', $enterpriseId);

            $totalCommission  = (clone $query)->whereIn('status', ['paid', 'frozen'])->sum('commissionFen') ?: 0;
            $paidCommission   = (clone $query)->where('status', 'paid')->sum('commissionFen') ?: 0;
            $frozenCommission = (clone $query)->where('status', 'frozen')->sum('commissionFen') ?: 0;
            $totalOrders      = (clone $query)->whereIn('status', ['paid', 'frozen'])->count();
            $todayCommission  = (clone $query)
                ->whereIn('status', ['paid', 'frozen'])
                ->where('createdAt', '>=', $todayStart)
                ->sum('commissionFen') ?: 0;
            $pendingCount = (clone $query)->where('status', 'frozen')->count();

            $bindingQuery = Db::name('distribution_bindings')
                ->where('enterpriseId', $enterpriseId)
                ->where('status', 'active')
                ->where('expireAt', '>', time());
            $bindingCount = (clone $bindingQuery)->count();
            $totalAgents = (clone $bindingQuery)->distinct(true)->count('inviterId');
            $todayAgents = Db::name('distribution_bindings')
                ->where('enterpriseId', $enterpriseId)
                ->where('createdAt', '>=', $todayStart)
                ->distinct(true)
                ->count('inviterId');

            $trendRows = Db::name('commission_records')
                ->where('enterpriseId', $enterpriseId)
                ->whereIn('status', ['paid', 'frozen'])
                ->where('createdAt', '>=', $trendStart)
                ->field("FROM_UNIXTIME(createdAt, '%Y-%m-%d') as d, SUM(commissionFen) as totalFen")
                ->group('d')
                ->order('d', 'asc')
                ->select()
                ->toArray();
            $trendMap = [];
            foreach ($trendRows as $row) {
                $trendMap[$row['d']] = (int) ($row['totalFen'] ?? 0);
            }
            $commissionTrend = [];
            for ($i = 0; $i < $days; $i++) {
                $date = date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days'));
                $commissionTrend[] = [
                    'date' => $date,
                    'amount' => round(($trendMap[$date] ?? 0) / 100, 2),
                ];
            }

            $productSeries = self::buildProductCommissionSeries($enterpriseId);

            return success([
                'totalAgents'        => (int) $totalAgents,
                'todayAgents'        => (int) $todayAgents,
                'totalCommission'    => number_format($totalCommission / 100, 2, '.', ''),
                'todayCommission'    => number_format($todayCommission / 100, 2, '.', ''),
                'pendingCommission'  => number_format($frozenCommission / 100, 2, '.', ''),
                'pendingCount'       => (int) $pendingCount,
                'paidCommission'     => number_format($paidCommission / 100, 2, '.', ''),
                'frozenCommission'   => number_format($frozenCommission / 100, 2, '.', ''),
                'totalOrders'        => (int) $totalOrders,
                'bindingCount'       => (int) $bindingCount,
                'commissionTrend'    => $commissionTrend,
                'productCommissionSeries' => $productSeries,
            ]);
        } catch (\Exception $e) {
            return error('获取数据失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/distributors  分销商列表（有过邀请行为的用户）
    // ─────────────────────────────────────────────────────────────
    public function distributors()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $search       = trim((string) Request::param('search', ''));
        $page         = max(1, (int) Request::param('page', 1));
        $pageSize     = min(100, (int) Request::param('pageSize', 20));

        try {
            $poolIds = $this->distributorUserIdsForEnterprise($enterpriseId);
            if (empty($poolIds)) {
                return success(['list' => [], 'total' => 0, 'page' => $page, 'pageSize' => $pageSize]);
            }

            $inviterQuery = Db::name('wechat_users')
                ->alias('u')
                ->whereIn('u.id', $poolIds)
                ->field('u.id, u.nickname, u.avatar, u.createdAt');

            if ($search !== '') {
                $inviterQuery->where(function ($q) use ($search) {
                    $q->where('u.nickname', 'like', "%{$search}%")
                      ->whereOr('u.id', '=', is_numeric($search) ? (int) $search : -1);
                });
            }

            $inviterQuery->order('u.id', 'desc');

            $total    = (int) (clone $inviterQuery)->count();
            $inviters = $inviterQuery->page($page, $pageSize)->select()->toArray();

            $inviterIds = array_values(array_filter(array_map('intval', array_column($inviters, 'id'))));

            $commStats    = [];
            $withdrawnMap = [];
            $teamMap      = [];

            if (!empty($inviterIds)) {
                $inStr = implode(',', $inviterIds);
                $rows  = Db::name('commission_records')
                    ->where('enterpriseId', $enterpriseId)
                    ->whereRaw('COALESCE(inviterId, agentId) IN (' . $inStr . ')')
                    ->fieldRaw("COALESCE(inviterId, agentId) AS inviterKey, SUM(IF(status IN ('paid','frozen'), commissionFen, 0)) AS totalFen, SUM(IF(status = 'paid', commissionFen, 0)) AS paidFen")
                    ->group('inviterKey')
                    ->select()
                    ->toArray();
                foreach ($rows as $r) {
                    $kid = (int) ($r['inviterKey'] ?? 0);
                    if ($kid > 0) {
                        $commStats[$kid] = $r;
                    }
                }

                $withdrawnRows = Db::name('distribution_withdrawals')
                    ->alias('w')
                    ->join('wechat_users u', 'w.userId = u.id')
                    ->whereIn('w.userId', $inviterIds)
                    ->where('u.enterpriseId', $enterpriseId)
                    ->whereIn('w.status', [0, 2, 3])
                    ->field('w.userId, SUM(w.amountFen) AS withdrawnFen')
                    ->group('w.userId')
                    ->select()
                    ->toArray();
                foreach ($withdrawnRows as $r) {
                    $withdrawnMap[(int) ($r['userId'] ?? 0)] = (int) ($r['withdrawnFen'] ?? 0);
                }

                $now = time();
                $teamRows = Db::name('distribution_bindings')
                    ->alias('b')
                    ->leftJoin('wechat_users w', 'w.id = b.inviteeId')
                    ->whereIn('b.inviterId', $inviterIds)
                    ->where(function ($q) use ($enterpriseId) {
                        $q->where('b.enterpriseId', $enterpriseId)
                            ->whereOr(function ($q2) use ($enterpriseId) {
                                $q2->whereNull('b.enterpriseId')->where('w.enterpriseId', $enterpriseId);
                            });
                    })
                    ->where('b.status', 'active')
                    ->where('b.expireAt', '>', $now)
                    ->fieldRaw('b.inviterId, COUNT(DISTINCT b.inviteeId) AS teamCount')
                    ->group('b.inviterId')
                    ->select()
                    ->toArray();
                foreach ($teamRows as $r) {
                    $teamMap[(int) ($r['inviterId'] ?? 0)] = (int) ($r['teamCount'] ?? 0);
                }
            }

            $list = [];
            foreach ($inviters as $inv) {
                $uid       = (int) ($inv['id'] ?? 0);
                $totalFen  = (int) ($commStats[$uid]['totalFen'] ?? 0);
                $paidFen   = (int) ($commStats[$uid]['paidFen'] ?? 0);
                $withdrawn = (int) ($withdrawnMap[$uid] ?? 0);
                $avail     = max(0, $paidFen - $withdrawn);
                $list[] = [
                    'id'                 => $uid,
                    'agentName'          => $inv['nickname'] ?: ('用户' . $uid),
                    'avatar'             => $inv['avatar'] ?? '',
                    'totalCommission'    => number_format($totalFen / 100, 2, '.', ''),
                    'availableCommission'=> number_format($avail / 100, 2, '.', ''),
                    'teamCount'          => (int) ($teamMap[$uid] ?? 0),
                    'teamPerformance'    => '-',
                    'inviteCode'         => '-',
                    'level'              => '-',
                    'createdAt'          => $inv['createdAt'],
                ];
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取分销商列表失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/bindings  绑定记录列表
    // ─────────────────────────────────────────────────────────────
    public function bindings()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $page         = max(1, (int) Request::param('page', 1));
        $pageSize     = min(100, (int) Request::param('pageSize', 20));
        $status       = Request::param('status', '');
        $inviterId    = (int) Request::param('inviterId', 0);

        try {
            $query = Db::name('distribution_bindings')
                ->alias('b')
                ->leftJoin('wechat_users inv', 'b.inviterId = inv.id')
                ->leftJoin('wechat_users invt', 'b.inviteeId = invt.id')
                ->field('b.*, inv.nickname as inviterName, inv.avatar as inviterAvatar,
                         invt.nickname as inviteeName, invt.avatar as inviteeAvatar')
                ->where(function ($q) use ($enterpriseId) {
                    $q->where('b.enterpriseId', $enterpriseId)
                        ->whereOr(function ($q2) use ($enterpriseId) {
                            $q2->whereNull('b.enterpriseId')
                                ->where(function ($q3) use ($enterpriseId) {
                                    $q3->where('inv.enterpriseId', $enterpriseId)
                                        ->whereOr('invt.enterpriseId', $enterpriseId);
                                });
                        });
                });

            if ($inviterId > 0) {
                $query->where('b.inviterId', $inviterId);
            }
            if ($status) {
                $query->where('b.status', $status);
            }

            $total = (clone $query)->count();
            $list  = $query->order('b.updatedAt', 'desc')
                           ->page($page, $pageSize)
                           ->select()
                           ->toArray();

            $now = time();
            foreach ($list as &$row) {
                $row['remainDays']    = max(0, (int) ceil(($row['expireAt'] - $now) / 86400));
                $row['inviterName']   = $row['inviterName'] ?: '未知';
                $row['inviteeName']   = $row['inviteeName'] ?: '未知';
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取绑定记录失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/commissions  佣金记录列表
    // ─────────────────────────────────────────────────────────────
    public function commissions()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $page         = max(1, (int) Request::param('page', 1));
        $pageSize     = min(100, (int) Request::param('pageSize', 20));
        $status       = Request::param('status', '');
        $inviterId    = (int) Request::param('inviterId', 0);

        try {
            $query = Db::name('commission_records')
                ->alias('c')
                ->leftJoin('wechat_users inv', 'c.inviterId = inv.id')
                ->leftJoin('wechat_users invt', 'c.inviteeId = invt.id')
                ->field('c.*, inv.nickname as inviterName, inv.avatar as inviterAvatar, invt.nickname as inviteeName, invt.avatar as inviteeAvatar')
                ->where('c.enterpriseId', $enterpriseId);

            if ($inviterId > 0) {
                $query->whereRaw('COALESCE(c.inviterId, c.agentId) = ?', [$inviterId]);
            }
            if ($status) {
                $query->where('c.status', $status);
            }

            $total = (clone $query)->count();
            $list  = $query->order('c.createdAt', 'desc')
                           ->page($page, $pageSize)
                           ->select()
                           ->toArray();

            $orderIds = [];
            $testResultIds = [];
            foreach ($list as $row) {
                if (!empty($row['orderId'])) {
                    $orderIds[] = (int) $row['orderId'];
                }
                if (!empty($row['testResultId'])) {
                    $testResultIds[] = (int) $row['testResultId'];
                }
            }

            $orderTypeMap = [];
            if (!empty($orderIds)) {
                $rows = Db::name('test_results')
                    ->whereIn('orderId', array_values(array_unique($orderIds)))
                    ->field('orderId, testType')
                    ->select()
                    ->toArray();
                foreach ($rows as $item) {
                    $orderTypeMap[(int) $item['orderId']] = self::normalizeTestType($item['testType'] ?? '');
                }
            }

            $resultTypeMap = [];
            if (!empty($testResultIds)) {
                $rows = Db::name('test_results')
                    ->whereIn('id', array_values(array_unique($testResultIds)))
                    ->field('id, testType')
                    ->select()
                    ->toArray();
                foreach ($rows as $item) {
                    $resultTypeMap[(int) $item['id']] = self::normalizeTestType($item['testType'] ?? '');
                }
            }

            foreach ($list as &$row) {
                $testType = 'other';
                if (($row['commissionSource'] ?? '') === 'test_completion' && !empty($row['testResultId'])) {
                    $testType = $resultTypeMap[(int) $row['testResultId']] ?? 'other';
                } elseif (!empty($row['orderId'])) {
                    $testType = $orderTypeMap[(int) $row['orderId']] ?? 'other';
                }

                $row['testType'] = $testType;
                $row['testTypeLabel'] = self::getTestTypeLabel($testType);
                $row['commissionYuan'] = number_format($row['commissionFen'] / 100, 2, '.', '');
                $row['orderYuan']      = number_format($row['orderAmount'] / 100, 2, '.', '');
            }

            return success(['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize]);
        } catch (\Exception $e) {
            return error('获取佣金记录失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  GET distribution/withdrawals  提现申请列表
    // ─────────────────────────────────────────────────────────────
    public function withdrawals()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $page         = max(1, (int) Request::param('page', 1));
        $pageSize     = min(100, (int) Request::param('pageSize', 20));
        $status       = Request::param('status', '');

        try {
            $query = Db::name('distribution_withdrawals')
                ->alias('w')
                ->join('wechat_users u', 'w.userId = u.id')
                ->field('w.*, u.nickname, u.avatar')
                ->where('u.enterpriseId', $enterpriseId);

            if ($status !== '') {
                // 后台传入可以是字符串或数字，这里统一转 int
                $query->where('w.status', (int)$status);
            }

            $total = (clone $query)->count();
            $list  = $query->order('w.createdAt', 'desc')
                           ->page($page, $pageSize)
                           ->select()
                           ->toArray();

            foreach ($list as &$row) {
                $row['amountYuan']  = number_format($row['amountFen'] / 100, 2, '.', '');
                $row['nickname']    = $row['nickname'] ?: '未知用户';

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
    //  POST distribution/withdrawals/:id/approve  审核通过
    // ─────────────────────────────────────────────────────────────
    public function approveWithdrawal(int $id)
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $note         = Request::param('note', '');
        $now          = time();

        $record = Db::name('distribution_withdrawals')
            ->alias('w')
            ->join('wechat_users u', 'w.userId = u.id')
            ->where('w.id', $id)
            ->where('u.enterpriseId', $enterpriseId)
            ->field('w.*, u.openid')
            ->find();
        // 仅允许处理审核中（status=0）的记录
        if (!$record || (int)$record['status'] !== 0) {
            return error('提现申请不存在、已处理或无权限', 400);
        }

        try {
            // 生成商户明细单号：TX + 时间戳 + 随机数（示例：TX202603121526520005）
            $outDetailNo = 'TX' . date('YmdHis') . mt_rand(1000, 9999);

            // 调用微信商家转账到零钱接口
            $service = new \app\common\service\WechatTransferService();
            $result  = $service->createTransfer([
                'out_detail_no'  => $outDetailNo,
                'transfer_amount'=> (int) $record['amountFen'],
                'transfer_remark'=> '推广佣金提现',
                'openid'         => $record['openid'],
                'batch_name'     => '推广佣金提现',
                'batch_remark'   => '用户提现',
            ]);

            if ($result['success'] !== true) {
                $err  = $result['error'] ?? [];
                $code = $err['code']    ?? 'UNKNOWN';
                $msg  = $err['message'] ?? '微信转账接口调用失败';
                return error("微信转账发起失败（{$code}）：{$msg}", 500);
            }

            $wechatData = $result['data'] ?? [];
            Db::name('distribution_withdrawals')
                ->where('id', $id)
                ->update([
                    // 2=待收款（已发起微信转账，等待用户确认）
                    'status'           => 2,
                    'auditNote'        => $note,
                    'auditAt'          => $now,
                    'updatedAt'        => $now,
                    'pay_type'         => 'wechat',
                    'out_bill_no'      => $outDetailNo,
                    'transfer_bill_no' => $wechatData['batch_id'] ?? null,
                    'wechat_pay_state' => $wechatData['batch_status'] ?? 'PROCESSING',
                    'transfer_scene_id'=> $wechatData['transfer_scene_id'] ?? env('TRANSFER_SCENE_ID', '1005'),
                    'mch_id'           => env('MCH_ID', null),
                ]);

            return success(null, '审核通过，已发起微信转账');
        } catch (\Exception $e) {
            return error('操作失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  POST distribution/withdrawals/:id/reject  审核拒绝
    // ─────────────────────────────────────────────────────────────
    public function rejectWithdrawal(int $id)
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $note         = Request::param('note', '');
        $now          = time();

        $record = Db::name('distribution_withdrawals')
            ->alias('w')
            ->join('wechat_users u', 'w.userId = u.id')
            ->where('w.id', $id)
            ->where('u.enterpriseId', $enterpriseId)
            ->field('w.*')
            ->find();
        // 仅允许处理审核中（status=0）的记录
        if (!$record || (int)$record['status'] !== 0) {
            return error('提现申请不存在、已处理或无权限', 400);
        }

        Db::startTrans();
        try {
            // 退回余额
            Db::name('wechat_users')
                ->where('id', $record['userId'])
                ->inc('walletBalance', $record['amountFen'])
                ->update(['updatedAt' => $now]);

            Db::name('distribution_withdrawals')
                ->where('id', $id)
                ->update([
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
    //  GET distribution/settings  获取企业分销配置
    // ─────────────────────────────────────────────────────────────
    public function settings()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        try {
            $config = Db::name('system_config')
                ->where('key', 'distribution')
                ->where('enterprise_id', $enterpriseId)
                ->find();

            $tsDefault = self::defaultTestSettings();
            $default = [
                'enabled'          => true,
                'promoCenterTitle' => '推广中心',
                'bindingDays'      => 30,
                'testSettings'     => $tsDefault,
            ];

            if ($config && $config['value']) {
                $settings = is_string($config['value']) ? json_decode($config['value'], true) : $config['value'];
                $settings = array_merge($default, $settings ?? []);
            } else {
                $settings = $default;
            }

            // 附加前端可读的 commissionAmount（元）；合并默认键，兼容旧库缺少 sbti 等字段
            $settings['testSettings'] = self::appendTestSettingsAmount(
                array_merge($tsDefault, $settings['testSettings'] ?? [])
            );

            return success($settings);
        } catch (\Exception $e) {
            return error('获取配置失败：' . $e->getMessage(), 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  PUT distribution/settings  更新企业分销配置
    // ─────────────────────────────────────────────────────────────
    public function updateSettings()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限', 403);
        }

        $enterpriseId = $this->resolveEnterpriseIdForAdmin($user);
        if ($enterpriseId <= 0) {
            return error('未绑定企业或企业无效', 400);
        }

        $settings = Request::only(['enabled', 'promoCenterTitle', 'bindingDays', 'testSettings']);

        $promoTitle = trim((string)($settings['promoCenterTitle'] ?? ''));
        $toSave = [
            'enabled'          => (bool)($settings['enabled'] ?? true),
            'promoCenterTitle' => $promoTitle !== '' ? $promoTitle : '推广中心',
            'bindingDays'      => (int)($settings['bindingDays'] ?? 30),
            'testSettings'     => self::sanitizeTestSettings($settings['testSettings'] ?? null),
        ];

        try {
            $now      = time();
            $existing = Db::name('system_config')
                ->where('key', 'distribution')
                ->where('enterprise_id', $enterpriseId)
                ->find();

            if ($existing) {
                Db::name('system_config')
                    ->where('key', 'distribution')
                    ->where('enterprise_id', $enterpriseId)
                    ->update(['value' => json_encode($toSave, JSON_UNESCAPED_UNICODE), 'updatedAt' => $now]);
            } else {
                Db::name('system_config')->insert([
                    'key'           => 'distribution',
                    'enterprise_id' => $enterpriseId,
                    'value'         => json_encode($toSave, JSON_UNESCAPED_UNICODE),
                    'createdAt'     => $now,
                    'updatedAt'     => $now,
                ]);
            }

            $toSave['testSettings'] = self::appendTestSettingsAmount($toSave['testSettings']);
            return success($toSave, '配置已保存');
        } catch (\Exception $e) {
            return error('保存配置失败：' . $e->getMessage(), 500);
        }
    }

    private static function defaultTestSettings(): array
    {
        $item = ['enabled' => true, 'commissionType' => 'ratio', 'commissionRate' => 90, 'commissionAmountFen' => 0, 'noPayment' => false];
        return ['face' => $item, 'mbti' => $item, 'sbti' => $item, 'disc' => $item, 'pdp' => $item];
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

    private static function buildProductCommissionSeries(int $enterpriseId): array
    {
        $records = Db::name('commission_records')
            ->where('enterpriseId', $enterpriseId)
            ->whereIn('status', ['paid', 'frozen'])
            ->field('orderId, testResultId, commissionSource, commissionFen')
            ->select()
            ->toArray();

        $orderIds = [];
        $testResultIds = [];
        foreach ($records as $record) {
            if (!empty($record['orderId'])) {
                $orderIds[] = (int) $record['orderId'];
            }
            if (!empty($record['testResultId'])) {
                $testResultIds[] = (int) $record['testResultId'];
            }
        }

        $orderTypeMap = [];
        if (!empty($orderIds)) {
            $rows = Db::name('test_results')
                ->whereIn('orderId', array_values(array_unique($orderIds)))
                ->field('orderId, testType')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $orderTypeMap[(int) $row['orderId']] = self::normalizeTestType($row['testType'] ?? '');
            }
        }

        $resultTypeMap = [];
        if (!empty($testResultIds)) {
            $rows = Db::name('test_results')
                ->whereIn('id', array_values(array_unique($testResultIds)))
                ->field('id, testType')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $resultTypeMap[(int) $row['id']] = self::normalizeTestType($row['testType'] ?? '');
            }
        }

        $totals = [
            'face'  => 0,
            'mbti'  => 0,
            'sbti'  => 0,
            'disc'  => 0,
            'pdp'   => 0,
            'other' => 0,
        ];

        foreach ($records as $record) {
            $type = 'other';
            if (($record['commissionSource'] ?? '') === 'test_completion' && !empty($record['testResultId'])) {
                $type = $resultTypeMap[(int) $record['testResultId']] ?? 'other';
            } elseif (!empty($record['orderId'])) {
                $type = $orderTypeMap[(int) $record['orderId']] ?? 'other';
            }

            if (!isset($totals[$type])) {
                $type = 'other';
            }
            $totals[$type] += (int) ($record['commissionFen'] ?? 0);
        }

        return [
            ['label' => '人脸分析', 'value' => round($totals['face']  / 100, 2)],
            ['label' => 'MBTI',     'value' => round($totals['mbti']  / 100, 2)],
            ['label' => 'SBTI',     'value' => round($totals['sbti']  / 100, 2)],
            ['label' => 'DISC',     'value' => round($totals['disc']  / 100, 2)],
            ['label' => 'PDP',      'value' => round($totals['pdp']   / 100, 2)],
            ['label' => '其他',     'value' => round($totals['other'] / 100, 2)],
        ];
    }

    private static function normalizeTestType(string $testType): string
    {
        $normalized = strtolower(trim($testType));
        if ($normalized === 'ai') {
            return 'face';
        }
        if (in_array($normalized, ['face', 'mbti', 'sbti', 'disc', 'pdp'], true)) {
            return $normalized;
        }
        return 'other';
    }

    private static function getTestTypeLabel(string $testType): string
    {
        $map = [
            'face'  => '人脸',
            'mbti'  => 'MBTI',
            'sbti'  => 'SBTI',
            'disc'  => 'DISC',
            'pdp'   => 'PDP',
            'other' => '其他',
        ];

        return $map[$testType] ?? strtoupper($testType ?: '其他');
    }
}
