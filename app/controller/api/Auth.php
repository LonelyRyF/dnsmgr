<?php

declare(strict_types=1);

namespace app\controller\api;

use app\utils\JwtHelper;
use think\facade\Db;
use Exception;

/**
 * 认证控制器
 * 处理登录、登出、Token 刷新等认证相关操作
 */
class Auth extends BaseController
{
    /**
     * 登录（返回 JWT Token）
     * POST /api/v1/auth/login
     */
    public function login()
    {
        $username = $this->request->post('username', '', 'trim');
        $password = $this->request->post('password', '', 'trim');

        if (empty($username) || empty($password)) {
            return $this->validationError('用户名或密码不能为空');
        }

        // 查询用户
        $user = Db::name('user')->where('username', $username)->find();
        if (!$user || !password_verify($password, $user['password'])) {
            // 记录登录失败日志
            if ($user) {
                Db::name('log')->insert([
                    'uid' => $user['id'],
                    'action' => '登录失败',
                    'data' => 'IP:' . $this->clientip . ' (API)',
                    'addtime' => date("Y-m-d H:i:s")
                ]);
            }
            return $this->error('用户名或密码错误', null, 401);
        }

        // 检查用户状态
        if ($user['status'] != 1) {
            return $this->forbidden('该用户已被封禁');
        }

        // 生成 JWT Token
        try {
            $tokens = JwtHelper::generateToken(
                (int)$user['id'],
                $user['username'],
                (int)$user['level']
            );

            // 记录登录成功日志
            Db::name('log')->insert([
                'uid' => $user['id'],
                'action' => '登录后台',
                'data' => 'IP:' . $this->clientip . ' (API)',
                'addtime' => date("Y-m-d H:i:s")
            ]);

            // 更新最后登录时间
            Db::name('user')->where('id', $user['id'])->update([
                'lasttime' => date("Y-m-d H:i:s")
            ]);

            return $this->success([
                'token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'level' => $user['level']
                ]
            ], '登录成功');
        } catch (Exception $e) {
            return $this->serverError('Token 生成失败');
        }
    }

    /**
     * 刷新 Token
     * POST /api/v1/auth/refresh
     */
    public function refresh()
    {
        $refreshToken = $this->request->post('refresh_token', '', 'trim');

        if (empty($refreshToken)) {
            return $this->validationError('refresh_token 不能为空');
        }

        try {
            $tokens = JwtHelper::refreshToken($refreshToken);
            if (!$tokens) {
                return $this->unauthorized('Refresh Token 无效或已过期');
            }

            return $this->success($tokens, 'Token 刷新成功');
        } catch (Exception $e) {
            return $this->serverError('Token 刷新失败');
        }
    }

    /**
     * 登出
     * POST /api/v1/auth/logout
     */
    public function logout()
    {
        // JWT 是无状态的，登出只需客户端删除 Token
        // 这里可以记录登出日志
        $user = $this->request->user;
        if ($user) {
            Db::name('log')->insert([
                'uid' => $user['id'],
                'action' => '退出登录',
                'data' => 'IP:' . $this->clientip . ' (API)',
                'addtime' => date("Y-m-d H:i:s")
            ]);
        }

        return $this->success(null, '登出成功');
    }

    /**
     * 获取当前用户信息
     * GET /api/v1/auth/profile
     */
    public function profile()
    {
        $user = $this->request->user;
        if (!$user) {
            return $this->unauthorized();
        }

        // 移除敏感信息
        unset($user['password']);
        unset($user['apikey']);
        unset($user['totp_secret']);

        return $this->success($user, '获取成功');
    }
}
