<?php
namespace app\controller\admin\concern;

use app\common\PdpDiscResultText;
use think\facade\Db;

/**
 * 从测试记录数组中解析 MBTI / DISC / PDP / 人脸子类型（与 AppUser 逻辑一致）
 */
trait ExtractsTestResults
{
    /**
     * 基于测试记录推算冷脸分值（0-100）
     *   -  面相 emotionScore/emotionNeutrality/microExpression 作为主输入
     *   -  MBTI I/E 作为补充（I 更冷，E 更暖）
     * 返回 ['score'=>int, 'level'=>'cold|neutral|warm']；如无任何线索返回 null
     */
    public function calcColdFace(array $tests): ?array
    {
        $score = null; // 0-100
        $mbtiType = '';

        foreach ($tests as $t) {
            $type = strtolower($t['testType'] ?? '');
            $raw = $t['result'] ?? ($t['resultData'] ?? '');
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $dec = json_decode($raw, true);
            if (!is_array($dec)) {
                continue;
            }

            if ($type === 'face' && $score === null) {
                $face = is_array($dec['face'] ?? null) ? $dec['face'] : $dec;
                $emotion = null;
                foreach (['coldFaceScore', 'coldScore', 'emotionNeutrality', 'neutrality'] as $k) {
                    if (isset($face[$k]) && is_numeric($face[$k])) {
                        $emotion = (float) $face[$k];
                        break;
                    }
                }
                if ($emotion === null && isset($face['emotionScore']) && is_numeric($face['emotionScore'])) {
                    // emotionScore 越高越暖 → 冷脸分取反
                    $emotion = 100 - (float) $face['emotionScore'];
                }
                if ($emotion === null && isset($face['microExpression']) && is_array($face['microExpression'])) {
                    $mx = $face['microExpression'];
                    $happy = is_numeric($mx['happy'] ?? null) ? (float) $mx['happy'] : 0;
                    $neutral = is_numeric($mx['neutral'] ?? null) ? (float) $mx['neutral'] : 0;
                    $total = $happy + $neutral + 1;
                    $emotion = ($neutral / $total) * 100;
                }
                if ($emotion !== null) {
                    if ($emotion <= 1) {
                        $emotion = $emotion * 100;
                    }
                    $score = max(0, min(100, (int) round($emotion)));
                }
            }

            if ($type === 'mbti' && $mbtiType === '') {
                foreach (['mbtiType', 'type', 'result'] as $k) {
                    $s = $this->coerceResultLabel($dec[$k] ?? null);
                    if ($s !== '') {
                        $mbtiType = strtoupper($s);
                        break;
                    }
                }
            }
        }

        if ($score === null && $mbtiType !== '') {
            $score = strpos($mbtiType, 'I') === 0 ? 62 : 42;
        }
        if ($score === null) {
            return null;
        }

        $level = 'neutral';
        if ($score > 65) {
            $level = 'cold';
        } elseif ($score < 35) {
            $level = 'warm';
        }

        return ['score' => (int) $score, 'level' => $level];
    }

    /**
     * 写回 user_profile 的冷脸字段（存在才更新，否则跳过；失败静默）
     */
    protected function writeColdFace(int $userId, ?int $enterpriseId, array $cold): void
    {
        if (!isset($cold['score'], $cold['level'])) {
            return;
        }
        try {
            $query = Db::name('user_profile')->where('userId', $userId);
            if ($enterpriseId) {
                $query->where('enterpriseId', $enterpriseId);
            }
            $query->update([
                'coldFaceScore' => (int) $cold['score'],
                'coldFaceLevel' => (string) $cold['level'],
                'coldFaceUpdatedAt' => time(),
                'updatedAt' => time(),
            ]);
        } catch (\Throwable $e) {
            // 字段不存在（未执行迁移）时直接忽略
        }
    }

    /**
     * 结果字段可能为数组/对象，禁止直接 (string) 强转导致 Array to string conversion
     */
    private function coerceResultLabel($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            if (isset($value['type']) && is_string($value['type'])) {
                return trim($value['type']);
            }
            if (isset($value['primary']) && is_string($value['primary'])) {
                return trim($value['primary']);
            }
            foreach (['name', 'label', 'code'] as $k) {
                if (isset($value[$k]) && is_string($value[$k]) && $value[$k] !== '') {
                    return trim($value[$k]);
                }
            }
            return '';
        }

