<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['top']['addons']['items']['as_sberpay_api'] = [
    'title' => 'SberPay API',
    'href' => 'addons.update&addon=as_sberpay_api',
    'position' => 100,
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
    ],
];

return $schema;
