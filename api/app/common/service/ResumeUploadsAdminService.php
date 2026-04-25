<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 管理后台用户详情：企业版上传的简历文件（mbti_enterprise_resume_uploads）
 */
final class ResumeUploadsAdminService
{
    /**
     * @param int      $wechatUserId        wechat_users.id
     * @param int|null $scopeEnterpriseId  非空时仅企业后台：该企业的记录；且包含「未写 enterpriseId 但用户已绑定该企业」的上传
     * @return list<array{id:int, enterpriseId: int|null, enterpriseName: string, fileName: string, url: string, isDefault: bool, uploadedAt: int|null}>
     */
    public static function listForWechatUser(int $wechatUserId, ?int $scopeEnterpriseId): array
    {
        if ($wechatUserId <= 0) {
            return [];
        }
        try {
            if ($scopeEnterpriseId !== null && (int) $scopeEnterpriseId > 0) {
                $eid = (int) $scopeEnterpriseId;
                $byE = Db::name('enterprise_resume_uploads')->alias('r')
                    ->leftJoin('enterprises e', 'e.id = r.enterpriseId')
                    ->where('r.userId', $wechatUserId)
                    ->where('r.enterpriseId', $eid)
                    ->field('r.id,r.enterpriseId,r.fileUrl,r.fileName,r.is_default,r.createdAt,e.name as enterpriseName')
                    ->order('r.createdAt', 'desc')
                    ->select()
                    ->toArray();
                $byNull = [];
                $boundE = (int) (Db::name('wechat_users')->where('id', $wechatUserId)->value('enterpriseId') ?? 0);
                if ($boundE === $eid) {
                    $byNull = Db::name('enterprise_resume_uploads')->alias('r')
                        ->leftJoin('enterprises e', 'e.id = r.enterpriseId')
                        ->where('r.userId', $wechatUserId)
                        ->whereNull('r.enterpriseId')
                        ->field('r.id,r.enterpriseId,r.fileUrl,r.fileName,r.is_default,r.createdAt,e.name as enterpriseName')
                        ->order('r.createdAt', 'desc')
                        ->select()
                        ->toArray();
                }
                $rows = self::mergeRowsById($byE, $byNull);
            } else {
                $rows = Db::name('enterprise_resume_uploads')->alias('r')
                    ->leftJoin('enterprises e', 'e.id = r.enterpriseId')
                    ->where('r.userId', $wechatUserId)
                    ->field('r.id,r.enterpriseId,r.fileUrl,r.fileName,r.is_default,r.createdAt,e.name as enterpriseName')
                    ->order('r.createdAt', 'desc')
                    ->select()
                    ->toArray();
            }
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapRows($rows);
    }

    /**
     * @param list<array> $a
     * @param list<array> $b
     * @return list<array>
     */
    private static function mergeRowsById(array $a, array $b): array
    {
        $byId = [];
        foreach (array_merge($a, $b) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $row;
            }
        }
        $out = array_values($byId);
        usort($out, static function ($x, $y) {
            $ax = (int) ($x['createdAt'] ?? 0);
            $ay = (int) ($y['createdAt'] ?? 0);

            return $ay <=> $ax;
        });

        return $out;
    }

    private static function mapRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $ts = (int) ($row['createdAt'] ?? 0);
            $out[] = [
                'id'             => (int) ($row['id'] ?? 0),
                'enterpriseId'   => isset($row['enterpriseId']) && $row['enterpriseId'] !== null && (int) $row['enterpriseId'] > 0
                    ? (int) $row['enterpriseId'] : null,
                'enterpriseName' => (string) ($row['enterpriseName'] ?? ''),
                'fileName'       => (string) ($row['fileName'] ?? ''),
                'url'            => (string) ($row['fileUrl'] ?? ''),
                'isDefault'      => (int) ($row['is_default'] ?? 0) === 1,
                'uploadedAt'     => $ts > 1000000000 ? $ts : null,
            ];
        }

        return $out;
    }
}
