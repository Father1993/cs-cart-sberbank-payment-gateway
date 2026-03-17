<?php
/** AS SberPay API — функции установки/удаления и хуки. */
if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/**
 * Установка: регистрирует payment processor в БД.
 */
function fn_as_sberpay_api_install()
{
    fn_as_sberpay_api_uninstall();

    db_query('INSERT INTO ?:payment_processors ?e', [
        'processor' => 'SberPay API',
        'processor_script' => 'as_sberpay_api.php',
        'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
        'admin_template' => 'as_sberpay_api.tpl',
        'callback' => 'Y',
        'type' => 'P',
        'addon' => 'as_sberpay_api',
    ]);
}

/**
 * Удаление: убирает processor из БД
 */
function fn_as_sberpay_api_uninstall()
{
    db_query('DELETE FROM ?:payment_processors WHERE processor_script = ?s', 'as_sberpay_api.php');
}

/**
 * Хук: отправляет закрывающий чек (doReceipt) при переводе оплаченного заказа в «Выполнен».
 *
 * Сигнатура хука CS-Cart 4.18 (fn.cart.php):
 * change_order_status_post($order_id, $status_to, $status_from, $force_notification, $place_order, $order_info, $edp_data)
 */
function fn_as_sberpay_api_change_order_status_post(
    $order_id, $status_to, $status_from,
    $force_notification, $place_order, $order_info, $edp_data
) {
    if ($status_to !== 'C') {
        return;
    }

    if (empty($order_info['payment_info']['transaction_id'])) {
        return;
    }

    $processor_data = fn_get_processor_data($order_info['payment_id']);
    if (($processor_data['processor']['processor_script'] ?? '') !== 'as_sberpay_api.php') {
        return;
    }

    (new \Tygh\Payments\Processors\AsSberPayApi($processor_data))->doReceipt($order_info);
}

/**
 * Хук: помечает процессор как российский для категоризации.
 *
 * @param string $lang_code  Код языка
 * @param array  $processors Список процессоров (по ссылке)
 */
function fn_as_sberpay_api_get_payment_processors_post($lang_code, &$processors)
{
    foreach ($processors as &$processor) {
        if ($processor['addon'] === 'as_sberpay_api') {
            $processor['russian'] = true;
        }
    }
    unset($processor);
}
