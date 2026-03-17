<?php
namespace app\common\service;

use Exception;
use think\facade\Log;

/**
 * 微信商家转账到零钱封装
 *
 * 配置从 env / config 中读取，字段示例：
 *  - WECHAT_MCH_ID
 *  - WECHAT_APP_ID
 *  - WECHAT_API_V3_KEY
 *  - WECHAT_MCH_PRIVATE_KEY (绝对路径 apiclient_key.pem)
 *  - WECHAT_MCH_CERT_SERIAL
 */
class WechatTransferService
{
    // 微信支付API域名
    const API_BASE_URL = 'https://api.mch.weixin.qq.com';
    const API_BASE_URL_BACKUP = 'https://api2.mch.weixin.qq.com';

    // 配置信息
    private $mchId;          // 商户号
    private $appId;          // 小程序/公众号AppID
    private $apiV3Key;       // API v3密钥
    private $privateKey;     // 商户私钥（用于签名）
    private $certSerialNo;  // 证书序列号（用于加密敏感信息）
    private $publicKey;      // 微信支付公钥（用于验证回调）

    /**
     * 构造函数：直接从 .env 读取配置
     */
    public function __construct()
    {
        // 核心配置来自 mbti/api/.env
        $this->mchId       = env('MCH_ID', '');
        $this->appId       = env('WECHAT_APPID', '');
        $this->apiV3Key    = env('API_KEY', '');
        $this->certSerialNo= env('CERT_SERIAL_NO', '');

        if (!$this->mchId || !$this->appId || !$this->apiV3Key || !$this->certSerialNo) {
            throw new Exception('微信转账配置不完整，请检查 .env 中的 MCH_ID / WECHAT_APPID / API_KEY / CERT_SERIAL_NO');
        }

        // 私钥：支持本地路径、URL 或直接内容
        $privateKeyConf = env('PRIVATE_KEY', '');
        if ($privateKeyConf) {
            if (file_exists($privateKeyConf)) {
                $this->privateKey = file_get_contents($privateKeyConf);
            } elseif (filter_var($privateKeyConf, FILTER_VALIDATE_URL)) {
                $this->privateKey = file_get_contents($privateKeyConf);
                if ($this->privateKey === false) {
                    Log::error('无法从URL加载私钥', ['url' => $privateKeyConf]);
                    $this->privateKey = '';
                }
            } else {
                $this->privateKey = $privateKeyConf;
            }
        }
        if (empty($this->privateKey)) {
            throw new Exception('商户私钥加载失败，请检查 PRIVATE_KEY 配置');
        }

        // 公钥（可选）：用于后续回调验签，支持本地路径、URL 或直接内容
        $publicKeyConf = env('WECHAT_PAY_PUB_KEY', '');
        $this->publicKey = '';
        if ($publicKeyConf) {
            if (file_exists($publicKeyConf)) {
                $this->publicKey = file_get_contents($publicKeyConf);
            } elseif (filter_var($publicKeyConf, FILTER_VALIDATE_URL)) {
                $this->publicKey = file_get_contents($publicKeyConf);
                if ($this->publicKey === false) {
                    Log::error('无法从URL加载公钥', ['url' => $publicKeyConf]);
                    $this->publicKey = '';
                }
            } else {
                $this->publicKey = $publicKeyConf;
            }
        }
    }

