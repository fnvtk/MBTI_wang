<?php
namespace app\common\service;

use app\common\PdpDiscResultText;
use app\common\service\EnterpriseBillingService;
use app\controller\api\Distribution;
use app\controller\api\Test as TestApiController;
use app\model\GaokaoUserProfile;
use think\facade\Db;

/**
 * 高考志愿业务服务
 *
 * 售价与小程序 Tab 一致：pricingScope=personal → admin_personal / 全局 personal；enterprise → admin_enterprise.gaokao
 *（需能解析出企业 ID：请求 enterpriseId / 绑定企业 / 子测评 enterpriseId）。
 *
 * test_results.enterpriseId：平台费扣款归属；
 * 每次「生成报告」都新建一条 test_results 行（与 MBTI/PDP/DISC/Face 测完即一条新记录的语义保持一致），
 * 平台费在 **生成报告成功（createAnalysis 提交事务后）** 立即扣除（按 testResultId 幂等，不复用旧行避免被旧扣费记录"幂等命中"导致漏扣）。
 */
class GaokaoService
{
    public static function resolveTenantIdByWechatUserId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $eid = (int) Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
        return $eid > 0 ? $eid : 0;
    }

    public static function getOrInitProfile(int $userId): GaokaoUserProfile
    {
        $tenantId = self::resolveTenantIdByWechatUserId($userId);
        $row = GaokaoUserProfile::where('userId', $userId)->find();
        if ($row) {
            if ((int) ($row->tenantId ?? 0) !== $tenantId) {
                $row->tenantId = $tenantId;
                $row->save();
            }
            return $row;
        }

        $now = time();
        $row = new GaokaoUserProfile([
            'userId' => $userId,
            'tenantId' => $tenantId,
            'entryStatus' => 0,
            'mbtiStatus' => 0,
            'pdpStatus' => 0,
            'discStatus' => 0,
            'formStatus' => 0,
            'analyzeStatus' => 0,
            'lastAnalyzeAt' => null,
            'latestReportId' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);
        $row->save();
        return $row;
    }

    public static function markEntry(int $userId, array $entry = []): void
    {
        $profile = self::getOrInitProfile($userId);
        $profile->entryStatus = 1;
        $profile->save();

        $referrerId = (int) ($entry['referrerId'] ?? 0);
        if ($referrerId <= 0) {
            return;
        }

        $tenantId = (int) ($profile->tenantId ?? 0);
        $enterpriseId = $tenantId > 0 ? $tenantId : null;
        Distribution::applyInviteBindingFromGaokao($userId, $referrerId, $enterpriseId);
    }

    /** 是否存在已支付的高考 test_results（用于解锁分析） */
    public static function hasGaokaoPaid(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $id = Db::name('test_results')
            ->where('userId', $userId)
            ->where('testType', 'gaokao')
            ->where('isPaid', 1)
            ->value('id');

        return !empty($id);
    }

    /**
     * 定价为 0 元时无需微信支付：补一条已付占位 test_results，避免任务中心一直「待支付」且 analyze 找不到 tid
     */
    public static function ensureFreeGaokaoPaidIfZeroPrice(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $pr = self::resolvePricing($userId, 'gaokao_single_report', 'personal', null);
        if ((int) ($pr['priceSale'] ?? 0) > 0) {
            return;
        }
        if (self::hasGaokaoPaid($userId)) {
            return;
        }
        $last = Db::name('test_results')
            ->where('userId', $userId)
            ->where('testType', 'gaokao')
            ->order('id', 'desc')
            ->find();
        $now = time();
        $ctx = self::resolveGaokaoReportSaleContext($userId, 'personal', null);
        $writeEid = $ctx['writeEnterpriseId'] ?? null;
        $testScope = (($ctx['pricingTier'] ?? 'personal') === 'enterprise') ? 'enterprise' : 'personal';
        if ($last) {
            Db::name('test_results')
                ->where('id', (int) $last['id'])
                ->where('userId', $userId)
                ->update([
                    'requiresPayment' => 0,
                    'isPaid' => 1,
                    'paidAmount' => 0,
                    'paidAt' => $now,
                    'updatedAt' => $now,
                    'enterpriseId' => $writeEid,
                    'testScope' => $testScope,
                ]);

            return;
        }
        Db::name('test_results')->insert([
            'userId' => $userId,
            'testType' => 'gaokao',
            'resultData' => json_encode([
                'kind' => 'gaokao',
                'state' => 'free_unlock',
                'version' => 'v1',
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'score' => null,
            'orderId' => null,
            'requiresPayment' => 0,
            'isPaid' => 1,
            'paidAmount' => 0,
            'paidAt' => $now,
            'createdAt' => $now,
            'updatedAt' => $now,
            'enterpriseId' => $writeEid,
            'testScope' => $testScope,
        ]);
    }

    public static function loadTaskStatus(int $userId): array
    {
        $profile = self::getOrInitProfile($userId);
        $latest = self::latestTestMap($userId);

        $mbtiDone = !empty($latest['mbti']);
        $pdpDone = !empty($latest['pdp']);
        $discDone = !empty($latest['disc']);
        $faceDone = !empty($latest['face']);
        $formDone = (int) ($profile->formStatus ?? 0) === 1;
        $allDone = $mbtiDone && $pdpDone && $discDone && $faceDone && $formDone;
        $gaokaoPaid = self::hasGaokaoPaid($userId);
        $analyzed = (int) ($profile->analyzeStatus ?? 0) === 1;

        // 同步写回状态，避免前后端状态不一致
        $profile->mbtiStatus = $mbtiDone ? 1 : 0;
        $profile->pdpStatus = $pdpDone ? 1 : 0;
        $profile->discStatus = $discDone ? 1 : 0;
        $profile->entryStatus = $allDone ? ($analyzed ? 2 : 1) : 1;
        $profile->save();

        $tasks = [
            'mbti' => self::buildTaskItem('mbti', $mbtiDone, $latest['mbti'] ?? null),
            'pdp' => self::buildTaskItem('pdp', $pdpDone, $latest['pdp'] ?? null),
            'disc' => self::buildTaskItem('disc', $discDone, $latest['disc'] ?? null),
            'face' => self::buildTaskItem('face', $faceDone, $latest['face'] ?? null),
            'form' => self::buildFormTaskItem($formDone, $profile),
        ];

        $missing = [];
        foreach ($tasks as $k => $v) {
            if (($v['status'] ?? 'todo') !== 'done') {
                $missing[] = $k;
            }
        }

        return [
            'tasks' => $tasks,
            /** 任务齐即可生成分析；付费在报告页解锁全文（与 MBTI/人脸一致） */
            'canAnalyze' => $allDone,
            'missingItems' => $missing,
            'analyzeStatus' => (int) ($profile->analyzeStatus ?? 0),
            'latestReportId' => (int) ($profile->latestReportId ?? 0),
            'gaokaoPaid' => $gaokaoPaid,
            'needGaokaoPayment' => false,
        ];
    }

    /**
     * @param string $pricingScope personal|enterprise
     */
    public static function loadTaskStatusWithPricing(
        int $userId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): array {
        $out = self::loadTaskStatus($userId);
        $out['gaokaoPricing'] = self::resolvePricing($userId, 'gaokao_single_report', $pricingScope, $requestEnterpriseId);

        return $out;
    }

    public static function saveForm(int $userId, array $form): GaokaoUserProfile
    {
        $profile = self::getOrInitProfile($userId);
        $profile->name = trim((string) ($form['name'] ?? $profile->name ?? ''));
        $profile->province = trim((string) ($form['province'] ?? $profile->province ?? ''));
        $profile->streamSubjects = trim((string) ($form['streamOrSubjects'] ?? $profile->streamSubjects ?? ''));
        $profile->estimatedScore = isset($form['estimatedScore']) ? (int) $form['estimatedScore'] : null;
        $profile->formJson = $form;
        $profile->formStatus = 1;
        if ((int) ($profile->entryStatus ?? 0) === 0) {
            $profile->entryStatus = 1;
        }
        $now = time();
        $profile->updatedAt = $now;
        $profile->save();
        return $profile;
    }

    /**
     * formJson 落库可能为 JSON 列/文本；读出口可能是 string、stdClass 或 array，统一为数组供接口返回
     */
    public static function formJsonAsArray(GaokaoUserProfile $profile): array
    {
        return self::jsonLikeToArray($profile->formJson ?? null);
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>
     */
    private static function jsonLikeToArray($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw instanceof \stdClass) {
            $a = json_decode(json_encode($raw, JSON_UNESCAPED_UNICODE), true);
            return is_array($a) ? $a : [];
        }
        if (is_string($raw)) {
            $t = trim($raw);
            if ($t === '') {
                return [];
            }
            $a = json_decode($t, true);
            return is_array($a) ? $a : [];
        }
        return [];
    }

    /**
     * @param string $pricingScope personal|enterprise（与小程序 appScope 一致）
     */
    public static function resolvePricing(
        int $userId,
        string $productCode = 'gaokao_single_report',
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): array {
        $tenantId = self::resolveTenantIdByWechatUserId($userId);
        $ctx = self::resolveGaokaoReportSaleContext($userId, $pricingScope, $requestEnterpriseId);
        $fen = (int) ($ctx['fen'] ?? 0);

        return [
            'hasPricing' => $fen > 0,
            'productCode' => $productCode,
            'pricingId' => 0,
            'priceOriginal' => $fen,
            'priceSale' => $fen,
            'currency' => 'CNY',
            'tenantId' => $tenantId,
            'pricingType' => (string) ($ctx['pricingType'] ?? 'personal'),
            'pricingScope' => (string) ($ctx['pricingTier'] ?? 'personal'),
        ];
    }

    /**
     * 支付侧重算高考应付（与当前 Tab / 请求参数一致）
     *
     * @param string $pricingScope personal|enterprise
     * @return array{0:int,1:string} [amountFen, pricingType]
     */
    public static function gaokaoSaleAmountForPaymentRecalc(
        int $wechatUserId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): array {
        if ($wechatUserId <= 0) {
            return [0, 'personal'];
        }
        $ctx = self::resolveGaokaoReportSaleContext($wechatUserId, $pricingScope, $requestEnterpriseId);

        return [(int) ($ctx['fen'] ?? 0), (string) ($ctx['pricingType'] ?? 'personal')];
    }

    /**
     * 发起支付或打开报告前：按当前 Tab 刷新未付高考记录的 paidAmount / enterpriseId（与订单金额一致）
     *
     * @param string $pricingScope personal|enterprise
     */
    public static function refreshGaokaoTestResultForPayment(
        int $userId,
        int $testResultId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): void {
        if ($userId <= 0 || $testResultId <= 0) {
            return;
        }
        $row = Db::name('test_results')
            ->where('id', $testResultId)
            ->where('userId', $userId)
            ->where('testType', 'gaokao')
            ->find();
        if (!$row || (int) ($row['isPaid'] ?? 0) !== 0) {
            return;
        }

        $ctx = self::resolveGaokaoReportSaleContext($userId, $pricingScope, $requestEnterpriseId);
        $now = time();
        $writeEid = $ctx['writeEnterpriseId'] ?? null;
        $testScope = (($ctx['pricingTier'] ?? 'personal') === 'enterprise') ? 'enterprise' : 'personal';

        $orderId = (int) ($row['orderId'] ?? 0);
        if ($orderId > 0) {
            Db::name('test_results')
                ->where('id', $testResultId)
                ->where('userId', $userId)
                ->where('testType', 'gaokao')
                ->where('isPaid', 0)
                ->update([
                    'enterpriseId' => $writeEid,
                    'testScope' => $testScope,
                    'updatedAt' => $now,
                ]);

            return;
        }

        $fen = (int) ($ctx['fen'] ?? 0);
        $audit = miniprogram_audit_mode_on();
        $requiresPayment = (!$audit && $fen > 0) ? 1 : 0;
        $isPaid = $requiresPayment ? 0 : 1;
        $paidAmount = ($requiresPayment && $fen > 0) ? $fen : ($isPaid ? 0 : null);
        $paidAt = $isPaid ? $now : null;

        Db::name('test_results')
            ->where('id', $testResultId)
            ->where('userId', $userId)
            ->where('testType', 'gaokao')
            ->where('isPaid', 0)
            ->update([
                'enterpriseId' => $writeEid,
                'testScope' => $testScope,
                'requiresPayment' => $requiresPayment,
                'isPaid' => $isPaid,
                'paidAmount' => $paidAmount,
                'paidAt' => $paidAt,
                'updatedAt' => $now,
            ]);
    }

    /**
     * 子测评最新一条是否带企业归属（与问卷提交时 enterpriseId 一致）
     */
    private static function inferEnterpriseIdFromPrerequisiteTests(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $latest = self::latestTestMap($userId);
        foreach (['mbti', 'pdp', 'disc', 'face'] as $k) {
            $row = $latest[$k] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $e = (int) ($row['enterpriseId'] ?? 0);
            if ($e > 0) {
                return $e;
            }
        }

        return 0;
    }

    /**
     * 报告生成后扣平台费：优先定价上下文中的企业，再绑定企业，再子测评 enterpriseId。
     */
    private static function resolveGaokaoPlatformFeeEnterpriseId(int $userId, ?int $preferredFromPricing): int
    {
        $e = (int) ($preferredFromPricing ?? 0);
        if ($e > 0) {
            return $e;
        }
        if ($userId > 0) {
            $e = self::resolveTenantIdByWechatUserId($userId);
        }
        if ($e > 0) {
            return $e;
        }

        return self::inferEnterpriseIdFromPrerequisiteTests($userId);
    }

    /**
     * @param string $pricingScope personal|enterprise
     * @return array{fen:int, pricingType:string, writeEnterpriseId:?int, pricingTier:string}
     */
    private static function resolveGaokaoReportSaleContext(
        int $userId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): array {
        $pricingScope = ($pricingScope === 'enterprise') ? 'enterprise' : 'personal';
        $bound = self::resolveTenantIdByWechatUserId($userId);
        $eFromTests = self::inferEnterpriseIdFromPrerequisiteTests($userId);

        $eidForEnterprise = (int) ($requestEnterpriseId ?? 0);
        if ($eidForEnterprise <= 0) {
            $eidForEnterprise = $bound > 0 ? $bound : $eFromTests;
        }

        if ($pricingScope === 'enterprise' && $eidForEnterprise > 0) {
            [$fen, $pricingType] = TestProductPricing::amountFenForTestProduct(
                'gaokao',
                $userId,
                $eidForEnterprise,
                1,
                'enterprise'
            );

            return [
                'fen' => (int) $fen,
                'pricingType' => $pricingType,
                'writeEnterpriseId' => $eidForEnterprise,
                'pricingTier' => 'enterprise',
            ];
        }

        [$fen, $pricingType] = TestProductPricing::amountFenForTestProduct(
            'gaokao',
            $userId,
            null,
            1,
            'personal'
        );

        $write = 0;
        if ((int) ($requestEnterpriseId ?? 0) > 0) {
            $write = (int) $requestEnterpriseId;
        } elseif ($bound > 0) {
            $write = $bound;
        } elseif ($eFromTests > 0) {
            $write = $eFromTests;
        }

        return [
            'fen' => (int) $fen,
            'pricingType' => $pricingType,
            'writeEnterpriseId' => $write > 0 ? $write : null,
            'pricingTier' => 'personal',
        ];
    }

    /**
     * 每次「生成分析」都新建一行 gaokao test_results：
     *  - 与 MBTI / PDP / DISC / Face 行为对齐（每次提交即一条记录）
     *  - 平台费按 testResultId 幂等，不复用旧行 → 重新生成会按企业版单价再次扣费
     *  - 旧的未付占位仅作为历史保留，不阻碍新报告
     *
     * @param string $pricingScope personal|enterprise
     */
    private static function acquireGaokaoAnalysisTestResultId(
        int $userId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): int {
        if ($userId <= 0) {
            return 0;
        }

        $ctx = self::resolveGaokaoReportSaleContext($userId, $pricingScope, $requestEnterpriseId);
        $fen = (int) ($ctx['fen'] ?? 0);
        $audit = miniprogram_audit_mode_on();
        $requiresPayment = (!$audit && $fen > 0) ? 1 : 0;
        $isPaid = $requiresPayment ? 0 : 1;
        $now = time();
        $writeEid = $ctx['writeEnterpriseId'] ?? null;
        $testScope = (($ctx['pricingTier'] ?? 'personal') === 'enterprise') ? 'enterprise' : 'personal';

        $id = Db::name('test_results')->insertGetId([
            'userId' => $userId,
            'testType' => 'gaokao',
            'resultData' => json_encode([
                'kind' => 'gaokao',
                'state' => 'generating',
                'version' => 'v1',
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'score' => null,
            'orderId' => null,
            'requiresPayment' => $requiresPayment,
            'isPaid' => $isPaid,
            'paidAmount' => ($requiresPayment && $fen > 0) ? $fen : ($isPaid ? 0 : null),
            'paidAt' => $isPaid ? $now : null,
            'createdAt' => $now,
            'updatedAt' => $now,
            'enterpriseId' => $writeEid,
            'testScope' => $testScope,
        ]);

        return (int) $id;
    }

    /**
     * @param string $pricingScope personal|enterprise 与小程序 appScope 一致
     */
    public static function createAnalysis(
        int $userId,
        string $pricingScope = 'personal',
        ?int $requestEnterpriseId = null
    ): array {
        $task = self::loadTaskStatus($userId);
        if (empty($task['canAnalyze'])) {
            return [
                'ok' => false,
                'message' => '请先完成测试与表单后再分析',
                'missingItems' => $task['missingItems'] ?? [],
            ];
        }

        $profile = self::getOrInitProfile($userId);
        $latest = self::latestTestMap($userId);
        $form = self::formJsonAsArray($profile);

        $input = [
            'name' => (string) ($profile->name ?? ''),
            'province' => (string) ($profile->province ?? ''),
            'streamSubjects' => (string) ($profile->streamSubjects ?? ''),
            'estimatedScore' => (int) ($profile->estimatedScore ?? 0),
            'mbti' => self::extractSummaryType('mbti', is_array($latest['mbti'] ?? null) ? ($latest['mbti']['resultData'] ?? []) : []),
            'pdp' => self::extractSummaryType('pdp', is_array($latest['pdp'] ?? null) ? ($latest['pdp']['resultData'] ?? []) : []),
            'disc' => self::extractSummaryType('disc', is_array($latest['disc'] ?? null) ? ($latest['disc']['resultData'] ?? []) : []),
            'faceMbti' => self::extractSummaryType('face', is_array($latest['face'] ?? null) ? ($latest['face']['resultData'] ?? []) : []),
            'form' => $form,
        ];

        $tid = self::acquireGaokaoAnalysisTestResultId($userId, $pricingScope, $requestEnterpriseId);
        if ($tid <= 0) {
            return [
                'ok' => false,
                'message' => '创建高考测评记录失败，请稍后重试',
                'missingItems' => [],
            ];
        }

        $promptSystem = '你是高考志愿分析助手。输出严格 JSON，字段至少包含 overview, schoolRecommend, majorRecommend, personalityReason, disclaimers, searchMeta。searchMeta.queryCount 必须是数字。';
        $promptUser = '请根据以下用户数据，输出高考志愿建议：' . json_encode($input, JSON_UNESCAPED_UNICODE);

        $content = '';
        for ($i = 0; $i < 2; $i++) {
            $res = AiCallService::chat([
                ['role' => 'system', 'content' => $promptSystem],
                ['role' => 'user', 'content' => $promptUser],
            ], ['temperature' => 0.2, 'maxTokens' => 2200]);
            $content = trim((string) ($res['content'] ?? ''));
            if ($content !== '') {
                break;
            }
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            $json = self::fallbackReport($input);
        }
        $json = self::normalizeReport($json, $input);

        $now = time();
        $resultPayload = [
            'version' => 'v1',
            'kind' => 'gaokao',
            'state' => 'ready',
            'inputSnapshot' => $input,
            'report' => $json,
            'overview' => (string) ($json['overview'] ?? ''),
            'searchMeta' => $json['searchMeta'] ?? [],
        ];

        $ctx = self::resolveGaokaoReportSaleContext($userId, $pricingScope, $requestEnterpriseId);
        $writeEid = $ctx['writeEnterpriseId'] ?? null;
        $testScope = (($ctx['pricingTier'] ?? 'personal') === 'enterprise') ? 'enterprise' : 'personal';

        Db::startTrans();
        try {
            Db::name('test_results')
                ->where('id', $tid)
                ->where('userId', $userId)
                ->update([
                    'resultData' => json_encode($resultPayload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    'enterpriseId' => $writeEid,
                    'testScope' => $testScope,
                    'updatedAt' => $now,
                ]);

            $profile->analyzeStatus = 1;
            $profile->lastAnalyzeAt = $now;
            $profile->latestReportId = $tid;
            $profile->entryStatus = 2;
            $profile->save();

            Db::commit();

            // 报告生成成功即扣企业平台费（与 Test::saveResult 一致）；ctx 未带企业时回落绑定/子测，避免漏扣
            try {
                $eidBill = self::resolveGaokaoPlatformFeeEnterpriseId($userId, $writeEid);
                if ($eidBill > 0) {
                    $rowE = (int) Db::name('test_results')->where('id', $tid)->value('enterpriseId');
                    if ($rowE <= 0) {
                        Db::name('test_results')->where('id', $tid)->update([
                            'enterpriseId' => $eidBill,
                            'updatedAt' => time(),
                        ]);
                    }
                    EnterpriseBillingService::chargePlatformFeeForTestResult($tid, 'gaokao', $eidBill);
                }
            } catch (\Throwable $e) {
                // 扣费失败不阻断已生成的报告
            }

            return [
                'ok' => true,
                'reportId' => $tid,
                'report' => $json,
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            return [
                'ok' => false,
                'message' => '生成失败：' . $e->getMessage(),
            ];
        }
    }

    public static function myLatestReport(int $userId): ?array
    {
        $row = Db::name('test_results')
            ->where('userId', $userId)
            ->where('testType', 'gaokao')
            ->order('id', 'desc')
            ->find();
        if (!$row) {
            return null;
        }
        $raw = $row['resultData'] ?? '';
        $rd = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $requiresPayment = (int) ($row['requiresPayment'] ?? 0);
        $isPaid = (int) ($row['isPaid'] ?? 0);
        if ($requiresPayment && !$isPaid && !miniprogram_audit_mode_on()) {
            $filtered = TestApiController::filterResultToPartialStatic('gaokao', $rd);
            $filtered = is_array($filtered) ? $filtered : [];
            $ov = (string) ($filtered['overview'] ?? '');
            $inputSnap = is_array($filtered['inputSnapshot'] ?? null)
                ? $filtered['inputSnapshot']
                : (is_array($rd['inputSnapshot'] ?? null) ? $rd['inputSnapshot'] : []);
            $report = self::normalizeReport([
                'overview' => $ov,
                'personalityReason' => '',
                'disclaimers' => '',
                'majorRecommend' => [],
                'schoolRecommend' => [],
                'locked' => true,
                'inputEcho' => [
                    'name' => (string) ($inputSnap['name'] ?? ''),
                    'province' => (string) ($inputSnap['province'] ?? ''),
                    'streamSubjects' => (string) ($inputSnap['streamSubjects'] ?? ''),
                    'estimatedScore' => isset($inputSnap['estimatedScore']) ? (int) $inputSnap['estimatedScore'] : 0,
                    'mbti' => (string) ($inputSnap['mbti'] ?? ''),
                    'pdp' => (string) ($inputSnap['pdp'] ?? ''),
                    'disc' => (string) ($inputSnap['disc'] ?? ''),
                ],
            ], $inputSnap);
            return [
                'id' => (int) $row['id'],
                'createdAt' => (int) ($row['createdAt'] ?? 0),
                'overview' => $ov,
                'report' => $report,
            ];
        }

        $report = $rd['report'] ?? null;
        if (is_string($report)) {
            $report = json_decode(trim($report), true);
        }
        if (!is_array($report) || $report === []) {
            return null;
        }
        $input = is_array($rd['inputSnapshot'] ?? null) ? $rd['inputSnapshot'] : [];
        $report = self::normalizeReport($report, $input);
        $overview = (string) ($rd['overview'] ?? $report['overview'] ?? '');

        return [
            'id' => (int) $row['id'],
            'createdAt' => (int) ($row['createdAt'] ?? 0),
            'overview' => $overview,
            'report' => $report,
        ];
    }

    /**
     * 与 api Test::decodeResultDataPayload 一致：解析 resultData 并展开内层 result（PDP/DISC 分数在嵌套里时任务摘要才正确）
     *
     * @param mixed $raw
     * @return array<string,mixed>
     */
    private static function decodeResultDataPayloadForTask($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            $data = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode(trim($raw), true);
            $data = is_array($decoded) ? $decoded : [];
        } else {
            return [];
        }
        if (isset($data['result']) && is_array($data['result'])) {
            $inner = $data['result'];
            if (isset($inner['percentages']) || isset($inner['scores']) || isset($inner['dominantType'])
                || isset($inner['description']) || isset($inner['mbtiType'])) {
                $data = array_merge($data, $inner);
            }
        }

        return $data;
    }

    private static function latestTestMap(int $userId): array
    {
        $rows = Db::name('test_results')
            ->where('userId', $userId)
            ->whereIn('testType', ['mbti', 'pdp', 'disc', 'face', 'ai'])
            ->order('createdAt', 'desc')
            ->field('id,testType,resultData,createdAt,enterpriseId')
            ->select()
            ->toArray();
        $out = [];
        foreach ($rows as $r) {
            $t = (string) ($r['testType'] ?? '');
            if ($t === '') {
                continue;
            }
            if ($t === 'face' || $t === 'ai') {
                if (isset($out['face'])) {
                    continue;
                }
                $r['resultData'] = self::decodeResultDataPayloadForTask($r['resultData'] ?? null);
                $out['face'] = $r;

                continue;
            }
            if (isset($out[$t])) {
                continue;
            }
            $r['resultData'] = self::decodeResultDataPayloadForTask($r['resultData'] ?? null);
            $out[$t] = $r;
        }

        return $out;
    }

    /**
     * 与 /api/test/recent 单条结构对齐，便于小程序「我的测评」同款卡片渲染
     *
     * @return array<string,mixed>
     */
    private static function buildTaskItem(string $code, bool $done, ?array $row): array
    {
        $labels = [
            'mbti' => ['testType' => 'mbti', 'typeName' => 'MBTI性格', 'emoji' => '🧠'],
            'pdp' => ['testType' => 'pdp', 'typeName' => 'PDP行为', 'emoji' => '🦁'],
            'disc' => ['testType' => 'disc', 'typeName' => 'DISC测评', 'emoji' => '📊'],
            'face' => ['testType' => 'ai', 'typeName' => '拍照面相', 'emoji' => '📷'],
        ];
        $lb = $labels[$code] ?? ['testType' => $code, 'typeName' => $code, 'emoji' => '📋'];

        $resultText = '';
        $updatedAt = 0;
        $data = [];
        $recordTestType = $lb['testType'];
        if ($row) {
            $updatedAt = (int) ($row['createdAt'] ?? 0);
            $data = is_array($row['resultData'] ?? null) ? $row['resultData'] : [];
            $resultText = self::extractSummaryType($code, $data);
            if ($code === 'face') {
                $rt = strtolower((string) ($row['testType'] ?? 'ai'));
                $recordTestType = ($rt === 'face' || $rt === 'ai') ? $rt : 'ai';
            }
        }
        $tid = 0;
        if ($done && $row && !empty($row['id'])) {
            $tid = (int) $row['id'];
        }

        $testTime = ($done && $updatedAt > 0) ? date('Y-m-d', $updatedAt) : '';

        $resultMeta = null;
        if ($done && $code === 'disc' && $data !== []) {
            $resultMeta = [
                'scores' => $data['scores'] ?? null,
                'percentages' => $data['percentages'] ?? null,
                'dominantType' => $data['dominantType'] ?? null,
                'secondaryType' => $data['secondaryType'] ?? null,
                'description' => $data['description'] ?? null,
                'disc' => $data['disc'] ?? null,
            ];
        } elseif ($done && $code === 'pdp' && $data !== []) {
            $resultMeta = [
                'scores' => $data['scores'] ?? null,
                'percentages' => $data['percentages'] ?? null,
                'dominantType' => $data['dominantType'] ?? null,
                'secondaryType' => $data['secondaryType'] ?? null,
                'description' => $data['description'] ?? null,
                'pdp' => $data['pdp'] ?? null,
            ];
        }

        $out = [
            'code' => $code,
            'status' => $done ? 'done' : 'todo',
            'resultText' => $done ? $resultText : '',
            'updatedAt' => $updatedAt,
            'testResultId' => $tid,
            'id' => $tid,
            'testType' => $lb['testType'],
            'typeName' => $lb['typeName'],
            'emoji' => $lb['emoji'],
            'testTime' => $testTime,
            'recordTestType' => $recordTestType,
        ];
        if ($resultMeta !== null) {
            $out['resultMeta'] = $resultMeta;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildFormTaskItem(bool $done, GaokaoUserProfile $profile): array
    {
        $updatedAt = (int) ($profile->updatedAt ?? 0);
        $createdAt = (int) ($profile->createdAt ?? 0);
        $ts = max($updatedAt, $createdAt);
        // 避免未写入时间戳时 date(0) 变成 1970-01-01
        $testTime = ($done && $ts >= 946684800) ? date('Y-m-d', $ts) : '';

        return [
            'code' => 'form',
            'status' => $done ? 'done' : 'todo',
            'resultText' => $done ? ((string) ($profile->province ?? '')) : '',
            'updatedAt' => $ts,
            'testResultId' => 0,
            'id' => 0,
            'testType' => 'form',
            'typeName' => '高考信息表单',
            'emoji' => '📝',
            'testTime' => $testTime,
        ];
    }

    /**
     * 任务摘要文案：PDP/DISC 与 PdpDiscResultText、/api/test/recent 一致
     *
     * @param array<string,mixed> $data
     */
    private static function extractSummaryType(string $code, array $data): string
    {
        $code = strtolower(trim($code));
        if ($code === 'mbti') {
            if (isset($data['mbtiType']) && $data['mbtiType'] !== '') {
                return (string) $data['mbtiType'];
            }

            return '';
        }
        if ($code === 'pdp') {
            $two = PdpDiscResultText::pdpTopTwo($data);
            if ($two !== '') {
                return $two;
            }
            $desc = $data['description'] ?? null;
            $dType = is_array($desc) ? ($desc['type'] ?? null) : null;
            if (is_string($dType) && trim($dType) !== '') {
                return trim($dType);
            }

            return isset($data['pdp']) ? (string) $data['pdp'] : '';
        }
        if ($code === 'disc') {
            $two = PdpDiscResultText::discTopTwo($data);
            if ($two !== '') {
                return $two;
            }
            $dominantType = $data['dominantType'] ?? $data['disc'] ?? '';
            if ($dominantType !== '' && $dominantType !== null) {
                return (is_string($dominantType) || is_numeric($dominantType) ? (string) $dominantType : '未知') . '型';
            }

            return '';
        }
        if ($code === 'face' || $code === 'ai') {
            if (isset($data['mbti']['type']) && $data['mbti']['type'] !== '') {
                return (string) $data['mbti']['type'];
            }
            if (isset($data['mbti']) && !is_array($data['mbti']) && $data['mbti'] !== '') {
                return (string) $data['mbti'];
            }

            return '';
        }

        return '';
    }

    /**
     * @param array<mixed> $a
     */
    private static function isListArray(array $a): bool
    {
        if ($a === []) {
            return true;
        }

        return array_keys($a) === range(0, count($a) - 1);
    }

    /**
     * @param mixed $v
     * @return array<mixed>|null
     */
    private static function tryJsonDecodeArray($v): ?array
    {
        if (is_array($v)) {
            return $v;
        }
        if (!is_string($v)) {
            return null;
        }
        $s = trim($v);
        if ($s === '') {
            return null;
        }
        $d = json_decode($s, true);

        return is_array($d) ? $d : null;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private static function mergeCanonicalReportKeys(array $report): array
    {
        $pairs = [
            'school_recommend' => 'schoolRecommend',
            'schoolRecommendations' => 'schoolRecommend',
            'major_recommend' => 'majorRecommend',
            'majorRecommends' => 'majorRecommend',
            'majors' => 'majorRecommend',
            'personality_reason' => 'personalityReason',
        ];
        foreach ($pairs as $from => $to) {
            if (!array_key_exists($to, $report) && array_key_exists($from, $report)) {
                $report[$to] = $report[$from];
            }
        }
        if (!array_key_exists('disclaimers', $report) && array_key_exists('disclaimer', $report)) {
            $d = $report['disclaimer'];
            $report['disclaimers'] = is_string($d) ? $d : (is_array($d) ? json_encode($d, JSON_UNESCAPED_UNICODE) : '');
        }

        return $report;
    }

    /**
     * @param mixed $raw
     * @return array<mixed>
     */
    private static function normalizeSchoolRecommendStructure($raw): array
    {
        $arr = self::tryJsonDecodeArray($raw);
        if ($arr === null) {
            return ['chong' => [], 'wen' => [], 'bao' => []];
        }
        if (isset($arr['chong']) || isset($arr['wen']) || isset($arr['bao'])) {
            return [
                'chong' => isset($arr['chong']) && is_array($arr['chong']) ? array_values($arr['chong']) : [],
                'wen' => isset($arr['wen']) && is_array($arr['wen']) ? array_values($arr['wen']) : [],
                'bao' => isset($arr['bao']) && is_array($arr['bao']) ? array_values($arr['bao']) : [],
            ];
        }
        if (isset($arr['冲']) || isset($arr['稳']) || isset($arr['保'])) {
            return [
                'chong' => isset($arr['冲']) && is_array($arr['冲']) ? array_values($arr['冲']) : [],
                'wen' => isset($arr['稳']) && is_array($arr['稳']) ? array_values($arr['稳']) : [],
                'bao' => isset($arr['保']) && is_array($arr['保']) ? array_values($arr['保']) : [],
            ];
        }
        if (isset($arr['stretch']) || isset($arr['stable']) || isset($arr['safe']) || isset($arr['safety']) || isset($arr['reach'])) {
            $bao = [];
            if (isset($arr['safe']) && is_array($arr['safe'])) {
                $bao = array_values($arr['safe']);
            } elseif (isset($arr['safety']) && is_array($arr['safety'])) {
                $bao = array_values($arr['safety']);
            }

            return [
                'chong' => isset($arr['stretch']) && is_array($arr['stretch'])
                    ? array_values($arr['stretch'])
                    : (isset($arr['reach']) && is_array($arr['reach']) ? array_values($arr['reach']) : []),
                'wen' => isset($arr['stable']) && is_array($arr['stable'])
                    ? array_values($arr['stable'])
                    : (isset($arr['match']) && is_array($arr['match']) ? array_values($arr['match']) : []),
                'bao' => $bao,
            ];
        }
        foreach (['schools', 'list', 'items'] as $k) {
            if (isset($arr[$k]) && is_array($arr[$k]) && self::isListArray($arr[$k])) {
                return array_values($arr[$k]);
            }
        }
        if (self::isListArray($arr)) {
            return array_values($arr);
        }

        return ['chong' => [], 'wen' => [], 'bao' => []];
    }

    /**
     * @param mixed $raw
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeMajorRecommendStructure($raw): array
    {
        $arr = self::tryJsonDecodeArray($raw);
        if ($arr === null) {
            return [];
        }
        if (!self::isListArray($arr)) {
            foreach (['items', 'majors', 'list', '专业'] as $k) {
                if (isset($arr[$k]) && is_array($arr[$k]) && self::isListArray($arr[$k])) {
                    $arr = $arr[$k];
                    break;
                }
            }
            if (!self::isListArray($arr)) {
                return [];
            }
        }
        $out = [];
        foreach ($arr as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $out[] = ['majorName' => $t];
                }
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $name = $item['majorName'] ?? $item['name'] ?? $item['title'] ?? $item['major'] ?? $item['major_name']
                ?? $item['专业'] ?? $item['专业名称'] ?? $item['majorChinese'] ?? '';
            $name = is_string($name) ? trim($name) : (is_numeric($name) ? (string) $name : '');
            $row = $item;
            $row['majorName'] = $name;
            $out[] = $row;
        }

        return $out;
    }

    private static function normalizeReport(array $report, array $input): array
    {
        $report = self::mergeCanonicalReportKeys($report);
        $report['schoolRecommend'] = self::normalizeSchoolRecommendStructure($report['schoolRecommend'] ?? null);
        $report['majorRecommend'] = self::normalizeMajorRecommendStructure($report['majorRecommend'] ?? null);

        if (!isset($report['overview']) || !is_string($report['overview'])) {
            $report['overview'] = '基于你的测评与分数信息，建议先采用冲稳保梯度填报，并结合目标地区与专业方向进行二次筛选。';
        }
        if (!isset($report['personalityReason']) || !is_string($report['personalityReason'])) {
            $report['personalityReason'] = '建议结合 MBTI/PDP/DISC 综合判断，优先选择与你认知风格和执行偏好一致的专业。';
        }
        if (!isset($report['disclaimers']) || !is_string($report['disclaimers'])) {
            $report['disclaimers'] = '本报告为估测建议，非录取保证，最终请以各省考试院与院校官方信息为准。';
        }
        if (!isset($report['searchMeta']) || !is_array($report['searchMeta'])) {
            $report['searchMeta'] = [];
        }
        if (!isset($report['searchMeta']['queryCount'])) {
            $report['searchMeta']['queryCount'] = 0;
        }
        if (!isset($report['searchMeta']['coverage'])) {
            $report['searchMeta']['coverage'] = 'none';
        }
        if (!isset($report['inputEcho'])) {
            $report['inputEcho'] = $input;
        }
        return $report;
    }

    private static function fallbackReport(array $input): array
    {
        return [
            'overview' => '当前使用降级分析结果：建议先按冲稳保策略建立志愿梯度，再由老师结合最新政策进行人工复核。',
            'schoolRecommend' => ['chong' => [], 'wen' => [], 'bao' => []],
            'majorRecommend' => [],
            'personalityReason' => '你的测评结果显示具备稳定的性格偏好，可优先选择与优势能力匹配的学科方向。',
            'disclaimers' => '上下文不完整，仅供方向参考，非录取保证，请以官方数据为准。',
            'searchMeta' => ['queryCount' => 0, 'queries' => [], 'coverage' => 'none'],
            'inputEcho' => $input,
        ];
    }
}
