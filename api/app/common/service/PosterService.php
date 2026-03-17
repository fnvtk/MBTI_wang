<?php
namespace app\common\service;

use think\facade\Db;

/**
 * 推广海报合成服务（PHP GD）
 * 需要 GD 扩展，中文需在 public/fonts/ 放置 TTF 字体（如 simhei.ttf）
 *
 * 支持两种模式：
 *  1. buildFromConfig()  — 根据超管可视化配置渲染
 *  2. build()            — 旧版硬编码布局（兜底）
 */
class PosterService
{
    /** 编辑器画布基准尺寸（CSS px） */
    private const CANVAS_W = 375;
    private const CANVAS_H = 667;

    /** 渲染倍率（2x 清晰度） */
    private const SCALE = 2;

    /** 旧版硬编码尺寸（向下兼容） */
    private const WIDTH  = 600;
    private const HEIGHT = 1066;

    /**
     * 字体注册表：key => [显示名, 文件名]
     * 文件存放于 public/fonts/ 目录
     */
    private const FONT_MAP = [
        'noto-sans'    => ['思源黑体',       'NotoSansCJKsc-Regular.otf'],
        'noto-serif'   => ['思源宋体',       'NotoSerifCJKsc-Regular.otf'],
        'alimama'      => ['阿里妈妈方圆体', 'AlimamaFangYuanTiVF.ttf'],
        'wqy-microhei' => ['文泉驿微米黑',   'wqy-microhei.ttc'],
    ];

    /**
     * 返回服务器上实际可用的字体列表
     * @return array [ ['key'=>'noto-sans','name'=>'思源黑体'], ... ]
     */
    public static function getAvailableFonts(): array
    {
        $base = root_path() . 'public/fonts/';
        $list = [];
        foreach (self::FONT_MAP as $key => [$name, $file]) {
            if (file_exists($base . $file)) {
                $list[] = ['key' => $key, 'name' => $name];
            }
        }
        // 兼容旧字体
        $legacy = ['simhei.ttf' => '黑体', 'msyh.ttf' => '微软雅黑'];
        foreach ($legacy as $file => $name) {
            if (file_exists($base . $file)) {
                $list[] = ['key' => pathinfo($file, PATHINFO_FILENAME), 'name' => $name];
            }
        }
        return $list;
    }

    // ─────────────────────────────────────────────
    //  NEW：根据超管可视化配置渲染海报
    // ─────────────────────────────────────────────

    /**
     * 读取 poster_config 并渲染，若无配置则回退到旧版 build()
     * @param int|null $enterpriseId 企业 ID，优先读 enterprise_id={id} 行，无则降级到 enterprise_id=0 全局行
     */
    public static function buildFromConfig(array $user, string $qrBinary, ?string $avatarBinary = null, ?int $enterpriseId = null): string
    {
        $raw = null;

        // 1. 优先读取企业专属海报配置（enterprise_id 列）
        if ($enterpriseId > 0) {
            $eidRow = Db::name('system_config')
                ->where('key', 'poster_config')
                ->where('enterprise_id', $enterpriseId)
                ->find();
            if ($eidRow && !empty($eidRow['value'])) {
                $raw = $eidRow['value'];
            }
        }

        // 2. 降级到全局配置（enterprise_id=0）
        if ($raw === null) {
            $row = Db::name('system_config')
                ->where('key', 'poster_config')
                ->where('enterprise_id', 0)
                ->find();
            $raw = $row['value'] ?? null;
        }

        // 安全解码：处理可能的多重 JSON 编码
        $cfg = null;
        if ($raw) {
            $val = $raw;
            for ($i = 0; $i < 5 && is_string($val); $i++) {
                $decoded = json_decode($val, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) break;
                $val = $decoded;
            }
            $cfg = is_array($val) ? $val : null;
        }

        if (empty($cfg) || empty($cfg['elements']) || !is_array($cfg['elements'])) {
            return self::build($user, $qrBinary, $avatarBinary);
        }

        return self::renderConfig($cfg, $user, $qrBinary, $avatarBinary);
    }