    /**
     * 发起转账
     * @param array $params 转账参数
     *   - out_bill_no: 商户单号（必填）
     *   - openid: 收款用户OpenID（必填）
     *   - transfer_amount: 转账金额，单位：分（必填）
     *   - transfer_remark: 转账备注（必填）
     *   - transfer_scene_id: 转账场景ID（必填，如：1000现金营销，1006企业报销）
     *   - user_name: 收款用户姓名（选填，>=2000元必填）
     *   - transfer_scene_report_infos: 转账场景报备信息（必填）
     *   - notify_url: 通知地址（选填）
     *   - user_recv_perception: 用户收款感知（选填）
     * @return array
     */
    public function createTransfer($params)
    {
        $url = self::API_BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills';

        // 构建请求体
        $body = [
            'appid'                    => $this->appId,
            'out_bill_no'              => $params['out_bill_no'],
            'transfer_scene_id'        => $params['transfer_scene_id'],
            'openid'                   => $params['openid'],
            'transfer_amount'          => intval($params['transfer_amount']),
            'transfer_remark'          => $params['transfer_remark'],
            // 场景报备信息（必填）：岗位类型 + 报酬说明
            'transfer_scene_report_infos' => $params['transfer_scene_report_infos'] ?? [],
        ];

        // 可选参数
        if (isset($params['user_name']) && !empty($params['user_name'])) {
            // 需要加密
            $body['user_name'] = $this->encryptSensitiveData($params['user_name']);
        }

        if (isset($params['notify_url']) && !empty($params['notify_url'])) {
            $body['notify_url'] = $params['notify_url'];
        }

        // user_recv_perception 暂不传，避免 INVALID_REQUEST：“暂不支持展示当前传入的用户收款感知”

        $result = $this->request('POST', $url, $body);

        return $result;
    }

    /**
     * 查询转账单（通过商户单号）
     * @param string $outBillNo 商户单号
     * @return array
     */
    public function queryByOutBillNo($outBillNo)
    {
        $url = self::API_BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/' . $outBillNo;
        return $this->request('GET', $url);
    }

    /**
     * 查询转账单（通过微信单号）
     * 参考：https://pay.weixin.qq.com/doc/v3/merchant/4012716457
     * @param string $transferBillNo 微信转账单号
     * @return array
     */
    public function queryByTransferBillNo($transferBillNo)
    {
        $url = self::API_BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills/transfer-bill-no/' . $transferBillNo;
        return $this->request('GET', $url);
    }

    /**
     * 撤销转账
     * @param string $transferBillNo 微信转账单号
     * @return array
     */
    public function cancelTransfer($transferBillNo)
    {
        $url = self::API_BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills/' . $transferBillNo . '/cancel';
        return $this->request('POST', $url);
    }

    /**
     * 发送HTTP请求
     * @param string $method 请求方法
     * @param string $url 请求URL
     * @param array $body 请求体（POST时使用）
     * @return array
     */
    private function request($method, $url, $body = [])
    {
        $timestamp = time();
        $nonce = $this->generateNonce();
        $bodyStr = !empty($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';

        // 构建签名
        $signature = $this->buildSignature($method, $url, $timestamp, $nonce, $bodyStr);

        // 构建请求头
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: WechatPay-APIv3-PHP',
            'Authorization: ' . $this->buildAuthorization($method, $url, $timestamp, $nonce, $bodyStr),
        ];

        // 如果有证书序列号，添加到请求头
        if (!empty($this->certSerialNo)) {
            $headers[] = 'Wechatpay-Serial: ' . $this->certSerialNo;
        }

        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            // 尝试写入日志，但不影响错误返回
            try {
                Log::error('微信支付请求失败: ' . $error);
            } catch (\Exception $e) {
                // 日志写入失败不影响错误返回
            }
            return ['success' => false, 'error' => ['code' => 'CURL_ERROR', 'message' => $error]];
        }

        $result = json_decode($response, true);

