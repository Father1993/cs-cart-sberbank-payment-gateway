<?php
/**
 * AS SberPay API — frontend: landing SberPay Web SDK / SBP C2B.
 */

use Tygh\Payments\Processors\AsSberPayApi;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (!in_array($mode, ['pay', 'sbp', 'cancel'], true)) {
    return [CONTROLLER_STATUS_NO_PAGE];
}

$order_id = !empty($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;

if ($mode === 'cancel') {
    $cancel_result = fn_as_sberpay_api_cancel_landing_payment($order_id, ['sberpay_sdk', 'sbp_c2b']);

    if ($cancel_result === 'paid') {
        return [CONTROLLER_STATUS_REDIRECT, 'checkout.complete?order_id=' . $order_id];
    }

    return [CONTROLLER_STATUS_REDIRECT, 'index.index'];
}

$protocol = (defined('HTTPS') && HTTPS) ? 'https' : 'http';
$cancel_url = fn_url('as_sberpay_api.cancel?order_id=' . $order_id, AREA, $protocol);

if ($mode === 'sbp') {
    $ctx = fn_as_sberpay_api_resolve_sbp_pay_order($order_id);
    if (!$ctx) {
        return [CONTROLLER_STATUS_REDIRECT, 'index.index'];
    }

    /** @var AsSberPayApi $processor */
    $processor = $ctx['processor'];
    $order_info = $ctx['order_info'];

    if (($order_info['status'] ?? '') === $processor->getConfirmedStatus()) {
        return [CONTROLLER_STATUS_REDIRECT, 'checkout.complete?order_id=' . $order_id];
    }

    Tygh::$app['view']->assign([
        'order_info' => $order_info,
        'order_id' => $order_id,
        'order_total_formatted' => fn_format_price_by_currency($order_info['total']),
        'sbp_payload' => $ctx['sbp_payload'],
        'cancel_url' => $cancel_url,
        'sbp_assets_version' => '13000',
        'content_tpl' => 'addons/as_sberpay_api/views/as_sberpay_api/pay_sbp.tpl',
    ]);

    return [CONTROLLER_STATUS_OK];
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

$order_currency = !empty($order_info['secondary_currency'])
    ? (string) $order_info['secondary_currency']
    : (defined('CART_SECONDARY_CURRENCY') ? CART_SECONDARY_CURRENCY : CART_PRIMARY_CURRENCY);
$order_amount = fn_as_sberpay_api_format_sdk_amount($order_info['total'], $order_currency);

Tygh::$app['view']->assign([
    'order_info' => $order_info,
    'order_id' => $order_id,
    'order_amount' => $order_amount,
    'order_total_formatted' => $order_amount['full'],
    'site_host' => (string) \Tygh\Registry::get('config.http_host'),
    'bank_invoice_id' => $ctx['gateway_id'],
    'back_url' => $processor->getSdkBackUrl($order_id, $protocol),
    'cancel_url' => $cancel_url,
    'widget_env' => $processor->getWidgetEnvironment(),
    'sdk_phone' => $processor->formatSdkPhone($order_info['phone'] ?? ''),
    'sdk_assets_version' => '10510',
    'content_tpl' => 'addons/as_sberpay_api/views/as_sberpay_api/pay.tpl',
]);

return [CONTROLLER_STATUS_OK];
