<?php
namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 企业模型
 */
class Enterprise extends Model
{
    use SoftDelete;

    // 设置表名（不带前缀，前缀在数据库配置中设置）
    protected $name = 'enterprises';

    // 软删除字段（驼峰命名，时间戳格式）
    protected $deleteTime = 'deletedAt';

    // 设置字段信息（匹配数据库字段命名：驼峰命名）
    protected $schema = [
        'id'            => 'int',
        'name'          => 'string',
        'code'          => 'string',
        'contactName'   => 'string',
        'contactPhone'  => 'string',
        'contactEmail'  => 'string',
        'balance'       => 'float',
        'status'        => 'string',
        'permissions'        => 'json',
        'permissionsCeiling' => 'json',
        'trialExpireAt' => 'int',
        'deletedAt'     => 'int',
        'createdAt'     => 'int',
        'updatedAt'     => 'int',
    ];

    // 自动时间戳（使用驼峰命名，时间戳格式）
    protected $autoWriteTimestamp = 'int';
    
    // 时间戳字段名（驼峰命名，匹配数据库）
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    // 时间字段类型（时间戳格式）
    protected $type = [
        'permissions'        => 'json',
        'permissionsCeiling' => 'json',
        'trialExpireAt' => 'integer',
        'deletedAt' => 'integer',
        'createdAt' => 'integer',
        'updatedAt' => 'integer',
    ];

    /** 权限键默认值（库中为 null / 空 / 缺列 / 解析失败时一律回落至此） */
    public static function permissionDefaults(): array
    {
        return [
            'face' => true,
            'mbti' => true,
            'pdp'  => true,
            'disc' => true,
            'distribution' => true,
        ];
    }

    /**
     * 将库中任意形态的 permissions 规范化为固定键数组（兼容 null、''、{}、[]、非法 JSON、双重 JSON 字符串）
     * @param mixed $value 原始列值
     */
    public static function normalizePermissionsValue($value): array
    {
        $defaults = self::permissionDefaults();
        if ($value === null) {
            return $defaults;
        }
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '' || strcasecmp($trim, 'null') === 0) {
                return $defaults;
            }
            $decoded = json_decode($trim, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $defaults;
            }
            $value = $decoded;
        }
        // 双重编码：第一次 decode 后仍是 JSON 字符串
        if (is_string($value)) {
            $trim = trim($value);
            $decoded = $trim === '' ? [] : json_decode($trim, true);
            $value = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        if (is_object($value)) {
            $value = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        }
        if (!is_array($value)) {
            return $defaults;
        }
        return array_merge($defaults, array_intersect_key($value, $defaults));
    }

    /**
     * 获取规范化权限（未设置的键默认 true）
     */
    public function getNormalizedPermissions(): array
    {
        return self::normalizePermissionsValue($this->permissions);
    }

    /**
     * 超管授权上限（未单独配置时回落为当前 permissions，兼容未跑迁移库）
     *
     * @param self|array<string,mixed>|null $enterprise 模型或 toArray 行
     */
    public static function normalizedPermissionsCeiling($enterprise): array
    {
        if ($enterprise instanceof self) {
            $raw = $enterprise->getAttr('permissionsCeiling');
            if ($raw === null || $raw === '') {
                $raw = $enterprise->getAttr('permissions');
            }

            return self::normalizePermissionsValue($raw);
        }
        if (is_array($enterprise)) {
            $raw = $enterprise['permissionsCeiling'] ?? null;
            if ($raw === null || $raw === '') {
                $raw = $enterprise['permissions'] ?? null;
            }

            return self::normalizePermissionsValue($raw);
        }

        return self::permissionDefaults();
    }

    /**
     * 在超管上限内合并企业管理员提交的开关
     */
    public static function clampPermissionsToCeiling(array $ceiling, array $requested): array
    {
        $req = self::normalizePermissionsValue($requested);
        $out = [];
        foreach (array_keys(self::permissionDefaults()) as $k) {
            $out[$k] = !empty($ceiling[$k]) && !empty($req[$k]);
        }

        return $out;
    }

    /**
     * 超管收紧上限后，同步收缩已生效 permissions（关掉的项强制 false）
     */
    public static function clampEffectiveToNewCeiling(array $newCeiling, array $oldEffective): array
    {
        $old = self::normalizePermissionsValue($oldEffective);
        $out = [];
        foreach (array_keys(self::permissionDefaults()) as $k) {
            $out[$k] = !empty($newCeiling[$k]) && !empty($old[$k]);
        }

        return $out;
    }
}

