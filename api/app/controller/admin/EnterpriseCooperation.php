<?php

namespace app\controller\admin;

use app\BaseController;
use app\common\service\EnterpriseCooperationService;
use app\model\Enterprise as EnterpriseModel;
use think\facade\Db;
use think\facade\Request;
use think\Response;

/**
 * 企业管理员：本企业合作模式配置
 */
class EnterpriseCooperation extends BaseController
{
    public function get()
    {
        $user = $this->request->user ?? null;
        if (!$this->canAccessEnterpriseCooperation($user)) {
            return error('仅已绑定企业的管理员可配置', 403);
        }

        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }

        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }

        $list = EnterpriseCooperationService::listModesForEnterprise($eid, false);

        return success(['list' => $list]);
    }

    public function update()
    {
        $user = $this->request->user ?? null;
        if (!$this->canAccessEnterpriseCooperation($user)) {
            return error('仅已绑定企业的管理员可配置', 403);
        }

        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }

        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }

        $body = Request::put();
        if (!is_array($body)) {
            $body = [];
        }
        $modes = $body['modes'] ?? null;
        if (!is_array($modes)) {
            return error('请提交 modes 数组', 400);
        }

        try {
            EnterpriseCooperationService::saveConfigs($eid, $modes);
        } catch (\InvalidArgumentException $e) {
            return error($e->getMessage(), 400);
        }
        $list = EnterpriseCooperationService::listModesForEnterprise($eid, false);

        return success(['list' => $list], '已保存');
    }

    /**
     * GET enterprise/cooperation-choices
     * 本企业下用户已选合作意向（分页）
     */
    public function listChoices()
    {
        $user = $this->request->user ?? null;
        if (!$this->canAccessEnterpriseCooperation($user)) {
            return error('无权限访问', 403);
        }
        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }
        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }
        $page     = (int) Request::param('page', 1);
        $pageSize = (int) Request::param('pageSize', 20);
        $keyword  = trim((string) Request::param('keyword', ''));

        $data = EnterpriseCooperationService::listUserCooperationChoices($eid, $page, $pageSize, $keyword);

        return paginate_response($data['list'], $data['total'], $page, $pageSize);
    }

    /**
     * GET enterprise/cooperation-choices/export
     * 导出本企业 CSV
     */
    public function exportChoices()
    {
        $user = $this->request->user ?? null;
        if (!$this->canAccessEnterpriseCooperation($user)) {
            return error('无权限访问', 403);
        }
        $eid = $this->resolveEnterpriseId($user);
        if (!$eid || $eid <= 0) {
            return error('未绑定企业', 403);
        }
        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }
        $keyword = trim((string) Request::param('keyword', ''));
        $rows    = EnterpriseCooperationService::listUserCooperationChoicesForExport($eid, $keyword, 10000);
        $csv     = "\xEF\xBB\xBF" . EnterpriseCooperationService::buildUserCooperationChoicesCsvContent($rows, false);
        $name    = 'cooperation-choices-e' . $eid . '.csv';

        return Response::create($csv, 'html', 200)
            ->contentType('text/csv; charset=UTF-8')
            ->header(['Content-Disposition' => 'attachment; filename="' . $name . '"']);
    }

    private function canAccessEnterpriseCooperation($user): bool
    {
        if (!is_array($user)) {
            return false;
        }
        $r = (string) ($user['role'] ?? '');

        return in_array($r, ['admin', 'enterprise_admin'], true);
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
