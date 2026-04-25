<?php
namespace app\model;

use think\Model;

/**
 * 高考业务订单表
 */
class GaokaoOrder extends Model
{
    protected $name = 'gaokao_order';

    protected $schema = [
        'id'             => 'int',
        'orderNo'        => 'string',
        'userId'         => 'int',
        'tenantId'       => 'int',
        'productCode'    => 'string',
        'pricingId'      => 'int',
        'amountOriginal' => 'int',
        'amountPayable'  => 'int',
        'amountPaid'     => 'int',
        'currency'       => 'string',
        'payStatus'      => 'int',
        'payChannel'     => 'string',
        'paidAt'         => 'int',
        'refundAt'       => 'int',
        'extJson'        => 'string',
        'createdAt'      => 'int',
        'updatedAt'      => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
    protected $json = ['extJson'];
}

