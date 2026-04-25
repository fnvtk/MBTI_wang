<?php
namespace app\common;

/**
 * 小程序 pagePath → 中文页面名（用于飞书/出站推送「最近行为」展示）
 */
class AnalyticsPagePathLabels
{
    /** @var array<string,string> */
    private static $MAP = [
        'pages/index/index'              => '首页',
        'pages/index/camera'             => '拍照测评',
        'pages/index/upload'             => '上传照片',
        'pages/index/result'             => '面相/AI 结果',
        'pages/test-select/index'        => '选择测评类型',
        'pages/test/mbti'                => 'MBTI 答题',
        'pages/test/disc'                => 'DISC 答题',
        'pages/test/pdp'                 => 'PDP 答题',
        'pages/test/sbti'                => 'SBTI 答题',
        'pages/result/mbti'              => 'MBTI 结果',
        'pages/result/disc'              => 'DISC 结果',
        'pages/result/pdp'               => 'PDP 结果',
        'pages/result/sbti'              => 'SBTI 结果',
        'pages/result/resume'            => '简历分析结果',
        'pages/profile/index'            => '我的',
        'pages/user-profile/index'       => '个人资料',
        'pages/history/index'            => '测评记录',
        'pages/order/index'              => '我的订单',
        'pages/purchase/index'           => '深度服务',
        'pages/promo/index'              => '推广中心',
        'pages/promo/poster'           => '推广海报',
        'pages/promo/withdrawals'       => '提现记录',
        'pages/phone-auth/index'         => '手机授权',
        'pages/enterprise/index'         => '企业版',
        'pages/enterprise/resume-history'=> '企业简历记录',
        'pages/recharge/index'           => '充值',
        'pages/match-job/index'          => '职位匹配',
        'pages/ai-test/index'            => 'AI 测试',
        'pages/ai-test/camera'           => 'AI 测试·拍照',
        'pages/ai-test/result'           => 'AI 测试结果',
        'pages/ai-chat/index'            => '神仙 AI',
        'pages/ai-chat/report'           => '神仙 AI 报告',
        'pages/ai-chat/history'          => '神仙 AI 历史',
        'pages/webview/index'            => '内置网页',
    ];

    public static function cn(string $path): string
    {
        $p = trim($path);
        if ($p === '') {
            return '';
        }
        $p = ltrim($p, '/');
        if (isset(self::$MAP[$p])) {
            return self::$MAP[$p];
        }
        foreach (self::$MAP as $k => $v) {
            if (strpos($p, $k) === 0) {
                return $v;
            }
        }

        return $p;
    }
}
