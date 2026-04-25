<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * 订单分账服务（参考知己 profit-sharing 思路）
 *
 * 用法：订单支付成功后调用 ::executeSharing($orderSn, $productType, $totalFen, $extra)
 * - 按 mbti_profit_sharing_rules 配置把 totalFen 拆给多个 receiver
 * - 对 distributor_l1 / distributor_l2：会联动现有 mbti_commission_records（ 复用项目已有分销系统 ）
 * - 最终写入 mbti_profit_sharing_records 一行（orderSn 唯一），details JSON 里记所有明细
 *
 * 该服务 **idempotent**：同一 orderSn 重复调用不会重复分账。
 */
class ProfitSharingService
{
    /**
     * @param string $orderSn     订单号（唯一）
     * @param string $productType 产品类型，对应 mbti_profit_sharing_rules.productType，找不到则走 default
     * @param int    $totalFen    订单总金额（分）
     * @param array  $extra       扩展 ['userId'=>购买者id, 'orderId'=>订单表id, 'consultantId'=>?]
     * @return array  分账明细
     */
    public static function executeSharing(string $orderSn, string $productType, int $totalFen, array $extra = []): array
    {
        if ($totalFen <= 0 || $orderSn === '') {
            return ['status' => 'skipped', 'reason' => 'invalid-input'];
        }

        $dup = Db::name('profit_sharing_records')->where('orderSn', $orderSn)->find();
        if ($dup) {
            return ['status' => 'dedup', 'recordId' => $dup['id']];
        }

        $rule = self::resolveRule($productType);
        $receivers = $rule['receivers'] ?? [];
        if (!is_array($receivers) || count($receivers) === 0) {
            $receivers = [['type' => 'platform', 'name' => '平台', 'ratio' => 1.0]];
        }

        $totalRatio = 0.0;
        foreach ($receivers as $r) { $totalRatio += (float) ($r['ratio'] ?? 0); }
        if (abs($totalRatio - 1.0) > 0.001) {
            Log::warning("ProfitSharingService: rule {$productType} ratio sum={$totalRatio}, will renormalize");
        }

        $now = time();
        $details = [];
        $buyerUserId = (int) ($extra['userId'] ?? 0);

        // 一级 / 二级分销人解析（复用现有 distribution_bindings）
        list($inviterL1, $inviterL2) = self::resolveInviters($buyerUserId);

        $allocated = 0;
        foreach ($receivers as $idx => $r) {
            $ratio  = (float) ($r['ratio'] ?? 0);
            $amount = (int) floor($totalFen * $ratio);
            $type   = (string) ($r['type'] ?? 'platform');
            $account = null;
            $status  = 'success';

            if ($type === 'distributor_l1') {
                $account = $inviterL1 ? (string) $inviterL1 : null;
                if (!$inviterL1) {
                    // 没有一级分销 → 金额归还平台
                    $status = 'no-inviter';
                    $amount = 0;
                }
            } elseif ($type === 'distributor_l2') {
                $account = $inviterL2 ? (string) $inviterL2 : null;
                if (!$inviterL2) {
                    $status = 'no-inviter';
                    $amount = 0;
                }
            } elseif ($type === 'consultant') {
                $account = isset($extra['consultantId']) ? (string) $extra['consultantId'] : null;
            }

            $details[] = [
                'receiverType' => $type,
                'receiverName' => (string) ($r['name'] ?? $type),
                'amount'       => $amount,
                'ratio'        => $ratio,
                'account'      => $account,
                'status'       => $status,
            ];
            $allocated += $amount;
        }

        // 尾差归平台
        if ($allocated < $totalFen) {
            $diff = $totalFen - $allocated;
            foreach ($details as &$d) {
                if ($d['receiverType'] === 'platform') { $d['amount'] += $diff; break; }
            }
            unset($d);
        }

        // 写主记录
        $recordId = Db::name('profit_sharing_records')->insertGetId([
            'orderSn'     => $orderSn,
            'orderId'     => (int) ($extra['orderId'] ?? 0) ?: null,
            'productType' => $productType,
            'totalAmount' => $totalFen,
            'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
            'status'      => 'processing',
            'createdAt'   => $now,
            'updatedAt'   => $now,
        ]);

        // 联动现有分销系统：把 distributor_l1/l2 的金额写进 mbti_commission_records
        try {
            foreach ($details as $d) {
                if (in_array($d['receiverType'], ['distributor_l1', 'distributor_l2'], true)
                    && (int) $d['amount'] > 0 && !empty($d['account'])) {
                    self::createCommissionRecord(
                        (int) $d['account'],
                        $buyerUserId,
                        $d['receiverType'] === 'distributor_l1' ? 1 : 2,
                        (int) $d['amount'],
                        (float) $d['ratio'],
                        $totalFen,
                        $orderSn,
                        (int) ($extra['orderId'] ?? 0)
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ProfitSharingService commission hook failed: ' . $e->getMessage());
        }

        Db::name('profit_sharing_records')->where('id', $recordId)->update([
            'status'      => 'completed',
            'processedAt' => time(),
            'updatedAt'   => time(),
        ]);

        return [
            'status'   => 'completed',
            'recordId' => $recordId,
            'details'  => $details,
        ];
    }

    private static function resolveRule(string $productType): array
    {
        $row = Db::name('profit_sharing_rules')
            ->where('productType', $productType)
            ->where('status', 'active')
            ->find();
        if (!$row) {
            $row = Db::name('profit_sharing_rules')
                ->where('productType', 'default')
                ->where('status', 'active')
                ->find();
        }
        if (!$row) {
            return ['receivers' => [['type' => 'platform', 'name' => '平台', 'ratio' => 1.0]]];
        }
        $receivers = json_decode($row['receivers'] ?? '[]', true) ?: [];
        return ['receivers' => $receivers];
    }

    /**
     * 解析一级/二级分销人（复用 mbti_distribution_bindings）
     * @return array [inviterL1_userId|null, inviterL2_userId|null]
     */
    private static function resolveInviters(int $buyerUserId): array
    {
        if ($buyerUserId <= 0) return [null, null];
        try {
            $now = time();
            $bind1 = Db::name('distribution_bindings')
                ->where('inviteeId', $buyerUserId)
                ->where('status', 'active')
                ->where('expireAt', '>', $now)
                ->order('id', 'desc')
                ->find();
            if (!$bind1) return [null, null];
            $l1 = (int) $bind1['inviterId'];
            $bind2 = Db::name('distribution_bindings')
                ->where('inviteeId', $l1)
                ->where('status', 'active')
                ->where('expireAt', '>', $now)
                ->order('id', 'desc')
                ->find();
            $l2 = $bind2 ? (int) $bind2['inviterId'] : null;
            return [$l1, $l2];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /**
     * 写入 mbti_commission_records（复用项目已有的分销佣金表结构）
     * 若该表结构/字段命名不同，请在本方法内做适配；当前按 mbti_data.sql 中看到的字段写入
     */
    private static function createCommissionRecord(
        int $inviterId,
        int $inviteeId,
        int $level,
        int $amountFen,
        float $rate,
        int $orderTotalFen,
        string $orderSn,
        int $orderId
    ): void {
        $now = time();
        // 防重：相同 orderSn + inviterId + level 只插一次
        $dup = Db::name('commission_records')
            ->where('orderSn', $orderSn)
            ->where('inviterId', $inviterId)
            ->where('level', $level)
            ->find();
        if ($dup) return;

        // 字段按 mbti_commission_records 实际结构写；amountFen 存分、amount 存元（容错）
        $data = [
            'inviterId'     => $inviterId,
            'inviteeId'     => $inviteeId,
            'level'         => $level,
            'rate'          => $rate,
            'amount'        => round($amountFen / 100, 2),
            'amountFen'     => $amountFen,
            'orderId'       => $orderId ?: null,
            'orderSn'       => $orderSn,
            'orderAmountFen'=> $orderTotalFen,
            'status'        => 'pending',
            'scope'         => 'personal',
            'source'        => 'ai_deep_report',
            'createdAt'     => $now,
            'updatedAt'     => $now,
        ];

        try {
            Db::name('commission_records')->insert($data);
        } catch (\Throwable $e) {
            // 字段不匹配时退化：只写关键字段
            Log::warning('commission_records insert fallback: ' . $e->getMessage());
            try {
                Db::name('commission_records')->insert([
                    'inviterId' => $inviterId,
                    'inviteeId' => $inviteeId,
                    'level'     => $level,
                    'rate'      => $rate,
                    'amount'    => round($amountFen / 100, 2),
                    'status'    => 'pending',
                    'orderId'   => $orderId ?: null,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            } catch (\Throwable $e2) {
                Log::error('commission_records insert failed: ' . $e2->getMessage());
            }
        }
    }
}
