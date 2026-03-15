<?php

declare(strict_types=1);

namespace app\api\controller;

use think\facade\Db;
use Exception;

/**
 * 用户管理 API 控制器
 */
class User extends BaseController
{
    /**
     * 获取用户列表
     * GET /api/v1/users/list
     */
    public function userList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('user');
        if (!empty($kw)) {
            $select->whereLike('id|username', $kw);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        return $this->paginate($rows, $total, $page, $pageSize, '获取用户列表成功');
    }

    /**
     * 获取用户详情
     * GET /api/v1/users/:id/detail
     */
    public function userDetail()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $user = Db::name('user')->where('id', $id)->find();

        if (!$user) {
            return $this->notFound('用户不存在');
        }

        $user['permission'] = Db::name('permission')->where('uid', $id)->column('domain');
        unset($user['password']);

        return $this->success($user, '获取用户详情成功');
    }

    /**
     * 创建用户
     * POST /api/v1/users/create
     */
    public function userCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $username = $this->request->post('username', '', 'trim');
        $password = $this->request->post('password', '', 'trim');
        $is_api = $this->request->post('is_api', 0, 'intval');
        $apikey = $this->request->post('apikey', '', 'trim');
        $level = $this->request->post('level', 1, 'intval');
        $permission = $this->request->post('permission', []);

        if (empty($username) || empty($password)) {
            return $this->validationError('username 和 password 为必填参数');
        }

        if ($is_api == 1 && empty($apikey)) {
            return $this->validationError('启用API时，apikey 不能为空');
        }

        if (Db::name('user')->where('username', $username)->find()) {
            return $this->error('用户名已存在', null, 409);
        }

        Db::startTrans();
        try {
            $uid = Db::name('user')->insertGetId([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'is_api' => $is_api,
                'apikey' => $apikey,
                'level' => $level,
                'regtime' => date('Y-m-d H:i:s'),
                'status' => 1,
            ]);

            if ($level == 1 && !empty($permission)) {
                $data = [];
                foreach ($permission as $domain) {
                    $data[] = ['uid' => $uid, 'domain' => $domain];
                }
                Db::name('permission')->insertAll($data);
            }

            Db::commit();

            $user = Db::name('user')->where('id', $uid)->find();
            unset($user['password']);

            return $this->created($user, '添加用户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('添加用户失败：' . $e->getMessage());
        }
    }

    /**
     * 更新用户
     * POST /api/v1/users/:id/update
     */
    public function userUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $user = Db::name('user')->where('id', $id)->find();

        if (!$user) {
            return $this->notFound('用户不存在');
        }

        $username = $this->request->post('username', '', 'trim');
        $is_api = $this->request->post('is_api', 0, 'intval');
        $apikey = $this->request->post('apikey', '', 'trim');
        $level = $this->request->post('level', 1, 'intval');
        $repwd = $this->request->post('repwd', '', 'trim');
        $permission = $this->request->post('permission', []);

        if (empty($username)) {
            return $this->validationError('username 不能为空');
        }

        if ($is_api == 1 && empty($apikey)) {
            return $this->validationError('启用API时，apikey 不能为空');
        }

        if (Db::name('user')->where('username', $username)->where('id', '<>', $id)->find()) {
            return $this->error('用户名已存在', null, 409);
        }

        // 防止降级管理员或当前用户
        if ($level == 1 && ($id == 1000 || $id == $this->request->user['id'])) {
            $level = 2;
        }

        Db::startTrans();
        try {
            Db::name('user')->where('id', $id)->update([
                'username' => $username,
                'is_api' => $is_api,
                'apikey' => $apikey,
                'level' => $level,
            ]);

            // 更新权限
            Db::name('permission')->where('uid', $id)->delete();
            if ($level == 1 && !empty($permission)) {
                $data = [];
                foreach ($permission as $domain) {
                    $data[] = ['uid' => $id, 'domain' => $domain];
                }
                Db::name('permission')->insertAll($data);
            }

            // 更新密码
            if (!empty($repwd)) {
                Db::name('user')->where('id', $id)->update(['password' => password_hash($repwd, PASSWORD_DEFAULT)]);
            }

            Db::commit();

            $user = Db::name('user')->where('id', $id)->find();
            unset($user['password']);

            return $this->success($user, '修改用户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('修改用户失败：' . $e->getMessage());
        }
    }

    /**
     * 删除用户
     * POST /api/v1/users/:id/delete
     */
    public function userDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');

        if ($id == 1000) {
            return $this->error('此用户无法删除');
        }

        if ($id == $this->request->user['id']) {
            return $this->error('当前登录用户无法删除');
        }

        $user = Db::name('user')->where('id', $id)->find();
        if (!$user) {
            return $this->notFound('用户不存在');
        }

        Db::startTrans();
        try {
            Db::name('user')->where('id', $id)->delete();
            Db::name('permission')->where('uid', $id)->delete();

            Db::commit();
            return $this->success(null, '删除用户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('删除用户失败：' . $e->getMessage());
        }
    }

    /**
     * 切换用户状态
     * POST /api/v1/users/:id/status
     */
    public function userToggleStatus()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $status = $this->request->post('status', 0, 'intval');

        if ($id == 1000) {
            return $this->error('此用户无法修改状态');
        }

        if ($id == $this->request->user['id']) {
            return $this->error('当前登录用户无法修改状态');
        }

        $user = Db::name('user')->where('id', $id)->find();
        if (!$user) {
            return $this->notFound('用户不存在');
        }

        Db::name('user')->where('id', $id)->update(['status' => $status]);
        return $this->success(['status' => $status], '切换用户状态成功');
    }

    /**
     * 修改密码
     * POST /api/v1/users/change-password
     */
    public function changePassword()
    {
        $oldPassword = $this->request->post('old_password', '', 'trim');
        $newPassword = $this->request->post('new_password', '', 'trim');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->validationError('old_password 和 new_password 为必填参数');
        }

        $user = Db::name('user')->where('id', $this->request->user['id'])->find();
        if (!password_verify($oldPassword, $user['password'])) {
            return $this->error('原密码错误');
        }

        Db::name('user')->where('id', $this->request->user['id'])->update([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);

        return $this->success(null, '修改密码成功');
    }

    /**
     * 获取操作日志列表
     * GET /api/v1/logs/list
     */
    public function logList()
    {
        $uid = $this->request->get('uid', '', 'trim');
        $kw = $this->request->get('kw', '', 'trim');
        $domain = $this->request->get('domain', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('log');

        // 权限过滤
        if ($this->request->user['type'] == 'domain') {
            $select->where('domain', $this->request->user['name']);
        } elseif ($this->request->user['level'] == 1) {
            $select->where('uid', $this->request->user['id']);
        } elseif (!empty($uid)) {
            $select->where('uid', $uid);
        }

        if (!empty($kw)) {
            $select->whereLike('action|data', '%' . $kw . '%');
        }

        if (!empty($domain)) {
            $select->where('domain', $domain);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        return $this->paginate($rows, $total, $page, $pageSize, '获取操作日志成功');
    }
}
