<?php
namespace app\model;

use think\Model;

/**
 * 高考定价配置
 */
class GaokaoPricing extends Model
{
    protected $name = 'gaokao_pricing';

    protected $schema = [
        'id'            => 'int',
        'tenantId'      => 'int',
        'productCode'   => 'string',
        'productName'   => 'string',
        'priceOriginal' => 'int',
        'priceSale'     => 'int',
        'priceChannel'  => 'int',
        'currency'      => 'string',
        'status'        => 'int',
        'effectiveAt'   => 'int',
        'expiredAt'     => 'int',
        'extraJson'     => 'string',
        'createdAt'     => 'int',
        'updatedAt'     => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    protected $json = ['extraJson'];

    /**
     * 命中某租户、商品在当前时间生效的定价（租户优先，平台兜底）
     */
    public static function resolveByTenantAndProduct(int $tenantId, string $productCode): ?self
    {
        $now = time();
        $base = self::where('productCode', $productCode)
            ->where('status', 1)
            ->where('effectiveAt', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('expiredAt')->whereOr('expiredAt', 0)->whereOr('expiredAt', '>', $now);
            })
            ->order('id', 'desc');

        if ($tenantId > 0) {
            $row = (clone $base)->where('tenantId', $tenantId)->find();
            if ($row) {
                return $row;
            }
        }

        return (clone $base)->where('tenantId', 0)->find();
    }
}

