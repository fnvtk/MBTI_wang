<?php
namespace app\common\service;

use app\common\PdpDiscResultText;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * 通用 HTTP 出站推送（JSON POST）
 * 配置：system_config key=push_hook_outbound，按 enterprise_id 分行：
 * - enterprise_id=0：全平台默认（超管维护；未配置本企业行时回落至此）
 * - enterprise_id>0：该企业专属；推送时若业务归属该企业且本行「对该事件可用」则优先用本行，否则回落 0
 *
 * 事件：lead.order_paid、lead.phone_bound、test.result_completed
 */
class OutboundPushHookService
{
    public const CONFIG_KEY = 'push_hook_outbound';
    public const ASYNC_ROUTE = '/api/internal/outbound-push/dispatch';

    /** 去重表 scene：出站 Hook（库中 dedupKey 为 _dedupKey 原值，不含 push_hook:） */
    private const DEDUP_SCENE_OUTBOUND = 'outbound_hook';

    /** @var string[] */
    public const DEFAULT_EVENTS = [
        'lead.order_paid',
        'lead.phone_bound',
        'test.result_completed',
    ];

    /**
     * 读取指定作用域配置（不合并回落；回落在 getEffectiveConfigForEvent / dispatch 中处理）
     */
    public static function getConfig(int $enterpriseId = 0): array
    {
        $def = [
            'enabled' => false,
            'url'       => '',
            'secret'    => '',
            'events'    => [],
        ];
        $row = Db::name('system_config')
            ->where('key', self::CONFIG_KEY)
            ->where('enterprise_id', $enterpriseId)
            ->find();
        if (!$row || empty($row['value'])) {
            return $def;
        }
        $v = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
        if (!is_array($v)) {
            return $def;
        }
        $merged = array_merge($def, $v);
        // 兼容历史或误存：events 为 JSON 字符串时转为数组，否则 isEventEnabled 判断异常
        $ev = $merged['events'] ?? [];
        if (is_string($ev)) {
            $decoded = json_decode($ev, true);
            $ev = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($ev)) {
            $ev = [];
        }
        $merged['events'] = $ev;

        return $merged;
    }

    /**
     * 按业务上下文企业 ID 解析实际出站使用的配置（择一：本企业优先，否则全平台）
     *
     * @return array{config: array, configEnterpriseId: int, usedPlatformFallback: bool}|null
     */
    public static function getEffectiveConfigForEvent(int $contextEnterpriseId, string $event): ?array
    {
        if ($contextEnterpriseId > 0) {
            $cfg = self::getConfig($contextEnterpriseId);
            if (self::isEventEnabled($event, $cfg)) {
                return [
                    'config'               => $cfg,
                    'configEnterpriseId'   => $contextEnterpriseId,
                    'usedPlatformFallback' => false,
                ];
            }
        }
        $cfg0 = self::getConfig(0);
        if (self::isEventEnabled($event, $cfg0)) {
            return [
                'config'               => $cfg0,
                'configEnterpriseId'   => 0,
                'usedPlatformFallback' => $contextEnterpriseId > 0,
            ];
        }
        return null;
    }

    /**
     * 仅校验「启用 + 合法 URL」，忽略 events 订阅（用于连接测试）
     *
     * @return array{config: array, configEnterpriseId: int, usedPlatformFallback: bool}|null
     */
    public static function resolveOutboundTransport(int $contextEnterpriseId): ?array
    {
        if ($contextEnterpriseId > 0) {
            $cfg = self::getConfig($contextEnterpriseId);
            if (self::isRowTransportReady($cfg)) {
                return [
                    'config'               => $cfg,
                    'configEnterpriseId'   => $contextEnterpriseId,
                    'usedPlatformFallback' => false,
                ];
            }
        }
        $cfg0 = self::getConfig(0);
        if (self::isRowTransportReady($cfg0)) {
            return [
                'config'               => $cfg0,
                'configEnterpriseId'   => 0,
                'usedPlatformFallback' => $contextEnterpriseId > 0,
            ];
        }
        return null;
    }

    private static function isRowTransportReady(array $cfg): bool
    {
        if (empty($cfg['enabled'])) {
            return false;
        }
        $url = trim((string) ($cfg['url'] ?? ''));
        return $url !== '' && stripos($url, 'http') === 0;
    }

    /** 企业微信机器人 Webhook（text 用 msgtype） */
    private static function isWeComBotWebhookUrl(string $url): bool
    {
        return stripos($url, 'qyapi.weixin.qq.com') !== false;
    }

    /** 飞书 / Lark 自定义机器人（text 用 msg_type，否则报 code=19002 等） */
    private static function isFeishuBotWebhookUrl(string $url): bool
    {
        $u = strtolower($url);
        if (strpos($u, 'qyapi.weixin.qq.com') !== false) {
            return false;
        }

        return (strpos($u, 'open.feishu.cn') !== false || strpos($u, 'open.larksuite.com') !== false)
            && (strpos($u, '/bot/') !== false || strpos($u, 'hook') !== false);
    }

    private static function isThirdPartyBotTextUrl(string $url): bool
    {
        return self::isWeComBotWebhookUrl($url) || self::isFeishuBotWebhookUrl($url);
    }

