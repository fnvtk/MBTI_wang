<?php
namespace app\controller\api;

use app\BaseController;
use app\model\Enterprise as EnterpriseModel;
use app\model\PricingConfig as PricingConfigModel;
use app\model\AiProvider as AiProviderModel;
use app\common\service\JwtService;
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
        $defaultEnterpriseId = null;
        $systemRow = Db::name('system_config')->where('key', 'system')->find();
        if ($systemRow && !empty($systemRow['value'])) {
            $sysValEarly = is_string($systemRow['value']) ? json_decode($systemRow['value'], true) : $systemRow['value'];
            if (is_array($sysValEarly)) {
                if (!empty($sysValEarly['maintenanceMode'])) {
                    $maintenanceMode = true;
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

        // 超管 AI 配置：第一个 enabled=1、visible=1（显示）且 apiKey 非空；隐藏的服务商不参与选用
        $firstProvider = AiProviderModel::where('enabled', 1)
            ->whereRaw('(visible IS NULL OR visible = 1)')
            ->whereRaw('(apiKey IS NOT NULL AND LENGTH(TRIM(apiKey)) > 0)')
            ->order('id', 'asc')
            ->find();

        $aiProviderId = null;
        $aiProviderName = null;
        if ($firstProvider) {
            $aiProviderId = $firstProvider->providerId;
            $aiProviderName = $firstProvider->name ?? $firstProvider->providerId;
        }

        // 报告付费开关：完全根据定价配置判断（价格 > 0 视为需要付费）
        $reportRequiresPayment = ['face' => 0, 'mbti' => 0, 'disc' => 0, 'pdp' => 0];
        foreach (['face', 'mbti', 'disc', 'pdp'] as $k) {
            $key = $k === 'team_analysis' ? 'teamAnalysis' : $k;
            if (isset($pricing[$key]) && (float) $pricing[$key] > 0) {
                $reportRequiresPayment[$k] = 1;
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
            'startButtonText' => '开始面相测试',
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

        return success([
            'pricingType' => $pricingType,
            'pricing' => $pricing,
            'aiProviderId' => $aiProviderId,
            'aiProviderName' => $aiProviderName,
            'reportRequiresPayment' => $reportRequiresPayment,
            'siteName' => $siteName,
            'miniprogramName' => $miniprogramName,
            'siteTitle' => $siteTitle,
            'textConfig' => $textConfig,
            'maintenanceMode' => $maintenanceMode,
            'reviewMode' => $reviewMode,
            'defaultEnterpriseId' => $defaultEnterpriseId,
            'enterprisePermissions' => $enterprisePermissions,
        ]);
    }

    /**
     * GET api/config/deep-pricing?scope=personal|enterprise
     * 深度服务价格（开通会员页）：个人版与企业版分别返回可配置的类目列表，支持后台新增类目
     */
    public function deepPricing()
    {
        $scope = (string) ($this->request->param('scope', 'personal') ?? 'personal');
        $type = $scope === 'enterprise' ? 'deep_enterprise' : 'deep_personal';

        $config = PricingConfigModel::where('type', $type)->whereNull('enterpriseId')->find();
        $categories = [];
        if ($config && !empty($config->config)) {
            $raw = $config->config;
            $data = is_array($raw) ? $raw : (array) $raw;
            $categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];

            // 兼容旧数据：补全可能缺失的字段，确保前端始终能读到完整结构
            foreach ($categories as &$cat) {
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
                // serviceWechat（客服微信）：展示给用户的微信号
                if (!isset($cat['serviceWechat'])) {
                    $cat['serviceWechat'] = '';
                }
                // consultWechat（存客宝KEY）：旧类目可能没有该字段，补空字符串
                if (!isset($cat['consultWechat'])) {
                    $cat['consultWechat'] = '';
                }
                // promptText：同样补全
                if (!isset($cat['promptText'])) {
                    $cat['promptText'] = '';
                }
                // successMessage：成功提示词，补全
                if (!isset($cat['successMessage'])) {
                    $cat['successMessage'] = '';
                }
            }
            unset($cat);
        }

        return success(['scope' => $scope, 'categories' => $categories]);
    }
}
