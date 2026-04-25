<?php

namespace app\controller\superadmin;

use app\BaseController;
use app\common\service\EnterpriseCooperationService;
use app\model\Enterprise as EnterpriseModel;
use think\facade\Request;
use think\Response;

/**
 * 超管：企业合作模式配置
 */
class EnterpriseCooperation extends BaseController
{
    /**
     * GET enterprises/:id/cooperation-modes
     */
    public function get($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $eid = (int) $id;
        if ($eid <= 0) {
            return error('无效的企业 ID', 400);
        }
        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }

        $list = EnterpriseCooperationService::listModesForEnterprise($eid, false);

        return success(['list' => $list]);
    }

    /**
     * PUT enterprises/:id/cooperation-modes
     * Body: { "modes": [ { "modeCode", "enabled", "sortOrder", "title", "description" }, ... ] }
     */
    public function update($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $eid = (int) $id;
        if ($eid <= 0) {
            return error('无效的企业 ID', 400);
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
     * GET enterprises/:id/cooperation-choices
     */
    public function listUserChoices($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }
        $eid = (int) $id;
        if ($eid <= 0) {
            return error('无效的企业 ID', 400);
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
     * GET enterprises/:id/cooperation-choices/export
     */
    public function exportUserChoices($id)
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }
        $eid = (int) $id;
        if ($eid <= 0) {
            return error('无效的企业 ID', 400);
        }
        if (!EnterpriseModel::find($eid)) {
            return error('企业不存在', 404);
        }
        $keyword = trim((string) Request::param('keyword', ''));
        $rows    = EnterpriseCooperationService::listUserCooperationChoicesForExport($eid, $keyword, 10000);
        $csv     = "\xEF\xBB\xBF" . EnterpriseCooperationService::buildUserCooperationChoicesCsvContent($rows, true);
        $name    = 'cooperation-choices-e' . $eid . '.csv';

        return Response::create($csv, 'html', 200)
            ->contentType('text/csv; charset=UTF-8')
            ->header(['Content-Disposition' => 'attachment; filename="' . $name . '"']);
    }
}
