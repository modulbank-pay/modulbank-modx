<?php

/**
 * Loads system settings into build
 * @var modX $modx
 * @package mspmodubank
 * @subpackage build
 */
$settings = [];

$tmp = [
    'merchant' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'secret' => [
        'xtype' => 'text-password',
        'value' => '',
    ],
    'test_secret' => [
        'xtype' => 'text-password',
        'value' => '',
    ],
    'success_id' => [
        'xtype' => 'numberfield',
        'value' => 0,
    ],
    'debug' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'tax_mode' => [
        'type' => 'textfield',
        'value' => 'usn_income_outcome',
    ],
    'payment_method' => [
        'type' => 'textfield',
        'value' => 'full_payment',
    ],
    'payment_object' => [
        'type' => 'textfield',
        'value' => 'commodity',
    ],
    'tax' => [
        'type' => 'textfield',
        'value' => 'none',
    ],
    'tax_delivery' => [
        'type' => 'textfield',
        'value' => 'none',
    ],
    'test_mode' => [
        'xtype' => 'combo-boolean',
        'value' => true,
    ],
    'preauth' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'status_for_confirm_id' => [
        'type' => 'textfield',
        'value' => '3',
    ],
    'status_for_cancel_id' => [
        'type' => 'textfield',
        'value' => '4',
    ],
];

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray(array_merge(
        [
            'key' => 'mspmodulbank_' . $k,
            'namespace' => 'mspmodulbank',
            'area' => 'main',
        ],
        $v
    ), '', true, true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;
