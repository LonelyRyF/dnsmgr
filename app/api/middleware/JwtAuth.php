<?php

declare(strict_types=1);

namespace app\api\middleware;

use app\utils\JwtHelper;
use app\utils\ApiResponseHelper;
use Closure;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Request;

/**
 * JWT 认证中间件
 * 负责验证 API 请求中的 JWT Token
 */
class JwtAuth
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // 从 Authorization 头提取 Token
        $authorization = $request->header('Authorization', '');

        if (empty($authorization)) {
            return ApiResponseHelper::unauthorized();
        }

        // 验证 Bearer Token 格式
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return ApiResponseHelper::unauthorized();
        }

        $token = $matches[1];

        // 验证 Token 有效性
        $payload = JwtHelper::verifyToken($token);
        if (!$payload) {
            return ApiResponseHelper::unauthorized();
        }

        // 验证 Token 类型（必须是 Access Token）
        if (!isset($payload['type']) || $payload['type'] !== 'access') {
            return ApiResponseHelper::unauthorized();
        }

        // 从数据库验证用户状态
        $user = Db::name('user')->where('id', $payload['uid'])->find();
        if (!$user) {
            return ApiResponseHelper::unauthorized('用户不存在');
        }

        if ($user['status'] != 1) {
            return ApiResponseHelper::forbidden('该用户已被封禁');
        }

        // 构建用户信息（与现有 AuthUser 中间件保持一致）
        $user['type'] = 'user';
        $user['permission'] = [];
        if ($user['level'] == 1) {
            $user['permission'] = Db::name('permission')->where('uid', $user['id'])->column('domain');
        }

        // 注入用户信息到 Request
        $request->islogin = true;
        $request->isApi = true;
        $request->user = $user;

        return $next($request);
    }
}
