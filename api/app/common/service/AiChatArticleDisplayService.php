<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 神仙 AI 页 · 推荐文章区块展示（超管可配）
 *
 * system_config.key = ai_chat_articles, enterprise_id = 0
 * JSON: enabled, maxShow(1-3), sectionExpandedDefault, profileRecoEnabled, profileSectionLabel,
 *       recoJumpMiniAppId, recoJumpMiniPath, recoJumpMiniEnvVersion,
 *       inlineRecoMinUserTurns(1-10 从第几条用户消息起可抽检), inlineRecoInterval(2-10 间隔),
 *       inlineRecoRoll(0-1 抽检概率), inlineRecoIconCount(1-3), inlineRecoIcons(string[] emoji)
 */
class AiChatArticleDisplayService
{
    public const CONFIG_KEY = 'ai_chat_articles';

    /** 我的页推荐条默认标题（超管「小程序 · 推荐文章展示」可改） */
    public const DEFAULT_PROFILE_SECTION_LABEL = '我的由来';

    /** 未在库中配置时：精选推荐点击跳转「一场 soul / 双赢实验」同源小程序（可在后台清空 AppID 关闭跳转） */
    public const DEFAULT_RECO_JUMP_APP_ID = 'wxb8bbb2b10dec74aa';

    public const DEFAULT_RECO_JUMP_PATH = 'pages/index/index';

    private const ENV_VERSIONS = ['release', 'trial', 'develop'];

