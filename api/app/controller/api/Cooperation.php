<?php

namespace app\controller\api;

use app\BaseController;
use app\common\service\EnterpriseCooperationService;
use think\facade\Request;

/**
 * 企业合作模式：小程序端
 */
class Cooperation extends BaseController
{
    /**
     * GET /api/enterprise/cooperation-modes
     * 当前用户绑定企业下、已启用的合作模式（需登录、需有企业）
     */
    public function modes()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $eid = EnterpriseCooperationService::getWechatEnterpriseId($userId);
        if (!$eid) {
            return error('未绑定企业', 403);
        }

        $list = EnterpriseCooperationService::listModesForEnterprise($eid, true);

        return success(['list' => $list]);
    }

    /**
     * GET /api/user/cooperation-status
     */
    public function status()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $eid = EnterpriseCooperationService::getWechatEnterpriseId($userId);
        if (!$eid) {
            return success([
                'enterpriseId'     => null,
                'resumeDone'       => false,
                'faceDone'         => false,
                'mbtiDone'         => false,
                'allDone'          => false,
                'chosen'           => false,
                'chosenModeCode'   => null,
                'modes'            => [],
            ]);
        }

        $flags = EnterpriseCooperationService::onboardingFlags($userId, $eid);
        $allDone = $flags['resumeDone'] && $flags['faceDone'] && $flags['mbtiDone'];

        $choiceRow = EnterpriseCooperationService::getUserChoice($userId, $eid);
        $chosen    = $choiceRow !== null;
        $chosenCode = $chosen ? (string) ($choiceRow['modeCode'] ?? '') : null;

        $modes = [];
        if ($allDone && !$chosen) {
            $modes = EnterpriseCooperationService::listModesForEnterprise($eid, true);
        }

        return success([
            'enterpriseId'   => $eid,
            'resumeDone'     => $flags['resumeDone'],
            'faceDone'       => $flags['faceDone'],
            'mbtiDone'       => $flags['mbtiDone'],
            'allDone'        => $allDone,
            'chosen'         => $chosen,
            'chosenModeCode' => $chosenCode,
            'modes'          => $modes,
        ]);
    }

    /**
     * POST /api/user/cooperation-preference
     * body: { "modeCode": "salary" }
     */
    public function submitPreference()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }
        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $eid = EnterpriseCooperationService::getWechatEnterpriseId($userId);
        if (!$eid) {
            return error('未绑定企业', 403);
        }

        $modeCode = EnterpriseCooperationService::normalizeModeCode(
            (string) (Request::post('modeCode', '') ?: Request::param('modeCode', ''))
        );
        if ($modeCode === '' || !EnterpriseCooperationService::isValidModeCodeString($modeCode)) {
            return error('无效的合作模式', 400);
        }

        $flags = EnterpriseCooperationService::onboardingFlags($userId, $eid);
        if (!$flags['resumeDone'] || !$flags['faceDone'] || !$flags['mbtiDone']) {
            return error('请先完成简历上传、面相分析与 MBTI 测评', 400);
        }

        if (!EnterpriseCooperationService::isModeEnabledForEnterprise($eid, $modeCode)) {
            return error('该合作模式未开放', 400);
        }

        EnterpriseCooperationService::saveUserChoice($userId, $eid, $modeCode);

        return success([
            'modeCode' => $modeCode,
        ], '已保存');
    }
}
