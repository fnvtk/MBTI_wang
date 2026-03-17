<?php
namespace app\model;

use think\Model;

/**
 * 企业版简历上传记录（仅记录上传，支持预览）
 */
class EnterpriseResumeUpload extends Model
{
    protected $name = 'enterprise_resume_uploads';

    protected $schema = [
        'id'        => 'int',
        'userId'    => 'int',
        'enterpriseId' => 'int',
        'fileUrl'   => 'string',
        'fileName'  => 'string',
        'is_default' => 'int',
        'createdAt' => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
}
