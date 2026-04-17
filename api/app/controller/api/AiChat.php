<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\AiCallService;
use app\common\service\AiChatArticleDisplayService;
use app\common\service\SoulArticleService;
use app\model\AiConversation as AiConversationModel;
use app\model\AiMessage as AiMessageModel;
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
    /** 每用户每日发送上限（超出则返回 429） */
    private const DAILY_LIMIT = 20;

    /** 单次对话最多携带的历史上下文（user+assistant 轮数） */
    private const CONTEXT_TURNS = 8;

    /**
     * POST /api/ai/chat
     * body: { conversationId?: int, message: string }
     * 返回 { conversationId, message: {role, content, providerId, isDegraded, createdAt}, usageToday, dailyLimit }
     */
    public function chat()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);

        $message = trim((string) Request::post('message', ''));
        if ($message === '') return error('消息不能为空', 400);
        if (mb_strlen($message, 'UTF-8') > 800) {
            return error('消息过长，请精简到 800 字以内', 400);
        }

        $conversationId = (int) Request::post('conversationId', 0);

        // 每日限流（必须在 try 外先取 used，失败时返回 200+降级，避免未捕获异常导致 HTTP 500）
        $used = 0;
        try {
            $used = $this->incrementAndCheckDaily($userId, self::DAILY_LIMIT);
        } catch (\Throwable $e) {
            Log::warning('AiChat incrementAndCheckDaily: ' . $e->getMessage());

            return $this->chatDegradeResponse(
                $conversationId,
                0,
                '小神仙这边计数服务抖了一下，请稍后再发一条消息～'
            );
        }
        if ($used === -1) {
            return error('今日对话次数已用完，明天再来找我呀～', 429);
        }

        $userMsgSaved = false;
        $conversation = null;
        try {
            // 拉取/新建对话（测评档案组装失败时不阻断聊天）
            $userContext = [
                'mbtiType' => '', 'summary' => '', 'nickname' => '', 'testAppendix' => '',
            ];
            try {
                $userContext = AiCallService::fetchUserContext($userId);
            } catch (\Throwable $e) {
                Log::warning('AiChat fetchUserContext: ' . $e->getMessage());
            }

            $conversation = $conversationId > 0
                ? AiConversationModel::where('userId', $userId)->where('id', $conversationId)->find()
                : null;

            $now = time();
            if (!$conversation) {
                $title = mb_substr($message, 0, 24, 'UTF-8');
                if ($title === false) {
                    $title = mb_substr($message, 0, 24);
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

            // 写入 user 消息
            AiMessageModel::create([
                'conversationId' => $conversationId,
                'role'             => 'user',
                'content'          => $message,
                'tokensIn'         => 0,
                'tokensOut'        => 0,
                'providerId'       => '',
                'isDegraded'       => 0,
                'createdAt'        => $now,
            ]);
            $userMsgSaved = true;

            // 组装 messages：system + 最近 N 轮历史 + 本次 user
            try {
                $systemPrompt = AiCallService::buildSystemPrompt($userContext);
            } catch (\Throwable $e) {
                Log::warning('AiChat buildSystemPrompt: ' . $e->getMessage());
                $systemPrompt = "你是「神仙 AI」，神仙团队 MBTI 小程序的专属伙伴。请亲切、简短地回答用户；禁止医疗诊断与投资建议。\n";
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

            $r = AiCallService::chat($messages, ['temperature' => 0.75, 'maxTokens' => 1024]);
            $assistantContent = $r['content'] !== '' ? $r['content'] : '（小神仙被问住了，换个问法试试？）';

            // 写入 assistant 消息
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

            // 更新对话元数据
            $conversation->lastMessageAt = time();
            $conversation->messageCount = AiMessageModel::where('conversationId', $conversationId)->count();
            $conversation->providerId = (string) ($r['providerId'] ?? '');
            $conversation->save();

            return success([
                'conversationId' => $conversationId,
                'message' => [
                    'id'         => (int) $msgRow->id,
                    'role'       => 'assistant',
                    'content'    => $assistantContent,
                    'providerId' => $r['providerId'] ?? '',
                    'isDegraded' => (bool) ($r['isDegraded'] ?? false),
                    'createdAt'  => (int) $msgRow->createdAt,
                ],
                'usageToday' => $used,
                'dailyLimit' => self::DAILY_LIMIT,
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
                        'usageToday' => $used,
                        'dailyLimit' => self::DAILY_LIMIT,
                    ]);
                } catch (\Throwable $e2) {
                    Log::error('AiChat::chat 降级回复写入失败: ' . $e2->getMessage());
                }
            }

            // 统一不再返回业务 code=500，前端只认 code=200；避免用户看到「服务异常(500)」
            return $this->chatDegradeResponse(
                $conversationId,
                $used,
                '小神仙服务暂时不可用，请稍后再试。如反复出现可在「我的」联系客服～'
            );
        }
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
            'dailyLimit' => self::DAILY_LIMIT,
        ]);
    }

    /**
     * GET /api/ai/articles/recommended
     * 无需登录（也可以；这里先要求登录保持一致）
     */
    public function recommendedArticles()
    {
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
        $display = AiChatArticleDisplayService::getSettings();
        if (empty($display['profileRecoEnabled'])) {
            $resp = success([
                'enabled'      => false,
                'sectionLabel' => $display['profileSectionLabel'] ?? '',
                'article'      => null,
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
                'enabled'      => true,
                'sectionLabel' => $display['profileSectionLabel'] ?? '',
                'article'      => $article,
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
        // 公开接口：无中间件时从 Authorization 解析微信用户（可选）
        $userId = $this->currentUserId();
        if ($userId <= 0) {
            $u = $this->resolveUser();
            if ($u && ($u['source'] ?? '') === 'wechat') {
                $userId = (int) ($u['user_id'] ?? $u['userId'] ?? 0);
            }
        }
        $ctx = AiCallService::fetchUserContext($userId);
        $qs  = AiCallService::filterQuickQuestions(AiCallService::quickQuestions($ctx['mbtiType']));
        return success([
            'mbtiType' => $ctx['mbtiType'],
            'nickname' => $ctx['nickname'],
            'questions' => $qs,
        ]);
    }

    /**
     * GET /api/ai/conversations  分页取当前用户会话列表
     * query: page, pageSize
     */
    public function conversations()
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) return error('请先登录', 401);

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

    /**
     * 自增今日用量，若已达上限返回 -1
     */
    private function incrementAndCheckDaily(int $userId, int $limit): int
    {
        $dateStr = date('Y-m-d');
        $now = time();

        $row = Db::name('ai_usage_daily')
            ->where('userId', $userId)
            ->where('dateStr', $dateStr)
            ->find();

        if (!$row) {
            try {
                Db::name('ai_usage_daily')->insert([
                    'userId'       => $userId,
                    'dateStr'      => $dateStr,
                    'messageCount' => 1,
                    'createdAt'    => $now,
                    'updatedAt'    => $now,
                ]);
                return 1;
            } catch (\Throwable $e) {
                // 并发首条：另一请求已插入，回读后再自增
                $row = Db::name('ai_usage_daily')
                    ->where('userId', $userId)
                    ->where('dateStr', $dateStr)
                    ->find();
                if (!$row) {
                    throw $e;
                }
            }
        }

        $used = (int) $row['messageCount'];
        if ($used >= $limit) return -1;

        Db::name('ai_usage_daily')
            ->where('id', $row['id'])
            ->update(['messageCount' => $used + 1, 'updatedAt' => $now]);

        return $used + 1;
    }
}
