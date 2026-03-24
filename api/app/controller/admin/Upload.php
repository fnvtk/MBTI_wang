<?php
namespace app\controller\admin;

use app\BaseController;
use app\model\UploadFile;
use think\facade\Request;

/**
 * 上传控制器（支持本地 & 阿里云 OSS）
 * 完全参考 BaseCrawler.php 的实现
 */
class Upload extends BaseController
{
    /**
     * 上传图片（新闻封面等）
     * @return \think\response\Json
     */
    public function image()
    {
        $file = Request::file('file');

        if (!$file) {
            return error('未找到上传文件');
        }

        // 基本校验：大小 & 类型
        $maxSize     = 5 * 1024 * 1024; // 5MB
        $allowExts   = ['jpg', 'jpeg', 'jfif', 'jpe', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'];
        $extension   = strtolower($file->extension());
        $fileSize    = $file->getSize();

        if (!in_array($extension, $allowExts, true)) {
            return error('不支持的文件类型，仅支持：jpg、jpeg、jfif、png、gif、webp、heic');
        }

        if ($fileSize > $maxSize) {
            return error('文件过大，最大支持 5MB');
        }

        $config = config('upload');
        $driver = $config['driver'] ?? 'oss';

        try {
            if ($driver === 'oss') {
                $result = $this->uploadToOss($file, $config['oss'] ?? []);
            } else {
                $result = $this->uploadToLocal($file, $config['local'] ?? []);
            }
        } catch (\Throwable $e) {
            return error('上传失败：' . $e->getMessage());
        }

        return success($result, '上传成功');
    }

    /**
     * 上传文件（简历等，可包含图片 / PDF / Word）
     * @return \think\response\Json
     */
    public function file()
    {
        $file = Request::file('file');

        if (!$file) {
            return error('未找到上传文件');
        }

        // 基本校验：大小 & 类型（放宽为 10MB）
        $maxSize   = 10 * 1024 * 1024; // 10MB
        $allowExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
        $extension = strtolower($file->extension());
        $fileSize  = $file->getSize();

        if (!in_array($extension, $allowExts, true)) {
            return error('不支持的文件类型，仅支持：jpg、jpeg、png、gif、webp、pdf、doc、docx');
        }

        if ($fileSize > $maxSize) {
            return error('文件过大，最大支持 10MB');
        }

        $config = config('upload');
        $driver = $config['driver'] ?? 'oss';

        try {
            if ($driver === 'oss') {
                $result = $this->uploadToOss($file, $config['oss'] ?? []);
            } else {
                $result = $this->uploadToLocal($file, $config['local'] ?? []);
            }
        } catch (\Throwable $e) {
            return error('上传失败：' . $e->getMessage());
        }

        return success($result, '上传成功');
    }

    /**
     * 本地上传
     */
    protected function uploadToLocal($file, array $config): array
    {
        $root = $config['root'] ?? (app()->getRootPath() . 'public/uploads');
        $driver = 'local';

        // 先计算文件哈希，用于去重
        $hash     = md5_file($file->getPathname());
        $mimeType = $this->getFileMimeSafe($file);
        $size     = $file->getSize();
        $extension = strtolower($file->extension());

        // 如果已存在相同文件（同一驱动 + hash），直接返回
        $exists = UploadFile::where(['hash' => $hash, 'driver' => $driver])->find();
        if ($exists) {
            return [
                'path' => $exists->path,
                'url'  => $exists->url,
                'id'   => $exists->id,
            ];
        }

        // 子目录：按 年/月 分目录，例如 2025/12
        $year   = date('Y');
        $month  = date('m');
        $subDir = $year . DIRECTORY_SEPARATOR . $month;
        $dir    = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $subDir;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('创建上传目录失败');
        }

        $filename  = uniqid('img_', true) . '.' . $extension;

        // 保存文件
        $file->move($dir, $filename);

        // web 访问路径使用 / 分隔，例如 uploads/2025/12/xxx.jpg
        $relativePath = 'uploads/' . $year . '/' . $month . '/' . $filename;
        $urlPrefix    = rtrim($config['url'] ?? '', '/');

        // 如果未配置，使用 API 域名（生产环境：api.737270.com）
        if (!$urlPrefix) {
            $apiDomain = env('API_DOMAIN', 'https://api.737270.com');
            $urlPrefix = rtrim($apiDomain, '/');
        }

        $url = $urlPrefix . '/' . $relativePath;

        // 记录上传信息
        $record = new UploadFile();
        $record->path      = $relativePath;
        $record->url       = $url;
        $record->driver    = $driver;
        $record->hash      = $hash;
        $record->size      = $size;
        $record->mimeType  = $mimeType;
        $record->extension = $extension;
        $record->save();

        return [
            'path' => $relativePath,
            'url'  => $url,
            'id'   => $record->id,
        ];
    }

