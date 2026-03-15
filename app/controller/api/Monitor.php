<?php

declare(strict_types=1);

namespace app\controller\api;

use think\facade\Db;
use Exception;

/**
 * 域名监控管理 API 控制器
 */
class Monitor extends BaseController
{
    /**
     * 获取监控概览
     * GET /api/v1/monitor/overview
     */
    public function overview()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $switch_count = Db::name('dmlog')->where('date', '>=', date("Y-m-d H:i:s", strtotime("-1 days")))->count();
        $fail_count = Db::name('dmlog')->where('date', '>=', date("Y-m-d H:i:s", strtotime("-1 days")))->where('action', 1)->count();

        $run_time = config_get('run_time', null, true);
        $run_state = $run_time ? (time() - strtotime($run_time) > 10 ? 0 : 1) : 0;

        $info = [
            'run_count' => config_get('run_count', null, true) ?? 0,
            'run_time' => $run_time ?? '无',
            'run_state' => $run_state,
            'run_error' => config_get('run_error', null, true),
            'switch_count' => $switch_count,
            'fail_count' => $fail_count,
            'swoole' => extension_loaded('swoole'),
        ];

        return $this->success($info, '获取监控概览成功');
    }

    /**
     * 获取监控任务列表
     * GET /api/v1/monitor/tasks/list
     */
    public function taskList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $type = $this->request->get('type', 1, 'intval');
        $status = $this->request->get('status', '', 'trim');
        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('dmtask')->alias('A')->join('domain B', 'A.did = B.id');

        if (!empty($kw)) {
            if ($type == 1) {
                $select->whereLike('rr|B.name', '%' . $kw . '%');
            } elseif ($type == 2) {
                $select->where('recordid', $kw);
            } elseif ($type == 3) {
                $select->where('main_value', $kw);
            } elseif ($type == 4) {
                $select->where('backup_value', $kw);
            } elseif ($type == 5) {
                $select->whereLike('remark', '%' . $kw . '%');
            }
        }

        if ($status !== '') {
            $select->where('status', intval($status));
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $list = $select->order('A.id', 'desc')
            ->limit($offset, $pageSize)
            ->field('A.*,B.name domain')
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $row['addtimestr'] = date('Y-m-d H:i:s', $row['addtime']);
            $row['checktimestr'] = $row['checktime'] > 0 ? date('Y-m-d H:i:s', $row['checktime']) : '未运行';
        }

        return $this->paginate($list, $total, $page, $pageSize, '获取监控任务列表成功');
    }

    /**
     * 获取监控任务详情
     * GET /api/v1/monitor/tasks/:id/detail
     */
    public function taskDetail()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('dmtask')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('监控任务不存在');
        }

        $switch_count = Db::name('dmlog')->where('taskid', $id)->where('date', '>=', date("Y-m-d H:i:s", strtotime("-1 days")))->count();
        $fail_count = Db::name('dmlog')->where('taskid', $id)->where('date', '>=', date("Y-m-d H:i:s", strtotime("-1 days")))->where('action', 1)->count();

        $task['switch_count'] = $switch_count;
        $task['fail_count'] = $fail_count;

        if ($task['type'] == 3) {
            $task['action_name'] = ['未知', '开启解析', '暂停解析'];
        } elseif ($task['type'] == 2) {
            $task['action_name'] = ['未知', '切换备用解析记录', '恢复主解析记录'];
        } else {
            $task['action_name'] = ['未知', '暂停解析', '启用解析'];
        }

        return $this->success($task, '获取监控任务详情成功');
    }

    /**
     * 创建监控任务
     * POST /api/v1/monitor/tasks/create
     */
    public function taskCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $task = [
            'did' => $this->request->post('did', 0, 'intval'),
            'rr' => $this->request->post('rr', '', 'trim'),
            'recordid' => $this->request->post('recordid', '', 'trim'),
            'type' => $this->request->post('type', 0, 'intval'),
            'main_value' => $this->request->post('main_value', '', 'trim'),
            'backup_value' => $this->request->post('backup_value', '', 'trim'),
            'checktype' => $this->request->post('checktype', 0, 'intval'),
            'checkurl' => $this->request->post('checkurl', '', 'trim'),
            'tcpport' => $this->request->post('tcpport', 0, 'intval') ?: null,
            'frequency' => $this->request->post('frequency', 0, 'intval'),
            'cycle' => $this->request->post('cycle', 0, 'intval'),
            'timeout' => $this->request->post('timeout', 0, 'intval'),
            'proxy' => $this->request->post('proxy', 0, 'intval'),
            'cdn' => $this->request->post('cdn', 0, 'intval'),
            'remark' => $this->request->post('remark', '', 'trim'),
            'recordinfo' => $this->request->post('recordinfo', '', 'trim'),
            'addtime' => time(),
            'active' => 1
        ];

        if (empty($task['did']) || empty($task['rr']) || empty($task['recordid']) || empty($task['main_value']) || empty($task['frequency']) || empty($task['cycle'])) {
            return $this->validationError('did、rr、recordid、main_value、frequency、cycle 为必填参数');
        }

        if ($task['checktype'] > 0 && $task['timeout'] > $task['frequency']) {
            return $this->error('为保障容灾切换任务正常运行，最大超时时间不能大于检测间隔');
        }

        if ($task['type'] == 2 && $task['backup_value'] == $task['main_value']) {
            return $this->error('主备地址不能相同');
        }

        if (Db::name('dmtask')->where('recordid', $task['recordid'])->find()) {
            return $this->error('当前容灾切换策略已存在', null, 409);
        }

        $id = Db::name('dmtask')->insertGetId($task);
        $task['id'] = $id;

        return $this->created($task, '添加监控任务成功');
    }

    /**
     * 更新监控任务
     * POST /api/v1/monitor/tasks/:id/update
     */
    public function taskUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('dmtask')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('监控任务不存在');
        }

        $updateData = [
            'did' => $this->request->post('did', 0, 'intval'),
            'rr' => $this->request->post('rr', '', 'trim'),
            'recordid' => $this->request->post('recordid', '', 'trim'),
            'type' => $this->request->post('type', 0, 'intval'),
            'main_value' => $this->request->post('main_value', '', 'trim'),
            'backup_value' => $this->request->post('backup_value', '', 'trim'),
            'checktype' => $this->request->post('checktype', 0, 'intval'),
            'checkurl' => $this->request->post('checkurl', '', 'trim'),
            'tcpport' => $this->request->post('tcpport', 0, 'intval') ?: null,
            'frequency' => $this->request->post('frequency', 0, 'intval'),
            'cycle' => $this->request->post('cycle', 0, 'intval'),
            'timeout' => $this->request->post('timeout', 0, 'intval'),
            'proxy' => $this->request->post('proxy', 0, 'intval'),
            'cdn' => $this->request->post('cdn', 0, 'intval'),
            'remark' => $this->request->post('remark', '', 'trim'),
            'recordinfo' => $this->request->post('recordinfo', '', 'trim'),
        ];

        if (empty($updateData['did']) || empty($updateData['rr']) || empty($updateData['recordid']) || empty($updateData['main_value']) || empty($updateData['frequency']) || empty($updateData['cycle'])) {
            return $this->validationError('did、rr、recordid、main_value、frequency、cycle 为必填参数');
        }

        if ($updateData['checktype'] > 0 && $updateData['timeout'] > $updateData['frequency']) {
            return $this->error('为保障容灾切换任务正常运行，最大超时时间不能大于检测间隔');
        }

        if ($updateData['type'] == 2 && $updateData['backup_value'] == $updateData['main_value']) {
            return $this->error('主备地址不能相同');
        }

        if (Db::name('dmtask')->where('recordid', $updateData['recordid'])->where('id', '<>', $id)->find()) {
            return $this->error('当前容灾切换策略已存在', null, 409);
        }

        Db::name('dmtask')->where('id', $id)->update($updateData);
        $task = Db::name('dmtask')->where('id', $id)->find();

        return $this->success($task, '修改监控任务成功');
    }

    /**
     * 删除监控任务
     * POST /api/v1/monitor/tasks/:id/delete
     */
    public function taskDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('dmtask')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('监控任务不存在');
        }

        Db::startTrans();
        try {
            Db::name('dmtask')->where('id', $id)->delete();
            Db::name('dmlog')->where('taskid', $id)->delete();

            Db::commit();
            return $this->success(null, '删除监控任务成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('删除监控任务失败：' . $e->getMessage());
        }
    }

    /**
     * 切换任务状态
     * POST /api/v1/monitor/tasks/:id/active
     */
    public function taskToggleActive()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $active = $this->request->post('active', 0, 'intval');

        $task = Db::name('dmtask')->where('id', $id)->find();
        if (!$task) {
            return $this->notFound('监控任务不存在');
        }

        Db::name('dmtask')->where('id', $id)->update(['active' => $active]);
        return $this->success(['active' => $active], '切换任务状态成功');
    }

    /**
     * 批量操作监控任务
     * POST /api/v1/monitor/tasks/batch-operation
     */
    public function taskBatchOperation()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $ids = $this->request->post('ids', []);
        $action = $this->request->post('action', '', 'trim');

        if (empty($ids) || !is_array($ids)) {
            return $this->validationError('ids 参数必须是数组且不能为空');
        }

        $success = 0;
        foreach ($ids as $id) {
            if ($action == 'delete') {
                Db::name('dmtask')->where('id', $id)->delete();
                Db::name('dmlog')->where('taskid', $id)->delete();
                $success++;
            } elseif ($action == 'retry') {
                Db::name('dmtask')->where('id', $id)->update(['checknexttime' => time()]);
                $success++;
            } elseif ($action == 'open' || $action == 'close') {
                $active = $action == 'open' ? 1 : 0;
                Db::name('dmtask')->where('id', $id)->update(['active' => $active]);
                $success++;
            }
        }

        return $this->success(['success_count' => $success], "成功操作 {$success} 个容灾切换策略");
    }

    /**
     * 获取监控日志列表
     * GET /api/v1/monitor/tasks/:id/logs
     */
    public function taskLogs()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $taskid = $this->request->param('id', 0, 'intval');
        $action = $this->request->get('action', 0, 'intval');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('dmlog')->where('taskid', $taskid);
        if ($action > 0) {
            $select->where('action', $action);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $list = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        return $this->paginate($list, $total, $page, $pageSize, '获取监控日志成功');
    }

    /**
     * 清理监控日志
     * POST /api/v1/monitor/logs/clean
     */
    public function cleanLogs()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $days = $this->request->post('days', 0, 'intval');
        if (!$days || $days < 0) {
            return $this->validationError('days 参数必须大于 0');
        }

        try {
            $prefix = config('database.connections.mysql.prefix');
            Db::execute("DELETE FROM `{$prefix}dmlog` WHERE `date`<'" . date("Y-m-d H:i:s", strtotime("-{$days} days")) . "'");
            Db::execute("OPTIMIZE TABLE `{$prefix}dmlog`");

            return $this->success(null, '清理监控日志成功');
        } catch (Exception $e) {
            return $this->error('清理监控日志失败：' . $e->getMessage());
        }
    }

    /**
     * 获取监控状态
     * GET /api/v1/monitor/status
     */
    public function status()
    {
        $run_time = config_get('run_time', null, true);
        $run_state = $run_time ? (time() - strtotime($run_time) > 10 ? 0 : 1) : 0;

        return $this->success([
            'status' => $run_state,
            'run_time' => $run_time
        ], '获取监控状态成功');
    }
}
