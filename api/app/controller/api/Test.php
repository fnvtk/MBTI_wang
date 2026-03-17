<?php
namespace app\controller\api;

use app\BaseController;
use app\model\PricingConfig as PricingConfigModel;
use app\model\UserProfile as UserProfileModel;
use think\facade\Db;
use think\facade\Request;

/**
 * 前端测试记录相关 API
 */
class Test extends BaseController
{
    /**
     * 获取当前微信用户的测试历史记录（用于小程序「测试历史」页）
     * GET /api/test/history
     */
    public function history()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $type     = Request::param('type', 'all');   // all|mbti|disc|pdp|face
        $scope    = Request::param('scope', 'all');  // all|personal|enterprise
        $page     = max(1, (int) Request::param('page', 1));
        $pageSize = (int) Request::param('pageSize', 0);
        if ($pageSize <= 0) {
            $pageSize = 500;
        }
        $pageSize = min(500, max(1, $pageSize));

        $base = Db::name('test_results')
            ->alias('tr')
            ->leftJoin('wechat_users wu', 'tr.userId = wu.id')
            ->leftJoin('enterprises e_tr', 'tr.enterpriseId = e_tr.id')
            ->leftJoin('enterprises e_wu', 'wu.enterpriseId = e_wu.id')
            ->where('tr.userId', $userId)
            ->field('tr.*, e_tr.name as enterpriseName, wu.enterpriseId as bindEnterpriseId, e_wu.name as bindEnterpriseName')
            ->order('tr.createdAt', 'desc');

        if ($type !== 'all') {
            if (in_array($type, ['face', 'ai'], true)) {
                $base->whereIn('tr.testType', ['face', 'ai']);
            } else {
                $base->where('tr.testType', $type);
            }
        }

        if ($scope === 'personal') {
            $base->whereNull('tr.enterpriseId');
        } elseif ($scope === 'enterprise') {
            $base->whereNotNull('tr.enterpriseId');
        }

        $total = (clone $base)->count('tr.id');
        $rows  = (clone $base)->page($page, $pageSize)->select()->toArray();

        $list = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? 0;
            $testType = $row['testType'] ?? '';
            $createdAt = $row['createdAt'] ?? null;
            $timeLabel = $createdAt ? date('Y-m-d H:i', $createdAt) : '未知时间';
            // enterpriseId 语义：仅代表“该次测试是否属于企业测试/企业分享链接”
            // 个人测试时 enterpriseId 可能为空，但用户依然可能在 wechat_users.enterpriseId 有归属企业
            $enterpriseName = '';
            if (isset($row['enterpriseId']) && (int) $row['enterpriseId'] > 0) {
                $enterpriseName = trim((string) ($row['enterpriseName'] ?? ''));
            } elseif (isset($row['bindEnterpriseId']) && (int) $row['bindEnterpriseId'] > 0) {
                $enterpriseName = trim((string) ($row['bindEnterpriseName'] ?? ''));
            }
            $requiresPayment = (int) ($row['requiresPayment'] ?? 0);
            $isPaid = (int) ($row['isPaid'] ?? 0);
            $orderId = isset($row['orderId']) ? (int) $row['orderId'] : null;

            $raw = $row['resultData'] ?? ($row['result'] ?? null);
            $data = null;
            if ($raw !== null && $raw !== '') {
                $decoded = json_decode($raw, true);
                $data = is_array($decoded) ? $decoded : $raw;
            }
            if ($requiresPayment && !$isPaid && $data !== null) {
                $data = $this->filterResultToPartial($testType, $data);
            }

            $paymentFields = [
                'requiresPayment' => $requiresPayment,
                'isPaid'          => $isPaid,
                'orderId'         => $orderId,
                'enterpriseName'  => $enterpriseName,
            ];

