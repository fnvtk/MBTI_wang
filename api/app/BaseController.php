<?php
namespace app;

use think\App;
use think\exception\ValidateException;
use think\Validate;
use app\common\service\JwtService;

/**
 * 控制器基础类
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
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

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
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    /**
     * 从请求中解析当前登录用户（兼容中间件注入和 JWT 直接解析两种方式）
     */
    protected function resolveUser(): ?array
    {
        $user = $this->request->user ?? null;
        if ($user) {
            return is_array($user) ? $user : (array) $user;
        }

        $token = JwtService::getTokenFromRequest($this->request);
        if (!$token) {
            return null;
        }

        $payload = JwtService::verifyToken($token);
        if (!$payload) {
            return null;
        }

        return [
            'source'  => $payload['source'] ?? '',
            'user_id' => $payload['user_id'] ?? $payload['userId'] ?? null,
            'userId'  => $payload['user_id'] ?? $payload['userId'] ?? null,
        ];
    }

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
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}


