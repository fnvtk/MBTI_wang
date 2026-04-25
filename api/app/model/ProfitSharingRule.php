<?php
namespace app\model;

use think\Model;

class ProfitSharingRule extends Model
{
    protected $name = 'profit_sharing_rules';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    protected $type = [
        'receivers' => 'json',
    ];
}
