<?php

declare(strict_types=1);

namespace app\utils;

use think\Response;

/**
 * API 响应辅助类
 * 提供标准化的 RESTful API 响应格式
 */
class ApiResponseHelper
{
    /**
     * 成功响应
     *
     * @param mixed|null $data 响应数据
     * @param string $message 提示信息
     * @param int $code HTTP 状态码
     * @return Response
     */
    public static function success(mixed $data = null, string $message = '操作成功', int $code = 200): Response
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];

        return json($response)->code($code);
    }

    /**
     * 失败响应
     *
     * @param string $message 错误信息
     * @param mixed|null $errors 详细错误信息（可选）
     * @param int $code HTTP 状态码
     * @return Response
     */
    public static function error(string $message = '操作失败', mixed $errors = null, int $code = 400): Response
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => time()
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return json($response)->code($code);
    }

    /**
     * 分页响应
     *
     * @param array $items 数据列表
     * @param int $total 总记录数
     * @param int $page 当前页码
     * @param int $pageSize 每页数量
     * @param string $message 提示信息
     * @return Response
     */
    public static function paginate(array $items, int $total, int $page, int $pageSize, string $message = '获取成功'): Response
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'totalPages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 0
                ]
            ],
            'timestamp' => time()
        ];

        return json($response)->code(200);
    }

    /**
     * 创建成功响应（201 Created）
     *
     * @param mixed|null $data 创建的资源数据
     * @param string $message 提示信息
     * @return Response
     */
    public static function created(mixed $data = null, string $message = '创建成功'): Response
    {
        return self::success($data, $message, 201);
    }

    /**
     * 无内容响应（204 No Content）
     * 通常用于删除操作
     *
     * @return Response
     */
    public static function noContent(): Response
    {
        return json(null)->code(204);
    }

    /**
     * 未认证响应（401 Unauthorized）
     *
     * @param string $message 错误信息
     * @return Response
     */
    public static function unauthorized(string $message = '未认证或认证已过期'): Response
    {
        return self::error($message, null, 401);
    }

    /**
     * 无权限响应（403 Forbidden）
     *
     * @param string $message 错误信息
     * @return Response
     */
    public static function forbidden(string $message = '无权限访问'): Response
    {
        return self::error($message, null, 403);
    }

    /**
     * 资源不存在响应（404 Not Found）
     *
     * @param string $message 错误信息
     * @return Response
     */
    public static function notFound(string $message = '资源不存在'): Response
    {
        return self::error($message, null, 404);
    }

    /**
     * 验证失败响应（422 Unprocessable Entity）
     *
     * @param string $message 错误信息
     * @param mixed $errors 验证错误详情
     * @return Response
     */
    public static function validationError(string $message = '请求参数验证失败', $errors = null): Response
    {
        return self::error($message, $errors, 422);
    }

    /**
     * 服务器错误响应（500 Internal Server Error）
     *
     * @param string $message 错误信息
     * @return Response
     */
    public static function serverError(string $message = '服务器内部错误'): Response
    {
        return self::error($message, null, 500);
    }
}
