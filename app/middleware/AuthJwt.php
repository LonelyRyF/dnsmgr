<?php

namespace app\middleware;

use app\lib\JWT;
use Closure;
use think\Request;
use think\Response;

class AuthJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        $token = JWT::extractToken($authHeader);

        if (!$token) {
            return json(['code' => -1, 'msg' => '未提供认证令牌'], 401);
        }

        $payload = JWT::decode($token);

        if (!$payload) {
            return json(['code' => -1, 'msg' => '认证令牌无效或已过期'], 401);
        }

        // 将用户信息注入到 request
        $request->user = $payload;
        $request->userId = $payload['id'] ?? 0;

        return $next($request);
    }
}
