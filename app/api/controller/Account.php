<?php

declare(strict_types=1);

namespace app\api\controller;

use app\lib\DnsHelper;
use think\facade\Db;
use Exception;

/**
 * 域名账户管理 API 控制器
 */
class Account extends BaseController
{
    /**
     * 获取账户列表
     * GET /api/v1/accounts
     */
    public function accountList()
    {
        // 权限检查：仅管理员可访问
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('account');
        if (!empty($kw)) {
            $select->whereLike('name|remark', '%' . $kw . '%');
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        // 添加类型信息
        foreach ($rows as &$row) {
            $row['typename'] = DnsHelper::$dns_config[$row['type']]['name'] ?? '未知';
            $row['icon'] = DnsHelper::$dns_config[$row['type']]['icon'] ?? '';
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取账户列表成功');
    }

    /**
     * 获取账户详情
     * GET /api/v1/accounts/:id
     */
    public function accountDetail()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('account')->where('id', $id)->find();

        if (!$account) {
            return $this->notFound('域名账户不存在');
        }

        // 添加类型信息
        $account['typename'] = DnsHelper::$dns_config[$account['type']]['name'] ?? '未知';
        $account['icon'] = DnsHelper::$dns_config[$account['type']]['icon'] ?? '';

        return $this->success($account, '获取账户详情成功');
    }

    /**
     * 创建账户
     * POST /api/v1/accounts
     */
    public function accountCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        // 参数验证
        if (empty($type) || empty($name) || empty($config)) {
            return $this->validationError('type、name、config 为必填参数');
        }

        // 检查类型是否支持
        if (!isset(DnsHelper::$dns_config[$type])) {
            return $this->validationError('不支持的 DNS 类型');
        }

        // 检查账户是否已存在
        if (Db::name('account')->where('type', $type)->where('name', $name)->find()) {
            return $this->error('域名账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            $id = Db::name('account')->insertGetId([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
                'addtime' => date('Y-m-d H:i:s'),
            ]);

            // 验证账户配置
            $dns = DnsHelper::getModel($id);
            if (!$dns) {
                throw new Exception('DNS 模块(' . $type . ')不存在');
            }

            if (!$dns->check()) {
                throw new Exception('验证域名账户失败：' . $dns->getError());
            }

            Db::commit();

            // 返回创建的账户信息
            $account = Db::name('account')->where('id', $id)->find();
            $account['typename'] = DnsHelper::$dns_config[$account['type']]['name'];
            $account['icon'] = DnsHelper::$dns_config[$account['type']]['icon'];

            return $this->created($account, '添加域名账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新账户
     * PUT /api/v1/accounts/:id
     */
    public function accountUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $row = Db::name('account')->where('id', $id)->find();

        if (!$row) {
            return $this->notFound('域名账户不存在');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        // 参数验证
        if (empty($type) || empty($name) || empty($config)) {
            return $this->validationError('type、name、config 为必填参数');
        }

        // 检查类型是否支持
        if (!isset(DnsHelper::$dns_config[$type])) {
            return $this->validationError('不支持的 DNS 类型');
        }

        // 检查账户名是否与其他账户冲突
        if (Db::name('account')->where('type', $type)->where('name', $name)->where('id', '<>', $id)->find()) {
            return $this->error('域名账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            Db::name('account')->where('id', $id)->update([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
            ]);

            // 验证账户配置
            $dns = DnsHelper::getModel($id);
            if (!$dns) {
                throw new Exception('DNS 模块(' . $type . ')不存在');
            }

            if (!$dns->check()) {
                throw new Exception('验证域名账户失败：' . $dns->getError());
            }

            Db::commit();

            // 返回更新后的账户信息
            $account = Db::name('account')->where('id', $id)->find();
            $account['typename'] = DnsHelper::$dns_config[$account['type']]['name'];
            $account['icon'] = DnsHelper::$dns_config[$account['type']]['icon'];

            return $this->success($account, '修改域名账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除账户
     * DELETE /api/v1/accounts/:id
     */
    public function accountDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('account')->where('id', $id)->find();

        if (!$account) {
            return $this->notFound('域名账户不存在');
        }

        // 检查是否有关联的域名
        $dcount = Db::name('domain')->where('aid', $id)->count();
        if ($dcount > 0) {
            return $this->error('该域名账户下存在 ' . $dcount . ' 个域名，无法删除', null, 409);
        }

        Db::name('account')->where('id', $id)->delete();

        return $this->success(null, '删除域名账户成功');
    }
}
