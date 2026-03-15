<?php

declare(strict_types=1);

namespace app\api\middleware;

use think\facade\Db;
use app\utils\ApiResponseHelper;

/**
 * API Key 认证中间件
 *
 * 验证 X-API-Key 请求头中的 API Key
 * 从数据库读取用户配置，验证 API Key 是否有效
 */
class ApiKeyAuth
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
        // 从请求头获取 API Key
        $apiKey = $request->header('X-API-Key', '');

        // 如果没有提供 API Key
        if (empty($apiKey)) {
            return ApiResponseHelper::unauthorized('缺少 API Key');
        }

        try {
            // 从数据库查询用户
            $user = Db::name('user')
                ->where('apikey', $apiKey)
                ->where('is_api', 1)
                ->where('status', 1)
                ->find();

            // 验证 API Key 是否有效
            if (!$user) {
                return ApiResponseHelper::unauthorized('无效的 API Key');
            }

            // 将用户信息注入到请求对象
            $request->user = [
                'id' => $user['id'],
                'username' => $user['username'],
                'level' => $user['level'],
                'type' => 'apikey',
                'name' => $user['username']
            ];

            // 标记为已登录和 API 请求
            $request->islogin = true;
            $request->isApi = true;

            return $next($request);
        } catch (\Exception $e) {
            return ApiResponseHelper::serverError('API Key 验证失败：' . $e->getMessage());
        }
    }
}
