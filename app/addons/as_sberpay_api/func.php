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
 * @param string $status_to           Новый статус
 * @param string $status_from         Предыдущий статус
 * @param array  $order_info          Данные заказа
 * @param array  $force_notification  Форс-уведомления
 * @param array  $order_statuses      Справочник статусов с параметрами
 * @param bool   $place_order         Флаг размещения заказа
 */
function fn_as_sberpay_api_change_order_status_post(
    $status_to, $status_from, $order_info,
    $force_notification, $order_statuses, $place_order
) {
    if ($status_to !== 'C') {
        return;
    }

    $prev = $order_statuses[$status_from]['params'] ?? [];
    if (empty($prev['payment_received']) || $prev['payment_received'] !== 'Y') {
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
