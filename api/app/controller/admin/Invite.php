<?php
namespace app\controller\admin;

use app\BaseController;
use app\common\service\WechatService;
use think\facade\Db;

/**
 * 管理端 - 小程序邀请二维码（企业版 + 个人版）
 */
class Invite extends BaseController
{
    /**
     * 一次生成两枚小程序码：企业测评入口 + 个人版首页入口
     * GET /api/v1/admin/invite/qrcode
     * 可选：?enterpriseId=1 仅普通管理员指定企业时传；企业管理员用自身 enterpriseId
     *
     * 返回 enterprise / personal 各含 data:image/png;base64,... ；兼容旧字段 qrcode=企业版图
     */
    public function qrcode()
    {
        $admin = $this->request->user ?? null;
        if (!$admin || !in_array($admin['role'] ?? '', ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $enterpriseId = null;
        if (($admin['role'] ?? '') === 'enterprise_admin') {
            $row = Db::name('users')->where('id', (int) ($admin['userId'] ?? 0))->find();
            $enterpriseId = isset($row['enterpriseId']) ? (int) $row['enterpriseId'] : null;
        } else {
            $enterpriseId = (int) $this->request->param('enterpriseId', 0);
            if ($enterpriseId <= 0) {
                return error('请指定企业（企业管理员无需传参，使用所属企业）', 400);
            }
        }
        if ($enterpriseId <= 0) {
            return error('无法确定企业，仅企业管理员或指定 enterpriseId 可生成邀请码', 400);
        }

        $sceneEnterprise = 'e_' . $enterpriseId;
        $pageEnterprise  = 'pages/enterprise/index';

        $resultEnt = WechatService::getWxacodeUnlimited($sceneEnterprise, $pageEnterprise, 430);
        if (isset($resultEnt['errcode'])) {
            return error('企业版小程序码失败：' . ($resultEnt['errmsg'] ?? ''), 500);
        }
        $binEnt = $resultEnt['binary'] ?? '';
        if ($binEnt === '') {
            return error('企业版小程序码生成失败', 500);
        }
        $b64Ent = 'data:image/png;base64,' . base64_encode($binEnt);

        // 个人版：首页，scene 使用短串 p（index 无 eid 则留在个人版流程）
        $scenePersonal = 'p';
        $pagePersonal  = 'pages/index/index';
        $resultPer     = WechatService::getWxacodeUnlimited($scenePersonal, $pagePersonal, 430);
        if (isset($resultPer['errcode'])) {
            return error('个人版小程序码失败：' . ($resultPer['errmsg'] ?? ''), 500);
        }
        $binPer = $resultPer['binary'] ?? '';
        if ($binPer === '') {
            return error('个人版小程序码生成失败', 500);
        }
        $b64Per = 'data:image/png;base64,' . base64_encode($binPer);

        return success([
            'qrcode'     => $b64Ent,
            'scene'      => $sceneEnterprise,
            'page'       => $pageEnterprise,
            'enterprise' => [
                'qrcode' => $b64Ent,
                'scene'  => $sceneEnterprise,
                'page'   => $pageEnterprise,
                'label'  => '企业版',
            ],
            'personal' => [
                'qrcode' => $b64Per,
                'scene'  => $scenePersonal,
                'page'   => $pagePersonal,
                'label'  => '个人版',
            ],
        ]);
    }
}

