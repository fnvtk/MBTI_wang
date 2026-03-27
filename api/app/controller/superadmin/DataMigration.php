<?php
namespace app\controller\superadmin;

use app\BaseController;
use think\facade\Db;
use think\facade\Request;

/**
 * 将「平台侧」无企业归属的订单/测试/用户画像归并到指定企业，供企业后台与概览统计一致展示。
 * 仅超级管理员可调用；默认 dryRun 预览，正式执行需 confirm=true。
 */
class DataMigration extends BaseController
{
    /**
     * POST /api/v1/superadmin/data-migration/attach-orphan-orders
     *
     * Body JSON:
     * - targetEnterpriseId (int, 必填) 目标企业 ID
     * - dryRun (bool, 默认 true) true 只统计不写入
     * - confirm (bool, 默认 false) 与 dryRun=false 同时为真时才写入
     * - orderIds (int[], 可选) 仅处理这些订单 id（仍须满足当前无企业归属）
     * - userIds (int[], 可选) 仅处理这些小程序用户 id 名下的无归属订单
     * - syncPersonalTestResults (bool, 默认 true) 是否把同用户下 enterpriseId 为空的 personal 测试记录一并标到目标企业
     * - syncWechatUsers (bool, 默认 true) 是否将 wechat_users.enterpriseId 为空的用户标到目标企业
     * - clonePersonalProfile (bool, 默认 true) 若无 (userId,enterprise,enterprise) 画像行，则从 personal 行复制一条 enterprise 画像（便于「用户运营」列表出现）
     */
    public function attachOrphanOrders()
    {
        $actor = $this->request->user ?? null;
        if (!$actor || ($actor['role'] ?? '') !== 'superadmin') {
            return error('仅超级管理员可操作', 403);
        }

        $body = Request::post();
        if (!is_array($body)) {
            $body = [];
        }

        $targetEnterpriseId = (int) ($body['targetEnterpriseId'] ?? 0);
        if ($targetEnterpriseId <= 0) {
            return error('targetEnterpriseId 无效', 400);
        }

        $ent = Db::name('enterprises')->where('id', $targetEnterpriseId)->find();
        if (!$ent) {
            return error('目标企业不存在', 404);
        }

        $dryRun = array_key_exists('dryRun', $body) ? (bool) $body['dryRun'] : true;
        $confirm = !empty($body['confirm']);
        $syncPersonalTestResults = array_key_exists('syncPersonalTestResults', $body) ? (bool) $body['syncPersonalTestResults'] : true;
        $syncWechatUsers = array_key_exists('syncWechatUsers', $body) ? (bool) $body['syncWechatUsers'] : true;
        $clonePersonalProfile = array_key_exists('clonePersonalProfile', $body) ? (bool) $body['clonePersonalProfile'] : true;

        $orderIdsFilter = $this->normalizeIdList($body['orderIds'] ?? null);
        $userIdsFilter = $this->normalizeIdList($body['userIds'] ?? null);

        $orderQuery = Db::name('orders')->where(function ($q) {
            $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
        });

        if (!empty($orderIdsFilter)) {
            $orderQuery->whereIn('id', $orderIdsFilter);
        }
        if (!empty($userIdsFilter)) {
            $orderQuery->whereIn('userId', $userIdsFilter);
        }

        $orderRows = $orderQuery->field('id,userId,orderNo,enterpriseId,status,amount')->select()->toArray();
        $affectedOrderIds = array_values(array_unique(array_filter(array_column($orderRows, 'id'))));
        $userIdsFromOrders = array_values(array_unique(array_filter(array_column($orderRows, 'userId'))));

        $testByOrderCount = 0;
        if (!empty($affectedOrderIds)) {
            $testByOrderCount = (int) Db::name('test_results')
                ->whereIn('orderId', $affectedOrderIds)
                ->count();
        }

        $personalTestExtraCount = 0;
        if ($syncPersonalTestResults && !empty($userIdsFromOrders)) {
            $personalTestExtraCount = (int) Db::name('test_results')
                ->whereIn('userId', $userIdsFromOrders)
                ->where('testScope', 'personal')
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
                })
                ->count();
        }

        $wechatPatchCount = 0;
        if ($syncWechatUsers && !empty($userIdsFromOrders)) {
            $wechatPatchCount = (int) Db::name('wechat_users')
                ->whereIn('id', $userIdsFromOrders)
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
                })
                ->count();
        }

        $profileCloneCount = 0;
        if ($clonePersonalProfile && !empty($userIdsFromOrders)) {
            foreach ($userIdsFromOrders as $uid) {
                $hasEnt = Db::name('user_profile')
                    ->where('userId', $uid)
                    ->where('userType', 'enterprise')
                    ->where('enterpriseId', $targetEnterpriseId)
                    ->find();
                if (!$hasEnt) {
                    $profileCloneCount++;
                }
            }
        }

        $preview = [
            'targetEnterpriseId'   => $targetEnterpriseId,
            'enterpriseName'       => $ent['name'] ?? '',
            'ordersMatched'        => count($orderRows),
            'orderIds'             => $affectedOrderIds,
            'distinctUserIds'      => $userIdsFromOrders,
            'testResultsByOrder'   => $testByOrderCount,
            'testResultsPersonalExtra' => $personalTestExtraCount,
            'wechatUsersToPatch'   => $wechatPatchCount,
            'userProfilesToClone'  => $profileCloneCount,
            'dryRun'               => $dryRun,
        ];

        if ($dryRun || !$confirm) {
            $preview['hint'] = $dryRun
                ? '当前为预览（dryRun=true）。若要执行写入，请传 dryRun=false 且 confirm=true。'
                : '未执行写入：请同时传 dryRun=false 与 confirm=true。';
            return success($preview);
        }

        $now = time();
        Db::startTrans();
        try {
            if (!empty($affectedOrderIds)) {
                Db::name('orders')
                    ->whereIn('id', $affectedOrderIds)
                    ->update([
                        'enterpriseId' => $targetEnterpriseId,
                        'updatedAt'    => $now,
                    ]);

                Db::name('test_results')
                    ->whereIn('orderId', $affectedOrderIds)
                    ->update([
                        'enterpriseId' => $targetEnterpriseId,
                        'testScope'    => 'enterprise',
                        'updatedAt'    => $now,
                    ]);
            }

            if ($syncPersonalTestResults && !empty($userIdsFromOrders)) {
                Db::name('test_results')
                    ->whereIn('userId', $userIdsFromOrders)
                    ->where('testScope', 'personal')
                    ->where(function ($q) {
                        $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
                    })
                    ->update([
                        'enterpriseId' => $targetEnterpriseId,
                        'testScope'    => 'enterprise',
                        'updatedAt'    => $now,
                    ]);
            }

            if ($syncWechatUsers && !empty($userIdsFromOrders)) {
                Db::name('wechat_users')
                    ->whereIn('id', $userIdsFromOrders)
                    ->where(function ($q) {
                        $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
                    })
                    ->update([
                        'enterpriseId' => $targetEnterpriseId,
                    ]);
            }

            if ($clonePersonalProfile && !empty($userIdsFromOrders)) {
                foreach ($userIdsFromOrders as $uid) {
                    $this->ensureEnterpriseProfileFromPersonal((int) $uid, $targetEnterpriseId, $now);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return error('迁移失败：' . $e->getMessage(), 500);
        }

        $preview['executed'] = true;
        $preview['hint'] = '已写入。企业管理员刷新「订单运营 / 概览 / 用户运营」即可看到归属数据。超管仍可见全平台订单。';
        return success($preview, '迁移完成');
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    private function normalizeIdList($raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    private function ensureEnterpriseProfileFromPersonal(int $userId, int $enterpriseId, int $now): void
    {
        if ($userId <= 0 || $enterpriseId <= 0) {
            return;
        }
        $exists = Db::name('user_profile')
            ->where('userId', $userId)
            ->where('userType', 'enterprise')
            ->where('enterpriseId', $enterpriseId)
            ->find();
        if ($exists) {
            return;
        }

        $personal = Db::name('user_profile')
            ->where('userId', $userId)
            ->where('userType', 'personal')
            ->where(function ($q) {
                $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
            })
            ->order('id', 'desc')
            ->find();

        $base = [
            'userId'           => $userId,
            'userType'         => 'enterprise',
            'enterpriseId'     => $enterpriseId,
            'testsTotal'       => 0,
            'testsMbti'        => 0,
            'testsDisc'        => 0,
            'testsPdp'         => 0,
            'testsFace'        => 0,
            'ordersTotal'      => 0,
            'paidOrders'       => 0,
            'totalPaidAmount'  => 0,
            'lastTestResultId' => null,
            'lastTestType'     => null,
            'lastTestAt'       => null,
            'lastMbtiResultId' => null,
            'lastDiscResultId' => null,
            'lastPdpResultId'  => null,
            'lastFaceResultId' => null,
            'createdAt'        => $now,
            'updatedAt'        => $now,
        ];

        if ($personal) {
            $copyFields = [
                'testsTotal', 'testsMbti', 'testsDisc', 'testsPdp', 'testsFace',
                'ordersTotal', 'paidOrders', 'totalPaidAmount',
                'lastTestResultId', 'lastTestType', 'lastTestAt',
                'lastMbtiResultId', 'lastDiscResultId', 'lastPdpResultId', 'lastFaceResultId',
            ];
            foreach ($copyFields as $f) {
                if (array_key_exists($f, $personal) && $personal[$f] !== null) {
                    $base[$f] = $personal[$f];
                }
            }
        }

        Db::name('user_profile')->insert($base);
    }

    /**
     * 将全平台「无 enterpriseId」的 test_results 与 wechat_users 归属到存客宝（或指定企业）
     * POST /api/v1/superadmin/data-migration/attach-orphans-to-cunkbao
     *
     * Body: targetEnterpriseId (可选)、dryRun (默认 true)、confirm、clonePersonalProfile (默认 true)
     */
    public function attachOrphansToCunkbao()
    {
        $actor = $this->request->user ?? null;
        if (!$actor || ($actor['role'] ?? '') !== 'superadmin') {
            return error('仅超级管理员可操作', 403);
        }

        $body = Request::post();
        if (!is_array($body)) {
            $body = [];
        }

        $dryRun = array_key_exists('dryRun', $body) ? (bool) $body['dryRun'] : true;
        $confirm = !empty($body['confirm']);
        $clonePersonalProfile = array_key_exists('clonePersonalProfile', $body) ? (bool) $body['clonePersonalProfile'] : true;

        $targetEnterpriseId = (int) ($body['targetEnterpriseId'] ?? 0);
        if ($targetEnterpriseId <= 0) {
            $row = Db::name('enterprises')->where('name', 'like', '%存客宝%')->order('id', 'asc')->find();
            if (!$row) {
                return error('未找到名称包含「存客宝」的企业，请先在企业管理中创建或传入 targetEnterpriseId', 404);
            }
            $targetEnterpriseId = (int) $row['id'];
        }

        $ent = Db::name('enterprises')->where('id', $targetEnterpriseId)->find();
        if (!$ent) {
            return error('目标企业不存在', 404);
        }

        $testAffected = (int) Db::name('test_results')
            ->where(function ($q) {
                $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0)->whereOr('enterpriseId', '');
            })
            ->count();

        $wechatAffected = (int) Db::name('wechat_users')
            ->where(function ($q) {
                $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
            })
            ->count();

        $userIdsFromTests = Db::name('test_results')
            ->where(function ($q) {
                $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0)->whereOr('enterpriseId', '');
            })
            ->distinct(true)
            ->column('userId');
        $userIdsFromWechat = Db::name('wechat_users')
            ->where(function ($q) {
                $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
            })
            ->column('id');
        $distinctUserIds = array_values(array_unique(array_filter(array_merge($userIdsFromTests, $userIdsFromWechat))));

        $preview = [
            'targetEnterpriseId' => $targetEnterpriseId,
            'enterpriseName'     => $ent['name'] ?? '',
            'testResultsRows'    => $testAffected,
            'wechatUsersRows'    => $wechatAffected,
            'distinctUserIds'    => $distinctUserIds,
            'dryRun'             => $dryRun,
        ];

        if ($dryRun || !$confirm) {
            $preview['hint'] = $dryRun
                ? '当前为预览。写入请传 dryRun=false 且 confirm=true。'
                : '未写入：请同时传 dryRun=false 与 confirm=true。';
            return success($preview);
        }

        $now = time();
        Db::startTrans();
        try {
            Db::name('test_results')
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0)->whereOr('enterpriseId', '');
                })
                ->update([
                    'enterpriseId' => $targetEnterpriseId,
                    'testScope'    => 'enterprise',
                    'updatedAt'    => $now,
                ]);

            Db::name('wechat_users')
                ->where(function ($q) {
                    $q->whereNull('enterpriseId')->whereOr('enterpriseId', 0);
                })
                ->update([
                    'enterpriseId' => $targetEnterpriseId,
                ]);

            if ($clonePersonalProfile && !empty($distinctUserIds)) {
                foreach ($distinctUserIds as $uid) {
                    $this->ensureEnterpriseProfileFromPersonal((int) $uid, $targetEnterpriseId, $now);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return error('归并失败：' . $e->getMessage(), 500);
        }

        $preview['executed'] = true;
        $preview['hint'] = '已写入。无企业归属的测试与用户已归属到目标企业。';
        return success($preview, '归并完成');
    }
}
