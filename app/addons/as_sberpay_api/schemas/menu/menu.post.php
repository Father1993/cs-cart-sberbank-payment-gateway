<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['top']['addons']['items']['as_sberpay_api'] = [
    'title'    => 'SberPay API',
    'href'     => 'addons.update&addon=as_sberpay_api',
    'position' => 100,
];

$schema['top']['addons']['items']['as_sberpay_api_payments'] = [
    'title'    => 'SberPay API: Способы оплаты',
    'href'     => 'payments.manage',
    'position' => 101,
];

return $schema;