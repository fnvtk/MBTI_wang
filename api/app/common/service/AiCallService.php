<?php
namespace app\common\service;

use app\model\AiProvider as AiProviderModel;
use think\facade\Db;
use think\facade\Log;

/**
 * 神仙 AI · 统一对话调用服务
 *
 * 能力：
 * 1. 自动按 sortWeight / id 升序挑选可用服务商
 * 2. 余额低于阈值的服务商自动降权沉底
 * 3. 故障自动切换（429/401/402/5xx/解析错误）
 * 4. 支持 OpenAI 兼容 / Anthropic 两类协议
 * 5. 全部失败返回降级兜底回答，**不抛给前端**
 *
 * 典型用法：
 *   $r = AiCallService::chat([
 *     ['role'=>'system','content'=>'你是…'],
 *     ['role'=>'user','content'=>'我是 INFP，今天压力大'],
 *   ]);
 *   // => ['content'=>'...', 'providerId'=>'deepseek', 'isDegraded'=>false]
 */
class AiCallService
{
    /**
     * 对话入口
     *
     * @param array $messages OpenAI messages 格式
     * @param array $options  ['temperature'=>0.7, 'maxTokens'=>2048, 'stream'=>false]
     * @return array          ['content'=>string, 'providerId'=>string, 'isDegraded'=>bool, 'tokensIn'=>int, 'tokensOut'=>int]
     */
    public static function chat(array $messages, array $options = []): array
    {
        $maxTokens   = (int) ($options['maxTokens'] ?? 2048);
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;

        $providers = self::resolveProviders();
        if (empty($providers)) {
            return self::degrade('no-provider', '小神仙在喝茶呢，超管还没配置好法力，稍后再来找我呀～');
        }

        $lastError = '';
        foreach ($providers as $provider) {
            $providerId = strtolower((string) ($provider->providerId ?? ''));
            $apiKey     = $provider->getRawApiKey();
            if ($apiKey === '') {
                continue;
            }

            $endpoint = self::resolveEndpoint($provider);
            $model    = !empty($provider->model) ? $provider->model : self::defaultModel($providerId);

            try {
                if ($providerId === 'anthropic') {
                    $content = self::callAnthropic($endpoint, $apiKey, $model, $messages, $maxTokens);
                } else {
                    // openai / deepseek / moonshot / qwen / zhipu / zhizengzeng / groq 等
                    $content = self::callOpenAICompatible($endpoint, $apiKey, $model, $messages, $maxTokens, $temperature);
                }

                if ($content === '') {
                    $lastError = "provider={$providerId} empty-content";
                    continue;
                }

                return [
                    'content'    => $content,
                    'providerId' => $providerId,
                    'model'      => $model,
                    'isDegraded' => false,
                    'tokensIn'   => 0, // 各家返回字段不一，留空（可二期补 usage）
                    'tokensOut'  => 0,
                ];
            } catch (\Throwable $e) {
                $lastError = "provider={$providerId} error=" . $e->getMessage();
                Log::warning('AiCallService chat fallback: ' . $lastError);
                // 遇到明显欠费/鉴权类错误，给该服务商的余额打低权重沉底
                if (self::looksLikeBillingError($e->getMessage())) {
                    try {
                        $provider->sortWeight = 999;
                        $provider->save();
                    } catch (\Throwable $ignore) {}
                }
                continue;
            }
        }

        return self::degrade('all-failed', '小神仙暂时联系不上天界了（' . $lastError . '），工程师已收到告警，请稍后再试 🌿');
    }

