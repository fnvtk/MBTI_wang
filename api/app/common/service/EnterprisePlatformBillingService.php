<?php
namespace app\common\service;

use app\model\PricingConfig as PricingConfigModel;
use think\facade\Db;

/**
 * 企业成员测试时：按超管全局价（personal/enterprise + enterpriseId=NULL）从企业钱包扣「平台结算」，
 * 与用户侧付费（paidAmount / 微信支付后 recharge 入账）分离。
 */
class EnterprisePlatformBillingService
{
    /**
     * 是否为企业测试场景（请求体带企业、走超管 enterprise 价）
     */
    public static function isEnterpriseTestScope(?int $requestEnterpriseId): bool
    {
        return $requestEnterpriseId !== null && $requestEnterpriseId > 0;
    }

    /**
     * 超管全局平台单价（分），仅读取 type=personal|enterprise 且 enterpriseId 为 NULL 的配置行。
     */
    public static function getSuperAdminPlatformFenByTestType(string $testType, bool $enterpriseScope): int
    {
        $type = $enterpriseScope ? 'enterprise' : 'personal';
        $row = PricingConfigModel::where('type', $type)->whereNull('enterpriseId')->find();
        if (!$row || empty($row->config)) {
            return 0;
        }
        $pricing = is_array($row->config) ? $row->config : (array) $row->config;
        $key = $testType === 'team_analysis' ? 'teamAnalysis' : $testType;
        if (!isset($pricing[$key])) {
            return 0;
        }
        $yuan = (float) $pricing[$key];
        return $yuan > 0 ? (int) round($yuan * 100) : 0;
    }

    /**
     * 快速校验企业余额是否足够（无锁，用于 AI 调用前拦截；最终以扣款事务为准）。
     */
    public static function assertEnterpriseBalanceCovers(int $enterpriseId, int $platformFen): ?string
    {
        if ($enterpriseId <= 0 || $platformFen <= 0) {
            return null;
        }
        $bal = (int) Db::name('enterprises')->where('id', $enterpriseId)->value('balance');
        if ($bal < $platformFen) {
            return '企业余额不足，请联系管理员';
        }
        return null;
    }

    /**
     * 测试类型中文说明（财务流水展示用）
     */
    public static function testTypeLabelZh(string $testType): string
    {
        $map = [
            'face'          => '人脸',
            'mbti'          => 'MBTI',
            'disc'          => 'DISC',
            'pdp'           => 'PDP',
            'resume'        => '简历综合分析',
            'ai'            => 'AI测试',
            'team_analysis' => '团队分析',
        ];
        return $map[$testType] ?? $testType;
    }

    /**
     * 流水中的用户展示：优先微信昵称，否则用户#id
     */
    public static function userDisplayForFinance(int $userId): string
    {
        if ($userId <= 0) {
            return '未知用户';
        }
        $nick = Db::name('wechat_users')->where('id', $userId)->value('nickname');
        $nick = is_string($nick) ? trim($nick) : '';
        if ($nick === '') {
            return '用户#' . $userId;
        }
        if (function_exists('mb_strlen') && mb_strlen($nick, 'UTF-8') > 36) {
            return mb_substr($nick, 0, 36, 'UTF-8') . '…';
        }
        if (!function_exists('mb_strlen') && strlen($nick) > 48) {
            return substr($nick, 0, 48) . '…';
        }
        return $nick;
    }

    /**
     * 平台结算扣款流水说明（具体到用户与测试类型）
     */
    public static function buildPlatformConsumeDescription(int $userId, string $testType, int $testResultId): string
    {
        $who    = self::userDisplayForFinance($userId);
        $typeZh = self::testTypeLabelZh($testType);
        return sprintf(
            '平台测试结算扣款：用户「%s」·%s(%s)·记录ID=%d',
            $who,
            $typeZh,
            $testType,
            $testResultId
        );
    }

    /**
     * 写入 test_results：若需从企业扣平台价，则在同一事务内 FOR UPDATE 扣减余额并记 consume 流水。
     *
     * @param int         $billingEnterpriseId 扣款企业（与 test_results.enterpriseId 一致：绑定或企业测试）
     * @param string      $testType
     * @param bool        $enterpriseScope     是否按超管 enterprise 价计费
     * @param array       $testResultRow        不含 id
     * @return array{0:int|null,1:string|null} [testResultId, errorMessage]
     */
    public static function insertTestResultWithPlatformDeduction(
        int $billingEnterpriseId,
        string $testType,
        bool $enterpriseScope,
        array $testResultRow
    ): array {
        $platformFen = self::getSuperAdminPlatformFenByTestType($testType, $enterpriseScope);

        if ($billingEnterpriseId <= 0 || $platformFen <= 0) {
            try {
                $id = (int) Db::name('test_results')->insertGetId($testResultRow);
                return [$id > 0 ? $id : null, null];
            } catch (\Throwable $e) {
                return [null, '保存测试结果失败'];
            }
        }

        Db::startTrans();
        try {
            $ent = Db::name('enterprises')->where('id', $billingEnterpriseId)->lock(true)->find();
            if (!$ent) {
                Db::rollback();
                return [null, '企业不存在'];
            }
            $bal = (int) ($ent['balance'] ?? 0);
            if ($bal < $platformFen) {
                Db::rollback();
                return [null, '企业余额不足，请联系管理员'];
            }
            $newBal = $bal - $platformFen;
            $now = (int) ($testResultRow['updatedAt'] ?? $testResultRow['createdAt'] ?? time());
            Db::name('enterprises')->where('id', $billingEnterpriseId)->update([
                'balance'   => $newBal,
                'updatedAt' => $now,
            ]);

            $id = (int) Db::name('test_results')->insertGetId($testResultRow);
            if ($id <= 0) {
                Db::rollback();
                return [null, '保存测试结果失败'];
            }

            $uidForDesc = (int) ($testResultRow['userId'] ?? 0);
            $consumeDesc = self::buildPlatformConsumeDescription($uidForDesc, $testType, $id);
            if (function_exists('mb_strlen') && mb_strlen($consumeDesc, 'UTF-8') > 250) {
                $consumeDesc = mb_substr($consumeDesc, 0, 247, 'UTF-8') . '…';
            } elseif (strlen($consumeDesc) > 255) {
                $consumeDesc = substr($consumeDesc, 0, 252) . '…';
            }

            Db::name('finance_records')->insert([
                'enterpriseId'  => $billingEnterpriseId,
                'type'          => 'consume',
                'amount'        => $platformFen,
                'balanceBefore' => $bal,
                'balanceAfter'  => $newBal,
                'description'   => $consumeDesc,
                'orderId'       => null,
                'createdAt'     => (int) ($testResultRow['createdAt'] ?? $now),
            ]);

            Db::commit();
            return [$id, null];
        } catch (\Throwable $e) {
            Db::rollback();
            return [null, '保存测试结果失败'];
        }
    }
}
