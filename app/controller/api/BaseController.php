<?php

declare(strict_types=1);

namespace app\controller\api;

use app\BaseController as AppBaseController;
use app\utils\ApiResponseHelper;

/**
 * API 基础控制器
 * 所有 API 控制器继承此类
 */
class BaseController extends AppBaseController
{
    /**
     * 成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 提示信息
     * @param int $code HTTP 状态码
     * @return \think\Response
     */
    protected function success(mixed $data = null, string $message = '操作成功', int $code = 200): \think\Response
    {
        return ApiResponseHelper::success($data, $message, $code);
    }

    /**
     * 失败响应
     *
     * @param string $message 错误信息
     * @param mixed $errors 详细错误信息
     * @param int $code HTTP 状态码
     * @return \think\Response
     */
    protected function error(string $message = '操作失败', mixed $errors = null, int $code = 400): \think\Response
    {
        return ApiResponseHelper::error($message, $errors, $code);
    }

    /**
     * 分页响应
     *
     * @param array $items 数据列表
     * @param int $total 总记录数
     * @param int $page 当前页码
     * @param int $pageSize 每页数量
     * @param string $message 提示信息
     * @return \think\Response
     */
    protected function paginate(array $items, int $total, int $page, int $pageSize, string $message = '获取成功'): \think\Response
    {
        return ApiResponseHelper::paginate($items, $total, $page, $pageSize, $message);
    }

    /**
     * 创建成功响应（201 Created）
     *
     * @param mixed $data 创建的资源数据
     * @param string $message 提示信息
     * @return \think\Response
     */
    protected function created(mixed $data = null, string $message = '创建成功'): \think\Response
    {
        return ApiResponseHelper::created($data, $message);
    }

    /**
     * 无内容响应（204 No Content）
     *
     * @return \think\Response
     */
    protected function noContent(): \think\Response
    {
        return ApiResponseHelper::noContent();
    }

    /**
     * 未认证响应（401 Unauthorized）
     *
     * @param string $message 错误信息
     * @return \think\Response
     */
    protected function unauthorized(string $message = '未认证或认证已过期'): \think\Response
    {
        return ApiResponseHelper::unauthorized($message);
    }

    /**
     * 无权限响应（403 Forbidden）
     *
     * @param string $message 错误信息
     * @return \think\Response
     */
    protected function forbidden(string $message = '无权限访问'): \think\Response
    {
        return ApiResponseHelper::forbidden($message);
    }

    /**
     * 资源不存在响应（404 Not Found）
     *
     * @param string $message 错误信息
     * @return \think\Response
     */
    protected function notFound(string $message = '资源不存在'): \think\Response
    {
        return ApiResponseHelper::notFound($message);
    }

    /**
     * 验证失败响应（422 Unprocessable Entity）
     *
     * @param string $message 错误信息
     * @param mixed $errors 验证错误详情
     * @return \think\Response
     */
    protected function validationError(string $message = '请求参数验证失败', mixed $errors = null): \think\Response
    {
        return ApiResponseHelper::validationError($message, $errors);
    }

    /**
     * 服务器错误响应（500 Internal Server Error）
     *
     * @param string $message 错误信息
     * @return \think\Response
     */
    protected function serverError(string $message = '服务器内部错误'): \think\Response
    {
        return ApiResponseHelper::serverError($message);
    }
}
