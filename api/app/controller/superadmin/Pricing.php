<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\model\PricingConfig as PricingConfigModel;
use think\facade\Request;

/**
 * 全局定价管理控制器（超管专用）
 */
class Pricing extends BaseController
{
    /**
     * 获取定价配置
     * @return \think\response\Json
     */
    public function index()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $type = Request::param('type', ''); // personal/enterprise/deep
        $enterpriseId = Request::param('enterpriseId', null); // 仅 type=enterprise 时有效，不传为全局

        if ($type) {
            $enterpriseId = $enterpriseId !== null && $enterpriseId !== '' ? (int) $enterpriseId : null;
            $query = PricingConfigModel::where('type', $type);
            if ($type === 'enterprise') {
                $query->where(empty($enterpriseId) ? 'enterpriseId' : 'enterpriseId', empty($enterpriseId) ? 'null' : '=', empty($enterpriseId) ? null : $enterpriseId);
                if (empty($enterpriseId)) {
                    $query->whereNull('enterpriseId');
                } else {
                    $query->where('enterpriseId', $enterpriseId);
                }
            } else {
                $query->whereNull('enterpriseId');
            }
            $config = $query->find();
            if (!$config) {
                return error('定价配置不存在', 404);
            }
            $cfg = $config->config;
            if (is_array($cfg)) {
                if ($type === 'personal') {
                    $cfg = self::normalizePersonalPricingConfig($cfg);
                } elseif ($type === 'enterprise') {
                    $cfg = self::normalizeEnterprisePricingConfig($cfg);
                }
            }

            return success([
                'type' => $config->type,
                'enterpriseId' => $config->enterpriseId,
                'config' => $cfg
            ]);
        } else {
            // 获取所有：个人/深度各一条(全局)，企业=全局默认定价 + 各企业专属列表
            $configs = PricingConfigModel::select()->toArray();
            $result = ['personal' => null, 'enterprise' => null, 'deep' => null, 'enterpriseList' => []];
            foreach ($configs as $row) {
                if ($row['enterpriseId'] === null || $row['enterpriseId'] === '') {
                    $result[$row['type']] = $row['config'];
                } else {
                    if ($row['type'] === 'enterprise') {
                        $result['enterpriseList'][] = ['enterpriseId' => (int) $row['enterpriseId'], 'config' => $row['config']];
                    }
                }
            }
            if (is_array($result['personal'])) {
                $result['personal'] = self::normalizePersonalPricingConfig($result['personal']);
            }
            if (is_array($result['enterprise'])) {
                $result['enterprise'] = self::normalizeEnterprisePricingConfig($result['enterprise']);
            }
            foreach ($result['enterpriseList'] as $i => $entRow) {
                if (isset($entRow['config']) && is_array($entRow['config'])) {
                    $result['enterpriseList'][$i]['config'] = self::normalizeEnterprisePricingConfig($entRow['config']);
                }
            }

            return success($result);
        }
    }

    /**
     * 更新定价配置
     * @return \think\response\Json
     */
    public function update()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // PUT JSON body 需显式解析，直接用 param() 读深层嵌套数组可能丢失数据
        $rawBody  = (string) $this->request->getContent();
        $jsonData = ($rawBody !== '') ? json_decode($rawBody, true) : null;

        if (is_array($jsonData)) {
            $type         = (string) ($jsonData['type']         ?? '');
            $enterpriseId = $jsonData['enterpriseId']           ?? null;
            $config       = $jsonData['config']                 ?? [];
        } else {
            $type         = (string) Request::param('type', '');
            $enterpriseId = Request::param('enterpriseId', null);
            $config       = Request::param('config', []);
        }

        if (empty($type)) {
            return error('定价类型不能为空', 400);
        }

        if (!in_array($type, ['personal', 'enterprise', 'deep', 'deep_personal', 'deep_enterprise'])) {
            return error('定价类型无效', 400);
        }

        if (empty($config) || !is_array($config)) {
            return error('配置数据不能为空', 400);
        }

        $enterpriseId = ($type === 'enterprise' && $enterpriseId !== null && $enterpriseId !== '') ? (int) $enterpriseId : null;
        if ($type !== 'enterprise') {
            $enterpriseId = null;
        }

        $query = PricingConfigModel::where('type', $type);
        if ($type === 'enterprise') {
            if ($enterpriseId !== null) {
                $query->where('enterpriseId', $enterpriseId);
            } else {
                $query->whereNull('enterpriseId');
            }
        } else {
            $query->whereNull('enterpriseId');
        }
        // deep_personal / deep_enterprise 仅全局一条，不按企业分
        $pricingConfig = $query->find();

        if (!$pricingConfig) {
            $pricingConfig = PricingConfigModel::create([
                'type' => $type,
                'enterpriseId' => $enterpriseId,
                'config' => $config
            ]);
        } else {
            $pricingConfig->config = $config;
            $pricingConfig->save();
        }

        return success([
            'type' => $pricingConfig->type,
            'enterpriseId' => $pricingConfig->enterpriseId,
            'config' => $pricingConfig->config
        ], '保存成功');
    }

    /**
     * 批量更新定价配置
     * @return \think\response\Json
     */
    public function batchUpdate()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $rawBody  = (string) $this->request->getContent();
        $jsonData = ($rawBody !== '') ? json_decode($rawBody, true) : null;
        $data     = is_array($jsonData) ? ($jsonData['data'] ?? []) : Request::param('data', []);

        if (empty($data) || !is_array($data)) {
            return error('配置数据不能为空', 400);
        }

        $successCount = 0;
        $errors = [];

        foreach ($data as $type => $config) {
            if (!in_array($type, ['personal', 'enterprise', 'deep', 'deep_personal', 'deep_enterprise'])) {
                $errors[] = "类型 {$type} 无效";
                continue;
            }

            if (empty($config) || !is_array($config)) {
                $errors[] = "类型 {$type} 的配置数据无效";
                continue;
            }

            try {
                $pricingConfig = PricingConfigModel::where('type', $type)->whereNull('enterpriseId')->find();
                
                if (!$pricingConfig) {
                    PricingConfigModel::create([
                        'type' => $type,
                        'config' => $config
                    ]);
                } else {
                    $pricingConfig->config = $config;
                    $pricingConfig->save();
                }
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "保存类型 {$type} 失败：" . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return error('部分配置保存失败：' . implode('；', $errors), 400);
        }

        return success(null, "成功保存 {$successCount} 个配置");
    }

    /**
     * 旧库 JSON 可能缺少高考等字段，合并默认值便于管理端展示与保存
     *
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private static function normalizePersonalPricingConfig(array $cfg): array
    {
        $defaults = [
            'face' => 0,
            'mbti' => 0,
            'disc' => 0,
            'pdp' => 0,
            'sbti' => 0,
            'gaokao' => 0,
        ];

        return array_merge($defaults, $cfg);
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private static function normalizeEnterprisePricingConfig(array $cfg): array
    {
        $defaults = [
            'face' => 0,
            'mbti' => 0,
            'pdp' => 0,
            'disc' => 0,
            'sbti' => 0,
            'gaokao' => 0,
            'minRecharge' => 0,
        ];

        return array_merge($defaults, $cfg);
    }
}

