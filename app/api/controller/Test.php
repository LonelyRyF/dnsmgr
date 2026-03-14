<?php

namespace app\api\controller;

use app\api\response\ApiResponse;

/**
 * API Test Controller
 * Simple test endpoint to verify API routing and JWT setup
 */
class Test extends BaseController
{
    /**
     * Test endpoint - no authentication required
     */
    public function ping()
    {
        return ApiResponse::success([
            'message' => 'pong',
            'timestamp' => time(),
            'server_time' => date('Y-m-d H:i:s'),
        ], 'API is working');
    }

    /**
     * Test JWT token generation
     */
    public function testToken()
    {
        try {
            // Generate a test token
            $token = generateJwtToken(1, 'user');
            $refreshToken = generateRefreshToken(1, 'user');

            return ApiResponse::success([
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl'),
            ], 'Test tokens generated successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Token generation failed: ' . $e->getMessage(), -1, null, 500);
        }
    }

    /**
     * Test protected endpoint - requires JWT authentication
     */
    public function protected()
    {
        $user = $this->user();

        return ApiResponse::success([
            'message' => 'You are authenticated!',
            'user' => $user,
        ], 'Protected endpoint accessed successfully');
    }
}
