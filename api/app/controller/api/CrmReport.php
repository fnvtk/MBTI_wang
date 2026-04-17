<?php
namespace app\controller\api;

use app\BaseController;
use app\common\PdpDiscResultText;
use app\common\service\FeishuLeadWebhookService;
use app\common\service\JwtService;
use app\common\service\UserJourneyService;
use think\facade\Db;
use think\facade\Log;

/**
 * 存客宝获客线索上报
 * 将小程序用户行为（申请咨询/完成付款）上报给存客宝系统
 */
class CrmReport extends BaseController
{
    /**
     * POST api/crm/report
     * 接收前端上报请求，向存客宝发送线索数据
     *
     * @param string apiKey    类目配置中的存客宝KEY（consultWechat字段）
     * @param string source    线索来源描述，如"个人深度服务-1v1深度解读"
     * @param string remark    备注，如"申请咨询"/"完成付款"
     * @param string tags      可选，逗号分隔的微信标签
     * @param string siteTags  可选，逗号分隔的站内标签
     * @param bool   deepConsult 为 true 时：「了解自己」申请咨询；apiKey 可空，后端按用户归属企业从 cunkebao_keys 解析；并推送飞书获客（若已配置）
     */
    public function report()
    {
        // 获取当前用户（支持中间件注入和手动解析两种方式）
        $user = $this->request->user ?? null;
        if (!$user) {
            $token = JwtService::getTokenFromRequest($this->request);
            if ($token) {
                $payload = JwtService::verifyToken($token);
                if ($payload) {
                    $user = [
                        'source'  => $payload['source'] ?? '',
                        'user_id' => $payload['user_id'] ?? $payload['userId'] ?? null,
                    ];
                }
            }
        }

        $userId = (int) ($user['user_id'] ?? 0);

        // 接收参数
        $apiKey   = trim((string) ($this->request->param('apiKey', '') ?? ''));
        $source   = trim((string) ($this->request->param('source', '') ?? ''));
        $remark   = trim((string) ($this->request->param('remark', '') ?? ''));
        $tags     = trim((string) ($this->request->param('tags', '') ?? ''));
        $siteTags = trim((string) ($this->request->param('siteTags', '') ?? ''));

        $deepConsult = self::isTruthyParam($this->request->param('deepConsult', false));

        // 测评类付费（人脸/MBTI/PDP/DISC）：未传 apiKey 时从企业后台配置 cunkebao_keys 解析（与深度服务「完成付款」上报一致）
        $testType           = trim((string) ($this->request->param('testType', '') ?? ''));
        $testResultId       = (int) ($this->request->param('testResultId', 0) ?? 0);
        $contextEnterpriseId = (int) ($this->request->param('contextEnterpriseId', 0) ?? 0);

        $resolvedFromEnterpriseKeys = false;
        if ($apiKey === '' && $testType !== '' && $userId > 0) {
            $resolved = self::resolveTestPaymentApiKey($userId, $testType, $testResultId, $contextEnterpriseId);
            if ($resolved !== null && $resolved !== '') {
                $apiKey = $resolved;
                $resolvedFromEnterpriseKeys = true;
                if ($source === '') {
                    $source = self::buildTestPaymentSource($testType, $testResultId, $contextEnterpriseId, $userId);
                }
                // 测评类：不写「测试完成/完成付款」等流程备注，仅结果摘要+标签见下方 apply
                if ($siteTags === '') {
                    $siteTags = self::testTypeSiteTag($testType);
                }
            }
        }

        // 「了解自己」申请咨询：类目未配 consultWechat 时，按用户归属企业回落 cunkebao_keys
        if ($apiKey === '' && $deepConsult && $userId > 0) {
            $eidDeep = (int) (Db::name('wechat_users')->where('id', $userId)->value('enterpriseId') ?? 0);
            $fk      = self::readSingleCunkebaoApiKeyWithFallback($eidDeep);
            if ($fk !== '') {
                $apiKey = $fk;
            }
        }

        // 使用企业 cunkebao_keys 的「测评付费」上报：须校验已支付（防止未支付伪造「完成付款」）
        if ($resolvedFromEnterpriseKeys && !empty($apiKey)) {
            $deny = self::verifyTestPaidForCrmReport($userId, $testType, $testResultId);
            if ($deny !== null) {
                return success(['reported' => false, 'reason' => $deny]);
            }
        }

        if (empty($apiKey) && !$deepConsult) {
            return success(['reported' => false, 'reason' => 'no_api_key']);
        }

        // 测评结果：备注仅摘要；站内/微信标签含「测评名,结果」
        if ($testResultId > 0 && $userId > 0) {
            self::applyTestResultSummaryToReportPayload($userId, $testType, $testResultId, $remark, $tags, $siteTags);
            $eidForMgmt = $contextEnterpriseId;
            if ($eidForMgmt <= 0) {
                $eidForMgmt = (int) (Db::name('test_results')->where('id', $testResultId)->where('userId', $userId)->value('enterpriseId') ?? 0);
            }
            self::appendCrmRemarkUserJourney($userId, $eidForMgmt, $remark);
        }

        // 申请咨询：备注中附带用户管理摘要与旅程（与飞书卡片「最近行为」同源）
        if ($deepConsult && $userId > 0 && !empty($apiKey)) {
            $eidJ = (int) (Db::name('wechat_users')->where('id', $userId)->value('enterpriseId') ?? 0);
            self::appendCrmRemarkUserJourney($userId, $eidJ, $remark);
        }

        $ok = false;
        if (!empty($apiKey)) {
            $ok = self::doReport($userId, $apiKey, $source, $remark, $tags, $siteTags);
        }

        if ($deepConsult && $userId > 0) {
            $feishuSource = $source !== '' ? $source : '深度服务·申请咨询';
            FeishuLeadWebhookService::onDeepServiceConsultApply($userId, $feishuSource, $siteTags);
        }

        if ($ok) {
            return success(['reported' => true, 'reason' => '']);
        }

        return success([
            'reported' => false,
            'reason'   => empty($apiKey) ? 'no_api_key' : 'api_error',
        ]);
    }

