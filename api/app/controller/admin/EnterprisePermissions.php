<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\Enterprise as EnterpriseModel;
use think\facade\Db;
use think\facade\Request;

/**
 * 企业管理员：在超管授权上限内配置各功能开关（PUT 不可突破 permissionsCeiling）
 */
class EnterprisePermissions extends BaseController
{
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'enterprise_admin') {
            return error('仅企业管理员可配置', 403);
        }

        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }

        $enterprise = EnterpriseModel::find($eid);
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        $ceiling  = EnterpriseModel::normalizedPermissionsCeiling($enterprise);
        $effective = EnterpriseModel::normalizePermissionsValue($enterprise->permissions ?? null);

        return success([
            'permissions'        => $effective,
            'permissionsCeiling' => $ceiling,
        ]);
    }

    public function update()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'enterprise_admin') {
            return error('仅企业管理员可配置', 403);
        }

        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }

        $enterprise = EnterpriseModel::find($eid);
        if (!$enterprise) {
            return error('企业不存在', 404);
        }

        $body = Request::put();
        if (!is_array($body)) {
            $body = [];
        }
        $incoming = $body['permissions'] ?? null;
        if (!is_array($incoming)) {
            return error('请提交 permissions 对象', 400);
        }

        $ceiling   = EnterpriseModel::normalizedPermissionsCeiling($enterprise);
        $effective = EnterpriseModel::clampPermissionsToCeiling($ceiling, $incoming);

        $enterprise->permissions = $effective;
        $enterprise->save();

        return success([
            'permissions'        => $effective,
            'permissionsCeiling' => $ceiling,
        ], '已保存');
    }

    private function resolveEnterpriseId(array $user): ?int
    {
        $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
        $eid      = $adminRow['enterpriseId'] ?? null;
        if ($eid === null || $eid === '') {
            return null;
        }

        return (int) $eid;
    }
}
