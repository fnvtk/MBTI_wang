<?php
namespace app\model;

use think\Model;

/**
 * 高考用户档案
 */
class GaokaoUserProfile extends Model
{
    protected $name = 'gaokao_user_profile';

    protected $schema = [
        'id'              => 'int',
        'userId'          => 'int',
        'tenantId'        => 'int',
        'entryStatus'     => 'int',
        'mbtiStatus'      => 'int',
        'pdpStatus'       => 'int',
        'discStatus'      => 'int',
        'formStatus'      => 'int',
        'analyzeStatus'   => 'int',
        'lastAnalyzeAt'   => 'int',
        'latestReportId'  => 'int',
        'name'            => 'string',
        'province'        => 'string',
        'streamSubjects'  => 'string',
        'estimatedScore'  => 'int',
        'formJson'        => 'string',
        'tagsJson'        => 'string',
        'createdAt'       => 'int',
        'updatedAt'       => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
    protected $json = ['formJson', 'tagsJson'];
}

