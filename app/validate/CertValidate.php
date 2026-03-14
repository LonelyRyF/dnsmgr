<?php

namespace app\validate;

use think\Validate;

/**
 * Certificate Validation Rules
 */
class CertValidate extends Validate
{
    protected $rule = [
        'domain' => 'require|checkDomainList',
        'type' => 'require|in:letsencrypt,zerossl,buypass,google,aliyun,tencent',
        'auth_type' => 'require|in:dns,http',
        'dns_account_id' => 'requireIf:auth_type,dns|integer|gt:0',
        'deploy_type' => 'max:50',
        'deploy_config' => 'array',
        'auto_renew' => 'in:0,1',
        'remark' => 'max:255',
    ];

    protected $message = [
        'domain.require' => 'Domain is required',
        'type.require' => 'Certificate type is required',
        'type.in' => 'Invalid certificate type',
        'auth_type.require' => 'Authentication type is required',
        'auth_type.in' => 'Invalid authentication type',
        'dns_account_id.requireIf' => 'DNS account is required for DNS authentication',
        'dns_account_id.integer' => 'DNS account ID must be an integer',
        'dns_account_id.gt' => 'Invalid DNS account ID',
        'deploy_type.max' => 'Deploy type cannot exceed 50 characters',
        'deploy_config.array' => 'Deploy configuration must be an array',
        'auto_renew.in' => 'Invalid auto renew value',
        'remark.max' => 'Remark cannot exceed 255 characters',
    ];

    protected $scene = [
        'add' => ['domain', 'type', 'auth_type', 'dns_account_id'],
        'edit' => ['auto_renew', 'deploy_type', 'deploy_config', 'remark'],
        'deploy' => ['deploy_type', 'deploy_config'],
    ];

    /**
     * Validate domain list format
     *
     * @param string $value
     * @return bool|string
     */
    protected function checkDomainList($value)
    {
        $domains = array_filter(array_map('trim', explode(',', $value)));

        if (empty($domains)) {
            return 'At least one domain is required';
        }

        foreach ($domains as $domain) {
            if (!preg_match('/^(?:\*\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
                return "Invalid domain format: {$domain}";
            }
        }

        return true;
    }
}
