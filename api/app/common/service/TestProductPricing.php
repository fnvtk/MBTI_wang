<?php
namespace app\common\service;

use app\model\PricingConfig as PricingConfigModel;
use think\facade\Db;

/**
 * 与 Payment::calculateAmount 中「测试类产品」分支一致，供非 Controller 复用（如高考定价）
 */
class TestProductPricing
{
    /** @var string[] */
    public const TEST_PRODUCT_TYPES = ['face', 'mbti', 'sbti', 'disc', 'pdp', 'resume', 'report', 'team_analysis', 'gaokao'];

    /**
     * @param int|null $enterpriseId     用于查价的企业 ID：personal 时走 admin_personal+eid；enterprise 时走 admin_enterprise+eid
     * @param string   $pricingTier       personal=用户侧个人版定价（含企业后台「个人版」专属价）；enterprise=企业版定价
     * @return array{0:int,1:string} [amountFen, pricingType personal|enterprise]
     */
    public static function amountFenForTestProduct(
        string $productType,
        int $wechatUserId,
        ?int $enterpriseId = null,
        int $quantity = 1,
        string $pricingTier = 'personal'
    ): array {
        $pricingType = $pricingTier === 'enterprise' ? 'enterprise' : 'personal';
        $quantity = $quantity > 0 ? $quantity : 1;

        $pricingEnterpriseId = null;
        if ($enterpriseId !== null && (int) $enterpriseId > 0) {
            $pricingEnterpriseId = (int) $enterpriseId;
        } elseif ($wechatUserId > 0) {
            $userEid = (int) Db::name('wechat_users')->where('id', $wechatUserId)->value('enterpriseId');
            if ($userEid > 0) {
                $pricingEnterpriseId = $userEid;
            }
        }

        $pricingConfig = PricingConfigModel::getByTypeAndEnterprise($pricingType, $pricingEnterpriseId);
        $config = [];
        if ($pricingConfig && !empty($pricingConfig->config)) {
            $raw = $pricingConfig->config;
            $config = is_array($raw) ? $raw : (array) $raw;
        }

        $keyMap = ['team_analysis' => 'teamAnalysis'];
        $key = $keyMap[$productType] ?? $productType;
        $unitPriceYuan = isset($config[$key]) ? (float) $config[$key] : 0.0;
        $amountFen = (int) round($unitPriceYuan * 100 * $quantity);

        return [$amountFen, $pricingType];
    }
}
