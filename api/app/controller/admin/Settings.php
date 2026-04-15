<?php
namespace app\controller\admin;

use app\BaseController;
use app\common\service\FeishuLeadWebhookService;
use app\common\service\OutboundPushHookService;
use app\model\SystemConfig as SystemConfigModel;
use app\model\User as UserModel;
use think\facade\Request;
use think\facade\Db;

/**
 * 系统设置控制器（普通管理员）
 */
class Settings extends BaseController
{
    /**
     * 获取系统配置
     * @return \think\response\Json
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 验证是否为管理员
        if (!in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        try {
            // 获取当前管理员用户名
            $jwtUsername = $user['username'] ?? null;
            $username = 'admin';
            
            if ($jwtUsername) {
                $currentUser = UserModel::where('username', $jwtUsername)
                    ->whereIn('role', ['admin', 'enterprise_admin'])
                    ->find();
                if ($currentUser) {
                    $username = $currentUser->username;
                } else {
                    $username = $jwtUsername;
                }
            }

            return success([
                'username' => $username
            ]);
        } catch (\Exception $e) {
            return error('获取配置失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取可用字体列表
     * GET /api/v1/admin/settings/fonts
     */
    public function getFonts()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
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
     * GET /api/v1/admin/settings/poster
     * 有 enterpriseId 则读企业专属行，否则读全局（enterprise_id=0）
     */
    public function getPosterConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $eid = (int)($user['enterpriseId'] ?? 0);
        $row = self::getConfig('poster_config', $eid);
        $poster = $row ?: ['bgColor' => '#ffffff', 'bgImage' => '', 'elements' => []];
        return success(['poster' => $poster]);
    }

    /**
     * 保存海报配置
     * PUT /api/v1/admin/settings/poster
     */
    public function updatePosterConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
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
        $eid = (int)($user['enterpriseId'] ?? 0);

        try {
            self::saveConfig('poster_config', $data, $eid, '分销海报可视化配置');
            return success(null, '海报配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 读取配置：key + enterprise_id，有企业专属则取，否则降级到 enterprise_id=0
     */
    private static function getConfig(string $key, int $enterpriseId = 0, bool $fallbackGlobal = false): ?array
    {
        $row = Db::name('system_config')
            ->where('key', $key)
            ->where('enterprise_id', $enterpriseId)
            ->find();
        if ($row && !empty($row['value'])) {
            $val = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
            if (is_array($val)) return $val;
        }
        if ($fallbackGlobal && $enterpriseId > 0) {
            $row = Db::name('system_config')
                ->where('key', $key)
                ->where('enterprise_id', 0)
                ->find();
            if ($row && !empty($row['value'])) {
                $val = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
                if (is_array($val)) return $val;
            }
        }
        return null;
    }

    /**
     * 保存配置：key + enterprise_id，存在则 update，否则 insert
     */
    private static function saveConfig(string $key, array $value, int $enterpriseId = 0, string $description = ''): void
    {
        $now  = time();
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $exists = Db::name('system_config')
            ->where('key', $key)
            ->where('enterprise_id', $enterpriseId)
            ->find();
        if ($exists) {
            Db::name('system_config')
                ->where('key', $key)
                ->where('enterprise_id', $enterpriseId)
                ->update(['value' => $json, 'updatedAt' => $now]);
        } else {
            Db::name('system_config')->insert([
                'key'           => $key,
                'enterprise_id' => $enterpriseId,
                'value'         => $json,
                'description'   => $description,
                'createdAt'     => $now,
                'updatedAt'     => $now,
            ]);
        }
    }

    /**
     * 存客宝默认结构（测评线索共用一个 Key + 上报时机）
     */
    private static function defaultCunkebaoKeysStructure(): array
    {
        return [
            'apiKey'       => '',
            'reportTiming' => 'after_paid',
        ];
    }

    /**
     * @param mixed $raw 已解码的配置或 null（单 Key；兼容旧版 enterprise/personal 及按题型分栏）
     */
    private static function normalizeCunkebaoKeysPayload($raw): array
    {
        $out = self::defaultCunkebaoKeysStructure();
        if (!is_array($raw)) {
            return $out;
        }

        $rtIn = static function ($v) use (&$out): void {
            $rt = (string) ($v ?? '');
            if (in_array($rt, ['after_paid', 'after_test'], true)) {
                $out['reportTiming'] = $rt;
            }
        };

        // 新版：apiKey + reportTiming
        if (array_key_exists('apiKey', $raw)) {
            $out['apiKey'] = isset($raw['apiKey']) ? trim((string) $raw['apiKey']) : '';
            $rtIn($raw['reportTiming'] ?? '');

            return $out;
        }

        // 上一版：enterprise / personal 合二为一（优先企业栏）
        if (array_key_exists('enterprise', $raw) || array_key_exists('personal', $raw)) {
            $e = isset($raw['enterprise']) ? trim((string) $raw['enterprise']) : '';
            $p = isset($raw['personal']) ? trim((string) $raw['personal']) : '';
            $out['apiKey'] = $e !== '' ? $e : $p;
            $rtIn($raw['reportTiming'] ?? '');

            return $out;
        }

        // 旧版：按题型分栏
        $types = ['face', 'pdp', 'disc', 'mbti'];
        foreach ($types as $type) {
            if (!isset($raw[$type]) || !is_array($raw[$type])) {
                continue;
            }
            $row = $raw[$type];
            if ($out['apiKey'] === '' && isset($row['enterprise'])) {
                $x = trim((string) $row['enterprise']);
                if ($x !== '') {
                    $out['apiKey'] = $x;
                }
            }
            if ($out['apiKey'] === '' && isset($row['personal'])) {
                $x = trim((string) $row['personal']);
                if ($x !== '') {
                    $out['apiKey'] = $x;
                }
            }
            if ((string) ($row['reportTiming'] ?? '') === 'after_test') {
                $out['reportTiming'] = 'after_test';
            }
        }

        return $out;
    }

    /**
     * JWT 中的企业 ID（企业管理员必有关联企业）
     */
    private function adminBoundEnterpriseId($user): int
    {
        if (!is_array($user)) {
            return 0;
        }
        $eid = (int) ($user['enterpriseId'] ?? $user['enterprise_id'] ?? 0);

        return $eid > 0 ? $eid : 0;
    }

    /**
     * GET /api/v1/admin/settings/cunkebao-keys
     * 按当前管理员所属企业读取（system_config.enterprise_id = 企业 ID）
     */
    public function getCunkebaoKeys()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $eid = $this->adminBoundEnterpriseId($user);
        if ($eid <= 0) {
            return error('当前账号未关联企业，无法配置存客宝 Key', 403);
        }

        $row = self::getConfig('cunkebao_keys', $eid, false);

        return success([
            'cunkebaoKeys' => self::normalizeCunkebaoKeysPayload($row),
        ]);
    }

    /**
     * PUT /api/v1/admin/settings/cunkebao-keys
     */
    public function updateCunkebaoKeys()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $eid = $this->adminBoundEnterpriseId($user);
        if ($eid <= 0) {
            return error('当前账号未关联企业，无法配置存客宝 Key', 403);
        }

        $raw = $this->request->getContent();
        $input = $raw ? json_decode($raw, true) : [];
        if (!is_array($input)) {
            $input = [];
        }
        $payload = $input['cunkebaoKeys'] ?? [];
        if (!is_array($payload)) {
            return error('存客宝 Key 格式错误', 400);
        }

        $sanitized = self::normalizeCunkebaoKeysPayload($payload);

        try {
            self::saveConfig('cunkebao_keys', $sanitized, $eid, '存客宝 Key（本企业 · 测评类共用）');

            return success($sanitized, '存客宝 Key 已保存');
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
     * 获取小程序配置
     * 读取全局 text_config（enterprise_id=0）作为默认值，再用企业专属行覆盖
     * GET /api/v1/admin/settings/miniprogram
     */
    public function getMiniprogramConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        
        try {
            $eid = (int)($user['enterpriseId'] ?? 0);

            // 全局小程序名称（仅超管可改，此处只读）
            $miniprogramName = '神仙团队AI性格测试';
            $siteInfo = Db::name('system_config')
                ->where('key', 'site_info')
                ->where('enterprise_id', 0)
                ->find();
            if ($siteInfo && !empty($siteInfo['value'])) {
                $val = is_string($siteInfo['value']) ? json_decode($siteInfo['value'], true) : $siteInfo['value'];
                $miniprogramName = (string) ($val['miniprogramName'] ?? $val['siteName'] ?? $miniprogramName);
            }

            $tcDefaults = [
                'analyzingTitle'        => '正在分析中',
                'startButtonText'       => '开始面相测试',
                'startButtonEnterprise' => '开始面部测试',
                'reportTitle'           => '分析报告',
                'aiAnalysisText'        => '智能分析',
            ];

            // 全局文案（enterprise_id=0）作为基础
            $globalTc = self::getConfig('text_config', 0);
            $textConfigData = $globalTc
                ? array_merge($tcDefaults, array_intersect_key($globalTc, $tcDefaults))
                : $tcDefaults;

            // 企业专属文案 + 小程序名称 覆盖
            if ($eid > 0) {
                $eidTc = self::getConfig('text_config', $eid);
                if ($eidTc) {
                    $textConfigData = array_merge($textConfigData, array_intersect_key($eidTc, $tcDefaults));
                    if (!empty($eidTc['miniprogramName'])) {
                        $miniprogramName = (string) $eidTc['miniprogramName'];
                    }
                }
            }

            return success([
                'miniprogramName' => $miniprogramName,
                'textConfig'      => $textConfigData,
            ]);
        } catch (\Exception $e) {
            return error('获取配置失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 飞书获客 Webhook（全局 enterprise_id=0）
     * GET /api/v1/admin/settings/feishu-lead
     */
    public function getFeishuLeadConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $cfg = FeishuLeadWebhookService::getConfig();
        return success([
            'enabled'       => !empty($cfg['enabled']),
            'webhookUrl'    => (string) ($cfg['webhookUrl'] ?? ''),
            'contactPerson' => (string) ($cfg['contactPerson'] ?? '运营'),
        ]);
    }

    /**
     * PUT /api/v1/admin/settings/feishu-lead
     */
    public function updateFeishuLeadConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
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
            'enabled'         => $enabled,
            'webhookUrl'      => $webhookUrl,
            'contactPerson'   => $contactPerson,
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
     * 当前管理员可编辑的作用域：有企业则读写 enterprise_id=本企业，否则读写全平台默认（0，与超管维护同一条）
     * GET /api/v1/admin/settings/push-hook
     */
    public function getPushHookConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $eid = $this->resolveAdminPushHookEnterpriseId();
        $cfg = OutboundPushHookService::getConfig($eid);
        $events = $cfg['events'] ?? [];
        if (!is_array($events)) {
            $events = [];
        }
        $enterpriseName = null;
        if ($eid > 0) {
            $n = Db::name('enterprises')->where('id', $eid)->value('name');
            $enterpriseName = ($n !== null && $n !== '') ? (string) $n : null;
        }
        return success([
            'scope'              => $eid > 0 ? 'enterprise' : 'platform',
            'configEnterpriseId' => $eid,
            'enterpriseName'     => $enterpriseName,
            'enabled'            => !empty($cfg['enabled']),
            'url'                => (string) ($cfg['url'] ?? ''),
            'secret'             => (string) ($cfg['secret'] ?? ''),
            'events'             => $events,
        ]);
    }

    /**
     * PUT /api/v1/admin/settings/push-hook
     */
    public function updatePushHookConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $raw = $this->request->getContent();
        $input = $raw ? json_decode($raw, true) : [];
        if (!is_array($input)) {
            $input = [];
        }
        $enabled = !empty($input['enabled']);
        $url = trim((string) ($input['url'] ?? ''));
        $secret = (string) ($input['secret'] ?? '');
        $eventsRaw = $input['events'] ?? null;
        $events = [];
        if (is_array($eventsRaw)) {
            foreach ($eventsRaw as $ev) {
                $ev = trim((string) $ev);
                if ($ev !== '') {
                    $events[] = $ev;
                }
            }
        }
        if ($enabled && $url !== '' && stripos($url, 'http') !== 0) {
            return error('URL 须以 http(s) 开头', 400);
        }
        $json = json_encode([
            'enabled' => $enabled,
            'url'     => $url,
            'secret'  => $secret,
            'events'  => $events,
        ], JSON_UNESCAPED_UNICODE);
        $now = time();
        $key = OutboundPushHookService::CONFIG_KEY;
        $eid = $this->resolveAdminPushHookEnterpriseId();
        $desc = $eid > 0 ? '通用 HTTP 出站推送 Hook（企业专属）' : '通用 HTTP 出站推送 Hook（全平台默认）';
        $exists = Db::name('system_config')->where('key', $key)->where('enterprise_id', $eid)->find();
        if ($exists) {
            Db::name('system_config')
                ->where('key', $key)
                ->where('enterprise_id', $eid)
                ->update(['value' => $json, 'updatedAt' => $now]);
        } else {
            Db::name('system_config')->insert([
                'key'           => $key,
                'enterprise_id' => $eid,
                'value'         => $json,
                'description'   => $desc,
                'createdAt'     => $now,
                'updatedAt'     => $now,
            ]);
        }
        return success(null, '已保存');
    }

    /**
     * POST /api/v1/admin/settings/push-hook/test
     * 向当前解析到的 URL 发送 hook.ping（不写去重表）
     */
    public function testPushHookConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }
        $ctx = $this->resolveAdminPushHookEnterpriseId();
        $r = OutboundPushHookService::sendTestPing($ctx);

        return success($r, $r['ok'] ? '测试推送已发出' : ($r['message'] ?? '测试失败'));
    }

    /**
     * POST /api/v1/admin/settings/push-hook/test-result
     * 按真实业务数据重放一条 test.result_completed，支持 force=1 强制清去重。
     */
    public function testPushHookTestResult()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $testResultId = (int) Request::param('testResultId', 0);
        $force = (int) Request::param('force', 0) === 1;
        $r = OutboundPushHookService::replayTestResultForDebug($testResultId, $force);

        return success($r, $r['message'] ?? ($r['ok'] ? '已重放推送' : '重放失败'));
    }

    /**
     * 出站 Hook 可编辑行：JWT 或 users 表解析到的本企业 ID；无企业则为 0（与超管共用全平台默认行）
     */
    private function resolveAdminPushHookEnterpriseId(): int
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return 0;
        }
        $eid = (int) ($user['enterpriseId'] ?? 0);
        if ($eid > 0) {
            return $eid;
        }
        $adminId = (int) ($user['userId'] ?? 0);
        if ($adminId > 0) {
            $v = Db::name('users')->where('id', $adminId)->value('enterpriseId');
            if ($v !== null && $v !== '') {
                $x = (int) $v;
                if ($x > 0) {
                    return $x;
                }
            }
        }
        return 0;
    }

    /**
     * 更新小程序配置
     * 写入 text_config 行：enterprise_id={eid}（有企业）或 0（无企业）
     * PUT /api/v1/admin/settings/miniprogram
     */
    public function updateMiniprogramConfig()
    {
        $user = $this->request->user ?? null;
        if (!$user || !in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $rawBody = $this->request->getContent();
        if (empty($rawBody)) {
            $rawBody = file_get_contents('php://input');
        }
        $input = $rawBody ? json_decode($rawBody, true) : null;
        if (!is_array($input)) {
            $input = [
                'miniprogramName' => Request::param('miniprogramName', ''),
                'textConfig'      => Request::param('textConfig', []),
            ];
        }

        $miniprogramName = trim((string) ($input['miniprogramName'] ?? ''));
        $textConfig      = $input['textConfig'] ?? [];

        if ($miniprogramName === '') {
            return error('小程序名称不能为空', 400);
        }

        $tcKeys = ['analyzingTitle', 'startButtonText', 'startButtonEnterprise', 'reportTitle', 'aiAnalysisText'];
        $tcDefaults = [
            'analyzingTitle'        => '正在分析中',
            'startButtonText'       => '开始面相测试',
            'startButtonEnterprise' => '开始面部测试',
            'reportTitle'           => '分析报告',
            'aiAnalysisText'        => '智能分析',
        ];
        $tcData  = is_array($textConfig) ? array_intersect_key($textConfig, array_flip($tcKeys)) : [];
        $tcMerge = array_merge($tcDefaults, $tcData);
        $eid     = (int)($user['enterpriseId'] ?? 0);

        try {
            // eid=0：更新 site_info 的小程序名称（全局）
            if ($eid === 0) {
                $siteRow = Db::name('system_config')->where('key', 'site_info')->where('enterprise_id', 0)->find();
                $siteInfo = $siteRow && !empty($siteRow['value'])
                    ? (is_string($siteRow['value']) ? json_decode($siteRow['value'], true) : $siteRow['value'])
                    : [];
                $siteInfo = is_array($siteInfo) ? $siteInfo : [];
                $siteInfo['miniprogramName'] = $miniprogramName;
                $siteInfo['siteName'] = $siteInfo['siteName'] ?? $miniprogramName;
                $siteInfo['updatedAt'] = time();
                self::saveConfig('site_info', $siteInfo, 0, '站点信息');
            } else {
                // 企业专属：把 miniprogramName 一并写入 text_config
                $tcMerge['miniprogramName'] = $miniprogramName;
            }

            // 统一写到 text_config（企业行已含 miniprogramName，全局行不含）
            self::saveConfig('text_config', $tcMerge, $eid, $eid > 0 ? "小程序文案配置（企业{$eid}）" : '小程序文案配置（全局）');
            return success([
                'miniprogramName' => $miniprogramName,
                'textConfig'      => $tcMerge,
            ], '小程序配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新管理员账户信息
     * @return \think\response\Json
     */
    public function updateCredentials()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 验证是否为管理员
        if (!in_array($user['role'], ['admin', 'enterprise_admin'])) {
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
            // 优先使用JWT中的username来查找用户
            $jwtUsername = $user['username'] ?? null;
            
            if (empty($jwtUsername)) {
                return error('无法获取用户信息，请重新登录', 400);
            }
            
            // 直接通过username查找用户
            $userModel = UserModel::where('username', $jwtUsername)
                ->whereIn('role', ['admin', 'enterprise_admin'])
                ->find();
            
            if (!$userModel) {
                return error('用户不存在，请检查登录状态', 404);
            }

            // 如果要修改密码，需要验证当前密码
            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    return error('请输入当前密码', 400);
                }

                if ($newPassword !== $confirmPassword) {
                    return error('两次输入的密码不一致', 400);
                }

                // 验证当前密码（User 模型已有原始加密密码）
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
}

