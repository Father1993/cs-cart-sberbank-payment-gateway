<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

// Добавляем пункт меню в раздел "Аддоны"
$schema['top']['addons']['items']['as_sberpay_api'] = [
    'title' => '1C REST API',
    'href' => 'addons.update&addon=as_sberpay_api',
    'position' => 100,
];

return $schema;