    /**
     * 选出可用服务商列表（按 sortWeight 升序、余额不足的沉底）
     * @return AiProviderModel[]
     */
    private static function resolveProviders(): array
    {
        $all = AiProviderModel::where('enabled', 1)
            ->whereRaw('(visible IS NULL OR visible = 1)')
            ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
            ->order('sortWeight', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        if (empty($all)) {
            return [];
        }

        // 把「余额低于阈值」的服务商排到队尾
        $healthy = [];
        $depleted = [];
        foreach ($all as $row) {
            $provider = AiProviderModel::where('id', $row['id'])->find();
            if (!$provider) continue;

            $isLowBalance = $provider->balanceAlertEnabled == 1
                && $provider->lastBalance !== null
                && $provider->lastBalance <= (float) ($provider->balanceAlertThreshold ?? 0);

            if ($isLowBalance) {
                $depleted[] = $provider;
            } else {
                $healthy[] = $provider;
            }
        }

        return array_merge($healthy, $depleted);
    }

    private static function resolveEndpoint($provider): string
    {
        $endpoint = !empty($provider->apiEndpoint) ? rtrim($provider->apiEndpoint, '/') : '';
        if ($endpoint !== '') return $endpoint;
        return self::defaultEndpoint(strtolower((string) $provider->providerId));
    }

    private static function defaultEndpoint(string $providerId): string
    {
        switch ($providerId) {
            case 'openai':      return 'https://api.openai.com/v1';
            case 'groq':        return 'https://api.groq.com/openai/v1';
            case 'deepseek':    return 'https://api.deepseek.com/v1';
            case 'moonshot':    return 'https://api.moonshot.ai/v1';
            case 'qwen':        return 'https://dashscope.aliyuncs.com/compatible-mode/v1';
            case 'anthropic':   return 'https://api.anthropic.com/v1';
            case 'zhipu':       return 'https://api.z.ai/api/paas/v4';
            case 'zhizengzeng': return 'https://api.zhizengzeng.com/v1';
            default:            return 'https://api.openai.com/v1';
        }
    }

    private static function defaultModel(string $providerId): string
    {
        switch ($providerId) {
            case 'deepseek':    return 'deepseek-chat';
            case 'moonshot':    return 'moonshot-v1-8k';
            case 'qwen':        return 'qwen-turbo';
            case 'zhipu':       return 'glm-4-flash';
            case 'anthropic':   return 'claude-3-5-haiku-latest';
            case 'groq':        return 'llama-3.1-70b-versatile';
            default:            return 'gpt-4o-mini';
        }
    }

    /**
     * OpenAI 兼容协议（deepseek / moonshot / qwen / zhipu / zhizengzeng / groq / openai…）
     */
    private static function callOpenAICompatible(string $endpoint, string $apiKey, string $model, array $messages, int $maxTokens, float $temperature): string
    {
        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
        ];

        if (strpos($endpoint, 'api.zhizengzeng.com') !== false) {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
        }

        $url     = rtrim($endpoint, '/') . '/chat/completions';
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            throw new \RuntimeException("curl-failed code={$curlErrNo} msg={$curlError}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("http-{$httpCode}: " . substr((string) $response, 0, 400));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid-json');
        }

        if (!empty($data['error'])) {
            $errMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];
            throw new \RuntimeException('api-error: ' . $errMsg);
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        return trim((string) $text);
    }

