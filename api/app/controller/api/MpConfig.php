<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\MpTabbarService;

/**
 * 小程序公开运行配置
 * - GET /api/mp/tabbar : 返回底部 TabBar 动态布局
 *
 * 不强制登录，结果做 60s 软缓存（客户端也会做 localStorage 缓存）。
 */
class MpConfig extends BaseController
{
    public function tabbar()
    {
        return success(MpTabbarService::getPayload());
    }
}
