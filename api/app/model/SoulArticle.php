<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * Soul 采集文章（MBTI 主题 · 为一场 soul 创业实验引流）
 */
class SoulArticle extends Model
{
    use SoftDelete;

    protected $name = 'soul_articles';
    protected $deleteTime = 'deletedAt';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    protected $type = [
        'createdAt'        => 'integer',
        'updatedAt'        => 'integer',
        'deletedAt'        => 'integer',
        'publishedAt'      => 'integer',
        'isRecommended'    => 'integer',
        'recommendedOrder' => 'integer',
        'viewCount'        => 'integer',
    ];
}
