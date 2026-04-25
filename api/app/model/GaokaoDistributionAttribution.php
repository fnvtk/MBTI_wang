<?php
namespace app\model;

use think\Model;

/**
 * 高考分销归因
 */
class GaokaoDistributionAttribution extends Model
{
    protected $name = 'gaokao_distribution_attribution';

    protected $schema = [
        'id'             => 'int',
        'userId'         => 'int',
        'tenantId'       => 'int',
        'referrerUserId' => 'int',
        'channelCode'    => 'string',
        'scene'          => 'string',
        'attributedAt'   => 'int',
        'expireAt'       => 'int',
        'isLocked'       => 'int',
        'createdAt'      => 'int',
        'updatedAt'      => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
}

