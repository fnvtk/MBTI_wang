<?php
namespace app\common;

use app\model\Enterprise as EnterpriseModel;
use think\facade\Db;

/**
 * 超管「系统基础配置」中的小程序默认企业，与 AppConfig 语义一致。
 */
class SystemDefaultEnterprise
{
    /**
     * 有效则返回企业 ID，否则 null（企业不存在或已软删时亦为 null）
     */
    public static function getId(): ?int
    {
        try {
            $row = Db::name('system_config')->where('key', 'system')->where('enterprise_id', 0)->find();
            if (!$row || empty($row['value'])) {
                return null;
            }
            $raw = $row['value'];
            $arr = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($arr) || !isset($arr['defaultEnterpriseId']) || $arr['defaultEnterpriseId'] === '' || $arr['defaultEnterpriseId'] === null) {
                return null;
            }
            $de = (int) $arr['defaultEnterpriseId'];
            if ($de <= 0) {
                return null;
            }
            if (!EnterpriseModel::where('id', $de)->find()) {
                return null;
            }

            return $de;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
