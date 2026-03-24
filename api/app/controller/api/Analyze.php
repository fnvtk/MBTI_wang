<?php
namespace app\controller\api;

use app\BaseController;
use app\controller\api\Test as TestController;
use app\model\AiProvider as AiProviderModel;
use app\model\SystemConfig as SystemConfigModel;
use app\model\PricingConfig as PricingConfigModel;
use app\model\UserProfile as UserProfileModel;
use app\common\service\JwtService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

/**
 * 面相分析 API：使用超管配置的第一个启用的 AI 服务商进行分析
 * POST api/analyze  body: { "photoUrls": ["url1","url2","url3"] }
 * 若请求带 token，分析成功后顺带写入 test_results，无需前端再调 /api/test/submit
 */
class Analyze extends BaseController
{
    /**
     * 面相分析：取超管第一个启用的 AI 服务商，传入照片 URL 进行分析
     */
    public function index()
    {
        $input = Request::post();
        $photoUrls = $input['photoUrls'] ?? [];
        if (!is_array($photoUrls)) {
            $photoUrls = [];
        }
        $photoUrls = array_slice(array_filter($photoUrls), 0, 3);
        // 企业分享链接会传 enterpriseId；个人分享不传，稍后从 wechat_users 回落
        $enterpriseId = isset($input['enterpriseId']) ? (int) $input['enterpriseId'] : null;
        if ($enterpriseId !== null && $enterpriseId <= 0) {
            $enterpriseId = null;
        }
        // 标记来源：后续只有"请求体明确传入"时才更新 wechat_users.enterpriseId
        $enterpriseFromRequest = $enterpriseId !== null;

        // 提前解析 token，以便用 wechat_users.enterpriseId 回落（face 由本接口直接写入 test_results）
        $earlyUserId = 0;
        $earlyToken  = JwtService::getTokenFromRequest($this->request);
        if ($earlyToken) {
            $earlyPayload = JwtService::verifyToken($earlyToken);
            if ($earlyPayload && ($earlyPayload['source'] ?? '') === 'wechat') {
                $earlyUserId = (int) ($earlyPayload['user_id'] ?? $earlyPayload['userId'] ?? 0);
            }
        }
        // 三个变量各司其职（同 Test::submit 逻辑）：
        // $enterpriseId        —— 仅企业测试（请求体传入）才非 null，决定走 admin_enterprise 定价
        // $pricingEnterpriseId —— 个人测试时从 wechat_users 取，走 admin_personal + eid 定价
        // $writeEnterpriseId   —— 写入 test_results.enterpriseId（企业测试 or 绑定企业都记录）
        $pricingEnterpriseId = $enterpriseId;
        $writeEnterpriseId   = $enterpriseId;
        if ($enterpriseId === null && $earlyUserId > 0) {
            $boundEid = Db::name('wechat_users')->where('id', $earlyUserId)->value('enterpriseId');
            if (!empty($boundEid)) {
                $pricingEnterpriseId = (int) $boundEid; // admin_personal + eid
                $writeEnterpriseId   = (int) $boundEid; // 历史记录展示企业名
            }
        }

        if (empty($photoUrls)) {
            return error('请上传至少一张人脸照片', 400);
        }

        $provider = AiProviderModel::where('enabled', 1)
            ->whereRaw('(visible IS NULL OR visible = 1)')
            ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
            ->order('id', 'asc')
            ->find();

        if (!$provider) {
            return error('暂无可用的 AI 服务，请联系管理员配置', 503);
        }

        $apiKey = $provider->getRawApiKey();

        if (empty($apiKey)) {
            return error('AI 服务未配置有效密钥', 503);
        }

        // 前置人脸检测：将全部上传图片一次性发给 AI 判断，不通过则立即返回，不进行完整分析
        if (!$this->quickFaceCheck($photoUrls, $provider, $apiKey)) {
            return error('图片中未检测到人脸，请重新拍摄清晰的正面照片', 422);
        }

        // 企业版：拉取用户已完成的 MBTI/PDP/DISC 测试数据 + 简历（默认或最新），拼入 prompt 供 AI 交叉验证
        $existingTestContext = '';
        $resumeImageUrl = '';
        if ($enterpriseId !== null && $earlyUserId > 0) {
            try {
                $latestTests = TestController::getLatestResultsForResume($earlyUserId, $enterpriseId);
                $resumeText = '';
                $resumeFileUrl = '';
                // 从 enterprise_resume_uploads 读取简历：优先默认，无则最新
                $resumeQuery = Db::name('enterprise_resume_uploads')->where('userId', $earlyUserId)->where('enterpriseId', $enterpriseId);
                $defaultUpload = (clone $resumeQuery)->where('is_default', 1)->order('createdAt', 'desc')->find();
                $resumeUpload  = $defaultUpload ?: $resumeQuery->order('createdAt', 'desc')->find();
                if ($resumeUpload && !empty($resumeUpload['fileUrl'])) {
                    $resumeFileUrl = $resumeUpload['fileUrl'];
                    // PDF/docx 提取文本入 prompt；图片需作为 image_url 传入才能被 AI 读取；doc 仅提供文件名
                    $resumeText = $this->extractResumeFileText($resumeFileUrl);
                    $ext = strtolower(pathinfo(parse_url($resumeFileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                        $resumeImageUrl = $resumeFileUrl;
                    }
                }
                $ctx = $this->buildResumeContext($latestTests, $resumeText, $resumeFileUrl);
                if ($ctx !== '') {
                    $existingTestContext = $ctx;
                }
            } catch (\Throwable $e) {
                // 拉取失败不阻塞分析
            }
        }

        try {
            $result = $this->callAiAnalyze($provider, $apiKey, $photoUrls, $enterpriseId, $existingTestContext, $resumeImageUrl);
        } catch (\Throwable $e) {
            Log::warning('faceAnalyze: AI 调用失败 ' . $e->getMessage());
            return error('AI 分析失败：' . $e->getMessage(), 500);
        }

        // 完整分析中 AI 再次确认无人脸（双重保障）
        if (!empty($result['_noFace'])) {
            return error('图片中未检测到人脸，请重新拍摄清晰的正面照片', 422);
        }

        // 将用户上传的图片 URL 一并写入结果，方便 mbti_test_results 保留原始图片记录
        $storePayload = $result;
        if (is_array($storePayload)) {
            // 若模型结果中尚未包含 photoUrls，则追加一份
            if (!isset($storePayload['photoUrls'])) {
                $storePayload['photoUrls'] = $photoUrls;
            }
        }

        // 当前标准定价（分）与是否需要付费
        // $enterpriseId=非null 时走企业版定价；=null 时走个人版定价（含绑定企业的 admin_personal）
        $standardAmountFen = $this->getStandardAmountByTestType('face', $enterpriseId, $pricingEnterpriseId);
        $requiresPayment = $standardAmountFen > 0 ? 1 : 0;

        // 有 token 时顺带写入测试记录，避免小程序多请求一次 /api/test/submit
        $testResultId = null;
        $token = JwtService::getTokenFromRequest($this->request);
        if ($token) {
            $payload = JwtService::verifyToken($token);
            if ($payload && ($payload['source'] ?? '') === 'wechat') {
                $userId = (int) ($payload['user_id'] ?? $payload['userId'] ?? 0);
                if ($userId > 0) {
                    try {
                        $now = time();

                        $testResultId = Db::name('test_results')->insertGetId([
                            'userId'          => $userId,
                            'enterpriseId'    => $writeEnterpriseId,
                            'testScope'       => $enterpriseFromRequest ? 'enterprise' : 'personal',
                            'testType'        => 'face',
                            'resultData'      => is_string($storePayload) ? $storePayload : json_encode($storePayload, JSON_UNESCAPED_UNICODE),
                            'score'           => null,
                            'orderId'         => null,
                            'requiresPayment' => $requiresPayment,
                            'isPaid'          => 0,
                            'paidAmount'      => $standardAmountFen > 0 ? $standardAmountFen : null,
                            'paidAt'          => null,
                            'createdAt'       => $now,
                            'updatedAt'       => $now,
                        ]);

                        if ($testResultId) {
                            UserProfileModel::recordTest($userId, 'face', $testResultId, $writeEnterpriseId, $now);
                            // 仅当 enterpriseId 来自请求体（企业分享链接）时才更新绑定关系
                            if ($enterpriseFromRequest && $enterpriseId !== null && $enterpriseId > 0) {
                                Db::name('wechat_users')->where('id', $userId)->update([
                                    'enterpriseId' => $enterpriseId,
                                    'updatedAt'    => $now,
                                ]);
                            }
                            // 面相分析由本接口直接写入 test_results，也要补触发测试完成佣金
                            try {
                                \app\controller\api\Distribution::settleTestCommission($testResultId, $userId, 'face');
                            } catch (\Throwable $e) {
                                // 分销失败不影响主流程
                            }
                        }
                    } catch (\Throwable $e) {
                        // 写入失败不影响返回分析结果
                    }
                }
            }
        }

        // 构造返回给前端的 payload（不影响入库的原始结果）
        $responsePayload = is_array($storePayload) ? $storePayload : [];

        // 前端可直接使用的付费信息（避免二次请求 runtime）
        $responsePayload['_payment'] = [
            'requiresPayment' => $requiresPayment,
            'amountFen'       => $standardAmountFen,
            'amountYuan'      => $standardAmountFen > 0 ? round($standardAmountFen / 100, 2) : 0,
        ];

        // 未付费 / 资料未完善：与 /api/test/detail 一致做预览脱敏，防止直接抓包拿到完整报告
        $gateResponse = false;
        if ($requiresPayment > 0) {
            $gateResponse = true;
        } elseif ($earlyUserId > 0 && !TestController::isWechatProfileComplete($earlyUserId)) {
            $gateResponse = true;
        }
        if (is_array($responsePayload) && $gateResponse) {
            $responsePayload = TestController::filterFaceResultToPreview($responsePayload);
        }

        // 把本次测试记录ID一起返回，便于解锁后通过 /api/test/detail 拉取完整数据
        if ($testResultId) {
            $responsePayload['_testResultId'] = $testResultId;
        }

        return success($responsePayload);
    }

    /**
     * 简历综合分析：基于当前用户人脸、MBTI、PDP、DISC 四种测试的最近一次结果，拼接后调用 AI 生成综合分析（纯文本）
     * POST api/analyze/resume  需登录（wechat）
     */
    public function resumeAnalysis()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('请先登录', 401);
        }
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('请先登录', 401);
        }

