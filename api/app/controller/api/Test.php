<?php
namespace app\controller\api;

use app\BaseController;
use app\common\SystemDefaultEnterprise;
use app\common\PdpDiscResultText;
use app\model\Enterprise as EnterpriseModel;
use app\model\PricingConfig as PricingConfigModel;
use app\model\Question as QuestionModel;
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

        $type     = Request::param('type', 'all');   // all|mbti|sbti|disc|pdp|face|ai|resume
        $scope    = Request::param('scope', 'all');  // all|personal|enterprise
        $page     = max(1, (int) Request::param('page', 1));
        $pageSize = (int) Request::param('pageSize', 0);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        $pageSize = min(100, max(1, $pageSize));

        $allowedTestTypes = $this->wechatAllowedTestTypes($userId);

        // 列表只展示摘要：禁止 tr.* 拉全表 resultData（单条可达数 MB×500=卡死）；仅截取 JSON 前若干字符用于解析标题
        $listJsonSliceLen = 32768;
        $base = Db::name('test_results')
            ->alias('tr')
            ->leftJoin('wechat_users wu', 'tr.userId = wu.id')
            ->leftJoin('enterprises e_tr', 'tr.enterpriseId = e_tr.id')
            ->leftJoin('enterprises e_wu', 'wu.enterpriseId = e_wu.id')
            ->where('tr.userId', $userId)
            ->field(
                'tr.id,tr.testType,tr.createdAt,tr.enterpriseId,tr.requiresPayment,tr.isPaid,tr.orderId,tr.paidAmount,' .
                'e_tr.name as enterpriseName,wu.enterpriseId as bindEnterpriseId,e_wu.name as bindEnterpriseName,' .
                'SUBSTRING(COALESCE(CAST(tr.resultData AS CHAR(65532)),\'\'),1,' . $listJsonSliceLen . ') as resultDataLite'
            )
            ->order('tr.createdAt', 'desc');

        $profileIncomplete = !self::isWechatProfileComplete($userId);

        if ($allowedTestTypes === []) {
            return success([
                'list'     => [],
                'total'    => 0,
                'page'     => $page,
                'pageSize' => $pageSize,
                'hasMore'  => false,
            ]);
        }
        $base->whereIn('tr.testType', $allowedTestTypes);

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
            $paidAmountRow = isset($row['paidAmount']) ? (int) $row['paidAmount'] : 0;
            $needPayUnlock = $requiresPayment && !$isPaid && $paidAmountRow > 0;

            $raw = $row['resultDataLite'] ?? ($row['resultData'] ?? null);
            $data = null;
            if ($raw !== null && $raw !== '') {
                $decoded = json_decode($raw, true);
                $data = is_array($decoded) ? $decoded : $raw;
            }
            if ($data !== null && is_array($data)) {
                if (in_array($testType, ['face', 'ai'], true)) {
                    if ($needPayUnlock || $profileIncomplete) {
                        $data = self::filterFaceResultToPreview($data);
                    }
                } elseif ($needPayUnlock || $profileIncomplete) {
                    $data = $this->filterResultToPartial($testType, $data);
                }
            }

            $paymentFields = [
                'requiresPayment' => $requiresPayment,
                'isPaid'          => $isPaid,
                'orderId'         => $orderId,
                'enterpriseName'  => $enterpriseName,
            ];

            // 映射为小程序 history 页列表结构；完整报告走 GET /api/test/detail?id= 减轻响应体积
            switch ($testType) {
                case 'mbti':
                    $mbtiType = is_array($data) ? ($data['mbtiType'] ?? $data['mbti'] ?? '未知') : '未知';
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'mbti',
                        'key'       => 'mbti_' . $id,
                        'emoji'     => '🧠',
                        'typeName'  => 'MBTI性格测试',
                        'resultText'=> is_string($mbtiType) || is_numeric($mbtiType) ? (string) $mbtiType : '未知',
                        'testTime'  => $timeLabel,
                        'data'      => null,
                    ], $paymentFields);
                    break;
                case 'disc':
                    $discTxt = '未知';
                    if (is_array($data)) {
                        $discTxt = PdpDiscResultText::discTopTwo($data);
                        if ($discTxt === '') {
                            $fallback = $data['dominantType'] ?? $data['disc'] ?? '未知';
                            $discTxt = (is_string($fallback) || is_numeric($fallback) ? (string) $fallback : '未知') . '型';
                        }
                    }
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'disc',
                        'key'       => 'disc_' . $id,
                        'emoji'     => '📊',
                        'typeName'  => 'DISC性格测试',
                        'resultText'=> $discTxt,
                        'testTime'  => $timeLabel,
                        'data'      => null,
                    ], $paymentFields);
                    break;
                case 'pdp':
                    $primary = '未知';
                    if (is_array($data)) {
                        $primary = PdpDiscResultText::pdpTopTwo($data);
                        if ($primary === '') {
                            $primary = (string) ($data['description']['type'] ?? $data['pdp'] ?? '未知');
                        }
                    }
                    $emoji = (is_array($data) && isset($data['description']['emoji'])) ? $data['description']['emoji'] : '🦁';
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'pdp',
                        'key'       => 'pdp_' . $id,
                        'emoji'     => $emoji,
                        'typeName'  => 'PDP行为偏好测试',
                        'resultText'=> $primary,
                        'testTime'  => $timeLabel,
                        'data'      => null,
                    ], $paymentFields);
                    break;
                case 'sbti':
                    $sbtiTxt = '未知';
                    if (is_array($data)) {
                        $sbtiTxt = (string) ($data['sbtiType'] ?? $data['finalType']['code'] ?? $data['code'] ?? '未知');
                        if (($data['sbtiCn'] ?? '') !== '') {
                            $sbtiTxt .= '（' . $data['sbtiCn'] . '）';
                        } elseif (isset($data['finalType']['cn']) && $data['finalType']['cn'] !== '') {
                            $sbtiTxt .= '（' . $data['finalType']['cn'] . '）';
                        }
                    }
                    $list[] = array_merge([
                        'id'        => $id,
                        'type'      => 'sbti',
                        'key'       => 'sbti_' . $id,
                        'emoji'     => '🎭',
                        'typeName'  => 'SBTI 人格测试',
                        'resultText'=> $sbtiTxt,
                        'testTime'  => $timeLabel,
                        'data'      => null,
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
                        'data'      => null,
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
                        'data'      => null,
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

        $allowedAll = $this->wechatAllowedTestTypes($userId);
        // 「我的」卡片不含简历，但 totalCount 与列表需与 history 权限一致
        $allowedForRecent = array_values(array_intersect($allowedAll, ['mbti', 'sbti', 'pdp', 'disc', 'face', 'ai']));
        if ($allowedForRecent === []) {
            return success([
                'records'    => new \stdClass(),
                'totalCount' => 0,
            ]);
        }

        $records = [];

        $query = Db::name('test_results')
            ->where('userId', $userId)
            ->whereIn('testType', $allowedForRecent);

        if ($scope === 'personal') {
            $query->whereNull('enterpriseId');
        } elseif ($scope === 'enterprise') {
            $query->whereNotNull('enterpriseId');
        }

        $allRows = $query->order('createdAt', 'desc')->select()->toArray();
        
        $foundTypes = [];
        $totalCount = count($allRows);

        foreach ($allRows as $row) {
            $type = $row['testType'];
            // face 和 ai 视为同一种类型
            $effectiveType = in_array($type, ['face', 'ai']) ? 'ai' : $type;
            
            if (!isset($foundTypes[$effectiveType]) && in_array($effectiveType, ['mbti', 'sbti', 'disc', 'pdp', 'ai'])) {
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
     * 微信用户当前允许的 test_results.testType（绑定企业 permissions + 审核模式；简历仅绑定企业可见）
     * @return string[]
     */
    protected function wechatAllowedTestTypes(int $userId): array
    {
        $bindRow           = Db::name('wechat_users')->where('id', $userId)->field('enterpriseId')->find();
        $boundEnterpriseId = (int) ($bindRow['enterpriseId'] ?? 0);
        $enterprisePerms   = EnterpriseModel::permissionDefaults();
        if ($boundEnterpriseId > 0) {
            $entPermRow = Db::name('enterprises')->where('id', $boundEnterpriseId)->field('permissions')->find();
            if (is_array($entPermRow)) {
                $enterprisePerms = EnterpriseModel::normalizePermissionsValue($entPermRow['permissions'] ?? null);
            }
        }
        $reviewMode = false;
        $systemRow  = Db::name('system_config')->where('key', 'system')->find();
        if ($systemRow && !empty($systemRow['value'])) {
            $sysVal = is_string($systemRow['value']) ? json_decode($systemRow['value'], true) : $systemRow['value'];
            if (is_array($sysVal) && !empty($sysVal['maintenanceMode'])) {
                $reviewMode = true;
            }
        }
        $allowed = [];
        if (!empty($enterprisePerms['mbti'])) {
            $allowed[] = 'mbti';
        }
        if (!empty($enterprisePerms['pdp'])) {
            $allowed[] = 'pdp';
        }
        if (!empty($enterprisePerms['disc'])) {
            $allowed[] = 'disc';
        }
        if (!empty($enterprisePerms['sbti'])) {
            $allowed[] = 'sbti';
        }
        if (!empty($enterprisePerms['face']) && !$reviewMode) {
            $allowed[] = 'face';
            $allowed[] = 'ai';
        }
        if ($boundEnterpriseId > 0) {
            $allowed[] = 'resume';
        }

        return array_values(array_unique($allowed));
    }

    /**
     * 解析 test_results.resultData：支持 JSON 字符串、已解码的 array、包在 result 键里的结构
     *
     * @param mixed $raw
     * @return array<string,mixed>
     */
    protected function decodeResultDataPayload($raw): array
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

    /**
     * 格式化单条记录为 recent 接口返回结构
     */
    protected function _formatRecentRow(array $row): array
    {
        $testType  = $row['testType'] ?? '';
        $createdAt = $row['createdAt'] ?? null;
        $raw = $row['resultData'] ?? ($row['result'] ?? null);
        $data = $this->decodeResultDataPayload($raw);

        $userIdRow = (int) ($row['userId'] ?? 0);
        $profileIncomplete = $userIdRow > 0 && !self::isWechatProfileComplete($userIdRow);
        $requiresPayment = (int) ($row['requiresPayment'] ?? 0);
        $isPaid = (int) ($row['isPaid'] ?? 0);
        $paidAmountRow = isset($row['paidAmount']) ? (int) $row['paidAmount'] : 0;
        $needPayUnlock = $requiresPayment && !$isPaid && $paidAmountRow > 0;
        if ($data !== []) {
            if (in_array($testType, ['face', 'ai'], true)) {
                if ($needPayUnlock || $profileIncomplete) {
                    $data = self::filterFaceResultToPreview($data);
                }
            } elseif ($needPayUnlock || $profileIncomplete) {
                $data = $this->filterResultToPartial($testType, $data);
            }
        }

        $resultText = '';
        $emoji      = '';
        $typeName   = '';
        $gallupPreview = '';

        switch ($testType) {
            case 'mbti':
                $resultText = $data['mbtiType'] ?? $data['mbti'] ?? '未知';
                $emoji      = '🧠';
                $typeName   = 'MBTI性格';
                break;
            case 'disc':
                $resultText = PdpDiscResultText::discTopTwo($data);
                if ($resultText === '') {
                    $dominantType = $data['dominantType'] ?? $data['disc'] ?? '未知';
                    $resultText = (is_string($dominantType) || is_numeric($dominantType) ? (string) $dominantType : '未知') . '型';
                }
                $emoji    = '📊';
                $typeName = 'DISC测评';
                break;
            case 'pdp':
                $resultText = PdpDiscResultText::pdpTopTwo($data);
                if ($resultText === '') {
                    $resultText = $data['description']['type'] ?? $data['pdp'] ?? '未知';
                }
                $emoji      = $data['description']['emoji'] ?? '🦁';
                $typeName   = 'PDP行为';
                break;
            case 'sbti':
                $resultText = (string) ($data['sbtiType'] ?? $data['finalType']['code'] ?? '未知');
                if (!empty($data['sbtiCn'])) {
                    $resultText .= '（' . $data['sbtiCn'] . '）';
                } elseif (!empty($data['finalType']['cn'])) {
                    $resultText .= '（' . $data['finalType']['cn'] . '）';
                }
                $emoji    = '🎭';
                $typeName = 'SBTI';
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

        if (in_array($testType, ['face', 'ai'], true)) {
            $g = $data['gallupTop3'] ?? null;
            if (is_array($g) && $g !== []) {
                $slice = array_slice($g, 0, 3);
                $gallupPreview = implode('、', array_map(static function ($x) {
                    return (string) $x;
                }, $slice));
            }
        }

        // 小程序「最新测试」：始终附带 resultMeta（便于前端 getTypeOnly；避免仅 resultText 旧格式）
        $resultMeta = null;
        if ($testType === 'disc') {
            $resultMeta = [
                'scores'        => $data['scores'] ?? null,
                'percentages'   => $data['percentages'] ?? null,
                'dominantType'  => $data['dominantType'] ?? null,
                'secondaryType' => $data['secondaryType'] ?? null,
                'description'   => $data['description'] ?? null,
                'disc'          => $data['disc'] ?? null,
            ];
        } elseif ($testType === 'pdp') {
            $resultMeta = [
                'scores'        => $data['scores'] ?? null,
                'percentages'   => $data['percentages'] ?? null,
                'dominantType'  => $data['dominantType'] ?? null,
                'secondaryType' => $data['secondaryType'] ?? null,
                'description'   => $data['description'] ?? null,
                'pdp'           => $data['pdp'] ?? null,
            ];
        } elseif ($testType === 'sbti') {
            $resultMeta = [
                'sbtiType' => $data['sbtiType'] ?? null,
                'sbtiCn'   => $data['sbtiCn'] ?? null,
                'levels'   => $data['levels'] ?? null,
            ];
        }

        $out = [
            'id'              => (int) $row['id'],
            'testType'        => ($testType === 'face') ? 'ai' : $testType,
            'emoji'           => $emoji,
            'typeName'        => $typeName,
            'resultText'      => $resultText,
            'testTime'        => $createdAt ? date('Y-m-d', (int) $createdAt) : '',
            'isPaid'          => (int) ($row['isPaid'] ?? 0),
            'requiresPayment' => (int) ($row['requiresPayment'] ?? 0),
        ];
        if ($gallupPreview !== '') {
            $out['gallupPreview'] = $gallupPreview;
        }
        if ($resultMeta !== null) {
            $out['resultMeta'] = $resultMeta;
        }

        return $out;
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
        $profileIncomplete = !self::isWechatProfileComplete($userId);
        if ($data !== null && is_array($data)) {
            if (in_array($testType, ['face', 'ai'], true)) {
                if ($needPaymentToUnlock || $profileIncomplete) {
                    $data = self::filterFaceResultToPreview($data);
                }
            } elseif ($needPaymentToUnlock || $profileIncomplete) {
                $data = $this->filterResultToPartial($testType, $data);
            }
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
            'profileIncomplete'   => $profileIncomplete,
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
        if (!in_array($testType, ['mbti', 'sbti', 'disc', 'pdp', 'face', 'ai'], true)) {
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
                } else {
                    // 主入口无参数且未绑定企业：回落超管配置的默认企业（与个人版 getEnterpriseIdForApiPayload 不传参一致，由服务端统一落库）
                    $defEid = SystemDefaultEnterprise::getId();
                    if ($defEid !== null) {
                        $pricingEnterpriseId = $defEid;
                        $writeEnterpriseId   = $defEid;
                    }
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
                // 用户尚无企业归属时，从本次测试上下文补写（请求体带 eid 或已绑定企业回落值）
                if ($writeEnterpriseId !== null && (int) $writeEnterpriseId > 0) {
                    $curEid = Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
                    if ($curEid === null || $curEid === '' || (int) $curEid === 0) {
                        Db::name('wechat_users')->where('id', $userId)->update([
                            'enterpriseId' => (int) $writeEnterpriseId,
                            'updatedAt'    => $now,
                        ]);
                    }
                }
                // 测试完成佣金结算（无需付款，异步不影响主流程）
                try {
                    \app\controller\api\Distribution::settleTestCommission($id, $userId, $testType);
                } catch (\Throwable $e) {
                    // 佣金结算失败不阻断测试保存
                }

                // 存客宝线索：MBTI/DISC/PDP 等问卷与人脸一致——免费(requiresPayment=0)测完即报；需付费则按企业后台「上报时机」
                try {
                    \app\controller\api\CrmReport::reportTestCompletion(
                        $userId,
                        $testType,
                        (int) $id,
                        (int) ($writeEnterpriseId ?? 0),
                        $enterpriseId !== null ? 'enterprise' : 'personal'
                    );
                } catch (\Throwable $e) {
                    // 上报失败不阻断
                }

                // 第三方开放平台：ext_uid 或 third_party_phone 有值且配置 URL/Key 时推送（含人脸走 mbti 字段）
                try {
                    if (is_array($result)) {
                        \app\common\service\OpenPlatformService::notifyQuestionnaireIfNeeded($userId, $testType, $result);
                    }
                } catch (\Throwable $e) {
                    // 对接失败不阻断
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
     * 问卷/简历类：未付费或资料未完善时只返回部分数据（与实例方法 filterResultToPartial 一致）
     *
     * @param string $testType
     * @param array|null $data
     * @return array|null
     */
    public static function filterResultToPartialStatic(string $testType, $data)
    {
        if (!is_array($data)) {
            return $data;
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
        if ($testType === 'sbti') {
            return [
                'sbtiType' => $data['sbtiType'] ?? $data['finalType']['code'] ?? '',
                'sbtiCn'   => $data['sbtiCn'] ?? $data['finalType']['cn'] ?? '',
                'locked'   => true,
            ];
        }
        if ($testType === 'resume') {
            $preview = '';
            if (!empty($data['content']) && is_string($data['content'])) {
                $preview = self::truncatePreviewText(strip_tags($data['content']), 72);
            } elseif (!empty($data['overview']) && is_string($data['overview'])) {
                $preview = self::truncatePreviewText(strip_tags($data['overview']), 72);
            }

            return [
                'locked'      => true,
                'content'     => $preview,
                '_structured' => false,
            ];
        }

        return $data;
    }

    /**
     * 未付费时只返回部分数据（完整数据需付费解锁）
     */
    protected function filterResultToPartial(string $testType, $data)
    {
        return self::filterResultToPartialStatic($testType, $data);
    }

    /**
     * 微信用户资料是否与小程序 isProfileComplete 一致：头像、昵称、手机号必填
     */
    public static function isWechatProfileComplete(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $row = Db::name('wechat_users')->where('id', $userId)->field('nickname,avatar,phone')->find();
        if (!$row) {
            return false;
        }
        $nick = trim((string) ($row['nickname'] ?? ''));
        $avatar = trim((string) ($row['avatar'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));

        return $nick !== '' && $avatar !== '' && $phone !== '';
    }

    /**
     * 人脸/AI 分析报告：未付费或资料未完善时仅返回预览级字段（防止抓包看全文）
     */
    public static function filterFaceResultToPreview(array $data): array
    {
        $out = $data;
        $out['faceAnalysis'] = null;
        $out['boneAnalysis'] = null;
        unset($out['faceAnalysisText'], $out['boneAnalysisText']);
        $out['relationship'] = '';
        $out['portrait'] = null;
        $out['hrView'] = null;
        $out['bossView'] = null;
        $out['resumeHighlights'] = '';
        $out['careerDevelopment'] = '';
        $out['familyParenting'] = '';
        $out['partnerCofounder'] = '';
        if (isset($out['careers'])) {
            $out['careers'] = [];
        }

        $sum = (string) ($out['personalitySummary'] ?? '');
        $ov = (string) ($out['overview'] ?? '');
        $out['personalitySummary'] = self::truncatePreviewText($sum, 72);
        $out['overview'] = self::truncatePreviewText($ov, 72);
        $out['gallupTop3'] = [];

        $adv = $out['advantages'] ?? [];
        if (is_array($adv) && $adv !== []) {
            $slice = array_slice($adv, 0, 2);
            $out['advantages'] = array_map(function ($x) {
                return self::truncatePreviewText((string) $x, 28);
            }, $slice);
        } else {
            $out['advantages'] = [];
        }

        return $out;
    }

    private static function truncatePreviewText(string $s, int $maxChars): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($s, 'UTF-8') > $maxChars) {
                return mb_substr($s, 0, $maxChars, 'UTF-8') . '…';
            }

            return $s;
        }
        if (strlen($s) > $maxChars) {
            return substr($s, 0, $maxChars) . '…';
        }

        return $s;
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

    /**
     * 小程序拉取做题题库（仅启用题）：企业本题库有题则用企业，否则用超管 enterpriseId 为空
     * GET /api/test/questions?type=mbti|sbti|disc|pdp&enterpriseId=可选
     */
    public function questions()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $type = (string) Request::param('type', '');
        if (!in_array($type, ['mbti', 'sbti', 'disc', 'pdp'], true)) {
            return error('type 须为 mbti、sbti、disc 或 pdp', 400);
        }

        $rawEid = Request::param('enterpriseId', null);
        $enterpriseId = null;
        if ($rawEid !== null && $rawEid !== '') {
            $enterpriseId = (int) $rawEid;
            if ($enterpriseId <= 0) {
                $enterpriseId = null;
            }
        }

        $resolvedEnterpriseId = null;
        if ($enterpriseId !== null) {
            $enterpriseQuestionCount       = QuestionModel::where('enterpriseId', $enterpriseId)
                ->where('type', $type)
                ->where('status', 1)
                ->count();
            if ($enterpriseQuestionCount > 0) {
                $resolvedEnterpriseId = $enterpriseId;
            }
        }

        $query = QuestionModel::where('type', $type)->where('status', 1);
        if ($resolvedEnterpriseId !== null) {
            $query->where('enterpriseId', $resolvedEnterpriseId);
        } else {
            $query->whereNull('enterpriseId');
        }

        $list = $query->order('sort', 'asc')
            ->order('id', 'asc')
            ->field('id,question,options,dimension')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            if (isset($item['options']) && is_object($item['options'])) {
                $item['options'] = json_decode(json_encode($item['options']), true);
            }
            if (isset($item['options']) && is_array($item['options']) && !empty($item['options']) && !isset($item['options'][0])) {
                $item['options'] = array_values($item['options']);
            }
            if (!isset($item['options']) || !is_array($item['options'])) {
                $item['options'] = [];
            }
            $item['id'] = (int) ($item['id'] ?? 0);
            foreach ($item['options'] as &$opt) {
                if (!is_array($opt)) {
                    continue;
                }
                if (!isset($opt['value']) && isset($opt['label'])) {
                    $opt['value'] = $opt['label'];
                }
                if (!isset($opt['text']) && isset($opt['label'])) {
                    $opt['text'] = $opt['label'];
                }
            }
            unset($opt);
            // MBTI / SBTI 前端组卷与计分依赖 dimension，其余题型不对外返回该字段
            if (!in_array($type, ['mbti', 'sbti'], true)) {
                unset($item['dimension']);
            }
        }
        unset($item);

        return success([
            'list'                  => $list,
            'resolvedEnterpriseId'  => $resolvedEnterpriseId,
            'usingSuperAdminBank'   => $resolvedEnterpriseId === null,
        ]);
    }

}

