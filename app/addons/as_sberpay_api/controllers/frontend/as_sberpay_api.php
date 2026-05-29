<?php
/**
 * AS SberPay API — frontend: landing SberPay Web SDK.
 */

use Tygh\Payments\Processors\AsSberPayApi;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (!in_array($mode, ['pay', 'cancel'], true)) {
    return [CONTROLLER_STATUS_NO_PAGE];
}

$order_id = !empty($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

if ($mode === 'cancel') {
    $cancel_result = fn_as_sberpay_api_cancel_sdk_payment($order_id);

    if ($cancel_result === 'paid') {
        return [CONTROLLER_STATUS_REDIRECT, 'checkout.complete?order_id=' . $order_id];
    }

    return [CONTROLLER_STATUS_REDIRECT, 'index.index'];
}

$ctx = fn_as_sberpay_api_resolve_sdk_pay_order($order_id);
if (!$ctx) {
    return [CONTROLLER_STATUS_REDIRECT, 'index.index'];
}

/** @var AsSberPayApi $processor */
$processor = $ctx['processor'];
$order_info = $ctx['order_info'];

if (($order_info['status'] ?? '') === $processor->getConfirmedStatus()) {
    return [CONTROLLER_STATUS_REDIRECT, 'checkout.complete?order_id=' . $order_id];
}

$protocol = (defined('HTTPS') && HTTPS) ? 'https' : 'http';

Tygh::$app['view']->assign([
    'order_info' => $order_info,
    'order_id' => $order_id,
    'order_total_formatted' => fn_format_price_by_currency($order_info['total']),
    'bank_invoice_id' => $ctx['gateway_id'],
    'back_url' => $processor->getSdkBackUrl($order_id, $protocol),
    'cancel_url' => fn_url('as_sberpay_api.cancel?order_id=' . $order_id, AREA, $protocol),
    'widget_env' => $processor->getWidgetEnvironment(),
    'sdk_phone' => $processor->formatSdkPhone($order_info['phone'] ?? ''),
    'sdk_assets_version' => '10508',
    'content_tpl' => 'addons/as_sberpay_api/views/as_sberpay_api/pay.tpl',
]);

return [CONTROLLER_STATUS_OK];
