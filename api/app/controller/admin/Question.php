<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\Question as QuestionModel;
use think\facade\Request;
use think\facade\Db;

/**
 * 题库管理控制器（企业管理员和普通管理员）
 * 企业管理员只能管理自己企业的题库，如果没有则使用超管题库
 */
class Question extends BaseController
{
    /**
     * 获取题库列表
     * 如果企业没有自己的题库，返回超管题库
     * @return \think\response\Json
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 确定企业ID
        $enterpriseId = null;
        if ($user['role'] === 'enterprise_admin') {
            // 企业管理员：使用自己的企业ID
            $userModel = Db::name('users')->where('id', $user['userId'])->find();
            $enterpriseId = $userModel['enterpriseId'] ?? null;
        } elseif ($user['role'] === 'admin') {
            // 普通管理员：可以查看所有企业的题库，但优先显示超管题库
            $enterpriseId = Request::param('enterpriseId', null);
        } else {
            return error('无权限访问', 403);
        }

        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 20);
        $type = Request::param('type', ''); // mbti/disc/pdp
        $status = Request::param('status', ''); // 1启用/0禁用

        $where = [];
        
        // 如果指定了企业ID，优先查询企业题库
        // 如果没有企业题库，则查询超管题库（enterpriseId = NULL）
        if ($enterpriseId !== null) {
            // 先检查企业是否有自己的题库（未指定 type 时需统计 mbti/disc/pdp 三类）
            $countQuery = QuestionModel::where('enterpriseId', $enterpriseId);
            if ($type !== '') {
                $countQuery->where('type', $type);
            } else {
                $countQuery->whereIn('type', ['mbti', 'disc', 'pdp']);
            }
            $enterpriseQuestionCount = $countQuery->count();
            
            if ($enterpriseQuestionCount > 0) {
                // 使用企业题库
                $where['enterpriseId'] = $enterpriseId;
            } else {
                // 使用超管题库
                $where['enterpriseId'] = null;
            }
        } else {
            // 普通管理员查看超管题库
            $where['enterpriseId'] = null;
        }
        
        // 类型筛选
        if ($type) {
            $where['type'] = $type;
        }
        
        // 状态筛选
        if ($status !== '') {
            $where['status'] = $status;
        }

        // 查询题库列表
        $list = QuestionModel::where($where)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        // 处理 options 字段，确保返回数组格式
        foreach ($list as &$item) {
            if (isset($item['options'])) {
                // 如果是对象格式（stdClass），先转换为数组
                if (is_object($item['options'])) {
                    $item['options'] = json_decode(json_encode($item['options']), true);
                }
                // 如果是关联数组（不是索引数组），转换为索引数组
                if (is_array($item['options']) && !isset($item['options'][0])) {
                    $item['options'] = array_values($item['options']);
                }
            }
        }
        unset($item);

        // 总数
        $total = QuestionModel::where($where)->count();

        // 标识当前使用的是企业题库还是超管题库
        $isUsingSuperAdminBank = ($where['enterpriseId'] === null);

        return success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'isUsingSuperAdminBank' => $isUsingSuperAdminBank,
            'enterpriseId' => $enterpriseId
        ]);
    }

    /**
     * 获取题目详情
     * @param int $id
     * @return \think\response\Json
     */
    public function detail($id)
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 确定企业ID
        $enterpriseId = null;
        if ($user['role'] === 'enterprise_admin') {
            $userModel = Db::name('users')->where('id', $user['userId'])->find();
            $enterpriseId = $userModel['enterpriseId'] ?? null;
        } elseif ($user['role'] === 'admin') {
            $enterpriseId = Request::param('enterpriseId', null);
        } else {
            return error('无权限访问', 403);
        }

        // 先查询企业题库，如果没有则查询超管题库
        $question = null;
        if ($enterpriseId !== null) {
            $question = QuestionModel::where('id', $id)
                ->where('enterpriseId', $enterpriseId)
                ->find();
        }
        
        if (!$question) {
            $question = QuestionModel::where('id', $id)
                ->where('enterpriseId', null)
                ->find();
        }
        
        if (!$question) {
            return error('题目不存在', 404);
        }

        $data = $question->toArray();
        
        // 处理 options 字段，确保返回数组格式
        if (isset($data['options'])) {
            // 如果是对象格式（stdClass），先转换为数组
            if (is_object($data['options'])) {
                $data['options'] = json_decode(json_encode($data['options']), true);
            }
            // 如果是关联数组（不是索引数组），转换为索引数组
            if (is_array($data['options']) && !isset($data['options'][0])) {
                $data['options'] = array_values($data['options']);
            }
        }

