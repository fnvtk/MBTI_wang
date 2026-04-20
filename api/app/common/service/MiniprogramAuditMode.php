<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 小程序「提审模式」：
 * - 隐藏神仙 AI 对话等深度合成能力；
 * - 关闭虚拟商品/付费解锁与「了解自己」深度套餐展示（应对 iOS 虚拟支付类审核意见）。
 * 配置存储：system_config.key = system 的 JSON 内 miniprogramAuditMode = true
 */
class MiniprogramAuditMode
{
    public static function isOn(): bool
    {
        try {
            $row = Db::name('system_config')->where('key', 'system')->find();
            if (!$row || empty($row['value'])) {
                return false;
            }
            $v = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];

            return is_array($v) && !empty($v['miniprogramAuditMode']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
