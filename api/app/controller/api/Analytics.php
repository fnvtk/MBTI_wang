<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\JwtService;
use think\facade\Db;
use think\facade\Request;

/**
 * 小程序埋点上报（可匿名；带 token 时关联用户）
 * POST /api/analytics/events
 */
class Analytics extends BaseController
{
    public function batch()
    {
        $body = Request::post();
        $events = $body['events'] ?? [];
        if (!is_array($events) || count($events) === 0) {
            return success(['accepted' => 0], 'ok');
        }
        if (count($events) > 50) {
            return error('单次最多 50 条', 400);
        }

        $userId = null;
        $openid = null;
        $token = JwtService::getTokenFromRequest($this->request);
        if ($token) {
            $payload = JwtService::verifyToken($token);
            if ($payload && ($payload['source'] ?? '') === 'wechat') {
                $userId = (int) ($payload['userId'] ?? $payload['user_id'] ?? 0) ?: null;
            }
        }

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($events as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $name = isset($ev['event_name']) ? trim((string) $ev['event_name']) : '';
            if ($name === '' || strlen($name) > 128) {
                continue;
            }
            $pagePath = isset($ev['page_path']) ? mb_substr(trim((string) $ev['page_path']), 0, 255) : '';
            $props = $ev['props'] ?? null;
            $propsJson = null;
            if ($props !== null && $props !== []) {
                $propsJson = json_encode($props, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if (strlen($propsJson) > 8000) {
                    $propsJson = mb_substr($propsJson, 0, 8000);
                }
            }
            $clientTs = isset($ev['client_ts']) ? (int) $ev['client_ts'] : null;
            $rowOpenid = null;
            if (!$userId && isset($ev['openid'])) {
                $rowOpenid = mb_substr(trim((string) $ev['openid']), 0, 64) ?: null;
            }
            $rows[] = [
                'userId'    => $userId,
                'openid'    => $rowOpenid,
                'eventName' => $name,
                'pagePath'  => $pagePath ?: null,
                'propsJson' => $propsJson,
                'clientTs'  => $clientTs ?: null,
                'createdAt' => $now,
            ];
        }
        if (count($rows) === 0) {
            return success(['accepted' => 0], 'ok');
        }
        try {
            Db::name('analytics_events')->insertAll($rows);
        } catch (\Throwable $e) {
            // 表未创建时不抛 500，避免小程序端刷屏；超管端「小程序埋点」会提示建表 SQL
            return success(['accepted' => 0, 'skipped' => true], 'ok');
        }
        return success(['accepted' => count($rows)], 'ok');
    }
}
