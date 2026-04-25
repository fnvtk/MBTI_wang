<?php
namespace app\controller\admin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 企业后台：高考用户管理
 */
class GaokaoUser extends BaseController
{
    private function currentEnterpriseId(): int
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array(($user['role'] ?? ''), ['admin', 'enterprise_admin'], true)) {
            return 0;
        }
        $eid = (int) ($user['enterpriseId'] ?? 0);
        if ($eid > 0) {
            return $eid;
        }
        $adminRow = Db::name('users')->where('id', (int) ($user['userId'] ?? 0))->find();
        return (int) ($adminRow['enterpriseId'] ?? 0);
    }

    public function index()
    {
        $eid = $this->currentEnterpriseId();
        if ($eid <= 0) {
            return error('无权限访问', 403);
        }
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(1, (int) Request::param('pageSize', 20)));
        $keyword = trim((string) Request::param('keyword', ''));
        $analyzeStatus = Request::param('analyzeStatus', '');

        $q = Db::name('gaokao_user_profile')->alias('g')
            ->join('wechat_users w', 'w.id = g.userId')
            ->where('g.tenantId', $eid);
        if ($keyword !== '') {
            $q->whereRaw('(w.nickname LIKE ? OR w.phone LIKE ? OR g.name LIKE ?)', ['%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%']);
        }
        if ($analyzeStatus !== '' && $analyzeStatus !== null) {
            $q->where('g.analyzeStatus', (int) $analyzeStatus);
        }

        $total = (int) (clone $q)->count();
        $rows = (clone $q)->field('g.*,w.nickname,w.phone,w.avatar')
            ->order('g.id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        return paginate_response($rows, $total, $page, $pageSize);
    }

    public function detail($id)
    {
        $eid = $this->currentEnterpriseId();
        if ($eid <= 0) {
            return error('无权限访问', 403);
        }
        $row = Db::name('gaokao_user_profile')->alias('g')
            ->join('wechat_users w', 'w.id = g.userId')
            ->where('g.id', (int) $id)
            ->where('g.tenantId', $eid)
            ->field('g.*,w.nickname,w.phone,w.avatar')
            ->find();
        if (!$row) {
            return error('记录不存在', 404);
        }
        $report = null;
        if (!empty($row['latestReportId'])) {
            $tr = Db::name('test_results')
                ->where('id', (int) $row['latestReportId'])
                ->where('testType', 'gaokao')
                ->find();
            if ($tr) {
                $report = $tr;
            }
        }
        $orders = Db::name('orders')
            ->where('userId', (int) $row['userId'])
            ->where('productType', 'gaokao')
            ->order('id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();
        return success([
            'profile' => $row,
            'latestReport' => $report,
            'orders' => $orders,
        ]);
    }
}

