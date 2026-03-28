<?php
namespace app\controller\admin;

use app\BaseController;
use app\common\service\WechatService;
use think\facade\Db;

/**
 * 管理端 - 小程序邀请二维码（带企业参数）
 */
class Invite extends BaseController
{
    /**
     * 生成专属邀请小程序码，scene 带企业 ID，扫码进入 pages/enterprise/index 可解析
     * GET /api/v1/admin/invite/qrcode
     * 可选：?enterpriseId=1 仅普通管理员指定企业时传；企业管理员用自身 enterpriseId
     *
     * 返回 data:image/png;base64,... 形式的图片地址
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

        // 场景值：e_企业ID，小程序 onLoad(options.scene) 可解析
        $scene = 'e_' . $enterpriseId;
        $page = 'pages/enterprise/index';

        $result = WechatService::getWxacodeUnlimited($scene, $page, 430);
        if (isset($result['errcode'])) {
            return error('获取小程序码失败：' . ($result['errmsg'] ?? ''), 500);
        }

        $binary = $result['binary'] ?? '';
        if ($binary === '') {
            return error('小程序码生成失败', 500);
        }

        $base64 = 'data:image/png;base64,' . base64_encode($binary);

        return success([
            'qrcode' => $base64,
            'scene'  => $scene,
            'page'   => $page,
        ]);
    }
}

