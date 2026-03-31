<?php
namespace app\controller\admin\concern;

use app\common\PdpDiscResultText;

/**
 * 从测试记录数组中解析 MBTI / DISC / PDP / 人脸子类型（与 AppUser 逻辑一致）
 */
trait ExtractsTestResults
{
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
