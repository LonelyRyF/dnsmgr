<?php

declare(strict_types=1);

namespace app\api\middleware;

use app\utils\ApiResponseHelper;

/**
 * API 统一认证中间件
 *
 * 支持多种认证方式：
 * 1. JWT Token (Authorization: Bearer <token>)
 * 2. API Key (X-API-Key: <key>)
 *
 * 优先级：JWT > API Key
 */
class ApiAuth
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 优先检查 JWT Token
        $authHeader = $request->header('Authorization', '');
        if (!empty($authHeader) && stripos($authHeader, 'Bearer ') === 0) {
            return (new JwtAuth())->handle($request, $next);
        }

        // 其次检查 API Key
        $apiKey = $request->header('X-API-Key', '');
        if (!empty($apiKey)) {
            return (new ApiKeyAuth())->handle($request, $next);
        }

        // 未提供任何认证凭据
        return ApiResponseHelper::unauthorized('未提供有效的认证凭据，请使用 JWT Token 或 API Key');
    }
}
