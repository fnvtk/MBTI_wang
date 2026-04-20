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
     * 若 7 天内有测评记录：与「测评完成」合并为一条纯文本（与出站 Hook 机器人文案一致），不再单独发「首次绑定手机」
     */
    public static function onPhoneBound(int $userId, string $phone): void
    {
        if ($userId <= 0 || trim($phone) === '') {
            return;
        }
        $latest = Db::name('test_results')->where('userId', $userId)->order('id', 'desc')->find();
        if ($latest) {
            $tid = (int) ($latest['id'] ?? 0);
            $testTs = isset($latest['createdAt']) ? (int) $latest['createdAt'] : 0;
            if ($tid > 0 && $testTs > 0 && (time() - $testTs) <= 604800) {
                $boundAt = date('Y-m-d H:i:s');
                $plain = OutboundPushHookService::botPlainTextTestResultCompleted($tid, $phone, $boundAt);
                if ($plain !== null && $plain !== '') {
                    self::pushPlainDedup('merged_test_phone:' . $tid, $plain);

                    return;
                }
            }
        }

        self::pushLead([
            'dedupKey'  => 'phone_bind:' . $userId,
            'userId'    => $userId,
            'source'    => '测试完成·授权手机号',
            'phone'     => $phone,
        ]);
    }

    /**
     * 小程序「了解自己」页提交咨询申请（与 CrmReport 存客宝上报并行；飞书机器人需 feishu_lead_webhook.enabled）
     */
    public static function onDeepServiceConsultApply(int $userId, string $source, string $categoryTag): void
    {
        if ($userId <= 0) {
            return;
        }
        $src = trim($source) !== '' ? trim($source) : '深度服务·申请咨询';
        $tag = trim($categoryTag);
        $dedup = 'deep_consult_apply:' . $userId . ':' . md5($src . '|' . $tag) . ':' . date('YmdH');
        $extra = $tag !== '' ? ('套餐: ' . self::oneLine($tag, 60)) : '';
        self::pushLead([
            'dedupKey'  => $dedup,
            'userId'    => $userId,
            'source'    => $src,
            'extraLine' => $extra,
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

        $lines = UserJourneyService::recentBehaviorLines($userId, 8);
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
     * 仅投递纯文本（无「新获客」头），用于测评+手机号合并等与出站机器人对齐的文案
     */
    private static function pushPlainDedup(string $dedupKey, string $text): void
    {
        $cfg = self::getConfig();
        if (empty($cfg['enabled'])) {
            return;
        }
        $url = trim((string) ($cfg['webhookUrl'] ?? ''));
        if ($url === '' || stripos($url, 'http') !== 0) {
            return;
        }
        if ($dedupKey === '') {
            return;
        }
        if (!self::beginDedup($dedupKey)) {
            return;
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
