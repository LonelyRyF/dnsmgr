<?php

namespace app\validate;

use think\Validate;

/**
 * DNS Record Validation Rules
 */
class RecordValidate extends Validate
{
    protected $rule = [
        'domain_id' => 'require|integer|gt:0',
        'name' => 'require|max:255',
        'type' => 'require|in:A,AAAA,CNAME,MX,TXT,NS,SRV,CAA,REDIRECT_URL,FORWARD_URL',
        'value' => 'require|checkRecordValue',
        'line' => 'max:50',
        'ttl' => 'integer|between:1,86400',
        'mx' => 'integer|between:1,50',
        'status' => 'in:0,1',
        'weight' => 'integer|between:0,100',
        'remark' => 'max:255',
    ];

    protected $message = [
        'domain_id.require' => 'Domain ID is required',
        'domain_id.integer' => 'Domain ID must be an integer',
        'domain_id.gt' => 'Invalid domain ID',
        'name.require' => 'Record name is required',
        'name.max' => 'Record name cannot exceed 255 characters',
        'type.require' => 'Record type is required',
        'type.in' => 'Invalid record type',
        'value.require' => 'Record value is required',
        'line.max' => 'Line cannot exceed 50 characters',
        'ttl.integer' => 'TTL must be an integer',
        'ttl.between' => 'TTL must be between 1 and 86400',
        'mx.integer' => 'MX priority must be an integer',
        'mx.between' => 'MX priority must be between 1 and 50',
        'status.in' => 'Invalid status value',
        'weight.integer' => 'Weight must be an integer',
        'weight.between' => 'Weight must be between 0 and 100',
        'remark.max' => 'Remark cannot exceed 255 characters',
    ];

    protected $scene = [
        'add' => ['domain_id', 'name', 'type', 'value', 'line', 'ttl'],
        'edit' => ['name', 'type', 'value', 'line', 'ttl'],
        'batch' => ['domain_id', 'records'],
    ];

    /**
     * Validate record value based on type
     *
     * @param string $value
     * @param array $data
     * @return bool|string
     */
    protected function checkRecordValue($value, $data)
    {
        $type = $data['type'] ?? '';

        switch ($type) {
            case 'A':
                if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return 'Invalid IPv4 address';
                }
                break;

            case 'AAAA':
                if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return 'Invalid IPv6 address';
                }
                break;

            case 'CNAME':
            case 'NS':
                if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.?$/i', $value)) {
                    return 'Invalid domain name';
                }
                break;

            case 'MX':
                if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.?$/i', $value)) {
                    return 'Invalid mail server domain';
                }
                break;
        }

        return true;
    }
}
