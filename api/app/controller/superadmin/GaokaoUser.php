<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 超管：高考用户管理（全平台）
 */
class GaokaoUser extends BaseController
{
    private function authOk(): bool
    {
        $user = $this->request->user ?? null;
        return !!($user && ($user['role'] ?? '') === 'superadmin');
    }

    public function index()
    {
        if (!$this->authOk()) {
            return error('无权限访问', 403);
        }
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(1, (int) Request::param('pageSize', 20)));
        $keyword = trim((string) Request::param('keyword', ''));
        $tenantId = (int) Request::param('tenantId', 0);

        $q = Db::name('gaokao_user_profile')->alias('g')
            ->join('wechat_users w', 'w.id = g.userId')
            ->leftJoin('enterprises e', 'e.id = g.tenantId');

        if ($keyword !== '') {
            $q->whereRaw('(w.nickname LIKE ? OR w.phone LIKE ? OR g.name LIKE ?)', ['%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%']);
        }
        if ($tenantId > 0) {
            $q->where('g.tenantId', $tenantId);
        }
        $total = (int) (clone $q)->count();
        $rows = (clone $q)
            ->field('g.*,w.nickname,w.phone,w.avatar,e.name as tenantName')
            ->order('g.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        return paginate_response($rows, $total, $page, $pageSize);
    }

    public function detail($id)
    {
        if (!$this->authOk()) {
            return error('无权限访问', 403);
        }
        $row = Db::name('gaokao_user_profile')->alias('g')
            ->join('wechat_users w', 'w.id = g.userId')
            ->leftJoin('enterprises e', 'e.id = g.tenantId')
            ->where('g.id', (int) $id)
            ->field('g.*,w.nickname,w.phone,w.avatar,e.name as tenantName')
            ->find();
        if (!$row) {
            return error('记录不存在', 404);
        }
        $uid = (int) $row['userId'];
        $reports = Db::name('test_results')
            ->where('userId', $uid)
            ->where('testType', 'gaokao')
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();
        $orders = Db::name('orders')
            ->where('userId', $uid)
            ->where('productType', 'gaokao')
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();
        $orderIds = array_map(static function ($o) {
            return (int) ($o['id'] ?? 0);
        }, $orders);
        $orderIds = array_values(array_filter($orderIds));
        $commissions = [];
        if ($orderIds !== []) {
            $commissions = Db::name('commission_records')
                ->whereIn('orderId', $orderIds)
                ->order('id', 'desc')
                ->select()
                ->toArray();
        }
        $binding = Db::name('distribution_bindings')
            ->where('inviteeId', $uid)
            ->where('status', 'active')
            ->where('expireAt', '>', time())
            ->order('id', 'desc')
            ->find();
        return success([
            'profile' => $row,
            'reports' => $reports,
            'orders' => $orders,
            'commissions' => $commissions,
            'distributionBinding' => $binding,
        ]);
    }
}

