<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 用户模型
 */
class User extends Model
{
    use SoftDelete;

    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'users';

    // 软删除字段（驼峰命名，时间戳格式）
    protected $deleteTime = 'deletedAt';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'username'     => 'string',
        'password'     => 'string',
        'phone'        => 'string',
        'email'        => 'string',
        'role'         => 'string',
        'enterpriseId' => 'int',
        'mbtiType'    => 'string',
        'region'       => 'string',
        'industry'     => 'string',
        'status'       => 'int',
        'lastLoginTime' => 'int',
        'lastLoginIp'   => 'string',
        'deletedAt'     => 'int',
        'createdAt'   => 'int',
        'updatedAt'   => 'int',
    ];

    // 自动时间戳（使用驼峰命名，时间戳格式）
    protected $autoWriteTimestamp = 'int';
    
    // 时间戳字段名（驼峰命名，匹配数据库）
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    // 隐藏字段（不返回给前端）
    protected $hidden = ['password'];

    // 时间字段类型（时间戳格式）
    protected $type = [
        'lastLoginTime' => 'integer',
        'deletedAt' => 'integer',
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
    ];

    /**
     * 密码修改器（自动加密）
     */
    public function setPasswordAttr($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     * @param string $password 明文密码
     * @return bool
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }
}

