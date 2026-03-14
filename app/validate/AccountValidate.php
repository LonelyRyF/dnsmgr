<?php

namespace app\validate;

use think\Validate;

/**
 * Account Validation Rules
 */
class AccountValidate extends Validate
{
    protected $rule = [
        'type' => 'require|max:50',
        'name' => 'require|max:100',
        'config' => 'require',
        'remark' => 'max:255',
        'active' => 'in:0,1',
    ];

    protected $message = [
        'type.require' => 'Account type is required',
        'type.max' => 'Account type cannot exceed 50 characters',
        'name.require' => 'Account name is required',
        'name.max' => 'Account name cannot exceed 100 characters',
        'config.require' => 'Account configuration is required',
        'remark.max' => 'Remark cannot exceed 255 characters',
        'active.in' => 'Invalid active status',
    ];

    protected $scene = [
        'add' => ['type', 'name', 'config'],
        'edit' => ['name', 'config', 'remark', 'active'],
    ];
}
