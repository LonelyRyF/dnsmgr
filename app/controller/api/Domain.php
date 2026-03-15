<?php

declare(strict_types=1);

namespace app\controller\api;

use app\lib\DnsHelper;
use think\facade\Db;
use Exception;

/**
 * 域名管理 API 控制器
 */
class Domain extends BaseController
{
    /**
     * 获取域名列表
     * GET /api/v1/domains
     */
    public function domainList()
    {
        $kw = $this->request->get('kw', '', 'trim');
        $type = $this->request->get('type', '', 'trim');
        $status = $this->request->get('status', '', 'trim');
        $order = $this->request->get('order', '', 'trim');
        $aid = $this->request->get('aid', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('domain')->alias('A')->join('account B', 'A.aid = B.id');

        // 关键词搜索
        if (!empty($kw)) {
            $select->whereLike('A.name|A.remark', '%' . $kw . '%');
        }

        // 按账户筛选
        if (!empty($aid)) {
            $select->where('A.aid', $aid);
        }

        // 按类型筛选
        if (!empty($type)) {
            $select->whereLike('B.type', $type);
        }

        // 权限控制
        if ($this->request->user['level'] == 1) {
            $select->where('is_hide', 0)->where('A.name', 'in', $this->request->user['permission']);
        }

        // 状态筛选
        if (!empty($status)) {
            if ($status == '2') {
                // 已过期
                $select->where('A.expiretime', '<=', date('Y-m-d H:i:s'));
            } elseif ($status == '1') {
                // 即将过期（30天内）
                $select->where('A.expiretime', '<=', date('Y-m-d H:i:s', time() + 86400 * 30))
                    ->where('A.expiretime', '>', date('Y-m-d H:i:s'));
            }
        }

        $total = $select->count();

        // 排序
        switch ($order) {
            case '1':
                $select->order('A.regtime', 'asc');
                break;
            case '2':
                $select->order('A.regtime', 'desc');
                break;
            case '3':
                $select->order('A.expiretime', 'asc');
                break;
            case '4':
                $select->order('A.expiretime', 'desc');
                break;
            default:
                $select->order('A.id', 'desc');
        }

        $offset = ($page - 1) * $pageSize;
        $rows = $select->fieldRaw('A.*,B.type,B.remark aremark')->limit($offset, $pageSize)->select()->toArray();

        // 添加类型信息
        foreach ($rows as &$row) {
            $row['typename'] = DnsHelper::$dns_config[$row['type']]['name'] ?? '未知';
            $row['icon'] = DnsHelper::$dns_config[$row['type']]['icon'] ?? '';
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取域名列表成功');
    }

    /**
     * 获取域名详情
     * GET /api/v1/domains/:id
     */
    public function domainDetail()
    {
        $id = $this->request->param('id', 0, 'intval');
        $domain = Db::name('domain')->alias('A')
            ->join('account B', 'A.aid = B.id')
            ->where('A.id', $id)
            ->fieldRaw('A.*,B.type,B.remark aremark')
            ->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限访问此域名');
            }
        }

        // 添加类型信息
        $domain['typename'] = DnsHelper::$dns_config[$domain['type']]['name'] ?? '未知';
        $domain['icon'] = DnsHelper::$dns_config[$domain['type']]['icon'] ?? '';

        return $this->success($domain, '获取域名详情成功');
    }

