<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 神仙 AI 页 · 推荐文章区块展示（超管可配）
 *
 * system_config.key = ai_chat_articles, enterprise_id = 0
 * JSON: { enabled, maxShow(1-3), sectionExpandedDefault, profileRecoEnabled, profileSectionLabel }
 */
class AiChatArticleDisplayService
{
    public const CONFIG_KEY = 'ai_chat_articles';

    /** 我的页推荐条默认标题（后台可改） */
    public const DEFAULT_PROFILE_SECTION_LABEL = '推荐阅读';

    /**
     * @return array{enabled:bool,maxShow:int,sectionExpandedDefault:bool,profileRecoEnabled:bool,profileSectionLabel:string}
     */
    public static function getSettings(): array
    {
        $defaults = [
            'enabled'                  => false,
            'maxShow'                  => 1,
            'sectionExpandedDefault'   => false,
            'profileRecoEnabled'       => false,
            'profileSectionLabel'      => self::DEFAULT_PROFILE_SECTION_LABEL,
        ];
        $row = Db::name('system_config')
            ->where('key', self::CONFIG_KEY)
            ->where('enterprise_id', 0)
            ->find();
        if (!$row || empty($row['value'])) {
            return $defaults;
        }
        $v = is_string($row['value']) ? json_decode($row['value'], true) : $row['value'];
        if (!is_array($v)) {
            return $defaults;
        }
        $maxShow = (int) ($v['maxShow'] ?? 1);
        if ($maxShow < 1) {
            $maxShow = 1;
        }
        if ($maxShow > 3) {
            $maxShow = 3;
        }

        $profileLabel = isset($v['profileSectionLabel']) ? trim((string) $v['profileSectionLabel']) : '';
        if ($profileLabel === '') {
            $profileLabel = self::DEFAULT_PROFILE_SECTION_LABEL;
        }
        if (function_exists('mb_substr')) {
            $profileLabel = mb_substr($profileLabel, 0, 32, 'UTF-8');
        } else {
            $profileLabel = substr($profileLabel, 0, 32);
        }

        return [
            'enabled'                  => !empty($v['enabled']),
            'maxShow'                  => $maxShow,
            'sectionExpandedDefault'   => !empty($v['sectionExpandedDefault']),
            'profileRecoEnabled'       => !empty($v['profileRecoEnabled']),
            'profileSectionLabel'      => $profileLabel,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{enabled:bool,maxShow:int,sectionExpandedDefault:bool,profileRecoEnabled:bool,profileSectionLabel:string}
     */
    public static function saveSettings(array $input): array
    {
        $rawLabel = isset($input['profileSectionLabel']) ? trim((string) $input['profileSectionLabel']) : '';
        if ($rawLabel === '') {
            $rawLabel = self::DEFAULT_PROFILE_SECTION_LABEL;
        }
        if (function_exists('mb_substr')) {
            $rawLabel = mb_substr($rawLabel, 0, 32, 'UTF-8');
        } else {
            $rawLabel = substr($rawLabel, 0, 32);
        }

        $settings = [
            'enabled'                  => !empty($input['enabled']),
            'maxShow'                  => max(1, min(3, (int) ($input['maxShow'] ?? 1))),
            'sectionExpandedDefault'   => !empty($input['sectionExpandedDefault']),
            'profileRecoEnabled'       => !empty($input['profileRecoEnabled']),
            'profileSectionLabel'      => $rawLabel,
        ];
        $json  = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $now   = time();
        $exists = Db::name('system_config')
            ->where('key', self::CONFIG_KEY)
            ->where('enterprise_id', 0)
            ->find();
        if ($exists) {
            Db::name('system_config')
                ->where('key', self::CONFIG_KEY)
                ->where('enterprise_id', 0)
                ->update(['value' => $json, 'updatedAt' => $now]);
        } else {
            Db::name('system_config')->insert([
                'key'           => self::CONFIG_KEY,
                'enterprise_id' => 0,
                'value'         => $json,
                'description'   => '神仙 AI / 我的页：推荐文章展示配置',
                'createdAt'     => $now,
                'updatedAt'     => $now,
            ]);
        }

        return $settings;
    }
}
