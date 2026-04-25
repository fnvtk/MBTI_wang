<?php
namespace app\common\service;

/**
 * 从简历文件 URL 提取可读文本（PDF / docx），供神仙 AI 对话与简历分析共用。
 */
class ResumeFileExtractService
{
    public static function extractFromUrl(string $fileUrl): string
    {
        if ($fileUrl === '' || !filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            return '';
        }

        $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return '';
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 15,
                'user_agent' => 'Mozilla/5.0',
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $raw = @file_get_contents($fileUrl, false, $ctx);
        if ($raw === false || strlen($raw) < 10) {
            return '';
        }

        if ($ext === 'docx') {
            return self::extractDocxText($raw);
        }

        if ($ext === 'pdf') {
            return self::extractPdfText($raw);
        }

        return '';
    }

    private static function extractDocxText(string $raw): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmp === false) {
            return '';
        }
        if (file_put_contents($tmp, $raw) === false) {
            @unlink($tmp);

            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        if ($xml === false || $xml === '') {
            return '';
        }
        preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $xml, $m);
        $text = isset($m[1]) ? implode('', $m[1]) : '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[^\x{4e00}-\x{9fff}\x{3000}-\x{303f}a-zA-Z0-9\s\.,;:!?()（）【】\[\]\/\-_@]/u', ' ', $text);

        return trim(preg_replace('/\s{2,}/', ' ', $text));
    }

    private static function extractPdfText(string $raw): string
    {
        $text = '';
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $block, $tj)) {
                    $text .= implode(' ', $tj[1]) . ' ';
                }
                if (preg_match_all('/\[(.*?)]\s*TJ/s', $block, $tjArr)) {
                    foreach ($tjArr[1] as $inner) {
                        if (preg_match_all('/\(([^)]*)\)/s', $inner, $parts)) {
                            $text .= implode('', $parts[1]) . ' ';
                        }
                    }
                }
            }
        }
        $text = preg_replace('/[^\x{4e00}-\x{9fff}\x{3000}-\x{303f}a-zA-Z0-9\s\.,;:!?()（）【】\[\]\/\-_@]/u', ' ', $text);

        return trim(preg_replace('/\s{2,}/', ' ', $text));
    }
}
