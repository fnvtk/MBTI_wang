<?php
namespace app\controller\api;

use app\BaseController;
use app\model\User as UserModel;
use app\model\WechatUser;
use app\common\service\JwtService;
use app\common\service\WechatService;
use think\facade\Request;
use think\facade\Db;

/**
 * 前端用户认证API控制器
 */
class Auth extends BaseController
{
    /**
     * 用户登录（前端）
     * @return \think\response\Json
     */
    public function login()
    {
        $username = Request::param('username', '');
        $password = Request::param('password', '');

        if (empty($username) || empty($password)) {
            return error('用户名和密码不能为空', 400);
        }

        // 注意：mbti_users表只存储管理员和超管，前端用户需要存储在单独的表中
        // 这里暂时返回错误，需要创建前端用户表后再实现
        return error('前端用户登录功能暂未实现，请联系管理员', 501);
        
        // 查找用户（如果将来有前端用户表，使用以下代码）
        // $user = Db::name('frontend_users')
        //     ->where('username', $username)
        //     ->find();

        if (!$user) {
            return error('用户名或密码错误', 401);
        }

        // 验证密码
        if (!password_verify($password, $user['password'])) {
            return error('用户名或密码错误', 401);
        }

        // 检查状态
        if ($user['status'] != 1) {
            return error('账号已被禁用', 403);
        }

        // 更新登录信息（使用时间戳，驼峰命名）
        Db::name('users')
            ->where('id', $user['id'])
            ->update([
                'lastLoginTime' => time(),
                'lastLoginIp' => Request::ip(),
                'updatedAt' => time()
            ]);

        // 生成Token
        $payload = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];

        $token = JwtService::generateToken($payload);

