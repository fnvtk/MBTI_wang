<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\common\service\FeishuLeadWebhookService;
use app\model\SystemConfig as SystemConfigModel;
use app\model\User as UserModel;
use app\model\Enterprise as EnterpriseModel;
use think\facade\Request;
use think\facade\Db;

/**
 * 系统设置控制器（超管专用）
 */
class Settings extends BaseController
{
    /**
     * 获取系统配置
     * @return \think\response\Json
     */
    public function index()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            // 获取系统配置（全局 enterprise_id=0）
            $systemConfig = SystemConfigModel::where('key', 'system')->where('enterprise_id', 0)->find();
            $notificationConfig = SystemConfigModel::where('key', 'notification')->where('enterprise_id', 0)->find();
            $promptsConfig = SystemConfigModel::where('key', 'prompts')->where('enterprise_id', 0)->find();
            $reportRequiresPaymentConfig = SystemConfigModel::where('key', 'report_requires_payment')->where('enterprise_id', 0)->find();
            $textConfigModel = SystemConfigModel::where('key', 'text_config')->where('enterprise_id', 0)->find();
            $reviewModeConfig = SystemConfigModel::where('key', 'review_mode')->where('enterprise_id', 0)->find();

            // 获取当前超管用户名（直接使用JWT中的username）
            $jwtUsername = $user['username'] ?? null;
            $username = 'admin';
            
            if ($jwtUsername) {
                $currentUser = UserModel::where('username', $jwtUsername)
                    ->where('role', 'superadmin')
                    ->find();
                if ($currentUser) {
                    $username = $currentUser->username;
                } else {
                    // 如果找不到用户，使用JWT中的username
                    $username = $jwtUsername;
                }
            }

            $systemDefault = [
                'siteName' => '神仙团队AI性格测试',
                'siteDescription' => '专业的AI性格测试平台',
                'miniprogramName' => '神仙团队AI性格测试',
                'maintenanceMode' => false,
                'maxTestsPerDay' => 100,
                'trialTestCount' => 10,
                'defaultEnterpriseId' => null,
            ];
            $systemOut = $systemDefault;
            if ($systemConfig && $systemConfig->value !== null && $systemConfig->value !== '') {
                $raw = $systemConfig->value;
                $decoded = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
                if (is_array($decoded)) {
                    $systemOut = array_merge($systemDefault, $decoded);
                }
            }
            // 审核模式唯一口径：system.maintenanceMode（布尔）。兼容旧版 review_mode.enabled
            $maint = false;
            if (isset($systemOut['maintenanceMode'])) {
                $maint = (bool) $systemOut['maintenanceMode'];
            }
            if (!$maint && $reviewModeConfig && !empty($reviewModeConfig->value)) {
                $rv = $reviewModeConfig->value;
                if (is_string($rv)) {
                    $rv = json_decode($rv, true);
                }
                if (is_array($rv) && !empty($rv['enabled'])) {
                    $maint = true;
                }
            }
            $systemOut['maintenanceMode'] = $maint;

            // 与前端 el-option 的 number value 对齐，避免类型不一致导致下拉不反显
            if (array_key_exists('defaultEnterpriseId', $systemOut)) {
                $de = $systemOut['defaultEnterpriseId'];
                if ($de === '' || $de === null || (int) $de <= 0) {
                    $systemOut['defaultEnterpriseId'] = null;
                } else {
                    $systemOut['defaultEnterpriseId'] = (int) $de;
                }
            }

