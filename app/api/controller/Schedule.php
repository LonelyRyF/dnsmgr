<?php

declare(strict_types=1);

namespace app\api\controller;

use app\service\ScheduleService;
use think\facade\Db;
use Exception;

/**
 * 定时任务管理 API 控制器
 */
class Schedule extends BaseController
{
    /**
     * 获取定时任务列表
     * GET /api/v1/schedule-tasks/list
     */
    public function taskList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $type = $this->request->get('type', 1, 'intval');
        $kw = $this->request->get('kw', '', 'trim');
        $stype = $this->request->get('stype', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('sctask')->alias('A')->join('domain B', 'A.did = B.id');

        if (!empty($kw)) {
            if ($type == 1) {
                $select->whereLike('rr|B.name', '%' . $kw . '%');
            } elseif ($type == 2) {
                $select->where('recordid', $kw);
            } elseif ($type == 3) {
                $select->where('value', $kw);
            } elseif ($type == 4) {
                $select->whereLike('remark', '%' . $kw . '%');
            }
        }

        if ($stype !== '') {
            $select->where('type', $stype);
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
            $row['updatetimestr'] = $row['updatetime'] > 0 ? date('Y-m-d H:i:s', $row['updatetime']) : '未运行';
            $row['nexttimestr'] = $row['nexttime'] > 0 ? date('Y-m-d H:i:s', $row['nexttime']) : '无';
        }

        return $this->paginate($list, $total, $page, $pageSize, '获取定时任务列表成功');
    }

    /**
     * 创建定时任务
     * POST /api/v1/schedule-tasks/create
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
            'cycle' => $this->request->post('cycle', 0, 'intval'),
            'switchtype' => $this->request->post('switchtype', 0, 'intval'),
            'switchdate' => $this->request->post('switchdate', '', 'trim'),
            'switchtime' => $this->request->post('switchtime', '', 'trim'),
            'value' => $this->request->post('value', '', 'trim'),
            'line' => $this->request->post('line', '', 'trim'),
            'remark' => $this->request->post('remark', '', 'trim'),
            'recordinfo' => $this->request->post('recordinfo', '', 'trim'),
            'addtime' => time(),
            'active' => 1
        ];

        if (empty($task['did']) || empty($task['rr']) || empty($task['recordid'])) {
            return $this->validationError('did、rr、recordid 为必填参数');
        }

        if (Db::name('sctask')->where('recordid', $task['recordid'])
            ->where('switchtype', $task['switchtype'])
            ->where('switchtime', $task['switchtime'])
            ->find()) {
            return $this->error('当前定时切换策略已存在', null, 409);
        }

        $id = Db::name('sctask')->insertGetId($task);
        $row = Db::name('sctask')->where('id', $id)->find();
        (new ScheduleService())->update_nexttime($row);

        return $this->created($row, '添加定时任务成功');
    }

    /**
     * 更新定时任务
     * POST /api/v1/schedule-tasks/:id/update
     */
    public function taskUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('sctask')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('定时任务不存在');
        }

        $updateData = [
            'did' => $this->request->post('did', 0, 'intval'),
            'rr' => $this->request->post('rr', '', 'trim'),
            'recordid' => $this->request->post('recordid', '', 'trim'),
            'type' => $this->request->post('type', 0, 'intval'),
            'cycle' => $this->request->post('cycle', 0, 'intval'),
            'switchtype' => $this->request->post('switchtype', 0, 'intval'),
            'switchdate' => $this->request->post('switchdate', '', 'trim'),
            'switchtime' => $this->request->post('switchtime', '', 'trim'),
            'value' => $this->request->post('value', '', 'trim'),
            'line' => $this->request->post('line', '', 'trim'),
            'remark' => $this->request->post('remark', '', 'trim'),
            'recordinfo' => $this->request->post('recordinfo', '', 'trim'),
        ];

        if (empty($updateData['did']) || empty($updateData['rr']) || empty($updateData['recordid'])) {
            return $this->validationError('did、rr、recordid 为必填参数');
        }

        if (Db::name('sctask')->where('recordid', $updateData['recordid'])
            ->where('switchtype', $updateData['switchtype'])
            ->where('switchtime', $updateData['switchtime'])
            ->where('id', '<>', $id)
            ->find()) {
            return $this->error('当前定时切换策略已存在', null, 409);
        }

        Db::name('sctask')->where('id', $id)->update($updateData);
        $row = Db::name('sctask')->where('id', $id)->find();
        (new ScheduleService())->update_nexttime($row);

        return $this->success($row, '修改定时任务成功');
    }

    /**
     * 删除定时任务
     * POST /api/v1/schedule-tasks/:id/delete
     */
    public function taskDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('sctask')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('定时任务不存在');
        }

        Db::name('sctask')->where('id', $id)->delete();
        return $this->success(null, '删除定时任务成功');
    }

    /**
     * 切换任务状态
     * POST /api/v1/schedule-tasks/:id/active
     */
    public function taskToggleActive()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $active = $this->request->post('active', 0, 'intval');

        $task = Db::name('sctask')->where('id', $id)->find();
        if (!$task) {
            return $this->notFound('定时任务不存在');
        }

        Db::name('sctask')->where('id', $id)->update(['active' => $active]);
        return $this->success(['active' => $active], '切换任务状态成功');
    }

    /**
     * 批量操作定时任务
     * POST /api/v1/schedule-tasks/batch-operation
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
                Db::name('sctask')->where('id', $id)->delete();
                $success++;
            } elseif ($action == 'open' || $action == 'close') {
                $active = $action == 'open' ? 1 : 0;
                Db::name('sctask')->where('id', $id)->update(['active' => $active]);
                $success++;
            }
        }

        return $this->success(['success_count' => $success], "成功操作 {$success} 个定时切换策略");
    }
}
