<?php
namespace app\common\service;

/**
 * 微信小程序接口服务
 */
class WechatService
{
    protected static $jscode2sessionUrl = 'https://api.weixin.qq.com/sns/jscode2session';
    protected static $tokenUrl = 'https://api.weixin.qq.com/cgi-bin/token';
    protected static $getPhoneNumberUrl = 'https://api.weixin.qq.com/wxa/business/getuserphonenumber';
    protected static $getWxacodeUnlimitedUrl = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit';

    /** @var string|null 内存缓存的 access_token */
    protected static $cachedAccessToken = null;
    /** @var int 缓存的 access_token 过期时间戳 */
    protected static $cachedAccessTokenExpire = 0;

    /**
     * 获取小程序 access_token（带简单内存缓存，过期前 5 分钟刷新）
     * @return array{access_token:string}|array{errcode:int,errmsg:string}
     */
    public static function getAccessToken(): array
    {
        $now = time();
        if (self::$cachedAccessToken && self::$cachedAccessTokenExpire > $now + 300) {
            return ['access_token' => self::$cachedAccessToken];
        }
        $appId = config('wechat.app_id');
        $appSecret = config('wechat.app_secret');
        if (empty($appId) || empty($appSecret)) {
            return ['errcode' => -1, 'errmsg' => '未配置微信小程序 app_id 或 app_secret'];
        }
        $url = self::$tokenUrl . '?' . http_build_query([
            'grant_type' => 'client_credential',
            'appid'      => $appId,
            'secret'     => $appSecret,
        ]);
        $resp = @file_get_contents($url);
        if ($resp === false) {
            return ['errcode' => -2, 'errmsg' => '请求微信接口失败'];
        }
        $data = json_decode($resp, true);
        if (empty($data) || !is_array($data)) {
            return ['errcode' => -3, 'errmsg' => '微信接口返回异常'];
        }
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return [
                'errcode' => (int) $data['errcode'],
                'errmsg'  => $data['errmsg'] ?? 'unknown',
            ];
        }
        $token = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 7200);
        self::$cachedAccessToken = $token;
        self::$cachedAccessTokenExpire = $now + $expiresIn;
        return ['access_token' => $token];
    }

    /**
     * 用 getPhoneNumber 回调里的 code 换取手机号
     * @param string $code 小程序 button open-type="getPhoneNumber" 回调中的 detail.code
     * @return array{phoneNumber:string,purePhoneNumber:string,countryCode:string}|array{errcode:int,errmsg:string}
     */
    public static function getPhoneNumber(string $code): array
    {
        $tokenResult = self::getAccessToken();
        if (isset($tokenResult['errcode'])) {
            return $tokenResult;
        }
        $accessToken = $tokenResult['access_token'];
        $url = self::$getPhoneNumberUrl . '?access_token=' . urlencode($accessToken);
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode(['code' => $code]),
            ],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['errcode' => -2, 'errmsg' => '请求微信接口失败'];
        }
        $data = json_decode($resp, true);
        if (empty($data) || !is_array($data)) {
            return ['errcode' => -3, 'errmsg' => '微信接口返回异常'];
        }
        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return [
                'errcode' => (int) $data['errcode'],
                'errmsg'  => $data['errmsg'] ?? 'unknown',
            ];
        }
        $phoneInfo = $data['phone_info'] ?? [];
        $purePhoneNumber = $phoneInfo['purePhoneNumber'] ?? $phoneInfo['phoneNumber'] ?? '';
        $phoneNumber = $phoneInfo['phoneNumber'] ?? $purePhoneNumber;
        $countryCode = $phoneInfo['countryCode'] ?? '86';
        return [
            'phoneNumber'     => $phoneNumber,
            'purePhoneNumber' => $purePhoneNumber,
            'countryCode'     => $countryCode,
        ];
    }

    /**
     * code 换取 openid、session_key（及 unionid）
     * @param string $code 小程序 wx.login 返回的 code
     * @return array{openid:string,session_key:string,unionid?:string}|array{errcode:int,errmsg:string}
     */
    public static function jscode2session(string $code): array
    {
        $appId = config('wechat.app_id');
        $appSecret = config('wechat.app_secret');
        if (empty($appId) || empty($appSecret)) {
            return ['errcode' => -1, 'errmsg' => '未配置微信小程序 app_id 或 app_secret'];
        }

        $url = self::$jscode2sessionUrl . '?' . http_build_query([
            'appid'      => $appId,
            'secret'     => $appSecret,
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);

        $resp = @file_get_contents($url);
        if ($resp === false) {
            return ['errcode' => -2, 'errmsg' => '请求微信接口失败'];
        }

        $data = json_decode($resp, true);
        if (empty($data) || !is_array($data)) {
            return ['errcode' => -3, 'errmsg' => '微信接口返回异常'];
        }

        if (isset($data['errcode']) && $data['errcode'] !== 0) {
            return [
                'errcode' => (int) $data['errcode'],
                'errmsg'  => $data['errmsg'] ?? 'unknown',
            ];
        }

        return [
            'openid'      => $data['openid'] ?? '',
            'session_key' => $data['session_key'] ?? '',
            'unionid'     => $data['unionid'] ?? null,
        ];
    }

    /**
     * 生成带参数的小程序码（永久有效），返回原始二进制或错误信息
     * @param string $scene  最大 32 个可见字符，用于区分邀请人/渠道
     * @param string $page   小程序页面路径，如 pages/index/index
     * @param int    $width  小程序码宽度，默认 430
     * @return array{binary:string}|array{errcode:int,errmsg:string}
     */
    public static function getWxacodeUnlimited(string $scene, string $page, int $width = 430): array
    {
        $tokenResult = self::getAccessToken();
        if (isset($tokenResult['errcode'])) {
            return $tokenResult;
        }
        $accessToken = $tokenResult['access_token'];
        $url = self::$getWxacodeUnlimitedUrl . '?access_token=' . urlencode($accessToken);
        $payload = [
            'scene'      => mb_substr($scene, 0, 32),
            'page'       => $page,
            'width'      => $width,
            'check_path' => false,
        ];
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return ['errcode' => -2, 'errmsg' => '请求微信接口失败'];
        }
        // 微信错误时返回 JSON，成功时返回图片二进制
        $head = substr($resp, 0, 1);
        if ($head === '{' || $head === '[') {
            $data = json_decode($resp, true);
            if (is_array($data) && isset($data['errcode']) && $data['errcode'] !== 0) {
                return [
                    'errcode' => (int) $data['errcode'],
                    'errmsg'  => $data['errmsg'] ?? 'unknown',
                ];
            }
        }
        return ['binary' => $resp];
    }
}
