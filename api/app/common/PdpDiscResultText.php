<?php
namespace app\common;

/**
 * PDP / DISC 结果摘要：权重最高的 2 项。
 * 展示格式：DISC 为「D+I型」（仅最后一项带「型」）；PDP 为「孔雀+老虎型」。
 */
class PdpDiscResultText
{
    private const PDP_EN_TO_CN = [
        'Tiger'     => '老虎型',
        'Peacock'   => '孔雀型',
        'Koala'     => '无尾熊型',
        'Owl'       => '猫头鹰型',
        'Chameleon' => '变色龙型',
    ];

    /**
     * @param array<string,mixed> $data
     */
    public static function discTopTwo(array $data): string
    {
        [$fL, $sL] = self::discResolveTwoLetters($data);
        if ($fL !== '' || $sL !== '') {
            if ($fL === '') {
                return $sL !== '' ? ($sL . '型') : '';
            }
            if ($sL === '' || $sL === $fL) {
                return $fL . '型';
            }

            return $fL . '+' . $sL . '型';
        }

        return self::discNormalizeLegacyDualDescription($data['description']['type'] ?? null) ?? '';
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function pdpTopTwo(array $data): string
    {
        [$firstFull, $secondFull] = self::pdpResolveTwoFull($data);
        if ($firstFull === '') {
            return $secondFull;
        }
        if ($secondFull === '' || $secondFull === $firstFull) {
            return $firstFull;
        }
        $firstShort = preg_replace('/型$/u', '', $firstFull);

        return $firstShort . '+' . $secondFull;
    }

    /**
     * @return array{0:string,1:string} DISC 字母 D/I/S/C
     */
    private static function discResolveTwoLetters(array $data): array
    {
        $f = self::discPrimaryLetter($data);
        $s = '';
        $sk = $data['secondaryType'] ?? null;
        if (is_string($sk) && $sk !== '') {
            $u = strtoupper(substr(trim($sk), 0, 1));
            if (in_array($u, ['D', 'I', 'S', 'C'], true)) {
                $s = $u;
            }
        }
        $a = '';
        $b = '';
        if (isset($data['scores']) && is_array($data['scores'])) {
            [$a, $b] = self::discOrderedLetters($data['scores']);
        }
        if ($a === '' && $b === '' && isset($data['percentages']) && is_array($data['percentages'])) {
            [$a, $b] = self::discOrderedLetters($data['percentages']);
        }
        if ($f === '' && $a !== '') {
            $f = $a;
        }
        if ($s === '' || $s === $f) {
            if ($b !== '' && $b !== $f) {
                $s = $b;
            } else {
                $s = '';
            }
        }

        return [$f, $s];
    }

    /**
     * 旧数据：「S型 + I型」等 → 「S+I型」
     */
    private static function discNormalizeLegacyDualDescription(?string $desc): ?string
    {
        if ($desc === null || trim($desc) === '') {
            return null;
        }
        $t = preg_replace('/\s+/u', '', trim($desc));
        $t = str_replace('＋', '+', $t);
        if (preg_match('/^([DISC])型\+([DISC])型$/iu', $t, $m)) {
            return strtoupper($m[1]) . '+' . strtoupper($m[2]) . '型';
        }
        if (preg_match('/^([DISC])型$/iu', $t, $m)) {
            return strtoupper($m[1]) . '型';
        }

        return null;
    }

    /**
     * @return array{0:string,1:string} 完整 PDP 类型文案（含「型」）
     */
    private static function pdpResolveTwoFull(array $data): array
    {
        $f = self::pdpPrimaryFull($data);
        $s = '';
        $sk = $data['secondaryType'] ?? null;
        if (is_string($sk) && $sk !== '') {
            $s = self::PDP_EN_TO_CN[$sk] ?? $sk;
        }
        $aEn = '';
        $bEn = '';
        if (isset($data['scores']) && is_array($data['scores'])) {
            [$aEn, $bEn] = self::pdpOrderedKeys($data['scores']);
        }
        if ($aEn === '' && $bEn === '' && isset($data['percentages']) && is_array($data['percentages'])) {
            [$aEn, $bEn] = self::pdpOrderedKeys($data['percentages']);
        }
        if ($f === '' && $aEn !== '') {
            $f = self::PDP_EN_TO_CN[$aEn] ?? $aEn;
        }
        if ($s === '' || $s === $f) {
            if ($bEn !== '') {
                $cand = self::PDP_EN_TO_CN[$bEn] ?? $bEn;
                if ($cand !== $f) {
                    $s = $cand;
                }
            }
        }

        return [$f, $s];
    }

    private static function discPrimaryLetter(array $data): string
    {
        $desc = $data['description']['type'] ?? null;
        if (is_string($desc) && $desc !== '') {
            $t = trim($desc);
            $noXing = preg_replace('/型$/u', '', $t);
            if (mb_strlen($noXing) === 1) {
                $u = strtoupper($noXing);
                if (in_array($u, ['D', 'I', 'S', 'C'], true)) {
                    return $u;
                }
            }
        }
        $dom = $data['dominantType'] ?? null;
        if (is_string($dom) && $dom !== '') {
            $u = strtoupper(substr(trim($dom), 0, 1));
            if (in_array($u, ['D', 'I', 'S', 'C'], true)) {
                return $u;
            }
        }
        $disc = $data['disc'] ?? null;
        if (is_string($disc) && $disc !== '') {
            $t = trim($disc);
            $noXing = preg_replace('/型$/u', '', $t);
            if (mb_strlen($noXing) === 1) {
                $u = strtoupper($noXing);
                if (in_array($u, ['D', 'I', 'S', 'C'], true)) {
                    return $u;
                }
            }
        }

        return '';
    }

    private static function pdpPrimaryFull(array $data): string
    {
        $desc = $data['description']['type'] ?? null;
        if (is_string($desc) && $desc !== '') {
            return trim($desc);
        }
        $dom = $data['dominantType'] ?? null;
        if (is_string($dom) && $dom !== '') {
            return self::PDP_EN_TO_CN[$dom] ?? trim($dom);
        }
        $pdp = $data['pdp'] ?? null;
        if (is_string($pdp) && $pdp !== '') {
            return trim($pdp);
        }

        return '';
    }

    /**
     * @param array<string,int|float> $scores
     * @return array{0:string,1:string}
     */
    private static function discOrderedLetters(array $scores): array
    {
        $allowed = ['D' => true, 'I' => true, 'S' => true, 'C' => true];
        $pairs = [];
        foreach ($scores as $k => $v) {
            if (!is_string($k) && !is_numeric($k)) {
                continue;
            }
            $ku = strtoupper(substr(trim((string) $k), 0, 1));
            if (!isset($allowed[$ku])) {
                continue;
            }
            $pairs[] = [$ku, (int) $v];
        }
        usort($pairs, static function ($a, $b) {
            return ($b[1] <=> $a[1]) ?: strcmp($a[0], $b[0]);
        });
        $f = $pairs[0][0] ?? '';
        $s = $pairs[1][0] ?? '';

        return [$f, $s];
    }

    /**
     * @param array<string,int|float> $scores
     * @return array{0:string,1:string}
     */
    private static function pdpOrderedKeys(array $scores): array
    {
        $pairs = [];
        foreach ($scores as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $key = trim($k);
            if ($key === '' || !isset(self::PDP_EN_TO_CN[$key])) {
                continue;
            }
            $pairs[] = [$key, (int) $v];
        }
        usort($pairs, static function ($a, $b) {
            return ($b[1] <=> $a[1]) ?: strcmp($a[0], $b[0]);
        });
        $f = $pairs[0][0] ?? '';
        $s = $pairs[1][0] ?? '';

        return [$f, $s];
    }
}
