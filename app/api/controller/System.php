<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\ScheduleService;
use app\service\CertTaskService;
use app\service\ExpireNoticeService;
use app\service\OptimizeService;
use app\utils\MsgNotice;
use think\facade\Db;
use think\facade\Cache;
use Exception;

/**
 * 系统管理 API 控制器
 */
class System extends BaseController
{
    /**
     * 获取系统配置
     * GET /api/v1/system/config
     */
    public function getConfig()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $configs = Db::name('config')->select()->toArray();
        $result = [];
        foreach ($configs as $config) {
            $result[$config['key']] = $config['value'];
        }

        return $this->success($result, '获取系统配置成功');
    }

    /**
     * 更新系统配置
     * POST /api/v1/system/config
     */
    public function updateConfig()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $params = $this->request->post();

        if (isset($params['mail_type']) && isset($params['mail_name2']) && $params['mail_type'] > 0) {
            $params['mail_name'] = $params['mail_name2'];
            unset($params['mail_name2']);
        }

        foreach ($params as $key => $value) {
            if (empty($key)) {
                continue;
            }
            config_set($key, $value);
        }

        Cache::delete('configs');
        return $this->success(null, '更新系统配置成功');
    }

    /**
     * 测试邮件发送
     * POST /api/v1/system/test-mail
     */
    public function testMail()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $mail_name = config_get('mail_recv') ? config_get('mail_recv') : config_get('mail_name');
        if (empty($mail_name)) {
            return $this->error('您还未设置邮箱');
        }

        $result = MsgNotice::send_mail($mail_name, '邮件发送测试。', '这是一封测试邮件！<br/><br/>来自：' . $this->request->root(true));
        if ($result === true) {
            return $this->success(null, '邮件发送成功');
        } else {
            return $this->error('邮件发送失败：' . $result);
        }
    }

    /**
     * 测试Telegram Bot
     * POST /api/v1/system/test-telegram
     */
    public function testTelegram()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $tgbot_token = config_get('tgbot_token');
        $tgbot_chatid = config_get('tgbot_chatid');

        if (empty($tgbot_token) || empty($tgbot_chatid)) {
            return $this->error('请先保存设置');
        }

        $content = "<strong>消息发送测试</strong>\n\n这是一封测试消息！\n\n来自：" . $this->request->root(true);
        $result = MsgNotice::send_telegram_bot($content);

        if ($result === true) {
            return $this->success(null, '消息发送成功');
        } else {
            return $this->error('消息发送失败：' . $result);
        }
    }

    /**
     * 测试Webhook
     * POST /api/v1/system/test-webhook
     */
    public function testWebhook()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $webhook_url = config_get('webhook_url');
        if (empty($webhook_url)) {
            return $this->error('请先保存设置');
        }

        $content = "这是一封测试消息！\n来自：" . $this->request->root(true);
        $result = MsgNotice::send_webhook('消息发送测试', $content);

        if ($result === true) {
            return $this->success(null, '消息发送成功');
        } else {
            return $this->error('消息发送失败：' . $result);
        }
    }

    /**
     * 测试代理服务器
     * POST /api/v1/system/test-proxy
     */
    public function testProxy()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $proxy_server = $this->request->post('proxy_server', '', 'trim');
        $proxy_port = $this->request->post('proxy_port', 0, 'intval');
        $proxy_user = $this->request->post('proxy_user', '', 'trim');
        $proxy_pwd = $this->request->post('proxy_pwd', '', 'trim');
        $proxy_type = $this->request->post('proxy_type', 'http', 'trim');

        try {
            check_proxy('https://dl.amh.sh/ip.htm', $proxy_server, $proxy_port, $proxy_type, $proxy_user, $proxy_pwd);
        } catch (Exception $e) {
            try {
                check_proxy('https://myip.ipip.net/', $proxy_server, $proxy_port, $proxy_type, $proxy_user, $proxy_pwd);
            } catch (Exception $e) {
                return $this->error('代理服务器测试失败：' . $e->getMessage());
            }
        }

        return $this->success(null, '代理服务器测试成功');
    }

    /**
     * 获取定时任务配置
     * GET /api/v1/system/cron-config
     */
    public function getCronConfig()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        if (config_get('cron_key') === null) {
            config_set('cron_key', random(10));
            Cache::delete('configs');
        }

        $config = [
            'cron_key' => config_get('cron_key'),
            'cron_type' => config_get('cron_type', '0'),
            'siteurl' => $this->request->root(true),
            'is_user_www' => isset($_SERVER['USER']) && $_SERVER['USER'] == 'www',
        ];

        return $this->success($config, '获取定时任务配置成功');
    }

    /**
     * 执行定时任务（通过URL访问）
     * GET /api/v1/system/cron
     */
    public function executeCron()
    {
        if (function_exists("set_time_limit")) {
            @set_time_limit(0);
        }
        if (function_exists("ignore_user_abort")) {
            @ignore_user_abort(true);
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'Baiduspider')) {
            return $this->error('禁止访问');
        }

        $key = $this->request->get('key', '');
        $cron_key = config_get('cron_key');

        if (config_get('cron_type', '0') != '1' || empty($cron_key)) {
            return $this->error('未开启当前方式');
        }

        if ($key != $cron_key) {
            return $this->error('访问密钥错误', null, 403);
        }

        try {
            (new ScheduleService())->execute();
            $res = (new OptimizeService())->execute();
            if (!$res) {
                (new CertTaskService())->execute();
                (new ExpireNoticeService())->task();
            }

            return $this->success(null, '定时任务执行成功');
        } catch (Exception $e) {
            return $this->error('定时任务执行失败：' . $e->getMessage());
        }
    }

    /**
     * 清理缓存
     * POST /api/v1/system/clear-cache
     */
    public function clearCache()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        try {
            Cache::clear();
            return $this->success(null, '清理缓存成功');
        } catch (Exception $e) {
            return $this->error('清理缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 获取系统信息
     * GET /api/v1/system/info
     */
    public function getSystemInfo()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $info = [
            'php_version' => PHP_VERSION,
            'think_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'db_version' => Db::query('SELECT VERSION() as version')[0]['version'] ?? 'Unknown',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        return $this->success($info, '获取系统信息成功');
    }
}
