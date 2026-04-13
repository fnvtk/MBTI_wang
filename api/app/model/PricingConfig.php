<?php
namespace app\model;

use think\Model;

/**
 * 定价配置模型
 * 实际表名 = 数据库前缀 + pricing_config，例如 .env 中 DATABASE_PREFIX=mbti_ 时为 mbti_pricing_config
 */
class PricingConfig extends Model
{
    // 表名（不含前缀）；最终访问表 = config(database.prefix) + pricing_config
    protected $name = 'pricing_config';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'type'          => 'string',
        'enterpriseId'  => 'int',
        'config'        => 'string',
        'createdAt'     => 'int',
        'updatedAt'     => 'int',
    ];

    // 自动时间戳（使用驼峰命名，时间戳格式）
    protected $autoWriteTimestamp = 'int';
    
    // 时间戳字段名（驼峰命名，匹配数据库）
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    // 时间字段类型（时间戳格式）
    protected $type = [
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
    ];

    // JSON字段自动转换
    protected $json = ['config'];

    /**
     * 配置修改器（自动转换为JSON）
     */
    public function setConfigAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }

    /**
     * 配置获取器（自动解析JSON）
     */
    public function getConfigAttr($value)
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }
        return $value;
    }

    /**
     * 按类型与可选企业ID取定价配置
     *
     * personal（个人版）优先级：
     *   1. admin_personal + enterpriseId（企业专属管理端配置，有 eid 时）
     *   2. personal + null（超管全局，仅未绑定企业或该企业未配置时使用）
     *
     * enterprise（企业版）优先级：
     *   1. admin_enterprise + enterpriseId（有 eid 时）
     *   2. enterprise + null（超管全局兜底）
     *
     * @param string $type personal|enterprise|deep
     * @param int|null $enterpriseId 有则优先读该企业专属配置
     * @return \app\model\PricingConfig|null
     */
    public static function getByTypeAndEnterprise(string $type, ?int $enterpriseId = null): ?self
    {
        if ($type === 'personal') {
            if (!empty($enterpriseId)) {
                $row = self::where('type', 'admin_personal')->where('enterpriseId', $enterpriseId)->find();
                if ($row) return $row;
            }
            // 超管全局个人定价（最后兜底，仅管理端完全未配置时使用）
            return self::where('type', 'personal')->whereNull('enterpriseId')->find();
        }
        if ($type === 'enterprise') {
            if (!empty($enterpriseId)) {
                $row = self::where('type', 'admin_enterprise')->where('enterpriseId', $enterpriseId)->find();
                if ($row) return $row;
            }
            return self::where('type', 'enterprise')->whereNull('enterpriseId')->find();
        }
        if ($type === 'deep') {
            return self::where('type', 'deep')->whereNull('enterpriseId')->find();
        }
        return self::where('type', $type)->whereNull('enterpriseId')->find();
    }
}