    /**
     * 根据配置数组渲染海报
     */
    private static function renderConfig(array $cfg, array $user, string $qrBinary, ?string $avatarBinary): string
    {
        $s  = self::SCALE;
        $cw = self::CANVAS_W * $s;
        $ch = self::CANVAS_H * $s;

        $img = imagecreatetruecolor($cw, $ch);
        if (!$img) throw new \RuntimeException('GD image create failed');
        imagesavealpha($img, true);
        imagealphablending($img, true);

        // 背景颜色
        $bgColorHex = $cfg['bgColor'] ?? '#ffffff';
        $bgRgb = self::parseColorToRgb($bgColorHex);
        $bgColor = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        imagefilledrectangle($img, 0, 0, $cw - 1, $ch - 1, $bgColor);

        // 背景图片
        if (!empty($cfg['bgImage'])) {
            $bgBin = self::fetchImage($cfg['bgImage']);
            if ($bgBin) {
                $bgImg = @imagecreatefromstring($bgBin);
                if ($bgImg) {
                    imagecopyresampled($img, $bgImg, 0, 0, 0, 0, $cw, $ch, imagesx($bgImg), imagesy($bgImg));
                    imagedestroy($bgImg);
                }
            }
        }

        // 缓存图像资源
        $qrRes     = null;
        $avatarRes = null;

        // 预先查询用户最近的测试结果（mbti / pdp / disc / face）
        $testResults = self::fetchLatestTestResults((int)($user['id'] ?? 0));

        foreach ($cfg['elements'] as $el) {
            $type = $el['type'] ?? '';
            $x = (int)(($el['x'] ?? 0) * $s);
            $y = (int)(($el['y'] ?? 0) * $s);
            $w = (int)(($el['w'] ?? 80) * $s);
            $h = (int)(($el['h'] ?? 80) * $s);

            $fontKey = $el['fontFamily'] ?? null;
            // 对齐方式：优先 align 字段，兼容旧 center 字段
            $align = $el['align'] ?? (!empty($el['center']) ? 'center' : 'left');

            switch ($type) {
                case 'text':
                    $text      = $el['content'] ?? '';
                    $fontSize  = max(8, (int)(($el['fontSize'] ?? 16) * $s));
                    $colorHex  = $el['color'] ?? '#333333';
                    $bold      = !empty($el['bold']);
                    $colorInt  = self::allocateHexColor($img, $colorHex);
                    self::drawTextBlock($img, $x, $y, $w, $h, $text, $colorInt, $fontSize, $bold, $align, $fontKey);
                    break;

                case 'nickname':
                    $text      = mb_substr($user['nickname'] ?? '好友', 0, 20);
                    $fontSize  = max(8, (int)(($el['fontSize'] ?? 16) * $s));
                    $colorHex  = $el['color'] ?? '#333333';
                    $bold      = !empty($el['bold']);
                    $colorInt  = self::allocateHexColor($img, $colorHex);
                    self::drawTextBlock($img, $x, $y, $w, $h, $text, $colorInt, $fontSize, $bold, $align, $fontKey);
                    break;

                case 'avatar':
                    if ($avatarBinary) {
                        if (!$avatarRes) $avatarRes = @imagecreatefromstring($avatarBinary);
                        if ($avatarRes) {
                            $shape = $el['shape'] ?? 'circle';
                            self::drawImageElement($img, $avatarRes, $x, $y, $w, $h, $shape);
                        }
                    }
                    break;

                case 'qrcode':
                    if (!$qrRes) $qrRes = @imagecreatefromstring($qrBinary);
                    if ($qrRes) {
                        self::drawImageElement($img, $qrRes, $x, $y, $w, $h, 'square');
                    }
                    break;

                case 'image':
                    if (!empty($el['url'])) {
                        $bin = self::fetchImage($el['url']);
                        if ($bin) {
                            $staticImg = @imagecreatefromstring($bin);
                            if ($staticImg) {
                                $shape = $el['shape'] ?? 'square';
                                self::drawImageElement($img, $staticImg, $x, $y, $w, $h, $shape);
                                imagedestroy($staticImg);
                            }
                        }
                    }
                    break;

                case 'mbti':
                case 'pdp':
                case 'disc':
                    $text = $testResults[$type] ?? ($el['content'] ?? strtoupper($type));
                    $fontSize  = max(8, (int)(($el['fontSize'] ?? 16) * $s));
                    $colorHex  = $el['color'] ?? '#333333';
                    $bold      = !empty($el['bold']);
                    $colorInt  = self::allocateHexColor($img, $colorHex);
                    self::drawTextBlock($img, $x, $y, $w, $h, $text, $colorInt, $fontSize, $bold, $align, $fontKey);
                    break;
            }
        }

        if ($qrRes) imagedestroy($qrRes);
        if ($avatarRes) imagedestroy($avatarRes);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png ?: '';
    }

