<?php
namespace app\controller\api;

use app\BaseController;
use app\model\Enterprise as EnterpriseModel;
use app\model\PricingConfig as PricingConfigModel;
use app\common\service\AiCallService;
use app\common\service\AiChatArticleDisplayService;
use app\common\service\JwtService;
use app\common\service\MpTabbarService;
use think\facade\Db;

/**
 * 小程序运行配置（定价 + AI 服务商）
 * 个人用户读超管个人定价，企业用户读超管企业定价；AI 一律读超管配置，默认使用第一个启用的服务商。
 */
class AppConfig extends BaseController
{
    /**
     * GET api/config/runtime
     * 可选 Header Authorization，有 token 且用户属于企业则返回企业定价，否则个人定价。
     * 返回：pricingType(personal|enterprise), pricing(config), aiProviderId, aiProviderName
     */
    public function runtime()
    {
        $pricingType = 'personal';
        $enterpriseId = null;
        $pricing = [];
        $scope = (string) ($this->request->param('scope', '') ?? '');

        $user = $this->request->user ?? null;
        if (!$user) {
            $token = JwtService::getTokenFromRequest($this->request);
            if ($token) {
                $payload = JwtService::verifyToken($token);
                if ($payload) {
                    $user = [
                        'source' => $payload['source'] ?? '',
                        'user_id' => $payload['user_id'] ?? $payload['userId'] ?? null,
                        'userId' => $payload['user_id'] ?? $payload['userId'] ?? null,
                    ];
                }
            }
        }
        // 绑定企业 ID：任意 scope 都要能读到（个人首页也是 scope=personal，否则 enterprisePermissions 永远为 null）
        $userBoundEnterpriseId = null;
        if ($user && ($user['source'] ?? '') === 'wechat') {
            $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
            if ($userId > 0) {
                $row = Db::name('wechat_users')->where('id', $userId)->field('enterpriseId')->find();
                if (!empty($row['enterpriseId'])) {
                    $userBoundEnterpriseId = (int) $row['enterpriseId'];
                }
            }
        }
        // 定价/文案用的 enterpriseId：仅在非个人 scope 时走企业定价（与个人页 scope=personal 仍可返回企业权限）
        if ($scope !== 'personal' && $userBoundEnterpriseId !== null && $userBoundEnterpriseId > 0) {
            $enterpriseId = $userBoundEnterpriseId;
            $pricingType = 'enterprise';
        }

        // 系统配置：审核模式、默认企业（无带参入口时小程序回落）
        $maintenanceMode = false;
        $miniprogramAuditMode = false;
        $defaultEnterpriseId = null;
        $systemRow = Db::name('system_config')->where('key', 'system')->find();
        if ($systemRow && !empty($systemRow['value'])) {
            $sysValEarly = is_string($systemRow['value']) ? json_decode($systemRow['value'], true) : $systemRow['value'];
            if (is_array($sysValEarly)) {
                if (!empty($sysValEarly['maintenanceMode'])) {
                    $maintenanceMode = true;
                }
                if (!empty($sysValEarly['miniprogramAuditMode'])) {
                    $miniprogramAuditMode = true;
                }
                if (!empty($sysValEarly['defaultEnterpriseId'])) {
                    $de = (int) $sysValEarly['defaultEnterpriseId'];
                    if ($de > 0) {
                        $defaultEnterpriseId = $de;
                    }
                }
            }
        }
        // scope=enterprise 且用户未绑定企业时，用超管默认企业拉企业定价与文案
        if ($scope !== 'personal' && $enterpriseId === null && $defaultEnterpriseId !== null) {
            $enterpriseId = $defaultEnterpriseId;
            $pricingType = 'enterprise';
        }

        $config = PricingConfigModel::getByTypeAndEnterprise($pricingType, $enterpriseId);
        if ($config && !empty($config->config)) {
            // PricingConfig 模型已对 config 做 JSON 转换，这里直接当数组/对象用即可
            $rawConfig = $config->config;
            $pricing = is_array($rawConfig) ? $rawConfig : (array) $rawConfig;
        }

        // 与 /api/ai/chat、/api/analyze 相同的第一优先服务商（sortWeight、余额沉底）
        $firstProvider = AiCallService::firstOrderedProvider();

        $aiProviderId = null;
        $aiProviderName = null;
        if ($firstProvider) {
            $aiProviderId = $firstProvider->providerId;
            $aiProviderName = $firstProvider->name ?? $firstProvider->providerId;
        }

        // 报告付费开关：完全根据定价配置判断（价格 > 0 视为需要付费）
        $reportRequiresPayment = ['face' => 0, 'mbti' => 0, 'disc' => 0, 'pdp' => 0, 'sbti' => 0];
        foreach (['face', 'mbti', 'disc', 'pdp', 'sbti'] as $k) {
            $key = $k === 'team_analysis' ? 'teamAnalysis' : $k;
            if (isset($pricing[$key]) && (float) $pricing[$key] > 0) {
                $reportRequiresPayment[$k] = 1;
            }
        }
        if ($miniprogramAuditMode) {
            foreach (array_keys($reportRequiresPayment) as $k) {
                $reportRequiresPayment[$k] = 0;
            }
        }

        // 站点信息（网站名称、小程序名称）：供小程序导航栏等展示
        $siteName = '';
        $miniprogramName = '';
        $siteInfo = Db::name('system_config')->where('key', 'site_info')->find();
        if ($siteInfo && !empty($siteInfo['value'])) {
            $val = is_string($siteInfo['value']) ? json_decode($siteInfo['value'], true) : $siteInfo['value'];
            if (is_array($val)) {
                $siteName = (string) ($val['siteName'] ?? '');
                $miniprogramName = (string) ($val['miniprogramName'] ?? '');
            }
        }
        if ($siteName === '' || $miniprogramName === '') {
            $system = Db::name('system_config')->where('key', 'system')->find();
            if ($system && !empty($system['value'])) {
                $val = is_string($system['value']) ? json_decode($system['value'], true) : $system['value'];
                if (is_array($val)) {
                    if ($siteName === '') $siteName = (string) ($val['siteName'] ?? '');
                    if ($miniprogramName === '') $miniprogramName = (string) ($val['miniprogramName'] ?? $val['siteName'] ?? '');
                }
            }
        }
        $siteTitle = $miniprogramName !== '' ? $miniprogramName : ($siteName !== '' ? $siteName : '神仙团队AI性格测试');

        // 小程序文案配置（分析中提示、按钮、报告标题等）
        $textConfig = [
            'analyzingTitle' => '正在分析中',
            'startButtonText' => '30秒测出你的性格',
            'startButtonEnterprise' => '开始面部测试',
            'reportTitle' => '分析报告',
            'aiAnalysisText' => '智能分析'
        ];
        // 先读全局 text_config（enterprise_id=0）
        $tcRow = Db::name('system_config')->where('key', 'text_config')->where('enterprise_id', 0)->find();
        if ($tcRow && !empty($tcRow['value'])) {
            $tcVal = is_string($tcRow['value']) ? json_decode($tcRow['value'], true) : $tcRow['value'];
            if (is_array($tcVal)) {
                $textConfig = array_merge($textConfig, array_intersect_key($tcVal, $textConfig));
            }
        }
        // 企业专属文案 + 小程序名称 覆盖全局（enterprise_id={eid}）
        if ($enterpriseId > 0) {
            $eidTc = Db::name('system_config')->where('key', 'text_config')->where('enterprise_id', $enterpriseId)->find();
            if ($eidTc && !empty($eidTc['value'])) {
                $eidVal = is_string($eidTc['value']) ? json_decode($eidTc['value'], true) : $eidTc['value'];
                if (is_array($eidVal)) {
                    $textConfig = array_merge($textConfig, array_intersect_key($eidVal, $textConfig));
                    if (!empty($eidVal['miniprogramName'])) {
                        $miniprogramName = (string) $eidVal['miniprogramName'];
                        $siteTitle = $miniprogramName;
                    }
                }
            }
        }

        // 与 maintenanceMode 同源：小程序旧逻辑读 reviewMode
        $reviewMode = $maintenanceMode;

        // 企业功能权限：与定价 scope 解耦；库中 permissions 为空/异常时仍返回默认值，避免前端判空异常
        $eidForPermissions = ($enterpriseId > 0) ? $enterpriseId : (($userBoundEnterpriseId ?? 0) > 0 ? $userBoundEnterpriseId : null);
        $enterprisePermissions = null;
        if ($eidForPermissions > 0) {
            try {
                $ent = Db::name('enterprises')->where('id', $eidForPermissions)->field('permissions')->find();
                if (is_array($ent)) {
                    $enterprisePermissions = EnterpriseModel::normalizePermissionsValue($ent['permissions'] ?? null);
                }
            } catch (\Throwable $e) {
                $enterprisePermissions = null;
            }
        }

        // 与已放行的本接口一并下发，避免 Nginx 仅允许 /api/config/runtime 时 /api/mp/tabbar、/api/config/* 子路径 404
        $tabBar = null;
        try {
            $tabBar = MpTabbarService::getPayload();
        } catch (\Throwable $e) {
            $tabBar = ['items' => [], 'version' => 0];
        }

        $aiQuickQuestions = $miniprogramAuditMode
            ? ['mbtiType' => '', 'nickname' => '', 'questions' => []]
            : $this->embeddedAiQuickQuestionsForRuntime($user);

        // 神仙 AI：与 AiChat 一致，不设每日条数上限；内嵌推荐抽检参数与 articles/recommended.display 同源
        $aiDisp = AiChatArticleDisplayService::getSettings();
        $aiChatRuntime = [
            'dailyMessageLimit'     => 0,
            'unlimited'             => true,
            'inlineRecoMinUserTurns'=> (int) ($aiDisp['inlineRecoMinUserTurns'] ?? 2),
            'inlineRecoInterval'    => (int) ($aiDisp['inlineRecoInterval'] ?? 3),
            'inlineRecoRoll'        => (float) ($aiDisp['inlineRecoRoll'] ?? 0.5),
            'inlineRecoIconCount'   => (int) ($aiDisp['inlineRecoIconCount'] ?? 3),
            'inlineRecoIcons'       => $aiDisp['inlineRecoIcons'] ?? ['✨', '💬', '📌'],
        ];

        return success([
            'pricingType' => $pricingType,
            'pricing' => $pricing,
            'aiProviderId' => $aiProviderId,
            'aiProviderName' => $aiProviderName,
            'aiChat' => $aiChatRuntime,
            'reportRequiresPayment' => $reportRequiresPayment,
            'siteName' => $siteName,
            'miniprogramName' => $miniprogramName,
            'siteTitle' => $siteTitle,
            'textConfig' => $textConfig,
            'maintenanceMode' => $maintenanceMode,
            /** 提审模式：隐藏神仙 AI 对话等（与 maintenanceMode 面相审核可独立开关） */
            'miniprogramAuditMode' => $miniprogramAuditMode,
            'reviewMode' => $reviewMode,
            'defaultEnterpriseId' => $defaultEnterpriseId,
            'enterprisePermissions' => $enterprisePermissions,
            'tabBar' => $tabBar,
            'aiQuickQuestions' => $aiQuickQuestions,
            // 与 /api/config/deep-pricing 同源；提审模式下不下发类目（避免虚拟商品购买页）
            'deepPricingPersonal' => ['categories' => $miniprogramAuditMode ? [] : $this->normalizeDeepPricingCategories('personal')],
            'deepPricingEnterprise' => ['categories' => $miniprogramAuditMode ? [] : $this->normalizeDeepPricingCategories('enterprise')],
        ]);
    }