        return success($data);
    }

    /**
     * 创建题目（企业管理员只能创建自己企业的题目）
     * @return \think\response\Json
     */
    public function create()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 只有企业管理员可以创建题目
        if ($user['role'] !== 'enterprise_admin') {
            return error('只有企业管理员可以创建题目', 403);
        }

        // 获取企业ID
        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        $enterpriseId = $userModel['enterpriseId'] ?? null;
        
        if (!$enterpriseId) {
            return error('企业信息不存在', 400);
        }

        $data = Request::only(['type', 'question', 'options', 'dimension', 'sort', 'status']);

        // 验证必填字段
        if (empty($data['type']) || empty($data['question']) || empty($data['options'])) {
            return error('题目类型、题目内容和选项不能为空', 400);
        }

        // 验证类型
        if (!in_array($data['type'], ['mbti', 'disc', 'pdp'])) {
            return error('题目类型必须是 mbti、disc 或 pdp', 400);
        }

        // 验证选项格式
        if (!is_array($data['options'])) {
            return error('选项必须是数组格式', 400);
        }

        // MBTI类型需要dimension字段
        if ($data['type'] === 'mbti' && empty($data['dimension'])) {
            return error('MBTI类型题目必须指定维度（EI/SN/TF/JP）', 400);
        }

        // 设置企业ID
        $data['enterpriseId'] = $enterpriseId;
        
        // 设置默认值
        $data['sort'] = $data['sort'] ?? 0;
        $data['status'] = $data['status'] ?? 1;

        // 创建题目
        $question = QuestionModel::create($data);

        return success($question->toArray(), '创建成功');
    }

    /**
     * 更新题目（企业管理员只能更新自己企业的题目）
     * @param int $id
     * @return \think\response\Json
     */
    public function update($id)
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 只有企业管理员可以更新题目
        if ($user['role'] !== 'enterprise_admin') {
            return error('只有企业管理员可以更新题目', 403);
        }

        // 获取企业ID
        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        $enterpriseId = $userModel['enterpriseId'] ?? null;
        
        if (!$enterpriseId) {
            return error('企业信息不存在', 400);
        }

        // 只能更新自己企业的题目
        $question = QuestionModel::where('id', $id)
            ->where('enterpriseId', $enterpriseId)
            ->find();
        
        if (!$question) {
            return error('题目不存在或无权限修改', 404);
        }

        $data = Request::only(['type', 'question', 'options', 'dimension', 'sort', 'status']);

        // 验证类型
        if (isset($data['type']) && !in_array($data['type'], ['mbti', 'disc', 'pdp'])) {
            return error('题目类型必须是 mbti、disc 或 pdp', 400);
        }

        // 验证选项格式
        if (isset($data['options']) && !is_array($data['options'])) {
            return error('选项必须是数组格式', 400);
        }

        // MBTI类型需要dimension字段
        if (($data['type'] ?? $question->type) === 'mbti' && empty($data['dimension'] ?? $question->dimension)) {
            return error('MBTI类型题目必须指定维度（EI/SN/TF/JP）', 400);
        }

        // 更新题目
        $question->save($data);

        return success($question->toArray(), '更新成功');
    }

    /**
     * 删除题目（软删除，企业管理员只能删除自己企业的题目）
     * @param int $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 只有企业管理员可以删除题目
        if ($user['role'] !== 'enterprise_admin') {
            return error('只有企业管理员可以删除题目', 403);
        }

        // 获取企业ID
        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        $enterpriseId = $userModel['enterpriseId'] ?? null;
        
        if (!$enterpriseId) {
            return error('企业信息不存在', 400);
        }

        // 只能删除自己企业的题目
        $question = QuestionModel::where('id', $id)
            ->where('enterpriseId', $enterpriseId)
            ->find();
        
        if (!$question) {
            return error('题目不存在或无权限删除', 404);
        }

        // 执行软删除
        $question->delete();

        return success(null, '删除成功');
    }

    /**
     * 批量导入题目（企业管理员）
     * @return \think\response\Json
     */
    public function batchImport()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 只有企业管理员可以导入题目
        if ($user['role'] !== 'enterprise_admin') {
            return error('只有企业管理员可以导入题目', 403);
        }

        // 获取企业ID
        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        $enterpriseId = $userModel['enterpriseId'] ?? null;
        
        if (!$enterpriseId) {
            return error('企业信息不存在', 400);
        }

        $questions = Request::param('questions', []);

        if (empty($questions) || !is_array($questions)) {
            return error('题目数据不能为空', 400);
        }

        $successCount = 0;
        $failCount = 0;
        $errors = [];

        Db::startTrans();
        try {
            foreach ($questions as $index => $q) {
                // 验证必填字段
                if (empty($q['type']) || empty($q['question']) || empty($q['options'])) {
                    $failCount++;
                    $errors[] = "第" . ($index + 1) . "题：题目类型、题目内容和选项不能为空";
                    continue;
                }

                // 验证类型
                if (!in_array($q['type'], ['mbti', 'disc', 'pdp'])) {
                    $failCount++;
                    $errors[] = "第" . ($index + 1) . "题：题目类型必须是 mbti、disc 或 pdp";
                    continue;
                }

                // MBTI类型需要dimension字段
                if ($q['type'] === 'mbti' && empty($q['dimension'])) {
                    $failCount++;
                    $errors[] = "第" . ($index + 1) . "题：MBTI类型题目必须指定维度";
                    continue;
                }

                // 设置企业ID
                $q['enterpriseId'] = $enterpriseId;
                $q['sort'] = $q['sort'] ?? ($index + 1);
                $q['status'] = $q['status'] ?? 1;

                QuestionModel::create($q);
                $successCount++;
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return error('批量导入失败：' . $e->getMessage(), 500);
        }

        return success([
            'successCount' => $successCount,
            'failCount' => $failCount,
            'errors' => $errors
        ], "成功导入 {$successCount} 题，失败 {$failCount} 题");
    }

    /**
     * 切换题目状态
     * @param int $id
     * @return \think\response\Json
     */
    public function toggleStatus($id)
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 只有企业管理员可以切换状态
        if ($user['role'] !== 'enterprise_admin') {
            return error('只有企业管理员可以切换题目状态', 403);
        }

        // 获取企业ID
        $userModel = Db::name('users')->where('id', $user['userId'])->find();
        $enterpriseId = $userModel['enterpriseId'] ?? null;
        
        if (!$enterpriseId) {
            return error('企业信息不存在', 400);
        }

        // 只能操作自己企业的题目
        $question = QuestionModel::where('id', $id)
            ->where('enterpriseId', $enterpriseId)
            ->find();
        
        if (!$question) {
            return error('题目不存在或无权限操作', 404);
        }

        $question->status = $question->status == 1 ? 0 : 1;
        $question->save();

        return success($question->toArray(), '状态更新成功');
    }
}

