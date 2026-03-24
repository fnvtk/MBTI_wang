<?php
// 应用公共文件

/**
 * 统一响应格式
 * @param int $code 状态码
 * @param string $message 消息
 * @param mixed $data 数据
 * @return \think\response\Json
 */
function json_response($code = 200, $message = 'success', $data = null)
{
    return json([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * 成功响应
 * @param mixed $data 数据
 * @param string $message 消息
 * @return \think\response\Json
 */
function success($data = null, $message = 'success')
{
    return json_response(200, $message, $data);
}

/**
 * 错误响应
 * @param string $message 错误消息
 * @param int $code 错误码
 * @return \think\response\Json
 */
function error($message = 'error', $code = 400)
{
    return json_response($code, $message, null);
}

/**
 * 分页响应
 * @param array $list 列表数据
 * @param int $total 总数
 * @param int $page 当前页
 * @param int $pageSize 每页数量
 * @return \think\response\Json
 */
function paginate_response($list, $total, $page = 1, $pageSize = 10)
{
    return success([
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
        'hasMore' => ($page * $pageSize) < $total
    ]);
}

if (!function_exists('requestCurl')) {
    /**
     * @param string $url 请求的链接
     * @param array $params 请求附带的参数
     * @param string $method 请求的方式, 支持GET, POST, PUT, DELETE等
     * @param array $header 头部
     * @param string $type 数据类型，支持dataBuild、json等
     * @return bool|string
     */
    function requestCurl($url, $params = [], $method = 'GET', $header = [], $type = 'dataBuild')
    {
        $str = '';
        if (!empty($url)) {
            try {
                $ch = curl_init();

                // 处理GET请求的参数
                if (strtoupper($method) == 'GET' && !empty($params)) {
                    $url = $url . '?' . dataBuild($params);
                }

                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

                // 处理不同的请求方法
                if (strtoupper($method) != 'GET') {
                    // 设置请求方法
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

                    // 处理参数格式
                    if ($type == 'dataBuild') {
                        $params = dataBuild($params);
                    } elseif ($type == 'json') {
                        $params = json_encode($params);
                    } else {
                        $params = dataBuild($params);
                    }

                    // 设置请求体
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                }

                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //是否验证对等证书,1则验证，0则不验证
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $str = curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                $str = '';
            }
        }
        return $str;
    }
}


if (!function_exists('dataBuild')) {
    function dataBuild($array)
    {
        if (!is_array($array)) {
            return $array;
        }
        
        // 处理嵌套数组
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = json_encode($value);
            }
        }
        
        return http_build_query($array);
    }
}


if (!function_exists('setHeader')) {
    /**
     * 设置头部
     *
     * @param array $headerData 头部数组
     * @param string $authorization
     * @param string $type 类型 默认json (json,plain)
     * @return array
     */
    function setHeader($headerData = [], $authorization = '', $type = '')
    {
        $header = $headerData;

        switch ($type) {
            case 'json':
                $header[] = 'Content-Type:application/json';
                break;
            case 'html' :
                $header[] = 'Content-Type:text/html';
                break;
            case 'plain' :
                $header[] = 'Content-Type:text/plain';
                break;
            default:
                $header[] = 'Content-Type:application/json';
        }
//        $header[] = $type == 'plain' ? 'Content-Type:text/plain' : 'Content-Type: application/json';
        if ($authorization !== "") $header[] = 'Authorization:Bearer ' . $authorization;
        return $header;
    }
}