    public static function normalizeRecoJumpAppId(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return '';
        }
        return preg_match('/^wx[0-9a-f]{16}$/', $s) ? $s : '';
    }

    public static function normalizeRecoJumpPath(string $raw): string
    {
        $s = trim($raw);
        $s = ltrim($s, '/');
        if ($s === '') {
            return self::DEFAULT_RECO_JUMP_PATH;
        }
        if (strlen($s) > 512) {
            $s = substr($s, 0, 512);
        }
        if (str_contains($s, '..')) {
            return self::DEFAULT_RECO_JUMP_PATH;
        }

        return $s;
    }

    public static function normalizeRecoJumpEnvVersion(string $raw): string
    {
        $s = strtolower(trim($raw));
        if (in_array($s, self::ENV_VERSIONS, true)) {
            return $s;
        }

        return 'release';
    }

    /**
     * @return array{
     *   enabled:bool,maxShow:int,sectionExpandedDefault:bool,profileRecoEnabled:bool,profileSectionLabel:string,
     *   recoJumpMiniAppId:string,recoJumpMiniPath:string,recoJumpMiniEnvVersion:string
     * }
     */
    public static function getSettings(): array
    {
        // 无库表记录时：神仙 AI 首屏最多 3 条推荐，默认折叠精选条
        $defaults = [
            'enabled'                  => true,
            'maxShow'                  => 3,
            'sectionExpandedDefault'   => false,
            'profileRecoEnabled'       => false,
            'profileSectionLabel'      => self::DEFAULT_PROFILE_SECTION_LABEL,
            'recoJumpMiniAppId'        => self::DEFAULT_RECO_JUMP_APP_ID,
            'recoJumpMiniPath'         => self::DEFAULT_RECO_JUMP_PATH,
            'recoJumpMiniEnvVersion'   => 'release',
            'inlineRecoMinUserTurns'   => 2,
            'inlineRecoInterval'       => 3,
            'inlineRecoRoll'           => 0.5,
            'inlineRecoIconCount'      => 3,
            'inlineRecoIcons'          => ['✨', '💬', '📌'],
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

        // 未配置该键时默认折叠；仅当库中显式为真时展开
        $sectionExpanded = isset($v['sectionExpandedDefault'])
            ? !empty($v['sectionExpandedDefault'])
            : false;

        // 跳转小程序：库中无键时用默认 AppID；键存在且为空字符串表示运营主动关闭跳转
        if (array_key_exists('recoJumpMiniAppId', $v)) {
            $jumpApp = self::normalizeRecoJumpAppId((string) $v['recoJumpMiniAppId']);
        } else {
            $jumpApp = self::normalizeRecoJumpAppId(self::DEFAULT_RECO_JUMP_APP_ID);
        }
        $jumpPath = isset($v['recoJumpMiniPath'])
            ? self::normalizeRecoJumpPath((string) $v['recoJumpMiniPath'])
            : self::DEFAULT_RECO_JUMP_PATH;
        $jumpEnv = isset($v['recoJumpMiniEnvVersion'])
            ? self::normalizeRecoJumpEnvVersion((string) $v['recoJumpMiniEnvVersion'])
            : 'release';

        $minTurns = (int) ($v['inlineRecoMinUserTurns'] ?? $defaults['inlineRecoMinUserTurns']);
        if ($minTurns < 1) {
            $minTurns = 1;
        }
        if ($minTurns > 10) {
            $minTurns = 10;
        }

        $interval = (int) ($v['inlineRecoInterval'] ?? $defaults['inlineRecoInterval']);
        if ($interval < 2) {
            $interval = 2;
        }
        if ($interval > 10) {
            $interval = 10;
        }

        $roll = isset($v['inlineRecoRoll']) ? (float) $v['inlineRecoRoll'] : (float) $defaults['inlineRecoRoll'];
        if ($roll < 0.05) {
            $roll = 0.05;
        }
        if ($roll > 1.0) {
            $roll = 1.0;
        }

        $iconCount = (int) ($v['inlineRecoIconCount'] ?? $defaults['inlineRecoIconCount']);
        if ($iconCount < 1) {
            $iconCount = 1;
        }
        if ($iconCount > 3) {
            $iconCount = 3;
        }

        $icons = self::normalizeInlineRecoIcons($v['inlineRecoIcons'] ?? null, $iconCount);

        return [
            'enabled'                  => !empty($v['enabled']),
            'maxShow'                  => $maxShow,
            'sectionExpandedDefault'   => $sectionExpanded,
            'profileRecoEnabled'       => !empty($v['profileRecoEnabled']),
            'profileSectionLabel'      => $profileLabel,
            'recoJumpMiniAppId'        => $jumpApp,
            'recoJumpMiniPath'         => $jumpPath,
            'recoJumpMiniEnvVersion'   => $jumpEnv,
            'inlineRecoMinUserTurns'   => $minTurns,
            'inlineRecoInterval'       => $interval,
            'inlineRecoRoll'           => $roll,
            'inlineRecoIconCount'      => $iconCount,
            'inlineRecoIcons'          => $icons,
        ];
    }

    /**
     * @param mixed $raw JSON 数组或逗号分隔字符串
     * @return string[]
     */
    public static function normalizeInlineRecoIcons($raw, int $iconCount): array
    {
        $iconCount = max(1, min(3, $iconCount));
        $defaults  = ['✨', '💬', '📌', '💼', '📝'];
        $list      = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $s = trim((string) $item);
                if ($s !== '' && !in_array($s, $list, true)) {
                    $list[] = $s;
                }
                if (count($list) >= 10) {
                    break;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            foreach (preg_split('/[,，\s]+/u', $raw) as $part) {
                $s = trim($part);
                if ($s !== '' && !in_array($s, $list, true)) {
                    $list[] = $s;
                }
                if (count($list) >= 10) {
                    break;
                }
            }
        }
        if (empty($list)) {
            $list = $defaults;
        }
        foreach ($defaults as $d) {
            if (count($list) >= $iconCount) {
                break;
            }
            if (!in_array($d, $list, true)) {
                $list[] = $d;
            }
        }

        return array_slice($list, 0, $iconCount);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   enabled:bool,maxShow:int,sectionExpandedDefault:bool,profileRecoEnabled:bool,profileSectionLabel:string,
     *   recoJumpMiniAppId:string,recoJumpMiniPath:string,recoJumpMiniEnvVersion:string
     * }
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

        $jumpApp = isset($input['recoJumpMiniAppId'])
            ? self::normalizeRecoJumpAppId((string) $input['recoJumpMiniAppId'])
            : '';
        $jumpPath = isset($input['recoJumpMiniPath'])
            ? self::normalizeRecoJumpPath((string) $input['recoJumpMiniPath'])
            : self::DEFAULT_RECO_JUMP_PATH;
        $jumpEnv = isset($input['recoJumpMiniEnvVersion'])
            ? self::normalizeRecoJumpEnvVersion((string) $input['recoJumpMiniEnvVersion'])
            : 'release';

        $minTurns = (int) ($input['inlineRecoMinUserTurns'] ?? 2);
        $minTurns = max(1, min(10, $minTurns));
        $interval = (int) ($input['inlineRecoInterval'] ?? 3);
        $interval = max(2, min(10, $interval));
        $roll     = isset($input['inlineRecoRoll']) ? (float) $input['inlineRecoRoll'] : 0.5;
        $roll     = max(0.05, min(1.0, $roll));
        $iconCnt  = (int) ($input['inlineRecoIconCount'] ?? 3);
        $iconCnt  = max(1, min(3, $iconCnt));
        $icons    = self::normalizeInlineRecoIcons($input['inlineRecoIcons'] ?? null, $iconCnt);

        $settings = [
            'enabled'                  => !empty($input['enabled']),
            'maxShow'                  => max(1, min(3, (int) ($input['maxShow'] ?? 1))),
            'sectionExpandedDefault'   => !empty($input['sectionExpandedDefault']),
            'profileRecoEnabled'       => !empty($input['profileRecoEnabled']),
            'profileSectionLabel'      => $rawLabel,
            'recoJumpMiniAppId'        => $jumpApp,
            'recoJumpMiniPath'         => $jumpPath,
            'recoJumpMiniEnvVersion'   => $jumpEnv,
            'inlineRecoMinUserTurns'   => $minTurns,
            'inlineRecoInterval'       => $interval,
            'inlineRecoRoll'           => $roll,
            'inlineRecoIconCount'      => $iconCnt,
            'inlineRecoIcons'          => $icons,
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
