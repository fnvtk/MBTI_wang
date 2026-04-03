<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 第三方渠道透传：userid / phone / tid 解析与 wechat_users 写入（仅存用户表）
 */
class ThirdPartyChannelService
{
    public static function parseFromRequestArray(array $input): array
    {
        $tp = $input['thirdParty'] ?? null;
        if (!is_array($tp)) {
            return ['userid' => '', 'phone' => '', 'tid' => ''];
        }
        $userid = isset($tp['userid']) ? trim((string) $tp['userid']) : '';
        $phone  = isset($tp['phone']) ? trim((string) $tp['phone']) : '';
        $tid    = isset($tp['tid']) ? trim((string) $tp['tid']) : '';
        return [
            'userid' => $userid,
            'phone'  => $phone,
            'tid'    => $tid,
        ];
    }

    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) > 11 && substr($digits, 0, 2) === '86') {
            $digits = substr($digits, -11);
        }
        if (strlen($digits) < 5 || strlen($digits) > 20) {
            return null;
        }
        return $digits;
    }

    /**
     * 按主手机号列查找用户（规范化后比对）
     *
     * @return array|null 表行
     */
    public static function findUserByNormalizedPhone(string $norm): ?array
    {
        if ($norm === '') {
            return null;
        }
        $row = Db::name('wechat_users')->where('phone', $norm)->find();
        if ($row) {
            return $row;
        }
        $candidates = Db::name('wechat_users')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->where('phone', 'like', '%' . $norm)
            ->limit(50)
            ->select()
            ->toArray();
        foreach ($candidates as $r) {
            if (self::normalizePhone($r['phone'] ?? '') === $norm) {
                return $r;
            }
        }
        return null;
    }

    /**
     * 合并第三方字段到指定用户行（有传则写）
     * - 有渠道 phone：始终更新 third_party_phone（含老用户命中手机号与透传一致时）
     * - 主字段 phone 为空时：用渠道号码写入 phone（新号、未绑手机用户）
     */
    public static function applyToUserId(int $userId, array $thirdParty): void
    {
        if ($userId <= 0) {
            return;
        }
        $data = [];
        if ($thirdParty['userid'] !== '') {
            $data['ext_uid'] = mb_substr($thirdParty['userid'], 0, 191);
        }
        if ($thirdParty['tid'] !== '') {
            $data['third_party_tid'] = mb_substr($thirdParty['tid'], 0, 191);
        }

        $norm = null;
        if ($thirdParty['phone'] !== '') {
            $norm = self::normalizePhone($thirdParty['phone']);
        }
        if ($norm !== null) {
            $data['third_party_phone'] = mb_substr($norm, 0, 32);
            $row       = Db::name('wechat_users')->where('id', $userId)->field('phone')->find();
            $mainPhone = trim((string) ($row['phone'] ?? ''));
            if ($mainPhone === '') {
                $data['phone'] = mb_substr($norm, 0, 20);
            }
        }

        if (empty($data)) {
            return;
        }
        $data['updatedAt'] = time();
        Db::name('wechat_users')->where('id', $userId)->update($data);
    }

}