    /**
     * runtime 内嵌快捷问句（与 AiChat::quickQuestions 同源），免再请求易被网关拦截的路径
     *
     * @param array<string, mixed>|null $user 与 runtime() 中解析的 JWT 用户一致
     * @return array{mbtiType: string, nickname: string, questions: string[]}
     */
    private function embeddedAiQuickQuestionsForRuntime(?array $user): array
    {
        $userId = 0;
        if ($user && ($user['source'] ?? '') === 'wechat') {
            $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        }
        try {
            $ctx = AiCallService::fetchUserContextLight($userId);
            $qs  = AiCallService::filterQuickQuestions(AiCallService::quickQuestions($ctx['mbtiType']));

            return [
                'mbtiType'  => $ctx['mbtiType'],
                'nickname'  => $ctx['nickname'],
                'questions' => $qs,
            ];
        } catch (\Throwable $e) {
            $qs = AiCallService::filterQuickQuestions(AiCallService::quickQuestions(''));

            return [
                'mbtiType'  => '',
                'nickname'  => '',
                'questions' => $qs,
            ];
        }
    }

    /**
     * GET api/config/deep-pricing?scope=personal|enterprise
     * 深度服务价格（开通会员页）：个人版与企业版分别返回可配置的类目列表，支持后台新增类目
     */
    public function deepPricing()
    {
        $scope = (string) ($this->request->param('scope', 'personal') ?? 'personal');
        if (miniprogram_audit_mode_on()) {
            return success(['scope' => $scope, 'categories' => []]);
        }
        $categories = $this->normalizeDeepPricingCategories($scope);

        return success(['scope' => $scope, 'categories' => $categories]);
    }