    /**
     * 企微 / 飞书机器人仅接受各自文本协议，与通用 JSON 信封不同
     */
    private static function buildThirdPartyTextBody(string $url, string $text): string
    {
        if (self::isWeComBotWebhookUrl($url)) {
            return json_encode([
                'msgtype' => 'text',
                'text'    => ['content' => $text],
            ], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'msg_type' => 'text',
            'content'  => ['text' => $text],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将通用 envelope 压成多行纯文本（用于飞书/企微机器人）
     *
     * @param array<string,mixed> $envelope
     */
    private static function envelopeToBotPlainText(string $event, array $envelope): string
    {
        $tenant = isset($envelope['tenant']) && is_array($envelope['tenant']) ? $envelope['tenant'] : [];
        $eid = (int) ($tenant['enterpriseId'] ?? 0);
        $ename = trim((string) ($tenant['enterpriseName'] ?? ''));
        $head = '';
        $payload = isset($envelope['payload']) && is_array($envelope['payload']) ? $envelope['payload'] : [];

        switch ($event) {
            case 'lead.order_paid':
                $paidTime = trim((string) ($payload['paidAt'] ?? ''));
                if ($paidTime === '') {
                    $paidTime = trim((string) ($envelope['occurredAt'] ?? ''));
                }

                return $head . "💰 支付成功\n"
                    . '订单号: ' . ($payload['orderNo'] ?? '') . "\n"
                    . '用户: ' . ($payload['userName'] ?? '') . "\n"
                    . '手机: ' . ($payload['phone'] ?? '') . "\n"
                    . '金额: ¥' . ($payload['amountYuan'] ?? '') . "\n"
                    . '商品: ' . self::truncatePlainText((string) ($payload['productTitle'] ?? ''), 60) . "\n"
                    . '来源: ' . ($payload['sourceLabel'] ?? '') . "\n"
                    . '支付时间: ' . $paidTime;
            case 'lead.phone_bound':
                $boundTime = trim((string) ($payload['boundAt'] ?? ''));
                if ($boundTime === '') {
                    $boundTime = trim((string) ($envelope['occurredAt'] ?? ''));
                }

                return $head . "📋 首次绑定手机\n"
                    . '用户: ' . ($payload['userName'] ?? '') . "\n"
                    . '手机: ' . ($payload['phone'] ?? '') . "\n"
                    . '绑定时间: ' . $boundTime;
            case 'test.result_completed':
                $testTime = trim((string) ($payload['completedAt'] ?? ''));
                if ($testTime === '') {
                    $testTime = trim((string) ($envelope['occurredAt'] ?? ''));
                }

                $tt = (string) ($payload['testType'] ?? '');
                $lines = [
                    $head . "📊 测评完成\n",
                    '类型: ' . ($payload['testTypeLabel'] ?? $payload['testType'] ?? '') . "\n",
                    '用户: ' . ($payload['userName'] ?? '') . "\n",
                    '手机: ' . ($payload['phone'] ?? '') . "\n",
                ];
                if (in_array($tt, ['face', 'ai'], true)) {
                    $mb = trim((string) ($payload['resultMbti'] ?? ''));
                    $pd = trim((string) ($payload['resultPdp'] ?? ''));
                    $di = trim((string) ($payload['resultDisc'] ?? ''));
                    if ($mb !== '') {
                        $lines[] = 'MBTI测试结果: ' . $mb . "\n";
                    }
                    if ($pd !== '') {
                        $lines[] = 'PDP测试结果: ' . $pd . "\n";
                    }
                    if ($di !== '') {
                        $lines[] = 'DISC测试结果: ' . $di . "\n";
                    }
                    if ($mb === '' && $pd === '' && $di === '') {
                        $lines[] = '测试结果: ' . self::truncatePlainText((string) ($payload['resultSummary'] ?? ''), 100) . "\n";
                    }
                } else {
                    $lines[] = '测试结果: ' . self::truncatePlainText((string) ($payload['resultSummary'] ?? ''), 100) . "\n";
                }
                $lines[] = '测试时间: ' . $testTime;
                $body = implode('', $lines);
                $mgmtSummary = trim((string) ($payload['managementSummary'] ?? ''));
                if ($mgmtSummary !== '') {
                    $body .= "\n━━━━━━━━━━\n用户管理:\n" . $mgmtSummary;
                }
                $um = $payload['userManagement'] ?? null;
                if (is_array($um) && !empty($um['openidTail6'])) {
                    $body .= "\nOpenID尾号(脱敏): " . (string) $um['openidTail6'];
                }
                $beh = $payload['recentBehaviors'] ?? [];
                if (is_array($beh) && count($beh) > 0) {
                    $body .= "\n━━━━━━━━━━\n最近行为:";
                    $i = 1;
                    foreach ($beh as $bl) {
                        if (!is_string($bl) || $bl === '') {
                            continue;
                        }
                        $body .= "\n  {$i}. {$bl}";
                        $i++;
                    }
                }

                return $body;
            default:
                return $head . "\n事件: " . $event;
        }
    }

    private static function truncatePlainText(string $s, int $max): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max) . '…';
        }

        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }

