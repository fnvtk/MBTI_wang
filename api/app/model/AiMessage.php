<?php
namespace app\model;

use think\Model;

/**
 * 神仙 AI 消息流
 */
class AiMessage extends Model
{
    protected $name = 'ai_messages';

    /**
     * 须为 true：库表列为 conversationId / isDegraded 等驼峰。
     * strict=false 时会转成 conversation_id 写入 → conversationId 恒为 0，上下文与异步任务错乱。
     */
    protected $strict = true;

    protected $autoWriteTimestamp = false;

    protected $type = [
        'createdAt' => 'integer',
        'tokensIn'  => 'integer',
        'tokensOut' => 'integer',
    ];
}
