<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 企业版：三项完成判定 + 合作模式配置与用户选择
 */
class EnterpriseCooperationService
{
    public const MODE_SALARY         = 'salary';
    public const MODE_STARTUP_EQUITY = 'startup_equity';
    public const MODE_KNOWLEDGE_PAY  = 'knowledge_pay';

    /** @return array<string, array{title:string,description:string}> */
    public static function builtinModeDefs(): array
    {
        return [
            self::MODE_SALARY => [
                'title'       => '工资',
                'description' => '全职/薪资导向合作',
            ],
            self::MODE_STARTUP_EQUITY => [
                'title'       => '创业分红',
                'description' => '合伙/股权激励类合作',
            ],
            self::MODE_KNOWLEDGE_PAY => [
                'title'       => '知识付费',
                'description' => '课程/咨询/付费内容类合作',
            ],
        ];
    }

    public static function validModeCodes(): array
    {
        return array_keys(self::builtinModeDefs());
    }

    /** 与表字段 modeCode varchar(32) 一致：小写字母/数字/下划线 */
    public static function normalizeModeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    public static function isValidModeCodeString(string $code): bool
    {
        if ($code === '' || strlen($code) > 32) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9_]+$/', $code);
    }

    public static function getWechatEnterpriseId(int $wechatUserId): ?int
    {
        $row = Db::name('wechat_users')->where('id', $wechatUserId)->field('enterpriseId')->find();
        if (!$row) {
            return null;
        }
        $eid = (int) ($row['enterpriseId'] ?? 0);

        return $eid > 0 ? $eid : null;
    }

    /**
     * 简历环节：该企业下有上传记录，或有 enterpriseId 匹配的 resume 测评记录
     */
    public static function resumeDone(int $userId, int $enterpriseId): bool
    {
        $n = (int) Db::name('enterprise_resume_uploads')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->count();
        if ($n > 0) {
            return true;
        }

        return (int) Db::name('test_results')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->where('testType', 'resume')
            ->count() > 0;
    }

    /**
     * 面相/人脸：testType in face|ai，且 enterpriseId 一致（首版 strictly 绑定企业维度）
     */
    public static function faceDone(int $userId, int $enterpriseId): bool
    {
        return (int) Db::name('test_results')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->whereIn('testType', ['face', 'ai'])
            ->count() > 0;
    }

    public static function mbtiDone(int $userId, int $enterpriseId): bool
    {
        return (int) Db::name('test_results')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->where('testType', 'mbti')
            ->count() > 0;
    }

    /** @return array{resumeDone:bool,faceDone:bool,mbtiDone:bool} */
    public static function onboardingFlags(int $userId, int $enterpriseId): array
    {
        return [
            'resumeDone' => self::resumeDone($userId, $enterpriseId),
            'faceDone'   => self::faceDone($userId, $enterpriseId),
            'mbtiDone'   => self::mbtiDone($userId, $enterpriseId),
        ];
    }

    /**
     * 新企业、尚无任意合作模式时，写入三条默认（仅当该企业 0 条记录，避免管理端删行后再次读列表又被补回）
     */
    public static function ensureDefaultConfigs(int $enterpriseId): void
    {
        $n = (int) Db::name('enterprise_cooperation_modes')
            ->where('enterpriseId', $enterpriseId)
            ->count();
        if ($n > 0) {
            return;
        }

        $defs = self::builtinModeDefs();
        $now  = time();
        foreach ($defs as $code => $meta) {
            Db::name('enterprise_cooperation_modes')->insert([
                'enterpriseId' => $enterpriseId,
                'modeCode'     => $code,
                'enabled'      => 1,
                'sortOrder'    => (int) (array_search($code, array_keys($defs), true) * 10),
                'title'        => $meta['title'],
                'description'  => $meta['description'],
                'createdAt'    => $now,
                'updatedAt'    => $now,
            ]);
        }
    }

    /**
     * @return list<array{code:string,title:string,description:string,sortOrder:int,enabled:bool}>
     */
    public static function listModesForEnterprise(int $enterpriseId, bool $onlyEnabled): array
    {
        self::ensureDefaultConfigs($enterpriseId);

        $q = Db::name('enterprise_cooperation_modes')
            ->where('enterpriseId', $enterpriseId)
            ->order('sortOrder', 'asc')
            ->order('id', 'asc');
        if ($onlyEnabled) {
            $q->where('enabled', 1);
        }
        $rows = $q->select()->toArray();
        $out  = [];
        $defs = self::builtinModeDefs();
        foreach ($rows as $row) {
            $code = (string) ($row['modeCode'] ?? '');
            if ($code === '' || !self::isValidModeCodeString($code)) {
                continue;
            }
            $def = $defs[$code] ?? null;
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '' && $def) {
                $title = $def['title'];
            }
            if ($title === '') {
                $title = '合作模式';
            }
            $desc = $row['description'] ?? null;
            $description = $desc !== null && (string) $desc !== ''
                ? (string) $desc
                : ($def['description'] ?? '');

            $out[] = [
                'code'        => $code,
                'title'       => $title,
                'description' => $description,
                'sortOrder'   => (int) ($row['sortOrder'] ?? 0),
                'enabled'     => (int) ($row['enabled'] ?? 0) === 1,
            ];
        }

        return $out;
    }

    /**
     * 批量写入配置（超管 / 企业管理员）
     *
     * @param list<array{modeCode:string,enabled?:bool,sortOrder?:int,title?:string,description?:string}> $modes
     */
    public static function saveConfigs(int $enterpriseId, array $modes): void
    {
        $defs = self::builtinModeDefs();
        $now  = time();

        $byCode = [];
        foreach ($modes as $item) {
            $raw  = (string) ($item['modeCode'] ?? $item['code'] ?? '');
            $code = self::normalizeModeCode($raw);
            if ($code === '' || !self::isValidModeCodeString($code)) {
                continue;
            }
            $byCode[$code] = $item;
        }
        if ($byCode === []) {
            throw new \InvalidArgumentException('请至少保存一条合作模式，且模式代码为 1–32 位小写字母、数字或下划线');
        }

        $codes = array_keys($byCode);

        Db::startTrans();
        try {
            Db::name('enterprise_cooperation_modes')
                ->where('enterpriseId', $enterpriseId)
                ->whereNotIn('modeCode', $codes)
                ->delete();

            foreach ($byCode as $code => $item) {
                $row = Db::name('enterprise_cooperation_modes')
                    ->where('enterpriseId', $enterpriseId)
                    ->where('modeCode', $code)
                    ->find();

                $def = $defs[$code] ?? null;
                $t   = trim((string) ($item['title'] ?? ''));
                $title = $t !== '' ? $t : ($def['title'] ?? '合作模式');
                $description = array_key_exists('description', $item)
                    ? (string) $item['description']
                    : ($def['description'] ?? '');

                $payload = [
                    'enabled'     => isset($item['enabled']) ? ((int) (bool) $item['enabled']) : 1,
                    'sortOrder'   => isset($item['sortOrder']) ? (int) $item['sortOrder'] : 0,
                    'title'       => $title,
                    'description' => $description,
                    'updatedAt'   => $now,
                ];
                if ($row) {
                    Db::name('enterprise_cooperation_modes')
                        ->where('id', (int) $row['id'])
                        ->update($payload);
                } else {
                    $payload['enterpriseId'] = $enterpriseId;
                    $payload['modeCode']     = $code;
                    $payload['createdAt']    = $now;
                    Db::name('enterprise_cooperation_modes')->insert($payload);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function getUserChoice(int $userId, int $enterpriseId): ?array
    {
        $row = Db::name('user_cooperation_choices')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->find();

        return $row ?: null;
    }

    public static function saveUserChoice(int $userId, int $enterpriseId, string $modeCode): void
    {
        $modeCode = self::normalizeModeCode($modeCode);
        $now      = time();
        $row = Db::name('user_cooperation_choices')
            ->where('userId', $userId)
            ->where('enterpriseId', $enterpriseId)
            ->find();
        if ($row) {
            Db::name('user_cooperation_choices')
                ->where('id', (int) $row['id'])
                ->update([
                    'modeCode'  => $modeCode,
                    'chosenAt'  => $now,
                    'updatedAt' => $now,
                ]);
        } else {
            Db::name('user_cooperation_choices')->insert([
                'userId'       => $userId,
                'enterpriseId' => $enterpriseId,
                'modeCode'     => $modeCode,
                'chosenAt'     => $now,
                'updatedAt'    => $now,
            ]);
        }
    }

    public static function isModeEnabledForEnterprise(int $enterpriseId, string $modeCode): bool
    {
        $code = self::normalizeModeCode($modeCode);
        $row  = Db::name('enterprise_cooperation_modes')
            ->where('enterpriseId', $enterpriseId)
            ->where('modeCode', $code)
            ->find();
        if (!$row) {
            return false;
        }

        return (int) ($row['enabled'] ?? 0) === 1;
    }

    /**
     * 管理端：某企业下用户合作意向查询（带筛选）
     */
    private static function userCooperationChoicesBaseQuery(int $enterpriseId, string $keyword = '')
    {
        $q = Db::name('user_cooperation_choices')->alias('uc')
            ->join('wechat_users w', 'uc.userId = w.id')
            ->leftJoin('enterprise_cooperation_modes ecm', 'ecm.enterpriseId = uc.enterpriseId AND ecm.modeCode = uc.modeCode')
            ->leftJoin('enterprises ent', 'ent.id = uc.enterpriseId')
            ->where('uc.enterpriseId', $enterpriseId);
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $q->where(function ($query) use ($like) {
                $query->where('w.nickname', 'like', $like)
                    ->whereOr('w.phone', 'like', $like)
                    ->whereOr('uc.modeCode', 'like', $like);
            });
        }

        return $q;
    }

    private static function mapUserCooperationChoiceRow(array $row): array
    {
        $ca = (int) ($row['chosenAt'] ?? 0);
        $ua = (int) ($row['updatedAt'] ?? 0);

        return [
            'id'              => (int) ($row['id'] ?? 0),
            'userId'          => (int) ($row['userId'] ?? 0),
            'enterpriseId'    => (int) ($row['enterpriseId'] ?? 0),
            'enterpriseName'  => (string) ($row['enterpriseName'] ?? ''),
            'modeCode'        => (string) ($row['modeCode'] ?? ''),
            'modeTitle'       => (string) ($row['modeTitle'] ?? ''),
            'nickname'        => (string) ($row['nickname'] ?? ''),
            'phone'           => (string) ($row['phone'] ?? ''),
            'chosenAt'        => $ca,
            'updatedAt'       => $ua,
            'chosenAtText'    => $ca > 0 ? date('Y-m-d H:i:s', $ca) : '',
            'updatedAtText'   => $ua > 0 ? date('Y-m-d H:i:s', $ua) : '',
        ];
    }

    /**
     * @return array{list: list<array>, total: int}
     */
    public static function listUserCooperationChoices(int $enterpriseId, int $page, int $pageSize, string $keyword = ''): array
    {
        $page     = max(1, $page);
        $pageSize = min(max($pageSize, 1), 100);

        $field = 'uc.id,uc.userId,uc.enterpriseId,uc.modeCode,uc.chosenAt,uc.updatedAt,'
            . 'w.nickname,w.phone,ecm.title as modeTitle,ent.name as enterpriseName';

        $total = (int) self::userCooperationChoicesBaseQuery($enterpriseId, $keyword)->count();
        $rows  = self::userCooperationChoicesBaseQuery($enterpriseId, $keyword)
            ->field($field)
            ->order('uc.chosenAt', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();
        $list = [];
        foreach ($rows as $row) {
            $list[] = self::mapUserCooperationChoiceRow($row);
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @return list<array>
     */
    public static function listUserCooperationChoicesForExport(int $enterpriseId, string $keyword = '', int $maxRows = 10000): array
    {
        $field = 'uc.id,uc.userId,uc.enterpriseId,uc.modeCode,uc.chosenAt,uc.updatedAt,'
            . 'w.nickname,w.phone,ecm.title as modeTitle,ent.name as enterpriseName';
        $rows = self::userCooperationChoicesBaseQuery($enterpriseId, $keyword)
            ->field($field)
            ->order('uc.chosenAt', 'desc')
            ->limit(max(1, min($maxRows, 50000)))
            ->select()
            ->toArray();
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::mapUserCooperationChoiceRow($row);
        }

        return $out;
    }

    public static function buildUserCooperationChoicesCsvContent(array $rows, bool $includeEnterpriseColumns): string
    {
        $lines   = [];
        $headers = $includeEnterpriseColumns
            ? ['企业ID', '企业名称', '用户ID', '微信昵称', '手机号', '模式代码', '模式标题', '选择时间', '更新时间']
            : ['用户ID', '微信昵称', '手机号', '模式代码', '模式标题', '选择时间', '更新时间'];
        $lines[] = self::csvLine($headers);
        foreach ($rows as $r) {
            if ($includeEnterpriseColumns) {
                $lines[] = self::csvLine([
                    (string) ($r['enterpriseId'] ?? ''),
                    (string) ($r['enterpriseName'] ?? ''),
                    (string) ($r['userId'] ?? ''),
                    (string) ($r['nickname'] ?? ''),
                    (string) ($r['phone'] ?? ''),
                    (string) ($r['modeCode'] ?? ''),
                    (string) ($r['modeTitle'] ?? ''),
                    (string) ($r['chosenAtText'] ?? ''),
                    (string) ($r['updatedAtText'] ?? ''),
                ]);
            } else {
                $lines[] = self::csvLine([
                    (string) ($r['userId'] ?? ''),
                    (string) ($r['nickname'] ?? ''),
                    (string) ($r['phone'] ?? ''),
                    (string) ($r['modeCode'] ?? ''),
                    (string) ($r['modeTitle'] ?? ''),
                    (string) ($r['chosenAtText'] ?? ''),
                    (string) ($r['updatedAtText'] ?? ''),
                ]);
            }
        }

        return implode("\r\n", $lines);
    }

    private static function csvLine(array $cells): string
    {
        $parts = [];
        foreach ($cells as $c) {
            $s = (string) $c;
            $s = str_replace('"', '""', $s);
            $parts[] = '"' . $s . '"';
        }

        return implode(',', $parts);
    }
}