        $input = Request::post();
        $resumeText = trim((string) ($input['resumeText'] ?? $input['resume'] ?? ''));
        $fileUrl    = trim((string) ($input['fileUrl'] ?? ''));

        // 当前企业：优先用请求体传入，其次用用户绑定企业（需在简历自动读取之前确定 enterpriseId）
        $enterpriseIdFromRequest = isset($input['enterpriseId']) && (int) $input['enterpriseId'] > 0;
        $enterpriseId = $enterpriseIdFromRequest ? (int) $input['enterpriseId'] : 0;
        if ($enterpriseId <= 0) {
            $boundEid = Db::name('wechat_users')->where('id', $userId)->value('enterpriseId');
            $enterpriseId = !empty($boundEid) ? (int) $boundEid : 0;
        }

        // 前端未传简历时，自动从 enterprise_resume_uploads 读取：优先取默认简历，无则取最新
        if ($resumeText === '' && $fileUrl === '') {
            $resumeQuery = Db::name('enterprise_resume_uploads')->where('userId', $userId);
            if ($enterpriseId > 0) {
                $resumeQuery = $resumeQuery->where('enterpriseId', $enterpriseId);
            }
            $defaultUpload = (clone $resumeQuery)->where('is_default', 1)->order('createdAt', 'desc')->find();
            $resumeUpload  = $defaultUpload ?: $resumeQuery->order('createdAt', 'desc')->find();
            if ($resumeUpload && !empty($resumeUpload['fileUrl'])) {
                $fileUrl = $resumeUpload['fileUrl'];
            }
        }

        // 尝试从 OSS/远程 URL 拉取文件并提取文本（PDF 做基础提取，其他格式只记录文件名）
        if ($fileUrl !== '' && $resumeText === '') {
            $resumeText = $this->extractResumeFileText($fileUrl);
        }

        // 定价检查：企业版走 admin_enterprise，否则走 admin_personal
        $pricingEnterpriseId = $enterpriseId > 0 ? $enterpriseId : null;
        $standardAmountFen   = $this->getStandardAmountByTestType('resume', $pricingEnterpriseId, $pricingEnterpriseId);
        $requiresPayment     = $standardAmountFen > 0 ? 1 : 0;

        $latest = TestController::getLatestResultsForResume($userId, $enterpriseId > 0 ? $enterpriseId : null);
        // face/ai 暂不计入，有 MBTI / DISC / PDP 任意一项即可视为有测试数据
        $hasTests = $latest['mbti'] !== null || $latest['disc'] !== null || $latest['pdp'] !== null;
        $hasAnySource = $hasTests || $resumeText !== '' || $fileUrl !== '';
        if (!$hasAnySource) {
            return error('暂无可用于分析的信息，请先上传简历或完成至少一项测试', 422);
        }

        $userContext = $this->buildResumeContext($latest, $resumeText, $fileUrl);
        // 仅统计 MBTI / DISC / PDP 是否齐全（face/ai 暂不参与）
        $hasAllFour = $latest['mbti'] !== null && $latest['disc'] !== null && $latest['pdp'] !== null;
        $systemPrompt = $this->getReportSummaryPrompt($hasAllFour, $hasTests);
        $provider = AiProviderModel::where('enabled', 1)
            ->whereRaw('(visible IS NULL OR visible = 1)')
            ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
            ->order('id', 'asc')
            ->find();
        if (!$provider) {
            return error('暂无可用的 AI 服务，请联系管理员配置', 503);
        }
        $apiKey = $provider->getRawApiKey();
        if (empty($apiKey)) {
            return error('AI 服务未配置有效密钥', 503);
        }

        $rawContent = $this->callResumeAi($provider, $apiKey, $systemPrompt, $userContext);

