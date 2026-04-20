<?php
/**
 * 神仙 AI 冒烟：与线上 AiChat 相同调用链（AiCallService::chat → OpenAI 兼容 /chat/completions 或 Anthropic）。
 * 用法：在 api 目录下执行 php scripts/smoke_ai_provider.php
 */
declare(strict_types=1);

use app\common\service\AiCallService;
use app\model\AiProvider as AiProviderModel;
use think\App;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App();
$app->initialize();

$row = AiProviderModel::where('enabled', 1)
    ->whereRaw('(visible IS NULL OR visible = 1)')
    ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
    ->order('sortWeight', 'asc')
    ->order('id', 'asc')
    ->find();

$providerHint = $row ? [
    'id'         => (int) $row->id,
    'providerId' => (string) ($row->providerId ?? ''),
    'endpoint'   => trim((string) ($row->apiEndpoint ?? '')) ?: '(默认内置)',
    'model'      => (string) ($row->model ?? ''),
] : null;

$messages = [
    ['role' => 'system', 'content' => AiCallService::buildSystemPrompt([
        'mbtiType'     => 'ENTJ',
        'summary'      => '',
        'nickname'     => '冒烟测试',
        'testAppendix' => '',
    ])],
    [
        'role'    => 'user',
        'content' => '用两三句话回答：ENTJ 常见的一个优势和一个盲点分别是什么？不要以#或@开头，不要人设签名。',
    ],
];

$r = AiCallService::chat($messages, ['temperature' => 0.45, 'maxTokens' => 512]);

$content = trim((string) ($r['content'] ?? ''));
$ok = $content !== '' && empty($r['isDegraded']);

$payload = [
    'first_provider' => $providerHint,
    'api_path'       => strtolower((string) ($row->providerId ?? '')) === 'anthropic'
        ? 'POST {endpoint}/v1/messages (Anthropic)'
        : 'POST {endpoint}/chat/completions (OpenAI 兼容)',
    'isDegraded'     => !empty($r['isDegraded']),
    'providerId'     => (string) ($r['providerId'] ?? ''),
    'content_preview'=> $ok ? mb_substr($content, 0, 280) . (mb_strlen($content) > 280 ? '…' : '') : $content,
    'ok'             => $ok,
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";

exit($ok ? 0 : 1);
