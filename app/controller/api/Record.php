<?php

declare(strict_types=1);

namespace app\controller\api;

use app\lib\DnsHelper;
use think\facade\Db;
use Exception;

/**
 * 域名记录管理 API 控制器
 */
class Record extends BaseController
{
    /**
     * 获取域名记录列表
     * GET /api/v1/domains/:domain_id/records
     */
    public function recordList()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $domainId)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限访问此域名');
            }
        }

        $rr = $this->request->get('rr', '', 'trim'); // 主机记录
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 100, 'intval');

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            // 获取记录列表
            if (!empty($rr)) {
                $result = $dns->getSubDomainRecords($rr, $page, $pageSize);
            } else {
                $result = $dns->getDomainRecords($page, $pageSize);
            }

            if ($result === false) {
                return $this->error('获取记录列表失败：' . $dns->getError());
            }

            // 获取解析线路信息
            $recordLine = $dns->getRecordLine();
            $list = [];
            foreach ($result['list'] as $row) {
                // 过滤 NS 和 SOA 记录
                if ($rr == '@' && ($row['Type'] == 'NS' || $row['Type'] == 'SOA')) {
                    continue;
                }
                $row['LineName'] = isset($recordLine[$row['Line']]) ? $recordLine[$row['Line']]['name'] : $row['Line'];
                $list[] = $row;
            }

            return $this->success([
                'items' => $list,
                'total' => $result['total'] ?? count($list),
                'page' => $page,
                'page_size' => $pageSize
            ], '获取记录列表成功');
        } catch (Exception $e) {
            return $this->error('获取记录列表失败：' . $e->getMessage());
        }
    }

    /**
     * 添加域名记录
     * POST /api/v1/domains/:domain_id/records
     */
    public function recordCreate()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $domainId)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        $name = $this->request->post('name', '', 'trim');
        $type = $this->request->post('type', '', 'trim');
        $value = $this->request->post('value', '', 'trim');
        $line = $this->request->post('line', 'default', 'trim');
        $ttl = $this->request->post('ttl', 600, 'intval');
        $weight = $this->request->post('weight', 0, 'intval');
        $mx = $this->request->post('mx', 1, 'intval');
        $remark = $this->request->post('remark', '', 'trim');

        // 参数验证
        if (empty($name) || empty($type) || empty($value)) {
            return $this->validationError('name、type、value 为必填参数');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $recordid = $dns->addDomainRecord($name, $type, $value, $line, $ttl, $mx, $weight, $remark);
            if (!$recordid) {
                return $this->error('添加解析记录失败：' . $dns->getError());
            }

            // 记录日志
            $this->addLog($domain['name'], '添加解析', $name . ' [' . $type . '] ' . $value . ' (线路:' . $line . ' TTL:' . $ttl . ')');

            return $this->created([
                'recordid' => $recordid,
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'line' => $line,
                'ttl' => $ttl
            ], '添加解析记录成功');
        } catch (Exception $e) {
            return $this->error('添加解析记录失败：' . $e->getMessage());
        }
    }

    /**
     * 更新域名记录
     * PUT /api/v1/domains/:domain_id/records/:id
     */
    public function recordUpdate()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $recordId = $this->request->param('id', '', 'trim');

        $domain = Db::name('domain')->where('id', $domainId)->find();
        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        $name = $this->request->post('name', '', 'trim');
        $type = $this->request->post('type', '', 'trim');
        $value = $this->request->post('value', '', 'trim');
        $line = $this->request->post('line', 'default', 'trim');
        $ttl = $this->request->post('ttl', 600, 'intval');
        $weight = $this->request->post('weight', 0, 'intval');
        $mx = $this->request->post('mx', 1, 'intval');
        $remark = $this->request->post('remark', '', 'trim');

        // 参数验证
        if (empty($name) || empty($type) || empty($value)) {
            return $this->validationError('name、type、value 为必填参数');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $result = $dns->updateDomainRecord($recordId, $name, $type, $value, $line, $ttl, $mx, $weight, $remark);
            if (!$result) {
                return $this->error('修改解析记录失败：' . $dns->getError());
            }

            // 记录日志
            $this->addLog($domain['name'], '修改解析', $name . ' [' . $type . '] ' . $value . ' (线路:' . $line . ' TTL:' . $ttl . ')');

            return $this->success([
                'recordid' => $recordId,
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'line' => $line,
                'ttl' => $ttl
            ], '修改解析记录成功');
        } catch (Exception $e) {
            return $this->error('修改解析记录失败：' . $e->getMessage());
        }
    }

    /**
     * 删除域名记录
     * DELETE /api/v1/domains/:domain_id/records/:id
     */
    public function recordDelete()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $recordId = $this->request->param('id', '', 'trim');

        $domain = Db::name('domain')->where('id', $domainId)->find();
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

            if (!$dns->deleteDomainRecord($recordId)) {
                return $this->error('删除解析记录失败：' . $dns->getError());
            }

            // 记录日志
            $this->addLog($domain['name'], '删除解析', '记录ID:' . $recordId);

            return $this->success(null, '删除解析记录成功');
        } catch (Exception $e) {
            return $this->error('删除解析记录失败：' . $e->getMessage());
        }
    }

    /**
     * 切换记录状态（启用/暂停）
     * PATCH /api/v1/domains/:domain_id/records/:id/status
     */
    public function recordToggleStatus()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $recordId = $this->request->param('id', '', 'trim');
        $status = $this->request->post('status', '', 'trim');

        $domain = Db::name('domain')->where('id', $domainId)->find();
        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        if (empty($status)) {
            return $this->validationError('status 参数不能为空');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            if (!$dns->setDomainRecordStatus($recordId, $status)) {
                return $this->error('切换记录状态失败：' . $dns->getError());
            }

            // 记录日志
            $statusText = $status == 'enable' ? '启用' : '暂停';
            $this->addLog($domain['name'], $statusText . '解析', '记录ID:' . $recordId);

            return $this->success(['status' => $status], '切换记录状态成功');
        } catch (Exception $e) {
            return $this->error('切换记录状态失败：' . $e->getMessage());
        }
    }

    /**
     * 批量添加记录
     * POST /api/v1/domains/:domain_id/records/batch
     */
    public function recordBatchCreate()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $domainId)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        $records = $this->request->post('records', []);
        if (empty($records) || !is_array($records)) {
            return $this->validationError('records 参数必须是数组且不能为空');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $successCount = 0;
            $failedList = [];

            foreach ($records as $record) {
                $name = $record['name'] ?? '';
                $type = $record['type'] ?? '';
                $value = $record['value'] ?? '';
                $line = $record['line'] ?? 'default';
                $ttl = $record['ttl'] ?? 600;
                $mx = $record['mx'] ?? 1;
                $weight = $record['weight'] ?? 0;
                $remark = $record['remark'] ?? '';

                if (empty($name) || empty($type) || empty($value)) {
                    $failedList[] = ['record' => $record, 'error' => '必填参数不能为空'];
                    continue;
                }

                $recordid = $dns->addDomainRecord($name, $type, $value, $line, $ttl, $mx, $weight, $remark);
                if ($recordid) {
                    $successCount++;
                } else {
                    $failedList[] = ['record' => $record, 'error' => $dns->getError()];
                }
            }

            // 记录日志
            $this->addLog($domain['name'], '批量添加解析', '成功:' . $successCount . ' 失败:' . count($failedList));

            return $this->success([
                'success_count' => $successCount,
                'failed_count' => count($failedList),
                'failed_list' => $failedList
            ], '批量添加完成');
        } catch (Exception $e) {
            return $this->error('批量添加失败：' . $e->getMessage());
        }
    }

    /**
     * 批量操作记录（启用/暂停/删除/修改备注）
     * POST /api/v1/domains/:domain_id/records/batch-operation
     */
    public function recordBatchOperation()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $domainId)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        $action = $this->request->post('action', '', 'trim'); // open/pause/delete/remark
        $records = $this->request->post('records', []);

        if (empty($action) || empty($records) || !is_array($records)) {
            return $this->validationError('action 和 records 参数不能为空');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $successCount = 0;
            $failedCount = 0;

            switch ($action) {
                case 'open': // 批量启用
                    foreach ($records as $record) {
                        $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                        if (empty($recordId)) continue;

                        if ($dns->setDomainRecordStatus($recordId, '1')) {
                            $successCount++;
                            $this->addLog($domain['name'], '启用解析', $this->formatRecordLog($record));
                        } else {
                            $failedCount++;
                        }
                    }
                    $message = "成功启用 {$successCount} 条解析记录";
                    break;

                case 'pause': // 批量暂停
                    foreach ($records as $record) {
                        $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                        if (empty($recordId)) continue;

                        if ($dns->setDomainRecordStatus($recordId, '0')) {
                            $successCount++;
                            $this->addLog($domain['name'], '暂停解析', $this->formatRecordLog($record));
                        } else {
                            $failedCount++;
                        }
                    }
                    $message = "成功暂停 {$successCount} 条解析记录";
                    break;

                case 'delete': // 批量删除
                    foreach ($records as $record) {
                        $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                        if (empty($recordId)) continue;

                        if ($dns->deleteDomainRecord($recordId)) {
                            $successCount++;
                            $this->addLog($domain['name'], '删除解析', $this->formatRecordLog($record));
                        } else {
                            $failedCount++;
                        }
                    }
                    $message = "成功删除 {$successCount} 条解析记录";
                    break;

                case 'remark': // 批量修改备注
                    $remark = $this->request->post('remark', '', 'trim');
                    foreach ($records as $record) {
                        $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                        if (empty($recordId)) continue;

                        if ($dns->updateDomainRecordRemark($recordId, $remark ?: null)) {
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    }
                    $message = "批量修改备注，成功 {$successCount} 条，失败 {$failedCount} 条";
                    break;

                default:
                    return $this->validationError('不支持的操作类型');
            }

            return $this->success([
                'success_count' => $successCount,
                'failed_count' => $failedCount
            ], $message);
        } catch (Exception $e) {
            return $this->error('批量操作失败：' . $e->getMessage());
        }
    }

    /**
     * 批量修改记录（修改类型/值）
     * POST /api/v1/domains/:domain_id/records/batch-update
     */
    public function recordBatchUpdate()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $domain = Db::name('domain')->where('id', $domainId)->find();

        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限操作此域名');
            }
        }

        $action = $this->request->post('action', '', 'trim'); // value/line/ttl
        $records = $this->request->post('records', []);

        if (empty($action) || empty($records) || !is_array($records)) {
            return $this->validationError('action 和 records 参数不能为空');
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $successCount = 0;
            $failedCount = 0;

            if ($action == 'value') {
                // 批量修改记录类型和值
                $type = $this->request->post('type', '', 'trim');
                $value = $this->request->post('value', '', 'trim');

                if (empty($type) || empty($value)) {
                    return $this->validationError('type 和 value 参数不能为空');
                }

                foreach ($records as $record) {
                    $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                    $name = $record['Name'] ?? $record['name'] ?? '';
                    $line = $record['Line'] ?? $record['line'] ?? 'default';
                    $ttl = $record['TTL'] ?? $record['ttl'] ?? 600;
                    $mx = $record['MX'] ?? $record['mx'] ?? 1;
                    $weight = $record['Weight'] ?? $record['weight'] ?? 0;
                    $remark = $record['Remark'] ?? $record['remark'] ?? '';

                    if (empty($recordId) || empty($name)) continue;

                    if ($dns->updateDomainRecord($recordId, $name, $type, $value, $line, $ttl, $mx, $weight, $remark)) {
                        $successCount++;
                        $this->addLog($domain['name'], '批量修改解析', "{$name} [{$type}] {$value}");
                    } else {
                        $failedCount++;
                    }
                }
            } elseif ($action == 'line') {
                // 批量修改解析线路
                $line = $this->request->post('line', '', 'trim');
                if (empty($line)) {
                    return $this->validationError('line 参数不能为空');
                }

                foreach ($records as $record) {
                    $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                    $name = $record['Name'] ?? $record['name'] ?? '';
                    $type = $record['Type'] ?? $record['type'] ?? '';
                    $value = is_array($record['Value'] ?? '') ? implode(',', $record['Value']) : ($record['Value'] ?? $record['value'] ?? '');
                    $ttl = $record['TTL'] ?? $record['ttl'] ?? 600;
                    $mx = $record['MX'] ?? $record['mx'] ?? 1;
                    $weight = $record['Weight'] ?? $record['weight'] ?? 0;
                    $remark = $record['Remark'] ?? $record['remark'] ?? '';

                    if (empty($recordId) || empty($name) || empty($type) || empty($value)) continue;

                    if ($dns->updateDomainRecord($recordId, $name, $type, $value, $line, $ttl, $mx, $weight, $remark)) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                }
            } elseif ($action == 'ttl') {
                // 批量修改 TTL
                $ttl = $this->request->post('ttl', 0, 'intval');
                if ($ttl <= 0) {
                    return $this->validationError('ttl 参数必须大于 0');
                }

                foreach ($records as $record) {
                    $recordId = $record['RecordId'] ?? $record['recordid'] ?? '';
                    $name = $record['Name'] ?? $record['name'] ?? '';
                    $type = $record['Type'] ?? $record['type'] ?? '';
                    $value = is_array($record['Value'] ?? '') ? implode(',', $record['Value']) : ($record['Value'] ?? $record['value'] ?? '');
                    $line = $record['Line'] ?? $record['line'] ?? 'default';
                    $mx = $record['MX'] ?? $record['mx'] ?? 1;
                    $weight = $record['Weight'] ?? $record['weight'] ?? 0;
                    $remark = $record['Remark'] ?? $record['remark'] ?? '';

                    if (empty($recordId) || empty($name) || empty($type) || empty($value)) continue;

                    if ($dns->updateDomainRecord($recordId, $name, $type, $value, $line, $ttl, $mx, $weight, $remark)) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                }
            } else {
                return $this->validationError('不支持的操作类型');
            }

            $this->addLog($domain['name'], '批量修改解析', "成功:{$successCount} 失败:{$failedCount}");

            return $this->success([
                'success_count' => $successCount,
                'failed_count' => $failedCount
            ], "批量修改完成，成功 {$successCount} 条，失败 {$failedCount} 条");
        } catch (Exception $e) {
            return $this->error('批量修改失败：' . $e->getMessage());
        }
    }

    /**
     * 格式化记录日志
     */
    private function formatRecordLog(array $record): string
    {
        $name = $record['Name'] ?? $record['name'] ?? '';
        $type = $record['Type'] ?? $record['type'] ?? '';
        $value = $record['Value'] ?? $record['value'] ?? '';
        $line = $record['Line'] ?? $record['line'] ?? '';
        $ttl = $record['TTL'] ?? $record['ttl'] ?? '';

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return "{$name} [{$type}] {$value} (线路:{$line} TTL:{$ttl})";
    }

    /**
     * 获取记录详情
     * GET /api/v1/domains/:domain_id/records/:id
     */
    public function recordDetail()
    {
        $domainId = $this->request->param('domain_id', 0, 'intval');
        $recordId = $this->request->param('id', '', 'trim');

        $domain = Db::name('domain')->where('id', $domainId)->find();
        if (!$domain) {
            return $this->notFound('域名不存在');
        }

        // 权限检查
        if ($this->request->user['level'] == 1) {
            if (!in_array($domain['name'], $this->request->user['permission'])) {
                return $this->forbidden('无权限访问此域名');
            }
        }

        try {
            $dns = DnsHelper::getModel($domain['aid'], $domain['name'], $domain['thirdid']);
            if (!$dns) {
                return $this->error('DNS 账户不存在或配置错误');
            }

            $record = $dns->getDomainRecordInfo($recordId);
            if (!$record) {
                return $this->notFound('记录不存在');
            }

            return $this->success($record, '获取记录详情成功');
        } catch (Exception $e) {
            return $this->error('获取记录详情失败：' . $e->getMessage());
        }
    }

    /**
     * 添加操作日志
     */
    private function addLog(string $domain, string $action, string $data): void
    {
        if (strlen($data) > 500) {
            $data = substr($data, 0, 500);
        }

        Db::name('log')->insert([
            'uid' => $this->request->user['id'],
            'domain' => $domain,
            'action' => $action,
            'data' => $data,
            'addtime' => date("Y-m-d H:i:s")
        ]);
    }
}
