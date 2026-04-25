<?php
namespace app\controller\admin;

use app\BaseController;
use app\common\PdpDiscResultText;
use think\facade\Db;
use think\facade\Request;

/**
 * 管理端订单列表（只读），包含用户信息与关联的测试数据
 */
class Order extends BaseController
{
    /**
     * 订单列表：分页、关键词、状态/产品筛选；企业管理员仅本企业订单
     * GET /api/v1/admin/orders?page=1&pageSize=20&keyword=&status=&productType=
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'] ?? '', ['admin', 'enterprise_admin', 'superadmin'])) {
            return error('无权限访问', 403);
        }

        $page = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $pageSize = min(max($pageSize, 1), 100);
        $keyword = trim(Request::param('keyword', ''));
        $status = trim(Request::param('status', ''));
        $productType = trim(Request::param('productType', ''));
        $inviterId = (int) Request::param('inviterId', 0);

        // 超管：全平台订单；其余管理员仅本企业
        $enterpriseId = null;
        if (($user['role'] ?? '') !== 'superadmin') {
            $enterpriseId = $user['enterpriseId'] ?? null;
            if (!$enterpriseId) {
                $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
                $enterpriseId = $adminRow['enterpriseId'] ?? null;
            }
        }

        $query = Db::name('orders');

        if ($enterpriseId !== null) {
            $query->where('enterpriseId', $enterpriseId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($productType !== '') {
            $query->where('productType', $productType);
        }
        if ($inviterId > 0) {
            // 通过分销绑定表过滤「该分销商带来的订单」：orders.userId = distribution_bindings.inviteeId，inviter=inviterId
            try {
                $inviteeIds = Db::name('distribution_bindings')
                    ->where('inviterId', $inviterId)
                    ->column('inviteeId');
                $inviteeIds = array_values(array_filter(array_map('intval', $inviteeIds ?: [])));
                if (empty($inviteeIds)) {
                    return success([
                        'list' => [], 'total' => 0, 'page' => $page, 'pageSize' => $pageSize,
                        'hasMore' => false, 'paidCompletedCount' => 0, 'totalRevenueFen' => 0,
                    ]);
                }
                $query->whereIn('userId', $inviteeIds);
            } catch (\Throwable $e) {
                // 表不存在或字段差异：忽略筛选
            }
        }
        if ($keyword !== '') {
            if (is_numeric($keyword)) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereLike('orderNo', '%' . $keyword . '%')->whereOr('userId', (int) $keyword);
                });
            } else {
                $userIdsMatch = Db::name('wechat_users')->where('nickname|phone', 'like', '%' . $keyword . '%')->column('id');
                $userIdsMatch = array_values(array_filter($userIdsMatch));
                $query->where(function ($q) use ($keyword, $userIdsMatch) {
                    $q->whereLike('orderNo', '%' . $keyword . '%');
                    if (!empty($userIdsMatch)) {
                        $q->whereOr('userId', 'in', $userIdsMatch);
                    }
                });
            }
        }

        // 与列表相同筛选条件下的全量统计（非当前页）：已支付/已完成单数、实收金额（分）
        $paidCompletedCount = (int) (clone $query)->whereIn('status', ['paid', 'completed'])->count();
        $totalRevenueFen = (int) (clone $query)->whereIn('status', ['paid', 'completed'])->sum('amount');

        $query->order('createdAt', 'desc');
        $total = (int) (clone $query)->count();
        $list = (clone $query)->page($page, $pageSize)->select()->toArray();

        $userIds = array_values(array_unique(array_filter(array_column($list, 'userId'))));
        $usersMap = [];
        if (!empty($userIds)) {
            $users = Db::name('wechat_users')
                ->where('id', 'in', $userIds)
                ->field('id, nickname, phone')
                ->select()
                ->toArray();
            foreach ($users as $u) {
                $usersMap[(int) $u['id']] = $u;
            }
        }

        $orderIds = array_column($list, 'id');
        $testsByOrder = [];
        if (!empty($orderIds)) {
            $tests = Db::name('test_results')
                ->where('orderId', 'in', $orderIds)
                ->field('id, orderId, userId, testType, resultData, createdAt')
                ->order('createdAt', 'desc')
                ->select()
                ->toArray();
            foreach ($tests as $t) {
                $oid = (int) ($t['orderId'] ?? 0);
                if ($oid <= 0) {
                    continue;
                }
                if (!isset($testsByOrder[$oid])) {
                    $testsByOrder[$oid] = [];
                }
                $raw = $t['resultData'] ?? '';
                $resultStr = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
                $testsByOrder[$oid][] = [
                    'id' => (int) $t['id'],
                    'testType' => $t['testType'] ?? '',
                    'resultSummary' => $this->extractResultSummary($t['testType'] ?? '', $resultStr),
                    'createdAt' => isset($t['createdAt']) ? (int) $t['createdAt'] : null,
                ];
            }
        }

        // 当前页订单的分销分润记录
        $commissionsByOrder = [];
        if (!empty($orderIds)) {
            try {
                $crRows = Db::name('commission_records')
                    ->where('orderId', 'in', $orderIds)
                    ->field('id, orderId, inviterId, commissionFen, rate, status, paidAt, createdAt')
                    ->order('createdAt', 'asc')
                    ->select()
                    ->toArray();
                $inviterIds = array_values(array_unique(array_filter(array_column($crRows, 'inviterId'))));
                $inviterMap = [];
                if (!empty($inviterIds)) {
                    $inviters = Db::name('wechat_users')
                        ->where('id', 'in', $inviterIds)
                        ->field('id, nickname, phone')
                        ->select()
                        ->toArray();
                    foreach ($inviters as $iv) {
                        $inviterMap[(int) $iv['id']] = $iv;
                    }
                }
                foreach ($crRows as $cr) {
                    $oid = (int) ($cr['orderId'] ?? 0);
                    if ($oid <= 0) {
                        continue;
                    }
                    $iv = $inviterMap[(int) ($cr['inviterId'] ?? 0)] ?? null;
                    $commissionsByOrder[$oid][] = [
                        'id' => (int) $cr['id'],
                        'inviterId' => (int) ($cr['inviterId'] ?? 0),
                        'inviterName' => $iv['nickname'] ?? null,
                        'inviterPhone' => $iv['phone'] ?? null,
                        'commissionFen' => (int) ($cr['commissionFen'] ?? 0),
                        'rate' => isset($cr['rate']) ? (float) $cr['rate'] : null,
                        'status' => (string) ($cr['status'] ?? ''),
                        'paidAt' => isset($cr['paidAt']) ? (int) $cr['paidAt'] : null,
                        'createdAt' => isset($cr['createdAt']) ? (int) $cr['createdAt'] : null,
                    ];
                }
            } catch (\Throwable $e) {
                $commissionsByOrder = [];
            }
        }

        foreach ($list as &$row) {
            $uid = (int) ($row['userId'] ?? 0);
            $u = $usersMap[$uid] ?? null;
            $row['userName'] = $u ? ($u['nickname'] ?? ('用户' . $uid)) : ('用户' . $uid);
            $row['userPhone'] = $u ? ($u['phone'] ?? '') : '';
            $row['testData'] = $testsByOrder[$row['id']] ?? [];
            $row['commissions'] = $commissionsByOrder[$row['id']] ?? [];
        }

        return success([
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'pageSize'   => $pageSize,
            'hasMore'    => ($page * $pageSize) < $total,
            // 看板卡片：全量口径（随 keyword / status / productType / 企业 筛选变化，与 total 一致）
            'paidCompletedCount' => $paidCompletedCount,
            'totalRevenueFen'    => $totalRevenueFen,
        ]);
    }

    /**
     * 从 resultData 字符串中提取简要结果（用于列表展示）
     */
    private function extractResultSummary(string $testType, string $resultStr): string
    {
        if ($resultStr === '') {
            return '-';
        }
        $data = json_decode($resultStr, true);
        if (!is_array($data)) {
            return mb_substr($resultStr, 0, 30) . (mb_strlen($resultStr) > 30 ? '…' : '');
        }
        $type = strtolower($testType);

        if ($type === 'mbti') {
            return (string) ($data['mbtiType'] ?? $data['type'] ?? $data['result'] ?? '');
        }
        if ($type === 'disc') {
            $two = PdpDiscResultText::discTopTwo($data);
            if ($two !== '') {
                return $two;
            }
            $desc = $data['description']['type'] ?? null;
            if (is_string($desc) && $desc !== '') {
                return $desc;
            }
            if (!empty($data['dominantType'])) {
                return (string) $data['dominantType'] . '型';
            }
            return (string) ($data['disc'] ?? '');
        }
        if ($type === 'pdp') {
            $two = PdpDiscResultText::pdpTopTwo($data);
            if ($two !== '') {
                return $two;
            }
            $desc = $data['description']['type'] ?? null;
            if (is_string($desc) && $desc !== '') {
                return $desc;
            }
            if (!empty($data['dominantType'])) {
                return (string) $data['dominantType'];
            }
            return (string) ($data['pdp'] ?? '');
        }
        if ($type === 'face' || $type === 'ai') {
            return '人脸分析';
        }

        return (string) ($data['type'] ?? $data['result'] ?? '');
    }
}
