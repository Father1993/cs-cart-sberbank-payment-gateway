<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['top']['addons']['items']['as_sberpay_api'] = [
    'title' => 'SberPay API',
    'href' => 'addons.update&addon=as_sberpay_api',
    'position' => 120,
    'strict' => true,
    'subitems' => [
        'settings' => [
            'title' => 'Настройки',
            'href' => 'addons.update&addon=as_sberpay_api',
            'position' => 10,
        ],
        'payments' => [
            'title' => 'Способы оплаты',
            'href' => 'payments.manage',
            'position' => 20,
        ],
        'receipt_audit' => [
            'title' => 'Аудит чеков',
            'href' => 'as_sberpay_api.receipt_audit',
            'position' => 30,
        ],
    ],
];

return $schema;
