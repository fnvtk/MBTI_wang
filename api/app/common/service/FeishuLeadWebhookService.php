<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 飞书自定义机器人获客推送（对齐 Soul 存客宝卡片：标题/来源/对接人/姓名手机/时间/最近行为）
 * 配置：system_config key=feishu_lead_webhook enterprise_id=0 JSON
 */
class FeishuLeadWebhookService
{
    public const CONFIG_KEY = 'feishu_lead_webhook';

    /** 去重表 scene：飞书获客（与 OutboundPushHookService::DEDUP_SCENE_OUTBOUND 区分） */
    private const DEDUP_SCENE = 'feishu_lead';

    public static function getConfig(): array
    {
        $def = [
            'enabled'         => false,
            'webhookUrl'      => '',
            'contactPerson'   => '运营',
        ];
        $row = Db::name('system_config')
            ->where('key', self::CONFIG_KEY)
            ->where('enterprise_id', 0)
            ->find();
        if (!$row || empty($row['value'])) {
            return $def;
        }
        $v = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
        if (!is_array($v)) {
            return $def;
        }
        return array_merge($def, $v);
    }

    /**
     * 支付成功（含测试付费、充值等）：每订单仅推一次
     */
    public static function onOrderPaid(int $orderDbId, int $userId): void
    {
        if ($orderDbId <= 0 || $userId <= 0) {
            return;
        }
        $dedupKey = 'order_paid:' . $orderDbId;
        $order = Db::name('orders')->where('id', $orderDbId)->find();
        if (!$order) {
            return;
        }
        $productType = (string) ($order['productType'] ?? '');
        $amountFen = (int) ($order['amount'] ?? 0);
        $title = (string) ($order['productTitle'] ?? '');
        $source = self::sourceLabelForOrder($productType, $title, $amountFen);
        self::pushLead([
            'dedupKey'       => $dedupKey,
            'userId'         => $userId,
            'source'         => $source,
            'extraLine'      => '订单号: ' . ($order['orderNo'] ?? '') . ' · 金额: ¥' . number_format($amountFen / 100, 2),
        ]);
    }

    /**
     * 首次绑定手机号（测试完成留资）
     */
    public static function onPhoneBound(int $userId, string $phone): void
    {
        if ($userId <= 0 || trim($phone) === '') {
            return;
        }
        self::pushLead([
            'dedupKey'  => 'phone_bind:' . $userId,
            'userId'    => $userId,
            'source'    => '测试完成·授权手机号',
            'phone'     => $phone,
        ]);
    }

    /**
     * @param array{dedupKey:string,userId:int,source:string,phone?:string,extraLine?:string} $p
     */
    public static function pushLead(array $p): void
    {
        $cfg = self::getConfig();
        if (empty($cfg['enabled'])) {
            return;
        }
        $url = trim((string) ($cfg['webhookUrl'] ?? ''));
        if ($url === '' || stripos($url, 'http') !== 0) {
            return;
        }

        $dedupKey = $p['dedupKey'] ?? '';
        if ($dedupKey === '') {
            return;
        }

        if (!self::beginDedup($dedupKey)) {
            return;
        }

        $userId = (int) ($p['userId'] ?? 0);
        $wu = $userId > 0
            ? Db::name('wechat_users')->where('id', $userId)->field('nickname,phone')->find()
            : null;
        $nickname = trim((string) ($wu['nickname'] ?? ''));
        if ($nickname === '') {
            $nickname = '微信用户';
        }
        $phone = trim((string) ($p['phone'] ?? ($wu['phone'] ?? '')));

        $contact = trim((string) ($cfg['contactPerson'] ?? '运营'));
        if ($contact === '') {
            $contact = '运营';
        }

        $source = (string) ($p['source'] ?? '小程序');
        $now = date('Y-m-d H:i');

        $text = "📋 新获客\n来源: {$source}\n对接人: {$contact}\n━━━━━━━━━━";
        $text .= "\n姓名: {$nickname}";
        if ($phone !== '') {
            $text .= "\n手机: {$phone}";
        }
        $text .= "\n时间: {$now}";
        if (!empty($p['extraLine'])) {
            $text .= "\n" . $p['extraLine'];
        }

        $lines = self::recentBehaviorLines($userId, 8);
        if (count($lines) > 0) {
            $text .= "\n━━━━━━━━━━\n最近行为:";
            $i = 1;
            foreach ($lines as $line) {
                $text .= "\n  {$i}. {$line}";
                $i++;
            }
        }

        $ok = self::postWebhook($url, $text);
        if (!$ok) {
            self::rollbackDedup($dedupKey);
        }
    }

