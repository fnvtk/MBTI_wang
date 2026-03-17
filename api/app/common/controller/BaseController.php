<?php
namespace app\common\controller;

use think\App;
use think\exception\ValidateException;
use think\Validate;
use think\facade\Request;
use think\Response;

/**
 * 公共基础控制器
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 成功响应
     * @param mixed $data 数据
     * @param string $message 消息
     * @return \think\response\Json
     */
    protected function success($data = null, $message = 'success')
    {
        $response = Response::create([
            'code' => 200,
            'message' => $message,
            'data' => $data
        ], 'json')->code(200);
        
        $response->header([
            'Content-Type' => 'application/json; charset=utf-8'
        ]);
        
        return $response;
    }

    /**
     * 错误响应
     * @param string $message 错误消息
     * @param int $code 错误码
     * @return \think\response\Json
     */
    protected function error($message = 'error', $code = 400)
    {
        $response = Response::create([
            'code' => $code,
            'message' => $message,
            'data' => null
        ], 'json')->code($code);
        
        $response->header([
            'Content-Type' => 'application/json; charset=utf-8'
        ]);
        
        return $response;
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}