    /**
     * 发送一次 `hook.ping` 测试投递（不写去重表）
     * 配置解析与真实「测评完成」推送一致：须满足对 test.result_completed 的事件订阅（空数组表示全部）。
     *
     * @return array<string,mixed>
     */
    public static function sendTestPing(int $contextEnterpriseId = 0): array
    {
        // 与 dispatch 一致，避免「仅测通 URL、但 events 未勾选测评」时误以为已配置
        $resolved = self::getEffectiveConfigForEvent($contextEnterpriseId, 'test.result_completed');
        if ($resolved === null) {
            return [
                'ok'                   => false,
                'message'              => '未找到对「测评完成」(test.result_completed) 有效的配置：请确认已启用、URL 以 http 开头，且「订阅事件」包含该项或留空表示全部（可先保存后重试，并检查全平台默认）',
                'httpStatus'           => 0,
                'configEnterpriseId'   => null,
                'usedPlatformFallback' => null,
                'responsePreview'      => null,
                'businessHint'         => null,
                'curlError'            => null,
            ];
        }
        $cfg = $resolved['config'];
        $url = trim((string) ($cfg['url'] ?? ''));
        $tenantEid = $contextEnterpriseId > 0 ? $contextEnterpriseId : 0;
        $tenant = self::tenantPayload($tenantEid);

        if (self::isThirdPartyBotTextUrl($url)) {
            $lines = "🔔 MBTI 出站 Hook 连接测试\n时间: " . date('Y-m-d H:i:s');
            if ($tenantEid > 0) {
                $lines .= "\n企业: " . (!empty($tenant['enterpriseName']) ? (string) $tenant['enterpriseName'] : ('ID ' . $tenantEid));
            }
            $lines .= "\n（已按飞书/企微机器人文本协议发送，非通用 JSON）";
            $body = self::buildThirdPartyTextBody($url, $lines);
            $headers = ['Content-Type: application/json; charset=utf-8'];
            [$ok, $httpStatus, $responseBody, $curlErr, $bizHint] = self::httpPostJsonWithCode($url, $body, $headers);
        } else {
            $envelope = [
                'event'       => 'hook.ping',
                'occurredAt'  => self::iso8601Cn(),
                'environment' => self::appEnv(),
                'hook'        => [
                    'configEnterpriseId'   => $resolved['configEnterpriseId'],
                    'usedPlatformFallback' => $resolved['usedPlatformFallback'],
                    'test'                 => true,
                ],
                'tenant'      => $tenant,
                'payload'     => [
                    'display' => [
                        'title' => '连接测试',
                        'emoji' => '🔔',
                    ],
                    'message' => 'MBTI 出站 Hook 模拟推送（可忽略业务语义）',
                    'sentAt'  => date('Y-m-d H:i:s'),
                ],
            ];
            $body = json_encode($envelope, JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return [
                    'ok'                   => false,
                    'message'              => 'JSON 编码失败',
                    'httpStatus'           => 0,
                    'configEnterpriseId'   => $resolved['configEnterpriseId'],
                    'usedPlatformFallback' => $resolved['usedPlatformFallback'],
                    'responsePreview'      => null,
                    'businessHint'         => null,
                    'curlError'            => null,
                ];
            }
            $deliveryId = self::uuidV4();
            $headers = [
                'Content-Type: application/json; charset=utf-8',
                'X-MBTI-Event: hook.ping',
                'X-MBTI-Delivery-Id: ' . $deliveryId,
            ];
            $secret = trim((string) ($cfg['secret'] ?? ''));
            if ($secret !== '') {
                $sig = hash_hmac('sha256', $body, $secret);
                $headers[] = 'X-MBTI-Signature: sha256=' . $sig;
            }
            [$ok, $httpStatus, $responseBody, $curlErr, $bizHint] = self::httpPostJsonWithCode($url, $body, $headers);
        }
        $preview = self::truncateForLog($responseBody, 800);
        if ($curlErr !== '') {
            Log::warning('OutboundPushHook test: curl error', ['url' => self::maskUrl($url), 'error' => $curlErr]);
        } elseif ($bizHint !== null) {
            Log::warning('OutboundPushHook test: business not ok', ['url' => self::maskUrl($url), 'hint' => $bizHint, 'preview' => $preview]);
        }

        $msgParts = [];
        if ($curlErr !== '') {
            $msgParts[] = '网络/cURL：' . $curlErr;
        } else {
            $msgParts[] = 'HTTP ' . $httpStatus;
        }
        if ($bizHint !== null) {
            $msgParts[] = '对端业务：' . $bizHint;
        } elseif ($ok) {
            $msgParts[] = '连接与响应体检查通过';
        } else {
            $msgParts[] = '未通过（见 HTTP 状态或业务字段）';
        }

        return [
            'ok'                   => $ok,
            'message'              => implode('；', $msgParts),
            'httpStatus'           => $httpStatus,
            'configEnterpriseId'   => $resolved['configEnterpriseId'],
            'usedPlatformFallback' => $resolved['usedPlatformFallback'],
            'responsePreview'      => $preview !== '' ? $preview : null,
            'businessHint'         => $bizHint,
            'curlError'            => $curlErr !== '' ? $curlErr : null,
        ];
    }

    /**
     * 主业务仅投递内部任务，不等待第三方推送完成，避免拖慢用户接口。
     */
    public static function triggerAsyncOrderPaid(int $orderDbId, int $userId): void
    {
        if ($orderDbId <= 0 || $userId <= 0) {
            return;
        }

        self::triggerAsyncInternalDispatch([
            'job'     => 'lead.order_paid',
            'orderId' => $orderDbId,
            'userId'  => $userId,
        ]);
    }

    /**
     * 测评结果写库后异步回调内部接口，避免在主提交流程里等待外部 Webhook。
     */
    public static function triggerAsyncTestResultCompleted(int $testResultId): void
    {
        if ($testResultId <= 0) {
            return;
        }

        self::triggerAsyncInternalDispatch([
            'job'          => 'test.result_completed',
            'testResultId' => $testResultId,
        ]);
    }

