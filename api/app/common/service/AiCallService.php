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

    /**
     * 与 POST /api/ai/chat 相同的第一优先服务商（sortWeight 升序 + 余额预警沉底）。
     * 面相 POST /api/analyze、简历分析等应与此一致，保证密钥、线路、优先级与神仙 AI 对齐。
     *
     * @return AiProviderModel|null
     */
    public static function firstOrderedProvider(): ?AiProviderModel
    {
        $list = self::resolveProviders();

        return $list[0] ?? null;
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
            // 异步任务在 shutdown 中执行时可等待更久；同步面相等场景亦避免慢模型被 60s 截断
            CURLOPT_TIMEOUT        => 120,
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
            CURLOPT_TIMEOUT        => 120,
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

        $prompt = "你是「神仙 AI」，微信小程序里的性格与成长助手。你必须**直接生成**对当前这条消息的回答，禁止套用固定模板或预置长文案。\n";
        $prompt .= "【语气】像真人深聊：完整、通顺的句子；先接住对方的问题或情绪，再展开；有轻有重、有节奏，不要电报式短句堆砌。口语自然，但保持可读与分寸，像值得信赖的朋友。**禁止**「作为 AI」「根据您的问题」等机械开场，**禁止**元叙述（解释自己怎么回答、用什么结构）。\n";
        $prompt .= "**禁止**在正文里提及任何「回复格式名」「模式名」「版本代号」或类似自报（含 Human、HUMAN、3.0、秋门等谐音或变体）；只自然说话，不要标签。\n";
        $prompt .= "第一句就要切入正题；分段清晰；单次回复以 180～380 字为宜（用户明确要求更长时再略增）。不堆砌术语。\n";
        $prompt .= "【时效】用户端网络请求约 60 秒内必须结束：请优先完整答完当前问题，避免冗长铺垫，以免用户看到超时错误。\n";
        $prompt .= "【重要】下方「测评档案」仅供你理解用户背景：**禁止**把档案整段抄给用户、**禁止**逐条罗列题号或问卷选项。\n";
        $prompt .= "**禁止**以 #、@、「我是xxx」、括号人设、运营标签或任何签名行开场；**禁止**自称/提及「卡若」或类似第三方运营人设；**禁止**编造「几点起床」等噱头头衔。**禁止**主动插入公众号、外链或营销话术；用户若问起其他测评，用一句话带过即可。\n";
        $prompt .= "禁止：医疗诊断、政治敏感、具体投资建议。\n";
        if ($nickname !== '') {
            $prompt .= "可偶尔称呼用户「{$nickname}」。\n";
        }
        if ($mbtiType !== '') {
            $prompt .= "已知 MBTI：{$mbtiType}，请结合类型作答。\n";
        } else {
            $prompt .= "用户可能尚未测 MBTI；可温和建议先完成站内测评。\n";
        }
        if ($summary !== '') {
            $prompt .= "性格摘要（勿照抄，仅作理解）：{$summary}\n";
        }

        $appendix = trim((string) ($userContext['testAppendix'] ?? ''));
        if ($appendix !== '') {
            $prompt .= "\n【测评档案·内部参考】\n";
            $prompt .= $appendix . "\n";
        }

        return $prompt;
    }

    /**
     * 拉取用户 MBTI 画像（轻量：不含测评附录，供快捷问句/runtime 嵌入等避免重型查询与异常导致 500）
     */
    public static function fetchUserContextLight(int $userId): array
    {
        $empty = ['mbtiType' => '', 'summary' => '', 'nickname' => '', 'testAppendix' => ''];
        if ($userId <= 0) {
            return $empty;
        }

        try {
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

            return [
                'mbtiType'     => $mbtiType,
                'summary'      => $summary,
                'nickname'     => $nickname,
                'testAppendix' => '',
            ];
        } catch (\Throwable $e) {
            Log::warning('fetchUserContextLight: ' . $e->getMessage());

            return $empty;
        }
    }

    /**
     * 拉取用户 MBTI 画像（最近一次 test_results 结果）
     */
    public static function fetchUserContext(int $userId): array
    {
        $base = self::fetchUserContextLight($userId);
        if ($userId <= 0) {
            return $base;
        }

        $testAppendix = '';
        try {
            // 对话场景不传逐题选项，避免模型照抄冗长问卷，仍走真实模型与类型/得分摘要
            $testAppendix = self::buildLatestTestsAppendix($userId, false);
        } catch (\Throwable $e) {
            Log::warning('fetchUserContext buildLatestTestsAppendix: ' . $e->getMessage());
        }

        $base['testAppendix'] = $testAppendix;

        return $base;
    }

    /**
     * 汇总用户最近一次各类型测评结果，供 system prompt 使用（有长度上限）
     *
     * @param bool $includeAnswerDetail 为 true 时附带逐题选项（仅排查/特殊场景；对话默认 false）
     */
    private static function buildLatestTestsAppendix(int $userId, bool $includeAnswerDetail = false): string
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
            $block = self::formatTestBlockForPrompt($type, $data, $includeAnswerDetail);
            if ($block !== '') {
                $blocks[] = $block;
            }
        }
        $text = implode("\n——\n", $blocks);
        if ($text === '') {
            return '';
        }
        $maxLen = 2800;
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8') . '…（档案已截断）';
        }
        return $text;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatTestBlockForPrompt(string $testType, array $data, bool $includeAnswerDetail = false): string
    {
        switch ($testType) {
            case 'mbti':
                return self::formatMbtiBlockForPrompt($data, $includeAnswerDetail);
            case 'sbti':
                return self::formatSbtiBlockForPrompt($data, $includeAnswerDetail);
            case 'disc':
                return self::formatDiscBlockForPrompt($data, $includeAnswerDetail);
            case 'pdp':
                return self::formatPdpBlockForPrompt($data, $includeAnswerDetail);
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
    private static function formatMbtiBlockForPrompt(array $data, bool $includeAnswerDetail = false): string
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
        if ($includeAnswerDetail) {
            $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
            if ($ans !== '') {
                $lines[] = '逐题选项（题号/ID→所选）：' . $ans;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatSbtiBlockForPrompt(array $data, bool $includeAnswerDetail = false): string
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
        if ($includeAnswerDetail) {
            $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
            if ($ans !== '') {
                $lines[] = '逐题选项：' . $ans;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatDiscBlockForPrompt(array $data, bool $includeAnswerDetail = false): string
    {
        $lines = ['【DISC·最近一次】'];
        if (!empty($data['dominantType'])) {
            $lines[] = '主导：' . (string) $data['dominantType'] . '，次要：' . (string) ($data['secondaryType'] ?? '');
        }
        if (!empty($data['scores']) && is_array($data['scores'])) {
            $lines[] = '得分：' . json_encode($data['scores'], JSON_UNESCAPED_UNICODE);
        }
        if ($includeAnswerDetail) {
            $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
            if ($ans !== '') {
                $lines[] = '逐题选项：' . $ans;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function formatPdpBlockForPrompt(array $data, bool $includeAnswerDetail = false): string
    {
        $lines = ['【PDP·最近一次】'];
        if (!empty($data['dominantType'])) {
            $lines[] = '主导：' . (string) $data['dominantType'] . '，次要：' . (string) ($data['secondaryType'] ?? '');
        }
        if (!empty($data['scores']) && is_array($data['scores'])) {
            $lines[] = '得分：' . json_encode($data['scores'], JSON_UNESCAPED_UNICODE);
        }
        if ($includeAnswerDetail) {
            $ans = self::formatAnswersCompact(isset($data['answers']) && is_array($data['answers']) ? $data['answers'] : null);
            if ($ans !== '') {
                $lines[] = '逐题选项：' . $ans;
            }
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
