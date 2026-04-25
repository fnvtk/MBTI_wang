<?php
namespace app\model;

use think\Model;

/**
 * 高考分销佣金流水
 */
class GaokaoDistributionCommission extends Model
{
    protected $name = 'gaokao_distribution_commission';

    protected $schema = [
        'id'                  => 'int',
        'tenantId'            => 'int',
        'orderId'             => 'int',
        'orderNo'             => 'string',
        'userId'              => 'int',
        'referrerUserId'      => 'int',
        'commissionRuleType'  => 'string',
        'commissionRuleValue' => 'float',
        'commissionAmount'    => 'int',
        'status'              => 'int',
        'settledAt'           => 'int',
        'reversedAt'          => 'int',
        'remark'              => 'string',
        'createdAt'           => 'int',
        'updatedAt'           => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
}

