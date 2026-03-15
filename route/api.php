<?php

use app\api\middleware\ApiAuth;
use app\api\middleware\ApiKeyAuth;
use think\facade\Route;

/**
 * RESTful API 路由配置
 * 所有路由前缀为 /api/v1
 *
 * 认证方式：
 * 1. JWT Token: Authorization: Bearer <token>
 * 2. API Key: X-API-Key: <key>
 */

// ==================== 公开路由（无需认证） ====================
Route::group('api/v1', function () {
    // 登录（返回 JWT Token）
    Route::post('auth/login', 'api.Auth/login');
    // 刷新 Token
    Route::post('auth/refresh', 'api.Auth/refresh');
})->allowCrossDomain();

// ==================== 定时任务路由（仅支持 API Key） ====================
Route::group('api/v1', function () {
    // 执行定时任务
    Route::get('system/cron', 'api.System/executeCron');
})->middleware(ApiKeyAuth::class)->allowCrossDomain();

// ==================== 受保护路由（支持 JWT 或 API Key） ====================
Route::group('api/v1', function () {

    // ==================== 认证相关 ====================
    Route::group('auth', function () {
        // 登出
        Route::post('logout', 'api.Auth/logout');
        // 获取当前用户信息
        Route::get('profile', 'api.Auth/profile');
    });

    // ==================== 域名账户管理 ====================
    Route::group('accounts', function () {
        // 获取账户列表
        Route::get('list', 'api.Account/accountList');
        // 创建账户
        Route::post('create', 'api.Account/accountCreate');
        // 获取账户详情
        Route::get(':id/detail', 'api.Account/accountDetail');
        // 更新账户
        Route::post(':id/update', 'api.Account/accountUpdate');
        // 删除账户
        Route::post(':id/delete', 'api.Account/accountDelete');
    });

    // ==================== 域名管理 ====================
    Route::group('domains', function () {
        // 获取域名列表
        Route::get('list', 'api.Domain/domainList');
        // 添加域名
        Route::post('create', 'api.Domain/domainCreate');
        // 获取域名详情
        Route::get(':id/detail', 'api.Domain/domainDetail');
        // 更新域名
        Route::post(':id/update', 'api.Domain/domainUpdate');
        // 删除域名
        Route::post(':id/delete', 'api.Domain/domainDelete');
        // 同步域名记录
        Route::post(':id/sync', 'api.Domain/domainSync');
    });

    // ==================== 域名记录管理 ====================
    Route::group('records', function () {
        // 获取记录列表
        Route::get(':domain_id/list', 'api.Record/recordList');
        // 获取记录详情
        Route::get(':domain_id/:id/detail', 'api.Record/recordDetail');
        // 添加记录
        Route::post(':domain_id/create', 'api.Record/recordCreate');
        // 更新记录
        Route::post(':domain_id/:id/update', 'api.Record/recordUpdate');
        // 删除记录
        Route::post(':domain_id/:id/delete', 'api.Record/recordDelete');
        // 切换记录状态
        Route::post(':domain_id/:id/status', 'api.Record/recordToggleStatus');
        // 批量添加记录
        Route::post(':domain_id/batch-create', 'api.Record/recordBatchCreate');
        // 批量操作记录（启用/暂停/删除/修改备注）
        Route::post(':domain_id/batch-operation', 'api.Record/recordBatchOperation');
        // 批量修改记录（修改类型/值/线路/TTL）
        Route::post(':domain_id/batch-update', 'api.Record/recordBatchUpdate');
    });

    // ==================== 证书账户管理 ====================
    Route::group('cert-accounts', function () {
        // 获取证书账户列表
        Route::get('list', 'api.Certificate/certAccountList');
        // 创建证书账户
        Route::post('create', 'api.Certificate/certAccountCreate');
        // 更新证书账户
        Route::post(':id/update', 'api.Certificate/certAccountUpdate');
        // 删除证书账户
        Route::post(':id/delete', 'api.Certificate/certAccountDelete');
    });

    // ==================== 证书管理 ====================
    Route::group('certificates', function () {
        // 获取证书列表
        Route::get('list', 'api.Certificate/certificateList');
        // 申请/导入证书
        Route::post('create', 'api.Certificate/certificateCreate');
        // 获取证书详情
        Route::get(':id/detail', 'api.Certificate/certificateDetail');
        // 删除证书
        Route::post(':id/delete', 'api.Certificate/certificateDelete');
        // 部署证书
        Route::post(':id/deploy', 'api.Certificate/certificateDeploy');
        // 获取证书处理进度
        Route::get(':id/process', 'api.Certificate/certificateProcess');
    });

    // ==================== 部署账户管理 ====================
    Route::group('deploy-accounts', function () {
        // 获取部署账户列表
        Route::get('list', 'api.Certificate/deployAccountList');
        // 创建部署账户
        Route::post('create', 'api.Certificate/deployAccountCreate');
        // 更新部署账户
        Route::post(':id/update', 'api.Certificate/deployAccountUpdate');
        // 删除部署账户
        Route::post(':id/delete', 'api.Certificate/deployAccountDelete');
    });

    // ==================== 部署任务管理 ====================
    Route::group('deploy-tasks', function () {
        // 获取部署任务列表
        Route::get('list', 'api.Certificate/deployTaskList');
        // 创建部署任务
        Route::post('create', 'api.Certificate/deployTaskCreate');
        // 更新部署任务
        Route::post(':id/update', 'api.Certificate/deployTaskUpdate');
        // 删除部署任务
        Route::post(':id/delete', 'api.Certificate/deployTaskDelete');
        // 切换任务状态
        Route::post(':id/active', 'api.Certificate/deployTaskToggleActive');
        // 重置部署任务
        Route::post(':id/reset', 'api.Certificate/deployTaskReset');
        // 执行部署任务
        Route::post(':id/execute', 'api.Certificate/deployTaskExecute');
        // 获取部署任务进度
        Route::get(':id/process', 'api.Certificate/deployTaskProcess');
    });

    // ==================== CNAME代理管理 ====================
    Route::group('cert-cnames', function () {
        // 获取CNAME代理列表
        Route::get('list', 'api.Certificate/cnameList');
        // 创建CNAME代理
        Route::post('create', 'api.Certificate/cnameCreate');
        // 更新CNAME代理
        Route::post(':id/update', 'api.Certificate/cnameUpdate');
        // 删除CNAME代理
        Route::post(':id/delete', 'api.Certificate/cnameDelete');
        // 检查CNAME代理
        Route::post(':id/check', 'api.Certificate/cnameCheck');
    });

    // ==================== 用户管理 ====================
    Route::group('users', function () {
        // 获取用户列表
        Route::get('list', 'api.User/userList');
        // 获取用户详情
        Route::get(':id/detail', 'api.User/userDetail');
        // 创建用户
        Route::post('create', 'api.User/userCreate');
        // 更新用户
        Route::post(':id/update', 'api.User/userUpdate');
        // 删除用户
        Route::post(':id/delete', 'api.User/userDelete');
        // 切换用户状态
        Route::post(':id/status', 'api.User/userToggleStatus');
        // 修改密码
        Route::post('change-password', 'api.User/changePassword');
    });

    // ==================== 操作日志 ====================
    Route::group('logs', function () {
        // 获取操作日志列表
        Route::get('list', 'api.User/logList');
    });

    // ==================== 系统管理 ====================
    Route::group('system', function () {
        // 获取系统配置
        Route::get('config', 'api.System/getConfig');
        // 更新系统配置
        Route::post('config', 'api.System/updateConfig');
        // 测试邮件发送
        Route::post('test-mail', 'api.System/testMail');
        // 测试Telegram Bot
        Route::post('test-telegram', 'api.System/testTelegram');
        // 测试Webhook
        Route::post('test-webhook', 'api.System/testWebhook');
        // 测试代理服务器
        Route::post('test-proxy', 'api.System/testProxy');
        // 获取定时任务配置
        Route::get('cron-config', 'api.System/getCronConfig');
        // 清理缓存
        Route::post('clear-cache', 'api.System/clearCache');
        // 获取系统信息
        Route::get('info', 'api.System/getSystemInfo');
    });

    // ==================== 定时任务管理 ====================
    Route::group('schedule-tasks', function () {
        // 获取定时任务列表
        Route::get('list', 'api.Schedule/taskList');
        // 创建定时任务
        Route::post('create', 'api.Schedule/taskCreate');
        // 更新定时任务
        Route::post(':id/update', 'api.Schedule/taskUpdate');
        // 删除定时任务
        Route::post(':id/delete', 'api.Schedule/taskDelete');
        // 切换任务状态
        Route::post(':id/active', 'api.Schedule/taskToggleActive');
        // 批量操作定时任务
        Route::post('batch-operation', 'api.Schedule/taskBatchOperation');
    });

    // ==================== 域名监控管理 ====================
    Route::group('monitor', function () {
        // 获取监控概览
        Route::get('overview', 'api.Monitor/overview');
        // 获取监控状态
        Route::get('status', 'api.Monitor/status');
        // 清理监控日志
        Route::post('logs/clean', 'api.Monitor/cleanLogs');

        // 监控任务管理
        Route::group('tasks', function () {
            // 获取监控任务列表
            Route::get('list', 'api.Monitor/taskList');
            // 获取监控任务详情
            Route::get(':id/detail', 'api.Monitor/taskDetail');
            // 创建监控任务
            Route::post('create', 'api.Monitor/taskCreate');
            // 更新监控任务
            Route::post(':id/update', 'api.Monitor/taskUpdate');
            // 删除监控任务
            Route::post(':id/delete', 'api.Monitor/taskDelete');
            // 切换任务状态
            Route::post(':id/active', 'api.Monitor/taskToggleActive');
            // 批量操作监控任务
            Route::post('batch-operation', 'api.Monitor/taskBatchOperation');
            // 获取监控日志
            Route::get(':id/logs', 'api.Monitor/taskLogs');
        });
    });

})->middleware(ApiAuth::class)->allowCrossDomain();
