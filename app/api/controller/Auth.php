<?php

namespace app\api\controller;

use app\api\response\ApiResponse;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * API Authentication Controller
 * Handles login, token refresh, logout, and profile management
 */
class Auth extends BaseController
{
    /**
     * Login and generate JWT token
     *
     * @return \think\Response
     */
    public function login()
    {
        $username = $this->request->param('username');
        $password = $this->request->param('password');

        if (empty($username) || empty($password)) {
            return $this->error('Username and password are required', -1, null, 422);
        }

        try {
            // Check if user exists
            $user = Db::name('user')->where('username', $username)->find();

            if (!$user) {
                return $this->error('Invalid username or password', -1, null, 401);
            }

            // Verify password
            if ($user['password'] !== getMd5Pwd($password, $user['username'])) {
                return $this->error('Invalid username or password', -1, null, 401);
            }

            // Check if user is active
            if (isset($user['status']) && $user['status'] == 0) {
                return $this->error('Account is disabled', -1, null, 403);
            }

            // Generate tokens
            $accessToken = generateJwtToken($user['id'], 'user');
            $refreshToken = generateRefreshToken($user['id'], 'user');

            // Update last login time
            Db::name('user')->where('id', $user['id'])->update([
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => real_ip(),
            ]);

            return $this->success([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl'),
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'level' => $user['level'],
                    'is_admin' => $user['level'] == 2,
                ]
            ], 'Login successful');

        } catch (\Exception $e) {
            return $this->error('Login failed: ' . $e->getMessage(), -1, null, 500);
        }
    }

    /**
     * Refresh access token using refresh token
     *
     * @return \think\Response
     */
    public function refresh()
    {
        $refreshToken = $this->request->param('refresh_token');

        if (empty($refreshToken)) {
            return $this->error('Refresh token is required', -1, null, 422);
        }

        try {
            // Decode refresh token
            $config = config('jwt');
            \Firebase\JWT\JWT::$leeway = $config['leeway'];
            $payload = \Firebase\JWT\JWT::decode(
                $refreshToken,
                new \Firebase\JWT\Key($config['secret'], $config['algo'])
            );

            // Verify it's a refresh token
            if (!isset($payload->refresh) || $payload->refresh !== true) {
                return $this->error('Invalid refresh token', -1, null, 401);
            }

            // Verify user still exists and is active
            if ($payload->type === 'user') {
                $user = Db::name('user')->where('id', $payload->uid)->find();
                if (!$user || (isset($user['status']) && $user['status'] == 0)) {
                    return $this->error('User not found or inactive', -1, null, 401);
                }
            } elseif ($payload->type === 'account') {
                $account = Db::name('account')->where('id', $payload->uid)->find();
                if (!$account || (isset($account['active']) && $account['active'] == 0)) {
                    return $this->error('Account not found or inactive', -1, null, 401);
                }
            } else {
                return $this->error('Invalid token type', -1, null, 401);
            }

            // Generate new access token
            $accessToken = generateJwtToken($payload->uid, $payload->type);

            return $this->success([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl'),
            ], 'Token refreshed successfully');

        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->error('Refresh token has expired', -1, null, 401);
        } catch (\Exception $e) {
            return $this->error('Token refresh failed: ' . $e->getMessage(), -1, null, 401);
        }
    }

    /**
     * Logout (client-side token invalidation)
     *
     * @return \think\Response
     */
    public function logout()
    {
        // JWT is stateless, so logout is handled client-side by discarding the token
        // Optionally, implement token blacklist here if needed

        return $this->success(null, 'Logout successful');
    }

    /**
     * Get current user profile
     *
     * @return \think\Response
     */
    public function profile()
    {
        $user = $this->user();

        if (!$user) {
            return $this->error('User not found', -1, null, 404);
        }

        // Remove sensitive fields
        unset($user['password']);

        return $this->success($user, 'Profile retrieved successfully');
    }

    /**
     * Update current user profile
     *
     * @return \think\Response
     */
    public function updateProfile()
    {
        $user = $this->user();

        if (!$user) {
            return $this->error('User not found', -1, null, 404);
        }

        $email = $this->request->param('email');
        $phone = $this->request->param('phone');

        try {
            $updateData = [];

            if ($email !== null) {
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->error('Invalid email format', -1, null, 422);
                }

                // Check if email is already used by another user
                $existingUser = Db::name('user')
                    ->where('email', $email)
                    ->where('id', '<>', $user['id'])
                    ->find();

                if ($existingUser) {
                    return $this->error('Email is already in use', -1, null, 422);
                }

                $updateData['email'] = $email;
            }

            if ($phone !== null) {
                $updateData['phone'] = $phone;
            }

            if (!empty($updateData)) {
                Db::name('user')->where('id', $user['id'])->update($updateData);
            }

            return $this->success(null, 'Profile updated successfully');

        } catch (\Exception $e) {
            return $this->error('Profile update failed: ' . $e->getMessage(), -1, null, 500);
        }
    }

    /**
     * Change password
     *
     * @return \think\Response
     */
    public function changePassword()
    {
        $user = $this->user();

        if (!$user) {
            return $this->error('User not found', -1, null, 404);
        }

        $oldPassword = $this->request->param('old_password');
        $newPassword = $this->request->param('new_password');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->error('Old password and new password are required', -1, null, 422);
        }

        if (strlen($newPassword) < 6) {
            return $this->error('New password must be at least 6 characters', -1, null, 422);
        }

        try {
            $userRecord = Db::name('user')->where('id', $user['id'])->find();

            // Verify old password
            if ($userRecord['password'] !== getMd5Pwd($oldPassword, $userRecord['username'])) {
                return $this->error('Old password is incorrect', -1, null, 422);
            }

            // Update password
            Db::name('user')->where('id', $user['id'])->update([
                'password' => getMd5Pwd($newPassword, $userRecord['username'])
            ]);

            return $this->success(null, 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->error('Password change failed: ' . $e->getMessage(), -1, null, 500);
        }
    }
}