        return success([
            'token' => $token,
            'expires_in' => config('jwt.expire'),
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'] ?? $user['username'],
                'email' => $user['email'] ?? '',
                'avatar' => $user['avatar'] ?? '',
                'role' => $user['role']
            ]
        ], '登录成功');
    }

    /**
     * 用户注册（前端）
     * @return \think\response\Json
     */
    public function register()
    {
        $data = Request::post();
        
        // 数据验证
        if (empty($data['username']) || empty($data['password'])) {
            return error('用户名和密码不能为空', 400);
        }

        // 检查用户名是否已存在
        if (Db::name('users')->where('username', $data['username'])->find()) {
            return error('用户名已存在', 400);
        }

        // 检查邮箱是否已存在
        if (!empty($data['email']) && Db::name('users')->where('email', $data['email'])->find()) {
            return error('邮箱已被注册', 400);
        }

        // 注意：mbti_users表只存储管理员和超管，前端用户需要存储在单独的表中
        // 这里暂时返回错误，需要创建前端用户表后再实现
        return error('前端用户注册功能暂未实现，请联系管理员', 501);
        
        // 创建用户（如果将来有前端用户表，使用以下代码）
        // $userId = Db::name('frontend_users')->insertGetId([
        //     'username' => $data['username'],
        //     'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        //     'email' => $data['email'] ?? '',
        //     'status' => 1,
        //     'created_at' => time(),
        //     'updated_at' => time()
        // ]);

        $user = Db::name('users')->where('id', $userId)->find();
        unset($user['password']);

        return success($user, '注册成功');
    }

    /**
     * 获取当前用户信息（需要认证）
     * 小程序用户（source=wechat）从 mbti_wechat_users 读取，否则从 mbti_users 读取
     * @return \think\response\Json
     */
    public function me()
    {
        $user = $this->request->user ?? null;

        if (!$user) {
            return error('未登录', 401);
        }

        $source = $user['source'] ?? null;
        $userId = $user['user_id'] ?? $user['userId'] ?? null;

        if ($source === 'wechat' && $userId) {
            $wechatUser = Db::name('wechat_users')->where('id', $userId)->find();
            if (!$wechatUser) {
                return error('用户不存在', 404);
            }
            unset($wechatUser['sessionKey'], $wechatUser['openid']);
            $wechatUser['avatarUrl'] = $wechatUser['avatar'] ?? '';
            $eid = isset($wechatUser['enterpriseId']) && $wechatUser['enterpriseId'] !== '' && $wechatUser['enterpriseId'] !== null ? (int) $wechatUser['enterpriseId'] : null;
            $wechatUser['hasEnterprise'] = $eid > 0;
            $wechatUser['enterpriseId'] = $eid;
            return success($wechatUser);
        }

        $userModel = Db::name('users')->where('id', $userId)->find();
        if (!$userModel) {
            return error('用户不存在', 404);
        }

        unset($userModel['password']);

        return success($userModel);
    }

    /**
     * 退出登录（需要认证）
     * @return \think\response\Json
     */
    public function logout()
    {
        $user = $this->request->user ?? null;
        
        if ($user && isset($user['user_id'])) {
            JwtService::deleteToken((int) $user['user_id'], $user['source'] ?? null);
        }

        return success(null, '退出成功');
    }

    /**
     * 刷新Token
     * @return \think\response\Json
     */
    public function refresh()
    {
        $token = JwtService::getTokenFromRequest($this->request);
        
        if (!$token) {
            return error('未提供Token', 401);
        }

        $newToken = JwtService::refreshToken($token);
        
        if (!$newToken) {
            return error('Token无效或已过期', 401);
        }

        return success([
            'token' => $newToken,
            'expires_in' => config('jwt.expire')
        ], '刷新成功');
    }

    /**
     * 微信小程序登录：code 换 openid，查/建用户，返回 token 与用户信息
     * POST api/auth/wechat  body: { "code": "xxx" }
     * @return \think\response\Json
     */
    public function wechatLogin()
    {
        $code = Request::param('code', '');
        if ($code === '') {
            return error('缺少 code', 400);
        }

        $session = WechatService::jscode2session($code);
        if (isset($session['errcode']) && $session['errcode'] !== 0) {
            return error($session['errmsg'] ?? '微信登录失败', 400);
        }

        $openid = $session['openid'];
        //$openid = 'oucCB15WDKCdwfNo-fpyS72iY5IQ';
        $sessionKey = $session['session_key'] ?? '';
        $unionid = $session['unionid'] ?? null;

        $wechatUser = Db::name('wechat_users')->where('openid', $openid)->find();
        $now = time();
        $ip = Request::ip();

        if ($wechatUser) {
            Db::name('wechat_users')->where('id', $wechatUser['id'])->update([
                'sessionKey'  => $sessionKey,
                'unionid'     => $unionid,
                'lastLoginAt' => $now,
                'lastLoginIp' => $ip,
                'updatedAt'   => $now,
            ]);
            $wechatUser = Db::name('wechat_users')->where('id', $wechatUser['id'])->find();
        } else {
            $id = Db::name('wechat_users')->insertGetId([
                'openid'      => $openid,
                'unionid'     => $unionid,
                'sessionKey'  => $sessionKey,
                'nickname'    => null,
                'avatar'     => null,
                'phone'      => null,
                'gender'     => 0,
                'country'    => null,
                'province'   => null,
                'city'      => null,
                'status'    => 1,
                'lastLoginAt' => $now,
                'lastLoginIp' => $ip,
                'createdAt'  => $now,
                'updatedAt'  => $now,
            ]);
            $wechatUser = Db::name('wechat_users')->where('id', $id)->find();
        }

        if (($wechatUser['status'] ?? 1) != 1) {
            return error('账号已被禁用', 403);
        }

        $payload = [
            'user_id' => (int) $wechatUser['id'],
            'source'  => 'wechat',
        ];
        $token = JwtService::generateToken($payload);

        $userId = (int) $wechatUser['id'];
        // 企业绑定取自 wechat_users.enterpriseId（企业分享测试链接时更新，个人分享不更新）
        $enterpriseId = isset($wechatUser['enterpriseId']) && $wechatUser['enterpriseId'] !== '' && $wechatUser['enterpriseId'] !== null
            ? (int) $wechatUser['enterpriseId']
            : null;
        $hasEnterprise = $enterpriseId > 0;

        $out = [
            'id'        => $userId,
            'openid'    => $openid,
            'nickname'  => $wechatUser['nickname'] ?? '',
            'avatar'    => $wechatUser['avatar'] ?? '',
            'avatarUrl' => $wechatUser['avatar'] ?? '',
            'phone'     => $wechatUser['phone'] ?? '',
            'gender'    => (int) ($wechatUser['gender'] ?? 0),
            'country'   => $wechatUser['country'] ?? '',
            'province'  => $wechatUser['province'] ?? '',
            'city'      => $wechatUser['city'] ?? '',
            'birthday'  => $wechatUser['birthday'] ?? '',
            'hasEnterprise' => $hasEnterprise,
            'enterpriseId'   => $enterpriseId,
        ];

        return success([
            'token'      => $token,
            'expires_in' => config('jwt.expire'),
            'user'       => $out,
        ], '登录成功');
    }

    /**
     * 更新小程序用户资料（昵称、头像等），需要认证且为微信用户
     * PUT api/auth/wechat/profile  body: { "nickname": "xxx", "avatar": "url", "gender", "country", "province", "city" }
     * @return \think\response\Json
     */
    public function updateWechatProfile()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (($user['source'] ?? '') !== 'wechat') {
            return error('仅支持小程序用户更新资料', 403);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('用户不存在', 404);
        }

        // PUT请求的数据在body中，Content-Type为application/json时需要特殊处理
        $contentType = Request::header('content-type', '');
        $input = [];
        
        if (stripos($contentType, 'application/json') !== false) {
            // JSON格式的请求体，需要从原始内容中解析
            $rawContent = Request::getContent();
            if ($rawContent) {
                $input = json_decode($rawContent, true) ?: [];
            }
        } else {
            // 表单格式的请求体
            $input = Request::post() ?: Request::put() ?: [];
        }
        
        // 如果还是空，尝试从param获取（兼容性处理）
        if (empty($input)) {
            $input = Request::param();
        }
        
        // 记录接收到的数据（调试用）
        \think\facade\Log::info('更新用户资料请求', [
            'userId' => $userId,
            'input' => $input,
            'method' => Request::method(),
            'contentType' => $contentType,
            'rawContent' => Request::getContent()
        ]);
        
        $allow = ['nickname', 'avatar', 'gender', 'country', 'province', 'city', 'birthday'];
        $data = [];
        foreach ($allow as $k) {
            if (isset($input[$k]) && $input[$k] !== null && $input[$k] !== '') {
                $v = $input[$k];
                if ($k === 'avatar') {
                    $data['avatar'] = is_string($v) ? $v : '';
                } elseif ($k === 'nickname') {
                    $data['nickname'] = is_string($v) ? mb_substr(trim($v), 0, 100) : '';
                } elseif ($k === 'birthday') {
                    $data['birthday'] = is_string($v) ? preg_replace('/[^\d\-]/', '', trim($v)) : '';
                } elseif ($k === 'gender') {
                    $data['gender'] = (int) $v;
                } else {
                    $data[$k] = is_string($v) ? trim($v) : '';
                }
            }
        }

        if (empty($data)) {
            \think\facade\Log::warning('更新用户资料：没有可更新的字段', ['input' => $input]);
            return error('没有可更新的字段', 400);
        }

        $data['updatedAt'] = time();
        \think\facade\Log::info('更新用户资料SQL', ['userId' => $userId, 'data' => $data]);
        
        $result = Db::name('wechat_users')->where('id', $userId)->update($data);
        
        \think\facade\Log::info('更新用户资料结果', ['userId' => $userId, 'affectedRows' => $result]);

        $row = Db::name('wechat_users')->where('id', $userId)->find();
        unset($row['sessionKey'], $row['openid']);
        $row['avatarUrl'] = $row['avatar'] ?? '';

        return success($row, '更新成功');
    }

    /**
     * 小程序获取手机号：用 getPhoneNumber 返回的 code 换手机号并写入当前用户
     * POST api/auth/wechat/phone  body: { "code": "xxx" }  需登录且为微信用户
     * @return \think\response\Json
     */
    public function wechatPhone()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (($user['source'] ?? '') !== 'wechat') {
            return error('仅支持小程序用户', 403);
        }
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('用户不存在', 404);
        }
        $contentType = Request::header('content-type', '');
        $input = [];
        if (stripos($contentType, 'application/json') !== false) {
            $rawContent = Request::getContent();
            if ($rawContent) {
                $input = json_decode($rawContent, true) ?: [];
            }
        } else {
            $input = Request::post() ?: [];
        }
        if (empty($input)) {
            $input = Request::param();
        }
        $code = $input['code'] ?? '';
        if ($code === '') {
            return error('缺少 code', 400);
        }

        // 调试日志：记录收到的手机号 code（仅保留前几位防止泄露）
        \think\facade\Log::info('WechatPhone 请求', [
            'userId'   => $userId,
            'codeHead' => substr($code, 0, 8) . '***',
        ]);

        $phoneResult = WechatService::getPhoneNumber($code);
        if (isset($phoneResult['errcode'])) {
            \think\facade\Log::warning('WechatPhone 获取手机号失败', [
                'userId'      => $userId,
                'codeHead'    => substr($code, 0, 8) . '***',
                'errcode'     => $phoneResult['errcode'] ?? null,
                'errmsg'      => $phoneResult['errmsg'] ?? null,
            ]);
            return error(($phoneResult['errmsg'] ?? '获取手机号失败') . ' (code inval)', 400);
        }
        $phone = $phoneResult['purePhoneNumber'] ?? $phoneResult['phoneNumber'] ?? '';
        if ($phone === '') {
            return error('未获取到手机号', 400);
        }

        Db::name('wechat_users')->where('id', $userId)->update([
            'phone'     => $phone,
            'updatedAt' => time(),
        ]);
        $row = Db::name('wechat_users')->where('id', $userId)->find();
        unset($row['sessionKey'], $row['openid']);
        $row['avatarUrl'] = $row['avatar'] ?? '';

        return success([
            'phone' => $phone,
            'user'  => $row,
        ], '获取成功');
    }

    /**
     * 小程序扫码企业邀请后绑定企业：更新 wechat_users.enterpriseId
     * POST api/auth/wechat/bind-enterprise  body: { "enterpriseId": 123 }
     */
    public function wechatBindEnterprise()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (($user['source'] ?? '') !== 'wechat') {
            return error('仅支持小程序用户', 403);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('用户不存在', 404);
        }

        $contentType = Request::header('content-type', '');
        $input = [];
        if (stripos($contentType, 'application/json') !== false) {
            $rawContent = Request::getContent();
            if ($rawContent) {
                $input = json_decode($rawContent, true) ?: [];
            }
        } else {
            $input = Request::post() ?: [];
        }
        if (empty($input)) {
            $input = Request::param();
        }

        $enterpriseId = (int) ($input['enterpriseId'] ?? 0);
        if ($enterpriseId <= 0) {
            return error('缺少或非法的 enterpriseId', 400);
        }

        $ent = Db::name('enterprises')
            ->where('id', $enterpriseId)
            ->where('status', '<>','disabled')
            ->find();
        if (!$ent) {
            return error('企业不存在或已禁用', 404);
        }

        Db::name('wechat_users')->where('id', $userId)->update([
            'enterpriseId' => $enterpriseId,
            'updatedAt'    => time(),
        ]);

        $row = Db::name('wechat_users')->where('id', $userId)->find();
        if (!$row) {
            return error('用户不存在', 404);
        }
        unset($row['sessionKey'], $row['openid']);
        $row['avatarUrl'] = $row['avatar'] ?? '';
        $eid = isset($row['enterpriseId']) && $row['enterpriseId'] !== '' && $row['enterpriseId'] !== null ? (int) $row['enterpriseId'] : null;
        $row['hasEnterprise'] = $eid > 0;
        $row['enterpriseId'] = $eid;
        $row['enterpriseName'] = $ent['name'] ?? '';

        return success($row, '绑定企业成功');
    }
}

