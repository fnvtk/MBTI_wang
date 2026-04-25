<?php
namespace app\common\service;

use app\model\AiReport as AiReportModel;
use think\facade\Db;
use think\facade\Log;

/**
 * AI 深度画像报告服务
 *
 * 生命周期：
 *   pending（创建）→ paid（支付成功 / dev：markPaidDev）
 *   → generating（后台开始调 AI） → done（生成完成）
 *   失败时置 failed + 自动重试（retryCount<=3）
 */
class AiReportService
{
    const PRICE_FEN = 990; // 9.9 元

    public static function createOrGetPending(int $userId, int $conversationId = 0, string $mbtiType = ''): array
    {
        // 已有 pending/paid/generating/done → 返回最新那条
        $existing = AiReportModel::where('userId', $userId)
            ->order('id', 'desc')
            ->find();
        if ($existing && in_array($existing->status, ['pending', 'paid', 'generating', 'done'])) {
            // 已完成的报告允许用户再做一份？按默认：只有 done 才允许再发起新的
            if ($existing->status !== 'done') {
                return self::toArray($existing);
            }
        }

        $orderSn = 'AIR' . date('YmdHis') . mt_rand(1000, 9999);
        $model = AiReportModel::create([
            'userId'         => $userId,
            'conversationId' => $conversationId ?: null,
            'mbtiType'       => $mbtiType ?: null,
            'orderSn'        => $orderSn,
            'priceFen'       => self::PRICE_FEN,
            'status'         => 'pending',
        ]);
        return self::toArray($model);
    }

    public static function myLatest(int $userId): ?array
    {
        $m = AiReportModel::where('userId', $userId)->order('id', 'desc')->find();
        return $m ? self::toArray($m) : null;
    }

    public static function get(int $id, int $userId): ?array
    {
        $m = AiReportModel::where('id', $id)->where('userId', $userId)->find();
        return $m ? self::toArray($m) : null;
    }

    /**
     * 支付成功回调：把 report 置为 paid，并触发异步生成
     * 同时执行订单分账（走 ProfitSharingService）
     */
    public static function markPaid(string $orderSn): array
    {
        $m = AiReportModel::where('orderSn', $orderSn)->find();
        if (!$m) return ['status' => 'not-found'];
        if ($m->status !== 'pending') {
            // 已处理过
            return ['status' => 'already', 'reportStatus' => $m->status];
        }

        $m->status = 'paid';
        $m->paidAt = time();
        $m->save();

        // 分账（idempotent）
        try {
            ProfitSharingService::executeSharing(
                $m->orderSn,
                'ai_deep_report',
                (int) $m->priceFen,
                ['userId' => (int) $m->userId, 'orderId' => (int) $m->id]
            );
        } catch (\Throwable $e) {
            Log::warning('AiReportService profit-sharing failed: ' . $e->getMessage());
        }

        // 同步生成报告（若耗时 >30s 可改异步队列，此处 PHP fpm 环境先同步，失败自动降级）
        self::generate((int) $m->id);

        $fresh = AiReportModel::find($m->id);
        return ['status' => 'ok', 'report' => self::toArray($fresh)];
    }

    /** 调试/内部：跳过支付直接 markPaid */
    public static function markPaidDev(string $orderSn): array
    {
        return self::markPaid($orderSn);
    }

    /**
     * 生成报告正文（调 AI）
     */
    public static function generate(int $reportId): void
    {
        $m = AiReportModel::find($reportId);
        if (!$m) return;
        if (!in_array($m->status, ['paid', 'failed', 'generating'])) return;

        $m->status = 'generating';
        $m->retryCount = (int) $m->retryCount + 1;
        $m->save();

        try {
            $user = Db::name('user')->where('id', (int) $m->userId)->find();
            $mbti = $m->mbtiType ?: ($user['mbti_type'] ?? '未知');
            $nickname = $user['nickname'] ?? '朋友';

            // 拉取最近对话作为输入素材
            $history = [];
            if (!empty($m->conversationId)) {
                $msgs = Db::name('ai_messages')
                    ->where('conversationId', (int) $m->conversationId)
                    ->order('id', 'asc')
                    ->limit(40)
                    ->select()
                    ->toArray();
                foreach ($msgs as $msg) {
                    if ($msg['role'] === 'system') continue;
                    $history[] = ($msg['role'] === 'user' ? '【你】' : '【神仙AI】') . "：" . $msg['content'];
                }
            }

            $system = "你是「神仙 AI」，要基于用户的 MBTI 类型与最近的对话，输出一份具个性化的深度画像报告。\n"
                . "报告要求：\n"
                . "1) 长度 1200-1800 字，分 5 个章节（性格内核 / 优势雷达 / 潜在盲点 / 亲密关系建议 / 下一步成长路径）\n"
                . "2) 每章 2-3 段，语气温暖不说教；禁止玄学与迷信\n"
                . "3) 首段总结一句，用一条「超短金句」概括这个人\n"
                . "4) 使用第二人称「你」，称呼：{$nickname}\n"
                . "5) Markdown 纯文本，章节用 「## 章节标题」 "
                . "";

            $prompt = "【用户 MBTI】{$mbti}\n【最近对话】\n" . (empty($history) ? '（无历史对话）' : implode("\n", $history));

            $r = AiCallService::chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ], ['maxTokens' => 3000, 'temperature' => 0.75]);

            $content = (string) ($r['content'] ?? '');
            if ($content === '' || !empty($r['isDegraded'])) {
                throw new \RuntimeException('AI 生成失败或降级：' . (string) ($r['content'] ?? ''));
            }

            $summary = self::extractSummary($content);
            $title   = self::extractTitle($content, $mbti);

            $m->title       = $title;
            $m->summary     = $summary;
            $m->content     = $content;
            $m->status      = 'done';
            $m->generatedAt = time();
            $m->lastError   = null;
            $m->save();
        } catch (\Throwable $e) {
            Log::warning('AiReportService generate fail: ' . $e->getMessage());
            $m->status    = $m->retryCount >= 3 ? 'failed' : 'paid';
            $m->lastError = mb_substr($e->getMessage(), 0, 480);
            $m->save();
        }
    }

    private static function extractSummary(string $md): string
    {
        $plain = preg_replace('/[#>*_`\[\]]+/', '', $md);
        $plain = trim((string) $plain);
        return mb_substr($plain, 0, 180);
    }

    private static function extractTitle(string $md, string $mbti): string
    {
        if (preg_match('/^\s*#+\s*(.+)$/m', $md, $mch)) {
            return mb_substr(trim($mch[1]), 0, 80);
        }
        return "{$mbti} · 神仙 AI 深度画像";
    }

    private static function toArray(AiReportModel $m): array
    {
        return [
            'id'             => (int) $m->id,
            'userId'         => (int) $m->userId,
            'conversationId' => (int) $m->conversationId,
            'mbtiType'       => $m->mbtiType,
            'orderSn'        => $m->orderSn,
            'priceFen'       => (int) $m->priceFen,
            'priceYuan'      => round($m->priceFen / 100, 2),
            'status'         => $m->status,
            'title'          => $m->title,
            'summary'        => $m->summary,
            'content'        => $m->content,
            'posterUrl'      => $m->posterUrl,
            'paidAt'         => (int) $m->paidAt,
            'generatedAt'    => (int) $m->generatedAt,
            'createdAt'      => (int) $m->createdAt,
        ];
    }
}
