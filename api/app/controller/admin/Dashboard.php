<?php
namespace app\controller\admin;

use app\BaseController;
use app\controller\admin\concern\ExtractsTestResults;
use think\facade\Db;
use think\facade\Request;

/**
 * 数据概览控制器（普通管理员）
 */
class Dashboard extends BaseController
{
    use ExtractsTestResults;
    /**
     * 获取统计数据
     * @return \think\response\Json
     */
    public function index()
    {
        $user = $this->request->user ?? null;
        
        if (!$user) {
            return error('未登录', 401);
        }

        // 验证是否为管理员
        if (!in_array($user['role'], ['admin', 'enterprise_admin'])) {
            return error('无权限访问', 403);
        }

        try {
            // admin / enterprise_admin 均只统计本企业数据
            $enterpriseId = $user['enterpriseId'] ?? null;
            if (is_array($enterpriseId)) {
                $enterpriseId = null;
            }
            $enterpriseId = $enterpriseId !== null && $enterpriseId !== '' ? (int) $enterpriseId : null;
            if ($enterpriseId !== null && $enterpriseId <= 0) {
                $enterpriseId = null;
            }
            if (!$enterpriseId) {
                $adminRow = Db::name('users')->where('id', $user['userId'] ?? 0)->find();
                $eid = $adminRow['enterpriseId'] ?? null;
                $enterpriseId = ($eid !== null && $eid !== '') ? (int) $eid : null;
                if ($enterpriseId !== null && $enterpriseId <= 0) {
                    $enterpriseId = null;
                }
            }

            // 企业用户 ID 集合（用于后续统计个人版测试）
            $enterpriseUserIds = [];
            if ($enterpriseId) {
                $enterpriseUserIds = Db::name('wechat_users')
                    ->where('enterpriseId', $enterpriseId)
                    ->column('id');
                $enterpriseUserIds = array_values(array_filter($enterpriseUserIds));
            }

            // 总用户数：wechat_users.enterpriseId = 本企业
            if ($enterpriseId) {
                $totalUsers = count($enterpriseUserIds);
            } else {
                try {
                    $totalUsers = (int) Db::name('wechat_users')->count('openid', true);
                } catch (\Throwable $e) {
                    $totalUsers = (int) Db::name('wechat_users')->count();
                }
            }

            // 已完成测试数：严格按 test_results.enterpriseId 归属企业统计
            if ($enterpriseId) {
                $testsCompleted = (int) Db::name('test_results')
                    ->where('enterpriseId', $enterpriseId)
                    ->count();
            } else {
                $testsCompleted = (int) Db::name('test_results')->count();
            }

            // 今日活跃用户数
            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $todayEnd = strtotime(date('Y-m-d 23:59:59'));
            $activeQuery = Db::name('test_results')
                ->where('createdAt', '>=', $todayStart)
                ->where('createdAt', '<=', $todayEnd);
            if ($enterpriseId) {
                $activeQuery->where('enterpriseId', $enterpriseId);
                $activeIds  = $activeQuery->distinct(true)->column('userId');
                $activeToday = count(array_filter($activeIds));
            } else {
                $activeIds  = $activeQuery->distinct(true)->column('userId');
                $activeToday = count(array_filter($activeIds));
            }

            // 待审核（暂返回0）
            $pendingReviews = 0;

            // 最近 14 天测试趋势
            $days = 14;
            $startDate = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')));
            $trendQuery = Db::name('test_results')
                ->where('createdAt', '>=', $startDate)
                ->whereIn('testType', ['face', 'mbti', 'disc', 'pdp']);
            if ($enterpriseId) {
                $trendQuery->where('enterpriseId', $enterpriseId);
            }
            $trendRows = $trendQuery
                ->field("FROM_UNIXTIME(createdAt, '%Y-%m-%d') as d, testType, COUNT(*) as c")
                ->group('d,testType')
                ->order('d', 'asc')
                ->select()
                ->toArray();

            // 组装为按日期汇总的数组
            $trendMap = [];
            foreach ($trendRows as $row) {
                $d = $row['d'];
                $type = $row['testType'];
                $cnt = (int) ($row['c'] ?? 0);
                if (!isset($trendMap[$d])) {
                    $trendMap[$d] = [
                        'date'  => $d,
                        'face'  => 0,
                        'mbti'  => 0,
                        'disc'  => 0,
                        'pdp'   => 0,
                        'total' => 0,
                    ];
                }
                if (in_array($type, ['face', 'mbti', 'disc', 'pdp'], true)) {
                    $trendMap[$d][$type] += $cnt;
                    $trendMap[$d]['total'] += $cnt;
                }
            }

            // 补齐没有数据的日期
            $trendData = [];
            for ($i = 0; $i < $days; $i++) {
                $d = date('Y-m-d', strtotime('-' . ($days - 1 - $i) . ' days'));
                if (isset($trendMap[$d])) {
                    $trendData[] = $trendMap[$d];
                } else {
                    $trendData[] = [
                        'date'  => $d,
                        'face'  => 0,
                        'mbti'  => 0,
                        'disc'  => 0,
                        'pdp'   => 0,
                        'total' => 0,
                    ];
                }
            }

            $topTestUsers = $this->buildTopTestUsers($enterpriseId, 10);

            $testCatalog = $this->buildTestCatalog($enterpriseId);
            $distributionMbti = $this->aggregateTestLabels($enterpriseId, 'mbti', 14);
            $distributionDisc = $this->aggregateTestLabels($enterpriseId, 'disc', 12);
            $distributionPdp = $this->aggregateTestLabels($enterpriseId, 'pdp', 12);
            $faceSubtypeHints = $this->aggregateFaceSubtypeHints($enterpriseId, 8);

            return success([
                'totalUsers' => $totalUsers,
                'testsCompleted' => $testsCompleted,
                'activeToday' => $activeToday,
                'pendingReviews' => $pendingReviews,
                'testTrends' => $trendData,
                'topTestUsers' => $topTestUsers,
                'testCatalog' => $testCatalog,
                'distributionMbti' => $distributionMbti,
                'distributionDisc' => $distributionDisc,
                'distributionPdp' => $distributionPdp,
                'faceSubtypeHints' => $faceSubtypeHints,
            ]);
        } catch (\Exception $e) {
            return error('获取统计数据失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 按测试完成次数排序，取前 N 名小程序用户（与列表页口径一致：test_results 按企业过滤）
     */
    private function buildTopTestUsers(?int $enterpriseId, int $limit = 10): array
    {
        $limit = min(max($limit, 1), 50);
        $q = Db::name('test_results')->field('userId, COUNT(*) as cnt')->group('userId')->order('cnt', 'desc')->limit($limit);
        if ($enterpriseId) {
            $q->where('enterpriseId', $enterpriseId);
        }
        $rankRows = $q->select()->toArray();
        if (empty($rankRows)) {
            return [];
        }
        $uids = array_values(array_filter(array_map(static function ($r) {
            return (int) ($r['userId'] ?? 0);
        }, $rankRows)));
        $countMap = [];
        foreach ($rankRows as $r) {
            $uid = (int) ($r['userId'] ?? 0);
            if ($uid > 0) {
                $countMap[$uid] = (int) ($r['cnt'] ?? 0);
            }
        }
        if (empty($uids)) {
            return [];
        }

        $users = Db::name('wechat_users')
            ->whereIn('id', $uids)
            ->field('id,nickname,phone,avatar,createdAt')
            ->select()
            ->toArray();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[(int) $u['id']] = $u;
        }

        $trQuery = Db::name('test_results')->whereIn('userId', $uids);
        if ($enterpriseId) {
            $trQuery->where('enterpriseId', $enterpriseId);
        }
        $testRows = $trQuery
            ->field('userId, testType, resultData, createdAt')
            ->order('createdAt', 'desc')
            ->select()
            ->toArray();

        $testsByUser = [];
        foreach ($testRows as $row) {
            $uid = (int) ($row['userId'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (!isset($testsByUser[$uid])) {
                $testsByUser[$uid] = [];
            }
            $raw = $row['resultData'] ?? '';
            $testsByUser[$uid][] = [
                'testType'  => $row['testType'] ?? '',
                'result'    => is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE),
                'createdAt' => (int) ($row['createdAt'] ?? 0),
            ];
        }

        $out = [];
        foreach ($uids as $uid) {
            $wu = $userMap[$uid] ?? null;
            $tests = $testsByUser[$uid] ?? [];
            $lastAt = 0;
            foreach ($tests as $t) {
                $lastAt = max($lastAt, (int) ($t['createdAt'] ?? 0));
            }
            $out[] = [
                'id'            => $uid,
                'username'      => $wu ? ($wu['nickname'] ?? ('用户' . $uid)) : ('用户' . $uid),
                'nickname'      => $wu ? ($wu['nickname'] ?? '') : '',
                'phone'         => $wu ? ($wu['phone'] ?? '') : '',
                'avatar'        => $wu ? ($wu['avatar'] ?? '') : '',
                'testCount'     => $countMap[$uid] ?? 0,
                'lastTestAt'    => $lastAt > 0 ? $lastAt : null,
                'mbtiType'      => $this->extractResultType($tests, 'mbti'),
                'pdpType'       => $this->extractResultType($tests, 'pdp'),
                'discType'      => $this->extractResultType($tests, 'disc'),
                'faceMbtiType'  => $this->extractFaceSubType($tests, 'mbti'),
                'faceDiscType'  => $this->extractFaceSubType($tests, 'disc'),
                'facePdpType'   => $this->extractFaceSubType($tests, 'pdp'),
            ];
        }

        return $out;
    }

    /**
     * 四类测评完成人次 / 参与人数（本企业口径）
     *
     * @return array<int, array{key:string,label:string,records:int,uniqueUsers:int}>
     */
    private function buildTestCatalog(?int $enterpriseId): array
    {
        $defs = [
            ['key' => 'face', 'label' => '人脸分析'],
            ['key' => 'mbti', 'label' => 'MBTI'],
            ['key' => 'disc', 'label' => 'DISC'],
            ['key' => 'pdp', 'label' => 'PDP'],
        ];
        $out = [];
        foreach ($defs as $def) {
            $tt = $def['key'];
            $q = Db::name('test_results')->where('testType', $tt);
            if ($enterpriseId) {
                $q->where('enterpriseId', $enterpriseId);
            }
            $records = (int) $q->count();
            $q2 = Db::name('test_results')->where('testType', $tt);
            if ($enterpriseId) {
                $q2->where('enterpriseId', $enterpriseId);
            }
            $uniqueUsers = (int) $q2->distinct(true)->count('userId');
            $out[] = [
                'key'         => $tt,
                'label'       => $def['label'],
                'records'     => $records,
                'uniqueUsers' => $uniqueUsers,
            ];
        }

        return $out;
    }

    /**
     * 按结果标签聚合单类测评（与列表摘要同一解析逻辑）
     *
     * @return array<int, array{label:string,count:int}>
     */
    private function aggregateTestLabels(?int $enterpriseId, string $testType, int $topN): array
    {
        $counts = [];
        $query = Db::name('test_results')
            ->where('testType', $testType)
            ->field('id,resultData');
        if ($enterpriseId) {
            $query->where('enterpriseId', $enterpriseId);
        }
        $query->chunk(400, function ($rows) use (&$counts, $testType) {
            foreach ($rows as $row) {
                $raw = $row['resultData'] ?? '';
                $label = $this->labelFromResultRow($testType, $raw);
                if ($label === '') {
                    $label = '未识别';
                }
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        });
        arsort($counts);

        return $this->countsToTopNWithOther($counts, $topN);
    }

    /**
     * 人脸结果中推测的 MBTI / DISC / PDP 标签分布（辅助「面相」侧报告）
     *
     * @return array{mbti:array,disc:array,pdp:array}
     */
    private function aggregateFaceSubtypeHints(?int $enterpriseId, int $topN): array
    {
        $subMaps = ['mbti' => [], 'disc' => [], 'pdp' => []];
        $query = Db::name('test_results')
            ->where('testType', 'face')
            ->field('id,resultData');
        if ($enterpriseId) {
            $query->where('enterpriseId', $enterpriseId);
        }
        $query->chunk(400, function ($rows) use (&$subMaps) {
            foreach ($rows as $row) {
                $raw = $row['resultData'] ?? '';
                $str = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
                if ($str === '' || $str === 'null') {
                    continue;
                }
                foreach (['mbti', 'disc', 'pdp'] as $sub) {
                    $label = $this->extractFaceSubType([['testType' => 'face', 'result' => $str]], $sub);
                    if ($label === '') {
                        continue;
                    }
                    $subMaps[$sub][$label] = ($subMaps[$sub][$label] ?? 0) + 1;
                }
            }
        });

        $out = [];
        foreach ($subMaps as $k => $counts) {
            arsort($counts);
            $out[$k] = $this->countsToTopNWithOther($counts, $topN);
        }

        return $out;
    }

    /**
     * @param array<string,int> $counts
     * @return array<int, array{label:string,count:int}>
     */
    private function countsToTopNWithOther(array $counts, int $topN): array
    {
        $topN = min(max($topN, 1), 50);
        $items = [];
        $i = 0;
        $other = 0;
        foreach ($counts as $label => $c) {
            $c = (int) $c;
            if ($i < $topN) {
                $items[] = ['label' => (string) $label, 'count' => $c];
                $i++;
            } else {
                $other += $c;
            }
        }
        if ($other > 0) {
            $items[] = ['label' => '其他', 'count' => $other];
        }

        return $items;
    }

    private function labelFromResultRow(string $testType, $raw): string
    {
        $str = is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE);
        if ($str === '' || $str === 'null') {
            return '';
        }

        return $this->extractResultType([['testType' => $testType, 'result' => $str]], $testType);
    }

    /**
     * 格式化时间
     * @param int $timestamp
     * @return string
     */
    private function formatTime($timestamp)
    {
        if (!$timestamp) {
            return '';
        }

        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . '天前';
        } else {
            return date('Y-m-d H:i', $timestamp);
        }
    }
}