        if ($httpCode === 200) {
            return ['success' => true, 'data' => $result];
        } else {
            // 尝试写入日志，但不影响错误返回
            try {
                Log::error('微信支付API错误: HTTP ' . $httpCode . ', Response: ' . $response);
            } catch (\Exception $e) {
                // 日志写入失败不影响错误返回
            }
            return ['success' => false, 'http_code' => $httpCode, 'error' => $result];
        }
    }

    /**
     * 构建签名
     * @param string $method 请求方法
     * @param string $url 请求URL（不包含域名）
     * @param int $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $body 请求体
     * @return string
     */
    private function buildSignature($method, $url, $timestamp, $nonce, $body)
    {
        $urlParts = parse_url($url);
        $urlPath = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

        $message = $method . "\n" .
            $urlPath . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";

        openssl_sign($message, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 构建Authorization头
     * @param string $method
     * @param string $url
     * @param int $timestamp
     * @param string $nonce
     * @param string $body
     * @return string
     */
    private function buildAuthorization($method, $url, $timestamp, $nonce, $body)
    {
        $urlParts = parse_url($url);
        $urlPath = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');

        $signature = $this->buildSignature($method, $url, $timestamp, $nonce, $body);

        // 获取证书序列号（从私钥中提取，这里简化处理，实际应该从证书中获取）
        $serialNo = $this->certSerialNo ?: 'YOUR_CERT_SERIAL_NO';

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->mchId,
            $nonce,
            $timestamp,
            $serialNo,
            $signature
        );
    }

    /**
     * 加密敏感信息（使用微信支付公钥加密）
     * @param string $data 待加密数据
     * @return string base64编码的加密数据
     */
    private function encryptSensitiveData($data)
    {
        // 注意：这里需要使用微信支付平台证书公钥加密
        // 简化实现，实际应该使用微信支付平台证书
        if (empty($this->publicKey)) {
            // 如果没有配置公钥，返回原数据（实际生产环境必须加密）
            Log::warning('未配置微信支付公钥，敏感数据未加密');
            return $data;
        }

        $encrypted = '';
        if (openssl_public_encrypt($data, $encrypted, $this->publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            return base64_encode($encrypted);
        }

        Log::error('敏感数据加密失败');
        return $data;
    }

    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    private function generateNonce($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 验证回调签名
     * @param array $headers 请求头
     * @param string $body 请求体
     * @return bool
     */
    public function verifyCallback($headers, $body)
    {
        if (empty($this->publicKey)) {
            // 不记录日志，避免日志错误
            return false;
        }

        // 从请求头中提取签名信息（注意：HTTP头中的下划线会被转换为中划线）
        $signature = $headers['Wechatpay-Signature'] ?? $headers['wechatpay-signature'] ?? '';
        $timestamp = $headers['Wechatpay-Timestamp'] ?? $headers['wechatpay-timestamp'] ?? '';
        $nonce = $headers['Wechatpay-Nonce'] ?? $headers['wechatpay-nonce'] ?? '';
        $serial = $headers['Wechatpay-Serial'] ?? $headers['wechatpay-serial'] ?? '';

        if (empty($signature) || empty($timestamp) || empty($nonce) || empty($serial)) {
            // 不记录日志，避免日志错误
            return false;
        }

        // 构建验证消息（按照微信支付文档格式）
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        // 验证签名
        $signatureData = base64_decode($signature);
        $result = openssl_verify($message, $signatureData, $this->publicKey, OPENSSL_ALGO_SHA256);
        
        if ($result === 1) {
            return true;
        } else {
            // 不记录日志，避免日志错误
            return false;
        }
    }

    /**
     * 解密回调通知中的resource数据
     * @param array $resource 回调通知中的resource对象
     * @return array|null 解密后的数据，失败返回null
     */
    /**
     * 解密回调报文（按照官方文档实现）
     * 参考：https://pay.weixin.qq.com/doc/v3/merchant/4012071382
     * 
     * @param array $resource 加密的资源对象
     * @return array|null 解密后的数据
     */
    public function decryptCallbackResource($resource)
    {
        // 调试信息
        $debug = [];
        $debug['step'] = '1.检查输入参数';
        
        // 1. 检查必要参数
        if (empty($resource['ciphertext']) || empty($resource['nonce']) || !isset($resource['associated_data'])) {
            $debug['error'] = '缺少必要参数';
            $debug['has_ciphertext'] = !empty($resource['ciphertext']);
            $debug['has_nonce'] = !empty($resource['nonce']);
            $debug['has_associated_data'] = isset($resource['associated_data']);
            return ['_debug' => $debug, 'result' => null];
        }

        // 2. 检查加密算法
        $algorithm = $resource['algorithm'] ?? '';
        $debug['step'] = '2.检查加密算法';
        $debug['algorithm'] = $algorithm;
        
        if ($algorithm !== 'AEAD_AES_256_GCM') {
            $debug['error'] = '不支持的加密算法';
            return ['_debug' => $debug, 'result' => null];
        }

        // 3. 检查APIv3密钥长度（必须是32字节）
        $debug['step'] = '3.检查APIv3密钥';
        $debug['api_v3_key_length'] = strlen($this->apiV3Key);
        
        if (strlen($this->apiV3Key) !== 32) {
            $debug['error'] = 'APIv3密钥长度必须为32字节';
            return ['_debug' => $debug, 'result' => null];
        }

        // 4. 准备解密参数（按照官方文档）
        $debug['step'] = '4.准备解密参数';
        
        // Base64解码密文
        $ciphertext = base64_decode($resource['ciphertext']);
        $nonce = $resource['nonce'];
        $associatedData = $resource['associated_data'];
        
        $debug['ciphertext_base64_length'] = strlen($resource['ciphertext']);
        $debug['ciphertext_decoded_length'] = strlen($ciphertext);
        $debug['nonce'] = $nonce;
        $debug['nonce_length'] = strlen($nonce);
        $debug['associated_data'] = $associatedData;

        // 5. 检查密文长度（必须大于认证标签长度16字节）
        $AUTH_TAG_LENGTH = 16;
        if (strlen($ciphertext) <= $AUTH_TAG_LENGTH) {
            $debug['error'] = '密文长度不足，必须大于' . $AUTH_TAG_LENGTH . '字节';
            return ['_debug' => $debug, 'result' => null];
        }

        // 6. 分离密文和认证标签（按照官方文档）
        $debug['step'] = '6.分离密文和认证标签';
        
        // 密文主体（去掉最后16字节）
        $ctext = substr($ciphertext, 0, -$AUTH_TAG_LENGTH);
        // 认证标签（最后16字节）
        $authTag = substr($ciphertext, -$AUTH_TAG_LENGTH);
        
        $debug['ctext_length'] = strlen($ctext);
        $debug['authTag_length'] = strlen($authTag);

        // 7. 使用OpenSSL解密（按照官方文档）
        $debug['step'] = '7.OpenSSL解密';
        
        // PHP >= 7.1 支持 AES-256-GCM
        if (PHP_VERSION_ID < 70100) {
            $debug['error'] = 'PHP版本必须 >= 7.1';
            $debug['php_version'] = PHP_VERSION;
            return ['_debug' => $debug, 'result' => null];
        }
        
        if (!in_array('aes-256-gcm', openssl_get_cipher_methods())) {
            $debug['error'] = 'OpenSSL不支持aes-256-gcm算法';
            return ['_debug' => $debug, 'result' => null];
        }

        // 执行解密（参数顺序按照官方文档）
        $decrypted = openssl_decrypt(
            $ctext,                 // 密文主体
            'aes-256-gcm',         // 加密算法
            $this->apiV3Key,       // API v3密钥
            OPENSSL_RAW_DATA,      // 原始数据
            $nonce,                // 随机串
            $authTag,              // 认证标签
            $associatedData        // 附加数据
        );

        $debug['step'] = '8.检查解密结果';
        $debug['decrypt_success'] = ($decrypted !== false);
        
        if ($decrypted === false) {
            $debug['error'] = 'openssl_decrypt解密失败';
            $debug['openssl_error'] = openssl_error_string() ?: '无错误信息';
            return ['_debug' => $debug, 'result' => null];
        }

        $debug['decrypted_length'] = strlen($decrypted);
        $debug['decrypted_preview'] = substr($decrypted, 0, 200);

        // 8. 解析JSON
        $debug['step'] = '9.解析JSON';
        $data = json_decode($decrypted, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $debug['error'] = 'JSON解析失败';
            $debug['json_error'] = json_last_error_msg();
            $debug['decrypted_full'] = $decrypted;
            return ['_debug' => $debug, 'result' => null];
        }

        $debug['success'] = true;
        $debug['data_keys'] = array_keys($data);
        
        return ['_debug' => $debug, 'result' => $data];
    }
}
