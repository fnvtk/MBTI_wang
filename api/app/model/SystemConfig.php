<?php
namespace app\model;

use think\Model;

/**
 * 系统配置模型
 */
class SystemConfig extends Model
{
    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'system_config';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'key'           => 'string',
        'enterprise_id' => 'int',
        'value'         => 'string',
        'description'   => 'string',
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
    protected $json = ['value'];

    /**
     * 配置值修改器（自动转换为JSON）
     */
    public function setValueAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }

    /**
     * 配置值获取器（自动解析JSON）
     */
    public function getValueAttr($value)
    {
        if (is_string($value)) {
            return json_decode($value, true);
        }
        return $value;
    }
}

