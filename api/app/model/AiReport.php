<?php
namespace app\model;

use think\Model;

class AiReport extends Model
{
    protected $name = 'ai_reports';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
}
