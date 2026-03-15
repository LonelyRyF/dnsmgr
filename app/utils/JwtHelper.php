<?php

declare(strict_types=1);

namespace app\utils;

use Exception;

/**
 * JWT 工具类
 * 负责 JWT Token 的生成、验证、刷新和解析
 */
class JwtHelper
{
    /**
     * 生成 JWT Token（Access Token + Refresh Token）
     *
     * @param int $uid 用户ID
     * @param string $username 用户名
     * @param int $level 用户等级（0=普通用户, 2=管理员）
     * @return array ['access_token' => string, 'refresh_token' => string, 'expires_in' => int]
     */
    public static function generateToken(int $uid, string $username, int $level): array
    {
        // 统一配置读取：优先 JWT_SECRET，fallback 到 sys_key
        $secret = env('JWT_SECRET');
        if (empty($secret)) {
            $secret = config_get('sys_key');
        }
        if (empty($secret)) {
            throw new Exception('JWT_SECRET 未配置且 sys_key 不可用');
        }

        $issuer = env('JWT_ISSUER', 'dnsmgr');
        $accessTtl = (int)env('JWT_TTL', 7200); // 默认2小时
        $refreshTtl = (int)env('JWT_REFRESH_TTL', 604800); // 默认7天

        $now = time();

        // 生成 Access Token
        $accessPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $accessTtl,
            'uid' => $uid,
            'username' => $username,
            'level' => $level,
            'type' => 'access'
        ];
        $accessToken = self::encode($accessPayload, $secret);

        // 生成 Refresh Token
        $refreshPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $refreshTtl,
            'uid' => $uid,
            'type' => 'refresh'
        ];
        $refreshToken = self::encode($refreshPayload, $secret);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessTtl,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * 验证 Token 有效性
     *
     * @param string $token JWT Token
     * @return array|false 成功返回 payload，失败返回 false
     */
    public static function verifyToken(string $token): array|false
    {
        try {
            // 统一配置读取：优先 JWT_SECRET，fallback 到 sys_key
            $secret = env('JWT_SECRET');
            if (empty($secret)) {
                $secret = config_get('sys_key');
            }
            if (empty($secret)) {
                return false;
            }

            $payload = self::decode($token, $secret);
            if (!$payload) {
                return false;
            }

            // 验证过期时间
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }

            // 验证签发者
            $issuer = env('JWT.JWT_ISSUER', 'dnsmgr');
            if (isset($payload['iss']) && $payload['iss'] !== $issuer) {
                return false;
            }

            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 刷新 Token（使用 Refresh Token 生成新的 Access Token）
     *
     * @param string $refreshToken Refresh Token
     * @return array|false 成功返回新的 token 数组，失败返回 false
     */
    public static function refreshToken(string $refreshToken): array|false
    {
        $payload = self::verifyToken($refreshToken);
        if (!$payload) {
            return false;
        }

        // 验证是否为 Refresh Token
        if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
            return false;
        }

        // 从数据库获取用户信息（确保用户仍然有效）
        $user = \think\facade\Db::name('user')->where('id', $payload['uid'])->find();
        if (!$user || $user['status'] != 1) {
            return false;
        }

        // 生成新的 Token
        return self::generateToken((int)$user['id'], $user['username'], (int)$user['level']);
    }

    /**
     * 编码 JWT Token
     *
     * @param array $payload 载荷数据
     * @param string $secret 密钥
     * @return string JWT Token
     */
    private static function encode(array $payload, string $secret): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * 解码 JWT Token
     *
     * @param string $token JWT Token
     * @param string $secret 密钥
     * @return array|false 成功返回 payload，失败返回 false
     */
    private static function decode(string $token, string $secret): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // 验证签名
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $signatureCheck = self::base64UrlEncode($signature);

        if ($signatureCheck !== $signatureEncoded) {
            return false;
        }

        // 解码 payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        return $payload ?: false;
    }

    /**
     * Base64 URL 编码
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL 解码
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