            // 映射为小程序 history 页需要的结构
            switch ($testType) {
                case 'mbti':
                    $mbtiType = $data['mbtiType'] ?? $data['mbti'] ?? '未知';
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'mbti',
                        'key'       => 'mbti_' . $id,
                        'emoji'     => '🧠',
                        'typeName'  => 'MBTI性格测试',
                        'resultText'=> $mbtiType,
                        'testTime'  => $timeLabel,
                        'data'      => $data,
                    ], $paymentFields);
                    break;
                case 'disc':
                    $discType = $data['dominantType'] ?? $data['disc'] ?? '未知';
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'disc',
                        'key'       => 'disc_' . $id,
                        'emoji'     => '📊',
                        'typeName'  => 'DISC性格测试',
                        'resultText'=> $discType . '型',
                        'testTime'  => $timeLabel,
                        'data'      => $data,
                    ], $paymentFields);
                    break;
                case 'pdp':
                    $primary = $data['description']['type'] ?? $data['pdp'] ?? '未知';
                    $emoji = $data['description']['emoji'] ?? '🦁';
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'pdp',
                        'key'       => 'pdp_' . $id,
                        'emoji'     => $emoji,
                        'typeName'  => 'PDP行为偏好测试',
                        'resultText'=> $primary,
                        'testTime'  => $timeLabel,
                        'data'      => $data,
                    ], $paymentFields);
                    break;
                case 'face':
                case 'ai':
                    $mbtiShort = '';
                    if (is_array($data)) {
                        if (isset($data['mbti']['type'])) {
                            $mbtiShort = $data['mbti']['type'];
                        } elseif (isset($data['mbti'])) {
                            $mbtiShort = is_array($data['mbti']) ? ($data['mbti']['type'] ?? '') : $data['mbti'];
                        }
                    }
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'ai',
                        'key'       => 'ai_' . $id,
                        'emoji'     => '👁️',
                        'typeName'  => '面相分析',
                        'resultText'=> $mbtiShort ?: '未知',
                        'testTime'  => $timeLabel,
                        'data'      => $data,
                    ], $paymentFields);
                    break;
                case 'resume':
                    $summary = '';
                    if (is_array($data) && !empty($data['content'])) {
                        $summary = mb_substr(strip_tags((string) $data['content']), 0, 20, 'UTF-8');
                        if (mb_strlen((string) $data['content'], 'UTF-8') > 20) {
                            $summary .= '...';
                        }
                    }
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'resume',
                        'key'       => 'resume_' . $id,
                        'emoji'     => '📋',
                        'typeName'  => '简历综合分析',
                        'resultText'=> $summary ?: '简历综合分析',
                        'testTime'  => $timeLabel,
                        'data'      => $data,
                    ], $paymentFields);
                    break;
                default:
                    break;
            }
        }

        return success([
            'list'     => $list,
            'total'    => (int) $total,
            'page'     => $page,
            'pageSize' => $pageSize,
            'hasMore'  => ($page * $pageSize) < $total,
        ]);
    }

    /**
     * 获取每种测试类型最新一条记录（用于小程序「我的」页）
     * GET /api/test/recent
     * 返回：{ records: { mbti, disc, pdp, ai }, totalCount }
     */
    public function recent()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $scope = Request::param('scope', 'all'); // all|personal|enterprise

        $records = [];

        // 优化：一次性查询所有需要的最新记录，减少数据库连接和查询次数
        $query = Db::name('test_results')
            ->where('userId', $userId);
        
        if ($scope === 'personal') {
            $query->whereNull('enterpriseId');
        } elseif ($scope === 'enterprise') {
            $query->whereNotNull('enterpriseId');
        }

        // 使用子查询或 Union 可能更复杂，这里采用分组取最新的优化思路
        // 但 ThinkPHP 中最简单有效的优化是先查出所有类型，再处理
        $allRows = $query->order('createdAt', 'desc')->select()->toArray();
        
        $foundTypes = [];
        $totalCount = count($allRows);

        foreach ($allRows as $row) {
            $type = $row['testType'];
            // face 和 ai 视为同一种类型
            $effectiveType = in_array($type, ['face', 'ai']) ? 'ai' : $type;
            
            if (!isset($foundTypes[$effectiveType]) && in_array($effectiveType, ['mbti', 'disc', 'pdp', 'ai'])) {
                $records[$effectiveType] = $this->_formatRecentRow($row);
                $foundTypes[$effectiveType] = true;
            }
            
            // 如果四个类型都找到了，且不需要总数（或者已经有了），可以提前结束
            if (count($foundTypes) >= 4) {
                // 如果不需要精确的总数统计，这里可以 break
                // 但为了保持接口兼容性，我们继续循环或者已经拿到了 count
            }
        }

        return success([
            'records'    => $records,
            'totalCount' => (int) $totalCount,
        ]);
    }

    /**
     * 格式化单条记录为 recent 接口返回结构
     */
    protected function _formatRecentRow(array $row): array
    {
        $testType  = $row['testType'] ?? '';
        $createdAt = $row['createdAt'] ?? null;
        $raw = $row['resultData'] ?? ($row['result'] ?? null);
        $data = [];
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $resultText = '';
        $emoji      = '';
        $typeName   = '';

        switch ($testType) {
            case 'mbti':
                $resultText = $data['mbtiType'] ?? $data['mbti'] ?? '未知';
                $emoji      = '🧠';
                $typeName   = 'MBTI性格';
                break;
            case 'disc':
                $dominantType = $data['dominantType'] ?? $data['disc'] ?? '未知';
                $resultText = $dominantType . '型';
                $emoji      = '📊';
                $typeName   = 'DISC测评';
                break;
            case 'pdp':
                $resultText = $data['description']['type'] ?? $data['pdp'] ?? '未知';
                $emoji      = $data['description']['emoji'] ?? '🦁';
                $typeName   = 'PDP行为';
                break;
            case 'face':
            case 'ai':
                $mbtiShort = '';
                if (isset($data['mbti']['type'])) {
                    $mbtiShort = $data['mbti']['type'];
                } elseif (isset($data['mbti']) && !is_array($data['mbti'])) {
                    $mbtiShort = (string) $data['mbti'];
                }
                $resultText = $mbtiShort ?: '面相分析';
                $emoji      = '👁️';
                $typeName   = '面相分析';
                break;
        }

        return [
            'id'              => (int) $row['id'],
            'testType'        => ($testType === 'face') ? 'ai' : $testType,
            'emoji'           => $emoji,
            'typeName'        => $typeName,
            'resultText'      => $resultText,
            'testTime'        => $createdAt ? date('Y-m-d', (int) $createdAt) : '',
            'isPaid'          => (int) ($row['isPaid'] ?? 0),
            'requiresPayment' => (int) ($row['requiresPayment'] ?? 0),
        ];
    }

    /**
     * 单条测试结果详情（按ID读取数据库）
     * GET /api/test/detail?id=123
     */
    public function detail()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $id = (int) Request::param('id', 0);
        if ($id <= 0) {
            return error('缺少ID', 400);
        }

        $row = Db::name('test_results')
            ->where('id', $id)
            ->where('userId', $userId)
            ->find();

        if (!$row) {
            return error('记录不存在', 404);
        }

        $raw = $row['resultData'] ?? ($row['result'] ?? null);
        $data = null;
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            $data = is_array($decoded) ? $decoded : $raw;
        }
        $requiresPayment = (int) ($row['requiresPayment'] ?? 0);
        $isPaid = (int) ($row['isPaid'] ?? 0);
        $paidAmount = isset($row['paidAmount']) ? (int) $row['paidAmount'] : 0;
        $testType = $row['testType'] ?? '';
        // 仅当需要付款且未付款且金额>0 时才脱敏；系统设置需付款但金额为0 则直接可查看
        $needPaymentToUnlock = $requiresPayment && !$isPaid && $paidAmount > 0;
        if ($needPaymentToUnlock && $data !== null) {
            $data = $this->filterResultToPartial($testType, $data);
        }

        return success([
            'id'                  => $row['id'],
            'testType'            => $testType,
            'createdAt'           => $row['createdAt'],
            'data'                => $data,
            'requiresPayment'    => $requiresPayment,
            'isPaid'              => $isPaid,
            'paidAmount'          => $paidAmount,
            'amountYuan'          => $paidAmount > 0 ? round($paidAmount / 100, 2) : 0,
            'needPaymentToUnlock'=> $needPaymentToUnlock,
            'orderId'             => isset($row['orderId']) ? (int) $row['orderId'] : null,
            'paidAt'              => isset($row['paidAt']) ? (int) $row['paidAt'] : null,
        ]);
    }

    /**
     * 提交测试结果（MBTI/DISC/PDP 等问卷）
     * POST /api/test/submit
     * body: { testType, answers, result, testDuration, timestamp }
     * - userId 从 token 中解析，保证与当前登录微信用户一致
     * - 结果统一写入 test_results 表，前端历史/详情接口复用现有逻辑
     */
    public function submit()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $input = Request::post();
        $testType = $input['testType'] ?? '';
        $result   = $input['result'] ?? null;
        $answers  = $input['answers'] ?? [];
        $duration = isset($input['testDuration']) ? (int) $input['testDuration'] : 0;
        // 企业分享链接会传 enterpriseId；个人分享不传，稍后从 wechat_users 回落
        $enterpriseId = isset($input['enterpriseId']) ? (int) $input['enterpriseId'] : null;
        if ($enterpriseId !== null && $enterpriseId <= 0) {
            $enterpriseId = null;
        }
        // 标记来源：只有"请求体明确传入"时才更新 wechat_users.enterpriseId
        $enterpriseFromRequest = $enterpriseId !== null;

        if (!$testType || $result === null) {
            return error('缺少必要参数', 400);
        }

        // 仅允许已知类型，避免脏数据
        if (!in_array($testType, ['mbti', 'disc', 'pdp', 'face', 'ai'], true)) {
            return error('不支持的测试类型', 400);
        }

        // 结果结构中附带 answers / testDuration，方便后续分析，同时保持历史结构兼容
        if (is_array($result)) {
            if (!isset($result['answers']) && is_array($answers)) {
                $result['answers'] = $answers;
            }
            if (!isset($result['testDuration']) && $duration > 0) {
                $result['testDuration'] = $duration;
            }
        }

        try {
            $now = time();
            // 三个变量各司其职：
            // $enterpriseId        —— 仅企业测试（请求体传入）才非 null，决定走 admin_enterprise 定价
            // $pricingEnterpriseId —— 个人测试时从 wechat_users 取，走 admin_personal + eid 定价
            // $writeEnterpriseId   —— 写入 test_results.enterpriseId（企业测试 or 绑定企业都记录）
            $pricingEnterpriseId = $enterpriseId;
            $writeEnterpriseId   = $enterpriseId;
            if ($enterpriseId === null) {
                $boundEid = Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
                if (!empty($boundEid)) {
                    $pricingEnterpriseId = (int) $boundEid; // admin_personal + eid
                    $writeEnterpriseId   = (int) $boundEid; // 历史记录展示企业名
                }
            }
            $requiresPayment = $this->getRequiresPaymentByTestType($testType, $enterpriseId, $pricingEnterpriseId);
            $standardAmountFen = $requiresPayment ? $this->getStandardAmountFenByTestType($testType, $enterpriseId, $pricingEnterpriseId) : 0;
            $id = Db::name('test_results')->insertGetId([
                'userId'          => $userId,
                'enterpriseId'    => $writeEnterpriseId,
                'testScope'       => $enterpriseId !== null ? 'enterprise' : 'personal',
                'testType'        => $testType,
                'resultData'      => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE),
                'score'           => null,
                'orderId'         => null,
                'requiresPayment' => $requiresPayment,
                'isPaid'          => 0,
                'paidAmount'      => $standardAmountFen > 0 ? $standardAmountFen : null,
                'paidAt'          => null,
                'createdAt'       => $now,
                'updatedAt'       => $now,
            ]);

            if ($id > 0) {
                UserProfileModel::recordTest($userId, $testType, $id, $writeEnterpriseId, $now);
                // 仅当 enterpriseId 来自请求体（企业分享链接）时才更新绑定关系
                if ($enterpriseFromRequest && $enterpriseId !== null && $enterpriseId > 0) {
                    Db::name('wechat_users')->where('id', $userId)->update([
                        'enterpriseId' => $enterpriseId,
                        'updatedAt'    => $now,
                    ]);
                }
                // 测试完成佣金结算（无需付款，异步不影响主流程）
                try {
                    \app\controller\api\Distribution::settleTestCommission($id, $userId, $testType);
                } catch (\Throwable $e) {
                    // 佣金结算失败不阻断测试保存
                }
            }
        } catch (\Throwable $e) {
            return error('保存测试结果失败', 500);
        }

        return success(null, '提交成功');
    }

    /**
     * 根据定价配置返回该测试类型是否需要付费才显示完整报告
     *
     * @param string   $testType          face|mbti|disc|pdp
     * @param int|null $enterpriseId      本次测试的企业 ID（NULL=个人测试）
     * @param int|null $pricingEnterpriseId 定价用企业 ID（个人测试时也可能有归属企业）
     * @return int 0 或 1
     */
    protected function getRequiresPaymentByTestType(string $testType, ?int $enterpriseId = null, ?int $pricingEnterpriseId = null): int
    {
        $pricingConfig = $this->resolvePricingConfig($enterpriseId, $pricingEnterpriseId);
        if (!$pricingConfig || empty($pricingConfig->config)) {
            return 0;
        }
        $pricing = is_array($pricingConfig->config) ? $pricingConfig->config : (array) $pricingConfig->config;
        $key = $testType === 'team_analysis' ? 'teamAnalysis' : $testType;
        return isset($pricing[$key]) && (float) $pricing[$key] > 0 ? 1 : 0;
    }

    /**
     * 获取某测试类型当前定价金额（分），用于写入 test_results.paidAmount
     *
     * @param int|null $enterpriseId      本次测试企业 ID
     * @param int|null $pricingEnterpriseId 定价用企业 ID
     */
    protected function getStandardAmountFenByTestType(string $testType, ?int $enterpriseId = null, ?int $pricingEnterpriseId = null): int
    {
        $pricingConfig = $this->resolvePricingConfig($enterpriseId, $pricingEnterpriseId);
        if (!$pricingConfig || empty($pricingConfig->config)) {
            return 0;
        }
        $pricing = is_array($pricingConfig->config) ? $pricingConfig->config : (array) $pricingConfig->config;
        $key = $testType === 'team_analysis' ? 'teamAnalysis' : $testType;
        if (!isset($pricing[$key])) return 0;
        $yuan = (float) $pricing[$key];
        return $yuan > 0 ? (int) round($yuan * 100) : 0;
    }

    /**
     * 解析定价配置：
     * - 企业测试（enterpriseId 非空）→ 企业版定价（admin_enterprise 优先）
     * - 个人测试但有归属企业（pricingEnterpriseId 非空）→ 企业专属个人定价（admin_personal 优先）
     * - 纯个人测试 → 全局个人定价
     */
    private function resolvePricingConfig(?int $enterpriseId, ?int $pricingEnterpriseId): ?PricingConfigModel
    {
        if ($enterpriseId !== null && $enterpriseId > 0) {
            return PricingConfigModel::getByTypeAndEnterprise('enterprise', $enterpriseId);
        }
        if ($pricingEnterpriseId !== null && $pricingEnterpriseId > 0) {
            return PricingConfigModel::getByTypeAndEnterprise('personal', $pricingEnterpriseId);
        }
        return PricingConfigModel::getByTypeAndEnterprise('personal', null);
    }

    /**
     * 未付费时只返回部分数据（完整数据需付费解锁）
     * @param string $testType
     * @param array|null $data 原始 resultData
     * @return array|null 脱敏后的数据
     */
    protected function filterResultToPartial(string $testType, $data)
    {
        if (!is_array($data)) {
            return $data;
        }
        if ($testType === 'face' || $testType === 'ai') {
            $out = $data;
            $out['faceAnalysis'] = null;
            $out['boneAnalysis'] = null;
            return $out;
        }
        if ($testType === 'mbti') {
            return [
                'mbtiType' => $data['mbtiType'] ?? $data['mbti'] ?? '',
                'locked'   => true,
            ];
        }
        if ($testType === 'disc') {
            return [
                'dominantType' => $data['dominantType'] ?? $data['disc'] ?? '',
                'locked'       => true,
            ];
        }
        if ($testType === 'pdp') {
            return [
                'description' => isset($data['description']) ? ['type' => $data['description']['type'] ?? '', 'emoji' => $data['description']['emoji'] ?? ''] : [],
                'locked'       => true,
            ];
        }
        return $data;
    }

    /**
     * 获取当前用户最近的 MBTI / DISC / PDP 测试记录（暂不使用人脸/AI 结果），供简历综合分析使用
     * @param int $userId 微信用户 ID
     * @param int|null $enterpriseId 当前企业ID（仅返回该企业下的记录；为空则不按企业过滤）
     * @return array ['face' => row|null, 'mbti' => row|null, 'disc' => row|null, 'pdp' => row|null]，row 含 id, testType, resultData, createdAt
     */
    public static function getLatestResultsForResume(int $userId, ?int $enterpriseId = null): array
    {
        if ($userId <= 0) {
            return ['face' => null, 'mbti' => null, 'disc' => null, 'pdp' => null];
        }

        $out = ['face' => null, 'mbti' => null, 'disc' => null, 'pdp' => null];

        $base = Db::name('test_results')->where('userId', $userId);
        if ($enterpriseId !== null && $enterpriseId > 0) {
            $base = $base->where('enterpriseId', (int) $enterpriseId);
        }

        // face/ai 暂不参与简历分析，保持为 null，避免写入上下文

        // mbti
        $out['mbti'] = (clone $base)
            ->where('testType', 'mbti')
            ->field('id, testType, resultData, createdAt')
            ->order('createdAt', 'desc')
            ->find();

        // pdp
        $out['pdp'] = (clone $base)
            ->where('testType', 'pdp')
            ->field('id, testType, resultData, createdAt')
            ->order('createdAt', 'desc')
            ->find();

        // disc
        $out['disc'] = (clone $base)
            ->where('testType', 'disc')
            ->field('id, testType, resultData, createdAt')
            ->order('createdAt', 'desc')
            ->find();

        return $out;
    }
}