    /**
     * 读取并规范化 deep_personal / deep_enterprise 类目（与 deepPricing、runtime 内嵌共用）
     *
     * @param string $scope personal|enterprise
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDeepPricingCategories(string $scope): array
    {
        $scope = $scope === 'enterprise' ? 'enterprise' : 'personal';
        $type = $scope === 'enterprise' ? 'deep_enterprise' : 'deep_personal';

        $config = PricingConfigModel::where('type', $type)->whereNull('enterpriseId')->find();
        $categories = [];
        if ($config && !empty($config->config)) {
            $raw = $config->config;
            $data = is_array($raw) ? $raw : (array) $raw;
            $categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
        }

        $categories = array_values(array_filter($categories, static function ($row) {
            return is_array($row);
        }));

        foreach ($categories as $idx => &$cat) {
            // features：旧数据只存 featuresText，动态拆成数组
            if (!isset($cat['features']) || !is_array($cat['features'])) {
                if (!empty($cat['featuresText']) && is_string($cat['featuresText'])) {
                    $lines = preg_split('/\r?\n/', $cat['featuresText']);
                    $cat['features'] = array_values(array_filter(array_map('trim', $lines), static function ($s) {
                        return $s !== '';
                    }));
                } else {
                    $cat['features'] = [];
                }
            }
            if (!isset($cat['serviceWechat'])) {
                $cat['serviceWechat'] = '';
            }
            if (!isset($cat['consultWechat'])) {
                $cat['consultWechat'] = '';
            }
            if (!isset($cat['promptText'])) {
                $cat['promptText'] = '';
            }
            if (!isset($cat['successMessage'])) {
                $cat['successMessage'] = '';
            }

            $id = trim((string) ($cat['id'] ?? ''));
            $pk = trim((string) ($cat['productKey'] ?? ''));
            if ($id === '' && $pk !== '') {
                $cat['id'] = $pk;
            } elseif ($id === '') {
                $cat['id'] = ($scope === 'enterprise' ? 'enterprise_cat_' : 'personal_cat_') . $idx;
            }
            if ($pk === '') {
                $cat['productKey'] = (string) $cat['id'];
            }

            $action = (string) ($cat['actionType'] ?? '');
            if ($scope === 'enterprise') {
                if ($action !== 'buy' && trim((string) ($cat['buttonText'] ?? '')) === '') {
                    $cat['buttonText'] = '申请咨询并降低30%成本';
                }
            } else {
                if ($action === 'buy' && trim((string) ($cat['purchaseButtonText'] ?? '')) === '') {
                    $cat['purchaseButtonText'] = '了解自己并付款';
                }
            }
        }
        unset($cat);

        return $categories;
    }
}
