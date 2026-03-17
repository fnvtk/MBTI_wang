<?php
namespace app\middleware;

/**
 * 跨域中间件
 */
class Cors
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // 从配置文件获取跨域配置
        $config = config('cors');
        
        $allowOrigin = $config['allow_origin'] ?? '*';
        $allowMethods = $config['allow_methods'] ?? 'GET,POST,PUT,DELETE,OPTIONS';
        $allowHeaders = $config['allow_headers'] ?? 'Content-Type,Authorization,X-Requested-With,Accept';
        $allowCredentials = $config['allow_credentials'] ?? false;
        $maxAge = $config['max_age'] ?? 86400;
        
        // 获取请求的Origin
        $origin = $request->header('Origin', '');
        
        // 确定允许的Origin
        $allowedOrigin = null;
        if ($allowOrigin === '*') {
            $allowedOrigin = '*';
        } else {
            // 支持多个域名（用逗号分隔）
            $origins = array_map('trim', explode(',', $allowOrigin));
            
            // 如果请求的Origin在允许列表中，则使用该Origin
            // 同时支持带/不带尾部斜杠的匹配
            foreach ($origins as $allowed) {
                if ($origin === $allowed || $origin === rtrim($allowed, '/') || rtrim($origin, '/') === $allowed) {
                    $allowedOrigin = $origin;
                    break;
                }
            }
        }
        
        // 处理预检请求（OPTIONS）
        if ($request->method(true) === 'OPTIONS') {
            $response = response('', 200);
        } else {
            $response = $next($request);
        }
        
        // 设置CORS响应头
        if ($allowedOrigin !== null) {
            $headers = [
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Headers' => $allowHeaders,
                'Access-Control-Max-Age' => (string)$maxAge,
            ];
            
            if ($allowCredentials) {
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }
            
            // 使用header方法设置响应头（ThinkPHP 8 需要传递数组）
            $response->header($headers);
        }

        return $response;
    }
}
