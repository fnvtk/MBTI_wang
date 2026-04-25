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

    /**
     * 须为 true：库表字段为驼峰 userId / lastMessageAt 等。
     * strict=false 时 ORM 会把属性转成 snake_case 写入，与真实列名不一致 → userId、时间戳落库为 0，
     * 异步 executeAssistantTurn 按 userId 查会话失败 →「会话不存在」与小程序兜底文案。
     */
    protected $strict = true;

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