    /**
     * 订单来源文案（飞书「来源」与 HTTP 出站 Hook `sourceLabel` 共用）
     */
    public static function sourceLabelForOrder(string $productType, string $title, int $amountFen): string
    {
        if ($productType === 'recharge') {
            return '企业余额·充值支付成功';
        }
        $map = [
            'face'          => '面相测试',
            'mbti'          => 'MBTI测试',
            'sbti'          => 'SBTI测试',
            'disc'          => 'DISC测试',
            'pdp'           => 'PDP测试',
            'resume'        => '简历分析',
            'report'        => '完整报告',
            'team_analysis' => '团队分析',
            'deep_personal' => '个人深度服务',
            'deep_team'     => '团队深度服务',
            'vip'           => 'VIP',
        ];
        $label = $map[$productType] ?? strtoupper($productType);
        $suffix = '·支付成功';
        if ($title !== '') {
            return $label . '·「' . self::oneLine($title, 40) . '」' . $suffix;
        }
        if ($amountFen === 100) {
            return $label . '·1元支付' . $suffix;
        }
        return $label . $suffix;
    }

    private static function oneLine(string $s, int $max): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        if (mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max) . '…';
        }
        return $s;
    }

    private static function recentBehaviorLines(int $userId, int $limit): array
    {
        if ($userId <= 0) {
            return [];
        }
        try {
            $rows = Db::name('analytics_events')
                ->where('userId', $userId)
                ->order('id', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = self::formatAnalyticsLine($r);
        }
        return $out;
    }

    private static function formatAnalyticsLine(array $r): string
    {
        $name = (string) ($r['eventName'] ?? '');
        $path = trim((string) ($r['pagePath'] ?? ''));
        $props = [];
        if (!empty($r['propsJson'])) {
            $decoded = is_string($r['propsJson']) ? json_decode($r['propsJson'], true) : [];
            $props = is_array($decoded) ? $decoded : [];
        }
        $labelMap = [
            'page_view'     => '浏览页面',
            'button_click'  => '按钮点击',
            'click_pay'     => '发起支付',
            'click_recharge'=> '点击充值',
        ];
        $label = $labelMap[$name] ?? $name;
        $detail = '';
        if ($name === 'page_view' && $path !== '') {
            $detail = $path;
        }
        if (isset($props['action']) && (string) $props['action'] !== '') {
            $detail = (string) $props['action'];
            if (!empty($props['productType'])) {
                $detail .= ' · ' . (string) $props['productType'];
            }
        } elseif (isset($props['label']) && (string) $props['label'] !== '') {
            $detail = (string) $props['label'];
        } elseif ($path !== '' && $detail === '') {
            $detail = $path;
        }
        $line = $detail !== '' ? "{$label}: {$detail}" : $label;
        $ts = isset($r['clientTs']) ? (int) $r['clientTs'] : null;
        if (!$ts && !empty($r['createdAt'])) {
            $ts = strtotime((string) $r['createdAt']) * 1000;
        }
        if ($ts) {
            $line .= ' · ' . self::humanTimeAgoCn((int) round($ts));
        }
        return $line;
    }

    private static function humanTimeAgoCn(int $clientTsMs): string
    {
        $now = (int) (microtime(true) * 1000);
        $sec = max(0, (int) (($now - $clientTsMs) / 1000));
        if ($sec < 60) {
            return '刚刚';
        }
        if ($sec < 3600) {
            return (int) floor($sec / 60) . '分钟前';
        }
        if ($sec < 86400) {
            return (int) floor($sec / 3600) . '小时前';
        }
        return (int) floor($sec / 86400) . '天前';
    }

    private static function beginDedup(string $dedupKey): bool
    {
        try {
            Db::name('delivery_dedup')->insert([
                'scene'     => self::DEDUP_SCENE,
                'dedupKey'  => $dedupKey,
                'createdAt' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function rollbackDedup(string $dedupKey): void
    {
        try {
            Db::name('delivery_dedup')
                ->where('scene', self::DEDUP_SCENE)
                ->where('dedupKey', $dedupKey)
                ->delete();
        } catch (\Throwable $e) {
        }
    }

    private static function postWebhook(string $url, string $text): bool
    {
        $payload = [];
        if (stripos($url, 'qyapi.weixin.qq.com') !== false) {
            $payload = [
                'msgtype' => 'text',
                'text'    => ['content' => $text],
            ];
        } else {
            $payload = [
                'msg_type' => 'text',
                'content'  => ['text' => $text],
            ];
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            return false;
        }
        if ($body !== false && $body !== '') {
            $resp = json_decode($body, true);
            if (is_array($resp)) {
                if (isset($resp['code']) && (int) $resp['code'] !== 0) {
                    return false;
                }
                if (isset($resp['StatusCode']) && (int) $resp['StatusCode'] !== 0) {
                    return false;
                }
            }
        }
        return true;
    }
}
