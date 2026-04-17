<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 神仙 AI 对话
 */
class AiConversation extends Model
{
    use SoftDelete;

    protected $strict = false;

    protected $name = 'ai_conversations';
    protected $deleteTime = 'deletedAt';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    protected $type = [
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
        'deletedAt' => 'integer',
        'lastMessageAt' => 'integer',
    ];
}
