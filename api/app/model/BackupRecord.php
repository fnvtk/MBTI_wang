<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 数据库备份记录模型
 */
class BackupRecord extends Model
{
    use SoftDelete;

    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'backup_records';

    // 软删除字段（驼峰命名，时间戳格式）
    protected $deleteTime = 'deletedAt';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'filename'     => 'string',
        'filepath'     => 'string',
        'fileSize'     => 'int',
        'ossUrl'       => 'string',
        'ossPath'      => 'string',
        'status'        => 'string',
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
}

