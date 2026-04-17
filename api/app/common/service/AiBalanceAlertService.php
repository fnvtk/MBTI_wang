<?php
namespace app\common\service;

use app\model\AiProvider as AiProviderModel;
use think\facade\Db;
use think\facade\Log;

/**
 * AI 服务商欠费预警
 *
 * 复用 `feishu_lead_webhook` 的 webhookUrl，但触发文案独立（不用走 lead 模板）；
 * 每个服务商·每天·同阈值只推一次（ai_balance_alerts 表）。
 *
 * 触发建议：
 * 1) 超管页面手动点击「余额检查」
 * 2) 宝塔/Linux crontab 每天 9:00 + 21:00 POST 调用
 *    curl -X POST -H "Authorization: Bearer {SUPERADMIN_TOKEN}" https://.../api/v1/superadmin/ai/balance-check
 */
class AiBalanceAlertService
{
    /**
     * 扫描所有启用且开启预警的服务商，低于阈值则推飞书
     * @return array{alerted:int,skipped:int,items:array<int,array<string,mixed>>}
     */
    public static function scanAndAlert(): array
    {
        $rows = AiProviderModel::where('enabled', 1)
            ->where('balanceAlertEnabled', 1)
            ->whereNotNull('lastBalance')
            ->select()
            ->toArray();

        $alerted = 0;
        $skipped = 0;
        $items   = [];

        foreach ($rows as $row) {
            $threshold = (float) ($row['balanceAlertThreshold'] ?? 0);
            $balance   = (float) $row['lastBalance'];
            if ($threshold <= 0 || $balance > $threshold) {
                continue;
            }

            $dateStr = date('Y-m-d');
            $dup = Db::name('ai_balance_alerts')
                ->where('providerId', $row['providerId'])
                ->where('dateStr', $dateStr)
                ->find();
            if ($dup) {
                $skipped++;
                $items[] = ['providerId' => $row['providerId'], 'status' => 'dedup'];
                continue;
            }

            $ok = self::pushFeishu($row, $balance, $threshold);
            if ($ok) {
                try {
                    Db::name('ai_balance_alerts')->insert([
                        'providerId' => $row['providerId'],
                        'balance'    => $balance,
                        'threshold'  => $threshold,
                        'currency'   => $row['lastBalanceCurrency'] ?? 'CNY',
                        'alertedAt'  => time(),
                        'dateStr'    => $dateStr,
                    ]);
                } catch (\Throwable $e) {}
                $alerted++;
                $items[] = ['providerId' => $row['providerId'], 'status' => 'alerted', 'balance' => $balance, 'threshold' => $threshold];
            } else {
                $items[] = ['providerId' => $row['providerId'], 'status' => 'send-failed'];
            }
        }

        return ['alerted' => $alerted, 'skipped' => $skipped, 'items' => $items];
    }

    private static function pushFeishu(array $row, float $balance, float $threshold): bool
    {
        $cfg = FeishuLeadWebhookService::getConfig();
        $url = trim((string) ($cfg['webhookUrl'] ?? ''));
        if ($url === '' || stripos($url, 'http') !== 0) {
            Log::warning('AiBalanceAlertService: feishu webhookUrl 未配置');
            return false;
        }

        $currency = ($row['lastBalanceCurrency'] ?? 'CNY') === 'USD' ? '$' : '¥';
        $now      = date('Y-m-d H:i');
        $text = "⚠️ AI 服务余额预警\n"
            . "服务商: {$row['name']} ({$row['providerId']})\n"
            . "当前余额: {$currency}" . number_format($balance, 2) . "\n"
            . "告警阈值: {$currency}" . number_format($threshold, 2) . "\n"
            . "━━━━━━━━━━\n"
            . "神仙 AI 对话 / 面相分析可能受影响，已自动把该服务商降权到故障切换队尾。\n"
            . "请尽快到后台充值：\n"
            . "时间: {$now}";

        $payload = stripos($url, 'qyapi.weixin.qq.com') !== false
            ? ['msgtype' => 'text', 'text' => ['content' => $text]]
            : ['msg_type' => 'text', 'content' => ['text' => $text]];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            Log::warning("AiBalanceAlertService feishu push http={$code}");
            return false;
        }
        if ($body !== false && $body !== '') {
            $resp = json_decode($body, true);
            if (is_array($resp)) {
                if (isset($resp['code']) && (int) $resp['code'] !== 0) return false;
                if (isset($resp['StatusCode']) && (int) $resp['StatusCode'] !== 0) return false;
            }
        }
        return true;
    }
}
