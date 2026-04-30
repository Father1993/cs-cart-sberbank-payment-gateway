<?php
/**
 * AS SberPay API — инициализация хуков.
 */

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
    'get_payment_processors_post',
    'change_order_status_post',
    'get_order_info',
    'get_orders_post'
);