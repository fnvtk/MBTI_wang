<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * AI服务商配置模型
 */
class AiProvider extends Model
{
    use SoftDelete;

    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'ai_providers';

    // 软删除字段（驼峰命名，时间戳格式）
    protected $deleteTime = 'deletedAt';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'                    => 'int',
        'providerId'            => 'string',
        'name'                  => 'string',
        'enabled'               => 'int',
        'visible'               => 'int',
        'apiKey'                => 'string',
        'apiEndpoint'           => 'string',
        'model'                 => 'string',
        'organizationId'        => 'string',
        'maxTokens'             => 'int',
        'balanceAlertEnabled'   => 'int',
        'balanceAlertThreshold' => 'float',
        'notes'                 => 'string',
        'docUrl'                => 'string',
        'isFree'                => 'int',
        'supportsBalance'        => 'int',
        'lastBalance'           => 'float',
        'lastBalanceCurrency'   => 'string',
        'lastBalanceCheckedAt'  => 'int',
        'createdAt'             => 'int',
        'updatedAt'             => 'int',
        'deletedAt'             => 'int',
        'extraConfig'           => 'string',
        'sortWeight'            => 'int',
    ];

    // 自动时间戳（使用驼峰命名，时间戳格式）
    protected $autoWriteTimestamp = 'int';
    
    // 时间戳字段名（驼峰命名，匹配数据库）
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    // 时间字段类型（时间戳格式）
    protected $type = [
        'lastBalanceCheckedAt' => 'integer',
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
        'deletedAt' => 'integer',
        'extraConfig' => 'json',
    ];

    // 注意：API Key需要可逆读取用于API调用，所以不隐藏，但在获取器中脱敏

    /**
     * API Key 修改器（存储原始值，用于API调用）
     */
    public function setApiKeyAttr($value)
    {
        if (empty($value)) {
            return null;
        }
        // 如果输入的是脱敏格式（包含****），不更新
        if (strpos($value, '****') !== false) {
            return null; // 返回null表示不更新此字段
        }
        // 直接存储原始值（实际生产环境建议使用AES加密）
        return $value;
    }

    /**
     * API Key 获取器（返回脱敏后的密钥）
     * 注意：如果需要原始密钥用于API调用，使用 getRawApiKey() 方法
     */
    public function getApiKeyAttr($value)
    {
        if (empty($value)) {
            return '';
        }
        // 返回脱敏后的密钥（显示前6位和后4位）
        if (strlen($value) > 10) {
            return substr($value, 0, 6) . '****' . substr($value, -4);
        }
        return '****';
    }

    /**
     * 获取原始API Key（用于API调用）
     * @return string
     */
    public function getRawApiKey()
    {
        // 直接从数据库读取原始值，绕过获取器
        return \think\facade\Db::name('ai_providers')
            ->where('id', $this->id)
            ->value('apiKey') ?: '';
    }
}

