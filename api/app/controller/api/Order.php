<?php
namespace app\controller\api;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 小程序端：当前登录用户的支付订单列表（mbti_orders.userId = wechat_users.id）
 */
class Order extends BaseController
{
    /**
     * GET /api/orders?page=1&pageSize=20
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        $source = $user['source'] ?? '';
        if (!in_array($source, ['wechat', 'douyin'], true)) {
            return error('仅支持小程序用户', 403);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('用户不存在', 400);
        }

        $page = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $pageSize = min(max($pageSize, 1), 100);

        $query = Db::name('orders')
            ->where('userId', $userId)
            ->order('createdAt', 'desc');

        $total = (int) (clone $query)->count();
        $rows = (clone $query)->page($page, $pageSize)->select()->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatOrderRow($row);
        }

        return paginate_response($list, $total, $page, $pageSize);
    }

    private function formatOrderRow(array $row): array
    {
        $amountFen = (int) ($row['amount'] ?? 0);
        $productType = (string) ($row['productType'] ?? '');
        $title = trim((string) ($row['productTitle'] ?? ''));
        if ($title === '') {
            $title = $this->productTypeLabel($productType);
        }

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'orderNo'      => (string) ($row['orderNo'] ?? ''),
            'productType'  => $productType,
            'productTitle' => $title,
            'amountFen'    => $amountFen,
            'amountYuan'   => number_format($amountFen / 100, 2, '.', ''),
            'status'       => (string) ($row['status'] ?? ''),
            'statusText'   => $this->statusLabel((string) ($row['status'] ?? '')),
            'payMethod'    => (string) ($row['payMethod'] ?? ''),
            'payTime'      => isset($row['payTime']) ? (int) $row['payTime'] : null,
            'payTimeStr'   => $this->formatTs($row['payTime'] ?? null),
            'createdAt'    => isset($row['createdAt']) ? (int) $row['createdAt'] : null,
            'createdAtStr' => $this->formatTs($row['createdAt'] ?? null),
        ];
    }

    private function formatTs($ts): string
    {
        if ($ts === null || $ts === '') {
            return '';
        }
        $t = (int) $ts;
        if ($t <= 0) {
            return '';
        }
        return date('Y-m-d H:i', $t);
    }

    private function statusLabel(string $s): string
    {
        $map = [
            'pending'   => '待支付',
            'paid'      => '已支付',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ];
        return $map[$s] ?? ($s !== '' ? $s : '未知');
    }

    private function productTypeLabel(string $t): string
    {
        $map = [
            'face'           => '人脸分析',
            'mbti'           => 'MBTI测试',
            'disc'           => 'DISC测试',
            'pdp'            => 'PDP测试',
            'report'         => '完整报告',
            'resume'         => '简历分析',
            'recharge'       => '企业充值',
            'vip'            => '会员服务',
            'deep_personal'  => '深度服务（个人）',
            'deep_team'      => '深度服务（团队）',
            'single_test'    => '单次测试',
            'test_count'     => '测试次数包',
            'team_analysis'  => '团队分析',
        ];
        return $map[$t] ?? ($t !== '' ? $t : '订单');
    }
}