        return '';
    }

    private function extractResultType(array $tests, string $type): string
    {
        $targetType = strtolower($type);
        foreach ($tests as $t) {
            if (strtolower($t['testType'] ?? '') !== $targetType) {
                continue;
            }
            $result = $t['result'] ?? '';
            if (!is_string($result)) {
                continue;
            }
            $dec = json_decode($result, true);
            if (!is_array($dec)) {
                return $targetType === 'face' ? '人脸分析' : trim($result);
            }

            if ($targetType === 'face') {
                return '人脸分析';
            }

            if ($targetType === 'mbti') {
                foreach (['mbtiType', 'type', 'result'] as $k) {
                    $s = $this->coerceResultLabel($dec[$k] ?? null);
                    if ($s !== '') {
                        return $s;
                    }
                }
                return '';
            }

            if ($targetType === 'disc') {
                $two = PdpDiscResultText::discTopTwo($dec);
                if ($two !== '') {
                    return $two;
                }
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                $dom = $this->coerceResultLabel($dec['dominantType'] ?? null);
                if ($dom !== '') {
                    return $dom;
                }
                return $this->coerceResultLabel($dec['disc'] ?? null);
            }

            if ($targetType === 'pdp') {
                $two = PdpDiscResultText::pdpTopTwo($dec);
                if ($two !== '') {
                    return $two;
                }
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                $dom = $this->coerceResultLabel($dec['dominantType'] ?? null);
                if ($dom !== '') {
                    return $dom;
                }
                return $this->coerceResultLabel($dec['pdp'] ?? null);
            }

            if ($targetType === 'sbti') {
                $code = $this->coerceResultLabel($dec['sbtiType'] ?? null);
                if ($code === '' && isset($dec['finalType']) && is_array($dec['finalType'])) {
                    $code = $this->coerceResultLabel($dec['finalType']['code'] ?? null);
                }
                $cn = '';
                if (!empty($dec['sbtiCn']) && is_string($dec['sbtiCn'])) {
                    $cn = trim($dec['sbtiCn']);
                } elseif (isset($dec['finalType']) && is_array($dec['finalType']) && !empty($dec['finalType']['cn']) && is_string($dec['finalType']['cn'])) {
                    $cn = trim((string) $dec['finalType']['cn']);
                }
                if ($code !== '' && $cn !== '') {
                    return $code . '（' . $cn . '）';
                }
                if ($code !== '') {
                    return $code;
                }
                if ($cn !== '') {
                    return $cn;
                }
                return $this->coerceResultLabel($dec['code'] ?? null);
            }

            $fallback = $this->coerceResultLabel($dec['type'] ?? null);
            if ($fallback !== '') {
                return $fallback;
            }
            return $this->coerceResultLabel($dec['result'] ?? null);
        }
        return '';
    }

    private function extractFaceSubType(array $tests, string $subType): string
    {
        $target = strtolower($subType);
        foreach ($tests as $t) {
            if (strtolower($t['testType'] ?? '') !== 'face') {
                continue;
            }
            $result = $t['result'] ?? '';
            if (!is_string($result)) {
                continue;
            }
            $dec = json_decode($result, true);
            if (!is_array($dec)) {
                continue;
            }

            if ($target === 'mbti') {
                if (!empty($dec['mbti']['type'])) {
                    return (string) $dec['mbti']['type'];
                }
                if (!empty($dec['mbtiType'])) {
                    return (string) $dec['mbtiType'];
                }
            } elseif ($target === 'disc') {
                if (!empty($dec['disc']['primary'])) {
                    return $this->coerceResultLabel($dec['disc']['primary']);
                }
                $d = $dec['disc'] ?? null;
                if (is_array($d)) {
                    $s = $this->coerceResultLabel($d);
                    if ($s !== '') {
                        return $s;
                    }
                }
            } elseif ($target === 'pdp') {
                if (!empty($dec['pdp']['primary'])) {
                    return $this->coerceResultLabel($dec['pdp']['primary']);
                }
                $p = $dec['pdp'] ?? null;
                if (is_array($p)) {
                    $s = $this->coerceResultLabel($p);
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }
        return '';
    }
}
