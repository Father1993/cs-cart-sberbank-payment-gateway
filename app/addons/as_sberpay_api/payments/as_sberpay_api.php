<?php
/**
 * AS SberPay API — основной скрипт обработки платежей.
 *
 * Потоки:
 *   1. PAYMENT_NOTIFICATION + action=callback → уведомление от Сбера (серверное)
 *   2. PAYMENT_NOTIFICATION + action=return   → клиент вернулся после оплаты
 *   3. PAYMENT_NOTIFICATION + mode=error      → клиент вернулся после ошибки
 *   4. Иначе                                  → инициация платежа (register)
 */

use Tygh\Payments\Processors\AsSberPayApi;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (defined('PAYMENT_NOTIFICATION')) {

    // =========================================================================
    //  CALLBACK — серверное уведомление от Сбера
    // =========================================================================
    if (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'callback') {

        // Получаем processor_data по payment_id из URL
        $payment_id = !empty($_REQUEST['payment_id']) ? (int) $_REQUEST['payment_id'] : 0;
        if (empty($processor_data) && $payment_id) {
            $processor_data = fn_get_processor_data($payment_id);
        }
        if (empty($processor_data)) {
            exit;
        }

        $gateway_id = !empty($_REQUEST['orderId'])
            ? $_REQUEST['orderId']
            : (!empty($_REQUEST['mdOrder']) ? $_REQUEST['mdOrder'] : null);

        if (empty($gateway_id)) {
            exit;
        }

        $processor = new AsSberPayApi($processor_data);
        $response = $processor->getOrderStatusExtended($gateway_id);

        if ($processor->isLogging()) {
            $processor->log($response, 'Callback received');
        }

        if ($processor->isError() || empty($response['orderNumber'])) {
            exit;
        }

        // orderNumber = "123_abc" → order_id = 123
        $order_id = (int) explode('_', $response['orderNumber'])[0];
        $order_info = fn_get_order_info($order_id);

        if (empty($order_info)) {
            exit;
        }

        // Защита: проверяем что transaction_id совпадает
        if (($order_info['payment_info']['transaction_id'] ?? '') !== $gateway_id) {
            if ($processor->isLogging()) {
                $processor->log([
                    'expected' => $order_info['payment_info']['transaction_id'] ?? '',
                    'received' => $gateway_id,
                ], 'Callback: transaction_id mismatch');
            }
            exit;
        }

        fn_as_sberpay_api_save_payment_meta($order_id, $response, $gateway_id);

        if (in_array((int) ($response['orderStatus'] ?? -1), [1, 2, 4], true)) {
            fn_as_sberpay_api_save_receipt_meta($order_id, $processor->getReceiptStatus($gateway_id));
        }

        // Идемпотентность: не обрабатываем повторно если уже оплачен
        if ($order_info['status'] === $processor->getConfirmedStatus()) {
            if ($processor->isLogging()) {
                $processor->log(['order_id' => $order_id], 'Callback: order already confirmed, skip');
            }
            exit;
        }

        $pp_response = fn_as_sberpay_api_build_response($response, $processor);

        fn_finish_payment($order_id, $pp_response);
        fn_order_placement_routines('save', $order_id, false);
        exit;
    }

    // =========================================================================
    //  RETURN — клиент вернулся из платёжной формы
    // =========================================================================
    if ((!empty($_REQUEST['action']) && $_REQUEST['action'] === 'return') || $mode === 'return' || $mode === 'error') {

        $order_id = !empty($_REQUEST['ordernumber']) ? (int) $_REQUEST['ordernumber'] : 0;
        $order_info = fn_get_order_info($order_id);

        if (empty($order_info)) {
            exit;
        }

        if (empty($processor_data)) {
            $processor_data = fn_get_processor_data($order_info['payment_id']);
        }

        $processor = new AsSberPayApi($processor_data);

        if ($processor->isLogging()) {
            $processor->log($_REQUEST, 'Return: incoming REQUEST');
        }

        $pp_response = ['order_status' => 'F'];

        $gateway_id = $order_info['payment_info']['transaction_id'] ?? '';
        $request_order_id = $_REQUEST['orderId'] ?? $_REQUEST['mdOrder'] ?? '';

        if (!empty($request_order_id) && $request_order_id !== $gateway_id) {
            $pp_response['reason_text'] = 'Неверный идентификатор транзакции';
        } elseif (empty($gateway_id)) {
            $pp_response['reason_text'] = 'Не найден идентификатор транзакции';
        } else {
            $response = $processor->getOrderStatusExtended($gateway_id);

            if ($processor->isLogging()) {
                $processor->log($response, 'Return: status check');
            }

            fn_as_sberpay_api_save_payment_meta($order_id, $response, $gateway_id);

            if (in_array((int) ($response['orderStatus'] ?? -1), [1, 2, 4], true)) {
                fn_as_sberpay_api_save_receipt_meta($order_id, $processor->getReceiptStatus($gateway_id));
            }

            $pp_response = fn_as_sberpay_api_build_response($response, $processor);
        }

        fn_finish_payment($order_id, $pp_response);
        fn_order_placement_routines('route', $order_id, false);
        exit;
    }

    exit;

} else {

    // =========================================================================
    //  ИНИЦИАЦИЯ ПЛАТЕЖА — register.do
    // =========================================================================
    $processor = new AsSberPayApi($processor_data);
    $response = $processor->register($order_info);

    if (!$processor->isError() && !empty($response['orderId']) && !empty($response['formUrl'])) {
        // Сохраняем gateway order ID
        fn_update_order_payment_info($order_id, [
            'transaction_id' => $response['orderId'],
        ]);

        fn_clear_cart(\Tygh::$app['session']['cart']);
        fn_create_payment_form($response['formUrl'], [], '', true, 'GET');
    } else {
        if ($processor->isLogging()) {
            $processor->log([
                'error_code' => $processor->getErrorCode(),
                'error_text' => $processor->getErrorText(),
            ], 'Register failed');
        }

        fn_finish_payment($order_id, [
            'order_status' => 'F',
            'reason_text'  => $processor->getErrorText(),
        ]);
        fn_order_placement_routines('route', $order_id, false);
    }
}

