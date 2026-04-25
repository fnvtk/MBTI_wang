<?php
namespace app\common\service;

use app\model\SoulArticle as SoulArticleModel;
use app\model\SystemConfig as SystemConfigModel;
use think\facade\Db;
use think\facade\Log;

/**
 * Soul 文章采集与推荐服务
 *
 * 数据源：一场 soul 创业实验 对外 API（默认 base 见 resolveConfig）
 * 采集方式：仅本服务内 curl HTTP(S) 请求上述接口；不写磁盘拉取、不经 SSH、不连对方服务器 shell
 * 鉴权：system_config.key=`soul_api` 的 {baseUrl, token}；环境变量 SOUL_API_BASE / SOUL_API_TOKEN 兜底
 */
class SoulArticleService
{
    /**
     * 按关键词搜索 Soul 内容并写入本地文章池
     */
    public static function syncByKeyword(string $keyword, int $limit = 10, string $tag = 'MBTI'): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0, 'items' => [], 'error' => '关键词不能为空'];
        }

        $cfg = self::resolveConfig();
        if ($cfg['baseUrl'] === '') {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0, 'items' => [], 'error' => 'soul_api.baseUrl 未配置'];
        }
        $base = rtrim($cfg['baseUrl'], '/');

        $headers = ['Accept: application/json'];
        if ($cfg['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $cfg['token'];
        }

        // 优先尝试通用文章接口（若支持 keyword）
        $url = $base . '/api/articles?' . http_build_query([
            'keyword' => $keyword,
            'limit' => $limit,
            'sort' => 'publishedAt:desc',
        ]);
        [$response] = self::httpGet($url, $headers);
        $data = is_string($response) ? json_decode($response, true) : null;
        $list = [];
        if (is_array($data)) {
            if (isset($data['data']['list']) && is_array($data['data']['list'])) {
                $list = $data['data']['list'];
            } elseif (isset($data['data']) && is_array($data['data']) && isset($data['data'][0])) {
                $list = $data['data'];
            } elseif (isset($data['items']) && is_array($data['items'])) {
                $list = $data['items'];
            } elseif (isset($data[0])) {
                $list = $data;
            }
        }

        // 若无结果，回退到 book API 全量抓取后关键词过滤
        if (empty($list)) {
            $list = self::fetchFromSoulBookApi($base, max($limit * 3, 30), $tag, $keyword);
        }

        return self::upsertArticles($list, $tag, $limit);
    }

    /**
     * 从 Soul 后台拉取最新 N 篇 MBTI 主题文章并写入本地 soul_articles 表
     *
     * @return array ['fetched'=>int, 'created'=>int, 'updated'=>int, 'items'=>[…]]
     */
    public static function syncLatest(int $limit = 10, string $tag = 'MBTI'): array
    {
        $cfg = self::resolveConfig();
        if ($cfg['baseUrl'] === '') {
            return ['fetched' => 0, 'created' => 0, 'updated' => 0, 'items' => [], 'error' => 'soul_api.baseUrl 未配置'];
        }

        $base = rtrim($cfg['baseUrl'], '/');
        $url = $base . '/api/articles';
        $params = [
            'tag'   => $tag,
            'limit' => $limit,
            'sort'  => 'publishedAt:desc',
        ];
        $url .= '?' . http_build_query($params);

        $headers = ['Accept: application/json'];
        if ($cfg['token'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $cfg['token'];
        }

        [$response, $httpCode, $err] = self::httpGet($url, $headers);

        if ($response === false || $response === '') {
            Log::warning("SoulArticleService sync failed: http={$httpCode} err={$err}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $data = [];
        }

        // 兼容常见返回结构：{data:{list:[]}} / {data:[]} / {items:[]}
        $list = [];
        if (isset($data['data']['list']) && is_array($data['data']['list'])) {
            $list = $data['data']['list'];
        } elseif (isset($data['data']) && is_array($data['data']) && isset($data['data'][0])) {
            $list = $data['data'];
        } elseif (isset($data['items']) && is_array($data['items'])) {
            $list = $data['items'];
        } elseif (isset($data[0])) {
            $list = $data;
        }

        // 兼容「一场 soul 创业实验」新接口：/api/miniprogram/book/*
        if (empty($list)) {
            $list = self::fetchFromSoulBookApi($base, $limit, $tag);
        }

        return self::upsertArticles($list, $tag, $limit);
    }

    /**
     * 自动推送（傻瓜模式）：检查 last_sync_at，超过 intervalSec 则自动执行一次采集
     * - 非阻塞：调用方可忽略返回值
     * - 配置：system_config.key='soul_article_auto_sync' {enabled, intervalSec, limit}
     * @return array ['didSync'=>bool, ...syncResult]
     */
    public static function autoSyncIfStale(): array
    {
        try {
            $cfgRow = SystemConfigModel::where('key', 'soul_article_auto_sync')->find();
            $enabled     = true;
            $intervalSec = 3600;
            $limit       = 10;
            if ($cfgRow && !empty($cfgRow->value)) {
                $v = $cfgRow->value;
                if (is_string($v)) {
                    $d = json_decode($v, true);
                    if (is_array($d)) $v = $d;
                }
                if (is_array($v)) {
                    $enabled     = !isset($v['enabled']) ? true : (bool) $v['enabled'];
                    $intervalSec = (int) ($v['intervalSec'] ?? 3600);
                    $limit       = (int) ($v['limit'] ?? 10);
                }
            }
            if (!$enabled) return ['didSync' => false, 'reason' => 'disabled'];

            $lastRow = SystemConfigModel::where('key', 'soul_article_last_sync_at')->find();
            $last    = 0;
            if ($lastRow && !empty($lastRow->value)) {
                $last = is_numeric($lastRow->value) ? (int) $lastRow->value : (int) strtotime($lastRow->value);
            }
            $now = time();
            if ($last > 0 && ($now - $last) < $intervalSec) {
                return ['didSync' => false, 'lastAt' => $last, 'reason' => 'fresh'];
            }

            $r = self::syncLatest($limit, 'MBTI');

            // 写回 last sync（即使采集 0 篇也写回，避免高频重试）
            $val = (string) $now;
            $exist = SystemConfigModel::where('key', 'soul_article_last_sync_at')->find();
            if ($exist) {
                $exist->value = $val;
                $exist->save();
            } else {
                SystemConfigModel::create([
                    'key'         => 'soul_article_last_sync_at',
                    'value'       => $val,
                    'description' => 'Soul 文章最近一次自动采集时间戳',
                ]);
            }
            return array_merge(['didSync' => true, 'lastAt' => $now], $r);
        } catch (\Throwable $e) {
            Log::warning('SoulArticleService autoSync failed: ' . $e->getMessage());
            return ['didSync' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 返回当前已推荐（最多 3 篇）
     */
    public static function getRecommended(int $limit = 3): array
    {
        // 仅返回后台「推荐位」文章，不用候选池补齐（与超管「当前推荐」严格一致）
        $rows = SoulArticleModel::where('isRecommended', 1)
            ->order('recommendedOrder', 'asc')
            ->order('publishedAt', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(function ($r) {
            $pub = (int) ($r['publishedAt'] ?? 0);
            $upd = (int) ($r['updatedAt'] ?? 0);
            // 避免 0 / 无效时间戳被格式化成 1970-01-01
            $dateStr = '';
            if ($pub > 946684800) {
                $dateStr = date('Y-m-d', $pub);
            } elseif ($upd > 946684800) {
                $dateStr = date('Y-m-d', $upd);
            }

            return [
                'id'          => $r['id'],
                'sourceId'    => $r['sourceId'],
                'title'       => $r['title'],
                'cover'       => $r['cover'],
                'url'         => $r['url'],
                'summary'     => $r['summary'],
                'author'      => $r['author'],
                'tag'         => $r['tag'],
                'publishedAt' => $dateStr,
            ];
        }, $rows);
    }

    /**
     * 把某篇文章设为推荐（至多 3 篇；超出时自动顶掉最老的一篇）
     */
    public static function recommend(int $articleId): array
    {
        $article = SoulArticleModel::find($articleId);
        if (!$article) {
            return ['ok' => false, 'message' => '文章不存在'];
        }
        if ($article->isRecommended) {
            $article->isRecommended = 0;
            $article->save();
            return ['ok' => true, 'message' => '已取消推荐', 'isRecommended' => false];
        }

        $current = SoulArticleModel::where('isRecommended', 1)->order('recommendedOrder', 'asc')->select()->toArray();
        if (count($current) >= 3) {
            // 顶掉最老的一篇
            $oldest = end($current);
            $old = SoulArticleModel::find($oldest['id']);
            if ($old) {
                $old->isRecommended = 0;
                $old->save();
            }
        }

        $maxOrder = (int) SoulArticleModel::where('isRecommended', 1)->max('recommendedOrder');
        $article->isRecommended = 1;
        $article->recommendedOrder = $maxOrder + 1;
        $article->save();

        return ['ok' => true, 'message' => '已设为推荐', 'isRecommended' => true];
    }

    /**
     * soul_api 配置：system_config.key='soul_api' / env 兜底
     */
    private static function resolveConfig(): array
    {
        $baseUrl = trim((string) getenv('SOUL_API_BASE'));
        $token   = trim((string) getenv('SOUL_API_TOKEN'));

        $row = SystemConfigModel::where('key', 'soul_api')->find();
        if ($row && !empty($row->value)) {
            $v = $row->value;
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) $v = $decoded;
            }
            if (is_array($v)) {
                if (!empty($v['baseUrl'])) $baseUrl = (string) $v['baseUrl'];
                if (!empty($v['token']))   $token   = (string) $v['token'];
            }
        }

        if ($baseUrl === '') {
            $baseUrl = 'https://soulapi.quwanzhi.com';
        }

        return ['baseUrl' => $baseUrl, 'token' => $token];
    }

    /**
     * 从 Soul 小程序图书接口拉取最新章节，映射成文章池结构（用于 /api/articles 不可用时兜底）
     */
    private static function fetchFromSoulBookApi(string $baseUrl, int $limit, string $tag, string $keyword = ''): array
    {
        $partsUrl = rtrim($baseUrl, '/') . '/api/miniprogram/book/parts';
        [$rawParts] = self::httpGet($partsUrl, ['Accept: application/json']);
        $partsData = is_string($rawParts) ? json_decode($rawParts, true) : null;
        if (!is_array($partsData)) return [];
        $parts = $partsData['parts'] ?? $partsData['data'] ?? [];
        if (!is_array($parts) || empty($parts)) return [];

        $chapters = [];
        foreach ($parts as $p) {
            $partId = (string) ($p['id'] ?? '');
            if ($partId === '') continue;
            $chaptersUrl = rtrim($baseUrl, '/') . '/api/miniprogram/book/chapters-by-part?partId=' . rawurlencode($partId);
            [$rawRows] = self::httpGet($chaptersUrl, ['Accept: application/json']);
            $rowsData = is_string($rawRows) ? json_decode($rawRows, true) : null;
            if (!is_array($rowsData)) continue;
            $rows = $rowsData['data'] ?? $rowsData['list'] ?? [];
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');
                $title = trim((string) ($row['sectionTitle'] ?? $row['title'] ?? ''));
                if ($id === '' || $title === '') continue;
                // 只抓 MBTI 相关，减少噪音；标题无 MBTI 时保留“第x场”高频经营内容
                $lc = mb_strtolower($title, 'UTF-8');
                if (mb_strpos($lc, 'mbti') === false && mb_strpos($title, '第') === false) {
                    continue;
                }
                if ($keyword !== '') {
                    $kw = mb_strtolower($keyword, 'UTF-8');
                    $summary = trim((string) ($row['summary'] ?? ''));
                    $haystack = mb_strtolower($title . ' ' . $summary, 'UTF-8');
                    if (mb_strpos($haystack, $kw) === false) {
                        continue;
                    }
                }
                $publishedAt = 0;
                $pubRaw = $row['updatedAt'] ?? ($row['createdAt'] ?? 0);
                if (is_numeric($pubRaw)) {
                    $publishedAt = (int) $pubRaw;
                } elseif (is_string($pubRaw) && trim($pubRaw) !== '') {
                    $t = strtotime((string) $pubRaw);
                    if ($t) $publishedAt = (int) $t;
                }
                // 异常时间戳（如 0 / 1970）兜底当前时间
                if ($publishedAt < 946684800) {
                    $publishedAt = time();
                }
                $cover = (string) ($row['coverUrl'] ?? $row['cover'] ?? '');
                $summary = trim((string) ($row['summary'] ?? ''));
                $chapters[] = [
                    'id' => $id,
                    'title' => $title,
                    'url' => rtrim($baseUrl, '/') . '/read/' . rawurlencode($id),
                    'cover' => $cover,
                    'summary' => $summary,
                    'author' => '一场 soul 创业实验',
                    'tag' => $tag,
                    'publishedAt' => $publishedAt ?: time(),
                ];
            }
        }

        usort($chapters, function ($a, $b) {
            return (int)($b['publishedAt'] ?? 0) <=> (int)($a['publishedAt'] ?? 0);
        });
        if (count($chapters) > $limit) {
            $chapters = array_slice($chapters, 0, $limit);
        }
        return $chapters;
    }

    /**
     * 统一写入文章池（幂等按 sourceId）
     */
    private static function upsertArticles(array $list, string $tag, int $limit): array
    {
        $created = 0;
        $updated = 0;
        $items   = [];
        $now     = time();

        if (!empty($list) && count($list) > $limit) {
            $list = array_slice($list, 0, $limit);
        }

        foreach ($list as $raw) {
            $sourceId = (string) ($raw['id'] ?? $raw['sourceId'] ?? $raw['articleId'] ?? '');
            $title    = trim((string) ($raw['title'] ?? ''));
            $url2     = trim((string) ($raw['url'] ?? $raw['link'] ?? $raw['shareUrl'] ?? ''));
            if ($sourceId === '' || $title === '' || $url2 === '') {
                continue;
            }

            $cover   = trim((string) ($raw['cover'] ?? $raw['coverUrl'] ?? $raw['thumbnail'] ?? ''));
            $summary = trim((string) ($raw['summary'] ?? $raw['excerpt'] ?? $raw['description'] ?? ''));
            $author  = trim((string) ($raw['author'] ?? ($raw['authorName'] ?? '')));
            $publishedAt = 0;
            $pub = $raw['publishedAt'] ?? ($raw['createdAt'] ?? null);
            if (is_numeric($pub)) {
                $publishedAt = (int) $pub > 2000000000 ? (int)($pub / 1000) : (int) $pub;
            } elseif (is_string($pub) && $pub !== '') {
                $t = strtotime($pub);
                if ($t) $publishedAt = $t;
            }

            if ($publishedAt < 946684800) {
                $publishedAt = $now;
            }

            $payload = [
                'title'       => mb_substr($title, 0, 250, 'UTF-8'),
                'cover'       => $cover,
                'url'         => $url2,
                'summary'     => mb_substr($summary, 0, 500, 'UTF-8'),
                'author'      => mb_substr($author, 0, 60, 'UTF-8'),
                'tag'         => $tag,
                'publishedAt' => $publishedAt ?: $now,
            ];

            $existed = SoulArticleModel::where('sourceId', $sourceId)->find();
            if ($existed) {
                $existed->save($payload);
                $updated++;
            } else {
                $payload['sourceId'] = $sourceId;
                SoulArticleModel::create($payload);
                $created++;
            }
            $items[] = array_merge(['sourceId' => $sourceId], $payload);
        }

        return ['fetched' => count($list), 'created' => $created, 'updated' => $updated, 'items' => $items];
    }

    /**
     * 统一 HTTP GET（返回 body/httpCode/error）
     */
    private static function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        return [$response, $httpCode, $err];
    }
}
