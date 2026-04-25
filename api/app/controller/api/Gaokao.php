<?php
namespace app\controller\api;

use app\BaseController;
use app\common\service\GaokaoService;
use think\facade\Request;

/**
 * 高考志愿功能 API
 */
class Gaokao extends BaseController
{
    private function wechatUserId(): int
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return 0;
        }
        return (int) ($user['user_id'] ?? $user['userId'] ?? 0);
    }

    public function taskStatus()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $entry = [
            'referrerId' => (int) Request::param('referrerId', 0),
            'channelCode' => (string) Request::param('channelCode', ''),
            'scene' => (string) Request::param('scene', 'entry'),
        ];
        GaokaoService::markEntry($uid, $entry);
        $pricingScope = trim((string) Request::param('pricingScope', 'personal'));
        $eidParam = (int) Request::param('enterpriseId', 0);

        return success(GaokaoService::loadTaskStatusWithPricing(
            $uid,
            $pricingScope === 'enterprise' ? 'enterprise' : 'personal',
            $eidParam > 0 ? $eidParam : null
        ));
    }

    public function saveForm()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $form = Request::post();
        if (!is_array($form)) {
            $form = [];
        }
        GaokaoService::saveForm($uid, $form);
        return success(GaokaoService::loadTaskStatus($uid), '保存成功');
    }

    public function myForm()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $profile = GaokaoService::getOrInitProfile($uid);
        return success([
            'form' => GaokaoService::formJsonAsArray($profile),
            'status' => (int) ($profile->formStatus ?? 0),
        ]);
    }

    public function analyze()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $pricingScope = trim((string) Request::param('pricingScope', 'personal'));
        $eidParam = (int) Request::param('enterpriseId', 0);
        $res = GaokaoService::createAnalysis(
            $uid,
            $pricingScope === 'enterprise' ? 'enterprise' : 'personal',
            $eidParam > 0 ? $eidParam : null
        );
        if (empty($res['ok'])) {
            return error((string) ($res['message'] ?? '分析失败'), 400);
        }
        return success($res, '分析成功');
    }

    public function latestReport()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $row = GaokaoService::myLatestReport($uid);
        if (!$row) {
            return error('暂无报告', 404);
        }
        return success($row);
    }

    public function pricing()
    {
        $uid = $this->wechatUserId();
        if ($uid <= 0) {
            return error('未登录', 401);
        }
        $productCode = trim((string) Request::param('productCode', 'gaokao_single_report'));
        $pricingScope = trim((string) Request::param('pricingScope', 'personal'));
        $eidParam = (int) Request::param('enterpriseId', 0);

        return success(GaokaoService::resolvePricing(
            $uid,
            $productCode,
            $pricingScope === 'enterprise' ? 'enterprise' : 'personal',
            $eidParam > 0 ? $eidParam : null
        ));
    }
}
