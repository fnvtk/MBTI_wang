<?php
namespace app\model;

use think\Model;
use think\facade\Db;

/**
 * 用户画像汇总模型
 * 实际表名: 前缀 + user_profile （如 mbti_user_profile）
 */
class UserProfile extends Model
{
    protected $name = 'user_profile';

    protected $schema = [
        'id'              => 'int',
        'userId'          => 'int',
        'userType'        => 'string',
        'enterpriseId'    => 'int',
        'testsTotal'      => 'int',
        'testsMbti'       => 'int',
        'testsDisc'       => 'int',
        'testsPdp'        => 'int',
        'testsFace'       => 'int',
        'ordersTotal'     => 'int',
        'paidOrders'      => 'int',
        'totalPaidAmount' => 'int',
        'lastTestResultId'=> 'int',
        'lastTestType'    => 'string',
        'lastTestAt'      => 'int',
        'lastMbtiResultId'=> 'int',
        'lastDiscResultId'=> 'int',
        'lastPdpResultId' => 'int',
        'lastFaceResultId'=> 'int',
        'createdAt'       => 'int',
        'updatedAt'       => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';

    /**
     * 测试完成后更新用户画像统计与最近测试ID
     */
    public static function recordTest(int $userId, string $testType, int $testResultId, ?int $enterpriseId = null, ?int $createdAt = null): void
    {
        if ($userId <= 0 || !$testType || $testResultId <= 0) {
            return;
        }
        $now = $createdAt ?: time();
        $userType = $enterpriseId ? 'enterprise' : 'personal';

        [$data, $id] = self::loadOrInitRow($userId, $userType, $enterpriseId, $now);

        $data['testsTotal']++;
        switch ($testType) {
            case 'mbti':
                $data['testsMbti']++;
                $data['lastMbtiResultId'] = $testResultId;
                break;
            case 'disc':
                $data['testsDisc']++;
                $data['lastDiscResultId'] = $testResultId;
                break;
            case 'pdp':
                $data['testsPdp']++;
                $data['lastPdpResultId'] = $testResultId;
                break;
            case 'face':
            case 'ai':
                $data['testsFace']++;
                $data['lastFaceResultId'] = $testResultId;
                break;
        }

        $data['lastTestResultId'] = $testResultId;
        $data['lastTestType'] = $testType;
        $data['lastTestAt'] = $now;
        $data['updatedAt'] = $now;

        self::upsertRow($data, $id);
    }

    /**
     * 支付成功后更新订单统计与总支付金额
     *
     * @param int      $userId
     * @param int|null $enterpriseId
     * @param int      $amountFen  本次支付金额（分）
     */
    public static function recordPayment(int $userId, ?int $enterpriseId, int $amountFen): void
    {
        if ($userId <= 0 || $amountFen <= 0) {
            return;
        }
        $now = time();
        $userType = $enterpriseId ? 'enterprise' : 'personal';

        [$data, $id] = self::loadOrInitRow($userId, $userType, $enterpriseId, $now);

        $data['ordersTotal'] = (int) ($data['ordersTotal'] ?? 0) + 1;
        $data['paidOrders'] = (int) ($data['paidOrders'] ?? 0) + 1;
        $currentTotal = (int) ($data['totalPaidAmount'] ?? 0);
        $data['totalPaidAmount'] = $currentTotal + $amountFen;
        $data['updatedAt'] = $now;

        self::upsertRow($data, $id);
    }

    /**
     * 读或初始化一行画像数据
     */
    protected static function loadOrInitRow(int $userId, string $userType, ?int $enterpriseId, int $now): array
    {
        $where = [
            'userId'       => $userId,
            'userType'     => $userType,
            'enterpriseId' => $enterpriseId,
        ];

        $row = Db::name('user_profile')->where($where)->lock(true)->find();

        $base = [
            'testsTotal'      => 0,
            'testsMbti'       => 0,
            'testsDisc'       => 0,
            'testsPdp'        => 0,
            'testsFace'       => 0,
            'ordersTotal'     => 0,
            'paidOrders'      => 0,
            'totalPaidAmount' => 0,
            'lastMbtiResultId'=> null,
            'lastDiscResultId'=> null,
            'lastPdpResultId' => null,
            'lastFaceResultId'=> null,
        ];

        if ($row) {
            $data = array_merge($base, $row);
            $id = (int) $row['id'];
        } else {
            $data = array_merge($base, $where, ['createdAt' => $now]);
            $id = 0;
        }

        return [$data, $id];
    }

    /**
     * 写入或更新一行画像数据
     */
    protected static function upsertRow(array $data, int $id): void
    {
        if ($id > 0) {
            Db::name('user_profile')->where('id', $id)->update($data);
        } else {
            Db::name('user_profile')->insert($data);
        }
    }
}