    /**
     * 添加域名
     * POST /api/v1/domains
     */
    public function domainCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可添加域名');
        }

        $aid = $this->request->post('aid', 0, 'intval');
        $method = $this->request->post('method', 0, 'intval'); // 0=导入已有域名, 1=创建新域名
        $name = $this->request->post('name', '', 'trim');
        $thirdid = $this->request->post('thirdid', '', 'trim');
        $recordcount = $this->request->post('recordcount', 0, 'intval');

        // 参数验证
        if (empty($name)) {
            return $this->validationError('域名名称不能为空');
        }

        if ($method == 0 && empty($thirdid)) {
            return $this->validationError('导入已有域名时，thirdid 不能为空');
        }

        // 检查域名是否已存在
        if (Db::name('domain')->where('aid', $aid)->where('name', $name)->find()) {
            return $this->error('域名已存在', null, 409);
        }

        try {
            if ($method == 1) {
                // 创建新域名
                $dns = DnsHelper::getModel($aid);
                if (!$dns) {
                    return $this->error('DNS 账户不存在或配置错误');
                }

                $result = $dns->addDomain($name);
                if (!$result) {
                    return $this->error('添加域名失败：' . $dns->getError());
                }

                $name = $result['name'];
                $thirdid = $result['id'];
            }

            $id = Db::name('domain')->insertGetId([
                'aid' => $aid,
                'name' => $name,
                'thirdid' => $thirdid,
                'addtime' => date('Y-m-d H:i:s'),
                'is_hide' => 0,
                'is_sso' => 1,
                'recordcount' => $recordcount,
            ]);

            $domain = Db::name('domain')->where('id', $id)->find();
            return $this->created($domain, '添加域名成功');
        } catch (Exception $e) {
            return $this->error('添加域名失败：' . $e->getMessage());
        }
    }

    /**
     * 更新域名配置
     * PUT /api/v1/domains/:id
     */
    public function domainUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可修改域名');
        }

        $id = $this->request->param('id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $id)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        $is_hide = $this->request->post('is_hide', null, 'intval');
        $is_sso = $this->request->post('is_sso', null, 'intval');
        $is_notice = $this->request->post('is_notice', null, 'intval');
        $expiretime = $this->request->post('expiretime', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        $updateData = [];
        if ($is_hide !== null) $updateData['is_hide'] = $is_hide;
        if ($is_sso !== null) $updateData['is_sso'] = $is_sso;
        if ($is_notice !== null) $updateData['is_notice'] = $is_notice;
        if (!empty($expiretime)) $updateData['expiretime'] = $expiretime;
        if ($remark !== null) $updateData['remark'] = $remark ?: null;

        Db::name('domain')->where('id', $id)->update($updateData);

        $domain = Db::name('domain')->where('id', $id)->find();
        return $this->success($domain, '修改域名配置成功');
    }

    /**
     * 删除域名
     * DELETE /api/v1/domains/:id
     */
    public function domainDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可删除域名');
        }

        $id = $this->request->param('id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $id)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        Db::startTrans();
        try {
            Db::name('domain')->where('id', $id)->delete();
            Db::name('domain_alias')->where('did', $id)->delete();
            Db::name('dmtask')->where('did', $id)->delete();
            Db::name('optimizeip')->where('did', $id)->delete();
            Db::name('sctask')->where('did', $id)->delete();

            Db::commit();
            return $this->success(null, '删除域名成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('删除域名失败：' . $e->getMessage());
        }
    }

    /**
     * 同步域名记录
     * POST /api/v1/domains/:id/sync
     */
    public function domainSync()
    {
        $id = $this->request->param('id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $id)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $result = $dns->getDomainRecords();
            if ($result === false) {
                return $this->error('同步失败：' . $dns->getError());
            }

            return $this->success([
                'count' => count($result),
                'records' => $result
            ], '同步域名记录成功');
        } catch (Exception $e) {
            return $this->error('同步失败：' . $e->getMessage());
        }
    }

    /**
     * 批量操作域名
     * POST /api/v1/domains/batch-operation
     */
    public function domainBatchOperation()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可进行此操作');
        }

        $action = $this->request->post('action', '', 'trim');
        $ids = $this->request->post('ids', []);
        
        if (empty($action) || empty($ids) || !is_array($ids)) {
            return $this->validationError('参数错误');
        }

        Db::startTrans();
        try {
            switch ($action) {
                case 'delete':
                    Db::name('domain')->where('id', 'in', $ids)->delete();
                    Db::name('domain_alias')->where('did', 'in', $ids)->delete();
                    Db::name('dmtask')->where('did', 'in', $ids)->delete();
                    Db::name('optimizeip')->where('did', 'in', $ids)->delete();
                    Db::name('sctask')->where('did', 'in', $ids)->delete();
                    break;
                case 'notice_on':
                    Db::name('domain')->where('id', 'in', $ids)->update(['is_notice' => 1]);
                    break;
                case 'notice_off':
                    Db::name('domain')->where('id', 'in', $ids)->update(['is_notice' => 0]);
                    break;
                case 'remark':
                    $remark = $this->request->post('remark', '', 'trim');
                    Db::name('domain')->where('id', 'in', $ids)->update(['remark' => $remark ?: null]);
                    break;
                default:
                    return $this->validationError('未知的操作指令');
            }
            
            Db::commit();
            return $this->success(null, '批量操作成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('批量操作失败：' . $e->getMessage());
        }
    }
}
