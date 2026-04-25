<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

class ProfitRule extends BaseController
{
    public function index()
    {
        $this->ensureSuperadmin();
        $list = Db::name('profit_sharing_rules')->order('id', 'asc')->select()->toArray();
        foreach ($list as &$row) {
            $row['receivers'] = json_decode($row['receivers'] ?? '[]', true) ?: [];
        }
        unset($row);
        return success(['list' => $list]);
    }

    public function save()
    {
        $this->ensureSuperadmin();
        $id          = (int) Request::param('id', 0);
        $productType = trim((string) Request::param('productType', ''));
        $name        = trim((string) Request::param('name', ''));
        $receivers   = Request::param('receivers/a', []);
        $status      = Request::param('status', 'active');
        if ($productType === '' || $name === '') return error('productType / name 必填');
        if (!is_array($receivers) || count($receivers) === 0) return error('至少一个收款人');

        // 校验比例合计 = 1
        $sum = 0.0;
        foreach ($receivers as $r) { $sum += (float) ($r['ratio'] ?? 0); }
        if (abs($sum - 1.0) > 0.005) {
            return error('分账比例合计必须等于 100%（当前 ' . round($sum * 100, 2) . '%）');
        }

        $now = time();
        $data = [
            'productType' => $productType,
            'name'        => $name,
            'receivers'   => json_encode($receivers, JSON_UNESCAPED_UNICODE),
            'status'      => $status === 'disabled' ? 'disabled' : 'active',
            'updatedAt'   => $now,
        ];

        if ($id > 0) {
            Db::name('profit_sharing_rules')->where('id', $id)->update($data);
        } else {
            $dup = Db::name('profit_sharing_rules')->where('productType', $productType)->find();
            if ($dup) return error('该产品类型已存在规则，请编辑现有规则');
            $data['createdAt'] = $now;
            Db::name('profit_sharing_rules')->insert($data);
        }
        return success(null, '已保存');
    }

    public function toggle($id)
    {
        $this->ensureSuperadmin();
        $row = Db::name('profit_sharing_rules')->where('id', (int) $id)->find();
        if (!$row) return error('规则不存在');
        $next = $row['status'] === 'active' ? 'disabled' : 'active';
        Db::name('profit_sharing_rules')->where('id', (int) $id)->update([
            'status'    => $next,
            'updatedAt' => time(),
        ]);
        return success(['status' => $next]);
    }

    private function ensureSuperadmin()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            abort(403, '无权限访问');
        }
    }
}