            return success([
                'system' => $systemOut,
                'reviewMode' => ['enabled' => $maint],
                'notification' => $notificationConfig ? $notificationConfig->value : [
                    'emailNotification' => true,
                    'lowBalanceAlert' => true,
                    'lowBalanceThreshold' => 1000,
                    'newEnterpriseNotify' => true
                ],
                'prompts' => $promptsConfig && !empty($promptsConfig->value) ? $promptsConfig->value : [
                    'faceAnalyze' => '{"mbti":"四字母如INTJ","pdp":"老虎/孔雀/无尾熊/猫头鹰/变色龙其一","disc":"D/I/S/C其一","overview":"一段50字以内的综合描述","faceAnalysis":"面相特点简短描述"}',
                    'reportSummary' => ''
                ],
                'reportRequiresPayment' => $reportRequiresPaymentConfig && !empty($reportRequiresPaymentConfig->value) ? $reportRequiresPaymentConfig->value : ['face' => 1, 'mbti' => 0, 'disc' => 0, 'pdp' => 0],
                'textConfig' => $textConfigModel && !empty($textConfigModel->value) ? $textConfigModel->value : [
                    'analyzingTitle' => '正在分析中',
                    'startButtonText' => '开始面相测试',
                    'startButtonEnterprise' => '开始面部测试',
                    'reportTitle' => '分析报告',
                    'aiAnalysisText' => '智能分析'
                ],
                'username' => $username
            ]);
        } catch (\Exception $e) {
            return error('获取配置失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新系统配置
     * @return \think\response\Json
     */
    public function updateSystem()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 前端 axios 发 JSON body，用 getContent 解析更可靠
        $input = json_decode($this->request->getContent(), true);
        if (!is_array($input)) {
            $input = [];
        }

        $allowedKeys = ['siteName', 'siteDescription', 'miniprogramName', 'maintenanceMode', 'maxTestsPerDay', 'trialTestCount', 'defaultEnterpriseId'];
        $data = array_intersect_key($input, array_flip($allowedKeys));
        // 兼容 fallback：JSON 解析失败时尝试 Request::only
        if (empty($data)) {
            $data = Request::only($allowedKeys);
        }
        if (array_key_exists('maintenanceMode', $data)) {
            $data['maintenanceMode'] = filter_var($data['maintenanceMode'], FILTER_VALIDATE_BOOLEAN);
        }
        // 默认企业：0 或空视为不启用
        if (array_key_exists('defaultEnterpriseId', $data)) {
            $de = $data['defaultEnterpriseId'];
            if ($de === '' || $de === null) {
                $data['defaultEnterpriseId'] = null;
            } else {
                $deInt = (int) $de;
                if ($deInt <= 0) {
                    $data['defaultEnterpriseId'] = null;
                } else {
                    $exists = EnterpriseModel::where('id', $deInt)->find();
                    if (!$exists) {
                        return error('所选默认企业不存在', 400);
                    }
                    $data['defaultEnterpriseId'] = $deInt;
                }
            }
        }
        $textConfig = $input['textConfig'] ?? (Request::param('textConfig') ?: []);

        try {
            // 查找或创建全局配置（enterprise_id=0）
            $config = SystemConfigModel::where('key', 'system')->where('enterprise_id', 0)->find();
            if (!$config) {
                $config = new SystemConfigModel();
                $config->key = 'system';
                $config->enterprise_id = 0;
                $config->description = '系统基础配置';
            }
            // 合并保存，避免仅提交部分字段时丢失其它键（如 defaultEnterpriseId）
            $oldVal = $config->value;
            $oldArr = is_array($oldVal) ? $oldVal : (is_string($oldVal) ? (json_decode($oldVal, true) ?: []) : []);
            if (!is_array($oldArr)) {
                $oldArr = [];
            }
            $config->value = array_merge($oldArr, $data);
            $config->save();

            // 更新站点信息
            $this->updateSiteInfo($data);

            // 保存全局小程序文案配置（enterprise_id=0）
            if (is_array($textConfig)) {
                $tcKeys = ['analyzingTitle', 'startButtonText', 'startButtonEnterprise', 'reportTitle', 'aiAnalysisText'];
                $tcData = array_intersect_key($textConfig, array_flip($tcKeys));
                $tcDefaults = ['analyzingTitle' => '正在分析中', 'startButtonText' => '开始面相测试', 'startButtonEnterprise' => '开始面部测试', 'reportTitle' => '分析报告', 'aiAnalysisText' => '智能分析'];
                $tcConfig = SystemConfigModel::where('key', 'text_config')->where('enterprise_id', 0)->find();
                if (!$tcConfig) {
                    $tcConfig = new SystemConfigModel();
                    $tcConfig->key = 'text_config';
                    $tcConfig->enterprise_id = 0;
                    $tcConfig->description = '小程序文案配置（全局）';
                }
                $tcConfig->value = array_merge($tcDefaults, $tcData);
                $tcConfig->save();
            }

            return success($config->value, '系统配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新「报告需付费」配置：哪些测试类型需付费后才显示完整报告
     * PUT body: { "face": 1, "mbti": 0, "disc": 0, "pdp": 0 }（1=需付费解锁完整，0=免费完整）
     * @return \think\response\Json
     */
    public function updateReportRequiresPayment()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $data = Request::param('reportRequiresPayment', Request::param('data', []));
        if (!is_array($data)) {
            return error('配置格式错误', 400);
        }

        $defaults = ['face' => 1, 'mbti' => 0, 'disc' => 0, 'pdp' => 0];
        $value = array_merge($defaults, array_intersect_key($data, array_flip(['face', 'mbti', 'disc', 'pdp'])));
        $value = array_map(function ($v) { return (int) $v ? 1 : 0; }, $value);

        try {
            $config = SystemConfigModel::where('key', 'report_requires_payment')->where('enterprise_id', 0)->find();
            if (!$config) {
                $config = new SystemConfigModel();
                $config->key = 'report_requires_payment';
                $config->enterprise_id = 0;
                $config->description = '哪些测试类型需付费后才显示完整报告:1需付费0免费';
            }
            $config->value = $value;
            $config->save();
            return success($config->value, '报告付费开关已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新提示词配置
     * @return \think\response\Json
     */
    public function updatePrompts()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $data = Request::param('prompts', []);
        if (!is_array($data)) {
            return error('提示词配置格式错误', 400);
        }

        try {
            $config = SystemConfigModel::where('key', 'prompts')->where('enterprise_id', 0)->find();
            if (!$config) {
                $config = new SystemConfigModel();
                $config->key = 'prompts';
                $config->enterprise_id = 0;
                $config->description = '系统提示词配置（如面相分析、企业简历等）';
            }
            $config->value = $data;
            $config->save();
            return success($config->value, '提示词配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新通知配置
     * @return \think\response\Json
     */
    public function updateNotification()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $data = Request::only([
            'emailNotification', 'lowBalanceAlert', 
            'lowBalanceThreshold', 'newEnterpriseNotify'
        ]);

        try {
            $config = SystemConfigModel::where('key', 'notification')->where('enterprise_id', 0)->find();
            if (!$config) {
                $config = new SystemConfigModel();
                $config->key = 'notification';
                $config->enterprise_id = 0;
                $config->description = '通知与告警配置';
            }
            $config->value = $data;
            $config->save();

            return success($config->value, '通知配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 飞书获客 Webhook（与 admin 共用配置）
     */
    public function getFeishuLeadConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }
        $cfg = FeishuLeadWebhookService::getConfig();
        return success([
            'enabled'       => !empty($cfg['enabled']),
            'webhookUrl'    => (string) ($cfg['webhookUrl'] ?? ''),
            'contactPerson' => (string) ($cfg['contactPerson'] ?? '运营'),
        ]);
    }

    public function updateFeishuLeadConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }
        $raw = $this->request->getContent();
        $input = $raw ? json_decode($raw, true) : [];
        if (!is_array($input)) {
            $input = [];
        }
        $enabled = !empty($input['enabled']);
        $webhookUrl = trim((string) ($input['webhookUrl'] ?? ''));
        $contactPerson = trim((string) ($input['contactPerson'] ?? '运营'));
        if ($contactPerson === '') {
            $contactPerson = '运营';
        }
        if ($enabled && $webhookUrl !== '' && stripos($webhookUrl, 'http') !== 0) {
            return error('Webhook 须以 http(s) 开头', 400);
        }
        $json = json_encode([
            'enabled'       => $enabled,
            'webhookUrl'    => $webhookUrl,
            'contactPerson' => $contactPerson,
        ], JSON_UNESCAPED_UNICODE);
        $now = time();
        $key = FeishuLeadWebhookService::CONFIG_KEY;
        $exists = Db::name('system_config')->where('key', $key)->where('enterprise_id', 0)->find();
        if ($exists) {
            Db::name('system_config')
                ->where('key', $key)
                ->where('enterprise_id', 0)
                ->update(['value' => $json, 'updatedAt' => $now]);
        } else {
            Db::name('system_config')->insert([
                'key'           => $key,
                'enterprise_id' => 0,
                'value'         => $json,
                'description'   => '飞书获客 Webhook',
                'createdAt'     => $now,
                'updatedAt'     => $now,
            ]);
        }
        return success(null, '已保存');
    }

    /**
     * 更新超管账户信息
     * @return \think\response\Json
     */
    public function updateCredentials()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 兼容 axios JSON PUT 与表单提交
        $rawBody = $this->request->getContent();
        if (empty($rawBody)) {
            $rawBody = file_get_contents('php://input');
        }
        $input = $rawBody ? json_decode($rawBody, true) : null;
        if (!is_array($input)) {
            $input = [];
        }

        $username        = trim((string)($input['username'] ?? Request::param('username', '')));
        $currentPassword = (string)($input['currentPassword'] ?? Request::param('currentPassword', ''));
        $newPassword     = (string)($input['newPassword'] ?? Request::param('newPassword', ''));
        $confirmPassword = (string)($input['confirmPassword'] ?? Request::param('confirmPassword', ''));

        if (empty($username)) {
            return error('用户名不能为空', 400);
        }

        try {
            // 优先使用JWT中的username来查找用户（最可靠的方式）
            $jwtUsername = $user['username'] ?? null;
            
            if (empty($jwtUsername)) {
                \think\facade\Log::error('JWT中缺少username', [
                    'user' => $user,
                    'requestUserId' => $this->request->userId ?? null
                ]);
                return error('无法获取用户信息，请重新登录', 400);
            }
            
            // 直接通过username查找用户
            $userModel = UserModel::where('username', $jwtUsername)
                ->where('role', 'superadmin')
                ->find();
            
            if (!$userModel) {
                // 添加调试信息
                \think\facade\Log::error('用户不存在', [
                    'jwtUsername' => $jwtUsername,
                    'user' => $user,
                    'requestUserId' => $this->request->userId ?? null,
                    'requestUsername' => $username
                ]);
                return error('用户不存在，请检查登录状态', 404);
            }
            
            // 验证当前用户是否为超级管理员（双重验证）
            if ($userModel->role !== 'superadmin') {
                \think\facade\Log::error('用户角色不正确', [
                    'userId' => $userModel->id,
                    'role' => $userModel->role
                ]);
                return error('无权限修改此账户', 403);
            }

            // 如果要修改密码，需要验证当前密码
            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    return error('请输入当前密码', 400);
                }

                if ($newPassword !== $confirmPassword) {
                    return error('两次输入的密码不一致', 400);
                }

                // 验证当前密码（User 模型中 password 字段已是加密值）
                if (!password_verify($currentPassword, $userModel->password)) {
                    return error('当前密码错误', 400);
                }

                // 更新密码：传入明文，交由 User 模型的 setPasswordAttr 自动加密
                $userModel->password = $newPassword;
            }

            // 更新用户名
            if ($username !== $userModel->username) {
                // 检查用户名是否已存在（排除当前用户）
                $exists = UserModel::where('username', $username)
                    ->where('id', '<>', $userModel->id)
                    ->find();
                
                if ($exists) {
                    return error('用户名已存在', 400);
                }

                $userModel->username = $username;
            }

            $userModel->save();

            return success([
                'username' => $userModel->username
            ], '账户信息已更新');
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新审核模式：仅写入 system 顶层 maintenanceMode（true/false），与 runtime 一致。
     * PUT body: { "enabled": true } 或 { "maintenanceMode": true }（二者等价，任一即可）
     */
    public function updateReviewMode()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $input = json_decode($this->request->getContent(), true);
        if (!is_array($input)) {
            $input = Request::param();
        }
        $on = false;
        if (array_key_exists('maintenanceMode', $input)) {
            $on = !empty($input['maintenanceMode']);
        } elseif (array_key_exists('enabled', $input)) {
            $on = !empty($input['enabled']);
        }

        try {
            $config = SystemConfigModel::where('key', 'system')->where('enterprise_id', 0)->find();
            if (!$config) {
                $config = new SystemConfigModel();
                $config->key = 'system';
                $config->enterprise_id = 0;
                $config->description = '系统基础配置';
            }
            $oldVal = $config->value;
            $oldArr = is_array($oldVal) ? $oldVal : (is_string($oldVal) ? (json_decode($oldVal, true) ?: []) : []);
            if (!is_array($oldArr)) {
                $oldArr = [];
            }
            $oldArr['maintenanceMode'] = (bool) $on;
            $config->value = $oldArr;
            $config->save();

            return success([
                'maintenanceMode' => (bool) $on,
                'enabled'         => (bool) $on,
            ], '审核模式已' . ($on ? '开启' : '关闭'));
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取可用字体列表
     * GET /api/v1/superadmin/settings/fonts
     */
    public function getFonts()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }
        $fonts = \app\common\service\PosterService::getAvailableFonts();
        return success([
            'fonts'    => $fonts,
            'fontDir'  => root_path() . 'public/fonts/',
            'dirExist' => is_dir(root_path() . 'public/fonts/'),
        ]);
    }

    /**
     * 获取海报配置
     * GET /api/v1/superadmin/settings/poster
     */
    public function getPosterConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $row = Db::name('system_config')->where('key', 'poster_config')->where('enterprise_id', 0)->find();
        $raw = $row['value'] ?? null;
        $poster = self::decodeJsonSafe($raw) ?: [
            'bgColor'  => '#ffffff',
            'bgImage'  => '',
            'elements' => []
        ];
        return success(['poster' => $poster]);
    }

    /**
     * 保存海报配置
     * PUT /api/v1/superadmin/settings/poster
     */
    public function updatePosterConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $input = json_decode($this->request->getContent(), true);
        if (!is_array($input)) {
            $input = [];
        }
        $data = [
            'bgColor'  => $input['bgColor'] ?? '#ffffff',
            'bgImage'  => $input['bgImage'] ?? '',
            'elements' => $input['elements'] ?? []
        ];
        $jsonValue = json_encode($data, JSON_UNESCAPED_UNICODE);

        try {
            $now    = time();
            $exists = Db::name('system_config')->where('key', 'poster_config')->where('enterprise_id', 0)->find();
            if ($exists) {
                Db::name('system_config')
                    ->where('key', 'poster_config')
                    ->where('enterprise_id', 0)
                    ->update(['value' => $jsonValue, 'updatedAt' => $now]);
            } else {
                Db::name('system_config')->insert([
                    'key'           => 'poster_config',
                    'enterprise_id' => 0,
                    'value'         => $jsonValue,
                    'description'   => '分销海报可视化配置（全局）',
                    'createdAt'     => $now,
                    'updatedAt'     => $now,
                ]);
            }
            return success(null, '海报配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 安全解码 JSON（处理可能的多重编码）
     */
    private static function decodeJsonSafe($raw): ?array
    {
        if (!$raw) return null;
        $val = $raw;
        for ($i = 0; $i < 5 && is_string($val); $i++) {
            $decoded = json_decode($val, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) break;
            $val = $decoded;
        }
        return is_array($val) ? $val : null;
    }

    /**
     * 更新站点信息
     * 当系统配置中的siteName或siteDescription修改时，同步更新站点信息
     */
    private function updateSiteInfo($systemData)
    {
        try {
            $siteConfig = SystemConfigModel::where('key', 'site_info')->where('enterprise_id', 0)->find();
            $siteInfo = [
                'siteName'        => $systemData['siteName'] ?? '',
                'siteDescription' => $systemData['siteDescription'] ?? '',
                'miniprogramName' => $systemData['miniprogramName'] ?? '',
                'updatedAt'       => time()
            ];
            if (!$siteConfig) {
                $siteConfig = new SystemConfigModel();
                $siteConfig->key = 'site_info';
                $siteConfig->enterprise_id = 0;
                $siteConfig->description = '站点信息配置';
            }
            $siteConfig->value = $siteInfo;
            $siteConfig->save();

            // 也可以更新其他相关的配置或缓存
            // 例如：清除缓存、更新.env文件等
            
        } catch (\Exception $e) {
            // 站点信息更新失败不影响系统配置保存
            \think\facade\Log::error('更新站点信息失败：' . $e->getMessage());
        }
    }
}

