<?php

namespace app\lib;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Exception;

class JWT
{
    private static function getSecret(): string
    {
        return env('jwt.JWT_SECRET', config_get('sys_key'));
    }

    private static function getIssuer(): string
    {
        return env('jwt.JWT_ISSUER', 'dnsmgr');
    }

    private static function getTTL(): int
    {
        return (int)env('jwt.JWT_TTL', 7200);
    }

    /**
     * 生成 JWT Token
     * @param array $payload 用户数据 ['id' => 1, 'username' => 'admin', 'level' => 2]
     * @return string
     */
    public static function encode(array $payload): string
    {
        $now = time();
        $data = [
            'iss' => self::getIssuer(),
            'iat' => $now,
            'exp' => $now + self::getTTL(),
            'data' => $payload
        ];

        return FirebaseJWT::encode($data, self::getSecret(), 'HS256');
    }

    /**
     * 验证并解码 JWT Token
     * @param string $token
     * @return array|null 返回 payload 数据，失败返回 null
     */
    public static function decode(string $token): ?array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::getSecret(), 'HS256'));
            return (array)$decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 从 Authorization Header 中提取 Token
     * @param string|null $header
     * @return string|null
     */
    public static function extractToken(?string $header): ?string
    {
        if (empty($header)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
