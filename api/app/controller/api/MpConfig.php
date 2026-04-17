<?php
namespace app\controller\api;

use app\BaseController;
use think\facade\Db;

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
        $items = Db::name('mp_tabbar_items')
            ->where('visible', 1)
            ->order('sortOrder', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $list = [];
        foreach ($items as $row) {
            $iconKey = $row['iconKey'] ?? 'home';
            $iconUrl = $row['iconUrl'] ?? null;
            if ($iconKey === 'ai') {
                // 神仙AI：纯圆老头像（无放射线装饰），避免后台误配导致小图标异常
                $iconUrl = '/images/shenxian-oldman-circle.png';
            }
            $list[] = [
                'id'        => (int) $row['id'],
                'pagePath'  => $row['pagePath'],
                'text'      => $row['text'],
                'iconKey'   => $iconKey,
                'iconUrl'   => $iconUrl,
                'highlight' => (int) ($row['highlight'] ?? 0) === 1,
                'badgeKey'  => $row['badgeKey'] ?? null,
            ];
        }

        // 兜底：后台还没配/表空，返回硬编码 4 项，保证小程序永远可用
        if (empty($list)) {
            $list = [
                ['id' => 0, 'pagePath' => 'pages/index/index',   'text' => '首页',   'iconKey' => 'home',    'iconUrl' => null, 'highlight' => false, 'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/index/camera',  'text' => '拍摄',   'iconKey' => 'camera',  'iconUrl' => null, 'highlight' => true,  'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/ai-chat/index', 'text' => '神仙AI', 'iconKey' => 'ai',      'iconUrl' => '/images/shenxian-oldman-circle.png', 'highlight' => false, 'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/profile/index', 'text' => '我',     'iconKey' => 'profile', 'iconUrl' => null, 'highlight' => false, 'badgeKey' => null],
            ];
        }

        return success([
            'items'    => $list,
            'version'  => (int) Db::name('mp_tabbar_items')->max('updatedAt'),
        ]);
    }
}
