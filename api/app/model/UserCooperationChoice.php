<?php

namespace app\model;

use think\Model;

class UserCooperationChoice extends Model
{
    protected $name = 'user_cooperation_choices';

    protected $schema = [
        'id'           => 'int',
        'userId'       => 'int',
        'enterpriseId' => 'int',
        'modeCode'     => 'string',
        'chosenAt'     => 'int',
        'updatedAt'    => 'int',
    ];

}
