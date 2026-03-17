<?php
namespace app\controller\superadmin;

use app\BaseController;
use app\model\BackupRecord as BackupRecordModel;
use think\facade\Request;
use think\facade\Db;
use think\facade\Config;
use think\facade\Log;

/**
 * 数据库管理控制器（超管专用）
 */
class Database extends BaseController
{
    /**
     * 获取数据库信息
     * @return \think\response\Json
     */
    public function info()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            // 获取数据库配置
            $config = Config::get('database.connections.mysql');
            $database = $config['database'] ?? '';

            // 获取数据库大小
            $dbSize = $this->getDatabaseSize($database);

            // 获取表数量
            $tableCount = $this->getTableCount($database);

            // 获取连接状态
            try {
                Db::query('SELECT 1');
                $connected = true;
            } catch (\Exception $e) {
                $connected = false;
            }

            return success([
                'databaseType' => 'MySQL',
                'databaseName' => $database,
                'connected' => $connected,
                'databaseSize' => $dbSize,
                'tableCount' => $tableCount
            ]);
        } catch (\Exception $e) {
            return error('获取数据库信息失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取表列表
     * @return \think\response\Json
     */
    public function tables()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $config = Config::get('database.connections.mysql');
            $database = $config['database'] ?? '';
            $prefix = $config['prefix'] ?? 'mbti_';

            // 获取所有表
            $tables = Db::query("SHOW TABLE STATUS FROM `{$database}`");

            $result = [];
            foreach ($tables as $table) {
                $tableName = $table['Name'];
                
                // 只显示带前缀的表（或者所有表）
                if (empty($prefix) || strpos($tableName, $prefix) === 0) {
                    // 获取记录数
                    $rowCount = Db::query("SELECT COUNT(*) as count FROM `{$tableName}`")[0]['count'] ?? 0;
                    
                    // 获取索引数
                    $indexes = Db::query("SHOW INDEX FROM `{$tableName}`");
                    $indexCount = count(array_unique(array_column($indexes, 'Key_name')));

                    $result[] = [
                        'name' => $tableName,
                        'docCount' => intval($rowCount),
                        'size' => intval($table['Data_length'] + $table['Index_length']),
                        'indexCount' => $indexCount,
                        'engine' => $table['Engine'] ?? '',
                        'collation' => $table['Collation'] ?? ''
                    ];
                }
            }

            return success($result);
        } catch (\Exception $e) {
            return error('获取表列表失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 查看表数据
     * @return \think\response\Json
     */
    public function viewTable()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $tableName = Request::param('table', '');
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 20);

        if (empty($tableName)) {
            return error('表名不能为空', 400);
        }

        try {
            // 验证表是否存在
            $config = Config::get('database.connections.mysql');
            $database = $config['database'] ?? '';
            $tables = Db::query("SHOW TABLES FROM `{$database}` LIKE '{$tableName}'");
            
            if (empty($tables)) {
                return error('表不存在', 404);
            }

            // 获取表结构
            $columns = Db::query("SHOW COLUMNS FROM `{$tableName}`");
            
            // 获取数据
            $total = Db::name(str_replace($config['prefix'] ?? 'mbti_', '', $tableName))->count();
            $list = Db::name(str_replace($config['prefix'] ?? 'mbti_', '', $tableName))
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return success([
                'columns' => $columns,
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize
            ]);
        } catch (\Exception $e) {
            return error('查看表数据失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 导出表数据
     * @return \think\response\Json
     */
    public function exportTable()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $tableName = Request::param('table', '');

        if (empty($tableName)) {
            return error('表名不能为空', 400);
        }

        try {
            // 生成SQL导出文件
            $backupDir = root_path() . 'runtime/backup/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = $tableName . '_' . date('YmdHis') . '.sql';
            $filepath = $backupDir . $filename;

            $this->exportTableToSql($tableName, $filepath);

            return success([
                'filename' => $filename,
                'filepath' => $filepath,
                'downloadUrl' => '/api/v1/superadmin/database/download?file=' . urlencode($filename)
            ], '导出成功');
        } catch (\Exception $e) {
            return error('导出表数据失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 清空表数据
     * @return \think\response\Json
     */
    public function clearTable()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $tableName = Request::param('table', '');

        if (empty($tableName)) {
            return error('表名不能为空', 400);
        }

        try {
            // 验证表是否存在
            $config = Config::get('database.connections.mysql');
            $database = $config['database'] ?? '';
            $tables = Db::query("SHOW TABLES FROM `{$database}` LIKE '{$tableName}'");
            
            if (empty($tables)) {
                return error('表不存在', 404);
            }

            // 清空表
            Db::execute("TRUNCATE TABLE `{$tableName}`");

            return success(null, '表数据已清空');
        } catch (\Exception $e) {
            return error('清空表数据失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 备份数据库
     * @return \think\response\Json
     */
    public function backup()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            $config = Config::get('database.connections.mysql');
            $host = $config['hostname'] ?? 'localhost';
            $port = $config['hostport'] ?? 3306;
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';

            // 创建备份目录
            $backupDir = root_path() . 'runtime/backup/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = 'backup_' . $database . '_' . date('YmdHis') . '.sql';
            $filepath = $backupDir . $filename;

            // 优先使用PHP方式备份（更可靠）
            $this->backupDatabase($database, $filepath);
            
            // 如果文件不存在或为空，尝试使用mysqldump
            if (!file_exists($filepath) || filesize($filepath) == 0) {
                $mysqldumpPath = $this->findMysqldump();
                
                if ($mysqldumpPath) {
                    // 使用mysqldump命令
                    $command = sprintf(
                        '"%s" -h%s -P%s -u%s -p%s %s > "%s" 2>&1',
                        $mysqldumpPath,
                        escapeshellarg($host),
                        escapeshellarg($port),
                        escapeshellarg($username),
                        escapeshellarg($password),
                        escapeshellarg($database),
                        escapeshellarg($filepath)
                    );
                    
                    exec($command, $output, $returnVar);
                    
                    if ($returnVar !== 0) {
                        throw new \Exception('mysqldump执行失败: ' . implode("\n", $output));
                    }
                }
            }

            // 获取文件大小
            $fileSize = filesize($filepath);

            // 上传到OSS
            $ossUrl = null;
            $ossPath = null;
            try {
                $ossResult = $this->uploadBackupToOss($filepath, $filename);
                if ($ossResult) {
                    $ossUrl = $ossResult['url'];
                    $ossPath = $ossResult['path'];
                }
            } catch (\Exception $e) {
                // OSS上传失败不影响备份成功，只记录错误
                Log::error('备份文件上传OSS失败：' . $e->getMessage());
            }

            // 记录备份信息
            $this->saveBackupRecord($filename, $filepath, $fileSize, $ossUrl, $ossPath);

            return success([
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $fileSize,
                'time' => date('Y-m-d H:i:s'),
                'ossUrl' => $ossUrl,
                'ossPath' => $ossPath,
                'downloadUrl' => '/api/v1/superadmin/database/download?file=' . urlencode($filename)
            ], '备份成功' . ($ossUrl ? '，已上传到OSS' : ''));
        } catch (\Exception $e) {
            return error('备份失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取备份记录列表
     * @return \think\response\Json
     */
    public function backups()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        try {
            // 从数据库读取备份记录
            $records = BackupRecordModel::order('createdAt', 'desc')->select()->toArray();
            
            $backups = [];
            foreach ($records as $record) {
                $backups[] = [
                    'id' => $record['id'],
                    'filename' => $record['filename'],
                    'time' => date('Y-m-d\TH:i:s', $record['createdAt']),
                    'size' => intval($record['fileSize']),
                    'status' => $record['status'] ?? 'success',
                    'ossUrl' => $record['ossUrl'] ?? null,
                    'ossPath' => $record['ossPath'] ?? null,
                    'filepath' => $record['filepath'] ?? null
                ];
            }

            return success($backups);
        } catch (\Exception $e) {
            return error('获取备份记录失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除备份记录（软删除）
     * @return \think\response\Json
     */
    public function delete()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        // 支持从路由参数或请求参数获取ID
        $id = Request::param('id', 0) ?: Request::route('id', 0);

        if (empty($id)) {
            return error('记录ID不能为空', 400);
        }

        try {
            $record = BackupRecordModel::find($id);
            
            if (!$record) {
                return error('备份记录不存在', 404);
            }

            // 软删除（ThinkPHP的SoftDelete会自动设置deletedAt）
            $record->delete();

            return success(null, '备份记录已删除');
        } catch (\Exception $e) {
            return error('删除失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 下载备份文件
     */
    public function download()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $filename = Request::param('file', '');
        
        if (empty($filename)) {
            return error('文件名不能为空', 400);
        }

        // 安全检查：只允许下载备份目录下的文件
        $backupDir = root_path() . 'runtime/backup/';
        $filepath = realpath($backupDir . $filename);

        if (!$filepath || strpos($filepath, realpath($backupDir)) !== 0) {
            return error('文件不存在', 404);
        }

        if (!file_exists($filepath)) {
            return error('文件不存在', 404);
        }

        // 下载文件
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * 恢复数据库
     * @return \think\response\Json
     */
    public function restore()
    {
        // 验证是否为超级管理员
        $user = $this->request->user ?? null;
        if (!$user || $user['role'] !== 'superadmin') {
            return error('无权限访问', 403);
        }

        $filename = Request::param('file', '');

        if (empty($filename)) {
            return error('文件名不能为空', 400);
        }

        try {
            $backupDir = root_path() . 'runtime/backup/';
            $filepath = realpath($backupDir . $filename);

            if (!$filepath || strpos($filepath, realpath($backupDir)) !== 0) {
                return error('文件不存在', 404);
            }

            if (!file_exists($filepath)) {
                return error('文件不存在', 404);
            }

            $config = Config::get('database.connections.mysql');
            $host = $config['hostname'] ?? 'localhost';
            $port = $config['hostport'] ?? 3306;
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';

            // 使用PHP方式恢复
            $this->restoreDatabase($filepath);

            return success(null, '数据库恢复成功');
        } catch (\Exception $e) {
            return error('恢复失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取数据库大小
     */
    private function getDatabaseSize($database)
    {
        try {
            $result = Db::query("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = '{$database}'");
            
            return floatval($result[0]['size_mb'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 获取表数量
     */
    private function getTableCount($database)
    {
        try {
            $result = Db::query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$database}'");
            return intval($result[0]['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 导出表到SQL文件
     */
    private function exportTableToSql($tableName, $filepath)
    {
        $fp = fopen($filepath, 'w');
        
        // 写入表结构
        $createTable = Db::query("SHOW CREATE TABLE `{$tableName}`");
        fwrite($fp, "-- 表结构: {$tableName}\n");
        fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
        fwrite($fp, $createTable[0]['Create Table'] . ";\n\n");
        
        // 写入数据
        $data = Db::query("SELECT * FROM `{$tableName}`");
        if (!empty($data)) {
            fwrite($fp, "-- 表数据: {$tableName}\n");
            foreach ($data as $row) {
                $values = [];
                foreach ($row as $value) {
                    $values[] = is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                }
                fwrite($fp, "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n");
            }
        }
        
        fclose($fp);
    }

    /**
     * 备份数据库（PHP方式）
     */
    private function backupDatabase($database, $filepath)
    {
        $fp = fopen($filepath, 'w');
        
        // 写入文件头
        fwrite($fp, "-- MySQL数据库备份\n");
        fwrite($fp, "-- 数据库: {$database}\n");
        fwrite($fp, "-- 备份时间: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET NAMES utf8mb4;\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        
        // 获取所有表
        $tables = Db::query("SHOW TABLES FROM `{$database}`");
        $tableKey = 'Tables_in_' . $database;
        
        foreach ($tables as $table) {
            $tableName = $table[$tableKey];
            
            // 写入表结构
            $createTable = Db::query("SHOW CREATE TABLE `{$tableName}`");
            if (!empty($createTable)) {
                fwrite($fp, "-- ----------------------------\n");
                fwrite($fp, "-- Table structure for {$tableName}\n");
                fwrite($fp, "-- ----------------------------\n");
                fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($fp, $createTable[0]['Create Table'] . ";\n\n");
                
                // 写入数据
                $data = Db::query("SELECT * FROM `{$tableName}`");
                if (!empty($data)) {
                    fwrite($fp, "-- ----------------------------\n");
                    fwrite($fp, "-- Records of {$tableName}\n");
                    fwrite($fp, "-- ----------------------------\n");
                    
                    foreach ($data as $row) {
                        $columns = [];
                        $values = [];
                        foreach ($row as $col => $val) {
                            $columns[] = "`{$col}`";
                            $values[] = is_null($val) ? 'NULL' : "'" . addslashes($val) . "'";
                        }
                        fwrite($fp, "INSERT INTO `{$tableName}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n");
                    }
                    fwrite($fp, "\n");
                }
            }
        }
        
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);
    }

    /**
     * 恢复数据库（PHP方式）
     */
    private function restoreDatabase($filepath)
    {
        $sql = file_get_contents($filepath);
        
        // 分割SQL语句
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                Db::execute($statement);
            }
        }
    }

    /**
     * 查找mysqldump路径
     */
    private function findMysqldump()
    {
        $paths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'C:\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump'
        ];
        
        foreach ($paths as $path) {
            if (is_executable($path) || shell_exec("which {$path}")) {
                return $path;
            }
        }
        
        return null;
    }

    /**
     * 查找mysql路径
     */
    private function findMysql()
    {
        $paths = [
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
            'C:\\mysql\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'mysql'
        ];
        
        foreach ($paths as $path) {
            if (is_executable($path) || shell_exec("which {$path}")) {
                return $path;
            }
        }
        
        return null;
    }

    /**
     * 上传备份文件到OSS
     * @param string $filepath 本地文件路径
     * @param string $filename 文件名
     * @return array|null 返回OSS URL和路径，失败返回null
     */
    private function uploadBackupToOss($filepath, $filename)
    {
        if (!class_exists('\OSS\OssClient')) {
            throw new \RuntimeException('未安装 Aliyun OSS SDK，请先执行：composer require aliyuncs/oss-sdk-php');
        }

        // 读取OSS配置
        $uploadConfig = Config::get('upload.oss');
        
        $accessKeyId = $uploadConfig['access_key_id'] ?? '';
        $accessKeySecret = $uploadConfig['access_key_secret'] ?? '';
        $endpoint = $uploadConfig['endpoint'] ?? '';
        $bucket = $uploadConfig['bucket'] ?? '';
        $baseUrl = rtrim($uploadConfig['url'] ?? '', '/');

        // 如果配置为空，尝试从环境变量读取
        if (empty($accessKeyId)) {
            $accessKeyId = getenv('OSS_ACCESS_KEY_ID') ?: getenv('ALIYUN_ACCESS_KEY_ID') ?: env('OSS_ACCESS_KEY_ID', env('ALIYUN_ACCESS_KEY_ID', ''));
        }
        if (empty($accessKeySecret)) {
            $accessKeySecret = getenv('OSS_ACCESS_KEY_SECRET') ?: getenv('ALIYUN_OSS_ACCESS_KEY_SECRET') ?: env('OSS_ACCESS_KEY_SECRET', env('ALIYUN_OSS_ACCESS_KEY_SECRET', ''));
        }
        if (empty($endpoint)) {
            $endpoint = getenv('OSS_ENDPOINT') ?: getenv('ALIYUN_OSS_ENDPOINT') ?: env('OSS_ENDPOINT', env('ALIYUN_OSS_ENDPOINT', ''));
        }
        if (empty($bucket)) {
            $bucket = getenv('OSS_BUCKET') ?: getenv('ALIYUN_OSS_BUCKET') ?: env('OSS_BUCKET', env('ALIYUN_OSS_BUCKET', ''));
        }
        if (empty($baseUrl)) {
            $baseUrl = rtrim(getenv('OSS_URL') ?: getenv('ALIYUN_OSS_URL') ?: env('OSS_URL', env('ALIYUN_OSS_URL', '')), '/');
        }

        // 检查配置是否完整
        if (empty($accessKeyId) || empty($accessKeySecret) || empty($endpoint) || empty($bucket) || empty($baseUrl)) {
            throw new \RuntimeException('OSS配置不完整，无法上传备份文件');
        }

        // 构建OSS对象路径（不使用OSS_PREFIX，直接使用backup目录）
        // 格式：backup/2026/02/12/backup_mbti_20260212160100.sql
        $datePath = date('Y/m/d');
        $object = 'backup/' . $datePath . '/' . $filename;

        try {
            // 创建OSS客户端
            $client = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
            
            // 验证Bucket是否存在
            if (!$client->doesBucketExist($bucket)) {
                throw new \RuntimeException("OSS Bucket '{$bucket}' 不存在或无法访问");
            }
            
            // 上传文件
            $client->uploadFile($bucket, $object, $filepath);
            
            // 生成访问URL
            $url = $baseUrl . '/' . ltrim($object, '/');
            
            return [
                'url' => $url,
                'path' => $object
            ];
        } catch (\OSS\Core\OssException $e) {
            throw new \RuntimeException('OSS上传失败：' . $e->getMessage());
        }
    }

    /**
     * 保存备份记录
     */
    private function saveBackupRecord($filename, $filepath, $fileSize, $ossUrl = null, $ossPath = null)
    {
        try {
            BackupRecordModel::create([
                'filename' => $filename,
                'filepath' => $filepath,
                'fileSize' => $fileSize,
                'ossUrl' => $ossUrl,
                'ossPath' => $ossPath,
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            // 记录保存失败不影响备份成功，只记录日志
            Log::error('保存备份记录失败：' . $e->getMessage());
        }
    }
}

