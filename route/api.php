<?php

use app\api\middleware\JwtAuth;
use think\facade\Route;

/**
 * API Routes (RESTful)
 * All routes use JWT authentication via JwtAuth middleware
 * Base path: /api/v1
 */

// API v1 routes
Route::group('api/v1', function () {

    // Test routes (no auth required)
    Route::get('test/ping', '\\app\\api\\controller\\Test@ping');
    Route::get('test/token', '\\app\\api\\controller\\Test@testToken');

    // Authentication routes (no auth required)
    Route::post('auth/login', '\\app\\api\\controller\\Auth@login');
    Route::post('auth/refresh', '\\app\\api\\controller\\Auth@refresh');

    // Protected routes (require JWT authentication)
    Route::group(function () {

        // Test protected endpoint
        Route::get('test/protected', '\\app\\api\\controller\\Test@protected');

        // Auth management
        Route::post('auth/logout', '\\app\\api\\controller\\Auth@logout');
        Route::get('auth/profile', '\\app\\api\\controller\\Auth@profile');
        Route::put('auth/profile', '\\app\\api\\controller\\Auth@updateProfile');
        Route::put('auth/password', '\\app\\api\\controller\\Auth@changePassword');

        // Domain management
        Route::get('domains', '\\app\\api\\controller\\Domain@index');
        Route::post('domains', '\\app\\api\\controller\\Domain@create');
        Route::get('domains/:id', '\\app\\api\\controller\\Domain@read');
        Route::put('domains/:id', '\\app\\api\\controller\\Domain@update');
        Route::delete('domains/:id', '\\app\\api\\controller\\Domain@delete');
        Route::post('domains/batch', '\\app\\api\\controller\\Domain@batchCreate');
        Route::delete('domains/batch', '\\app\\api\\controller\\Domain@batchDelete');

        // DNS Record management
        Route::get('domains/:domain_id/records', '\\app\\api\\controller\\Record@index');
        Route::post('domains/:domain_id/records', '\\app\\api\\controller\\Record@create');
        Route::get('records/:id', '\\app\\api\\controller\\Record@read');
        Route::put('records/:id', '\\app\\api\\controller\\Record@update');
        Route::delete('records/:id', '\\app\\api\\controller\\Record@delete');
        Route::post('records/batch', '\\app\\api\\controller\\Record@batchCreate');
        Route::put('records/batch', '\\app\\api\\controller\\Record@batchUpdate');
        Route::delete('records/batch', '\\app\\api\\controller\\Record@batchDelete');
        Route::put('records/:id/status', '\\app\\api\\controller\\Record@toggleStatus');

        // Account management (DNS provider accounts)
        Route::get('accounts', '\\app\\api\\controller\\Account@index');
        Route::post('accounts', '\\app\\api\\controller\\Account@create');
        Route::get('accounts/:id', '\\app\\api\\controller\\Account@read');
        Route::put('accounts/:id', '\\app\\api\\controller\\Account@update');
        Route::delete('accounts/:id', '\\app\\api\\controller\\Account@delete');

        // Certificate management
        Route::get('certificates', '\\app\\api\\controller\\Certificate@index');
        Route::post('certificates', '\\app\\api\\controller\\Certificate@create');
        Route::get('certificates/:id', '\\app\\api\\controller\\Certificate@read');
        Route::put('certificates/:id', '\\app\\api\\controller\\Certificate@update');
        Route::delete('certificates/:id', '\\app\\api\\controller\\Certificate@delete');
        Route::post('certificates/:id/renew', '\\app\\api\\controller\\Certificate@renew');
        Route::post('certificates/:id/deploy', '\\app\\api\\controller\\Certificate@deploy');
        Route::get('certificates/:id/download', '\\app\\api\\controller\\Certificate@download');

        // User management (admin only)
        Route::get('users', '\\app\\api\\controller\\User@index');
        Route::post('users', '\\app\\api\\controller\\User@create');
        Route::get('users/:id', '\\app\\api\\controller\\User@read');
        Route::put('users/:id', '\\app\\api\\controller\\User@update');
        Route::delete('users/:id', '\\app\\api\\controller\\User@delete');

        // System settings (admin only)
        Route::get('settings', '\\app\\api\\controller\\System@getSettings');
        Route::put('settings', '\\app\\api\\controller\\System@updateSettings');
        Route::get('settings/:key', '\\app\\api\\controller\\System@getSetting');
        Route::put('settings/:key', '\\app\\api\\controller\\System@updateSetting');

        // Monitoring
        Route::get('monitor/domains', '\\app\\api\\controller\\Monitor@domains');
        Route::get('monitor/certificates', '\\app\\api\\controller\\Monitor@certificates');

        // Logs
        Route::get('logs', '\\app\\api\\controller\\Log@index');
        Route::get('logs/:id', '\\app\\api\\controller\\Log@read');

    })->middleware(JwtAuth::class);

})->allowCrossDomain([
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept',
]);
