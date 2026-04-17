<?php
namespace app\model;

use think\Model;

/**
 * 神仙 AI 消息流
 */
class AiMessage extends Model
{
    protected $name = 'ai_messages';

    /** 避免因库表字段与模型缓存不一致导致写入抛错 */
    protected $strict = false;

    protected $autoWriteTimestamp = false;

    protected $type = [
        'createdAt' => 'integer',
        'tokensIn'  => 'integer',
        'tokensOut' => 'integer',
    ];
}
