<?php

declare(strict_types=1);

namespace app\controller\api;

use app\lib\CertHelper;
use app\lib\DeployHelper;
use app\service\CertOrderService;
use app\service\CertDeployService;
use think\facade\Db;
use Exception;

/**
 * 证书管理 API 控制器
 */
class Certificate extends BaseController
{
    // ==================== 证书账户管理 ====================

    /**
     * 获取证书账户列表
     * GET /api/v1/cert-accounts/list
     */
    public function certAccountList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('cert_account')->where('deploy', 0);
        if (!empty($kw)) {
            $select->whereLike('name|remark', '%' . $kw . '%')->whereOr('id', $kw);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        foreach ($rows as &$row) {
            if (!empty($row['type']) && isset(CertHelper::$cert_config[$row['type']])) {
                $row['typename'] = CertHelper::$cert_config[$row['type']]['name'];
                $row['icon'] = CertHelper::$cert_config[$row['type']]['icon'];
            }
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取证书账户列表成功');
    }

    /**
     * 创建证书账户
     * POST /api/v1/cert-accounts/create
     */
    public function certAccountCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if (empty($name) || empty($config)) {
            return $this->validationError('name 和 config 为必填参数');
        }

        if (Db::name('cert_account')->where('type', $type)->where('config', $config)->find()) {
            return $this->error('SSL证书账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            $id = Db::name('cert_account')->insertGetId([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
                'deploy' => 0,
                'addtime' => date('Y-m-d H:i:s'),
            ]);

            // 验证账户
            $mod = CertHelper::getModel($id);
            if (!$mod) {
                throw new Exception('SSL证书申请模块 ' . $type . ' 不存在');
            }

            $ext = $mod->register();
            if (is_array($ext)) {
                Db::name('cert_account')->where('id', $id)->update(['ext' => json_encode($ext)]);
            }

            Db::commit();

            $account = Db::name('cert_account')->where('id', $id)->find();
            return $this->created($account, '添加SSL证书账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('验证SSL证书账户失败：' . $e->getMessage());
        }
    }

    /**
     * 更新证书账户
     * POST /api/v1/cert-accounts/:id/update
     */
    public function certAccountUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('cert_account')->where('id', $id)->where('deploy', 0)->find();

        if (!$account) {
            return $this->notFound('SSL证书账户不存在');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if (empty($name) || empty($config)) {
            return $this->validationError('name 和 config 为必填参数');
        }

        if (Db::name('cert_account')->where('type', $type)->where('config', $config)->where('id', '<>', $id)->find()) {
            return $this->error('SSL证书账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            Db::name('cert_account')->where('id', $id)->update([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
            ]);

            // 验证账户
            $mod = CertHelper::getModel($id);
            if (!$mod) {
                throw new Exception('SSL证书申请模块 ' . $type . ' 不存在');
            }

            $ext = $mod->register();
            if (is_array($ext)) {
                Db::name('cert_account')->where('id', $id)->update(['ext' => json_encode($ext)]);
            }

            Db::commit();

            $account = Db::name('cert_account')->where('id', $id)->find();
            return $this->success($account, '修改SSL证书账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('验证SSL证书账户失败：' . $e->getMessage());
        }
    }

    /**
     * 删除证书账户
     * POST /api/v1/cert-accounts/:id/delete
     */
    public function certAccountDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('cert_account')->where('id', $id)->where('deploy', 0)->find();

        if (!$account) {
            return $this->notFound('SSL证书账户不存在');
        }

        $dcount = Db::name('cert_order')->where('aid', $id)->count();
        if ($dcount > 0) {
            return $this->error('该SSL证书账户下存在 ' . $dcount . ' 个证书订单，无法删除', null, 409);
        }

