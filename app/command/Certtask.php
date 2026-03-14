<?php

declare(strict_types=1);

namespace app\command;

use app\service\CertTaskService;
use app\service\ExpireNoticeService;
use app\service\OptimizeService;
use app\service\ScheduleService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Config;
use think\facade\Db;

class Certtask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('certtask')
            ->setDescription('SSL证书续签与部署、域名到期提醒、定时切换解析、CF优选IP更新');
    }

    protected function execute(Input $input, Output $output)
    {
        $res = Db::name('config')->cache('configs', 0)->column('value', 'key');
        Config::set($res, 'sys');

        (new ScheduleService())->execute();
        $res = (new OptimizeService())->execute();
        if (!$res) {
            (new CertTaskService())->execute();
            (new ExpireNoticeService())->task();
        }
        echo 'done' . PHP_EOL;
    }
}
