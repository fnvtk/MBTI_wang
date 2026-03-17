<?php
namespace app\controller\admin;

use app\BaseController;
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
        if (!in_array($user['role'] ?? '', ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $page = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $pageSize = min(max($pageSize, 1), 100);
        $keyword = trim(Request::param('keyword', ''));
        $status = trim(Request::param('status', ''));
        $productType = trim(Request::param('productType', ''));

        // admin / enterprise_admin 均只能看本企业订单
        $enterpriseId = $user['enterpriseId'] ?? null;
        if (!$enterpriseId) {
            $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
            $enterpriseId = $adminRow['enterpriseId'] ?? null;
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

        foreach ($list as &$row) {
            $uid = (int) ($row['userId'] ?? 0);
            $u = $usersMap[$uid] ?? null;
            $row['userName'] = $u ? ($u['nickname'] ?? ('用户' . $uid)) : ('用户' . $uid);
            $row['userPhone'] = $u ? ($u['phone'] ?? '') : '';
            $row['testData'] = $testsByOrder[$row['id']] ?? [];
        }

        return paginate_response($list, $total, $page, $pageSize);
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
