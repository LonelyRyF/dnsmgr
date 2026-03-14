<?php

namespace app\api\middleware;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use app\api\response\ApiResponse;
use think\facade\Db;

/**
 * JWT Authentication Middleware
 * Validates JWT tokens and sets user context for API requests
 */
class JwtAuth
{
    /**
     * Handle request
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Extract token from Authorization header
        $token = $this->getTokenFromHeader($request);

        if (!$token) {
            return ApiResponse::unauthorized('Missing or invalid authorization token');
        }

        try {
            // Decode and verify JWT token
            $payload = $this->decodeToken($token);

            // Load user from database
            $user = $this->loadUser($payload);

            if (!$user) {
                return ApiResponse::unauthorized('User not found or inactive');
            }

            // Set request properties for compatibility with existing permission checks
            $request->islogin = true;
            $request->isApi = true;
            $request->user = $user;

            return $next($request);

        } catch (ExpiredException $e) {
            return ApiResponse::unauthorized('Token has expired');
        } catch (SignatureInvalidException $e) {
            return ApiResponse::unauthorized('Invalid token signature');
        } catch (Exception $e) {
            return ApiResponse::unauthorized('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Extract token from Authorization header
     *
     * @param \think\Request $request
     * @return string|null
     */
    private function getTokenFromHeader($request): ?string
    {
        $header = $request->header(config('jwt.header', 'Authorization'));

        if (!$header) {
            return null;
        }

        $prefix = config('jwt.prefix', 'Bearer');

        // Check if header starts with "Bearer "
        if (stripos($header, $prefix) === 0) {
            return trim(substr($header, strlen($prefix)));
        }

        return null;
    }

    /**
     * Decode and verify JWT token
     *
     * @param string $token
     * @return object
     * @throws Exception
     */
    private function decodeToken(string $token): object
    {
        $secret = config('jwt.secret');
        $algo = config('jwt.algo', 'HS256');

        if (empty($secret)) {
            throw new Exception('JWT secret not configured');
        }

        // Set leeway for clock skew
        JWT::$leeway = config('jwt.leeway', 60);

        return JWT::decode($token, new Key($secret, $algo));
    }

    /**
     * Load user from database based on token payload
     *
     * @param object $payload
     * @return array|null
     */
    private function loadUser(object $payload): ?array
    {
        if (!isset($payload->uid) || !isset($payload->type)) {
            return null;
        }

        $uid = $payload->uid;
        $type = $payload->type;

        // Load user based on type (same logic as cookie-based auth)
        if ($type === 'user') {
            $user = Db::name('user')->where('id', $uid)->find();
            if (!$user || (isset($user['status']) && $user['status'] == 0)) {
                return null;
            }
            // Load permissions for non-admin users
            if ($user['level'] == 1) {
                $user['permission'] = Db::name('permission')->where('uid', $uid)->column('domain');
            }
            return $user;
        } elseif ($type === 'account') {
            $account = Db::name('account')->where('id', $uid)->find();
            if (!$account || (isset($account['active']) && $account['active'] == 0)) {
                return null;
            }
            return $account;
        }

        return null;
    }
}
