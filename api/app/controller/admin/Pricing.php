<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\PricingConfig as PricingConfigModel;
use think\facade\Db;

/**
 * 定价管理控制器（普通管理员）
 * 支持同时配置个人版和企业版定价：
 * - 个人版：type=admin_personal + enterpriseId（企业管理员）或 enterpriseId=NULL（普通管理员）
 * - 企业版：type=admin_enterprise + enterpriseId（企业管理员）
 * 无自定义配置时回落到超管全局定价
 */
class Pricing extends BaseController
{
    /**
     * 获取定价配置（个人版 + 企业版）
     * GET /api/v1/admin/pricing
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        try {
            $enterpriseId = $this->resolveEnterpriseId($user);

            // ── 个人版定价 ──
            $adminPersonalConfig = $this->queryConfig('admin_personal', $enterpriseId);
            $superPersonalConfig = PricingConfigModel::where('type', 'personal')->whereNull('enterpriseId')->find();
            $personalConfig = $adminPersonalConfig
                ? $adminPersonalConfig->config
                : ($superPersonalConfig ? $superPersonalConfig->config : []);
            $isUsingSuperAdminPersonalConfig = !$adminPersonalConfig;

            // ── 企业版定价 ──
            $adminEnterpriseConfig = $enterpriseId
                ? $this->queryConfig('admin_enterprise', $enterpriseId)
                : null;
            $superEnterpriseConfig = PricingConfigModel::where('type', 'enterprise')->whereNull('enterpriseId')->find();
            $enterpriseConfig = $adminEnterpriseConfig
                ? $adminEnterpriseConfig->config
                : ($superEnterpriseConfig ? $superEnterpriseConfig->config : []);
            $isUsingSuperAdminEnterpriseConfig = !$adminEnterpriseConfig;

            return success([
                'personal'                          => $personalConfig,
                'enterprise'                        => $enterpriseConfig,
                'isUsingSuperAdminConfig'           => $isUsingSuperAdminPersonalConfig,
                'isUsingSuperAdminPersonalConfig'   => $isUsingSuperAdminPersonalConfig,
                'isUsingSuperAdminEnterpriseConfig' => $isUsingSuperAdminEnterpriseConfig,
            ]);
        } catch (\Exception $e) {
            return error('获取定价配置失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新定价配置（个人版 + 企业版）
     * PUT /api/v1/admin/pricing
     * Body: { personalConfig: {...}, enterpriseConfig: {...} }
     *       兼容旧格式：{ config: {...} } → 仅更新个人版
     */
    public function update()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return error('未登录', 401);
        }
        if (!in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        $rawBody = $this->request->getContent();
        if (empty($rawBody)) {
            $rawBody = file_get_contents('php://input');
        }
        $input = $rawBody ? json_decode($rawBody, true) : null;
        if (!is_array($input)) {
            $input = [];
        }

        // 兼容旧版仅传 config 的情况
        $personalConfig   = $input['personalConfig'] ?? $input['config'] ?? null;
        $enterpriseConfig = $input['enterpriseConfig'] ?? null;

        if ($personalConfig === null && $enterpriseConfig === null) {
            return error('配置数据不能为空', 400);
        }

        try {
            $enterpriseId = $this->resolveEnterpriseId($user);

            $result = [];

            // ── 保存个人版定价 ──
            if ($personalConfig !== null) {
                if (!is_array($personalConfig)) {
                    return error('个人版定价格式错误', 400);
                }
                foreach (['face', 'mbti', 'disc', 'pdp', 'sbti'] as $field) {
                    if (!array_key_exists($field, $personalConfig)) {
                        return error("个人版定价缺少字段：{$field}", 400);
                    }
                }
                $cfg = $this->queryConfig('admin_personal', $enterpriseId);
                if (!$cfg) {
                    $cfg = PricingConfigModel::create([
                        'type'         => 'admin_personal',
                        'enterpriseId' => $enterpriseId,
                        'config'       => $personalConfig,
                    ]);
                } else {
                    $cfg->config = $personalConfig;
                    $cfg->save();
                }
                $result['personal'] = $cfg->config;
            }

            // ── 保存企业版定价（仅企业管理员）──
            if ($enterpriseConfig !== null) {
                if (!$enterpriseId) {
                    return error('仅企业管理员可设置企业版定价', 403);
                }
                if (!is_array($enterpriseConfig)) {
                    return error('企业版定价格式错误', 400);
                }
                foreach (['face', 'mbti', 'disc', 'pdp', 'sbti'] as $field) {
                    if (!array_key_exists($field, $enterpriseConfig)) {
                        return error("企业版定价缺少字段：{$field}", 400);
                    }
                }
                $cfg = $this->queryConfig('admin_enterprise', $enterpriseId);
                if (!$cfg) {
                    $cfg = PricingConfigModel::create([
                        'type'         => 'admin_enterprise',
                        'enterpriseId' => $enterpriseId,
                        'config'       => $enterpriseConfig,
                    ]);
                } else {
                    $cfg->config = $enterpriseConfig;
                    $cfg->save();
                }
                $result['enterprise'] = $cfg->config;
            }

            return success($result, '定价配置已保存');
        } catch (\Exception $e) {
            return error('保存失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 从 JWT 用户信息中解析 enterpriseId
     */
    private function resolveEnterpriseId(array $user): ?int
    {
        if (($user['role'] ?? '') !== 'enterprise_admin') {
            return null;
        }
        $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
        $eid = $adminRow['enterpriseId'] ?? null;
        return $eid ? (int) $eid : null;
    }

    /**
     * 按 type + enterpriseId 查询定价配置
     */
    private function queryConfig(string $type, ?int $enterpriseId): ?PricingConfigModel
    {
        $q = PricingConfigModel::where('type', $type);
        if ($enterpriseId) {
            $q->where('enterpriseId', $enterpriseId);
        } else {
            $q->whereNull('enterpriseId');
        }
        return $q->find();
    }
}
