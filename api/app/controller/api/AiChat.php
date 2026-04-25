<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\AiCallService;
use app\common\service\AiChatArticleDisplayService;
use app\common\service\OutboundPushHookService;
use app\common\service\ResumeFileExtractService;
use app\common\service\SoulArticleService;
use app\model\AiConversation as AiConversationModel;
use app\model\AiMessage as AiMessageModel;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * 神仙 AI 聊天接口（小程序侧）
 *
 * 路由：/api/ai/*
 * 鉴权：auth（微信登录后 token）
 */
class AiChat extends BaseController
{
    /** 单次对话最多携带的历史上下文（user+assistant 轮数）；减小可降低上游耗时 */
    private const CONTEXT_TURNS = 5;

    /** 异步任务缓存 TTL（秒）；须大于模型最坏耗时 */
    private const CHAT_JOB_TTL = 900;

    private static function chatJobCacheKey(int $userId, string $jobId): string
    {
        return 'ai_chat_job:' . $userId . ':' . $jobId;
    }

    /** 任务状态写入 MySQL，避免多机环境下文件 Cache 不一致导致轮询失败 */
    private static function jobTableTryInsertRunning(int $userId, string $jobId, int $conversationId): void
    {
        try {
            $now = time();
            Db::name('ai_chat_jobs')->insert([
                'userId'         => $userId,
                'jobId'          => $jobId,
                'conversationId' => $conversationId,
                'status'         => 'running',
                'resultJson'     => null,
                'errorMessage'   => null,
                'createdAt'      => $now,
                'updatedAt'      => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiChat jobTableTryInsertRunning: ' . $e->getMessage());
        }
    }

    private static function jobTableTrySetDone(int $userId, string $jobId, array $payload): void
    {
        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                Log::error('AiChat jobTableTrySetDone: json_encode failed for jobId=' . $jobId);

                return;
            }
            $n = Db::name('ai_chat_jobs')->where('userId', $userId)->where('jobId', $jobId)->update([
                'status'     => 'done',
                'resultJson' => $json,
                'updatedAt'  => time(),
            ]);
            if ($n < 1) {
                Log::error('AiChat jobTableTrySetDone: no row updated userId=' . $userId . ' jobId=' . $jobId);
            }
        } catch (\Throwable $e) {
            Log::error('AiChat jobTableTrySetDone: ' . $e->getMessage());
        }
    }

