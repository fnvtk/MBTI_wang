<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\common\service\AiChatArticleDisplayService;
use app\common\service\SoulArticleService;
use app\model\SoulArticle as SoulArticleModel;
use app\model\AiProvider as AiProviderModel;
use think\facade\Db;
use think\facade\Request;

/**
 * 超管 · Soul 文章管理（采集 / 推荐 / AI 健康概览）
 */
class SoulArticle extends BaseController
{
    /** POST /api/v1/superadmin/soul-articles/sync */
    public function sync()
    {
        $this->ensureSuperadmin();

        $limit = (int) Request::post('limit', 10);
        $limit = max(1, min($limit, 30));
        $tag   = trim((string) Request::post('tag', 'MBTI'));
        $keyword = trim((string) Request::post('keyword', ''));
        if ($tag === '') $tag = 'MBTI';

        $r = $keyword !== ''
            ? SoulArticleService::syncByKeyword($keyword, $limit, $tag)
            : SoulArticleService::syncLatest($limit, $tag);
        if (!empty($r['error'])) {
            return error($r['error'], 500);
        }
        if ($keyword !== '') {
            return success($r, "搜索并添加完成：新增 {$r['created']} 篇，更新 {$r['updated']} 篇");
        }
        return success($r, "采集完成：新增 {$r['created']} 篇，更新 {$r['updated']} 篇");
    }

    /** GET /api/v1/superadmin/soul-articles/ai-chat-display */
    public function aiChatDisplayGet()
    {
        $this->ensureSuperadmin();
        return success(AiChatArticleDisplayService::getSettings());
    }

    /** POST /api/v1/superadmin/soul-articles/ai-chat-display */
    public function aiChatDisplaySave()
    {
        $this->ensureSuperadmin();
        $input    = Request::post();
        $settings = AiChatArticleDisplayService::saveSettings(is_array($input) ? $input : []);
        return success($settings, '已保存');
    }

    /** GET /api/v1/superadmin/soul-articles */
    public function index()
    {
        $this->ensureSuperadmin();

        $page     = max(1, (int) Request::get('page', 1));
        $pageSize = min(50, max(1, (int) Request::get('pageSize', 20)));
        $isReco   = Request::get('isRecommended', '');
        $keyword  = trim((string) Request::get('keyword', ''));
        $tag      = trim((string) Request::get('tag', ''));
        $dateRange = Request::get('dateRange', '');

        $query = SoulArticleModel::order('isRecommended', 'desc')
            ->order('recommendedOrder', 'asc')
            ->order('publishedAt', 'desc');
        if ($isReco === '1' || $isReco === 1) {
            $query = $query->where('isRecommended', 1);
        } elseif ($isReco === '0' || $isReco === 0) {
            $query = $query->where('isRecommended', 0);
        }
        if ($keyword !== '') {
            $query = $query->whereLike('title', "%{$keyword}%");
        }
        if ($tag !== '') {
            $query = $query->where('tag', $tag);
        }
        if (is_array($dateRange) && count($dateRange) === 2) {
            $start = (int) strtotime((string) $dateRange[0] . ' 00:00:00');
            $end = (int) strtotime((string) $dateRange[1] . ' 23:59:59');
            if ($start > 0 && $end > 0) {
                $query = $query->whereBetween('publishedAt', [$start, $end]);
            }
        }

        $total = $query->count();
        $rows  = $query->page($page, $pageSize)->select()->toArray();

        return paginate_response($rows, $total, $page, $pageSize);
    }

    /** POST /api/v1/superadmin/soul-articles/:id/order */
    public function setOrder()
    {
        $this->ensureSuperadmin();
        $id = (int) Request::param('id', 0);
        $order = (int) Request::post('recommendedOrder', 0);
        if ($id <= 0) return error('无效 id', 400);
        if ($order < 0) return error('排序值不合法', 400);
        $article = SoulArticleModel::find($id);
        if (!$article) return error('文章不存在', 404);
        if ((int) $article->isRecommended !== 1) {
            return error('仅推荐中的文章可调整排序', 400);
        }
        $article->recommendedOrder = $order;
        $article->save();
        return success(['id' => $id, 'recommendedOrder' => $order], '排序已更新');
    }

