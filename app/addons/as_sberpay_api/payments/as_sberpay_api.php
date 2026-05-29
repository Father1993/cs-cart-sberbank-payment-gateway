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

        $order_status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;
        if (in_array($order_status, [1, 2], true) && $processor->usesOrderBundle()) {
            $processor->refreshClosingReceiptMeta($order_id, (string) $gateway_id, 'payment_confirmed');
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
        fn_order_placement_routines('save', $order_id, [], false);
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

        $gateway_id = (string) ($order_info['payment_info']['transaction_id'] ?? '');
        $request_order_id = fn_as_sberpay_api_get_request_gateway_id($_REQUEST);
        $sdk_return_reason = fn_as_sberpay_api_get_sdk_return_reason($_REQUEST);
        $sdk_state = !empty($_REQUEST['state']) ? strtolower((string) $_REQUEST['state']) : '';

        if ($gateway_id === '' && $request_order_id !== '') {
            $gateway_id = $request_order_id;
        }

        if (!empty($request_order_id) && $request_order_id !== $gateway_id) {
            $pp_response['reason_text'] = 'Неверный идентификатор транзакции';
        } elseif ($gateway_id === '') {
            $pp_response['reason_text'] = $sdk_return_reason !== ''
                ? $sdk_return_reason
                : 'Не найден идентификатор транзакции';
        } else {
            $response = $processor->getOrderStatusExtended($gateway_id);

            if ($processor->isLogging()) {
                $processor->log($response, 'Return: status check');
            }

            fn_as_sberpay_api_save_payment_meta($order_id, $response, $gateway_id);

            $order_status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;
            if (in_array($order_status, [1, 2], true) && $processor->usesOrderBundle()) {
                $processor->refreshClosingReceiptMeta($order_id, (string) $gateway_id, 'payment_confirmed');
            }

            $pp_response = fn_as_sberpay_api_build_response($response, $processor);
        }

        if (($pp_response['order_status'] ?? '') === 'F' && $sdk_return_reason !== '') {
            if ($sdk_state === 'cancel') {
                $pp_response['reason_text'] = $sdk_return_reason;
            } elseif ($sdk_state === 'return' && empty($pp_response['reason_text'])) {
                $pp_response['reason_text'] = $sdk_return_reason;
            }
        }

        fn_finish_payment($order_id, $pp_response);
        fn_as_sberpay_api_route_after_payment($order_id, $pp_response, $processor);
    }

    exit;

} else {

    // =========================================================================
    //  ИНИЦИАЦИЯ ПЛАТЕЖА — register.do
    // =========================================================================
    $processor = new AsSberPayApi($processor_data);
    $response = $processor->register($order_info);

    if ($processor->isSbpC2b()) {
        if ($processor->isRegisterSuccessForMode($response)) {
            $ext = $processor->extractRegisterExternalParams($response);

            fn_update_order_payment_info($order_id, [
                'transaction_id' => $response['orderId'],
                'sbp_payload' => $ext['sbp_payload'],
                'qrc_id' => $ext['qrc_id'],
            ]);

            fn_as_sberpay_api_save_fiscal_snapshot(
                $order_id,
                $order_info,
                $processor->getLastRegisterContext(),
                (string) $response['orderId']
            );

            fn_clear_cart(\Tygh::$app['session']['cart']);
            fn_redirect(fn_url('as_sberpay_api.sbp?order_id=' . (int) $order_id, AREA, 'current'));
            exit;
        }

        if ($processor->isLogging()) {
            $processor->log([
                'error_code' => $processor->getErrorCode(),
                'error_text' => $processor->getErrorText(),
                'response' => $response,
            ], 'SBP register failed');
        }

        $pp_response = [
            'order_status' => 'F',
            'reason_text'  => $processor->getErrorText() ?: __('addons.as_sberpay_api.sbp_return_failed'),
        ];
        fn_finish_payment($order_id, $pp_response);
        fn_as_sberpay_api_route_after_payment($order_id, $pp_response, $processor);
        exit;
    }

    if (!$processor->isError() && !empty($response['orderId']) && !empty($response['formUrl'])) {
        fn_update_order_payment_info($order_id, [
            'transaction_id' => $response['orderId'],
        ]);

        fn_as_sberpay_api_save_fiscal_snapshot(
            $order_id,
            $order_info,
            $processor->getLastRegisterContext(),
            (string) $response['orderId']
        );

        fn_clear_cart(\Tygh::$app['session']['cart']);

        if ($processor->isSberPaySdk()) {
            fn_redirect(fn_url('as_sberpay_api.pay?order_id=' . (int) $order_id, AREA, 'current'));
            exit;
        }

        fn_create_payment_form($response['formUrl'], [], '', true, 'GET');
    } else {
        if ($processor->isLogging()) {
            $processor->log([
                'error_code' => $processor->getErrorCode(),
                'error_text' => $processor->getErrorText(),
            ], 'Register failed');
        }

        $pp_response = [
            'order_status' => 'F',
            'reason_text'  => $processor->getErrorText(),
        ];
        fn_finish_payment($order_id, $pp_response);
        fn_as_sberpay_api_route_after_payment($order_id, $pp_response, $processor);
    }
}
