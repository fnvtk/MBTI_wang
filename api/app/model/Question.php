<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 题目模型
 */
class Question extends Model
{
    use SoftDelete;

    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'questions';

    // 软删除字段（驼峰命名，时间戳格式）
    protected $deleteTime = 'deletedAt';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'type'          => 'string',
        'question'      => 'string',
        'options'       => 'string',
        'dimension'     => 'string',
        'enterpriseId'  => 'int',
        'sort'          => 'int',
        'status'        => 'int',
        'deletedAt'     => 'int',
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
        'deletedAt' => 'integer',
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
    ];

    // JSON字段自动转换
    protected $json = ['options'];

    /**
     * 选项修改器（自动转换为JSON）
     */
    public function setOptionsAttr($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }

    /**
     * 选项获取器（自动解析JSON，确保返回数组格式）
     */
    public function getOptionsAttr($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            // 如果解码失败或返回null，返回空数组
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            // 如果是对象格式（关联数组），转换为索引数组
            if (is_array($decoded) && !empty($decoded) && !isset($decoded[0])) {
                return array_values($decoded);
            }
            return $decoded ?: [];
        }
        // 如果是对象（stdClass），转换为数组
        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }
        // 如果已经是数组，确保是索引数组
        if (is_array($value) && !empty($value) && !isset($value[0])) {
            return array_values($value);
        }
        return is_array($value) ? $value : [];
    }
}

