<?php
namespace app\controller\api;

use app\controller\admin\Upload as AdminUpload;

/**
 * 小程序用户上传（头像等），复用管理端上传逻辑，需 JWT 认证
 */
class Upload extends AdminUpload
{
}
