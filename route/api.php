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
    Route::post('auth/login', 'api/auth/login');
    // 刷新 Token
    Route::post('auth/refresh', 'api/auth/refresh');
})->allowCrossDomain();

// ==================== 定时任务路由（仅支持 API Key） ====================
Route::group('api/v1', function () {
    // 执行定时任务
    Route::get('system/cron', 'api/system/executeCron');
})->middleware(ApiKeyAuth::class)->allowCrossDomain();

// ==================== 受保护路由（支持 JWT 或 API Key） ====================
Route::group('api/v1', function () {

    // ==================== 认证相关 ====================
    Route::group('auth', function () {
        // 登出
        Route::post('logout', 'api/auth/logout');
        // 获取当前用户信息
        Route::get('profile', 'api/auth/profile');
    });

    // ==================== 域名账户管理 ====================
    Route::group('accounts', function () {
        // 获取账户列表
        Route::get('list', 'api/account/accountList');
        // 创建账户
        Route::post('create', 'api/account/accountCreate');
        // 获取账户详情
        Route::get(':id/detail', 'api/account/accountDetail');
        // 更新账户
        Route::post(':id/update', 'api/account/accountUpdate');
        // 删除账户
        Route::post(':id/delete', 'api/account/accountDelete');
    });

    // ==================== 域名管理 ====================
    Route::group('domains', function () {
        // 获取域名列表
        Route::get('list', 'api/domain/domainList');
        // 添加域名
        Route::post('create', 'api/domain/domainCreate');
        // 获取域名详情
        Route::get(':id/detail', 'api/domain/domainDetail');
        // 更新域名
        Route::post(':id/update', 'api/domain/domainUpdate');
        // 删除域名
        Route::post(':id/delete', 'api/domain/domainDelete');
        // 同步域名记录
        Route::post(':id/sync', 'api/domain/domainSync');
        // 批量操作域名
        Route::post('batch-operation', 'api/domain/domainBatchOperation');
    });

    // ==================== 域名记录管理 ====================
    Route::group('records', function () {
        // 获取记录列表
        Route::get(':domain_id/list', 'api/record/recordList');
        // 获取记录详情
        Route::get(':domain_id/:id/detail', 'api/record/recordDetail');
        // 添加记录
        Route::post(':domain_id/create', 'api/record/recordCreate');
        // 更新记录
        Route::post(':domain_id/:id/update', 'api/record/recordUpdate');
        // 删除记录
        Route::post(':domain_id/:id/delete', 'api/record/recordDelete');
        // 切换记录状态
        Route::post(':domain_id/:id/status', 'api/record/recordToggleStatus');
        // 批量添加记录
        Route::post(':domain_id/batch-create', 'api/record/recordBatchCreate');
        // 批量操作记录（启用/暂停/删除/修改备注）
        Route::post(':domain_id/batch-operation', 'api/record/recordBatchOperation');
        // 批量修改记录（修改类型/值/线路/TTL）
        Route::post(':domain_id/batch-update', 'api/record/recordBatchUpdate');
    });

    // ==================== 证书账户管理 ====================
    Route::group('cert-accounts', function () {
        // 获取证书账户列表
        Route::get('list', 'api/certificate/certAccountList');
        // 创建证书账户
        Route::post('create', 'api/certificate/certAccountCreate');
        // 更新证书账户
        Route::post(':id/update', 'api/certificate/certAccountUpdate');
        // 删除证书账户
        Route::post(':id/delete', 'api/certificate/certAccountDelete');
    });

    // ==================== 证书管理 ====================
    Route::group('certificates', function () {
        // 获取证书列表
        Route::get('list', 'api/certificate/certificateList');
        // 申请/导入证书
        Route::post('create', 'api/certificate/certificateCreate');
        // 获取证书详情
        Route::get(':id/detail', 'api/certificate/certificateDetail');
        // 删除证书
        Route::post(':id/delete', 'api/certificate/certificateDelete');
        // 部署证书
        Route::post(':id/deploy', 'api/certificate/certificateDeploy');
        // 获取证书处理进度
        Route::get(':id/process', 'api/certificate/certificateProcess');
        // 获取日志
        Route::get('log', 'api/certificate/showLog');
        // 切换自动续签状态
        Route::post(':id/auto-renew', 'api/certificate/certificateAutoRenew');
        // 重置证书订单
        Route::post(':id/reset', 'api/certificate/certificateReset');
        // 执行/重试申请
        Route::post(':id/execute', 'api/certificate/certificateExecute');
        // 吊销证书
        Route::post(':id/revoke', 'api/certificate/certificateRevoke');
    });

    // ==================== 部署账户管理 ====================
    Route::group('deploy-accounts', function () {
        // 获取部署账户列表
        Route::get('list', 'api/certificate/deployAccountList');
        // 创建部署账户
        Route::post('create', 'api/certificate/deployAccountCreate');
        // 更新部署账户
        Route::post(':id/update', 'api/certificate/deployAccountUpdate');
        // 删除部署账户
        Route::post(':id/delete', 'api/certificate/deployAccountDelete');
    });

    // ==================== 部署任务管理 ====================
    Route::group('deploy-tasks', function () {
        // 获取部署任务列表
        Route::get('list', 'api/certificate/deployTaskList');
        // 创建部署任务
        Route::post('create', 'api/certificate/deployTaskCreate');
        // 更新部署任务
        Route::post(':id/update', 'api/certificate/deployTaskUpdate');
        // 删除部署任务
        Route::post(':id/delete', 'api/certificate/deployTaskDelete');
        // 切换任务状态
        Route::post(':id/active', 'api/certificate/deployTaskToggleActive');
        // 重置部署任务
        Route::post(':id/reset', 'api/certificate/deployTaskReset');
        // 执行部署任务
        Route::post(':id/execute', 'api/certificate/deployTaskExecute');
        // 获取部署任务进度
        Route::get(':id/process', 'api/certificate/deployTaskProcess');
        // 获取日志
        Route::get('log', 'api/certificate/showLog');
    });

    // ==================== CNAME代理管理 ====================
    Route::group('cert-cnames', function () {
        // 获取CNAME代理列表
        Route::get('list', 'api/certificate/cnameList');
        // 创建CNAME代理
        Route::post('create', 'api/certificate/cnameCreate');
        // 更新CNAME代理
        Route::post(':id/update', 'api/certificate/cnameUpdate');
        // 删除CNAME代理
        Route::post(':id/delete', 'api/certificate/cnameDelete');
        // 检查CNAME代理
        Route::post(':id/check', 'api/certificate/cnameCheck');
    });

    // ==================== 用户管理 ====================
    Route::group('users', function () {
        // 获取用户列表
        Route::get('list', 'api/user/userList');
        // 获取用户详情
        Route::get(':id/detail', 'api/user/userDetail');
        // 创建用户
        Route::post('create', 'api/user/userCreate');
        // 更新用户
        Route::post(':id/update', 'api/user/userUpdate');
        // 删除用户
        Route::post(':id/delete', 'api/user/userDelete');
        // 切换用户状态
        Route::post(':id/status', 'api/user/userToggleStatus');
        // 修改密码
        Route::post('change-password', 'api/user/changePassword');
    });

    // ==================== 操作日志 ====================
    Route::group('logs', function () {
        // 获取操作日志列表
        Route::get('list', 'api/user/logList');
    });

    // ==================== 系统管理 ====================
    Route::group('system', function () {
        // 获取系统配置
        Route::get('config', 'api/system/getConfig');
        // 更新系统配置
        Route::post('config', 'api/system/updateConfig');
        // 测试邮件发送
        Route::post('test-mail', 'api/system/testMail');
        // 测试Telegram Bot
        Route::post('test-telegram', 'api/system/testTelegram');
        // 测试Webhook
        Route::post('test-webhook', 'api/system/testWebhook');
        // 测试代理服务器
        Route::post('test-proxy', 'api/system/testProxy');
        // 获取定时任务配置
        Route::get('cron-config', 'api/system/getCronConfig');
        // 清理缓存
        Route::post('clear-cache', 'api/system/clearCache');
        // 获取系统信息
        Route::get('info', 'api/system/getSystemInfo');
    });

    // ==================== 定时任务管理 ====================
    Route::group('schedule-tasks', function () {
        // 获取定时任务列表
        Route::get('list', 'api/schedule/taskList');
        // 创建定时任务
        Route::post('create', 'api/schedule/taskCreate');
        // 更新定时任务
        Route::post(':id/update', 'api/schedule/taskUpdate');
        // 删除定时任务
        Route::post(':id/delete', 'api/schedule/taskDelete');
        // 切换任务状态
        Route::post(':id/active', 'api/schedule/taskToggleActive');
        // 批量操作定时任务
        Route::post('batch-operation', 'api/schedule/taskBatchOperation');
    });

    // ==================== 域名监控管理 ====================
    Route::group('monitor', function () {
        // 获取监控概览
        Route::get('overview', 'api/monitor/overview');
        // 获取监控状态
        Route::get('status', 'api/monitor/status');
        // 清理监控日志
        Route::post('logs/clean', 'api/monitor/cleanLogs');

        // 监控任务管理
        Route::group('tasks', function () {
            // 获取监控任务列表
            Route::get('list', 'api/monitor/taskList');
            // 获取监控任务详情
            Route::get(':id/detail', 'api/monitor/taskDetail');
            // 创建监控任务
            Route::post('create', 'api/monitor/taskCreate');
            // 更新监控任务
            Route::post(':id/update', 'api/monitor/taskUpdate');
            // 删除监控任务
            Route::post(':id/delete', 'api/monitor/taskDelete');
            // 切换任务状态
            Route::post(':id/active', 'api/monitor/taskToggleActive');
            // 批量操作监控任务
            Route::post('batch-operation', 'api/monitor/taskBatchOperation');
            // 获取监控日志
            Route::get(':id/logs', 'api/monitor/taskLogs');
        });
    });

})->middleware(ApiAuth::class)->allowCrossDomain();
