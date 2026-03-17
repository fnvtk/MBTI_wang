<?php
namespace app\model;

use think\Model;

/**
 * 上传文件记录（本地/OSS），用于去重与 URL 查询
 */
class UploadFile extends Model
{
    protected $name = 'upload_files';

    protected $schema = [
        'id'        => 'int',
        'path'      => 'string',
        'url'       => 'string',
        'driver'    => 'string',
        'hash'      => 'string',
        'size'      => 'int',
        'mimeType'  => 'string',
        'extension' => 'string',
        'createdAt' => 'int',
        'updatedAt' => 'int',
    ];

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createdAt';
    protected $updateTime = 'updatedAt';
}