    /** @param mixed $v */
    private static function isTruthyParam($v): bool
    {
        if ($v === true || $v === 1) {
            return true;
        }
        if ($v === false || $v === 0 || $v === null || $v === '') {
            return false;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 企业 KEY 解析的测评付费上报：必须带 testResultId，且若该记录需付费则 isPaid=1
     */
    private static function verifyTestPaidForCrmReport(int $userId, string $testType, int $testResultId): ?string
    {
        if ($testResultId <= 0) {
            return 'need_test_result_id';
        }

        $tr = Db::name('test_results')
            ->where('id', $testResultId)
            ->where('userId', $userId)
            ->field('testType,requiresPayment,isPaid')
            ->find();
        if (!$tr) {
            return 'test_result_not_found';
        }

        $rowType = (string) ($tr['testType'] ?? '');
        if ($rowType !== '' && $rowType !== $testType) {
            return 'test_type_mismatch';
        }

        if ((int) ($tr['requiresPayment'] ?? 0) === 1 && (int) ($tr['isPaid'] ?? 0) !== 1) {
            return 'not_paid';
        }

        return null;
    }

    /**
     * 测评付费场景：从 system_config.cunkebao_keys（企业后台配置）解析存客宝 KEY
     *
     * @return string|null 解析失败返回 null
     */
    private static function resolveTestPaymentApiKey(
        int $userId,
        string $testType,
        int $testResultId,
        int $contextEnterpriseId
    ): ?string {
        $allowed = ['face', 'mbti', 'sbti', 'pdp', 'disc'];
        if (!in_array($testType, $allowed, true)) {
            return null;
        }

        $trEnterpriseId = 0;

        if ($testResultId > 0) {
            $tr = Db::name('test_results')
                ->where('id', $testResultId)
                ->where('userId', $userId)
                ->field('enterpriseId')
                ->find();
            if ($tr) {
                $trEnterpriseId = (int) ($tr['enterpriseId'] ?? 0);
            }
        }

        $configEid = $trEnterpriseId > 0 ? $trEnterpriseId : $contextEnterpriseId;
        if ($configEid <= 0 && $userId > 0) {
            $configEid = (int) (Db::name('wechat_users')->where('id', $userId)->value('enterpriseId') ?? 0);
        }

        $key = self::readSingleCunkebaoApiKeyWithFallback($configEid);

        return $key !== '' ? $key : null;
    }

    /**
     * 读取企业 cunkebao_keys：单 apiKey；兼容旧版 enterprise/personal 及按题型分栏
     */
    private static function readSingleCunkebaoApiKey(int $enterpriseId): string
    {
        $row = Db::name('system_config')
            ->where('key', 'cunkebao_keys')
            ->where('enterprise_id', $enterpriseId)
            ->find();
        if (!$row || empty($row['value'])) {
            return '';
        }
        $decoded = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
        if (!is_array($decoded)) {
            return '';
        }
        if (array_key_exists('apiKey', $decoded)) {
            return trim((string) ($decoded['apiKey'] ?? ''));
        }
        if (array_key_exists('enterprise', $decoded) || array_key_exists('personal', $decoded)) {
            $e = trim((string) ($decoded['enterprise'] ?? ''));
            $p = trim((string) ($decoded['personal'] ?? ''));

            return $e !== '' ? $e : $p;
        }
        foreach (['face', 'mbti', 'sbti', 'pdp', 'disc'] as $t) {
            if (!isset($decoded[$t]) || !is_array($decoded[$t])) {
                continue;
            }
            $rowT = $decoded[$t];
            foreach (['enterprise', 'personal'] as $col) {
                $v = trim((string) ($rowT[$col] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return '';
    }

    private static function readSingleCunkebaoApiKeyWithFallback(int $enterpriseId): string
    {
        $v = self::readSingleCunkebaoApiKey($enterpriseId);
        if ($v !== '') {
            return $v;
        }
        if ($enterpriseId > 0) {
            $v = self::readSingleCunkebaoApiKey(0);
        }

        return $v;
    }

    private static function testTypeSiteTag(string $testType): string
    {
        $map = [
            'face' => 'AI人脸性格分析',
            'mbti' => 'MBTI性格测试',
            'sbti' => 'SBTI人格测试',
            'pdp'  => 'PDP动物性格测试',
            'disc' => 'DISC行为风格测试',
        ];

        return $map[$testType] ?? $testType;
    }

    /**
     * 与深度服务「个人/企业深度服务-类目名」风格对齐
     */
    private static function buildTestPaymentSource(
        string $testType,
        int $testResultId,
        int $contextEnterpriseId,
        int $userId
    ): string {
        $prefix = '个人测评';
        if ($testResultId > 0) {
            $scope = trim((string) (Db::name('test_results')->where('id', $testResultId)->where('userId', $userId)->value('testScope') ?? ''));
            if ($scope === 'enterprise') {
                $prefix = '企业测评';
            } elseif ($scope === '') {
                $prefix = $contextEnterpriseId > 0 ? '企业测评' : '个人测评';
            }
        } else {
            $prefix = $contextEnterpriseId > 0 ? '企业测评' : '个人测评';
        }

        $title = self::testTypeSiteTag($testType);

        return $prefix . '-' . $title;
    }

    /**
     * 从 test_results.resultData 解析一行简短结果（如 PDP「孔雀+老虎型」、DISC「D+I型」、MBTI 四字母）
     */
    private static function summarizeResultLine(string $testType, $resultDataRaw): string
    {
        if ($resultDataRaw === null || $resultDataRaw === '') {
            return '';
        }
        $data = is_string($resultDataRaw) ? json_decode($resultDataRaw, true) : $resultDataRaw;
        if (!is_array($data)) {
            return '';
        }
        switch ($testType) {
            case 'pdp':
                return PdpDiscResultText::pdpTopTwo($data);
            case 'disc':
                return PdpDiscResultText::discTopTwo($data);
            case 'mbti':
                $t = $data['mbtiType'] ?? $data['mbti'] ?? '';

                return is_string($t) ? trim($t) : '';
            case 'sbti':
                $code = (string) ($data['sbtiType'] ?? $data['finalType']['code'] ?? '');
                $cn = (string) ($data['sbtiCn'] ?? $data['finalType']['cn'] ?? '');
                if ($code === '') {
                    return '';
                }

                return $cn !== '' ? $code . '（' . $cn . '）' : $code;
            case 'face':
                $fa = $data['faceAnalysis'] ?? '';
                if (is_string($fa) && $fa !== '') {
                    $fa = preg_replace('/\s+/u', ' ', trim($fa));

                    return mb_strlen($fa) > 80 ? (mb_substr($fa, 0, 80) . '…') : $fa;
                }

                return '';
            default:
                return '';
        }
    }

    /**
     * 测评上报：备注不要流程类文案，仅填结果摘要；tags/siteTags 为「测评名,结果摘要」
     */
    private static function applyTestResultSummaryToReportPayload(
        int $userId,
        string $paramTestType,
        int $testResultId,
        string &$remark,
        string &$tags,
        string &$siteTags
    ): void {
        if ($testResultId <= 0 || $userId <= 0) {
            return;
        }
        $tr = Db::name('test_results')
            ->where('id', $testResultId)
            ->where('userId', $userId)
            ->field('resultData,testType')
            ->find();
        if (!$tr) {
            return;
        }
        $tt = (string) ($tr['testType'] ?? $paramTestType);
        if ($tt === '') {
            $tt = $paramTestType;
        }
        $summary = self::summarizeResultLine($tt, $tr['resultData'] ?? '');
        $typeLabel = self::testTypeSiteTag($tt);

        $remark = $summary;

        $parts = array_values(array_unique(array_filter([$typeLabel, $summary], static function ($s) {
            return $s !== null && $s !== '';
        })));
        $merged = implode(',', $parts);
        if ($merged !== '') {
            $siteTags = $merged;
            $tags = $merged;
        }
    }

    /**
     * 供 Test::submit / Analyze 等内部调用：测试结果保存成功后上报
     * - requiresPayment=0（免费）：有 Key 即上报
     * - requiresPayment=1 且 reportTiming=after_test：提交后上报
     * - requiresPayment=1 且 reportTiming=after_paid：此处不上报，由支付成功流程上报
     *
     * @param int    $userId          wechat_users.id
     * @param string $testType        face|mbti|pdp|disc
     * @param int    $testResultId    test_results.id
     * @param int    $enterpriseId    写入 test_results 时的 enterpriseId
     * @param string $testScope       enterprise|personal
     */
    public static function reportTestCompletion(
        int    $userId,
        string $testType,
        int    $testResultId,
        int    $enterpriseId,
        string $testScope = 'personal'
    ): void {
        try {
            $allowed = ['face', 'mbti', 'sbti', 'pdp', 'disc'];
            if (!in_array($testType, $allowed, true) || $userId <= 0 || $testResultId <= 0) {
                return;
            }

            $trRow = Db::name('test_results')
                ->where('id', $testResultId)
                ->where('userId', $userId)
                ->field('requiresPayment,resultData,testType')
                ->find();
            if (!$trRow) {
                return;
            }
            $requiresPayment = (int) ($trRow['requiresPayment'] ?? 0);

            // 未配置付费（免费）：测试完成即上报（与「没付款可直接调用」一致）
            // 若需付费：仅当后台为「测试完即上报」时在提交后上报；「付款后才上报」则等支付成功后再走前端/接口
            $timing = self::readReportTiming($enterpriseId);
            if ($requiresPayment === 1 && $timing !== 'after_test') {
                return;
            }

            $apiKey = self::readSingleCunkebaoApiKeyWithFallback($enterpriseId);
            if ($apiKey === '') {
                return;
            }

            $source  = self::buildTestPaymentSource($testType, $testResultId, $enterpriseId, $userId);
            $rowType = (string) ($trRow['testType'] ?? $testType);
            $summary = self::summarizeResultLine($rowType, $trRow['resultData'] ?? '');
            $typeLabel = self::testTypeSiteTag($testType);
            $parts = array_values(array_unique(array_filter([$typeLabel, $summary], static function ($s) {
                return $s !== null && $s !== '';
            })));
            $merged = implode(',', $parts);
            $remark = $summary;
            $tags = $merged;
            $siteTags = $merged;

            self::appendCrmRemarkUserJourney($userId, $enterpriseId, $remark);

            self::doReport($userId, $apiKey, $source, $remark, $tags, $siteTags);
        } catch (\Throwable $e) {
            Log::warning('[CrmReport] reportTestCompletion 异常 userId=' . $userId . ' err=' . $e->getMessage());
        }
    }

    /**
     * 读取企业 reportTiming（after_paid / after_test），测评类共用
     */
    private static function readReportTiming(int $enterpriseId): string
    {
        $val = self::readReportTimingFromRow($enterpriseId);
        if ($val !== '') {
            return $val;
        }
        if ($enterpriseId > 0) {
            $val = self::readReportTimingFromRow(0);
        }

        return $val !== '' ? $val : 'after_paid';
    }

    private static function readReportTimingFromRow(int $enterpriseId): string
    {
        $row = Db::name('system_config')
            ->where('key', 'cunkebao_keys')
            ->where('enterprise_id', $enterpriseId)
            ->find();
        if (!$row || empty($row['value'])) {
            return '';
        }
        $decoded = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
        if (!is_array($decoded)) {
            return '';
        }
        if (isset($decoded['reportTiming'])) {
            $t = (string) $decoded['reportTiming'];

            return in_array($t, ['after_paid', 'after_test'], true) ? $t : '';
        }
        foreach (['face', 'mbti', 'sbti', 'pdp', 'disc'] as $tKey) {
            if (!isset($decoded[$tKey]['reportTiming'])) {
                continue;
            }
            $t = (string) $decoded[$tKey]['reportTiming'];
            if ($t === 'after_test') {
                return 'after_test';
            }
        }
        foreach (['face', 'mbti', 'sbti', 'pdp', 'disc'] as $tKey) {
            if (!isset($decoded[$tKey]['reportTiming'])) {
                continue;
            }
            $t = (string) $decoded[$tKey]['reportTiming'];
            if (in_array($t, ['after_paid', 'after_test'], true)) {
                return $t;
            }
        }

        return '';
    }

    /**
     * 存客宝 remark 追加：用户管理一行 + analytics 用户旅程（与飞书/出站 Hook 字段同源）
     */
    private static function appendCrmRemarkUserJourney(int $userId, int $enterpriseIdForMgmt, string &$remark): void
    {
        if ($userId <= 0) {
            return;
        }
        $blocks = [];
        $m = UserJourneyService::managementSummaryLine($userId, $enterpriseIdForMgmt);
        if ($m !== '') {
            $blocks[] = '【用户管理】' . $m;
        }
        $jb = UserJourneyService::journeyLinesToRemarkBlock(UserJourneyService::recentBehaviorLines($userId, 10), 720);
        if ($jb !== '') {
            $blocks[] = $jb;
        }
        if (count($blocks) === 0) {
            return;
        }
        $append = implode("\n", $blocks);
        $remark = trim($remark === '' ? $append : ($remark . "\n" . $append));
        $cap = 900;
        if (function_exists('mb_strlen') && mb_strlen($remark) > $cap) {
            $remark = mb_substr($remark, 0, $cap) . '…';
        } elseif (!function_exists('mb_strlen') && strlen($remark) > $cap) {
            $remark = substr($remark, 0, $cap) . '…';
        }
    }

    /**
     * 内部通用上报（供 report() 接口和 reportTestCompletion 共用）
     */
    private static function doReport(
        int $userId,
        string $apiKey,
        string $source,
        string $remark,
        string $tags,
        string $siteTags
    ): bool {
        $phone = '';
        $openid = '';
        $nickname = '';
        if ($userId > 0) {
            $wu = Db::name('wechat_users')->where('id', $userId)->field('phone, openid, nickname')->find();
            if ($wu) {
                $phone    = (string) ($wu['phone']    ?? '');
                $openid   = (string) ($wu['openid']   ?? '');
                $nickname = (string) ($wu['nickname'] ?? '');
            }
        }
        if ($phone === '' && $openid === '') {
            return false;
        }

        $apiUrl    = env('API_URL', 'https://ckbapi.quwanzhi.com/v1/api/scenarios');
        $timestamp = time();
        $params    = ['apiKey' => $apiKey, 'timestamp' => $timestamp];
        if ($phone    !== '') $params['phone']    = $phone;
        if ($nickname !== '') $params['name']     = $nickname;
        if ($source   !== '') $params['source']   = $source;
        if ($remark   !== '') $params['remark']   = $remark;
        if ($tags     !== '') $params['tags']     = $tags;
        if ($siteTags !== '') $params['siteTags'] = $siteTags;

        $params['sign'] = self::generateSign($params, $apiKey);

        $portrait = self::buildPortrait($userId);
        if ($portrait !== null) {
            $params['portrait'] = $portrait;
        }

        $result = self::callApi($apiUrl, $params);
        if (!$result['success']) {
            Log::warning('[CrmReport] doReport 失败 userId=' . $userId . ' reason=' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        return $result['success'];
    }

    /**
     * 从数据库读取用户最近一次 MBTI / DISC / PDP 测试结果，构建 portrait 对象
     * portrait 整体不参与签名，直接附加到请求体中（见接口文档 §2.3）
     */
    private static function buildPortrait(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // 一次查出所有相关类型的最新记录（按时间倒序）
        $rows = Db::name('test_results')
            ->where('userId', $userId)
            ->whereIn('testType', ['mbti', 'sbti', 'disc', 'pdp'])
            ->field('testType, resultData, createdAt')
            ->order('createdAt', 'desc')
            ->select()
            ->toArray();

        $found = [];
        foreach ($rows as $row) {
            $type = $row['testType'];
            if (isset($found[$type])) continue; // 只取每种类型的最新一条

            $data = [];
            if (!empty($row['resultData'])) {
                $decoded = json_decode($row['resultData'], true);
                $data = is_array($decoded) ? $decoded : [];
            }

            switch ($type) {
                case 'mbti':
                    $val = $data['mbtiType'] ?? $data['mbti'] ?? '';
                    if ($val !== '') $found['mbti'] = (string) $val;
                    break;
                case 'disc':
                    $val = $data['dominantType'] ?? $data['disc'] ?? '';
                    if ($val !== '') $found['disc'] = $val . '型';
                    break;
                case 'pdp':
                    $val = $data['description']['type'] ?? $data['pdp'] ?? '';
                    if ($val !== '') $found['pdp'] = (string) $val;
                    break;
                case 'sbti':
                    $val = $data['sbtiType'] ?? $data['finalType']['code'] ?? '';
                    if ($val !== '') {
                        $found['sbti'] = (string) $val;
                    }
                    break;
            }
        }

        if (empty($found)) {
            return null;
        }

        return [
            'type'       => 4,                                              // 互动（咨询/购买行为）
            'source'     => 0,                                              // 本站
            'sourceData' => $found,
            'remark'     => '性格测试画像',
            'uniqueId'   => 'wxmp_' . $userId . '_' . date('YmdH'),        // 同一小时内去重
        ];
    }

    /**
     * 生成存客宝签名
     * 规则（来自接口文档 §2.3）：
     *   1. 移除 sign / apiKey / portrait
     *   2. 移除值为 null 或空字符串的字段
     *   3. 按参数名 ASCII 升序排序
     *   4. 只取"值"按顺序拼接
     *   5. 第一次 MD5
     *   6. 拼接 apiKey 后第二次 MD5，得到最终签名
     */
    private static function generateSign(array $params, string $apiKey): string
    {
        unset($params['sign'], $params['apiKey'], $params['portrait']);

        $params = array_filter($params, static function ($value) {
            return !is_null($value) && $value !== '';
        });

        ksort($params);

        $stringToSign = implode('', array_values($params));
        $firstMd5     = md5($stringToSign);

        return md5($firstMd5 . $apiKey);
    }

    /**
     * 通过 cURL 调用存客宝接口
     */
    private static function callApi(string $url, array $params): array
    {
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Content-Length: ' . strlen($payload),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'curl:' . $curlError];
        }

        $data = json_decode($response, true);
        if (is_array($data) && isset($data['code']) && (int) $data['code'] === 200) {
            return ['success' => true, 'data' => $data];
        }

        return [
            'success'  => false,
            'error'    => $data['message'] ?? 'unknown',
            'response' => $response,
        ];
    }
}
