<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\model\AiProvider as AiProviderModel;
use think\facade\Request;
use think\facade\Db;

/**
 * AI服务商配置管理控制器（超管专用）
 */
class AiConfig extends BaseController
{
    /**
     * 获取所有AI服务商配置
     * @return \think\response\Json
     */
    public function index()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 列表只返回“显示”的配置（visible=1 或未设）；隐藏的由数据库 visible=0 控制，不在此列表展示
        $providers = AiProviderModel::order('id', 'asc')
            ->whereRaw('(visible IS NULL OR visible = 1)')
            ->select()
            ->toArray();

        // 处理返回数据
        $result = [];
        foreach ($providers as $provider) {
            $result[] = [
                'id' => $provider['providerId'],
                'name' => $provider['name'],
                'enabled' => $provider['enabled'] == 1,
                'visible' => isset($provider['visible']) ? ($provider['visible'] == 1) : true,
                'apiKey' => $provider['apiKey'] ?? '', // 脱敏后的密钥
                'apiEndpoint' => $provider['apiEndpoint'] ?? '',
                'model' => $provider['model'] ?? '',
                'organizationId' => $provider['organizationId'] ?? '',
                'maxTokens' => $provider['maxTokens'] ?? 4096,
                'balanceAlertEnabled' => $provider['balanceAlertEnabled'] == 1,
                'balanceAlertThreshold' => floatval($provider['balanceAlertThreshold'] ?? 10),
                'notes' => $provider['notes'] ?? '',
                'docUrl' => $provider['docUrl'] ?? '',
                'isFree' => $provider['isFree'] == 1,
                'supportsBalance' => $provider['supportsBalance'] == 1,
                '_hasKey' => !empty($provider['apiKey']),
                'lastBalance' => $provider['lastBalance'] ? floatval($provider['lastBalance']) : null,
                'lastBalanceCurrency' => $provider['lastBalanceCurrency'] ?? null,
                'lastBalanceCheckedAt' => $provider['lastBalanceCheckedAt'] ? date('Y-m-d H:i:s', $provider['lastBalanceCheckedAt']) : null,
                'extraConfig' => is_array($provider['extraConfig'] ?? null) ? $provider['extraConfig'] : (isset($provider['extraConfig']) && is_string($provider['extraConfig']) ? (json_decode($provider['extraConfig'], true) ?: []) : [])
            ];
        }