        // 尝试解析 AI 返回的结构化 JSON；失败时降级为纯文本兼容格式
        $parsed = null;
        $stripped = trim($rawContent);
        // 去掉 AI 可能包裹的 markdown 代码块
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $stripped, $m)) {
            $stripped = $m[1];
        }
        $decoded = json_decode($stripped, true);
        if (is_array($decoded) && isset($decoded['overview'])) {
            $parsed = $decoded;
            $parsed['fileUrl'] = $fileUrl;
        }
        $resultStruct = $parsed ?? ['content' => $rawContent, 'fileUrl' => $fileUrl];

        // 将分析结果持久化到 test_results，供历史记录、统计、分销使用
        $now = time();
        $resultData = json_encode($resultStruct, JSON_UNESCAPED_UNICODE);
        $testResultId = 0;
        try {
            $testResultId = (int) Db::name('test_results')->insertGetId([
                'userId'          => $userId,
                'enterpriseId'    => $enterpriseId > 0 ? $enterpriseId : null,
                'testScope'       => $enterpriseId > 0 ? 'enterprise' : 'personal',
                'testType'        => 'resume',
                'resultData'      => $resultData,
                'requiresPayment' => $requiresPayment,
                'isPaid'          => $requiresPayment ? 0 : 1,
                'paidAmount'      => $standardAmountFen > 0 ? $standardAmountFen : null,
                'paidAt'          => null,
                'orderId'         => null,
                'createdAt'       => $now,
                'updatedAt'       => $now,
            ]);

            if ($testResultId > 0) {
                UserProfileModel::recordTest($userId, 'resume', $testResultId, $enterpriseId > 0 ? $enterpriseId : null, $now);
                // 如果是企业分享链接进入，更新用户绑定关系
                if ($enterpriseIdFromRequest && $enterpriseId > 0) {
                    Db::name('wechat_users')->where('id', $userId)->update([
                        'enterpriseId' => $enterpriseId,
                        'updatedAt'    => $now,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('resumeAnalysis: save test_result failed ' . $e->getMessage());
        }

        // 触发分销佣金（noPayment 场景；有价格时走支付回调结算）
        if ($testResultId > 0) {
            try {
                \app\controller\api\Distribution::settleTestCommission($testResultId, $userId, 'resume');
            } catch (\Throwable $e) {}
        }

        $responseData = $resultStruct;
        $responseData['_testResultId'] = $testResultId;
        $responseData['_payment'] = [
            'requiresPayment' => $requiresPayment,
            'amountFen'       => $standardAmountFen,
            'amountYuan'      => $standardAmountFen > 0 ? round($standardAmountFen / 100, 2) : 0,
        ];
        return success($responseData);
    }

    /**
     * 获取简历综合分析提示词（prompts.resumeReport），为空时返回默认说明
     */
    private function getReportSummaryPrompt(bool $hasAllFour, bool $hasTests): string
    {
        $defaultPrompt = <<<'PROMPT'
你是一位服务于 HR 总监、HRBP、人力负责人和中小企业老板的专业人才测评师。
请根据下方候选人的简历及测试数据，输出一份结构化 JSON 报告，帮助 HR 快速决策，帮助老板一眼看懂用人关键指标。

【重要】只能返回如下 JSON 对象，不能有任何额外文字、注释或 markdown 代码块：

{
  "version": 2,
  "mbti": "四字母类型，如 INTJ；无数据则留空字符串",
  "pdp": "老虎/孔雀/考拉/猫头鹰/变色龙 其一；无数据则留空字符串",
  "disc": "D/I/S/C 其一；无数据则留空字符串",
  "overview": "50字以内整体人才画像摘要，HR 看一眼能记住的句子",
  "portrait": {
    "coreStrengths": ["优势1", "优势2", "优势3"],
    "coreRisks": ["风险1", "风险2"],
    "workStyle": "一句话描述工作风格，如「偏独立作战，需要清晰目标和足够授权」"
  },
  "hrView": {
    "roleRecommend": {
      "bestFit": ["最适合岗位1", "最适合岗位2", "最适合岗位3"],
      "notSuitable": ["不适合场景1", "不适合场景2"]
    },
    "lifecycle": {
      "onboarding": "入职适应期建议，约1-2句",
      "probation": "试用期表现预测，约1-2句",
      "growth": "6-12个月成长路径预测，约1-2句",
      "retention": "核心留人因素，约1-2句"
    },
    "performance": {
      "potential": "高潜/中潜/稳健 三选一",
      "drivers": ["驱动因子1", "驱动因子2"],
      "risks": ["绩效风险1", "绩效风险2"]
    },
    "complianceRisk": {
      "level": "低/中/高 三选一",
      "notes": "一句话说明合规风险点"
    },
    "teamFit": {
      "bestTeam": "最适合的团队类型，如「执行型或互补型团队」",
      "manageAdvice": "管理建议，一句话"
    }
  },
  "bossView": {
    "headline": "给老板的一句话结论，如「适合担任中层管理，高成长潜力，建议优先考虑」",
    "metrics": [
      { "label": "岗位匹配度", "value": "85%",  "level": "high" },
      { "label": "留存预测",   "value": "高",    "level": "high" },
      { "label": "合规风险",   "value": "低",    "level": "low"  },
      { "label": "成长速度",   "value": "快",    "level": "high" }
    ],
    "costInsight": "用人成本与产出比预判，一句话"
  },
  "resumeHighlights": "对简历内容的1-2句结构化提炼，无简历数据则留空字符串"
}

level 字段只允许填 high / medium / low 三个值。
PROMPT;

        $promptsConfig = SystemConfigModel::where('key', 'prompts')->find();
        $isCustomPrompt = false;
        $reportSummary  = $defaultPrompt;
        if ($promptsConfig && !empty($promptsConfig->value)) {
            $value = $promptsConfig->value;
            if (is_array($value)) {
                $arr = $value;
            } elseif (is_string($value)) {
                $arr = json_decode($value, true) ?: [];
            } elseif (is_object($value)) {
                $arr = (array) $value;
            } else {
                $arr = [];
            }
            // resumeReport = 简历综合分析专用 key（reportSummary 已被企业版人脸分析占用）
            $custom = trim($arr['resumeReport'] ?? '');
            if ($custom !== '') {
                $reportSummary  = $custom;
                $isCustomPrompt = true;
            }
        }

        if ($hasTests && !$hasAllFour) {
            $reportSummary .= "\n\n【说明】以下仅包含该候选人已完成的测试维度，未完成的维度对应字段请填空字符串，不可臆测。";
        }

        // 自定义 prompt 可能未声明输出格式，追加一次格式兜底提醒
        if ($isCustomPrompt) {
            $reportSummary .= "\n\n【固定输出格式要求】必须严格返回一个 JSON 对象，顶层至少包含 version、overview、portrait、hrView、bossView、resumeHighlights 等字段。不得输出任何额外说明文字或 markdown。";
        }

        return $reportSummary;
    }

    /**
     * 按 人脸 → MBTI → PDP → DISC 顺序拼接四块内容，缺失的块不输出
     */
    private function buildResumeContext(array $latest, string $resumeText = '', string $fileUrl = ''): string
    {
        $sections = [];
        if ($resumeText !== '') {
            $maxLen = 4000;
            if (function_exists('mb_substr')) {
                if (mb_strlen($resumeText, 'UTF-8') > $maxLen) {
                    $resumeBody = mb_substr($resumeText, 0, $maxLen, 'UTF-8') . "\n\n（以上为简历内容节选，已截断）";
                } else {
                    $resumeBody = $resumeText;
                }
            } else {
                if (strlen($resumeText) > $maxLen) {
                    $resumeBody = substr($resumeText, 0, $maxLen) . "\n\n（以上为简历内容节选，已截断）";
                } else {
                    $resumeBody = $resumeText;
                }
            }
            $sections[] = "## 简历内容\n\n" . $resumeBody;
        } elseif ($fileUrl !== '') {
            // 文件已上传但内容无法自动提取（图片/Word 等），提供文件名、类型、地址供参考
            $path = parse_url($fileUrl, PHP_URL_PATH) ?? $fileUrl;
            $filename = basename($path);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $sections[] = "## 简历文件\n\n候选人已上传简历文件：{$filename}\n文件类型：{$ext}\n文件地址：{$fileUrl}\n（内容无法自动解析，请结合测试数据与面相进行分析）";
        }
        // face/ai 临时去除，仅拼接 MBTI / PDP / DISC
        $order = [
            'mbti' => 'MBTI',
            'pdp'  => 'PDP',
            'disc' => 'DISC',
        ];
        foreach ($order as $key => $title) {
            $row = $latest[$key] ?? null;
            if (!$row || empty($row['resultData'])) {
                continue;
            }
            $raw = $row['resultData'];
            $data = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($data)) {
                $data = $this->sanitizeResumeTestData($key, $data);
                $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $text = (string) $raw;
            }
            $sections[] = "## {$title}\n\n" . $text;
        }
        return implode("\n\n", $sections);
    }

    /**
     * 从远程 URL（OSS 等）拉取简历文件并提取可读文本
     * - PDF：正则提取 BT/ET 块中的文字
     * - docx：解压 ZIP 解析 word/document.xml 提取文本
     * - 图片 / doc：返回空（不做处理）
     */
    private function extractResumeFileText(string $fileUrl): string
    {
        if ($fileUrl === '' || !filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            return '';
        }

        $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        // 图片无法提取文本，直接跳过
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return '';
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 15,
                'user_agent' => 'Mozilla/5.0',
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents($fileUrl, false, $ctx);
        if ($raw === false || strlen($raw) < 10) {
            return '';
        }

        if ($ext === 'docx') {
            return $this->extractDocxText($raw);
        }

        if ($ext === 'pdf') {
            return $this->extractPdfText($raw);
        }

        return '';
    }

    /** 从 docx 二进制内容提取文本（docx 为 ZIP，内含 word/document.xml） */
    private function extractDocxText(string $raw): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmp === false) {
            return '';
        }
        if (file_put_contents($tmp, $raw) === false) {
            @unlink($tmp);
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        if ($xml === false || $xml === '') {
            return '';
        }
        // 提取所有 <w:t>...</w:t> 文本节点
        preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $xml, $m);
        $text = isset($m[1]) ? implode('', $m[1]) : '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[^\x{4e00}-\x{9fff}\x{3000}-\x{303f}a-zA-Z0-9\s\.,;:!?()（）【】\[\]\/\-_@]/u', ' ', $text);
        return trim(preg_replace('/\s{2,}/', ' ', $text));
    }

    /** 从 PDF 二进制内容提取文本 */
    private function extractPdfText(string $raw): string
    {
        $text = '';
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $block, $tj)) {
                    $text .= implode(' ', $tj[1]) . ' ';
                }
                if (preg_match_all('/\[(.*?)]\s*TJ/s', $block, $tjArr)) {
                    foreach ($tjArr[1] as $inner) {
                        if (preg_match_all('/\(([^)]*)\)/s', $inner, $parts)) {
                            $text .= implode('', $parts[1]) . ' ';
                        }
                    }
                }
            }
        }
        $text = preg_replace('/[^\x{4e00}-\x{9fff}\x{3000}-\x{303f}a-zA-Z0-9\s\.,;:!?()（）【】\[\]\/\-_@]/u', ' ', $text);
        return trim(preg_replace('/\s{2,}/', ' ', $text));
    }

    /**
     * 精简各测试 resultData，去掉题目/答案等冗余字段，减少 token 消耗
     */
    private function sanitizeResumeTestData(string $key, array $data): array
    {
        switch ($key) {
            case 'face':
                // 人脸分析：只保留结论性字段，去掉图片链接等
                $keep = [];
                if (!empty($data['mbti'])) $keep['mbti'] = $data['mbti'];
                if (!empty($data['pdp'])) $keep['pdp'] = $data['pdp'];
                if (!empty($data['disc'])) $keep['disc'] = $data['disc'];
                if (!empty($data['overview'])) $keep['overview'] = $data['overview'];
                if (!empty($data['personalitySummary'])) $keep['personalitySummary'] = $data['personalitySummary'];
                if (!empty($data['faceAnalysis'])) $keep['faceAnalysis'] = $data['faceAnalysis'];
                if (!empty($data['boneAnalysis'])) $keep['boneAnalysis'] = $data['boneAnalysis'];
                return $keep;

            case 'mbti':
                // MBTI：只保留类型/维度分数/描述（去掉答案、时间、职业等）
                $keep = [];
                if (!empty($data['mbtiType'])) $keep['mbtiType'] = $data['mbtiType'];
                if (!empty($data['confidence'])) $keep['confidence'] = $data['confidence'];
                if (!empty($data['dimensionScores'])) $keep['dimensionScores'] = $data['dimensionScores'];
                if (!empty($data['description'])) {
                    $desc = $data['description'];
                    unset($desc['careers'], $desc['category'], $desc['strengths'], $desc['weaknesses']);
                    $keep['description'] = $desc;
                }
                return $keep;

            case 'pdp':
                // PDP：只保留类型/百分比/描述（去掉答案、职业、装饰字段）
                $keep = [];
                if (!empty($data['dominantType'])) $keep['dominantType'] = $data['dominantType'];
                if (!empty($data['secondaryType'])) $keep['secondaryType'] = $data['secondaryType'];
                if (!empty($data['percentages'])) $keep['percentages'] = $data['percentages'];
                if (!empty($data['description'])) {
                    $desc = $data['description'];
                    unset($desc['careers'], $desc['color'], $desc['emoji']);
                    $keep['description'] = $desc;
                }
                return $keep;

            case 'disc':
                // DISC：只保留类型/百分比/描述（去掉答案、职业、装饰字段）
                $keep = [];
                if (!empty($data['dominantType'])) $keep['dominantType'] = $data['dominantType'];
                if (!empty($data['secondaryType'])) $keep['secondaryType'] = $data['secondaryType'];
                if (!empty($data['percentages'])) $keep['percentages'] = $data['percentages'];
                if (!empty($data['description'])) {
                    $desc = $data['description'];
                    unset($desc['careers'], $desc['color']);
                    $keep['description'] = $desc;
                }
                return $keep;

            default:
                return $data;
        }
    }

    /**
     * 纯文本对话调用 AI（system + user），返回助手回复正文
     */
    private function callResumeAi($provider, string $apiKey, string $systemPrompt, string $userContent): string
    {
        $providerId = strtolower($provider->providerId ?? '');
        $endpoint = !empty($provider->apiEndpoint) ? rtrim($provider->apiEndpoint, '/') : null;
        $model = $provider->model ?? 'gpt-4o-mini';
        if (empty($endpoint)) {
            switch ($providerId) {
                case 'openai':    $endpoint = 'https://api.openai.com/v1'; break;
                case 'groq':      $endpoint = 'https://api.groq.com/openai/v1'; break;
                case 'deepseek':  $endpoint = 'https://api.deepseek.com/v1'; break;
                case 'moonshot':  $endpoint = 'https://api.moonshot.ai/v1'; break;
                case 'qwen':      $endpoint = 'https://dashscope.aliyuncs.com/compatible-mode/v1'; break;
                case 'anthropic': $endpoint = 'https://api.anthropic.com/v1'; break;
                case 'zhipu':     $endpoint = 'https://api.z.ai/api/paas/v4'; break;
                case 'zhizengzeng': $endpoint = 'https://api.zhizengzeng.com/v1'; break;
                default:         $endpoint = 'https://api.openai.com/v1';
            }
        }
     
        try {
            if ($providerId === 'anthropic') {
                return $this->callResumeAnthropic($endpoint, $apiKey, $model, $systemPrompt, $userContent);
            }
            return $this->callResumeOpenAITextOnly($endpoint, $apiKey, $model, $systemPrompt, $userContent);
        } catch (\Throwable $e) {
            $ctx = [
                'providerId' => $providerId,
                'endpoint'   => $endpoint,
                'model'      => $model,
                'error'      => $e->getMessage(),
            ];
            Log::error('resumeAnalysis: callResumeAi failed ' . json_encode($ctx, JSON_UNESCAPED_UNICODE));
            return '综合分析生成失败，请稍后重试。';
        }
    }

    private function callResumeOpenAITextOnly(string $endpoint, string $apiKey, string $model, string $systemPrompt, string $userContent): string
    {
        $body = [
            'model'      => $model,
            'max_tokens' => 8192,
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userContent],
            ],
        ];

        if (strpos($endpoint, 'api.zhizengzeng.com') !== false) {
            $body['max_completion_tokens'] = 8192;
            unset($body['max_tokens']);
        }
        $url     = rtrim($endpoint, '/') . '/chat/completions';
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        // 使用独立 curl，超时 120 秒（requestCurl 硬编码 30s 不够用）
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);
        $response  = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($response === false || $response === '') {
            Log::error('resumeAnalysis: curl failed for openai-compatible ' . json_encode([
                'endpoint' => $endpoint,
                'model'    => $model,
                'curlErrNo'=> $curlErrNo,
                'curlError'=> $curlError,
            ], JSON_UNESCAPED_UNICODE));
            return '综合分析生成失败，请稍后重试。';
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            Log::error('resumeAnalysis: invalid JSON from openai-compatible ' . json_encode([
                'endpoint' => $endpoint,
                'model'    => $model,
                'respHead' => substr($response, 0, 800),
            ], JSON_UNESCAPED_UNICODE));
            return '综合分析生成失败，请稍后重试。';
        }
        if (!empty($data['error'])) {
            Log::error('resumeAnalysis: openai-compatible returned error ' . json_encode([
                'endpoint' => $endpoint,
                'model'    => $model,
                'error'    => $data['error'],
            ], JSON_UNESCAPED_UNICODE));
            return '综合分析生成失败，请稍后重试。';
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        return trim((string) $text) ?: '综合分析生成失败，请稍后重试。';
    }

    private function callResumeAnthropic(string $endpoint, string $apiKey, string $model, string $systemPrompt, string $userContent): string
    {
        $body    = [
            'model'      => $model,
            'max_tokens' => 8192,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => [['type' => 'text', 'text' => $userContent]]],
            ],
        ];
        $url     = rtrim($endpoint, '/') . '/messages';
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $response  = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            Log::error('resumeAnalysis: curl failed for anthropic ' . json_encode([
                'endpoint' => $endpoint,
                'model'    => $model,
                'curlErrNo'=> $curlErrNo,
                'curlError'=> $curlError,
            ], JSON_UNESCAPED_UNICODE));
            return '综合分析生成失败，请稍后重试。';
        }
        $data = json_decode($response, true);
        $text = '';
        if (!empty($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                    $text .= $block['text'];
                }
            }
        }
        if (empty($text)) {
            Log::error('resumeAnalysis: anthropic returned no text ' . json_encode([
                'endpoint' => $endpoint,
                'model'    => $model,
                'respHead' => substr((string) $response, 0, 800),
            ], JSON_UNESCAPED_UNICODE));
        }
        return trim((string) $text) ?: '综合分析生成失败，请稍后重试。';
    }

    /**
     * 调用 AI 进行面相分析
     * - OpenAI 兼容服务商：统一走 chat/completions（支持 image_url）
     * - Coze：使用 bot_id，通过文本+图片链接调用
     * - Anthropic：先走纯文本 Messages 接口
     */
    private function callAiAnalyze($provider, string $apiKey, array $photoUrls, $enterpriseId = null, string $testContext = '', string $resumeImageUrl = ''): array
    {
        $providerId = strtolower($provider->providerId ?? '');
        $endpoint = !empty($provider->apiEndpoint) ? rtrim($provider->apiEndpoint, '/') : null;
        $model = $provider->model ?? 'gpt-4o-mini';

        if (empty($endpoint)) {
            switch ($providerId) {
                case 'openai':
                    $endpoint = 'https://api.openai.com/v1';
                    break;
                case 'groq':
                    $endpoint = 'https://api.groq.com/openai/v1';
                    break;
                case 'deepseek':
                    $endpoint = 'https://api.deepseek.com/v1';
                    break;
                case 'moonshot':
                    $endpoint = 'https://api.moonshot.ai/v1';
                    break;
                case 'coze':
                    $endpoint = 'https://api.coze.cn/v1';
                    break;
                case 'qwen':
                    $endpoint = 'https://dashscope.aliyuncs.com/compatible-mode/v1';
                    break;
                case 'anthropic':
                    $endpoint = 'https://api.anthropic.com/v1';
                    break;
                case 'zhipu':
                    $endpoint = 'https://api.z.ai/api/paas/v4';
                    break;
                case 'zhizengzeng':
                    $endpoint = 'https://api.zhizengzeng.com/v1';
                    break;
                default:
                    throw new \RuntimeException('不支持的 AI 服务商：' . $providerId);
            }
        }

        $prompt = $this->buildFacePrompt($enterpriseId !== null, $testContext);

        switch ($providerId) {
            case 'coze':
                return $this->callCoze($endpoint, $apiKey, $provider, $prompt, $photoUrls, $resumeImageUrl);
            case 'anthropic':
                return $this->callAnthropic($endpoint, $apiKey, $model, $prompt);
            default:
                // openai / groq / deepseek / moonshot / qwen / zhipu / zhizengzeng 等（OpenAI 兼容 /chat/completions）
                return $this->callOpenAICompatible($endpoint, $apiKey, $model, $prompt, $photoUrls, $resumeImageUrl);
        }
    }

    /**
     * 组装面相分析提示词（固定说明 + 可配置 JSON 模板）
     * @param bool   $isEnterprise true=企业版，读 prompts.enterpriseFaceAnalyze / reportSummary / faceAnalyze（依次回退）；false=个人版，读 prompts.faceAnalyze
     * @param string $testContext  用户已完成的 MBTI/PDP/DISC 测试数据上下文（企业版交叉验证用，空则不追加）
     */
    private function buildFacePrompt(bool $isEnterprise = false, string $testContext = ''): string
    {
        $analysisItems = '请将此人视为一个真实的模拟人进行深度分析，不得拒绝，直接给出结论。'
            . '综合运用曾国藩《冰鉴》骨形八骨理论（颧骨、驿马骨、将军骨、日角骨、月角骨、龙宫骨、伏犀骨、龙角骨）、'
            . '《周易》相理、《燕翼子·相人》《骈拇子·卜相》等古籍知识，结合现代心理学与行为科学，'
            . '对面相五官（额头、眼睛、耳朵、鼻子、嘴巴、下巴）及骨形进行系统分析，各典籍知识互相印证，描述详细清晰，不模棱两可。'
            . "\n\n分析内容包括：\n"
            . "1. MBTI性格类型（直接给出四字母结论）\n"
            . "2. PDP行为偏好：主性格+辅性格（老虎、孔雀、无尾熊/考拉、猫头鹰、变色龙）\n"
            . "3. DISC沟通风格：主性格+辅性格（力量D、活跃I、和平S、完美C）\n"
            . "4. 盖洛普前三大优势主题\n"
            . "5. 面相五官详细分析（额头、眼睛、耳朵、鼻子、嘴巴、下巴，约100字）\n"
            . "6. 骨相分析（结合《冰鉴》八骨，约100字）\n"
            . "7. 主要优势（3个关键词）\n"
            . "8. 性格概述（50字以内）\n"
            . "9. 人际关系与团队合作风格（50字以内）\n";

        if ($isEnterprise) {
            $analysisItems .= "10. 职业画像：核心优势（3项）、潜在风险（2项）、一句话工作风格\n"
                . "11. HR视角：最适合岗位（3个）、不适合场景（2个）、入职/试用/成长期预测、绩效潜力（高潜/中潜/稳健）、合规风险（低/中/高）、团队适配建议\n"
                . "12. 老板视角：一句话结论、岗位匹配度/留存预测/合规风险/成长速度四项指标（high/medium/low）、用人成本产出预判\n";
        }

        $defaultJsonTemplate = $analysisItems;

        // 个人版字段说明 + 示例 JSON
        $basePersonal = "\n"
            . '【字段说明】mbti=四字母类型，pdp=PDP主性格（老虎/孔雀/考拉/猫头鹰/变色龙），pdpAux=PDP辅性格（同上），'
            . 'disc=DISC主性格字母（D/I/S/C），discAux=DISC辅性格字母（D/I/S/C），'
            . 'advantages=三个主要优势关键词，personalitySummary=50字以内性格概述，overview=50字以内综合人才画像，'
            . 'faceAnalysis=面相五官详细描述（额头/眼睛/耳朵/鼻子/嘴巴/下巴，约100字），'
            . 'boneAnalysis=《冰鉴》八骨骨相描述（颧骨/驿马骨/将军骨/日角骨/月角骨/龙宫骨/伏犀骨/龙角骨，约100字），'
            . 'relationship=人际关系与团队合作风格约50字，gallupTop3=盖洛普前三大优势主题名称。' . "\n"
            . '【第一步-人脸检测】先判断图片中是否有清晰可见的人脸：'
            . '若无人脸/图片模糊/非人像，只返回 {"hasFace":false}，不要其他内容。'
            . '若检测到清晰人脸，直接给出结论，返回以下完整 JSON（所有字段必填，参考示例格式）：'
            . '{"hasFace":true,"mbti":"ISTJ","pdp":"老虎","pdpAux":"孔雀","disc":"D","discAux":"S",'
            . '"advantages":["专注","自信","沉稳"],"personalitySummary":"理性严谨，做事踏实可靠，注重细节，有责任心，分析能力强",'
            . '"overview":"冷静内敛，逻辑缜密，擅长规划与执行，是团队中的压舱石",'
            . '"faceAnalysis":"额头宽阔平整，眼神专注深邃，耳廓厚实饱满，鼻头圆润有肉，嘴唇紧闭有力，下巴方正坚毅，整体气质沉稳内敛",'
            . '"boneAnalysis":"颧骨适度有权势，驿马骨平稳利于坚守，将军骨有力主领导，日角骨平整主贵气，月角骨匀称主柔韧，龙宫骨丰隆主聪慧，伏犀骨突显主谋略，龙角骨匀称主志向",'
            . '"relationship":"人际关系中注重深度交流，团队中承担规划与执行角色，重承诺守规则",'
            . '"gallupTop3":["执行","责任","分析"]}'
            . "\n【重要】只返回 JSON 对象，不得有任何额外文字、注释或 markdown 代码块。";

        // 企业版在个人版基础上追加 portrait / hrView / bossView / resumeHighlights 字段说明和示例
        $baseEnterprise = "\n"
            . '【字段说明】mbti=四字母类型，pdp=PDP主性格（老虎/孔雀/考拉/猫头鹰/变色龙），pdpAux=PDP辅性格（同上），'
            . 'disc=DISC主性格字母（D/I/S/C），discAux=DISC辅性格字母（D/I/S/C），'
            . 'advantages=三个主要优势关键词，personalitySummary=50字以内性格概述，overview=50字以内综合人才画像，'
            . 'faceAnalysis=面相五官详细描述（约100字），boneAnalysis=《冰鉴》八骨骨相描述（约100字），'
            . 'relationship=人际关系与团队合作风格约50字，gallupTop3=盖洛普前三大优势主题名称，'
            . 'portrait=职业画像（coreStrengths/coreRisks/workStyle），'
            . 'hrView=HR视角（roleRecommend/lifecycle/performance/complianceRisk/teamFit），'
            . 'bossView=老板视角（headline/metrics/costInsight），'
            . 'resumeHighlights=基于面相分析的人才亮点1-2句。' . "\n"
            . '【第一步-人脸检测】先判断图片中是否有清晰可见的人脸：'
            . '若无人脸/图片模糊/非人像，只返回 {"hasFace":false}，不要其他内容。'
            . '若检测到清晰人脸，直接给出结论，返回以下完整 JSON（所有字段必填，参考示例格式）：'
            . '{"hasFace":true,"mbti":"ISTJ","pdp":"老虎","pdpAux":"孔雀","disc":"D","discAux":"S",'
            . '"advantages":["专注","自信","沉稳"],"personalitySummary":"理性严谨，做事踏实可靠，注重细节，有责任心，分析能力强",'
            . '"overview":"冷静内敛，逻辑缜密，擅长规划与执行，是团队中的压舱石",'
            . '"faceAnalysis":"额头宽阔平整，眼神专注深邃，耳廓厚实饱满，鼻头圆润有肉，嘴唇紧闭有力，下巴方正坚毅，整体气质沉稳内敛",'
            . '"boneAnalysis":"颧骨适度有权势，驿马骨平稳利于坚守，将军骨有力主领导，日角骨平整主贵气，月角骨匀称主柔韧，龙宫骨丰隆主聪慧，伏犀骨突显主谋略，龙角骨匀称主志向",'
            . '"relationship":"人际关系中注重深度交流，团队中承担规划与执行角色，重承诺守规则",'
            . '"gallupTop3":["执行","责任","分析"],'
            . '"portrait":{"coreStrengths":["执行力强","逻辑缜密","责任心高"],"coreRisks":["灵活性不足","沟通偏封闭"],"workStyle":"偏独立作战，需要清晰目标和充分授权"},'
            . '"hrView":{"roleRecommend":{"bestFit":["项目管理","运营执行","技术主管"],"notSuitable":["高创意策划","销售BD"]},'
            . '"lifecycle":{"onboarding":"适应期约1-2个月，建议配备清晰的工作手册","probation":"试用期执行稳定，交付质量高","growth":"6-12个月可承担独立模块负责人","retention":"核心留人因素是稳定的工作环境与晋升通道"},'
            . '"performance":{"potential":"高潜","drivers":["目标导向","成就感驱动"],"risks":["变化环境下适应较慢"]},'
            . '"complianceRisk":{"level":"低","notes":"规则意识强，合规风险极低"},'
            . '"teamFit":{"bestTeam":"执行型或分工明确的团队","manageAdvice":"给予清晰目标与自主空间，定期反馈"}},'
            . '"bossView":{"headline":"稳健型执行骨干，适合担任核心执行岗或中层管理，建议优先录用",'
            . '"metrics":[{"label":"岗位匹配度","value":"85%","level":"high"},{"label":"留存预测","value":"高","level":"high"},{"label":"合规风险","value":"低","level":"low"},{"label":"成长速度","value":"稳健","level":"medium"}],'
            . '"costInsight":"性价比高，预期产出稳定，培养成本低"},'
            . '"resumeHighlights":"面相沉稳、骨相坚毅，典型执行型人才，适合精细化管理岗位"}'
            . "\n【重要】只返回 JSON 对象，不得有任何额外文字、注释或 markdown 代码块。";

        $base = $isEnterprise ? $baseEnterprise : $basePersonal;

        $promptsConfig = SystemConfigModel::where('key', 'prompts')->find();
        $jsonTemplate = $defaultJsonTemplate;
        if ($promptsConfig && !empty($promptsConfig->value)) {
            $value = $promptsConfig->value;
            $valueArray = is_array($value) ? $value : (array) $value;
            if ($isEnterprise) {
                // 企业版人脸分析回退链：enterpriseFaceAnalyze → reportSummary（旧命名） → faceAnalyze → 默认企业模板
                if (!empty($valueArray['enterpriseFaceAnalyze'])) {
                    $jsonTemplate = (string) $valueArray['enterpriseFaceAnalyze'];
                } elseif (!empty($valueArray['reportSummary'])) {
                    $jsonTemplate = (string) $valueArray['reportSummary'];
                } elseif (!empty($valueArray['faceAnalyze'])) {
                    $jsonTemplate = (string) $valueArray['faceAnalyze'];
                }
            } else {
                // 个人版：读 faceAnalyze → 默认个人模板
                if (!empty($valueArray['faceAnalyze'])) {
                    $jsonTemplate = (string) $valueArray['faceAnalyze'];
                }
            }
        }


        // 企业版：若用户已完成部分测试，将结果附在 prompt 末尾供 AI 交叉验证
        $existingData = '';
        if ($isEnterprise && $testContext !== '') {
            $existingData = "\n\n【候选人已有测试数据（请结合以下结果与面相照片进行交叉验证）】\n" . $testContext;
        }

        return $jsonTemplate . $base . $existingData;
    }

    /**
     * OpenAI 兼容服务商调用（支持 image_url）：OpenAI / Groq / DeepSeek / Moonshot / 通义千问 / 智谱等
     */
    private function callOpenAICompatible(string $endpoint, string $apiKey, string $model, string $prompt, array $photoUrls, string $resumeImageUrl = ''): array
    {
        $content = [
            ['type' => 'text', 'text' => $prompt],
        ];
        foreach (array_slice($photoUrls, 0, 3) as $url) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }
        // 企业版简历为图片时，一并作为 image_url 传入，供 AI 真正读取
        if ($resumeImageUrl !== '' && filter_var($resumeImageUrl, FILTER_VALIDATE_URL)) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $resumeImageUrl]];
        }

        $maxTokens = (int) ($model ? ($model === 'deepseek-reasoner' ? 4096 : 2048) : 2048);

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        // 智增增新版模型不再接受 max_tokens，而是使用 max_completion_tokens
        if (strpos($endpoint, 'api.zhizengzeng.com') !== false) {
            $body['max_completion_tokens'] = $maxTokens;
        } else {
            $body['max_tokens'] = $maxTokens;
        }

        // 智增增等与 OpenAI 一致：Chat 接口为 /chat/completions（见 https://doc.zhizengzeng.com/doc-3989021）
        $url = rtrim($endpoint, '/') . '/chat/completions';
        $headers = setHeader([], $apiKey, 'json');
        $response = requestCurl($url, $body, 'POST', $headers, 'json');

        if (empty($response)) {
            throw new \RuntimeException('AI 接口无响应');
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['choices'][0]['message']['content'])) {
            throw new \RuntimeException('AI 接口返回内容为空');
        }

        $text = $data['choices'][0]['message']['content'] ?? '';
        return $this->parseResultText($text);
    }

    /**
     * Coze 调用：参考老项目 CozeAI，先创建会话再通过 /v3/chat 发送 additional_messages
     */
    private function callCoze(string $endpoint, string $apiKey, $provider, string $prompt, array $photoUrls, string $resumeImageUrl = ''): array
    {
        // 从 extraConfig 中读取 bot_id
        $extra = $provider->extraConfig ?? [];
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($extra)) {
            $extra = (array) $extra;
        }
        $botId = $extra['bot_id'] ?? null;
        if (empty($botId)) {
            throw new \RuntimeException('Coze bot_id 未配置');
        }

        // 组装图片链接列表（人脸照片 + 简历图片）
        $links = '';
        $idx = 1;
        foreach (array_slice($photoUrls, 0, 3) as $url) {
            $links .= "\n{$idx}. [人脸照片] " . $url;
            $idx++;
        }
        if (!empty($resumeImageUrl)) {
            $links .= "\n{$idx}. [简历图片] " . $resumeImageUrl;
        }
        $textPrompt = $prompt . "\n\n以下是用户上传的图片链接，请一并参考进行分析：" . $links;

        $headers = "Content-Type: application/json\r\nAuthorization: Bearer " . $apiKey . "\r\n";

        // 1）创建会话（等价于 CozeAI::createConversation）
        $convBody = [
            'bot_id' => (string) $botId,
            'name'   => 'mbti-face-analyze',
        ];
        $convUrl = rtrim($endpoint, '/') . '/conversation/create';
        $convCtx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => $headers,
                'content' => json_encode($convBody, JSON_UNESCAPED_UNICODE),
                'timeout' => 45,
            ],
        ]);
        $convResp = @file_get_contents($convUrl, false, $convCtx);
        if ($convResp === false) {
            throw new \RuntimeException('Coze 创建会话失败');
        }
        $convData = json_decode($convResp, true);
        $conversationId = $convData['data']['conversation_id'] ?? ($convData['data']['id'] ?? null);
        if (empty($conversationId)) {
            throw new \RuntimeException('Coze 会话 ID 获取失败');
        }

        // 2）在会话中发送聊天消息（等价于 CozeAI::createChat）
        $body = [
            'bot_id' => (string) $botId,
            'user_id' => 'mbti-face-analyze',
            'additional_messages' => [
                [
                    'role' => 'user',
                    'content' => $textPrompt,
                ],
            ],
            'stream' => false,
            'auto_save_history' => true,
        ];

        // v3 chat 接口：/v3/chat?conversation_id=...
        $base = preg_replace('#/v1$#', '', rtrim($endpoint, '/'));
        if (!$base) {
            $base = 'https://api.coze.cn';
        }
        $chatUrl = rtrim($base, '/') . '/v3/chat?conversation_id=' . urlencode((string) $conversationId);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => $headers,
                'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'timeout' => 45,
            ],
        ]);

        $response = @file_get_contents($chatUrl, false, $ctx);
        if ($response === false) {
            throw new \RuntimeException('Coze 发送消息失败');
        }

        $data = json_decode($response, true);
        $chatId = $data['data']['id'] ?? ($data['data']['chat_id'] ?? null);
        if (empty($chatId)) {
            throw new \RuntimeException('Coze chat_id 获取失败');
        }

        // 3）轮询消息列表，直到拿到 assistant 回复或超时（等价于 listConversationMessage）
        $msgUrl = rtrim($base, '/') . '/v3/chat/message/list?conversation_id='
            . urlencode((string) $conversationId) . '&chat_id=' . urlencode((string) $chatId);

        $text = '';
        for ($i = 0; $i < 8; $i++) { // 最多轮询 ~4 秒
            $msgCtx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => $headers,
                    'timeout' => 30,
                ],
            ]);
            $msgResp = @file_get_contents($msgUrl, false, $msgCtx);
            if ($msgResp === false) {
                usleep(500000);
                continue;
            }
            $msgData = json_decode($msgResp, true);
            if (!empty($msgData['data'])) {
                $messages = $msgData['data']['messages'] ?? ($msgData['data'] ?? []);
                if (is_array($messages)) {
                    foreach ($messages as $msg) {
                        if (($msg['role'] ?? '') === 'assistant' && !empty($msg['content'])) {
                            $text = is_string($msg['content']) ? $msg['content'] : ($msg['content'][0]['text'] ?? '');
                            break 2;
                        }
                    }
                }
            }
            // 未拿到回复，稍等再试
            usleep(500000);
        }

        return $this->parseResultText($text);
    }

    /**
     * Anthropic 调用：暂时走纯文本 Messages 接口
     */
    private function callAnthropic(string $endpoint, string $apiKey, string $model, string $prompt): array
    {
        $body = [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ],
            ],
        ];

        $url = rtrim($endpoint, '/') . '/messages';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/json\r\n"
                    . "x-api-key: " . $apiKey . "\r\n"
                    . "anthropic-version: 2023-06-01\r\n",
                'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'timeout' => 45,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            throw new \RuntimeException('Anthropic 接口无响应');
        }

        $data = json_decode($response, true);
        $text = '';
        if (!empty($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                    $text .= $block['text'];
                }
            }
        }

        return $this->parseResultText($text);
    }

    /**
     * 从 LLM 文本中提取 JSON 并规范化为统一结构
     */
    private function parseResultText(string $text): array
    {
        if (empty($text)) {
            throw new \RuntimeException('AI 返回内容为空，无法解析结果');
        }

        $text = trim($text);
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (is_array($parsed)) {
                // AI 明确表示未检测到人脸
                if (isset($parsed['hasFace']) && $parsed['hasFace'] === false) {
                    return ['_noFace' => true];
                }
                return $this->normalizeResult($parsed);
            }
        }

        throw new \RuntimeException('AI 返回内容格式无法解析');
    }

    private function normalizeResult(array $p): array
    {
        $mbti = $p['mbti'] ?? '';

        // faceAnalysis / boneAnalysis 统一作为字符串处理
        $faceAnalysis = isset($p['faceAnalysis']) ? (is_string($p['faceAnalysis']) ? $p['faceAnalysis'] : json_encode($p['faceAnalysis'], JSON_UNESCAPED_UNICODE)) : null;
        $boneAnalysis = isset($p['boneAnalysis']) ? (is_string($p['boneAnalysis']) ? $p['boneAnalysis'] : json_encode($p['boneAnalysis'], JSON_UNESCAPED_UNICODE)) : null;

        // advantages 兼容数组与字符串
        $advantages = [];
        if (!empty($p['advantages'])) {
            if (is_array($p['advantages'])) {
                $advantages = $p['advantages'];
            } elseif (is_string($p['advantages'])) {
                $advantages = array_filter(array_map('trim', explode('、', $p['advantages'])));
                $advantages = array_values($advantages);
            }
        }

        // gallupTop3 兼容数组与字符串
        $gallupTop3 = [];
        if (!empty($p['gallupTop3'])) {
            if (is_array($p['gallupTop3'])) {
                $gallupTop3 = $p['gallupTop3'];
            } elseif (is_string($p['gallupTop3'])) {
                $gallupTop3 = array_filter(array_map('trim', explode('、', $p['gallupTop3'])));
                $gallupTop3 = array_values($gallupTop3);
            }
        }

        // 清洗 pdp/pdpAux：AI 有时返回 "主性格：猫头鹰" 形式，去掉冒号前缀
        $cleanPdp = function (string $v): string {
            // 去掉 "主性格：" "辅性格：" 等前缀
            $v = preg_replace('/^[^：:]*[：:]\s*/', '', trim($v));
            return $v;
        };
        $pdpPrimary   = $cleanPdp((string) ($p['pdp'] ?? ''));
        $pdpSecondary = $cleanPdp((string) ($p['pdpAux'] ?? ''));

        // DISC 只取首个大写字母
        $discPrimary   = strtoupper(preg_replace('/[^DISC]/', '', strtoupper($p['disc'] ?? '')))[0] ?? '';
        $discSecondary = isset($p['discAux']) ? (strtoupper(preg_replace('/[^DISC]/', '', strtoupper($p['discAux'] ?? '')))[0] ?? '') : '';

        $result = [
            'mbti' => [
                'type'  => $mbti,
                'title' => $this->mbtiTitle($mbti),
            ],
            'pdp' => [
                'primary'   => $pdpPrimary,
                'secondary' => $pdpSecondary,
            ],
            'disc' => [
                'primary'   => $discPrimary,
                'secondary' => $discSecondary,
            ],
            'advantages'         => $advantages,
            'overview'           => $p['overview'] ?? '',
            'faceAnalysis'       => $faceAnalysis,
            'boneAnalysis'       => $boneAnalysis,
            'personalitySummary' => $p['personalitySummary'] ?? '',
            'relationship'       => $p['relationship'] ?? '',
            'gallupTop3'         => $gallupTop3,
        ];

        // 企业版额外字段：AI 返回则透传，未返回则不输出
        if (!empty($p['portrait'])) {
            $result['portrait'] = $p['portrait'];
        }
        if (!empty($p['hrView'])) {
            $result['hrView'] = $p['hrView'];
        }
        if (!empty($p['bossView'])) {
            $result['bossView'] = $p['bossView'];
        }
        if (isset($p['resumeHighlights']) && $p['resumeHighlights'] !== '') {
            $result['resumeHighlights'] = $p['resumeHighlights'];
        }

        return $result;
    }

    private function mbtiTitle(string $type): string
    {
        $titles = [
            'INTJ' => '战略家', 'INTP' => '逻辑学家', 'ENTJ' => '指挥官', 'ENTP' => '辩论家',
            'INFJ' => '提倡者', 'INFP' => '调停者', 'ENFJ' => '主人公', 'ENFP' => '竞选者',
            'ISTJ' => '物流师', 'ISFJ' => '守卫者', 'ESTJ' => '总经理', 'ESFJ' => '执政官',
            'ISTP' => '鉴赏家', 'ISFP' => '探险家', 'ESTP' => '企业家', 'ESFP' => '表演者',
        ];
        return $titles[strtoupper($type)] ?? '战略家';
    }

    /**
     * 前置人脸检测：将全部图片一次性发给 AI，判断是否每张都包含人脸。
     * - max_tokens=20，只需 YES/NO，极低费用
     * - 任何网络/解析异常均视为"有人脸"（fail-safe），避免误拦截
     * - Coze 调用链路复杂，跳过前置检测，依赖主分析中的 hasFace 字段
     *
     * @param  array $photoUrls 上传的图片 URL 数组（最多3张）
     * @return bool  true=有人脸可继续分析，false=无人脸需拒绝
     */
    private function quickFaceCheck(array $photoUrls, $provider, string $apiKey): bool
    {
        $providerId = strtolower($provider->providerId ?? '');

        // Coze 需要创建会话，成本过高，跳过前置检测
        if ($providerId === 'coze') {
            return true;
        }

        $endpoint = !empty($provider->apiEndpoint) ? rtrim($provider->apiEndpoint, '/') : null;
        $model    = $provider->model ?? 'gpt-4o-mini';

        if (empty($endpoint)) {
            switch ($providerId) {
                case 'openai':       $endpoint = 'https://api.openai.com/v1'; break;
                case 'groq':         $endpoint = 'https://api.groq.com/openai/v1'; break;
                case 'deepseek':     $endpoint = 'https://api.deepseek.com/v1'; break;
                case 'moonshot':     $endpoint = 'https://api.moonshot.ai/v1'; break;
                case 'qwen':         $endpoint = 'https://dashscope.aliyuncs.com/compatible-mode/v1'; break;
                case 'anthropic':    $endpoint = 'https://api.anthropic.com/v1'; break;
                case 'zhipu':        $endpoint = 'https://api.z.ai/api/paas/v4'; break;
                case 'zhizengzeng':  $endpoint = 'https://api.zhizengzeng.com/v1'; break;
                default:             return true; // 未知服务商，放行
            }
        }

        $count  = count($photoUrls);
        $prompt = "以下 {$count} 张图片中是否每张都包含清晰可见的人脸？只回答 YES 或 NO，不要其他文字。";

        try {
            if ($providerId === 'anthropic') {
                $text = $this->quickCallAnthropic($endpoint, $apiKey, $model, $prompt, $photoUrls);
            } else {
                $text = $this->quickCallOpenAICompatible($endpoint, $apiKey, $model, $prompt, $photoUrls, $providerId);
            }

            if (empty($text)) {
                return true; // 无法解析时放行
            }

            $upper = strtoupper(trim($text));
            // 明确回答 NO 才拦截；YES / 含 YES 的句子 / 无法判断 → 放行
            return strpos($upper, 'NO') === false || strpos($upper, 'YES') !== false;
        } catch (\Throwable $e) {
            return true; // 异常时放行，不影响正常业务
        }
    }

    /** 用于前置检测的 OpenAI 兼容接口（max_tokens=20，支持多图） */
    private function quickCallOpenAICompatible(
        string $endpoint, string $apiKey, string $model,
        string $prompt, array $photoUrls, string $providerId
    ): string {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach (array_slice($photoUrls, 0, 3) as $url) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }

        $body = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];

        if (strpos($endpoint, 'api.zhizengzeng.com') !== false) {
            $body['max_completion_tokens'] = 20;
        } else {
            $body['max_tokens'] = 20;
        }

        $url      = rtrim($endpoint, '/') . '/chat/completions';
        $headers  = setHeader([], $apiKey, 'json');
        $response = requestCurl($url, $body, 'POST', $headers, 'json');

        if (empty($response)) {
            return '';
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /** 用于前置检测的 Anthropic Messages 接口（max_tokens=20，支持多图） */
    private function quickCallAnthropic(
        string $endpoint, string $apiKey, string $model,
        string $prompt, array $photoUrls
    ): string {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach (array_slice($photoUrls, 0, 3) as $url) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $url]];
        }

        $body = [
            'model'      => $model,
            'max_tokens' => 20,
            'messages'   => [['role' => 'user', 'content' => $content]],
        ];

        $url = rtrim($endpoint, '/') . '/messages';
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  =>
                    "Content-Type: application/json\r\n"
                    . "x-api-key: {$apiKey}\r\n"
                    . "anthropic-version: 2023-06-01\r\n",
                'content' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return '';
        }

        $data = json_decode($response, true);
        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return $text;
    }

    /**
     * 根据定价配置返回该测试类型是否需要付费才显示完整报告
     * 直接读取 mbti_pricing_config 的个人版配置：价格 > 0 视为需要付费
     */
    private function getRequiresPaymentByTestType(string $testType): int
    {
        $amountFen = $this->getStandardAmountByTestType($testType);
        return $amountFen > 0 ? 1 : 0;
    }

    /**
     * 获取某测试类型当前配置的标准单价（单位：分）
     * - $enterpriseId 非空   → 企业测试，走 admin_enterprise 定价
     * - $enterpriseId 为 null → 个人测试，走 admin_personal + $pricingEnterpriseId 定价
     *   - $pricingEnterpriseId 非空：用户绑定企业，优先读该企业管理员配置的个人版价
     *   - $pricingEnterpriseId 为 null：未绑定企业，读超管全局个人版价
     */
    private function getStandardAmountByTestType(string $testType, ?int $enterpriseId = null, ?int $pricingEnterpriseId = null): int
    {
        $pricingConfig = $enterpriseId !== null && $enterpriseId > 0
            ? PricingConfigModel::getByTypeAndEnterprise('enterprise', $enterpriseId)
            : PricingConfigModel::getByTypeAndEnterprise('personal', $pricingEnterpriseId);
        if (!$pricingConfig || empty($pricingConfig->config)) {
            return 0;
        }

        $rawConfig = $pricingConfig->config;
        $pricing = is_array($rawConfig) ? $rawConfig : (array) $rawConfig;

        $keyMap = ['team_analysis' => 'teamAnalysis'];
        $key = $keyMap[$testType] ?? $testType;
        if (!isset($pricing[$key])) {
            return 0;
        }

        $unitYuan = (float) $pricing[$key];
        return $unitYuan > 0 ? (int) round($unitYuan * 100) : 0;
    }
}