        Db::name('cert_account')->where('id', $id)->delete();
        return $this->success(null, '删除SSL证书账户成功');
    }

    // ==================== 证书订单管理 ====================

    /**
     * 获取证书订单列表
     * GET /api/v1/certificates/list
     */
    public function certificateList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $domain = $this->request->get('domain', '', 'trim');
        $aid = $this->request->get('aid', '', 'trim');
        $type = $this->request->get('type', '', 'trim');
        $status = $this->request->get('status', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('cert_order')->alias('A')->leftJoin('cert_account B', 'A.aid = B.id');

        // 按域名搜索
        if (!empty($domain)) {
            $oids = Db::name('cert_domain')->where('domain', 'like', '%' . $domain . '%')->column('oid');
            $select->whereIn('A.id', $oids);
        }

        // 按账户筛选
        if (!empty($aid)) {
            $select->where('A.aid', $aid);
        }

        // 按类型筛选
        if (!empty($type)) {
            $select->where('B.type', $type);
        }

        // 按状态筛选
        if ($status !== '') {
            if ($status == '5') {
                // 失败状态
                $select->where('A.status', '<', 0);
            } elseif ($status == '6') {
                // 即将过期（7天内）
                $select->where('A.expiretime', '<', date('Y-m-d H:i:s', time() + 86400 * 7))
                    ->where('A.expiretime', '>=', date('Y-m-d H:i:s'));
            } elseif ($status == '7') {
                // 已过期
                $select->where('A.expiretime', '<', date('Y-m-d H:i:s'));
            } else {
                $select->where('A.status', $status);
            }
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->fieldRaw('A.*,B.type,B.remark aremark')
            ->order('A.id', 'desc')
            ->limit($offset, $pageSize)
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            if (!empty($row['type']) && isset(CertHelper::$cert_config[$row['type']])) {
                $row['typename'] = CertHelper::$cert_config[$row['type']]['name'];
                $row['icon'] = CertHelper::$cert_config[$row['type']]['icon'];
            } else {
                $row['typename'] = null;
            }
            $row['domains'] = Db::name('cert_domain')->where('oid', $row['id'])->order('sort', 'ASC')->column('domain');
            $row['end_day'] = $row['expiretime'] ? ceil((strtotime($row['expiretime']) - time()) / 86400) : null;
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取证书列表成功');
    }

    /**
     * 获取证书详情
     * GET /api/v1/certificates/:id/detail
     */
    public function certificateDetail()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $certificate = Db::name('cert_order')->where('id', $id)->find();

        if (!$certificate) {
            return $this->notFound('证书订单不存在');
        }

        $pfx = CertHelper::getPfx($certificate['fullchain'], $certificate['privatekey']);
        $certificate['pfx'] = base64_encode($pfx);
        $certificate['domains'] = Db::name('cert_domain')->where('oid', $id)->order('sort', 'ASC')->column('domain');

        return $this->success($certificate, '获取证书详情成功');
    }

    /**
     * 申请证书
     * POST /api/v1/certificates/create
     */
    public function certificateCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $aid = $this->request->post('aid', 0, 'intval');
        $keytype = $this->request->post('keytype', '', 'trim');
        $keysize = $this->request->post('keysize', '', 'trim');
        $domains = $this->request->post('domains', []);

        if ($aid == -1) {
            // 导入已有证书
            $fullchain = $this->request->post('fullchain', '', 'trim');
            $privatekey = $this->request->post('privatekey', '', 'trim');

            if (empty($fullchain) || empty($privatekey)) {
                return $this->validationError('fullchain 和 privatekey 为必填参数');
            }

            try {
                $certInfo = $this->parseCertKey($fullchain, $privatekey);
                $domains = $certInfo['domains'];

                // 检查证书是否已存在
                $order_ids = Db::name('cert_order')->where('issuetime', $certInfo['issuetime'])->column('id');
                if (!empty($order_ids)) {
                    foreach ($order_ids as $order_id) {
                        $domains2 = Db::name('cert_domain')->where('oid', $order_id)->column('domain');
                        if ($this->arraysAreEqual($domains2, $domains)) {
                            return $this->error('该证书已存在，无需重复添加', null, 409);
                        }
                    }
                }

                $order = [
                    'aid' => 0,
                    'keytype' => $certInfo['keytype'],
                    'keysize' => $certInfo['keysize'],
                    'addtime' => date('Y-m-d H:i:s'),
                    'updatetime' => date('Y-m-d H:i:s'),
                    'issuetime' => $certInfo['issuetime'],
                    'expiretime' => $certInfo['expiretime'],
                    'issuer' => $certInfo['issuer'],
                    'status' => 3,
                    'isauto' => 1,
                    'fullchain' => $fullchain,
                    'privatekey' => $privatekey,
                ];
            } catch (Exception $e) {
                return $this->error('解析证书失败：' . $e->getMessage());
            }
        } else {
            // 申请新证书
            if (empty($keytype) || empty($keysize) || empty($domains)) {
                return $this->validationError('keytype、keysize、domains 为必填参数');
            }

            $domains = array_map('trim', $domains);
            $domains = array_filter($domains, function ($v) {
                return !empty($v);
            });
            $domains = array_unique($domains);

            if (empty($domains)) {
                return $this->validationError('绑定域名不能为空');
            }

            $order = [
                'aid' => $aid,
                'keytype' => $keytype,
                'keysize' => $keysize,
                'addtime' => date('Y-m-d H:i:s'),
                'issuer' => '',
                'status' => 0,
                'isauto' => 1,
            ];
        }

        Db::startTrans();
        try {
            $id = Db::name('cert_order')->insertGetId($order);

            $domainList = [];
            $i = 1;
            foreach ($domains as $domain) {
                $domainList[] = [
                    'oid' => $id,
                    'domain' => $domain,
                    'sort' => $i++,
                ];
            }
            Db::name('cert_domain')->insertAll($domainList);

            Db::commit();

            $certificate = Db::name('cert_order')->where('id', $id)->find();
            $certificate['domains'] = $domains;

            return $this->created($certificate, '添加证书订单成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('添加证书订单失败：' . $e->getMessage());
        }
    }

    /**
     * 删除证书
     * POST /api/v1/certificates/:id/delete
     */
    public function certificateDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $certificate = Db::name('cert_order')->where('id', $id)->find();

        if (!$certificate) {
            return $this->notFound('证书订单不存在');
        }

        Db::startTrans();
        try {
            Db::name('cert_order')->where('id', $id)->delete();
            Db::name('cert_domain')->where('oid', $id)->delete();
            Db::name('cert_deploy')->where('oid', $id)->delete();

            Db::commit();
            return $this->success(null, '删除证书订单成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('删除证书订单失败：' . $e->getMessage());
        }
    }

    /**
     * 部署证书
     * POST /api/v1/certificates/:id/deploy
     */
    public function certificateDeploy()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $certificate = Db::name('cert_order')->where('id', $id)->find();

        if (!$certificate) {
            return $this->notFound('证书订单不存在');
        }

        if ($certificate['status'] != 3) {
            return $this->error('证书未签发，无法部署');
        }

        $deployAccounts = $this->request->post('deploy_accounts', []);
        if (empty($deployAccounts) || !is_array($deployAccounts)) {
            return $this->validationError('deploy_accounts 参数必须是数组且不能为空');
        }

        try {
            $results = [];

            foreach ($deployAccounts as $aid) {
                $client = DeployHelper::getModel($aid);
                if (!$client) {
                    $results[] = [
                        'aid' => $aid,
                        'success' => false,
                        'message' => '部署模块不存在'
                    ];
                    continue;
                }
                
                try {
                    // 构造一个空的 config 和 info
                    $account = Db::name('cert_account')->where('id', $aid)->find();
                    $config = $account ? json_decode($account['config'], true) : [];
                    $config['domainList'] = Db::name('cert_domain')->where('oid', $id)->order('sort', 'asc')->column('domain');
                    
                    $client->deploy($certificate['fullchain'], $certificate['privatekey'], $config, []);
                    $results[] = [
                        'aid' => $aid,
                        'success' => true,
                        'message' => '部署成功'
                    ];
                } catch (Exception $ex) {
                    $results[] = [
                        'aid' => $aid,
                        'success' => false,
                        'message' => $ex->getMessage()
                    ];
                }
            }

            return $this->success($results, '证书部署完成');
        } catch (Exception $e) {
            return $this->error('证书部署失败：' . $e->getMessage());
        }
    }

    /**
     * 获取证书处理进度
     * GET /api/v1/certificates/:id/process
     */
    public function certificateProcess()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $certificate = Db::name('cert_order')->where('id', $id)->find();

        if (!$certificate) {
            return $this->notFound('证书订单不存在');
        }

        $process = [
            'id' => $certificate['id'],
            'status' => $certificate['status'],
            'error' => $certificate['error'],
            'updatetime' => $certificate['updatetime'],
        ];

        return $this->success($process, '获取证书处理进度成功');
    }

    // ==================== 辅助方法 ====================

    /**
     * 解析证书和私钥
     */
    private function parseCertKey(string $fullchain, string $privatekey): array
    {
        $cert = openssl_x509_read($fullchain);
        if (!$cert) {
            throw new Exception('证书格式错误');
        }

        $certInfo = openssl_x509_parse($cert);
        if (!$certInfo) {
            throw new Exception('解析证书失败');
        }

        // 获取域名列表
        $domains = [];
        if (isset($certInfo['subject']['CN'])) {
            $domains[] = $certInfo['subject']['CN'];
        }
        if (isset($certInfo['extensions']['subjectAltName'])) {
            $altNames = explode(',', $certInfo['extensions']['subjectAltName']);
            foreach ($altNames as $altName) {
                $altName = trim(str_replace('DNS:', '', $altName));
                if (!in_array($altName, $domains)) {
                    $domains[] = $altName;
                }
            }
        }

        // 获取密钥类型和大小
        $key = openssl_pkey_get_private($privatekey);
        if (!$key) {
            throw new Exception('私钥格式错误');
        }
        $keyDetails = openssl_pkey_get_details($key);

        return [
            'domains' => $domains,
            'keytype' => $keyDetails['type'] == OPENSSL_KEYTYPE_RSA ? 'RSA' : 'EC',
            'keysize' => $keyDetails['bits'] ?? 0,
            'issuetime' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'expiretime' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            'issuer' => $certInfo['issuer']['CN'] ?? '',
        ];
    }

    /**
     * 比较两个数组是否相等
     */
    private function arraysAreEqual(array $arr1, array $arr2): bool
    {
        sort($arr1);
        sort($arr2);
        return $arr1 === $arr2;
    }

    // ==================== 部署账户管理 ====================

    /**
     * 获取部署账户列表
     * GET /api/v1/deploy-accounts/list
     */
    public function deployAccountList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('cert_account')->where('deploy', 1);
        if (!empty($kw)) {
            $select->whereLike('name|remark', '%' . $kw . '%')->whereOr('id', $kw);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->order('id', 'desc')->limit($offset, $pageSize)->select()->toArray();

        foreach ($rows as &$row) {
            if (!empty($row['type']) && isset(DeployHelper::$deploy_config[$row['type']])) {
                $row['typename'] = DeployHelper::$deploy_config[$row['type']]['name'];
                $row['icon'] = DeployHelper::$deploy_config[$row['type']]['icon'];
            }
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取部署账户列表成功');
    }

    /**
     * 创建部署账户
     * POST /api/v1/deploy-accounts/create
     */
    public function deployAccountCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if ($type == 'local') {
            $name = '复制到本机';
        }

        if (empty($name) || empty($config)) {
            return $this->validationError('name 和 config 为必填参数');
        }

        if (Db::name('cert_account')->where('type', $type)->where('config', $config)->find()) {
            return $this->error('自动部署账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            $id = Db::name('cert_account')->insertGetId([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
                'deploy' => 1,
                'addtime' => date('Y-m-d H:i:s'),
            ]);

            // 验证账户
            $mod = DeployHelper::getModel($id);
            if (!$mod) {
                throw new Exception('自动部署模块 ' . $type . ' 不存在');
            }

            $mod->check();

            Db::commit();

            $account = Db::name('cert_account')->where('id', $id)->find();
            return $this->created($account, '添加自动部署账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('验证自动部署账户失败：' . $e->getMessage());
        }
    }

    /**
     * 更新部署账户
     * POST /api/v1/deploy-accounts/:id/update
     */
    public function deployAccountUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('cert_account')->where('id', $id)->where('deploy', 1)->find();

        if (!$account) {
            return $this->notFound('自动部署账户不存在');
        }

        $type = $this->request->post('type', '', 'trim');
        $name = $this->request->post('name', '', 'trim');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if ($type == 'local') {
            $name = '复制到本机';
        }

        if (empty($name) || empty($config)) {
            return $this->validationError('name 和 config 为必填参数');
        }

        if (Db::name('cert_account')->where('type', $type)->where('config', $config)->where('id', '<>', $id)->find()) {
            return $this->error('自动部署账户已存在', null, 409);
        }

        Db::startTrans();
        try {
            Db::name('cert_account')->where('id', $id)->update([
                'type' => $type,
                'name' => $name,
                'config' => $config,
                'remark' => $remark,
            ]);

            // 验证账户
            $mod = DeployHelper::getModel($id);
            if (!$mod) {
                throw new Exception('自动部署模块 ' . $type . ' 不存在');
            }

            $mod->check();

            Db::commit();

            $account = Db::name('cert_account')->where('id', $id)->find();
            return $this->success($account, '修改自动部署账户成功');
        } catch (Exception $e) {
            Db::rollback();
            return $this->error('验证自动部署账户失败：' . $e->getMessage());
        }
    }

    /**
     * 删除部署账户
     * POST /api/v1/deploy-accounts/:id/delete
     */
    public function deployAccountDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $account = Db::name('cert_account')->where('id', $id)->where('deploy', 1)->find();

        if (!$account) {
            return $this->notFound('自动部署账户不存在');
        }

        $dcount = Db::name('cert_deploy')->where('aid', $id)->count();
        if ($dcount > 0) {
            return $this->error('该自动部署账户下存在 ' . $dcount . ' 个自动部署任务，无法删除', null, 409);
        }

        Db::name('cert_account')->where('id', $id)->delete();
        return $this->success(null, '删除自动部署账户成功');
    }

    // ==================== 部署任务管理 ====================

    /**
     * 获取部署任务列表
     * GET /api/v1/deploy-tasks/list
     */
    public function deployTaskList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $domain = $this->request->get('domain', '', 'trim');
        $oid = $this->request->get('oid', '', 'trim');
        $aid = $this->request->get('aid', '', 'trim');
        $type = $this->request->get('type', '', 'trim');
        $status = $this->request->get('status', '', 'trim');
        $remark = $this->request->get('remark', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('cert_deploy')->alias('A')
            ->leftJoin('cert_account B', 'A.aid = B.id')
            ->leftJoin('cert_order C', 'A.oid = C.id')
            ->leftJoin('cert_account D', 'C.aid = D.id');

        // 按域名搜索
        if (!empty($domain)) {
            $oids = Db::name('cert_domain')->where('domain', 'like', '%' . $domain . '%')->column('oid');
            $select->whereIn('A.oid', $oids);
        }

        // 按证书订单筛选
        if (!empty($oid)) {
            $select->where('A.oid', $oid);
        }

        // 按部署账户筛选
        if (!empty($aid)) {
            $select->where('A.aid', $aid);
        }

        // 按类型筛选
        if (!empty($type)) {
            $select->where('B.type', $type);
        }

        // 按状态筛选
        if ($status !== '') {
            $select->where('A.status', $status);
        }

        // 按备注筛选
        if (!empty($remark)) {
            $select->where('A.remark', $remark);
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->fieldRaw('A.*,B.type,B.remark aremark,B.name aname,D.type certtype,D.id certaid')
            ->order('A.id', 'desc')
            ->limit($offset, $pageSize)
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            if (!empty($row['type']) && isset(DeployHelper::$deploy_config[$row['type']])) {
                $row['typename'] = DeployHelper::$deploy_config[$row['type']]['name'];
                $row['icon'] = DeployHelper::$deploy_config[$row['type']]['icon'];
            }
            if (!empty($row['certtype']) && isset(CertHelper::$cert_config[$row['certtype']])) {
                $row['certtypename'] = CertHelper::$cert_config[$row['certtype']]['name'];
            } else {
                $row['certtypename'] = '手动续期';
            }
            $row['domains'] = Db::name('cert_domain')->where('oid', $row['oid'])->order('sort', 'ASC')->column('domain');
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取部署任务列表成功');
    }

    /**
     * 创建部署任务
     * POST /api/v1/deploy-tasks/create
     */
    public function deployTaskCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $aid = $this->request->post('aid', 0, 'intval');
        $oid = $this->request->post('oid', 0, 'intval');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if (empty($aid) || empty($oid) || empty($config)) {
            return $this->validationError('aid、oid、config 为必填参数');
        }

        $task = [
            'aid' => $aid,
            'oid' => $oid,
            'config' => $config,
            'remark' => $remark,
            'addtime' => date('Y-m-d H:i:s'),
            'status' => 0,
            'active' => 1
        ];

        $id = Db::name('cert_deploy')->insertGetId($task);
        $task['id'] = $id;

        return $this->created($task, '添加自动部署任务成功');
    }

    /**
     * 更新部署任务
     * POST /api/v1/deploy-tasks/:id/update
     */
    public function deployTaskUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('cert_deploy')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        $aid = $this->request->post('aid', 0, 'intval');
        $oid = $this->request->post('oid', 0, 'intval');
        $config = $this->request->post('config', '', 'trim');
        $remark = $this->request->post('remark', '', 'trim');

        if (empty($aid) || empty($oid) || empty($config)) {
            return $this->validationError('aid、oid、config 为必填参数');
        }

        Db::name('cert_deploy')->where('id', $id)->update([
            'aid' => $aid,
            'oid' => $oid,
            'config' => $config,
            'remark' => $remark,
        ]);

        $task = Db::name('cert_deploy')->where('id', $id)->find();
        return $this->success($task, '修改自动部署任务成功');
    }

    /**
     * 删除部署任务
     * POST /api/v1/deploy-tasks/:id/delete
     */
    public function deployTaskDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('cert_deploy')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        Db::name('cert_deploy')->where('id', $id)->delete();
        return $this->success(null, '删除自动部署任务成功');
    }

    /**
     * 切换部署任务状态
     * POST /api/v1/deploy-tasks/:id/active
     */
    public function deployTaskToggleActive()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $active = $this->request->post('active', 0, 'intval');

        $task = Db::name('cert_deploy')->where('id', $id)->find();
        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        Db::name('cert_deploy')->where('id', $id)->update(['active' => $active]);
        return $this->success(['active' => $active], '切换任务状态成功');
    }

    /**
     * 重置部署任务
     * POST /api/v1/deploy-tasks/:id/reset
     */
    public function deployTaskReset()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('cert_deploy')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        try {
            $service = new CertDeployService($id);
            $service->reset();
            return $this->success(null, '重置部署任务成功');
        } catch (Exception $e) {
            return $this->error('重置部署任务失败：' . $e->getMessage());
        }
    }

    /**
     * 执行部署任务
     * POST /api/v1/deploy-tasks/:id/execute
     */
    public function deployTaskExecute()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('cert_deploy')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        try {
            $service = new CertDeployService($id);
            $service->process(true);
            return $this->success(null, 'SSL证书部署任务执行成功');
        } catch (Exception $e) {
            return $this->error('SSL证书部署任务执行失败：' . $e->getMessage());
        }
    }

    /**
     * 获取部署任务进度
     * GET /api/v1/deploy-tasks/:id/process
     */
    public function deployTaskProcess()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $task = Db::name('cert_deploy')->where('id', $id)->find();

        if (!$task) {
            return $this->notFound('自动部署任务不存在');
        }

        $process = [
            'id' => $task['id'],
            'status' => $task['status'],
            'error' => $task['error'],
            'updatetime' => $task['updatetime'],
        ];

        return $this->success($process, '获取部署任务进度成功');
    }

    // ==================== CNAME代理管理 ====================

    /**
     * 获取CNAME代理列表
     * GET /api/v1/cert-cnames/list
     */
    public function cnameList()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $kw = $this->request->get('kw', '', 'trim');
        $page = $this->request->get('page', 1, 'intval');
        $pageSize = $this->request->get('page_size', 10, 'intval');

        $select = Db::name('cert_cname')->alias('A')->leftJoin('domain B', 'A.did = B.id');
        if (!empty($kw)) {
            $select->whereLike('A.domain', '%' . $kw . '%');
        }

        $total = $select->count();
        $offset = ($page - 1) * $pageSize;
        $rows = $select->field('A.*,B.name cnamedomain')
            ->order('A.id', 'desc')
            ->limit($offset, $pageSize)
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['host'] = $this->getCnameHost($row['domain']);
            $row['record'] = $row['rr'] . '.' . $row['cnamedomain'];
        }

        return $this->paginate($rows, $total, $page, $pageSize, '获取CNAME代理列表成功');
    }

    /**
     * 创建CNAME代理
     * POST /api/v1/cert-cnames/create
     */
    public function cnameCreate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $domain = $this->request->post('domain', '', 'trim');
        $rr = $this->request->post('rr', '', 'trim');
        $did = $this->request->post('did', 0, 'intval');

        if (empty($domain) || empty($rr) || empty($did)) {
            return $this->validationError('domain、rr、did 为必填参数');
        }

        if (Db::name('cert_cname')->where('domain', $domain)->find()) {
            return $this->error('域名 ' . $domain . ' 已存在', null, 409);
        }

        if (Db::name('cert_cname')->where('rr', $rr)->where('did', $did)->find()) {
            return $this->error('已存在相同CNAME记录值', null, 409);
        }

        $data = [
            'domain' => $domain,
            'rr' => $rr,
            'did' => $did,
            'addtime' => date('Y-m-d H:i:s'),
            'status' => 0
        ];

        $id = Db::name('cert_cname')->insertGetId($data);
        $data['id'] = $id;

        return $this->created($data, '添加CNAME代理成功');
    }

    /**
     * 更新CNAME代理
     * POST /api/v1/cert-cnames/:id/update
     */
    public function cnameUpdate()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $cname = Db::name('cert_cname')->where('id', $id)->find();

        if (!$cname) {
            return $this->notFound('CNAME代理不存在');
        }

        $rr = $this->request->post('rr', '', 'trim');
        $did = $this->request->post('did', 0, 'intval');

        if (empty($rr) || empty($did)) {
            return $this->validationError('rr、did 为必填参数');
        }

        if (Db::name('cert_cname')->where('rr', $rr)->where('did', $did)->where('id', '<>', $id)->find()) {
            return $this->error('已存在相同CNAME记录值', null, 409);
        }

        $data = ['rr' => $rr, 'did' => $did];
        if ($cname['rr'] != $rr || $cname['did'] != $did) {
            $data['status'] = 0;
        }

        Db::name('cert_cname')->where('id', $id)->update($data);

        $cname = Db::name('cert_cname')->where('id', $id)->find();
        return $this->success($cname, '修改CNAME代理成功');
    }

    /**
     * 删除CNAME代理
     * POST /api/v1/cert-cnames/:id/delete
     */
    public function cnameDelete()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $cname = Db::name('cert_cname')->where('id', $id)->find();

        if (!$cname) {
            return $this->notFound('CNAME代理不存在');
        }

        Db::name('cert_cname')->where('id', $id)->delete();
        return $this->success(null, '删除CNAME代理成功');
    }

    /**
     * 检查CNAME代理
     * POST /api/v1/cert-cnames/:id/check
     */
    public function cnameCheck()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $cname = Db::name('cert_cname')->alias('A')
            ->join('domain B', 'A.did = B.id')
            ->where('A.id', $id)
            ->field('A.*,B.name cnamedomain')
            ->find();

        if (!$cname) {
            return $this->notFound('CNAME代理不存在');
        }

        try {
            $domain = '_acme-challenge.' . $cname['domain'];
            $record = $cname['rr'] . '.' . $cname['cnamedomain'];

            // 使用 DNS 查询工具检查 CNAME 记录
            $result = \app\utils\DnsQueryUtils::get_dns_records($domain, 'CNAME');
            if (!$result || !in_array($record, $result)) {
                $result = \app\utils\DnsQueryUtils::query_dns_doh($domain, 'CNAME');
                if (!$result || !in_array($record, $result)) {
                    return $this->error('CNAME记录验证失败，请检查DNS配置');
                }
            }

            // 更新状态
            Db::name('cert_cname')->where('id', $id)->update(['status' => 1]);

            return $this->success(['status' => 1], 'CNAME记录验证成功');
        } catch (Exception $e) {
            return $this->error('CNAME记录验证失败：' . $e->getMessage());
        }
    }

    /**
     * 执行证书订单任务
     * POST /api/v1/certificates/:id/execute
     */
    public function certificateExecute()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $id = $this->request->param('id', 0, 'intval');
        $reset = $this->request->post('reset', 0, 'intval');

        $cert = Db::name('cert_order')->where('id', $id)->find();
        if (!$cert) {
            return $this->notFound('证书订单不存在');
        }

        try {
            $service = new CertOrderService($id);
            if ($reset == 1) {
                $service->reset();
            }
            $retcode = $service->process(true);
            
            if ($retcode == 3) {
                return $this->success(null, '证书已签发成功！');
            } elseif ($retcode == 1) {
                return $this->success(null, '添加DNS记录成功！请等待DNS生效后点击验证');
            } else {
                return $this->success(null, '订单正在处理...');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取CNAME主机记录
     */
    private function getCnameHost(string $domain): string
    {
        $main = getMainDomain($domain);
        if ($main == $domain) {
            return '_acme-challenge';
        } else {
            return '_acme-challenge.' . substr($domain, 0, -strlen($main) - 1);
        }
    }

    // ==================== 高级操作与日志 ====================

    /**
     * 获取任务日志
     * GET /api/v1/certificates/log
     * GET /api/v1/deploy-tasks/log
     */
    public function showLog()
    {
        if ($this->request->user['level'] != 2) {
            return $this->forbidden('仅管理员可访问');
        }

        $processid = $this->request->get('processid', '', 'trim');
        if (empty($processid)) {
            return $this->validationError('processid 参数不能为空');
        }

        $file = app()->getRuntimePath() . 'log/' . $processid . '.log';
        if (!file_exists($file)) {
            return $this->error('日志文件不存在');
        }

        $data = [
            'content' => file_get_contents($file),
            'time' => filemtime($file)
        ];

        return $this->success($data, '获取日志成功');
    }

    /**
     * 切换证书自动续签
     * POST /api/v1/certificates/:id/auto-renew
     */
    public function certificateAutoRenew()
    {
        if ($this->request->user['level'] != 2) return $this->forbidden('仅管理员可访问');

        $id = $this->request->param('id', 0, 'intval');
        $isauto = $this->request->post('isauto', 0, 'intval');

        $cert = Db::name('cert_order')->where('id', $id)->find();
        if (!$cert) return $this->notFound('证书订单不存在');

        Db::name('cert_order')->where('id', $id)->update(['isauto' => $isauto]);
        return $this->success(['isauto' => $isauto], '切换自动续签状态成功');
    }

    /**
     * 重置证书订单
     * POST /api/v1/certificates/:id/reset
     */
    public function certificateReset()
    {
        if ($this->request->user['level'] != 2) return $this->forbidden('仅管理员可访问');

        $id = $this->request->param('id', 0, 'intval');
        $cert = Db::name('cert_order')->where('id', $id)->find();
        if (!$cert) return $this->notFound('证书订单不存在');

        try {
            $service = new CertOrderService($id);
            $service->cancel();
            $service->reset();
            return $this->success(null, '重置证书订单成功');
        } catch (Exception $e) {
            return $this->error('重置证书订单失败：' . $e->getMessage());
        }
    }

    /**
     * 吊销证书
     * POST /api/v1/certificates/:id/revoke
     */
    public function certificateRevoke()
    {
        if ($this->request->user['level'] != 2) return $this->forbidden('仅管理员可访问');

        $id = $this->request->param('id', 0, 'intval');
        $cert = Db::name('cert_order')->where('id', $id)->find();
        if (!$cert) return $this->notFound('证书订单不存在');

        try {
            $service = new CertOrderService($id);
            $service->revoke();
            return $this->success(null, '吊销证书成功');
        } catch (Exception $e) {
            return $this->error('吊销证书失败：' . $e->getMessage());
        }
    }
}
