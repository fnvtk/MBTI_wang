<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 超管 · 小程序 TabBar 配置管理
 */
class MpTabBar extends BaseController
{
    public function index()
    {
        $this->ensureSuperadmin();
        $list = Db::name('mp_tabbar_items')
            ->order('sortOrder', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return success(['list' => $list]);
    }

    /**
     * POST /superadmin/tabbar/save  整体覆盖保存
     * body: { items: [{id?, sortOrder, pagePath, text, iconKey, highlight, visible, badgeKey}] }
     */
    public function save()
    {
        $this->ensureSuperadmin();
        $items = Request::param('items/a', []);
        if (!is_array($items) || count($items) === 0) {
            return error('items 不能为空');
        }
        if (count($items) > 5) {
            return error('最多支持 5 个 Tab');
        }

        $visibleCount = 0;
        foreach ($items as $it) {
            if ((int) ($it['visible'] ?? 1) === 1) $visibleCount++;
        }
        if ($visibleCount < 2) {
            return error('至少保留 2 个可见 Tab');
        }
        if ($visibleCount > 5) {
            return error('可见 Tab 不得超过 5 个');
        }

        $now = time();
        Db::startTrans();
        try {
            $keepIds = [];
            foreach ($items as $idx => $it) {
                $data = [
                    'sortOrder' => (int) ($it['sortOrder'] ?? ($idx + 1) * 10),
                    'pagePath'  => trim((string) ($it['pagePath'] ?? '')),
                    'text'      => trim((string) ($it['text'] ?? '')),
                    'iconKey'   => trim((string) ($it['iconKey'] ?? 'home')),
                    'iconUrl'   => isset($it['iconUrl']) && $it['iconUrl'] !== '' ? (string) $it['iconUrl'] : null,
                    'visible'   => (int) ($it['visible'] ?? 1) === 1 ? 1 : 0,
                    'highlight' => (int) ($it['highlight'] ?? 0) === 1 ? 1 : 0,
                    'badgeKey'  => isset($it['badgeKey']) ? (string) $it['badgeKey'] : null,
                    'updatedAt' => $now,
                ];
                if ($data['pagePath'] === '' || $data['text'] === '') {
                    throw new \Exception('pagePath / text 不能为空');
                }

                if (!empty($it['id'])) {
                    Db::name('mp_tabbar_items')->where('id', (int) $it['id'])->update($data);
                    $keepIds[] = (int) $it['id'];
                } else {
                    $data['createdAt'] = $now;
                    $newId = Db::name('mp_tabbar_items')->insertGetId($data);
                    $keepIds[] = (int) $newId;
                }
            }
            // 删除不在本次保存列表中的旧行
            Db::name('mp_tabbar_items')->whereNotIn('id', $keepIds)->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return error('保存失败：' . $e->getMessage());
        }

        return success(null, '已保存 ' . count($items) . ' 个 Tab 项');
    }

    /**
     * POST /superadmin/tabbar/reorder  仅排序
     * body: { ids: [id1,id2,id3,...] }
     */
    public function reorder()
    {
        $this->ensureSuperadmin();
        $ids = Request::param('ids/a', []);
        if (!is_array($ids) || empty($ids)) return error('ids 不能为空');
        $now = time();
        foreach ($ids as $idx => $id) {
            Db::name('mp_tabbar_items')->where('id', (int) $id)->update([
                'sortOrder' => ($idx + 1) * 10,
                'updatedAt' => $now,
            ]);
        }
        return success(null, '排序已更新');
    }

    public function remove()
    {
        $this->ensureSuperadmin();
        $id = (int) Request::param('id', 0);
        if ($id <= 0) return error('id 非法');
        $left = Db::name('mp_tabbar_items')->where('visible', 1)->where('id', '<>', $id)->count();
        if ($left < 2) return error('至少保留 2 个可见 Tab，不能再删');
        Db::name('mp_tabbar_items')->where('id', $id)->delete();
        return success(null, '已删除');
    }

    private function ensureSuperadmin()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            abort(403, '无权限访问');
        }
    }
}
