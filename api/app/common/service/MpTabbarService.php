<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 小程序底部 TabBar 配置（与 MpConfig::tabbar 同源，供 runtime 一并下发）
 */
class MpTabbarService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, version: int}
     */
    public static function getPayload(): array
    {
        $list = [];
        try {
            $items = Db::name('mp_tabbar_items')
                ->where('visible', 1)
                ->order('sortOrder', 'asc')
                ->order('id', 'asc')
                ->select()
                ->toArray();

            foreach ($items as $row) {
                $iconKey = $row['iconKey'] ?? 'home';
                $iconUrl = $row['iconUrl'] ?? null;
                if ($iconKey === 'ai') {
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
        } catch (\Throwable $e) {
            $list = [];
        }

        if (empty($list)) {
            $list = [
                ['id' => 0, 'pagePath' => 'pages/index/index',   'text' => '首页',   'iconKey' => 'home',    'iconUrl' => null, 'highlight' => false, 'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/index/camera',  'text' => '拍摄',   'iconKey' => 'camera',  'iconUrl' => null, 'highlight' => true,  'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/ai-chat/index', 'text' => '神仙AI', 'iconKey' => 'ai',      'iconUrl' => '/images/shenxian-oldman-circle.png', 'highlight' => false, 'badgeKey' => null],
                ['id' => 0, 'pagePath' => 'pages/profile/index', 'text' => '我',     'iconKey' => 'profile', 'iconUrl' => null, 'highlight' => false, 'badgeKey' => null],
            ];
        }

        $version = 0;
        try {
            $version = (int) Db::name('mp_tabbar_items')->max('updatedAt');
        } catch (\Throwable $e) {
        }

        return [
            'items'   => $list,
            'version' => $version,
        ];
    }
}