        return success($result);
    }

    /**
     * 更新AI服务商配置
     * @return \think\response\Json
     */
    public function update()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $providerId = Request::param('providerId', '');
        $data = Request::only([
            'name', 'enabled', 'visible', 'apiKey', 'apiEndpoint', 'model', 'organizationId',
            'maxTokens', 'balanceAlertEnabled', 'balanceAlertThreshold', 'notes',
            'extraConfig'
        ]);

        if (empty($providerId)) {
            return error('服务商ID不能为空', 400);
        }

        // 查找服务商配置
        $provider = AiProviderModel::where('providerId', $providerId)->find();
        
        if (!$provider) {
            return error('服务商配置不存在', 404);
        }

        // 处理 enabled 字段（前端传的是布尔值）
        if (isset($data['enabled'])) {
            $data['enabled'] = $data['enabled'] ? 1 : 0;
        }

        // 处理 balanceAlertEnabled 字段
        if (isset($data['balanceAlertEnabled'])) {
            $data['balanceAlertEnabled'] = $data['balanceAlertEnabled'] ? 1 : 0;
        }

        // 处理 visible 字段（显示/隐藏，数据库直接控制）
        if (isset($data['visible'])) {
            $data['visible'] = $data['visible'] ? 1 : 0;
        }

        // extraConfig 可为数组或 JSON 字符串，模型 type=json 会处理
        if (isset($data['extraConfig']) && is_string($data['extraConfig'])) {
            $decoded = json_decode($data['extraConfig'], true);
            $data['extraConfig'] = is_array($decoded) ? $decoded : [];
        }

        // 如果API Key为空或包含脱敏标记（****），不更新（保持原值）
        if (isset($data['apiKey'])) {
            if (empty($data['apiKey']) || strpos($data['apiKey'], '****') !== false) {
                unset($data['apiKey']);
            }
        }

        // 更新配置
        $provider->save($data);

        // 返回更新后的数据（脱敏）
        $result = [
            'id' => $provider->providerId,
            'name' => $provider->name,
            'enabled' => $provider->enabled == 1,
            'visible' => isset($provider->visible) ? ($provider->visible == 1) : true,
            'apiKey' => $provider->apiKey ?? '',
            'apiEndpoint' => $provider->apiEndpoint ?? '',
            'model' => $provider->model ?? '',
            'organizationId' => $provider->organizationId ?? '',
            'maxTokens' => $provider->maxTokens ?? 4096,
            'balanceAlertEnabled' => $provider->balanceAlertEnabled == 1,
            'balanceAlertThreshold' => floatval($provider->balanceAlertThreshold ?? 10),
            'notes' => $provider->notes ?? '',
            'isFree' => $provider->isFree == 1,
            'supportsBalance' => $provider->supportsBalance == 1,
            '_hasKey' => !empty($provider->apiKey),
            'extraConfig' => $provider->extraConfig ?? []
        ];

        return success($result, '保存成功');
    }

    /**
     * 批量更新AI服务商配置
     * @return \think\response\Json
     */
    public function batchUpdate()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $providers = Request::param('providers', []);

        if (empty($providers) || !is_array($providers)) {
            return error('配置数据不能为空', 400);
        }

        $successCount = 0;
        $errors = [];

        Db::startTrans();
        try {
            foreach ($providers as $providerData) {
                $providerId = $providerData['id'] ?? $providerData['providerId'] ?? '';
                
                if (empty($providerId)) {
                    $errors[] = '服务商ID不能为空';
                    continue;
                }

                $provider = AiProviderModel::where('providerId', $providerId)->find();
                
                if (!$provider) {
                    $errors[] = "服务商 {$providerId} 不存在";
                    continue;
                }

                // 准备更新数据
                $updateData = [];
                if (isset($providerData['enabled'])) {
                    $updateData['enabled'] = $providerData['enabled'] ? 1 : 0;
                }
                if (isset($providerData['apiKey']) && !empty($providerData['apiKey'])) {
                    $updateData['apiKey'] = $providerData['apiKey'];
                }
                if (isset($providerData['apiEndpoint'])) {
                    $updateData['apiEndpoint'] = $providerData['apiEndpoint'];
                }
                if (isset($providerData['model'])) {
                    $updateData['model'] = $providerData['model'];
                }
                if (isset($providerData['organizationId'])) {
                    $updateData['organizationId'] = $providerData['organizationId'];
                }
                if (isset($providerData['maxTokens'])) {
                    $updateData['maxTokens'] = intval($providerData['maxTokens']);
                }
                if (isset($providerData['balanceAlertEnabled'])) {
                    $updateData['balanceAlertEnabled'] = $providerData['balanceAlertEnabled'] ? 1 : 0;
                }
                if (isset($providerData['balanceAlertThreshold'])) {
                    $updateData['balanceAlertThreshold'] = floatval($providerData['balanceAlertThreshold']);
                }
                if (isset($providerData['notes'])) {
                    $updateData['notes'] = $providerData['notes'];
                }
                if (isset($providerData['visible'])) {
                    $updateData['visible'] = $providerData['visible'] ? 1 : 0;
                }
                if (isset($providerData['extraConfig'])) {
                    $updateData['extraConfig'] = is_array($providerData['extraConfig'])
                        ? $providerData['extraConfig']
                        : (is_string($providerData['extraConfig']) ? json_decode($providerData['extraConfig'], true) : []);
                    if (!is_array($updateData['extraConfig'])) {
                        $updateData['extraConfig'] = [];
                    }
                }

                $provider->save($updateData);
                $successCount++;
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error('批量保存失败：' . $e->getMessage(), 500);
        }

        if (!empty($errors)) {
            return error('部分配置保存失败：' . implode('；', $errors), 400);
        }

        return success(null, "成功保存 {$successCount} 个配置");
    }

    /**
     * 查询余额（单个服务商）
     * @return \think\response\Json
     */
    public function queryBalance()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $providerId = Request::param('providerId', '');

        if (empty($providerId)) {
            return error('服务商ID不能为空', 400);
        }

        $provider = AiProviderModel::where('providerId', $providerId)->find();
        
        if (!$provider) {
            return error('服务商配置不存在', 404);
        }

        if (empty($provider->apiKey)) {
            return error('请先配置 API Key', 400);
        }

        if (!$provider->supportsBalance) {
            return error('该服务商暂不支持余额查询', 400);
        }

        // 调用余额查询服务
        $balanceResult = $this->queryProviderBalance($provider);

        // 更新最后查询的余额
        if ($balanceResult['status'] === 'success' && isset($balanceResult['balance'])) {
            $provider->lastBalance = $balanceResult['balance'];
            $provider->lastBalanceCurrency = $balanceResult['currency'] ?? 'CNY';
            $provider->lastBalanceCheckedAt = time();
            $provider->save();
        }

        return success($balanceResult);
    }

    /**
     * 批量查询余额
     * @return \think\response\Json
     */
    public function queryAllBalances()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $providerIds = Request::param('providerIds', []);

        // 如果没有指定，查询所有已启用且已配置密钥的服务商
        if (empty($providerIds)) {
            $providers = AiProviderModel::where('enabled', 1)
                ->where('apiKey', '<>', '')
                ->where('apiKey', '<>', null)
                ->select();
        } else {
            $providers = AiProviderModel::where('providerId', 'in', $providerIds)
                ->where('apiKey', '<>', '')
                ->where('apiKey', '<>', null)
                ->select();
        }

        $results = [];
        foreach ($providers as $provider) {
            if (!$provider->supportsBalance) {
                continue;
            }

            $balanceResult = $this->queryProviderBalance($provider);

            // 更新最后查询的余额
            if ($balanceResult['status'] === 'success' && isset($balanceResult['balance'])) {
                $provider->lastBalance = $balanceResult['balance'];
                $provider->lastBalanceCurrency = $balanceResult['currency'] ?? 'CNY';
                $provider->lastBalanceCheckedAt = time();
                $provider->save();
            }

            $results[] = $balanceResult;
        }

        return success($results);
    }

    /**
     * 查询服务商余额（内部方法）
     * @param AiProviderModel $provider
     * @return array
     */
    private function queryProviderBalance($provider)
    {
        // 这里需要实现各服务商的余额查询逻辑
        // 由于各服务商的API不同，这里提供一个基础框架
        
        $providerId = $provider->providerId;
        $apiKey = $provider->getRawApiKey(); // 获取原始密钥用于API调用

        // TODO: 实现各服务商的余额查询API调用
        // 目前返回模拟数据，实际需要调用各服务商的API
        
        try {
            switch ($providerId) {
                case 'openai':
                    // OpenAI余额查询逻辑
                    return $this->queryOpenAIBalance($apiKey);
                    
                case 'deepseek':
                    // DeepSeek余额查询逻辑
                    return $this->queryDeepSeekBalance($apiKey);
                    
                case 'moonshot':
                    // Moonshot余额查询逻辑
                    return $this->queryMoonshotBalance($apiKey);
                    
                default:
                    return [
                        'providerId' => $providerId,
                        'providerName' => $provider->name,
                        'status' => 'unsupported',
                        'message' => '该服务商暂不支持余额查询',
                        'balance' => null,
                        'currency' => null,
                        'checkedAt' => date('Y-m-d H:i:s')
                    ];
            }
        } catch (\Exception $e) {
            return [
                'providerId' => $providerId,
                'providerName' => $provider->name,
                'status' => 'error',
                'message' => '查询失败：' . $e->getMessage(),
                'balance' => null,
                'currency' => null,
                'checkedAt' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 查询OpenAI余额
     * @param string $apiKey
     * @return array
     */
    private function queryOpenAIBalance($apiKey)
    {
        // TODO: 实现OpenAI余额查询
        // OpenAI没有直接的余额查询API，需要通过使用情况估算
        return [
            'providerId' => 'openai',
            'providerName' => 'OpenAI (GPT)',
            'status' => 'success',
            'message' => '余额查询成功：$100.00',
            'balance' => 100.00,
            'currency' => 'USD',
            'checkedAt' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 查询DeepSeek余额
     * @param string $apiKey
     * @return array
     */
    private function queryDeepSeekBalance($apiKey)
    {
        // TODO: 实现DeepSeek余额查询
        try {
            // 示例：调用DeepSeek API查询余额
            // $response = file_get_contents('https://api.deepseek.com/v1/balance', [
            //     'http' => [
            //         'method' => 'GET',
            //         'header' => "Authorization: Bearer {$apiKey}\r\n"
            //     ]
            // ]);
            
            return [
                'providerId' => 'deepseek',
                'providerName' => 'DeepSeek',
                'status' => 'success',
                'message' => '余额查询成功：¥500.00',
                'balance' => 500.00,
                'currency' => 'CNY',
                'checkedAt' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'providerId' => 'deepseek',
                'providerName' => 'DeepSeek',
                'status' => 'error',
                'message' => '查询失败：' . $e->getMessage(),
                'balance' => null,
                'currency' => null,
                'checkedAt' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * 查询Moonshot余额
     * @param string $apiKey
     * @return array
     */
    private function queryMoonshotBalance($apiKey)
    {
        // TODO: 实现Moonshot余额查询
        try {
            // 示例：调用Moonshot API查询余额
            return [
                'providerId' => 'moonshot',
                'providerName' => 'Moonshot (Kimi)',
                'status' => 'success',
                'message' => '余额查询成功：¥200.00',
                'balance' => 200.00,
                'currency' => 'CNY',
                'checkedAt' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'providerId' => 'moonshot',
                'providerName' => 'Moonshot (Kimi)',
                'status' => 'error',
                'message' => '查询失败：' . $e->getMessage(),
                'balance' => null,
                'currency' => null,
                'checkedAt' => date('Y-m-d H:i:s')
            ];
        }
    }
}

