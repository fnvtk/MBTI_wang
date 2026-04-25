<?php
namespace app\model;

use think\Model;

/**
 * 高考分析报告快照
 */
class GaokaoReport extends Model
{
    protected $name = 'gaokao_report';

    protected $schema = [
        'id'                => 'int',
        'userId'            => 'int',
        'tenantId'          => 'int',
        'version'           => 'string',
        'inputSnapshotJson' => 'string',
        'reportJson'        => 'string',
        'overview'          => 'string',
        'searchMetaJson'    => 'string',
        'status'            => 'int',
        'errorMsg'          => 'string',
        'createdAt'         => 'int',
    ];

    protected $autoWriteTimestamp = false;
    protected $json = ['inputSnapshotJson', 'reportJson', 'searchMetaJson'];
}

