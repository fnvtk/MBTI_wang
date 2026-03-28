<?php
namespace app\controller\admin\concern;

/**
 * 从测试记录数组中解析 MBTI / DISC / PDP / 人脸子类型（与 AppUser 逻辑一致）
 */
trait ExtractsTestResults
{
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
                return (string) ($dec['mbtiType'] ?? $dec['type'] ?? $dec['result'] ?? '');
            }

            if ($targetType === 'disc') {
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                if (!empty($dec['dominantType'])) {
                    return (string) $dec['dominantType'];
                }
                return (string) ($dec['disc'] ?? '');
            }

            if ($targetType === 'pdp') {
                $desc = $dec['description']['type'] ?? null;
                if (is_string($desc) && $desc !== '') {
                    return $desc;
                }
                if (!empty($dec['dominantType'])) {
                    return (string) $dec['dominantType'];
                }
                return (string) ($dec['pdp'] ?? '');
            }

            return (string) ($dec['type'] ?? $dec['result'] ?? '');
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
                    return (string) $dec['disc']['primary'];
                }
                if (!empty($dec['disc'])) {
                    return (string) $dec['disc'];
                }
            } elseif ($target === 'pdp') {
                if (!empty($dec['pdp']['primary'])) {
                    return (string) $dec['pdp']['primary'];
                }
                if (!empty($dec['pdp'])) {
                    return (string) $dec['pdp'];
                }
            }
        }
        return '';
    }
}
