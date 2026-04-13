<?php
namespace app\common\service;

use app\model\PricingConfig as PricingConfigModel;
use think\facade\Db;

/**
 * 企业平台费账务服务
 *
 * 口径：
 * - 仅当 test_results 归属企业时才扣费
 * - 扣费金额读取超管全局 enterprise 定价
 * - 每条 test_results 仅扣一次，使用 finance_records 描述做幂等标记
 */
class EnterpriseBillingService
{
    /**
     * 测评完成后，按超管全局企业版单价扣企业平台费。
     */
    public static function chargePlatformFeeForTestResult(int $testResultId, string $testType, ?int $enterpriseId): void
    {
        $enterpriseId = (int) ($enterpriseId ?? 0);
        if ($testResultId <= 0 || $enterpriseId <= 0) {
            return;
        }

        $amountFen = self::getPlatformFeeFen($testType);
        if ($amountFen <= 0) {
            return;
        }

        $now = time();
        $description = self::buildPlatformFeeDescription($testType, $testResultId);

        Db::startTrans();
        try {
            $enterprise = Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->field('id, balance')
                ->lock(true)
                ->find();
            if (!$enterprise) {
                Db::rollback();
                return;
            }

            $exists = Db::name('finance_records')
                ->where('enterpriseId', $enterpriseId)
                ->where('type', 'consume')
                ->whereNull('orderId')
                ->where('description', $description)
                ->find();
            if ($exists) {
                Db::commit();
                return;
            }

            $beforeFen = (int) ($enterprise['balance'] ?? 0);
            // 平台费独立记账，允许与企业测试收入分列后形成净额。
            $afterFen = $beforeFen - $amountFen;

            Db::name('enterprises')
                ->where('id', $enterpriseId)
                ->update([
                    'balance'   => $afterFen,
                    'updatedAt' => $now,
                ]);

            Db::name('finance_records')->insert([
                'enterpriseId'  => $enterpriseId,
                'type'          => 'consume',
                'amount'        => $amountFen,
                'balanceBefore' => $beforeFen,
                'balanceAfter'  => $afterFen,
                'description'   => $description,
                'orderId'       => null,
                'createdAt'     => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
        }
    }

    /**
     * 从超管全局 enterprise 定价读取平台费（分）。
     */
    public static function getPlatformFeeFen(string $testType): int
    {
        $config = PricingConfigModel::where('type', 'enterprise')->whereNull('enterpriseId')->find();
        if (!$config || empty($config->config)) {
            return 0;
        }

        $raw = $config->config;
        $pricing = is_array($raw) ? $raw : (array) $raw;
        $key = $testType === 'team_analysis' ? 'teamAnalysis' : $testType;
        if (!isset($pricing[$key])) {
            return 0;
        }

        $yuan = (float) $pricing[$key];
        return $yuan > 0 ? (int) round($yuan * 100) : 0;
    }

    private static function buildPlatformFeeDescription(string $testType, int $testResultId): string
    {
        return '平台扣费：' . self::getTestTypeLabel($testType) . '测试（testResultId=' . $testResultId . '）';
    }

    private static function getTestTypeLabel(string $testType): string
    {
        $map = [
            'face'   => '人脸',
            'mbti'   => 'MBTI',
            'sbti'   => 'SBTI',
            'disc'   => 'DISC',
            'pdp'    => 'PDP',
            'resume' => '简历',
        ];

        return $map[$testType] ?? strtoupper($testType);
    }
}
