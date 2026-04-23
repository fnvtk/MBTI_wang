<?php

namespace app\model;

use think\Model;

class EnterpriseCooperationMode extends Model
{
    protected $name = 'enterprise_cooperation_modes';

    protected $schema = [
        'id'           => 'int',
        'enterpriseId' => 'int',
        'modeCode'     => 'string',
        'enabled'      => 'int',
        'sortOrder'    => 'int',
        'title'        => 'string',
        'description'  => 'string',
        'createdAt'    => 'int',
        'updatedAt'    => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime         = 'createdAt';
    protected $updateTime         = 'updatedAt';
}
