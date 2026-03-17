<?php
namespace app\model;

use think\Model;

/**
 * 微信小程序用户模型
 */
class WechatUser extends Model
{
    protected $name = 'wechat_users';

    protected $schema = [
        'id'           => 'int',
        'openid'       => 'string',
        'unionid'      => 'string',
        'sessionKey'   => 'string',
        'nickname'     => 'string',
        'avatar'       => 'string',
        'phone'        => 'string',
        'gender'       => 'int',
        'country'      => 'string',
        'province'     => 'string',
        'city'         => 'string',
        'birthday'     => 'string',
        'status'       => 'int',
        'lastLoginAt'  => 'int',
        'lastLoginIp'  => 'string',
        'enterpriseId' => 'int',
        'createdAt'    => 'int',
        'updatedAt'    => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    protected $hidden = ['sessionKey', 'openid'];

    protected $type = [
        'lastLoginAt' => 'integer',
        'createdAt'   => 'integer',
        'updatedAt'   => 'integer',
    ];

    /**
     * 返回给前端的用户信息（不包含敏感字段）
     */
    public function toApiArray(): array
    {
        $row = $this->toArray();
        unset($row['sessionKey'], $row['openid']);
        $row['avatarUrl'] = $row['avatar'] ?? '';
        return $row;
    }
}