    /**
     * Anthropic Messages 协议
     */
    private static function callAnthropic(string $endpoint, string $apiKey, string $model, array $messages, int $maxTokens): string
    {
        // Anthropic 需要单独传 system，messages 只接受 user/assistant
        $system = '';
        $userMessages = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system .= "\n" . ($m['content'] ?? '');
            } else {
                $userMessages[] = [
                    'role'    => $m['role'] ?? 'user',
                    'content' => [['type' => 'text', 'text' => (string) ($m['content'] ?? '')]],
                ];
            }
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $userMessages,
        ];
        if (trim($system) !== '') {
            $body['system'] = trim($system);
        }

        $url     = rtrim($endpoint, '/') . '/messages';
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $response === '') {
            throw new \RuntimeException('anthropic-no-response');
        }
        if ($httpCode >= 400) {
            throw new \RuntimeException("http-{$httpCode}: " . substr((string) $response, 0, 400));
        }

        $data = json_decode($response, true);
        $text = '';
        if (!empty($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }
        }
        return trim($text);
    }

    /**
     * 常见欠费/鉴权类错误关键字 → 触发降权
     */
    private static function looksLikeBillingError(string $msg): bool
    {
        $lower = strtolower($msg);
        foreach (['insufficient', 'balance', 'quota', 'rate_limit', '402', '429', 'unauthorized', '401'] as $kw) {
            if (strpos($lower, $kw) !== false) return true;
        }
        return false;
    }

    private static function degrade(string $reason, string $content): array
    {
        Log::warning('AiCallService degraded: ' . $reason);
        return [
            'content'    => $content,
            'providerId' => 'degrade',
            'model'      => '',
            'isDegraded' => true,
            'tokensIn'   => 0,
            'tokensOut'  => 0,
        ];
    }

    /**
     * 构造针对某用户的 system prompt（结合 MBTI 类型 + 画像）
     */
    public static function buildSystemPrompt(array $userContext): string
    {
        $mbtiType = trim((string) ($userContext['mbtiType'] ?? ''));
        $summary  = trim((string) ($userContext['summary'] ?? ''));
        $nickname = trim((string) ($userContext['nickname'] ?? ''));

        $prompt = "你是「神仙 AI」，神仙团队 MBTI 性格小程序的专属人格伙伴。\n";
        $prompt .= "说话风格：像朋友一样亲切、简短（每次回答 150 字以内，必要时可延展），不堆砌术语；适度鼓励用户完成其他测评（DISC / PDP / SBTI / 面相）或去「一场 soul 创业实验」公众号阅读相关文章。\n";
        $prompt .= "禁止：做医疗诊断 / 政治议题 / 具体投资建议。\n";
        if ($nickname !== '') {
            $prompt .= "称呼：可以偶尔叫用户「{$nickname}」。\n";
        }
        if ($mbtiType !== '') {
            $prompt .= "用户 MBTI：{$mbtiType}。请结合此类型做个性化回答。\n";
        } else {
            $prompt .= "用户尚未完成 MBTI 测试。回答时可友好建议先去做一下测评。\n";
        }
        if ($summary !== '') {
            $prompt .= "用户性格画像摘要：{$summary}\n";
        }

        $appendix = trim((string) ($userContext['testAppendix'] ?? ''));
        if ($appendix !== '') {
            $prompt .= "\n【用户测评客观记录（含问卷选项，供你结合其当前提问做针对性回答；勿机械罗列题号套话）】\n";
            $prompt .= $appendix . "\n";
        }

        return $prompt;
    }

    /**
     * 拉取用户 MBTI 画像（最近一次 test_results 结果）
     */
    public static function fetchUserContext(int $userId): array
    {
        if ($userId <= 0) {
            return ['mbtiType' => '', 'summary' => '', 'nickname' => '', 'testAppendix' => ''];
        }

        $nickname = (string) Db::name('wechat_users')->where('id', $userId)->value('nickname');

        $mbtiType = '';
        $summary  = '';
        $row = Db::name('test_results')
            ->where('userId', $userId)
            ->where('testType', 'mbti')
            ->order('id', 'desc')
            ->find();
        if ($row && !empty($row['resultData'])) {
            $data = is_string($row['resultData']) ? json_decode($row['resultData'], true) : $row['resultData'];
            if (is_array($data)) {
                $mbtiType = (string) ($data['mbtiType'] ?? ($data['mbti']['type'] ?? ''));
                if (!empty($data['description']['summary'])) {
                    $summary = (string) $data['description']['summary'];
                } elseif (!empty($data['description']['overview'])) {
                    $summary = (string) $data['description']['overview'];
                }
            }
        }

        // 若无 MBTI，尝试回落到面相分析给出的 mbti
        if ($mbtiType === '') {
            $faceRow = Db::name('test_results')
                ->where('userId', $userId)
                ->where('testType', 'face')
                ->order('id', 'desc')
                ->find();
            if ($faceRow && !empty($faceRow['resultData'])) {
                $data = is_string($faceRow['resultData']) ? json_decode($faceRow['resultData'], true) : $faceRow['resultData'];
                if (is_array($data) && isset($data['mbti']['type'])) {
                    $mbtiType = (string) $data['mbti']['type'];
                    $summary  = (string) ($data['personalitySummary'] ?? ($data['overview'] ?? ''));
                }
            }
        }

        $testAppendix = self::buildLatestTestsAppendix($userId);

        return [
            'mbtiType'     => $mbtiType,
            'summary'      => $summary,
            'nickname'     => $nickname,
            'testAppendix' => $testAppendix,
        ];
    }

    /**
     * 汇总用户最近一次各类型测评的答题与结果，供 system prompt 使用（有长度上限）
     */
    private static function buildLatestTestsAppendix(int $userId): string
    {
        $types   = ['mbti', 'sbti', 'disc', 'pdp'];
        $blocks  = [];
        foreach ($types as $type) {
            $row = Db::name('test_results')
                ->where('userId', $userId)
                ->where('testType', $type)
                ->order('id', 'desc')
                ->find();
            if (!$row || empty($row['resultData'])) {
                continue;
            }
            $data = is_string($row['resultData']) ? json_decode($row['resultData'], true) : $row['resultData'];
            if (!is_array($data)) {
                continue;
            }
            $block = self::formatTestBlockForPrompt($type, $data);
            if ($block !== '') {
                $blocks[] = $block;
            }
        }
        $text = implode("\n——\n", $blocks);
        if ($text === '') {
            return '';
        }
        $maxLen = 3800;
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8') . '…（档案已截断）';
        }
        return $text;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatTestBlockForPrompt(string $testType, array $data): string
    {
        switch ($testType) {
            case 'mbti':
                return self::formatMbtiBlockForPrompt($data);
            case 'sbti':
                return self::formatSbtiBlockForPrompt($data);
            case 'disc':
                return self::formatDiscBlockForPrompt($data);
            case 'pdp':
                return self::formatPdpBlockForPrompt($data);
            default:
                return '';
        }
    }

    /**
     * @param array<string, mixed>|null $answers
     */
    private static function formatAnswersCompact(?array $answers): string
    {
        if (!is_array($answers) || $answers === []) {
            return '';
        }
        $keys = array_keys($answers);
        sort($keys, SORT_NATURAL);
        $parts = [];
        foreach ($keys as $k) {
            $v = $answers[$k];
            if (is_array($v) || is_object($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $parts[] = (string) $k . '→' . (string) $v;
            if (count($parts) >= 100) {
                $parts[] = '…共' . count($keys) . '题';
                break;
            }
        }
        return implode('，', $parts);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatMbtiBlockForPrompt(array $data): string
    {
        $lines = ['【MBTI·最近一次】'];
        $type = (string) ($data['mbtiType'] ?? ($data['mbti']['type'] ?? ''));
        if ($type !== '') {
            $lines[] = '类型：' . $type;
        }
        if (isset($data['confidence'])) {
            $lines[] = '置信度：' . (int) $data['confidence'] . '%';
        }
        if (!empty($data['dimensionScores']) && is_array($data['dimensionScores'])) {
            $lines[] = '四轴：' . json_encode($data['dimensionScores'], JSON_UNESCAPED_UNICODE);
        }
        if (!empty($data['scores']) && is_array($data['scores'])) {
            $lines[] = '字母计数：' . json_encode($data['scores'], JSON_UNESCAPED_UNICODE);
        }
        $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
        if ($ans !== '') {
            $lines[] = '逐题选项（题号/ID→所选）：' . $ans;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatSbtiBlockForPrompt(array $data): string
    {
        $lines  = ['【SBTI·最近一次】'];
        $final  = $data['finalType'] ?? null;
        $fcode  = is_array($final) ? (string) ($final['code'] ?? '') : '';
        $code   = (string) ($data['sbtiType'] ?? $fcode);
        if ($code !== '') {
            $lines[] = '类型代码：' . $code;
        }
        if (!empty($data['special'])) {
            $lines[] = '特殊标记：' . json_encode($data['special'], JSON_UNESCAPED_UNICODE);
        }
        $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
        if ($ans !== '') {
            $lines[] = '逐题选项：' . $ans;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatDiscBlockForPrompt(array $data): string
    {
        $lines = ['【DISC·最近一次】'];
        if (!empty($data['dominantType'])) {
            $lines[] = '主导：' . (string) $data['dominantType'] . '，次要：' . (string) ($data['secondaryType'] ?? '');
        }
        if (!empty($data['scores']) && is_array($data['scores'])) {
            $lines[] = '得分：' . json_encode($data['scores'], JSON_UNESCAPED_UNICODE);
        }
        $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
        if ($ans !== '') {
            $lines[] = '逐题选项：' . $ans;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatPdpBlockForPrompt(array $data): string
    {
        $lines = ['【PDP·最近一次】'];
        if (!empty($data['dominantType'])) {
            $lines[] = '主导：' . (string) $data['dominantType'] . '，次要：' . (string) ($data['secondaryType'] ?? '');
        }
        if (!empty($data['scores']) && is_array($data['scores'])) {
            $lines[] = '得分：' . json_encode($data['scores'], JSON_UNESCAPED_UNICODE);
        }
        $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
        if ($ans !== '') {
            $lines[] = '逐题选项：' . $ans;
        }
        return implode("\n", $lines);
    }

    /**
     * 过滤不在小程序展示的快捷问句（与产品配置一致）
     *
     * @param string[] $questions
     * @return string[]
     */
    public static function filterQuickQuestions(array $questions): array
    {
        $out = [];
        foreach ($questions as $q) {
            $s = trim((string) $q);
            if ($s === '') {
                continue;
            }
            if (preg_match('/记\s*一下\s*我的\s*MBTI/ui', $s)) {
                continue;
            }
            $out[] = $s;
        }
        return $out;
    }

    /**
     * 基于 MBTI 类型返回 3 条快捷问句
     */
    public static function quickQuestions(string $mbtiType): array
    {
        $mbtiType = strtoupper(trim($mbtiType));
        $generic = [
            '我应该找什么样的工作？',
            '我适合什么样的伴侣？',
            '我的职业发展方向是什么？',
            '我最近有点迷茫，有什么建议？',
            '帮我做一个简短的自我介绍',
            '我有哪些需要警惕的盲点？',
        ];
        if ($mbtiType === '') return $generic;

        return [
            "作为 {$mbtiType}，我最大的优势和盲点是什么？",
            "{$mbtiType} 适合什么样的工作？",
            "{$mbtiType} 适合找什么样的伴侣？",
            "{$mbtiType} 的职业发展路径推荐？",
            "{$mbtiType} 如何处理职场人际关系？",
            "{$mbtiType} 最容易掉进什么心理陷阱？",
        ];
    }
}