    /**
     * 内部接口验签：仅允许本服务自行投递的异步任务进入。
     */
    public static function verifyAsyncInternalDispatch(string $body, string $timestamp, string $signature): bool
    {
        $ts = ctype_digit($timestamp) ? (int) $timestamp : 0;
        if ($ts <= 0 || abs(time() - $ts) > 300) {
            return false;
        }
        if ($body === '' || $signature === '') {
            return false;
        }

        $expected = self::signAsyncInternalDispatch($body, $timestamp);
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $signature);
        }

        return $expected === $signature;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function triggerAsyncInternalDispatch(array $payload): void
    {
        $url = self::resolveAsyncDispatchUrl();
        if ($url === '') {
            Log::warning('OutboundPushHook async enqueue skipped: no internal url', [
                'payload' => $payload,
            ]);

            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Log::warning('OutboundPushHook async enqueue skipped: json encode failed', [
                'payload' => $payload,
            ]);

            return;
        }

        $timestamp = (string) time();
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'X-MBTI-Internal-Timestamp: ' . $timestamp,
            'X-MBTI-Internal-Signature: ' . self::signAsyncInternalDispatch($body, $timestamp),
        ];

        if (!self::postJsonAsyncNoWait($url, $body, $headers)) {
            Log::warning('OutboundPushHook async enqueue failed', [
                'url'     => self::maskUrl($url),
                'payload' => $payload,
            ]);
        }
    }

    private static function resolveAsyncDispatchUrl(): string
    {
        $host = trim((string) Request::server('HTTP_HOST', ''));
        if ($host !== '') {
            $scheme = Request::isSsl() ? 'https' : 'http';

            return $scheme . '://' . $host . self::ASYNC_ROUTE;
        }

        $appHost = trim((string) config('app.app_host', ''));
        if ($appHost !== '') {
            if (stripos($appHost, 'http://') === 0 || stripos($appHost, 'https://') === 0) {
                return rtrim($appHost, '/') . self::ASYNC_ROUTE;
            }

            return 'https://' . trim($appHost, '/') . self::ASYNC_ROUTE;
        }

        return '';
    }

    private static function signAsyncInternalDispatch(string $body, string $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $body, self::asyncInternalDispatchSecret());
    }

    private static function asyncInternalDispatchSecret(): string
    {
        $secret = (string) (env('jwt.secret', '') ?: getenv('JWT_SECRET') ?: '');

        return $secret !== '' ? $secret : 'mbti-outbound-push';
    }

    /**
     * fire-and-forget 异步 POST：只负责把请求投出去，不等待接口处理完成。
     *
     * @param array<int,string> $headers
     */
    private static function postJsonAsyncNoWait(string $url, string $body, array $headers): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $path = (string) ($parts['path'] ?? '/');
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        $transport = $scheme === 'https' ? 'ssl://' : '';
        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 1);
        if (!is_resource($socket)) {
            Log::warning('OutboundPushHook async socket open failed', [
                'url'   => self::maskUrl($url),
                'errno' => $errno,
                'error' => $errstr,
            ]);

            return false;
        }

        stream_set_timeout($socket, 1);
        $hostHeader = $host;
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $hostHeader .= ':' . $port;
        }

        $requestLines = [
            'POST ' . $path . ' HTTP/1.1',
            'Host: ' . $hostHeader,
            'Connection: Close',
            'Content-Length: ' . strlen($body),
        ];
        foreach ($headers as $header) {
            $requestLines[] = $header;
        }

        $rawRequest = implode("\r\n", $requestLines) . "\r\n\r\n" . $body;
        $written = @fwrite($socket, $rawRequest);
        @fclose($socket);

        return $written !== false;
    }

    /**
     * 将库表中的 enterpriseId 规范为 int（null/''/0 视为无）
     */
    private static function intEnterpriseId($v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        $n = (int) $v;

        return $n > 0 ? $n : 0;
    }

    /**
     * 测评行可能未写 enterpriseId（null），但用户已绑定企业：与 CrmReport 等一致，回落 wechat_users.enterpriseId
     *
     * @param array<string,mixed>        $testResultRow
     * @param array<string,mixed>|null $wechatUser      wechat_users 一行，须含 enterpriseId（可与昵称查询合并）
     */
    private static function resolveEnterpriseIdForTestResult(array $testResultRow, ?array $wechatUser): int
    {
        $fromRow = self::intEnterpriseId($testResultRow['enterpriseId'] ?? null);
        if ($fromRow > 0) {
            return $fromRow;
        }
        if ($wechatUser !== null) {
            $fromUser = self::intEnterpriseId($wechatUser['enterpriseId'] ?? null);
            if ($fromUser > 0) {
                return $fromUser;
            }
        }

        return 0;
    }

    /**
     * 测评完成 JSON / 飞书文本：附加用户管理字段 + analytics 用户旅程（与 FeishuLeadWebhookService 获客卡片「最近行为」同源）
     *
     * @param array<string,mixed>        $payload   引用
     * @param array<string,mixed>        $testResultRow test_results 一行
     * @param array<string,mixed>|null $wuArr     wechat_users 行（可含 openid）
     */
    private static function mergeTestResultUserJourneyPayload(array &$payload, int $userId, array $testResultRow, ?array $wuArr): void
    {
        if ($userId <= 0) {
            $payload['recentBehaviors'] = [];
            $payload['userManagement'] = [
                'wechatUserId'   => 0,
                'enterpriseId'   => 0,
                'enterpriseName' => null,
                'openidTail6'    => null,
                'testScope'      => (string) ($testResultRow['testScope'] ?? ''),
            ];
            $payload['managementSummary'] = '';

            return;
        }
        $eidResolved = self::resolveEnterpriseIdForTestResult($testResultRow, $wuArr);
        $tenant = self::tenantPayload($eidResolved);
        $openid = '';
        if ($wuArr !== null && isset($wuArr['openid'])) {
            $openid = trim((string) $wuArr['openid']);
        }
        $tail = '';
        if ($openid !== '') {
            $tail = strlen($openid) >= 6 ? substr($openid, -6) : $openid;
        }
        $payload['userManagement'] = [
            'wechatUserId'   => $userId,
            'enterpriseId'   => $eidResolved,
            'enterpriseName' => $tenant['enterpriseName'],
            'openidTail6'    => $tail !== '' ? $tail : null,
            'testScope'      => (string) ($testResultRow['testScope'] ?? ''),
        ];
        $payload['recentBehaviors'] = UserJourneyService::recentBehaviorLines($userId, 12);
        $trEid = (int) ($testResultRow['enterpriseId'] ?? 0);
        $payload['managementSummary'] = UserJourneyService::managementSummaryLine($userId, $trEid);
    }

    public static function isEventEnabled(string $event, array $cfg): bool
    {
        if (empty($cfg['enabled'])) {
            return false;
        }
        $url = trim((string) ($cfg['url'] ?? ''));
        if ($url === '' || stripos($url, 'http') !== 0) {
            return false;
        }
        $ev = $cfg['events'] ?? [];
        if (!is_array($ev) || count($ev) === 0) {
            return true;
        }
        return in_array($event, $ev, true);
    }

    /**
     * 支付成功：与 FeishuLeadWebhookService::onOrderPaid 同路径触发
     */
    public static function onOrderPaid(int $orderDbId, int $userId): void
    {
        if ($orderDbId <= 0 || $userId <= 0) {
            return;
        }
        $order = Db::name('orders')->where('id', $orderDbId)->find();
        if (!$order) {
            return;
        }
        $productType = (string) ($order['productType'] ?? '');
        $amountFen = (int) ($order['amount'] ?? 0);
        $title = (string) ($order['productTitle'] ?? '');
        $sourceLabel = FeishuLeadWebhookService::sourceLabelForOrder($productType, $title, $amountFen);
        $payTs = isset($order['payTime']) ? (int) $order['payTime'] : time();
        $paidAt = date('Y-m-d H:i:s', $payTs);
        $wu = Db::name('wechat_users')->where('id', $userId)->field('nickname,phone')->find();
        $userName = trim((string) ($wu['nickname'] ?? ''));
        if ($userName === '') {
            $userName = '微信用户';
        }
        $phone = trim((string) ($wu['phone'] ?? ''));
        $eid = isset($order['enterpriseId']) ? (int) $order['enterpriseId'] : 0;
        $tenant = self::tenantPayload($eid);

        $payload = [
            'display'      => [
                'title' => '用户购买成功（实时推送）',
                'emoji' => '💰',
            ],
            'orderId'      => (int) $order['id'],
            'orderNo'      => (string) ($order['orderNo'] ?? ''),
            'userId'       => $userId,
            'userName'     => $userName,
            'phone'        => $phone,
            'productTitle' => $title,
            'productType'  => $productType,
            'amountYuan'   => number_format($amountFen / 100, 2, '.', ''),
            'amountFen'    => $amountFen,
            'status'       => (string) ($order['status'] ?? 'paid'),
            'paidAt'       => $paidAt,
            'sourceLabel'  => $sourceLabel,
        ];

        self::dispatch('lead.order_paid', [
            'event'       => 'lead.order_paid',
            'occurredAt'  => self::iso8601Cn(),
            'environment' => self::appEnv(),
            'tenant'      => $tenant,
            'payload'     => $payload,
            '_dedupKey'   => 'lead.order_paid:' . $orderDbId,
        ], $eid);
    }

    public static function onPhoneBound(int $userId, string $phone): void
    {
        if ($userId <= 0 || trim($phone) === '') {
            return;
        }
        $wu = Db::name('wechat_users')->where('id', $userId)->field('nickname,enterpriseId')->find();
        $userName = trim((string) ($wu['nickname'] ?? ''));
        if ($userName === '') {
            $userName = '微信用户';
        }
        $eid = isset($wu['enterpriseId']) ? (int) $wu['enterpriseId'] : 0;

        self::dispatch('lead.phone_bound', [
            'event'       => 'lead.phone_bound',
            'occurredAt'  => self::iso8601Cn(),
            'environment' => self::appEnv(),
            'tenant'      => self::tenantPayload($eid),
            'payload'     => [
                'display' => [
                    'title' => '用户完成手机号授权',
                    'emoji' => '📋',
                ],
                'userId'   => $userId,
                'userName' => $userName,
                'phone'    => $phone,
                'boundAt'  => date('Y-m-d H:i:s'),
                'sourceLabel' => '测试完成·授权手机号',
            ],
            '_dedupKey' => 'lead.phone_bound:' . $userId,
        ], $eid);
    }

    /**
     * 测评记录落库后推送（问卷 submit / 分析写库）
     */
    public static function onTestResultCompleted(int $testResultId): void
    {
        if ($testResultId <= 0) {
            return;
        }
        $row = Db::name('test_results')->where('id', $testResultId)->find();
        if (!$row) {
            return;
        }
        $userId = (int) ($row['userId'] ?? 0);
        $testType = (string) ($row['testType'] ?? '');
        $createdAt = isset($row['createdAt']) ? (int) $row['createdAt'] : time();
        $completedAt = date('Y-m-d H:i:s', $createdAt);

        $wu = $userId > 0
            ? Db::name('wechat_users')->where('id', $userId)->field('nickname,phone,enterpriseId,openid')->find()
            : null;
        $userName = $wu ? trim((string) ($wu['nickname'] ?? '')) : '';
        if ($userName === '') {
            $userName = '微信用户';
        }
        $phone = $wu ? trim((string) ($wu['phone'] ?? '')) : '';

        $raw = $row['resultData'] ?? null;
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            $data = [];
        }

        $summary = self::formatTestResultSummary($testType, $data);
        $label = self::testTypeLabel($testType);

        $wuArr = null;
        if ($wu !== null) {
            if (is_array($wu)) {
                $wuArr = $wu;
            } elseif (is_object($wu) && method_exists($wu, 'toArray')) {
                $wuArr = $wu->toArray();
            }
        }
        $eid = self::resolveEnterpriseIdForTestResult($row, $wuArr);

        $payload = [
            'display' => [
                'title' => '用户测评完成（实时推送）',
                'emoji' => '📊',
            ],
            'testResultId'   => $testResultId,
            'userId'         => $userId,
            'userName'       => $userName,
            'phone'          => $phone,
            'testType'       => $testType,
            'testTypeLabel'  => $label,
            'resultSummary'  => $summary,
            'completedAt'    => $completedAt,
        ];
        if (in_array($testType, ['face', 'ai'], true)) {
            $dims = self::buildFaceAiBotDimensions($data);
            if ($dims['mbti'] !== '') {
                $payload['resultMbti'] = $dims['mbti'];
            }
            if ($dims['pdp'] !== '') {
                $payload['resultPdp'] = $dims['pdp'];
            }
            if ($dims['disc'] !== '') {
                $payload['resultDisc'] = $dims['disc'];
            }
        }
        self::mergeTestResultUserJourneyPayload($payload, $userId, $row, $wuArr);

        self::dispatch('test.result_completed', [
            'event'       => 'test.result_completed',
            'occurredAt'  => self::iso8601Cn(),
            'environment' => self::appEnv(),
            'tenant'      => self::tenantPayload($eid),
            'payload'     => $payload,
            '_dedupKey' => 'test.result_completed:' . $testResultId,
        ], $eid);
    }

    /**
     * 管理端调试入口：按真实业务数据重放 test.result_completed，可选强制清去重后再发。
     *
     * @return array<string,mixed>
     */
    public static function replayTestResultForDebug(int $testResultId, bool $force = false): array
    {
        if ($testResultId <= 0) {
            return [
                'ok'      => false,
                'status'  => 'invalid',
                'message' => 'testResultId 非法',
            ];
        }

        $row = Db::name('test_results')->where('id', $testResultId)->find();
        if (!$row) {
            return [
                'ok'           => false,
                'status'       => 'not_found',
                'message'      => '测试记录不存在',
                'testResultId' => $testResultId,
            ];
        }

        $userId = (int) ($row['userId'] ?? 0);
        $testType = (string) ($row['testType'] ?? '');
        $createdAt = isset($row['createdAt']) ? (int) $row['createdAt'] : time();
        $completedAt = date('Y-m-d H:i:s', $createdAt);

        $wu = $userId > 0
            ? Db::name('wechat_users')->where('id', $userId)->field('nickname,phone,enterpriseId,openid')->find()
            : null;
        $userName = $wu ? trim((string) ($wu['nickname'] ?? '')) : '';
        if ($userName === '') {
            $userName = '微信用户';
        }
        $phone = $wu ? trim((string) ($wu['phone'] ?? '')) : '';

        $raw = $row['resultData'] ?? null;
        $data = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($data)) {
            $data = [];
        }

        $summary = self::formatTestResultSummary($testType, $data);
        $label = self::testTypeLabel($testType);

        $wuArr = null;
        if ($wu !== null) {
            if (is_array($wu)) {
                $wuArr = $wu;
            } elseif (is_object($wu) && method_exists($wu, 'toArray')) {
                $wuArr = $wu->toArray();
            }
        }
        $eid = self::resolveEnterpriseIdForTestResult($row, $wuArr);

        $payload = [
            'display' => [
                'title' => '用户测评完成（实时推送）',
                'emoji' => '📊',
            ],
            'testResultId'   => $testResultId,
            'userId'         => $userId,
            'userName'       => $userName,
            'phone'          => $phone,
            'testType'       => $testType,
            'testTypeLabel'  => $label,
            'resultSummary'  => $summary,
            'completedAt'    => $completedAt,
        ];
        if (in_array($testType, ['face', 'ai'], true)) {
            $dims = self::buildFaceAiBotDimensions($data);
            if ($dims['mbti'] !== '') {
                $payload['resultMbti'] = $dims['mbti'];
            }
            if ($dims['pdp'] !== '') {
                $payload['resultPdp'] = $dims['pdp'];
            }
            if ($dims['disc'] !== '') {
                $payload['resultDisc'] = $dims['disc'];
            }
        }
        self::mergeTestResultUserJourneyPayload($payload, $userId, $row, $wuArr);

        return self::dispatchDetailed('test.result_completed', [
            'event'       => 'test.result_completed',
            'occurredAt'  => self::iso8601Cn(),
            'environment' => self::appEnv(),
            'tenant'      => self::tenantPayload($eid),
            'payload'     => $payload,
            '_dedupKey' => 'test.result_completed:' . $testResultId,
        ], $eid, $force);
    }

    /**
     * @param array<string,mixed> $envelope 须含 event、payload，可选 _dedupKey
     * @param int                   $contextEnterpriseId 业务归属企业（订单/用户/测评行上的 enterpriseId，无则 0）
     */
    public static function dispatch(string $event, array $envelope, int $contextEnterpriseId = 0): void
    {
        self::dispatchDetailed($event, $envelope, $contextEnterpriseId, false);
    }

    /**
     * @param array<string,mixed> $envelope
     * @return array<string,mixed>
     */
    private static function dispatchDetailed(string $event, array $envelope, int $contextEnterpriseId = 0, bool $force = false): array
    {
        $rawDedupKey = (string) ($envelope['_dedupKey'] ?? '');
        $fullDedupKey = $rawDedupKey !== '' ? 'push_hook:' . $rawDedupKey : '';

        if ($force && $rawDedupKey !== '') {
            self::rollbackDedup($rawDedupKey);
        }

        $resolved = self::getEffectiveConfigForEvent($contextEnterpriseId, $event);
        if ($resolved === null) {
            Log::warning('OutboundPushHook skipped: no effective config', [
                'event'                 => $event,
                'contextEnterpriseId'   => $contextEnterpriseId,
                'hint'                  => '检查本企业与全平台行的 enabled、url，以及 events 是否包含该事件（空数组表示全部）',
            ]);

            return [
                'ok'                   => false,
                'status'               => 'no_config',
                'message'              => '未找到对此事件有效的 Hook 配置',
                'event'                => $event,
                'contextEnterpriseId'  => $contextEnterpriseId,
                'configEnterpriseId'   => null,
                'usedPlatformFallback' => null,
                'dedupKey'             => $fullDedupKey !== '' ? $fullDedupKey : null,
                'forced'               => $force,
            ];
        }
        $cfg = $resolved['config'];

        $dedupKey = (string) ($envelope['_dedupKey'] ?? '');
        unset($envelope['_dedupKey']);

        if ($dedupKey !== '' && !self::beginDedup($dedupKey)) {
            Log::warning('OutboundPushHook skipped: dedup duplicate', [
                'event'               => $event,
                'contextEnterpriseId' => $contextEnterpriseId,
                'dedupKey'            => $dedupKey,
            ]);

            return [
                'ok'                   => false,
                'status'               => 'duplicate',
                'message'              => '命中去重，已跳过发送',
                'event'                => $event,
                'contextEnterpriseId'  => $contextEnterpriseId,
                'configEnterpriseId'   => $resolved['configEnterpriseId'],
                'usedPlatformFallback' => $resolved['usedPlatformFallback'],
                'dedupKey'             => 'push_hook:' . $dedupKey,
                'forced'               => $force,
            ];
        }

        $envelope['hook'] = [
            'configEnterpriseId'   => $resolved['configEnterpriseId'],
            'usedPlatformFallback' => $resolved['usedPlatformFallback'],
        ];

        $url = trim((string) ($cfg['url'] ?? ''));
        $httpStatus = 0;
        $respBody = '';
        $curlErr = '';
        $bizHint = null;

        if (self::isThirdPartyBotTextUrl($url)) {
            $plain = self::envelopeToBotPlainText($event, $envelope);
            $body = self::buildThirdPartyTextBody($url, $plain);
            $headers = ['Content-Type: application/json; charset=utf-8'];
            [$ok, $httpStatus, $respBody, $curlErr, $bizHint] = self::httpPostJsonWithCode($url, $body, $headers);
        } else {
            $body = json_encode($envelope, JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                if ($dedupKey !== '') {
                    self::rollbackDedup($dedupKey);
                }

                return [
                    'ok'                   => false,
                    'status'               => 'json_encode_failed',
                    'message'              => 'JSON 编码失败',
                    'event'                => $event,
                    'contextEnterpriseId'  => $contextEnterpriseId,
                    'configEnterpriseId'   => $resolved['configEnterpriseId'],
                    'usedPlatformFallback' => $resolved['usedPlatformFallback'],
                    'dedupKey'             => $dedupKey !== '' ? ('push_hook:' . $dedupKey) : null,
                    'forced'               => $force,
                ];
            }

            $deliveryId = self::uuidV4();
            $headers = [
                'Content-Type: application/json; charset=utf-8',
                'X-MBTI-Event: ' . $event,
                'X-MBTI-Delivery-Id: ' . $deliveryId,
            ];
            $secret = trim((string) ($cfg['secret'] ?? ''));
            if ($secret !== '') {
                $sig = hash_hmac('sha256', $body, $secret);
                $headers[] = 'X-MBTI-Signature: sha256=' . $sig;
            }

            [$ok, $httpStatus, $respBody, $curlErr, $bizHint] = self::httpPostJsonWithCode($url, $body, $headers);
        }
        if (!$ok) {
            Log::warning('OutboundPushHook dispatch failed', [
                'event'   => $event,
                'url'     => self::maskUrl($url),
                'curl'    => $curlErr,
                'biz'     => $bizHint,
                'preview' => self::truncateForLog($respBody, 400),
            ]);
        } else {
            Log::info('OutboundPushHook dispatch ok', [
                'event'               => $event,
                'configEnterpriseId'  => $resolved['configEnterpriseId'],
                'contextEnterpriseId' => $contextEnterpriseId,
                'url'                 => self::maskUrl($url),
            ]);
        }
        if (!$ok && $dedupKey !== '') {
            self::rollbackDedup($dedupKey);
        }

        return [
            'ok'                   => $ok,
            'status'               => $ok ? 'dispatched' : 'failed',
            'message'              => $ok ? '已发送到对端' : '发送失败',
            'event'                => $event,
            'contextEnterpriseId'  => $contextEnterpriseId,
            'configEnterpriseId'   => $resolved['configEnterpriseId'],
            'usedPlatformFallback' => $resolved['usedPlatformFallback'],
            'dedupKey'             => $dedupKey !== '' ? ('push_hook:' . $dedupKey) : null,
            'forced'               => $force,
            'httpStatus'           => $httpStatus,
            'responsePreview'      => $respBody !== '' ? self::truncateForLog($respBody, 800) : null,
            'businessHint'         => $bizHint,
            'curlError'            => $curlErr !== '' ? $curlErr : null,
            'targetUrl'            => self::maskUrl($url),
        ];
    }

    /**
     * 是否已存在出站去重记录（scene=outbound_hook）。
     *
     * @param string $dedupKey envelope._dedupKey 原值，如 test.result_completed:123、lead.order_paid:456
     */
    public static function hasPushHookDedup(string $dedupKey): bool
    {
        if ($dedupKey === '') {
            return false;
        }

        try {
            return Db::name('delivery_dedup')
                ->where('scene', self::DEDUP_SCENE_OUTBOUND)
                ->where('dedupKey', $dedupKey)
                ->find() ? true : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function tenantPayload(int $enterpriseId): array
    {
        if ($enterpriseId <= 0) {
            return [
                'enterpriseId'   => 0,
                'enterpriseName' => null,
            ];
        }
        $name = Db::name('enterprises')->where('id', $enterpriseId)->value('name');
        return [
            'enterpriseId'   => $enterpriseId,
            'enterpriseName' => $name !== null && $name !== '' ? (string) $name : null,
        ];
    }

    private static function appEnv(): string
    {
        $e = (string) (env('app.env', '') ?: getenv('APP_ENV') ?: '');
        return $e !== '' ? $e : 'production';
    }

    private static function iso8601Cn(): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        return $dt->format('c');
    }

    private static function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private static function beginDedup(string $rawDedupKey): bool
    {
        if ($rawDedupKey === '') {
            return false;
        }
        try {
            Db::name('delivery_dedup')->insert([
                'scene'     => self::DEDUP_SCENE_OUTBOUND,
                'dedupKey'  => $rawDedupKey,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function rollbackDedup(string $rawDedupKey): void
    {
        if ($rawDedupKey === '') {
            return;
        }
        try {
            Db::name('delivery_dedup')
                ->where('scene', self::DEDUP_SCENE_OUTBOUND)
                ->where('dedupKey', $rawDedupKey)
                ->delete();
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<string,mixed> $headers
     */
    /**
     * @return array{0: bool, 1: int, 2: string, 3: string, 4: ?string} ok, httpStatus, responseBody, curlError, businessHint
     */
    private static function httpPostJsonWithCode(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return [false, 0, '', 'curl_init failed', null];
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $curlErr = $errno ? (string) curl_error($ch) : '';
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseBody = is_string($raw) ? $raw : '';
        if ($errno !== 0) {
            return [false, $code, $responseBody, $curlErr, null];
        }

        $httpOk = $code >= 200 && $code < 300;
        $biz = self::interpretOutboundResponseBody($responseBody);
        $ok = $httpOk && $biz['ok'];

        return [$ok, $code, $responseBody, '', $biz['hint']];
    }

    /**
     * 企微/飞书等常见接口：HTTP 200 但 body 内声明失败（与 FeishuLeadWebhookService::postWebhook 对齐）
     *
     * @return array{ok: bool, hint: ?string}
     */
    private static function interpretOutboundResponseBody(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['ok' => true, 'hint' => null];
        }
        if ($body[0] === '<' || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<html') !== false) {
            return ['ok' => false, 'hint' => '对端返回 HTML 而非 JSON，多为 URL 填成网站首页或错误页'];
        }
        $resp = json_decode($body, true);
        if (!is_array($resp)) {
            return ['ok' => true, 'hint' => null];
        }
        if (isset($resp['errcode']) && (int) $resp['errcode'] !== 0) {
            $msg = isset($resp['errmsg']) ? (string) $resp['errmsg'] : '';

            return ['ok' => false, 'hint' => 'errcode=' . $resp['errcode'] . ($msg !== '' ? ' ' . $msg : '')];
        }
        if (isset($resp['StatusCode']) && (int) $resp['StatusCode'] !== 0) {
            $msg = isset($resp['StatusMessage']) ? (string) $resp['StatusMessage'] : '';

            return ['ok' => false, 'hint' => 'StatusCode=' . $resp['StatusCode'] . ($msg !== '' ? ' ' . $msg : '')];
        }
        if (isset($resp['code']) && (int) $resp['code'] !== 0) {
            return ['ok' => false, 'hint' => 'code=' . $resp['code']];
        }

        return ['ok' => true, 'hint' => null];
    }

    private static function truncateForLog(string $s, int $max): string
    {
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max) . '…';
        }
        if (strlen($s) > $max) {
            return substr($s, 0, $max) . '…';
        }

        return $s;
    }

    /** 日志中隐藏 query 敏感参数 */
    private static function maskUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strpos($url, '?') === false) {
            return $url;
        }
        $p = parse_url($url);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return $url;
        }

        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . ($p['path'] ?? '') . '?…';
    }

    /**
     * 面相/人脸结果中的 MBTI、PDP、DISC 三维度文案（与小程序 resultData 结构一致）。
     *
     * @param array<string,mixed> $data
     * @return array{mbti: string, pdp: string, disc: string}
     */
    private static function buildFaceAiBotDimensions(array $data): array
    {
        $mbti = '';
        if (isset($data['mbti']['type'])) {
            $mbti = trim((string) $data['mbti']['type']);
        } elseif (isset($data['mbti']) && !is_array($data['mbti'])) {
            $mbti = trim((string) $data['mbti']);
        } elseif (!empty($data['mbtiType']) && is_string($data['mbtiType'])) {
            $mbti = trim($data['mbtiType']);
        }

        $pdp = '';
        if (isset($data['pdp']) && is_array($data['pdp'])) {
            $p1 = trim((string) ($data['pdp']['primary'] ?? ''));
            $p2 = trim((string) ($data['pdp']['secondary'] ?? ''));
            $p1 = preg_replace('/型$/u', '', $p1);
            $p2 = preg_replace('/型$/u', '', $p2);
            if ($p1 !== '' && $p2 !== '' && $p1 !== $p2) {
                $pdp = $p1 . '+' . $p2 . '型';
            } elseif ($p1 !== '') {
                $pdp = $p1;
                if (!preg_match('/型$/u', $pdp)) {
                    $pdp .= '型';
                }
            }
        }
        if ($pdp === '') {
            $pdp = PdpDiscResultText::pdpTopTwo($data);
        }

        $disc = '';
        if (isset($data['disc']) && is_array($data['disc'])) {
            $d1 = trim((string) ($data['disc']['primary'] ?? ''));
            $d2 = trim((string) ($data['disc']['secondary'] ?? ''));
            $L1 = strtoupper(substr(preg_replace('/型$/u', '', $d1), 0, 1));
            $L2 = strtoupper(substr(preg_replace('/型$/u', '', $d2), 0, 1));
            if (in_array($L1, ['D', 'I', 'S', 'C'], true) && in_array($L2, ['D', 'I', 'S', 'C'], true) && $L1 !== $L2) {
                $disc = $L1 . '+' . $L2;
            } elseif (in_array($L1, ['D', 'I', 'S', 'C'], true)) {
                $disc = $L1;
            }
        }
        if ($disc === '') {
            $disc = PdpDiscResultText::discTopTwo($data);
            $disc = preg_replace('/型$/u', '', $disc);
        }

        return [
            'mbti' => $mbti,
            'pdp'  => $pdp,
            'disc' => $disc,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function formatTestResultSummary(string $testType, array $data): string
    {
        switch ($testType) {
            case 'mbti':
                return (string) ($data['mbtiType'] ?? $data['mbti'] ?? '未知');
            case 'disc':
                $t = PdpDiscResultText::discTopTwo($data);
                if ($t !== '') {
                    return $t;
                }
                $dominantType = $data['dominantType'] ?? $data['disc'] ?? '未知';
                return (is_string($dominantType) || is_numeric($dominantType) ? (string) $dominantType : '未知') . '型';
            case 'pdp':
                $t = PdpDiscResultText::pdpTopTwo($data);
                if ($t !== '') {
                    return $t;
                }
                return (string) ($data['description']['type'] ?? $data['pdp'] ?? '未知');
            case 'sbti':
                $r = (string) ($data['sbtiType'] ?? $data['finalType']['code'] ?? '未知');
                if (!empty($data['sbtiCn'])) {
                    $r .= '（' . $data['sbtiCn'] . '）';
                } elseif (!empty($data['finalType']['cn'])) {
                    $r .= '（' . $data['finalType']['cn'] . '）';
                }
                return $r;
            case 'face':
            case 'ai':
                if (isset($data['mbti']['type'])) {
                    return (string) $data['mbti']['type'];
                }
                if (isset($data['mbti']) && !is_array($data['mbti'])) {
                    return (string) $data['mbti'];
                }
                if (!empty($data['mbtiType']) && is_string($data['mbtiType'])) {
                    return (string) $data['mbtiType'];
                }
                if (!empty($data['faceAnalysis']) && is_string($data['faceAnalysis'])) {
                    return self::truncatePlainText($data['faceAnalysis'], 100);
                }
                return '面相分析';
            case 'resume':
                if (!empty($data['overview'])) {
                    $s = strip_tags((string) $data['overview']);
                    if (function_exists('mb_strlen') && mb_strlen($s) > 80) {
                        return mb_substr($s, 0, 80) . '…';
                    }
                    return strlen($s) > 80 ? substr($s, 0, 80) . '…' : $s;
                }
                return '简历综合分析';
            default:
                return $testType !== '' ? $testType : '未知';
        }
    }

    private static function testTypeLabel(string $testType): string
    {
        $map = [
            'mbti'   => 'MBTI 性格测试',
            'sbti'   => 'SBTI 性格测试',
            'disc'   => 'DISC 性格测试',
            'pdp'    => 'PDP 行为偏好测试',
            'face'   => '面相分析',
            'ai'     => 'AI 人脸分析',
            'resume' => '简历综合分析',
        ];
        return $map[$testType] ?? strtoupper($testType);
    }
}
