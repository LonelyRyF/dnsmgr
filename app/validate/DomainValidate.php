<?php

namespace app\validate;

use think\Validate;

/**
 * Domain Validation Rules
 */
class DomainValidate extends Validate
{
    protected $rule = [
        'name' => 'require|checkDomainFormat',
        'type' => 'require|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA',
        'account_id' => 'require|integer|gt:0',
        'remark' => 'max:255',
    ];

    protected $message = [
        'name.require' => 'Domain name is required',
        'type.require' => 'Record type is required',
        'type.in' => 'Invalid record type',
        'account_id.require' => 'Account ID is required',
        'account_id.integer' => 'Account ID must be an integer',
        'account_id.gt' => 'Invalid account ID',
        'remark.max' => 'Remark cannot exceed 255 characters',
    ];

    protected $scene = [
        'add' => ['name', 'account_id'],
        'edit' => ['name', 'remark'],
    ];

    /**
     * Validate domain format
     *
     * @param string $value
     * @return bool|string
     */
    protected function checkDomainFormat($value)
    {
        // Basic domain format validation
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $value)) {
            return 'Invalid domain format';
        }

        return true;
    }
}
