<?php

namespace app\common\service;

use app\common\PdpDiscResultText;
use think\facade\Db;
use think\facade\Log;

/**
 * 第三方开放平台：按手机号回写测评结果（POST /api/open）
 * 仅当用户存在渠道绑定字段且配置了 OPEN_PLATFORM_URL / OPEN_PLATFORM_API_KEY 时推送
 */
class OpenPlatformService
{
    public static function hasThirdPartyBinding(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $u = Db::name('wechat_users')
            ->where('id', $userId)
            ->field('ext_uid,third_party_phone')
            ->find();
        if (!$u) {
            return false;
        }
        foreach (['ext_uid', 'third_party_phone'] as $k) {
            if (trim((string) ($u[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * 开放平台要求的 phone：**优先 third_party_phone**，无则回落主库 phone
     */
    public static function resolveNotifyPhone(array $wechatRow): ?string
    {
        $tp   = trim((string) ($wechatRow['third_party_phone'] ?? ''));
        $main = trim((string) ($wechatRow['phone'] ?? ''));
        $norm = ThirdPartyChannelService::normalizePhone($tp !== '' ? $tp : $main);
        if ($norm !== null) {
            return $norm;
        }
        $digits = preg_replace('/\D+/', '', $tp !== '' ? $tp : $main);
        if ($digits !== '' && strlen($digits) >= 5 && strlen($digits) <= 20) {
            return $digits;
        }

        return null;
    }

    /**
     * 从本次提交的 result 数组生成开放平台单字段摘要
     *
     * @return array<string,string> 如 ['mbti'=>'INTJ']，失败返回 []
     */
    public static function buildAssessmentPayload(string $testType, array $result): array
    {
        $testType = strtolower(trim($testType));
        if (!in_array($testType, ['mbti', 'disc', 'pdp', 'face', 'ai'], true)) {
            return [];
        }

        if ($testType === 'face' || $testType === 'ai') {
            $mbtiShort = '';
            if (isset($result['mbti']['type'])) {
                $mbtiShort = trim((string) $result['mbti']['type']);
            } elseif (isset($result['mbti']) && !is_array($result['mbti'])) {
                $mbtiShort = trim((string) $result['mbti']);
            }
            if ($mbtiShort !== '') {
                $mbtiShort = self::formatMbtiForOpenPlatform($mbtiShort);
                if ($mbtiShort === '') {
                    return [];
                }
                if (mb_strlen($mbtiShort) > 500) {
                    $mbtiShort = mb_substr($mbtiShort, 0, 500) . '…';
                }

                return ['mbti' => $mbtiShort];
            }
            $fa = $result['faceAnalysis'] ?? '';
            if (is_string($fa) && trim($fa) !== '') {
                $fa = preg_replace('/\s+/u', ' ', trim($fa));
                $snippet = mb_strlen($fa) > 500 ? mb_substr($fa, 0, 500) . '…' : $fa;

                return ['mbti' => $snippet];
            }

            return [];
        }

        $text = '';
        switch ($testType) {
            case 'mbti':
                $t = $result['mbtiType'] ?? $result['mbti'] ?? '';
                $raw = is_string($t) ? trim($t) : (is_numeric($t) ? (string) $t : '');
                $text = self::formatMbtiForOpenPlatform($raw);
                break;
            case 'disc':
                $text = PdpDiscResultText::discTopTwo($result);
                if ($text === '') {
                    $dominantType = $result['dominantType'] ?? $result['disc'] ?? '';
                    $text = (is_string($dominantType) || is_numeric($dominantType) ? (string) $dominantType : '') . '型';
                }
                $text = self::formatDiscForOpenPlatform($text);
                break;
            case 'pdp':
                $text = PdpDiscResultText::pdpTopTwo($result);
                if ($text === '') {
                    $text = (string) ($result['description']['type'] ?? $result['pdp'] ?? '');
                }
                $text = self::formatPdpForOpenPlatform($text);
                break;
        }
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) > 500) {
            $text = mb_substr($text, 0, 500) . '…';
        }

        return [$testType => $text];
    }

    /**
     * 测评结果写入成功后调用：mbti/disc/pdp/face(ai)，且用户有第三方绑定（ext_uid 或 third_party_phone）
     *
     * 开放平台仅接收 mbti/disc/pdp：人脸结果优先推推断的 MBTI；无则推 faceAnalysis 摘要到 mbti 字段
     *
     * @param array<string,mixed> $result 与写入 test_results 前相同的数组
     */
    public static function notifyQuestionnaireIfNeeded(int $userId, string $testType, $result): void
    {
        if ($userId <= 0 || !is_array($result)) {
            return;
        }
        $testType = strtolower(trim($testType));
        if (!in_array($testType, ['mbti', 'disc', 'pdp', 'face', 'ai'], true)) {
            return;
        }

        $baseUrl = trim((string) env('OPEN_PLATFORM_URL', ''));
        $apiKey  = trim((string) env('OPEN_PLATFORM_API_KEY', ''));
        if ($baseUrl === '' || $apiKey === '') {
            return;
        }
        if (!self::hasThirdPartyBinding($userId)) {
            return;
        }

        $wechatRow = Db::name('wechat_users')
            ->where('id', $userId)
            ->field('phone,third_party_phone')
            ->find();
        if (!$wechatRow) {
            return;
        }
        $phone = self::resolveNotifyPhone($wechatRow);
        if ($phone === null || $phone === '') {
            Log::warning('OpenPlatform skip: no phone', ['userId' => $userId]);

            return;
        }

        $assessment = self::buildAssessmentPayload($testType, $result);
        if ($assessment === []) {
            return;
        }

        $url = rtrim(trim($baseUrl), '/');
        if (!preg_match('#/api/open/user/profile$#', $url)) {
            $url .= '/api/open/user/profile';
        }

        

        $body = array_merge(['phone' => $phone], $assessment);
        $headers = [
            // requestCurl 对 SSL 默认会关闭校验，避免「找不到本地 issuer certificate」阻断对接
            'Content-Type:application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        try {
            $respBody = \requestCurl($url, $body, 'POST', $headers, 'json');
        } catch (\Throwable $e) {
            Log::warning('OpenPlatform requestCurl exception: ' . $e->getMessage(), [
                'userId' => $userId,
                'url'    => $url,
            ]);
            return;
        }

        if (!is_string($respBody)) {
            $respBody = '';
        }
        if ($respBody === '') {
            // requestCurl 没返回 http code，这里只做响应为空的弱提示
            Log::warning('OpenPlatform empty response', [
                'userId' => $userId,
                'url'    => $url,
                'body'   => [
                    'phone' => $phone,
                    // 只回显 keys，避免日志把完整结果打爆
                    'keys'  => array_keys($assessment),
                ],
            ]);
        }
    }

    /**
     * MBTI：四字母类型，如 ENFJ（兼容文案中带 ENFJ-A、括号等）
     */
    private static function formatMbtiForOpenPlatform(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/\b([EI][NS][FT][JP])\b/i', $raw, $m)) {
            return strtoupper($m[1]);
        }
        $compact = strtoupper(preg_replace('/[^EINSFTPJ]/i', '', $raw));
        if (strlen($compact) >= 4 && preg_match('/^[EI][NS][FT][JP]$/', substr($compact, 0, 4))) {
            return substr($compact, 0, 4);
        }
        $letters = strtoupper(preg_replace('/[^A-Z]/', '', $raw));
        if (strlen($letters) >= 4) {
            return substr($letters, 0, 4);
        }

        return $raw;
    }

    /**
     * DISC：D+C（去掉尾缀「型」）
     */
    private static function formatDiscForOpenPlatform(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return preg_replace('/型$/u', '', $text);
    }

    /**
     * PDP：无尾熊+变色龙（各段去掉尾缀「型」）
     */
    private static function formatPdpForOpenPlatform(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $parts = preg_split('/\+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(static function ($p) {
            return preg_replace('/型$/u', '', trim($p));
        }, $parts);
        $parts = array_values(array_filter($parts, static function ($p) {
            return $p !== '';
        }));

        return implode('+', $parts);
    }
}