    /**
     * 上传到阿里云 OSS
     * 完全参考 BaseCrawler.php 的实现
     */
    protected function uploadToOss($file, array $config): array
    {
        if (!class_exists('\OSS\OssClient')) {
            throw new \RuntimeException('未安装 Aliyun OSS SDK，请先执行：composer require aliyuncs/oss-sdk-php');
        }

        // 参考 database.php 的配置读取方式，直接从 config 读取（config 已通过 env() 读取 .env）
        $accessKeyId     = $config['access_key_id'] ?? '';
        $accessKeySecret = $config['access_key_secret'] ?? '';
        $endpoint        = $config['endpoint'] ?? '';
        $bucket          = $config['bucket'] ?? '';
        $prefix          = trim($config['prefix'] ?? 'mbti', '/');
        $baseUrl         = rtrim($config['url'] ?? '', '/');

        // 如果未配置 OSS_URL，自动使用 OSS 自带域名：https://{bucket}.{endpoint}
        if (empty($baseUrl) && !empty($bucket) && !empty($endpoint)) {
            // 移除 endpoint 中的协议前缀（如果有）
            $endpointClean = preg_replace('#^https?://#', '', $endpoint);
            $baseUrl = 'https://' . $bucket . '.' . $endpointClean;
        }

        if (empty($accessKeyId) || empty($accessKeySecret) || empty($endpoint) || empty($bucket)) {
            throw new \RuntimeException('OSS 配置不完整，请在 .env 文件中配置 OSS_ACCESS_KEY_ID、OSS_ACCESS_KEY_SECRET、OSS_ENDPOINT、OSS_BUCKET（OSS_URL 可选，不配置则使用 OSS 自带域名）');
        }

        $driver    = 'oss';
        $extension = strtolower($file->extension());
        $hash      = md5_file($file->getPathname());
        $size      = $file->getSize();
        $mimeType  = $this->getFileMimeSafe($file);

        // 先查重（完全参考 BaseCrawler.php）
        try {
            $exists = UploadFile::where(['hash' => $hash, 'driver' => $driver])->find();
            if ($exists) {
                $tempFilePath = $file->getPathname();
                if (file_exists($tempFilePath)) {
                    @unlink($tempFilePath);
                }
                $latestUrl = $baseUrl . '/' . ltrim($exists->path, '/');
                if ($exists->url !== $latestUrl) {
                    $exists->url = $latestUrl;
                    $exists->save();
                }
                return [
                    'path' => $exists->path,
                    'url'  => $latestUrl,
                    'id'   => $exists->id,
                ];
            }
        } catch (\Exception $e) {
            // 查重失败不影响上传流程
        }

        $object = $prefix . '/' . date('Ymd') . '/' . uniqid('img_', true) . '.' . $extension;

        // 保存并临时清除代理设置（完全参考 BaseCrawler.php）
        // 如果 putenv 函数可用则使用，否则跳过代理处理
        $putenvAvailable = function_exists('putenv');
        $originalHttpProxy = false;
        $originalHttpsProxy = false;
        $originalHttpProxyVar = false;
        $originalHttpsProxyVar = false;

        if ($putenvAvailable) {
            $originalHttpProxy = getenv('HTTP_PROXY');
            $originalHttpsProxy = getenv('HTTPS_PROXY');
            $originalHttpProxyVar = getenv('http_proxy');
            $originalHttpsProxyVar = getenv('https_proxy');

            \putenv('HTTP_PROXY=');
            \putenv('HTTPS_PROXY=');
            \putenv('http_proxy=');
            \putenv('https_proxy=');
        }

        try {
            $client = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);

            if (!$client->doesBucketExist($bucket)) {
                throw new \RuntimeException("OSS Bucket '{$bucket}' 不存在或无法访问");
            }

            $client->uploadFile($bucket, $object, $file->getPathname());
        } finally {
            // 恢复代理设置（完全参考 BaseCrawler.php）
            if ($putenvAvailable) {
                if ($originalHttpProxy !== false) {
                    \putenv('HTTP_PROXY=' . $originalHttpProxy);
                } else {
                    \putenv('HTTP_PROXY');
                }
                if ($originalHttpsProxy !== false) {
                    \putenv('HTTPS_PROXY=' . $originalHttpsProxy);
                } else {
                    \putenv('HTTPS_PROXY');
                }
                if ($originalHttpProxyVar !== false) {
                    \putenv('http_proxy=' . $originalHttpProxyVar);
                } else {
                    \putenv('http_proxy');
                }
                if ($originalHttpsProxyVar !== false) {
                    \putenv('https_proxy=' . $originalHttpsProxyVar);
                } else {
                    \putenv('https_proxy');
                }
            }
        }

        // 上传成功后，删除本地临时文件（完全参考 BaseCrawler.php）
        $tempFilePath = $file->getPathname();
        if (file_exists($tempFilePath)) {
            @unlink($tempFilePath);
        }

        // 生成文件访问 URL（完全参考 BaseCrawler.php）
        $ossUrl = $baseUrl . '/' . ltrim($object, '/');

        // 记录上传信息（完全参考 BaseCrawler.php，try-catch 包裹）
        try {
            $record = new UploadFile();
            $record->path = $object;
            $record->url = $ossUrl;
            $record->driver = $driver;
            $record->hash = $hash;
            $record->size = $size;
            $record->mimeType = $mimeType;
            $record->extension = $extension;
            $record->save();
        } catch (\Exception $e) {
            // 记录失败不影响返回 URL
        }

        return [
            'path' => $object,
            'url'  => $ossUrl,
            'id'   => $record->id ?? 0,
        ];
    }

    /**
     * 安全获取文件 MIME：服务器未开 fileinfo 扩展时用扩展名推断，避免 finfo_open() 报错
     */
    protected function getFileMimeSafe($file): string
    {
        if (function_exists('finfo_open')) {
            try {
                return $file->getMime() ?: $this->mimeByExtension($file->extension());
            } catch (\Throwable $e) {
                return $this->mimeByExtension($file->extension());
            }
        }
        return $this->mimeByExtension($file->extension());
    }

    private function mimeByExtension(string $ext): string
    {
        $ext = strtolower($ext ?: '');
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jfif' => 'image/jpeg',
            'jpe'  => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
