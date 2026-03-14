<?php

namespace app\api\response;

/**
 * Standardized API Response Helper
 * Provides consistent JSON response format for all API endpoints
 */
class ApiResponse
{
    /**
     * Success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $httpCode HTTP status code
     * @return \think\Response
     */
    public static function success($data = null, string $message = 'Success', int $httpCode = 200)
    {
        $response = [
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];

        return json($response, $httpCode);
    }

    /**
     * Error response
     *
     * @param string $message Error message
     * @param int $code Error code (default -1)
     * @param mixed $errors Detailed error information
     * @param int $httpCode HTTP status code
     * @return \think\Response
     */
    public static function error(string $message, int $code = -1, $errors = null, int $httpCode = 400)
    {
        $response = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time()
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return json($response, $httpCode);
    }

    /**
     * Paginated response
     *
     * @param array $items Data items
     * @param int $total Total count
     * @param int $page Current page
     * @param int $limit Items per page
     * @param string $message Success message
     * @return \think\Response
     */
    public static function paginate(array $items, int $total, int $page, int $limit, string $message = 'Success')
    {
        $response = [
            'code' => 0,
            'message' => $message,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => $limit > 0 ? (int)ceil($total / $limit) : 0
                ]
            ],
            'timestamp' => time()
        ];

        return json($response, 200);
    }

    /**
     * 201 Created response
     *
     * @param mixed $data Created resource data
     * @param string $message Success message
     * @return \think\Response
     */
    public static function created($data = null, string $message = 'Resource created successfully')
    {
        return self::success($data, $message, 201);
    }

    /**
     * 204 No Content response
     *
     * @return \think\Response
     */
    public static function noContent()
    {
        return json(null, 204);
    }

    /**
     * 401 Unauthorized response
     *
     * @param string $message Error message
     * @return \think\Response
     */
    public static function unauthorized(string $message = 'Unauthorized')
    {
        return self::error($message, -1, null, 401);
    }

    /**
     * 403 Forbidden response
     *
     * @param string $message Error message
     * @return \think\Response
     */
    public static function forbidden(string $message = 'Forbidden')
    {
        return self::error($message, -1, null, 403);
    }

    /**
     * 404 Not Found response
     *
     * @param string $message Error message
     * @return \think\Response
     */
    public static function notFound(string $message = 'Resource not found')
    {
        return self::error($message, -1, null, 404);
    }

    /**
     * 422 Validation Error response
     *
     * @param string $message Error message
     * @param mixed $errors Validation errors
     * @return \think\Response
     */
    public static function validationError(string $message = 'Validation failed', $errors = null)
    {
        return self::error($message, -1, $errors, 422);
    }

    /**
     * 500 Internal Server Error response
     *
     * @param string $message Error message
     * @return \think\Response
     */
    public static function serverError(string $message = 'Internal server error')
    {
        return self::error($message, -1, null, 500);
    }
}
