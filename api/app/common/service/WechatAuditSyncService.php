<?php
namespace app\common\service;

use app\model\SystemConfig as SystemConfigModel;

/**
 * 根据微信 get_latest_auditstatus 自动切换 system.miniprogramAuditMode
 * 审核中(2)/延后(4) → 开启提审隐藏；成功(0)/拒绝(1)/撤回(3)/无审核单 → 关闭
 */
class WechatAuditSyncService
{
    /** 无有效审核单等：关闭提审模式 */
    public const NO_AUDIT_ERRCODES = [85058, 86001];

    public const THROTTLE_SECONDS = 300;

    public static function shouldAutoSync(array $systemOut): bool
    {
        if (array_key_exists('wechatAuditAutoMiniprogramMode', $systemOut)
            && $systemOut['wechatAuditAutoMiniprogramMode'] === false) {
            return false;
        }
        $last = (int) ($systemOut['wechatLastAuditSyncedAt'] ?? 0);
        if ($last > 0 && (time() - $last) < self::THROTTLE_SECONDS) {
            return false;
        }
        return true;
    }

    /**
     * @param bool $bypassThrottle 管理端「立即同步」为 true；定时/打开设置页自动为 false
     * @return array{
     *   ok: bool,
     *   skipped?: bool,
     *   applied: bool,
     *   miniprogramAuditMode: bool,
     *   wechat: array,
     *   message?: string,
     *   systemBroadcast?: array
     * }
     */
    public static function run(bool $bypassThrottle = false): array
    {
        $config = SystemConfigModel::where('key', 'system')->where('enterprise_id', 0)->find();
        $oldVal = $config ? $config->value : null;
        $systemArr = is_array($oldVal) ? $oldVal : (is_string($oldVal) ? (json_decode($oldVal, true) ?: []) : []);
        if (!is_array($systemArr)) {
            $systemArr = [];
        }

        $currentMpAudit = !empty($systemArr['miniprogramAuditMode']);

        if (!$bypassThrottle && !self::shouldAutoSync($systemArr)) {
            return [
                'ok'       => true,
                'skipped'  => true,
                'applied'  => false,
                'miniprogramAuditMode' => $currentMpAudit,
                'wechat'   => [],
                'message'  => 'skipped_throttle_or_auto_off',
            ];
        }

        $wx = WechatService::getLatestAuditStatus();
        $now = time();

        $systemArr['wechatLastAuditSyncedAt'] = $now;

        $target = null;
        $errcode = isset($wx['errcode']) ? (int) $wx['errcode'] : -9;

        if (isset($wx['errcode']) && $wx['errcode'] === 0 && array_key_exists('status', $wx)) {
            $st = (int) $wx['status'];
            if (in_array($st, [2, 4], true)) {
                $target = true;
            } elseif (in_array($st, [0, 1, 3], true)) {
                $target = false;
            }
            $systemArr['wechatLastAuditErrcode'] = 0;
            $systemArr['wechatLastAuditStatus'] = $st;
            $systemArr['wechatLastAuditReason'] = (string) ($wx['reason'] ?? '');
        } elseif (in_array($errcode, self::NO_AUDIT_ERRCODES, true)) {
            $target = false;
            $systemArr['wechatLastAuditErrcode'] = $errcode;
            $systemArr['wechatLastAuditStatus'] = null;
            $systemArr['wechatLastAuditReason'] = (string) ($wx['errmsg'] ?? '');
        } else {
            $systemArr['wechatLastAuditErrcode'] = $errcode;
            $systemArr['wechatLastAuditStatus'] = isset($wx['status']) ? (int) $wx['status'] : null;
            $systemArr['wechatLastAuditReason'] = (string) ($wx['errmsg'] ?? '');
        }

        $applied = false;
        if ($target !== null && (bool) $target !== $currentMpAudit) {
            $systemArr['miniprogramAuditMode'] = (bool) $target;
            $applied = true;
        }

        if (!$config) {
            $config = new SystemConfigModel();
            $config->key = 'system';
            $config->enterprise_id = 0;
            $config->description = '系统基础配置';
        }
        $config->value = $systemArr;
        $config->save();

        $broadcast = [
            'miniprogramAuditMode'           => !empty($systemArr['miniprogramAuditMode']),
            'wechatAuditAutoMiniprogramMode' => !array_key_exists('wechatAuditAutoMiniprogramMode', $systemArr)
                || $systemArr['wechatAuditAutoMiniprogramMode'] !== false,
            'wechatLastAuditErrcode'  => $systemArr['wechatLastAuditErrcode'] ?? null,
            'wechatLastAuditStatus'   => $systemArr['wechatLastAuditStatus'] ?? null,
            'wechatLastAuditReason'   => $systemArr['wechatLastAuditReason'] ?? '',
            'wechatLastAuditSyncedAt' => $systemArr['wechatLastAuditSyncedAt'] ?? null,
        ];

        return [
            'ok'               => true,
            'applied'          => $applied,
            'miniprogramAuditMode' => (bool) ($systemArr['miniprogramAuditMode'] ?? false),
            'wechat'           => $wx,
            'systemBroadcast'  => $broadcast,
        ];
    }
}
