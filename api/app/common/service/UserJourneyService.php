<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 用户旅程：从 analytics_events 聚合最近行为，供飞书获客、出站 Hook、存客宝备注等复用。
 */
class UserJourneyService
{
    /**
     * @return string[] 每条一行展示文案（与 FeishuLeadWebhookService 历史格式一致）
     */
    public static function recentBehaviorLines(int $userId, int $limit = 8): array
    {
        if ($userId <= 0 || $limit <= 0) {
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
            if (is_array($r)) {
                $out[] = self::formatAnalyticsLine($r);
            }
        }

        return $out;
    }

    /**
     * 用户管理摘要一行（平台用户 ID、归属企业），用于 CRM 备注与推送文案。
     */
    public static function managementSummaryLine(int $userId, int $testResultEnterpriseId = 0): string
    {
        if ($userId <= 0) {
            return '';
        }
        $parts = ['平台用户ID:' . $userId];
        $eid = $testResultEnterpriseId > 0 ? $testResultEnterpriseId : 0;
        if ($eid <= 0) {
            try {
                $bound = Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
                $eid = (int) ($bound ?? 0);
            } catch (\Throwable $e) {
                $eid = 0;
            }
        }
        if ($eid > 0) {
            try {
                $name = Db::name('enterprises')->where('id', $eid)->value('name');
            } catch (\Throwable $e) {
                $name = null;
            }
            $label = $name !== null && (string) $name !== '' ? (string) $name : '企业';
            $parts[] = '归属企业:' . $label . '(ID' . $eid . ')';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param array<string,mixed> $r analytics_events 一行
     */
    public static function formatAnalyticsLine(array $r): string
    {
        $name = (string) ($r['eventName'] ?? '');
        $path = trim((string) ($r['pagePath'] ?? ''));
        $props = [];
        if (!empty($r['propsJson'])) {
            $decoded = is_string($r['propsJson']) ? json_decode($r['propsJson'], true) : [];
            $props = is_array($decoded) ? $decoded : [];
        }
        $labelMap = [
            'page_view'      => '浏览页面',
            'button_click'   => '按钮点击',
            'click_pay'      => '发起支付',
            'click_recharge' => '点击充值',
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

    public static function humanTimeAgoCn(int $clientTsMs): string
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

    /**
     * 将旅程行压成一段备注（用于存客宝 remark 等），超长截断。
     *
     * @param string[] $lines
     */
    public static function journeyLinesToRemarkBlock(array $lines, int $maxChars = 600): string
    {
        if (count($lines) === 0) {
            return '';
        }
        $body = '用户旅程：' . implode('；', $lines);
        if (function_exists('mb_strlen') && mb_strlen($body) > $maxChars) {
            return mb_substr($body, 0, $maxChars) . '…';
        }
        if (!function_exists('mb_strlen') && strlen($body) > $maxChars) {
            return substr($body, 0, $maxChars) . '…';
        }

        return $body;
    }
}