// =============================================================================
//  Вспомогательная функция: формирование pp_response из ответа Сбера
// =============================================================================

/**
 * Формирует массив pp_response на основе ответа getOrderStatusExtended.
 *
 * orderStatus:
 *   0 = заказ зарегистрирован, не оплачен
 *   1 = предавторизованная сумма захолдирована
 *   2 = полная авторизация (оплачен)
 *   3 = авторизация отменена
 *   4 = по транзакции была проведена операция возврата
 *   5 = ACS авторизация инициирована
 *   6 = авторизация отклонена
 *
 * @param array           $response  Ответ API
 * @param AsSberPayApi    $processor Экземпляр процессора
 * @return array pp_response
 */
function fn_as_sberpay_api_build_response($response, $processor)
{
    $status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;
    $pai = !empty($response['paymentAmountInfo']) ? $response['paymentAmountInfo'] : [];

    // Успешная оплата или холд
    if ($status === 1 || $status === 2) {
        return [
            'order_status'      => $processor->getConfirmedStatus(),
            'gateway_status'    => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved'  => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded'  => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    // Возврат
    if ($status === 4) {
        return [
            'gateway_status'    => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved'  => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded'  => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    // Отмена
    if ($status === 3) {
        return [
            'order_status'      => 'F',
            'gateway_status'    => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved'  => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded'  => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    // Ошибка / отклонение / прочее
    return [
        'order_status' => 'F',
        'reason_text'  => !empty($response['actionCodeDescription'])
            ? $response['actionCodeDescription']
            : (!empty($response['errorMessage']) ? $response['errorMessage'] : 'Оплата не прошла'),
    ];
}