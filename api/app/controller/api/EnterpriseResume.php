<?php
namespace app\controller\api;

use app\BaseController;
use app\model\EnterpriseResumeUpload;
use think\facade\Db;
use think\facade\Request;

/**
 * 企业版简历上传记录 API（仅记录与列表，支持预览用 URL）
 */
class EnterpriseResume extends BaseController
{
    /**
     * 获取当前用户的简历上传记录列表
     * GET /api/enterprise/resume-uploads
     */
    public function list()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $enterpriseId = Request::param('enterpriseId');
        $page = max(1, (int) Request::param('page', 1));
        $pageSize = min(100, max(1, (int) Request::param('pageSize', 50)));

        $query = EnterpriseResumeUpload::where('userId', $userId)
            ->field('id, userId, enterpriseId, fileUrl, fileName, is_default, createdAt as created_at_ts')
            ->order('createdAt', 'desc');

        if ($enterpriseId !== null && $enterpriseId !== '') {
            $eid = (int) $enterpriseId;
            if ($eid > 0) {
                $query->where('enterpriseId', $eid);
            } else {
                $query->whereNull('enterpriseId');
            }
        }

        $total = $query->count();
        $rows = $query->page($page, $pageSize)->select()->toArray();

        $list = [];
        foreach ($rows as $row) {
            $ts = $this->pickCreatedAt($row);
            $list[] = [
                'id'           => (int) ($row['id'] ?? 0),
                'url'          => (string) ($row['fileUrl'] ?? ''),
                'fileName'     => (string) ($row['fileName'] ?? ''),
                'uploadedAt'   => $ts,
                'uploadedAtStr' => $this->formatTime($ts),
                'isDefault'    => (int) ($row['is_default'] ?? 0) === 1,
            ];
        }

        return success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'pageSize' => $pageSize,
        ]);
    }

    /**
     * 新增一条简历上传记录（上传文件后由前端调用）
     * POST /api/enterprise/resume-uploads
     * body: { "url": "文件URL", "fileName": "原始文件名", "enterpriseId": 可选 }
     */
    public function add()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $url = Request::param('url');
        $fileName = Request::param('fileName', '');
        $enterpriseId = Request::param('enterpriseId');

        if (empty($url) || !is_string($url)) {
            return error('缺少文件地址 url', 400);
        }

        $url = trim($url);
        if ($url === '') {
            return error('url 不能为空', 400);
        }

        $fileName = is_string($fileName) ? trim($fileName) : '';
        if ($fileName === '') {
            $fileName = '简历文件';
        }

        $eid = null;
        if ($enterpriseId !== null && $enterpriseId !== '') {
            $eid = (int) $enterpriseId;
            if ($eid <= 0) {
                $eid = null;
            }
        }
        // 前端未传或为 0 时：用当前用户绑定企业（wechat_users.enterpriseId）补全
        if ($eid === null) {
            $wu = Db::name('wechat_users')->where('id', $userId)->field('enterpriseId')->find();
            if (!empty($wu['enterpriseId']) && (int) $wu['enterpriseId'] > 0) {
                $eid = (int) $wu['enterpriseId'];
            }
        }

        $record = new EnterpriseResumeUpload();
        $record->userId = $userId;
        $record->enterpriseId = $eid;
        $record->fileUrl = $url;
        $record->fileName = $fileName;
        $record->createdAt = time();
        $record->save();

        return success([
            'id'           => (int) $record->id,
            'url'          => $record->fileUrl,
            'fileName'     => $record->fileName,
            'uploadedAt'   => (int) $record->createdAt,
            'uploadedAtStr' => $this->formatTime($record->createdAt),
        ]);
    }

    /**
     * 设为默认简历（同用户同企业仅一条为默认）
     * POST /api/enterprise/resume-uploads/set-default  body: { "id": 记录ID }
     */
    public function setDefault()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $id = (int) Request::param('id', 0);
        if ($id <= 0) {
            return error('缺少或无效的记录 id', 400);
        }

        $record = EnterpriseResumeUpload::where('id', $id)->where('userId', $userId)->find();
        if (!$record) {
            return error('记录不存在或无权操作', 404);
        }

        $eid = isset($record->enterpriseId) && (int) $record->enterpriseId > 0 ? (int) $record->enterpriseId : null;

        Db::name('enterprise_resume_uploads')
            ->where('userId', $userId)
            ->where(function ($q) use ($eid) {
                if ($eid !== null) {
                    $q->where('enterpriseId', $eid);
                } else {
                    $q->whereNull('enterpriseId');
                }
            })
            ->update(['is_default' => 0]);

        $record->is_default = 1;
        $record->save();

        return success(['id' => (int) $record->id, 'isDefault' => true]);
    }

    /**
     * 删除一条简历上传记录（仅本人可删）
     * POST /api/enterprise/resume-uploads/delete  body: { "id": 记录ID }
     */
    public function delete()
    {
        $user = $this->request->user ?? null;
        if (!$user || ($user['source'] ?? '') !== 'wechat') {
            return error('未登录', 401);
        }

        $userId = (int) ($user['user_id'] ?? $user['userId'] ?? 0);
        if ($userId <= 0) {
            return error('未登录', 401);
        }

        $id = (int) Request::param('id', 0);
        if ($id <= 0) {
            return error('缺少或无效的记录 id', 400);
        }

        $record = EnterpriseResumeUpload::where('id', $id)->where('userId', $userId)->find();
        if (!$record) {
            return error('记录不存在或无权操作', 404);
        }

        $record->delete();
        return success(['id' => $id]);
    }

    /**
     * 从查询行中取出时间戳（优先用 SQL 别名 created_at_ts，再兼容 createdAt/created_at）；
     * 若值为 4 位数（如年份 2026）则视为无效，返回 0。
     */
    private function pickCreatedAt(array $row): int
    {
        $v = $row['created_at_ts'] ?? $row['createdAt'] ?? $row['created_at'] ?? $row['createdat'] ?? null;
        if ($v === null) {
            return 0;
        }
        $ts = (int) $v;
        if ($ts <= 0) {
            return 0;
        }
        // 小于约 1971 年的秒数视为无效（避免误存为年份 2026 等）
        if ($ts < 86400 * 365) {
            return 0;
        }
        return $ts;
    }

    private function formatTime($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0 || $ts < 86400 * 365) {
            return '';
        }
        $d = getdate($ts);
        return sprintf(
            '%04d-%02d-%02d %02d:%02d',
            $d['year'],
            $d['mon'],
            $d['mday'],
            $d['hours'],
            $d['minutes']
        );
    }
}
