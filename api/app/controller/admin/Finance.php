<?php
namespace app\controller\admin;

use app\BaseController;
use app\common\service\WechatService;
use think\facade\Db;
use think\facade\Request;

/**
 * 企业财务控制器（企业管理端）
 */
class Finance extends BaseController
{
    /**
     * 财务概览
     */
    public function overview()
    {
        $enterpriseId = $this->resolveEnterpriseId();
        if (!$enterpriseId) {
            return error('未获取到企业信息', 400);
        }

        try {
            $enterprise = Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->field('id, name, balance')
                ->find();
            if (!$enterprise) {
                return error('企业不存在', 404);
            }

            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $monthStart = strtotime(date('Y-m-01 00:00:00'));

            $baseOrderQuery = Db::name('orders')
                ->where('enterpriseId', $enterpriseId)
                ->whereIn('status', ['paid', 'completed'])
                ->whereIn('productType', ['face', 'mbti', 'sbti', 'disc', 'pdp']);

            $totalIncomeFen = (int) ((clone $baseOrderQuery)->sum('amount') ?? 0);
            $todayIncomeFen = (int) ((clone $baseOrderQuery)->where('payTime', '>=', $todayStart)->sum('amount') ?? 0);
            $monthIncomeFen = (int) ((clone $baseOrderQuery)->where('payTime', '>=', $monthStart)->sum('amount') ?? 0);
            $paidOrderCount = (int) ((clone $baseOrderQuery)->count());

            $manualRechargeFen = (int) (Db::name('finance_records')
                ->where('enterpriseId', $enterpriseId)
                ->where('type', 'recharge')
                ->whereNull('orderId')
                ->sum('amount') ?? 0);

            $frozenCommissionFen = (int) (Db::name('commission_records')
                ->where('enterpriseId', $enterpriseId)
                ->where('status', 'frozen')
                ->sum('commissionFen') ?? 0);

            return success([
                'enterpriseId' => $enterpriseId,
                'enterpriseName' => $enterprise['name'] ?? '',
                'balanceFen' => (int) ($enterprise['balance'] ?? 0),
                'totalIncomeFen' => $totalIncomeFen,
                'todayIncomeFen' => $todayIncomeFen,
                'monthIncomeFen' => $monthIncomeFen,
                'manualRechargeFen' => $manualRechargeFen,
                'frozenCommissionFen' => $frozenCommissionFen,
                'paidOrderCount' => $paidOrderCount,
            ]);
        } catch (\Throwable $e) {
            return error('获取企业财务概览失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 财务流水
     */
    public function records()
    {
        $enterpriseId = $this->resolveEnterpriseId();
        if (!$enterpriseId) {
            return error('未获取到企业信息', 400);
        }

        try {
            $page = max(1, (int) Request::param('page', 1));
            $pageSize = min(100, max(1, (int) Request::param('pageSize', 20)));

            $query = Db::name('finance_records')
                ->where('enterpriseId', $enterpriseId)
                ->order('createdAt', 'desc')
                ->order('id', 'desc');

            $total = (int) (clone $query)->count();
            $list = (clone $query)
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            $result = array_map(function ($row) {
                $type = (string) ($row['type'] ?? '');
                $orderId = isset($row['orderId']) ? (int) $row['orderId'] : 0;
                $direction = $type === 'consume' ? 'out' : 'in';
                $description = (string) ($row['description'] ?? '');
                $typeLabel = $type === 'consume'
                    ? '佣金扣减'
                    : (strpos($description, '企业余额充值') !== false ? '余额充值' : ($orderId > 0 ? '测试收入' : '余额充值'));

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => $type,
                    'typeLabel' => $typeLabel,
                    'direction' => $direction,
                    'amountFen' => (int) ($row['amount'] ?? 0),
                    'balanceBeforeFen' => (int) ($row['balanceBefore'] ?? 0),
                    'balanceAfterFen' => (int) ($row['balanceAfter'] ?? 0),
                    'description' => $description,
                    'orderId' => $orderId ?: null,
                    'createdAt' => (int) ($row['createdAt'] ?? 0),
                ];
            }, $list);

            return success([
                'list' => $result,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
            ]);
        } catch (\Throwable $e) {
            return error('获取财务流水失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 企业手动充值
     */
    public function rechargeQrcode()
    {
        $enterpriseId = $this->resolveEnterpriseId();
        if (!$enterpriseId) {
            return error('未获取到企业信息', 400);
        }

        try {
            $amountFen = (int) Request::param('amountFen', 0);
            if ($amountFen <= 0) {
                return error('充值金额必须大于 0', 400);
            }
            $enterprise = Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->field('id, name')
                ->find();
            if (!$enterprise) {
                return error('企业不存在', 404);
            }

            // scene 长度要尽量短，避免超过微信限制
            $scene = 'eid=' . $enterpriseId . '&a=' . $amountFen . '&r=1';
            $page = 'pages/recharge/index';
            $result = WechatService::getWxacodeUnlimited($scene, $page, 430);
            if (isset($result['errcode'])) {
                return error('获取充值小程序码失败：' . ($result['errmsg'] ?? ''), 500);
            }

            $binary = $result['binary'] ?? '';
            if ($binary === '') {
                return error('充值小程序码生成失败', 500);
            }

            return success([
                'enterpriseId' => $enterpriseId,
                'enterpriseName' => (string) ($enterprise['name'] ?? ''),
                'amountFen' => $amountFen,
                'amountYuan' => number_format($amountFen / 100, 2, '.', ''),
                'scene' => $scene,
                'page' => $page,
                'qrcode' => 'data:image/png;base64,' . base64_encode($binary),
            ]);
        } catch (\Throwable $e) {
            return error('生成充值二维码失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 解析当前管理账号所属企业
     */
    protected function resolveEnterpriseId(): ?int
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'enterprise_admin'], true)) {
            return null;
        }

        $enterpriseId = (int) ($user['enterpriseId'] ?? 0);
        if ($enterpriseId > 0) {
            return $enterpriseId;
        }

        $adminId = (int) ($user['userId'] ?? 0);
        if ($adminId <= 0) {
            return null;
        }

        return (int) (Db::name('users')->where('id', $adminId)->value('enterpriseId') ?? 0) ?: null;
    }
}