    private static function jobTableTrySetError(int $userId, string $jobId, int $conversationId, string $err): void
    {
        try {
            $msg = mb_substr($err, 0, 500, 'UTF-8');
            $n   = Db::name('ai_chat_jobs')->where('userId', $userId)->where('jobId', $jobId)->update([
                'status'         => 'error',
                'errorMessage'   => $msg,
                'conversationId' => $conversationId,
                'updatedAt'      => time(),
            ]);
            if ($n < 1) {
                Log::error('AiChat jobTableTrySetError: no row updated userId=' . $userId . ' jobId=' . $jobId);
            }
        } catch (\Throwable $e) {
            Log::error('AiChat jobTableTrySetError: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed>|null */
    private static function jobTableTryFetch(int $userId, string $jobId): ?array
    {
        try {
            $row = Db::name('ai_chat_jobs')->where('userId', $userId)->where('jobId', $jobId)->find();
            if (!$row) {
                return null;
            }

            return is_object($row) ? $row->toArray() : (array) $row;
        } catch (\Throwable $e) {
            Log::warning('AiChat jobTableTryFetch: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param array<string, mixed> $row DB 行或 Cache 数组
     * @param 'db'|'cache'        $source
     */
    private function respondChatJobPayload(array $row, string $source)
    {
        $st = (string) ($row['status'] ?? '');
        if ($st === 'running') {
            return success([
                'pending'          => true,
                'conversationId' => (int) ($row['conversationId'] ?? 0),
                'usageToday'       => 0,
                'dailyLimit'       => 0,
            ]);
        }
        if ($st === 'done') {
            if ($source === 'db') {
                $raw  = $row['resultJson'] ?? '';
                $data = is_string($raw) ? json_decode($raw, true) : null;
            } else {
                $data = $row['data'] ?? null;
            }
            if (!is_array($data)) {
                return error('任务结果异常', 500);
            }

            return success($data);
        }
        if ($st === 'error') {
            $err = $source === 'db'
                ? (string) ($row['errorMessage'] ?? '')
                : (string) ($row['error'] ?? '');
            Log::warning('AiChat chatJobStatus error job: ' . $err);

            return success([
                'pending'          => false,
                'conversationId'   => (int) ($row['conversationId'] ?? 0),
                'message'          => [
                    'id'           => 0,
                    'role'         => 'assistant',
                    'content'      => '小神仙这边出了点状况，请稍后再试～',
                    'providerId'   => 'degrade',
                    'isDegraded'   => true,
                    'createdAt'    => time(),
                ],
                'usageToday' => 0,
                'dailyLimit' => 0,
            ]);
        }

        return error('任务状态未知', 500);
    }

    /**
     * POST /api/ai/chat
     * body: { conversationId?: int, message: string, resumeFileUrl?: string, resumeFileName?: string }
     *
     * 小程序真机 wx.request 约 60s 上限，大模型单次同步易超时；此处固定走异步：
     * 立即返回 { async, jobId, conversationId }，客户端轮询 GET /api/ai/chat/job?jobId=
     * 完成后 data 与旧版同步接口一致：{ conversationId, message, usageToday, dailyLimit }
     */
    public function chat()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('对话功能升级中，请稍后再试', 503);
        }

        // param 合并 post/json 体，避免个别网关/客户端下 post 键未进 $_POST 的边角情况
        $message = trim((string) Request::param('message', ''));
        $resumeFileUrl = trim((string) Request::param('resumeFileUrl', ''));
        $resumeFileName = trim((string) Request::param('resumeFileName', ''));

        $resumeExtra = '';
        if ($resumeFileUrl !== '') {
            if (!filter_var($resumeFileUrl, FILTER_VALIDATE_URL)) {
                return error('简历地址无效', 400);
            }
            $resumeText = ResumeFileExtractService::extractFromUrl($resumeFileUrl);
            $resumeText = trim(preg_replace('/\s+/u', ' ', $resumeText));
            if ($resumeText === '') {
                $resumeText = '（未能从文件中提取可读正文；若为扫描件或图片简历，请你在下一条消息里补充关键经历。）';
            }
            $cap = 8000;
            if (mb_strlen($resumeText, 'UTF-8') > $cap) {
                $resumeText = mb_substr($resumeText, 0, $cap, 'UTF-8') . "\n…（正文已截断）";
            }
            $fnHint = $resumeFileName !== '' ? '《' . $resumeFileName . '》' : '简历附件';
            $resumeExtra = "\n\n【" . $fnHint . " · 系统提取正文】\n" . $resumeText;
        }

        if ($message === '' && $resumeExtra === '') {
            return error('消息不能为空', 400);
        }

        $fullMessage = $message;
        if ($resumeExtra !== '') {
            if ($fullMessage === '') {
                $fullMessage = '我刚上传了简历'
                    . ($resumeFileName !== '' ? '「' . $resumeFileName . '」' : '')
                    . '，请结合我已有测评类型，帮我做简历诊断：优势、匹配岗位、改进建议与下一步行动。';
            }
            $fullMessage .= $resumeExtra;
        }

        if (mb_strlen($fullMessage, 'UTF-8') > 12000) {
            return error('简历内容过长，请换较短文件或分段说明', 400);
        }
        if ($resumeExtra === '' && mb_strlen($message, 'UTF-8') > 800) {
            return error('消息过长，请精简到 800 字以内', 400);
        }

        $conversationId = (int) Request::param('conversationId', 0);

        // 每日对话条数：已永久取消（不设上限、不计数、不返回 429）。

        $userMsgSaved = false;
        $conversation = null;
        try {
            // 拉取/新建对话（测评档案组装失败时不阻断聊天）
            $userContext = [
                'mbtiType' => '', 'summary' => '', 'nickname' => '', 'testAppendix' => '',
            ];
            // 首包须尽快返回 { jobId }；完整测评附录仅在异步 executeAssistantTurn 中拉取，避免 buildLatestTestsAppendix 拖垮 wx.request
            try {
                $userContext = AiCallService::fetchUserContextLight($userId);
            } catch (\Throwable $e) {
                Log::warning('AiChat fetchUserContextLight: ' . $e->getMessage());
            }

            $conversation = $conversationId > 0
                ? AiConversationModel::where('userId', $userId)->where('id', $conversationId)->find()
                : null;

            $now = time();
            if (!$conversation) {
                $title = mb_substr($message !== '' ? $message : $fullMessage, 0, 24, 'UTF-8');
                if ($title === false) {
                    $title = mb_substr($message !== '' ? $message : $fullMessage, 0, 24);
                }
                if (!is_string($title) || $title === '') {
                    $title = '新对话';
                }
                $conversation = AiConversationModel::create([
                    'userId'        => $userId,
                    'title'         => $title,
                    'mbtiType'      => $userContext['mbtiType'] ?: '',
                    'providerId'    => '',
                    'lastMessageAt' => $now,
                    'messageCount'  => 0,
                ]);
                $conversationId = (int) $conversation->id;
            }

            // 写入 user 消息（含简历正文时 content 较长，供异步轮与模型上下文一致）
            AiMessageModel::create([
                'conversationId' => $conversationId,
                'role'             => 'user',
                'content'          => $fullMessage,
                'tokensIn'         => 0,
                'tokensOut'        => 0,
                'providerId'       => '',
                'isDegraded'       => 0,
                'createdAt'        => $now,
            ]);
            $userMsgSaved = true;

            $jobId = bin2hex(random_bytes(16));
            $jobKey = self::chatJobCacheKey($userId, $jobId);
            Cache::set($jobKey, [
                'status'           => 'running',
                'conversationId' => $conversationId,
                'userId'           => $userId,
            ], self::CHAT_JOB_TTL);
            self::jobTableTryInsertRunning($userId, $jobId, $conversationId);

            // 优先：与本站 InternalPushHook 同源的自建 HTTP 异步（独立 FPM 请求，完整 max_execution_time）
            $dispatched = OutboundPushHookService::triggerAiChatDeferredJob($userId, $conversationId, $jobId);
            if (!$dispatched) {
                $uidF = $userId;
                $cidF = $conversationId;
                $jidF = $jobId;
                register_shutdown_function(static function () use ($uidF, $cidF, $jidF) {
                    self::runDeferredChatJob($uidF, $cidF, $jidF);
                });
                Log::warning('AiChat: internal async dispatch failed, fallback to shutdown handler');
            }

            return success([
                'async'            => true,
                'jobId'            => $jobId,
                'conversationId'   => $conversationId,
                'usageToday'       => 0,
                'dailyLimit'       => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('AiChat::chat 异常: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            // 用户消息已落库时：降级返回一条助手回复，避免前端只看到 500
            if ($userMsgSaved && $conversationId > 0 && $conversation) {
                try {
                    $assistantContent = '小神仙这边刚刚抖了一下（已自动记录）。你可以把问题缩短一点重发，或稍后再试～';
                    $msgRow = AiMessageModel::create([
                        'conversationId' => $conversationId,
                        'role'             => 'assistant',
                        'content'          => $assistantContent,
                        'tokensIn'         => 0,
                        'tokensOut'        => 0,
                        'providerId'       => 'degrade',
                        'isDegraded'       => 1,
                        'createdAt'        => time(),
                    ]);
                    $conversation->lastMessageAt = time();
                    $conversation->messageCount = AiMessageModel::where('conversationId', $conversationId)->count();
                    $conversation->providerId = 'degrade';
                    $conversation->save();

                    return success([
                        'conversationId' => $conversationId,
                        'message' => [
                            'id'         => (int) $msgRow->id,
                            'role'       => 'assistant',
                            'content'    => $assistantContent,
                            'providerId' => 'degrade',
                            'isDegraded' => true,
                            'createdAt'  => (int) $msgRow->createdAt,
                        ],
                        'usageToday' => 0,
                        'dailyLimit' => 0,
                    ]);
                } catch (\Throwable $e2) {
                    Log::error('AiChat::chat 降级回复写入失败: ' . $e2->getMessage());
                }
            }

            return $this->chatDegradeResponse(
                $conversationId,
                0,
                '小神仙服务暂时不可用，请稍后再试。如反复出现可在「我的」联系客服～'
            );
        }
    }

    /**
     * GET /api/ai/chat/job?jobId=
     * 查询异步聊天任务；running 时 data.pending=true，完成时 data 与旧版 chat 成功响应一致（含 message）
     */
    public function chatJobStatus()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return error('请先登录', 401);
        }

        $jobId = trim((string) Request::get('jobId', ''));
        if ($jobId === '' || strlen($jobId) > 64 || !preg_match('/^[a-f0-9]+$/', $jobId)) {
            return error('任务无效', 400);
        }

        $jobKey = self::chatJobCacheKey($userId, $jobId);
        $dbRow = self::jobTableTryFetch($userId, $jobId);
        $cacheRow = Cache::get($jobKey);
        if (is_array($cacheRow) && (int) ($cacheRow['userId'] ?? 0) === $userId) {
            $cSt = (string) ($cacheRow['status'] ?? '');
            if ($cSt === 'done' || $cSt === 'error') {
                $dbSt = $dbRow ? (string) ($dbRow['status'] ?? '') : '';
                // 多机/DB 更新失败时：库中仍 running，但本机 Cache 已写入终态 → 以 Cache 为准，避免永久 pending
                if ($dbSt === '' || $dbSt === 'running') {
                    return $this->respondChatJobPayload($cacheRow, 'cache');
                }
            }
        }
        if ($dbRow) {
            if ((int) ($dbRow['userId'] ?? 0) !== $userId) {
                return error('任务不存在或已过期', 404);
            }

            return $this->respondChatJobPayload($dbRow, 'db');
        }

        $row = $cacheRow;
        if ($row === null || $row === false) {
            return error('任务不存在或已过期', 404);
        }
        if (!is_array($row)) {
            return error('任务状态异常', 500);
        }
        if ((int) ($row['userId'] ?? 0) !== $userId) {
            return error('任务不存在或已过期', 404);
        }

        return $this->respondChatJobPayload($row, 'cache');
    }

    /**
     * 在 PHP 向客户端输出完毕后的 shutdown 阶段调用模型，避免占用 HTTP 连接 60s+
     *
     * @internal
     */
    public static function runDeferredChatJob(int $userId, int $conversationId, string $jobId): void
    {
        @ignore_user_abort(true);
        @set_time_limit(300);

        $jobKey = self::chatJobCacheKey($userId, $jobId);

        if (miniprogram_audit_mode_on()) {
            $msg = '当前为提审模式，对话暂不可用';
            self::jobTableTrySetError($userId, $jobId, $conversationId, $msg);
            Cache::set($jobKey, [
                'status'           => 'error',
                'userId'           => $userId,
                'conversationId'   => $conversationId,
                'error'            => $msg,
            ], self::CHAT_JOB_TTL);

            return;
        }

        try {
            $payload = self::executeAssistantTurn($userId, $conversationId);
            self::jobTableTrySetDone($userId, $jobId, $payload);
            Cache::set($jobKey, [
                'status'           => 'done',
                'userId'           => $userId,
                'conversationId'   => $conversationId,
                'data'             => $payload,
            ], self::CHAT_JOB_TTL);
        } catch (\Throwable $e) {
            Log::error('AiChat::runDeferredChatJob: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            $conversation = null;
            if ($conversationId > 0) {
                try {
                    $conversation = AiConversationModel::where('userId', $userId)
                        ->where('id', $conversationId)
                        ->find();
                } catch (\Throwable $e2) {
                    Log::warning('runDeferredChatJob reload conversation: ' . $e2->getMessage());
                }
            }

            if ($conversation) {
                try {
                    $assistantContent = '小神仙这边刚刚抖了一下（已自动记录）。你可以把问题缩短一点重发，或稍后再试～';
                    $msgRow = AiMessageModel::create([
                        'conversationId' => $conversationId,
                        'role'             => 'assistant',
                        'content'          => $assistantContent,
                        'tokensIn'         => 0,
                        'tokensOut'        => 0,
                        'providerId'       => 'degrade',
                        'isDegraded'       => 1,
                        'createdAt'        => time(),
                    ]);
                    $conversation->lastMessageAt = time();
                    $conversation->messageCount = AiMessageModel::where('conversationId', $conversationId)->count();
                    $conversation->providerId = 'degrade';
                    $conversation->save();

                    $donePayload = [
                        'conversationId' => $conversationId,
                        'message'        => [
                            'id'         => (int) $msgRow->id,
                            'role'       => 'assistant',
                            'content'    => $assistantContent,
                            'providerId' => 'degrade',
                            'isDegraded' => true,
                            'createdAt'  => (int) $msgRow->createdAt,
                        ],
                        'usageToday' => 0,
                        'dailyLimit' => 0,
                    ];
                    self::jobTableTrySetDone($userId, $jobId, $donePayload);
                    Cache::set($jobKey, [
                        'status'           => 'done',
                        'userId'           => $userId,
                        'conversationId'   => $conversationId,
                        'data'             => $donePayload,
                    ], self::CHAT_JOB_TTL);

                    return;
                } catch (\Throwable $e3) {
                    Log::error('AiChat::runDeferredChatJob 降级写入失败: ' . $e3->getMessage());
                }
            }

            self::jobTableTrySetError($userId, $jobId, $conversationId, $e->getMessage());
            Cache::set($jobKey, [
                'status'           => 'error',
                'userId'           => $userId,
                'conversationId'   => $conversationId,
                'error'            => $e->getMessage(),
            ], self::CHAT_JOB_TTL);
        }
    }

    /**
     * 基于已落库的 user 消息调用模型并写入 assistant（与原先同步 chat 核心逻辑一致）
     *
     * @return array{conversationId:int, message:array, usageToday:int, dailyLimit:int}
     */
    private static function executeAssistantTurn(int $userId, int $conversationId): array
    {
        $conversation = AiConversationModel::where('userId', $userId)->where('id', $conversationId)->find();
        if (!$conversation) {
            throw new \RuntimeException('会话不存在');
        }

        $userContext = [
            'mbtiType' => '', 'summary' => '', 'nickname' => '', 'testAppendix' => '',
        ];
        try {
            $userContext = AiCallService::fetchUserContext($userId);
        } catch (\Throwable $e) {
            Log::warning('AiChat executeAssistantTurn fetchUserContext: ' . $e->getMessage());
        }

        try {
            $systemPrompt = AiCallService::buildSystemPrompt($userContext);
        } catch (\Throwable $e) {
            Log::warning('AiChat executeAssistantTurn buildSystemPrompt: ' . $e->getMessage());
            $systemPrompt = "你是「神仙 AI」，性格与成长助手。用完整通顺、像真人深聊的语气直接回答；禁止机械套话、禁止自称任何回复格式或版本名；禁止医疗诊断与投资建议。\n";
        }

        $history = AiMessageModel::where('conversationId', $conversationId)
            ->order('id', 'desc')
            ->limit(self::CONTEXT_TURNS * 2)
            ->select()
            ->toArray();
        $history = array_reverse($history);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $row) {
            if (!in_array($row['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = ['role' => $row['role'], 'content' => (string) $row['content']];
        }

        $r = AiCallService::chat($messages, ['temperature' => 0.7, 'maxTokens' => 1024]);
        $assistantContent = $r['content'] !== '' ? $r['content'] : '（小神仙被问住了，换个问法试试？）';

        $msgRow = AiMessageModel::create([
            'conversationId' => $conversationId,
            'role'             => 'assistant',
            'content'          => $assistantContent,
            'tokensIn'         => (int) ($r['tokensIn'] ?? 0),
            'tokensOut'        => (int) ($r['tokensOut'] ?? 0),
            'providerId'       => (string) ($r['providerId'] ?? ''),
            'isDegraded'       => !empty($r['isDegraded']) ? 1 : 0,
            'createdAt'        => time(),
        ]);

        $conversation->lastMessageAt = time();
        $conversation->messageCount = AiMessageModel::where('conversationId', $conversationId)->count();
        $conversation->providerId = (string) ($r['providerId'] ?? '');
        $conversation->save();

        return [
            'conversationId' => $conversationId,
            'message'          => [
                'id'         => (int) $msgRow->id,
                'role'       => 'assistant',
                'content'    => $assistantContent,
                'providerId' => $r['providerId'] ?? '',
                'isDegraded' => (bool) ($r['isDegraded'] ?? false),
                'createdAt'  => (int) $msgRow->createdAt,
            ],
            'usageToday' => 0,
            'dailyLimit' => 0,
        ];
    }

    /**
     * 聊天失败时仍返回 HTTP JSON code=200，由 message.isDegraded 标记，小程序可正常展示气泡
     *
     * @param int    $conversationId 当前会话 id（未知则 0）
     * @param int    $usageToday       今日已计次数（失败时可能为 0）
     * @param string $assistantText    助手可见文案
     */
    private function chatDegradeResponse(int $conversationId, int $usageToday, string $assistantText)
    {
        $now = time();

        return success([
            'conversationId' => $conversationId,
            'message'        => [
                'id'           => 0,
                'role'         => 'assistant',
                'content'      => $assistantText,
                'providerId'   => 'degrade',
                'isDegraded'   => true,
                'createdAt'    => $now,
            ],
            'usageToday' => max(0, $usageToday),
            'dailyLimit' => 0,
        ]);
    }

    /**
     * GET /api/ai/articles/recommended
     * 无需登录（也可以；这里先要求登录保持一致）
     *
     * 兼容：usage=profile 时返回与 profileArticleTeaser 相同结构（我的页底部推荐），
     * 避免部分线上 Nginx 对「profile-teaser」路径返回 404，而 recommended 已放行。
     */
    public function recommendedArticles()
    {
        if (miniprogram_audit_mode_on()) {
            return success([
                'list'      => [],
                'recoCount' => 0,
                'display'   => [
                    'enabled'                => false,
                    'maxShow'                => 0,
                    'sectionExpandedDefault'   => false,
                    'profileSectionLabel'      => '',
                    'recoJumpMiniAppId'        => '',
                    'recoJumpMiniPath'         => '',
                    'recoJumpMiniEnvVersion'   => 'release',
                    'inlineRecoMinUserTurns'   => 2,
                    'inlineRecoInterval'       => 3,
                    'inlineRecoRoll'           => 0.0,
                    'inlineRecoIconCount'      => 0,
                    'inlineRecoIcons'          => [],
                ],
            ]);
        }

        $usage = trim((string) Request::get('usage', ''));
        if ($usage === 'profile') {
            return $this->profileArticleTeaser();
        }

        $display = AiChatArticleDisplayService::getSettings();
        $rows    = [];
        if ($display['enabled']) {
            try { SoulArticleService::autoSyncIfStale(); } catch (\Throwable $e) {}
            $limit = (int) $display['maxShow'];
            $rows = SoulArticleService::getRecommended($limit);
            if (empty($rows)) {
                try { SoulArticleService::syncLatest(10, 'MBTI'); } catch (\Throwable $e) {}
                $rows = SoulArticleService::getRecommended($limit);
            }
        }

        $resp = success([
            'list'       => $rows,
            'recoCount'  => count($rows),
            'display'    => [
                'enabled'                  => $display['enabled'],
                'maxShow'                  => $display['maxShow'],
                'sectionExpandedDefault'   => $display['sectionExpandedDefault'],
                'profileSectionLabel'      => $display['profileSectionLabel'] ?? '',
                'recoJumpMiniAppId'        => $display['recoJumpMiniAppId'] ?? '',
                'recoJumpMiniPath'         => $display['recoJumpMiniPath'] ?? '',
                'recoJumpMiniEnvVersion'   => $display['recoJumpMiniEnvVersion'] ?? 'release',
                'inlineRecoMinUserTurns'   => (int) ($display['inlineRecoMinUserTurns'] ?? 2),
                'inlineRecoInterval'       => (int) ($display['inlineRecoInterval'] ?? 3),
                'inlineRecoRoll'           => (float) ($display['inlineRecoRoll'] ?? 0.5),
                'inlineRecoIconCount'      => (int) ($display['inlineRecoIconCount'] ?? 3),
                'inlineRecoIcons'          => $display['inlineRecoIcons'] ?? ['✨', '💬', '📌'],
            ],
        ]);
        if (method_exists($resp, 'header')) {
            $resp->header([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ]);
        }
        return $resp;
    }

    /**
     * GET /api/ai/articles/profile-teaser
     * 我的页底部：展示后台「当前推荐」排序第一的一篇 + 可配区块标题（无需登录）
     */
    public function profileArticleTeaser()
    {
        if (miniprogram_audit_mode_on()) {
            return success([
                'enabled'                => false,
                'sectionLabel'           => '',
                'article'                => null,
                'recoJumpMiniAppId'      => '',
                'recoJumpMiniPath'       => '',
                'recoJumpMiniEnvVersion' => 'release',
            ]);
        }

        try {
            $display = AiChatArticleDisplayService::getSettings();
            if (empty($display['profileRecoEnabled'])) {
                $resp = success([
                    'enabled'                => false,
                    'sectionLabel'           => $display['profileSectionLabel'] ?? '',
                    'article'                => null,
                    'recoJumpMiniAppId'      => $display['recoJumpMiniAppId'] ?? '',
                    'recoJumpMiniPath'       => $display['recoJumpMiniPath'] ?? '',
                    'recoJumpMiniEnvVersion' => $display['recoJumpMiniEnvVersion'] ?? 'release',
                ]);
            } else {
                try {
                    SoulArticleService::autoSyncIfStale();
                } catch (\Throwable $e) {
                }
                $rows = SoulArticleService::getRecommended(1);
                if (empty($rows)) {
                    try {
                        SoulArticleService::syncLatest(10, 'MBTI');
                    } catch (\Throwable $e) {
                    }
                    $rows = SoulArticleService::getRecommended(1);
                }
                $article = !empty($rows[0]) ? $rows[0] : null;
                $resp    = success([
                    'enabled'                => true,
                    'sectionLabel'           => $display['profileSectionLabel'] ?? '',
                    'article'                => $article,
                    'recoJumpMiniAppId'      => $display['recoJumpMiniAppId'] ?? '',
                    'recoJumpMiniPath'       => $display['recoJumpMiniPath'] ?? '',
                    'recoJumpMiniEnvVersion' => $display['recoJumpMiniEnvVersion'] ?? 'release',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('profileArticleTeaser: ' . $e->getMessage());
            $resp = success([
                'enabled'                => false,
                'sectionLabel'           => '',
                'article'                => null,
                'recoJumpMiniAppId'      => '',
                'recoJumpMiniPath'       => '',
                'recoJumpMiniEnvVersion' => 'release',
            ]);
        }
        if (method_exists($resp, 'header')) {
            $resp->header([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ]);
        }

        return $resp;
    }

    /**
     * GET /api/ai/quick-questions
     * 基于当前用户 MBTI 类型返回快捷提问
     */
    public function quickQuestions()
    {
        if (miniprogram_audit_mode_on()) {
            return success([
                'mbtiType'  => '',
                'nickname'  => '',
                'questions' => [],
            ]);
        }

        $emptyPayload = [
            'mbtiType'  => '',
            'nickname'  => '',
            'questions' => [
                '我应该找什么样的工作？',
                '我适合什么样的伴侣？',
                '我的职业发展方向是什么？',
                '我最近有点迷茫，有什么建议？',
                '帮我做一个简短的自我介绍',
                '我有哪些需要警惕的盲点？',
            ],
        ];

        try {
            // 公开接口：无中间件时从 Authorization 解析微信用户（可选）
            $userId = $this->currentUserId();
            if ($userId <= 0) {
                $u = $this->resolveUser();
                if ($u && ($u['source'] ?? '') === 'wechat') {
                    $userId = (int) ($u['user_id'] ?? $u['userId'] ?? 0);
                }
            }
            $ctx = AiCallService::fetchUserContextLight($userId);
            $qs  = AiCallService::filterQuickQuestions(AiCallService::quickQuestions($ctx['mbtiType'] ?? ''));

            return success([
                'mbtiType' => $ctx['mbtiType'] ?? '',
                'nickname' => $ctx['nickname'] ?? '',
                'questions' => $qs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('quickQuestions: ' . $e->getMessage());
        }

        try {
            $qs = AiCallService::filterQuickQuestions(AiCallService::quickQuestions(''));

            return success([
                'mbtiType' => '',
                'nickname' => '',
                'questions' => $qs,
            ]);
        } catch (\Throwable $e2) {
            Log::warning('quickQuestions fallback: ' . $e2->getMessage());
        }

        return success($emptyPayload);
    }

    /**
     * GET /api/ai/conversations  分页取当前用户会话列表
     * query: page, pageSize
     */
    public function conversations()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            $page = max(1, (int) Request::get('page', 1));
            $pageSize = min(50, max(1, (int) Request::get('pageSize', 20)));

            return paginate_response([], 0, $page, $pageSize);
        }

        $page = max(1, (int) Request::get('page', 1));
        $pageSize = min(50, max(1, (int) Request::get('pageSize', 20)));

        $query = AiConversationModel::where('userId', $userId);
        $total = $query->count();
        $list = $query
            ->order('lastMessageAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        return paginate_response($list, $total, $page, $pageSize);
    }

    /**
     * GET /api/ai/conversations/:id/messages
     */
    public function messages()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('会话不存在', 404);
        }

        $cid = (int) Request::param('id', 0);
        $conversation = AiConversationModel::where('userId', $userId)->where('id', $cid)->find();
        if (!$conversation) return error('会话不存在', 404);

        $rows = AiMessageModel::where('conversationId', $cid)
            ->order('id', 'asc')
            ->limit(200)
            ->select()
            ->toArray();

        $list = array_map(function ($r) {
            return [
                'id'         => (int) $r['id'],
                'role'       => $r['role'],
                'content'    => $r['content'],
                'providerId' => $r['providerId'],
                'isDegraded' => (bool) $r['isDegraded'],
                'createdAt'  => (int) $r['createdAt'],
            ];
        }, $rows);

        return success([
            'conversation' => [
                'id'            => (int) $conversation->id,
                'title'         => (string) $conversation->title,
                'mbtiType'      => (string) $conversation->mbtiType,
                'messageCount'  => (int) $conversation->messageCount,
                'lastMessageAt' => (int) $conversation->lastMessageAt,
            ],
            'messages' => $list,
        ]);
    }

    /**
     * POST /api/ai/transcribe
     * 语音转文字：接受前端 audio 文件上传。
     * 当前占位：若未配置语音识别服务（ai_transcribe_provider），返回 501 + 降级提示，
     * 前端会降级到"请用键盘麦克风"。接入 ASR 后在此填充 provider 调用即可。
     */
    public function transcribe()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);
        if (miniprogram_audit_mode_on()) {
            return error('功能升级中', 503);
        }

        $file = Request::file('audio');
        if (!$file) return error('未收到音频文件', 400);

        // 读取 system_config.ai_transcribe_provider（未配置则降级）
        $cfgRow = Db::name('system_config')->where('key', 'ai_transcribe_provider')->find();
        if (!$cfgRow || empty($cfgRow['value'])) {
            return error('语音识别未开通，请使用键盘麦克风', 501);
        }

        // TODO: 二期对接 ASR（如通义听悟/讯飞/whisper API）
        // 当前阶段：保持降级，避免伪造返回值骗用户
        return error('语音识别通道建设中，请使用键盘麦克风', 501);
    }

    /**
     * POST /api/ai/articles/:id/click
     * 轻量记录点击，用于分析（失败不影响用户）
     */
    public function articleClick()
    {
        $id = (int) Request::param('id', 0);
        if ($id > 0) {
            try {
                Db::name('soul_articles')->where('id', $id)->inc('viewCount')->update();
            } catch (\Throwable $e) {
            }
        }
        return success(null);
    }

    /** 从 auth 中间件挂好的 request->user 中取出当前微信用户 id */
    private function currentUserId(): int
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') return 0;
        return (int) ($user['user_id'] ?? $user['userId'] ?? 0);
    }
}
