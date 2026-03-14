<?php

namespace app\api\controller;

use app\api\response\ApiResponse;
use think\exception\ValidateException;
use think\App;

/**
 * API Base Controller
 * Base class for all API controllers with helper methods
 */
class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var App
     */
    protected $app;

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
    }

    /**
     * Get authenticated user
     *
     * @return array|null
     */
    protected function user(): ?array
    {
        return $this->request->user ?? null;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return $this->request->islogin ?? false;
    }

    /**
     * Validate request data
     *
     * @param array $data Data to validate
     * @param string|array $validate Validate class name or rules
     * @param string|null $scene Validation scene
     * @return bool
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, ?string $scene = null): bool
    {
        if (is_array($validate)) {
            $v = new \think\Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.') !== false) {
                [$validate, $scene] = explode('.', $validate);
            }
            $class = str_contains($validate, '\\') ? $validate : 'app\\validate\\' . $validate;
            $v = new $class();
            if ($scene) {
                $v->scene($scene);
            }
        }

        if (!$v->check($data)) {
            throw new ValidateException($v->getError());
        }

        return true;
    }

    /**
     * Validate and return error response on failure
     *
     * @param array $data Data to validate
     * @param string|array $validate Validate class name or rules
     * @param string|null $scene Validation scene
     * @return \think\Response|null Returns error response on failure, null on success
     */
    protected function validateWithResponse(array $data, $validate, ?string $scene = null): ?\think\Response
    {
        try {
            $this->validate($data, $validate, $scene);
            return null;
        } catch (ValidateException $e) {
            return ApiResponse::validationError($e->getError());
        }
    }

    /**
     * Success response helper
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $httpCode HTTP status code
     * @return \think\Response
     */
    protected function success($data = null, string $message = 'Success', int $httpCode = 200): \think\Response
    {
        return ApiResponse::success($data, $message, $httpCode);
    }

    /**
     * Error response helper
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param mixed $errors Detailed errors
     * @param int $httpCode HTTP status code
     * @return \think\Response
     */
    protected function error(string $message, int $code = -1, $errors = null, int $httpCode = 400): \think\Response
    {
        return ApiResponse::error($message, $code, $errors, $httpCode);
    }

    /**
     * Paginated response helper
     *
     * @param array $items Data items
     * @param int $total Total count
     * @param int $page Current page
     * @param int $limit Items per page
     * @param string $message Success message
     * @return \think\Response
     */
    protected function paginate(array $items, int $total, int $page, int $limit, string $message = 'Success'): \think\Response
    {
        return ApiResponse::paginate($items, $total, $page, $limit, $message);
    }

    /**
     * Check user permission
     *
     * @param string $permission Permission name
     * @return bool
     */
    protected function checkPermission(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Admin has all permissions
        if (isset($user['is_admin']) && $user['is_admin'] == 1) {
            return true;
        }

        // Check specific permission
        return checkPermission($permission);
    }

    /**
     * Check domain permission
     *
     * @param int $domainId Domain ID
     * @return bool
     */
    protected function checkDomainPermission(int $domainId): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Admin has access to all domains
        if (isset($user['is_admin']) && $user['is_admin'] == 1) {
            return true;
        }

        // Check if user owns the domain
        $domain = \app\model\Domain::where('id', $domainId)->find();
        if (!$domain) {
            return false;
        }

        return $domain['uid'] == $user['id'];
    }
}