    /** POST /api/v1/superadmin/soul-articles/reorder-normalize */
    public function normalizeOrder()
    {
        $this->ensureSuperadmin();
        $rows = SoulArticleModel::where('isRecommended', 1)
            ->order('recommendedOrder', 'asc')
            ->order('publishedAt', 'desc')
            ->select()
            ->toArray();
        $idx = 1;
        foreach ($rows as $row) {
            $m = SoulArticleModel::find((int) $row['id']);
            if (!$m) continue;
            $m->recommendedOrder = $idx;
            $m->save();
            $idx++;
        }
        return success(['count' => count($rows)], '推荐权重已归一化');
    }

    /** POST /api/v1/superadmin/soul-articles/:id/recommend */
    public function recommend()
    {
        $this->ensureSuperadmin();
        $id = (int) Request::param('id', 0);
        if ($id <= 0) return error('无效 id', 400);

        $r = SoulArticleService::recommend($id);
        if (empty($r['ok'])) {
            return error($r['message'] ?? '操作失败', 400);
        }
        return success($r, $r['message']);
    }

    /** POST /api/v1/superadmin/soul-articles/:id/delete */
    public function remove()
    {
        $this->ensureSuperadmin();
        $id = (int) Request::param('id', 0);
        if ($id <= 0) return error('无效 id', 400);
        $article = SoulArticleModel::find($id);
        if (!$article) return error('文章不存在', 404);
        $article->delete();
        return success(null, '已删除');
    }

    /**
     * GET /api/v1/superadmin/ai/health
     * AI 健康小条：各服务商可用 / 余额 / 最后检查时间
     */
    public function health()
    {
        $this->ensureSuperadmin();

        $rows = AiProviderModel::whereRaw('(visible IS NULL OR visible = 1)')
            ->order('sortWeight', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $balance   = isset($r['lastBalance']) ? (float) $r['lastBalance'] : null;
            $threshold = isset($r['balanceAlertThreshold']) ? (float) $r['balanceAlertThreshold'] : 0.0;
            $alertOn   = (int) ($r['balanceAlertEnabled'] ?? 0) === 1;
            $hasKey    = !empty($r['apiKey']);
            $enabled   = (int) ($r['enabled'] ?? 0) === 1;

            $status = 'unknown';
            if (!$enabled) {
                $status = 'disabled';
            } elseif (!$hasKey) {
                $status = 'no-key';
            } elseif ($alertOn && $balance !== null && $balance <= $threshold) {
                $status = 'low-balance';
            } elseif ($balance !== null) {
                $status = 'healthy';
            } else {
                $status = 'pending-check';
            }

            $list[] = [
                'providerId'           => $r['providerId'],
                'name'                 => $r['name'],
                'enabled'              => $enabled,
                'hasKey'               => $hasKey,
                'balance'              => $balance,
                'currency'             => $r['lastBalanceCurrency'] ?? 'CNY',
                'threshold'            => $threshold,
                'balanceAlertEnabled'  => $alertOn,
                'lastBalanceCheckedAt' => !empty($r['lastBalanceCheckedAt'])
                    ? date('Y-m-d H:i:s', (int) $r['lastBalanceCheckedAt'])
                    : null,
                'sortWeight'           => (int) ($r['sortWeight'] ?? 100),
                'status'               => $status,
            ];
        }

        // 最近一次告警
        $lastAlert = Db::name('ai_balance_alerts')->order('alertedAt', 'desc')->find();
        return success([
            'providers' => $list,
            'lastAlert' => $lastAlert ? [
                'providerId' => $lastAlert['providerId'],
                'balance'    => (float) $lastAlert['balance'],
                'threshold'  => (float) $lastAlert['threshold'],
                'alertedAt'  => date('Y-m-d H:i:s', (int) $lastAlert['alertedAt']),
            ] : null,
        ]);
    }

    private function ensureSuperadmin()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            abort(403, '无权限访问');
        }
    }
}
