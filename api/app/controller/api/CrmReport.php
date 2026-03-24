<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\JwtService;
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

        // apiKey 为空则跳过，不影响主流程
        if (empty($apiKey)) {
            return success(['reported' => false, 'reason' => 'no_api_key']);
        }

        // 从数据库获取用户信息（手机号、openid、昵称）
        $phone    = '';
        $openid   = '';
        $nickname = '';
        if ($userId > 0) {
            $wechatUser = Db::name('wechat_users')
                ->where('id', $userId)
                ->field('phone, openid, nickname')
                ->find();
            if ($wechatUser) {
                $phone    = (string) ($wechatUser['phone']    ?? '');
                $openid   = (string) ($wechatUser['openid']   ?? '');
                $nickname = (string) ($wechatUser['nickname'] ?? '');
            }
        }

        // 至少需要手机号或微信号，否则没有意义
        if (empty($phone) && empty($openid)) {
            return success(['reported' => false, 'reason' => 'no_identifier']);
        }

        // 读取接口地址（从 .env 的 API_URL）
        $apiUrl   = env('API_URL', 'https://ckbapi.quwanzhi.com/v1/api/scenarios');
        $timestamp = time();

        // 构建请求参数（只加非空字段）
        $params = ['apiKey' => $apiKey, 'timestamp' => $timestamp];
        if ($phone    !== '') $params['phone']    = $phone;
        if ($nickname !== '') $params['name']     = $nickname;
        if ($source   !== '') $params['source']   = $source;
        if ($remark   !== '') $params['remark']   = $remark;
        if ($tags     !== '') $params['tags']     = $tags;
        if ($siteTags !== '') $params['siteTags'] = $siteTags;

        // 生成签名（portrait 不参与签名，需在签名后单独附加）
        $params['sign'] = self::generateSign($params, $apiKey);

        // 附加用户画像（从最近测试结果构建，不参与签名）
        $portrait = self::buildPortrait($userId);
        if ($portrait !== null) {
            $params['portrait'] = $portrait;
        }

        // 发起请求
        $result = self::callApi($apiUrl, $params);

        if ($result['success']) {
            return success(['reported' => true]);
        }

        Log::warning('[CrmReport] 上报失败 userId=' . $userId . ' reason=' . json_encode($result, JSON_UNESCAPED_UNICODE));
        // 上报失败不影响主业务，始终返回成功
        return success(['reported' => false, 'reason' => $result['error'] ?? 'api_error']);
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
            ->whereIn('testType', ['mbti', 'disc', 'pdp'])
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