    /**
     * 查询用户最近各类型测试的 resultText
     * 复用 Test::_formatRecentRow 的解析逻辑
     * @return array ['mbti' => 'ESTP', 'pdp' => '老虎型', 'disc' => 'D型']
     */
    private static function fetchLatestTestResults(int $userId): array
    {
        $out = [];
        if ($userId <= 0) return $out;

        $types = ['face', 'mbti', 'pdp', 'disc'];
        foreach ($types as $t) {
            $row = Db::name('test_results')
                ->where('userId', $userId)
                ->where('testType', $t)
                ->order('createdAt', 'desc')
                ->field('resultData, testType')
                ->find();
            if (!$row || empty($row['resultData'])) continue;
            $raw  = $row['resultData'];
            $data = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($data)) continue;

            switch ($t) {
                case 'face':
                    // face 结果中包含 mbti/pdp/disc 子结构
                    if (!isset($out['mbti'])) {
                        $v = '';
                        if (isset($data['mbti']['type'])) $v = $data['mbti']['type'];
                        elseif (isset($data['mbti']) && is_scalar($data['mbti'])) $v = (string)$data['mbti'];
                        if ($v !== '') $out['mbti'] = $v;
                    }
                    if (!isset($out['pdp'])) {
                        $v = '';
                        if (isset($data['pdp']['type'])) $v = $data['pdp']['type'];
                        elseif (isset($data['pdp']) && is_scalar($data['pdp'])) $v = (string)$data['pdp'];
                        if ($v !== '') $out['pdp'] = $v;
                    }
                    if (!isset($out['disc'])) {
                        $v = '';
                        if (isset($data['disc']['primary'])) $v = $data['disc']['primary'] . '型';
                        elseif (isset($data['disc']) && is_scalar($data['disc'])) $v = (string)$data['disc'];
                        if ($v !== '') $out['disc'] = $v;
                    }
                    break;
                case 'mbti':
                    if (!isset($out['mbti'])) {
                        $v = $data['mbtiType'] ?? $data['mbti'] ?? '';
                        if (is_array($v)) $v = $v['type'] ?? '';
                        if ((string)$v !== '') $out['mbti'] = (string)$v;
                    }
                    break;
                case 'pdp':
                    if (!isset($out['pdp'])) {
                        $v = $data['description']['type'] ?? $data['pdp'] ?? '';
                        if (is_array($v)) $v = $v['type'] ?? '';
                        if ((string)$v !== '') $out['pdp'] = (string)$v;
                    }
                    break;
                case 'disc':
                    if (!isset($out['disc'])) {
                        $v = $data['dominantType'] ?? $data['disc'] ?? '';
                        if (is_array($v)) $v = $v['type'] ?? $v['primary'] ?? '';
                        if ((string)$v !== '') $out['disc'] = (string)$v . '型';
                    }
                    break;
            }
        }
        return $out;
    }

    /**
     * 在指定区域内绘制文字（imagettftext 的 y 为基线坐标）
     */
    private static function drawTextBlock($img, int $x, int $y, int $w, int $h, string $text, int $color, int $fontSize, bool $bold, string $align = 'left', ?string $fontKey = null): void
    {
        $font = self::getFontPath($fontKey);
        if ($font && function_exists('imagettftext')) {
            $textW = self::ttfTextWidth($font, $fontSize, $text);
            switch ($align) {
                case 'center': $drawX = $x + (int)(($w - $textW) / 2); break;
                case 'right':  $drawX = $x + $w - $textW - 6; break;
                default:       $drawX = $x + 6; break;
            }
            $drawY = $y + (int)(($h + $fontSize * 0.8) / 2);
            imagettftext($img, $fontSize, 0, $drawX, $drawY, $color, $font, $text);
        } else {
            $f    = $fontSize <= 16 ? 4 : 5;
            $textW = imagefontwidth($f) * mb_strlen($text);
            switch ($align) {
                case 'center': $drawX = $x + (int)(($w - $textW) / 2); break;
                case 'right':  $drawX = $x + $w - $textW - 4; break;
                default:       $drawX = $x + 4; break;
            }
            imagestring($img, $f, $drawX, $y + (int)(($h - imagefontheight($f)) / 2), preg_replace('/[^\x20-\x7e]/', '?', $text), $color);
        }
    }

    /**
     * 估算 TTF 文字宽度（近似）
     */
    private static function ttfTextWidth(string $font, int $size, string $text): int
    {
        if (function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            return $box ? abs($box[4] - $box[0]) : $size * mb_strlen($text);
        }
        return $size * mb_strlen($text);
    }

    /**
     * 将图像资源绘制到画布（支持圆形/方形裁剪）
     */
    private static function drawImageElement($img, $src, int $x, int $y, int $w, int $h, string $shape): void
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($shape === 'circle') {
            // 先绘制到临时图像再做圆形遮罩
            $tmp = imagecreatetruecolor($w, $h);
            imagesavealpha($tmp, true);
            imagealphablending($tmp, false);
            $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $w - 1, $h - 1, $transparent);
            imagealphablending($tmp, true);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);

            // 圆形遮罩（逐像素）—— 仅在 GD 无更好方案时使用
            $mask = imagecreatetruecolor($w, $h);
            imagesavealpha($mask, true);
            imagealphablending($mask, false);
            imagefilledrectangle($mask, 0, 0, $w - 1, $h - 1, imagecolorallocatealpha($mask, 0, 0, 0, 127));
            imagealphablending($mask, true);
            imagefilledellipse($mask, (int)($w / 2), (int)($h / 2), $w, $h, imagecolorallocate($mask, 255, 255, 255));

            // 将 tmp 叠加到主图（遮罩内白外黑，白色表示保留区域）
            for ($px = 0; $px < $w; $px++) {
                for ($py = 0; $py < $h; $py++) {
                    $mPx = imagecolorat($mask, $px, $py);
                    $rMask = ($mPx >> 16) & 0xFF;
                    if ($rMask > 128) {
                        $srcPx = imagecolorat($tmp, $px, $py);
                        $r = ($srcPx >> 16) & 0xFF;
                        $g = ($srcPx >> 8) & 0xFF;
                        $b = $srcPx & 0xFF;
                        $c = imagecolorallocate($img, $r, $g, $b);
                        imagesetpixel($img, $x + $px, $y + $py, $c);
                    }
                }
            }
            imagedestroy($tmp);
            imagedestroy($mask);
        } else {
            imagecopyresampled($img, $src, $x, $y, 0, 0, $w, $h, $srcW, $srcH);
        }
    }

    /**
     * 将颜色字符串解析为 RGB 数组
     * 支持 #fff、#ffffff、rgba(r,g,b,a)、rgb(r,g,b)、数组 [r,g,b]
     */
    private static function parseColorToRgb($color): array
    {
        if (is_array($color)) {
            $r = (int)($color['r'] ?? $color[0] ?? 51);
            $g = (int)($color['g'] ?? $color[1] ?? 51);
            $b = (int)($color['b'] ?? $color[2] ?? 51);
            return [min(255, max(0, $r)), min(255, max(0, $g)), min(255, max(0, $b))];
        }
        if (!is_string($color) && !is_scalar($color)) {
            return [51, 51, 51];
        }
        $s = trim((string)$color);
        if ($s === '') {
            return [51, 51, 51];
        }
        // rgba(r,g,b,a) 或 rgb(r,g,b)
        if (preg_match('/^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $s, $m)) {
            return [(int)min(255, $m[1]), (int)min(255, $m[2]), (int)min(255, $m[3])];
        }
        // 仅保留 # 后合法十六进制字符
        $hex = preg_replace('/[^0-9a-fA-F]/', '', ltrim($s, '#'));
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return [51, 51, 51];
        }
        $r = (int)hexdec(substr($hex, 0, 2));
        $g = (int)hexdec(substr($hex, 2, 2));
        $b = (int)hexdec(substr($hex, 4, 2));
        return [min(255, $r), min(255, $g), min(255, $b)];
    }

    private static function allocateHexColor($img, $hex): int
    {
        [$r, $g, $b] = self::parseColorToRgb($hex);
        return imagecolorallocate($img, $r, $g, $b);
    }

    /**
     * 根据字体 key 获取字体文件路径；key 为空则返回第一个可用字体
     */
    private static function getFontPath(?string $fontKey = null): ?string
    {
        $base = root_path() . 'public/fonts/';

        // 按 key 精确查找
        if ($fontKey) {
            if (isset(self::FONT_MAP[$fontKey])) {
                $p = $base . self::FONT_MAP[$fontKey][1];
                if (file_exists($p)) return $p;
            }
            // 兼容旧字体 key（如 simhei / msyh）
            $legacy = $base . $fontKey . '.ttf';
            if (file_exists($legacy)) return $legacy;
        }

        // 回退：按优先级返回第一个可用字体
        foreach (self::FONT_MAP as [$name, $file]) {
            $p = $base . $file;
            if (file_exists($p)) return $p;
        }
        $fallbacks = [$base . 'simhei.ttf', $base . 'msyh.ttf', '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc'];
        foreach ($fallbacks as $p) {
            if (file_exists($p)) return $p;
        }
        return null;
    }

    private static function drawText($img, int $x, int $y, string $text, int $color, int $size = 14): void
    {
        $font = self::getFontPath();
        if ($font && function_exists('imagettftext')) {
            imagettftext($img, $size, 0, $x, $y + $size, $color, $font, $text);
        } else {
            $f = $size <= 12 ? 4 : 5;
            imagestring($img, $f, $x, $y, preg_replace('/[^\x20-\x7e]/', '', $text) ?: $text, $color);
        }
    }

    /**
     * 合成推广海报 PNG 二进制
     * @param array $user   当前用户 {id, nickname, avatar}
     * @param string $qrBinary 小程序码 PNG 二进制
     * @param string|null $avatarBinary 头像二进制（已下载），为空则跳过
     */
    public static function build(array $user, string $qrBinary, ?string $avatarBinary = null): string
    {
        $w = self::WIDTH;
        $h = self::HEIGHT;
        $img = imagecreatetruecolor($w, $h);
        if (!$img) {
            throw new \RuntimeException('GD image create failed');
        }
        imagesavealpha($img, true);
        imagealphablending($img, true);

        self::drawGradient($img, 0, 0, $w, (int)($h * 0.45), [0xFF, 0xD1, 0xE3], [0xE9, 0xD5, 0xFF]);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, (int)($h * 0.4), $w, $h, $white);

        $primary    = imagecolorallocate($img, 244, 63, 94);
        $secondary  = imagecolorallocate($img, 139, 92, 246);
        $orange     = imagecolorallocate($img, 249, 115, 22);
        $dark       = imagecolorallocate($img, 30, 41, 59);
        $gray       = imagecolorallocate($img, 100, 116, 139);
        $lightGray  = imagecolorallocate($img, 148, 163, 184);
        $cx         = (int)($w / 2);

        self::drawRoundedRect($img, 30, 50, 60, 20, 10, imagecolorallocatealpha($img, 255, 255, 255, 80));
        self::drawRoundedRect($img, 130, 50, 60, 20, 10, $primary);
        self::drawText($img, 45, 52, '专业分析', $primary, 10);
        self::drawText($img, 145, 52, '结果精准', $white, 10);

        self::drawText($img, $cx - 80, 100, 'MBTI 神仙测试', $dark, 22);

        self::drawRoundedRect($img, $cx - 80, 150, 65, 24, 8, imagecolorallocatealpha($img, 255, 255, 255, 50));
        self::drawRoundedRect($img, $cx + 5, 150, 65, 24, 8, imagecolorallocatealpha($img, 255, 255, 255, 50));
        self::drawText($img, $cx - 70, 155, 'INTJ', $primary, 10);
        self::drawText($img, $cx - 45, 155, '战略家', $gray, 10);
        self::drawText($img, $cx + 15, 155, 'PDP', $secondary, 10);
        self::drawText($img, $cx + 40, 155, '猫头鹰', $gray, 10);

        $gridY = 220;
        $gridW = (int)(($w - 80) / 3);
        self::drawStatCard($img, 30, $gridY, $gridW, 70, '100+', '性格档案', $primary);
        self::drawStatCard($img, 30 + $gridW + 10, $gridY, $gridW, 70, '0%', '好友折扣', $secondary);
        self::drawStatCard($img, 30 + ($gridW + 10) * 2, $gridY, $gridW, 70, '90%', '收益分红', $orange);

        $cardY = 320;
        self::drawRoundedRect($img, 30, $cardY, $w - 60, 180, 20, $white);
        imagefilledrectangle($img, 45, $cardY + 18, 49, $cardY + 34, $primary);
        self::drawText($img, 55, $cardY + 15, '完整版性格深度解析', $dark, 12);
        $bullets = [
            '你的决策风格在高压场景下会如何变化？',
            '在团队中更适合担当怎样的关键角色？',
            '哪些性格盲区最容易拖累你的发展？',
        ];
        foreach ($bullets as $i => $t) {
            self::drawText($img, 50, $cardY + 50 + $i * 28, '• ' . $t, $gray, 10);
        }

        $recY = 530;
        self::drawRoundedRect($img, $cx - 100, $recY, 200, 36, 18, imagecolorallocate($img, 248, 250, 252));
        if ($avatarBinary) {
            $avatar = @imagecreatefromstring($avatarBinary);
            if ($avatar) {
                imagecopyresampled($img, $avatar, $cx - 92, $recY + 6, 0, 0, 24, 24, imagesx($avatar), imagesy($avatar));
                imagedestroy($avatar);
            }
        }
        $nickname = mb_substr($user['nickname'] ?? '好友', 0, 8);
        self::drawText($img, $cx - 95, $recY + 12, '由 ' . $nickname . ' 推荐给你', $gray, 10);

        $qrY = 620;
        $qrImg = @imagecreatefromstring($qrBinary);
        if ($qrImg) {
            $qrSize = 88;
            $qrX = $cx - (int)($qrSize / 2);
            imagecopyresampled($img, $qrImg, $qrX, $qrY + 6, 0, 0, $qrSize, $qrSize, imagesx($qrImg), imagesy($qrImg));
            imagedestroy($qrImg);
        }
        self::drawText($img, $cx - 80, $qrY + 100, '扫码解锁完整报告', $lightGray, 10);
        $inviteCode = 'MBTI-' . ($user['id'] ?? '888');
        self::drawText($img, $cx - 60, $qrY + 125, '邀请码 ' . $inviteCode, $primary, 11);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png ?: '';
    }

    private static function drawGradient($img, $x, $y, $w, $h, array $from, array $to): void
    {
        for ($i = 0; $i < $h; $i++) {
            $r = (int)($from[0] + ($to[0] - $from[0]) * $i / $h);
            $g = (int)($from[1] + ($to[1] - $from[1]) * $i / $h);
            $b = (int)($from[2] + ($to[2] - $from[2]) * $i / $h);
            $c = imagecolorallocate($img, max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
            imagefilledrectangle($img, $x, $y + $i, $x + $w - 1, $y + $i, $c);
        }
    }

    private static function drawRoundedRect($img, $x, $y, $w, $h, $r, $color): void
    {
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r - 1, $y + $h - 1, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w - 1, $y + $h - $r - 1, $color);
        imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r - 1, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $r, $y + $h - $r - 1, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r - 1, $y + $h - $r - 1, $r * 2, $r * 2, $color);
    }

    private static function drawStatCard($img, $x, $y, $w, $h, string $val, string $label, int $color): void
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        self::drawRoundedRect($img, $x, $y, $w, $h, 15, $white);
        $gray = imagecolorallocate($img, 148, 163, 184);
        $lw = imagefontwidth(5) * strlen($val);
        imagestring($img, 5, $x + ($w - $lw) / 2, $y + 20, $val, $color);
        $lw2 = imagefontwidth(4) * strlen($label);
        imagestring($img, 4, $x + ($w - $lw2) / 2, $y + 45, $label, $gray);
    }

    /**
     * 下载远程图片为二进制
     */
    public static function fetchImage(string $url): ?string
    {
        $url = str_replace('http://', 'https://', $url);
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $bin = @file_get_contents($url, false, $ctx);
        return $bin !== false ? $bin : null;
    }
}
