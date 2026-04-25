<?php
namespace app\common;

/**
 * 埋点事件中文名字典
 * 配合 analytics_events.eventName 展示
 */
class AnalyticsEventLabels
{
    /** @var array<string,string> */
    private static $MAP = [
        // 基础
        'page_view'   => '页面浏览',
        'app_launch'  => '小程序启动',
        'share'       => '分享',
        'pay_success' => '支付成功',
        'pay_fail'    => '支付失败',
        'pay_success_attribution' => '支付成功·归因',

        // 首页 / 拍照入口
        'tap_start_camera'         => '点击·开始拍照测试',
        'tap_questionnaire_home'   => '点击·首页·去做问卷',
        'tap_home_test_select'     => '点击·首页·去选测评',
        'tap_home_camera_tab'      => '点击·首页·拍照 Tab',
        'tap_enterprise_entry'     => '点击·切换企业版',
        'tap_test_select'          => '点击·选择测评类型',
        'tap_test_select_from_profile' => '点击·我的·选择测评',
        'tap_test_history'         => '点击·测评记录',
        'tap_my_resume'            => '点击·我的简历',
        'tap_match_job'            => '点击·职位匹配',
        'tap_promo_withdrawals'    => '点击·提现记录',

        // 问卷类
        'test_start'               => '开始答题',
        'test_submit'              => '提交答题',
        'test_complete'            => '测评提交完成',
        'test_next'                => '下一题',
        'test_prev'                => '上一题',
        'tap_upload_photo_home'    => '点击·首页·上传照片',

        // 付费墙
        'paywall_view'             => '付费墙·曝光',
        'tap_unlock_pay'           => '点击·解锁支付',

        // 结果页通用
        'tap_read_full'            => '点击·看全文',
        'tap_share_moment'         => '点击·分享到朋友圈',
        'tap_share_friend'         => '点击·分享给好友',
        'tap_face_camera'          => '点击·去拍照',
        'tap_complete_profile'     => '点击·去完善资料',
        'tap_unlock_full'          => '点击·解锁完整报告',
        'tap_retake_test'          => '点击·重新测试',

        // 深度解读 / 推广（带测评类型后缀）
        'tap_deep_service_from_mbti' => '点击·MBTI·深度解读方案',
        'tap_deep_service_from_disc' => '点击·DISC·深度解读方案',
        'tap_deep_service_from_pdp'  => '点击·PDP·深度解读方案',
        'tap_deep_service_from_sbti' => '点击·SBTI·深度解读方案',
        'tap_deep_service_face_result' => '点击·面相·深度解读方案',

        'tap_promo_from_mbti'      => '点击·MBTI·推广中心',
        'tap_promo_from_disc'      => '点击·DISC·推广中心',
        'tap_promo_from_pdp'       => '点击·PDP·推广中心',
        'tap_promo_from_sbti'      => '点击·SBTI·推广中心',
        'tap_promo_face_result'    => '点击·面相·推广中心',

        // 面相成交链路
        'tap_questionnaire_face_result' => '点击·面相页·补做问卷',

        // 我的 / 推广中心
        'tap_deep_service'         => '点击·我的·深度解读方案',
        'tap_my_orders'            => '点击·我的·我的订单',
        'tap_promo_center'         => '点击·进入推广中心',
        'tap_promo_poster'         => '点击·生成推广海报',
        'tap_promo_withdraw'       => '点击·提现',
        'tap_promo_share'          => '点击·推广分享',

        // 面相 / AI 结果页
        'face_analyze_success'     => '面相分析成功',
        'face_analyze_fail'        => '面相分析失败',
        'ai_analysis_complete'     => 'AI 分析完成',

        'take_photo'               => '拍照',
        'photo_upload_success'     => '照片上传成功',
        'photo_upload_fail'        => '照片上传失败',

        'tap_ai_test_entry'        => '点击·AI 测试入口',
        'ai_chat_go_test_select'   => '神仙AI·去选测评',
        'ai_chat_feature_reco_tap' => '神仙AI·功能推荐点击',
        'ai_chat_inline_reco_show' => '神仙AI·内联推荐展示',
        'ai_chat_resume_entry_tap' => '神仙AI·简历入口',

        'tap_match_upload_resume'  => '职位匹配·上传简历',
        'tap_match_analyze'        => '职位匹配·开始分析',

        // 企业 / 简历
        'tap_resume_upload'        => '点击·上传简历',
        'tap_resume_analyze'       => '点击·简历分析',

        // 登录 / 绑手机
        'login_silent_success'     => '静默登录成功',
        'login_silent_fail'        => '静默登录失败',
        'bind_phone_success'       => '绑定手机成功',
        'bind_phone_fail'          => '绑定手机失败',

        // 神仙 AI（功能六）
        'tap_tab_ai_chat'          => '点击·底部Tab·神仙AI',
        'ai_chat_send'             => '神仙AI·发送消息',
        'ai_chat_receive'          => '神仙AI·收到回复',
        'ai_chat_degrade'          => '神仙AI·降级兜底',
        'ai_quick_question_click'  => '神仙AI·快捷问题点击',
        'tap_ai_article'           => '神仙AI·点击推荐文章',
        'ai_article_read'          => '神仙AI·文章阅读完成',
        'ai_chat_share'            => '神仙AI·分享对话',
        'ai_chat_share_invite_tap' => '神仙AI·点击邀请赚佣金',
        'ai_report_cta_tap'        => '神仙AI·点击深度报告CTA',
        'ai_report_pay_tap'        => '神仙AI·深度报告点击支付',
        'ai_report_pay_success'    => '神仙AI·深度报告支付成功',
        'ai_report_share'          => '神仙AI·分享深度报告',
    ];

    /**
     * 获取事件中文名。未命中时回退到原 eventName。
     */
    public static function cn(string $eventName): string
    {
        $name = trim($eventName);
        if ($name === '') return '';
        return self::$MAP[$name] ?? $name;
    }

    /**
     * 批量翻译：在数组每行补 eventNameCn 字段
     * @param array<int,array<string,mixed>> $rows
     */
    public static function withCn(array $rows, string $field = 'eventName', string $cnField = 'eventNameCn'): array
    {
        foreach ($rows as &$r) {
            $name = isset($r[$field]) ? (string) $r[$field] : '';
            $r[$cnField] = self::cn($name);
        }
        unset($r);
        return $rows;
    }

    /**
     * 返回整张字典（供管理端下拉筛选）
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::$MAP;
    }
}
