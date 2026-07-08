<?php
/**
 * AS SberPay API — действия администратора.
 */

use Tygh\Payments\Processors\AsSberPayApi;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'refund') {
        $order_id = !empty($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;
        $order_info = fn_get_order_info($order_id, false, false);

        if (empty($order_info)) {
            fn_set_notification('E', __('error'), __('object_not_found'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.manage'];
        }

        $runtime_company_id = (int) Registry::get('runtime.company_id');
        if ($runtime_company_id && (int) ($order_info['company_id'] ?? 0) !== $runtime_company_id) {
            fn_set_notification('E', __('error'), __('access_denied'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.manage'];
        }

        $processor_data = fn_get_processor_data($order_info['payment_id']);
        if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
            fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_not_available'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        if (empty($order_info['payment_info']['transaction_id'])) {
            fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_missing_transaction'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $payment_meta = fn_as_sberpay_api_get_payment_meta($order_id);
        if (!empty($payment_meta['refund']['status']) && $payment_meta['refund']['status'] === 'succeeded') {
            fn_set_notification('W', __('warning'), __('addons.as_sberpay_api.refund_already_done'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $processor = new AsSberPayApi($processor_data);
        $refund_context = fn_as_sberpay_api_build_refund_context($payment_meta);

        if ($processor->usesOrderBundle()) {
            $order_total_minor = (int) round(fn_format_price_by_currency($order_info['total'] ?? 0) * 100);
            $active_snapshot = fn_as_sberpay_api_get_active_fiscal_snapshot($payment_meta);

            if (empty($active_snapshot) || !$refund_context) {
                fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_missing_snapshot'));

                return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
            }

            if (!empty($refund_context['missing_closing_receipt_snapshot'])) {
                fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_bundle_rebuild_required'));

                return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
            }

            if ($order_total_minor !== (int) ($refund_context['refundable_amount_minor'] ?? 0)) {
                fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_only_full_snapshot_supported'));

                return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
            }

            if (empty($refund_context['refund_order_bundle_ready']) || empty($refund_context['refund_order_bundle'])) {
                fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_bundle_rebuild_required'));

                return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
            }
        }

        $external_refund_id = !empty($payment_meta['refund']['external_refund_id'])
            ? (string) $payment_meta['refund']['external_refund_id']
            : 'refund-' . $order_id . '-full';

        $refund_response = $processor->refundOrder($order_info, $external_refund_id, $payment_meta);
        if ($processor->isError() || !isset($refund_response['errorCode']) || (string) $refund_response['errorCode'] !== '0') {
            fn_as_sberpay_api_save_refund_meta($order_id, [
                'status' => 'failed',
                'external_refund_id' => $external_refund_id,
                'amount' => (float) $order_info['total'],
                'error_code' => (string) ($refund_response['errorCode'] ?? $processor->getErrorCode()),
                'error_message' => (string) ($refund_response['errorMessage'] ?? $processor->getErrorText()),
                'updated_at' => TIME,
            ]);

            fn_set_notification(
                'E',
                __('error'),
                $processor->getErrorText() ?: (string) ($refund_response['errorMessage'] ?? __('addons.as_sberpay_api.refund_failed'))
            );

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $status_response = $processor->getOrderStatusExtended($order_info['payment_info']['transaction_id']);
        if (!$processor->isError()) {
            fn_as_sberpay_api_save_payment_meta($order_id, $status_response, $order_info['payment_info']['transaction_id']);
            $payment_info = fn_as_sberpay_api_build_response($status_response, $processor);
            if ($payment_info) {
                fn_update_order_payment_info($order_id, array_merge((array) ($order_info['payment_info'] ?? []), $payment_info));
            }
        }

        fn_as_sberpay_api_save_refund_meta($order_id, [
            'status' => 'succeeded',
            'external_refund_id' => $external_refund_id,
            'amount' => (float) $order_info['total'],
            'error_code' => '0',
            'error_message' => (string) ($refund_response['errorMessage'] ?? ''),
            'updated_at' => TIME,
        ]);

        if ($processor->usesOrderBundle()) {
            $processor->refreshClosingReceiptMeta(
                $order_id,
                (string) $order_info['payment_info']['transaction_id'],
                'refund_confirmed'
            );
        }

        fn_set_notification('N', __('notice'), __('addons.as_sberpay_api.refund_success'));

        return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
    }

    if ($mode === 'receipt_status') {
        $order_id = !empty($_REQUEST['order_id']) ? (int) $_REQUEST['order_id'] : 0;
        $order_info = fn_get_order_info($order_id, false, false);

        if (empty($order_info)) {
            fn_set_notification('E', __('error'), __('object_not_found'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.manage'];
        }

        $runtime_company_id = (int) Registry::get('runtime.company_id');
        if ($runtime_company_id && (int) ($order_info['company_id'] ?? 0) !== $runtime_company_id) {
            fn_set_notification('E', __('error'), __('access_denied'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.manage'];
        }

        $processor_data = fn_get_processor_data($order_info['payment_id']);
        if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
            fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_not_available'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        if (empty($order_info['payment_info']['transaction_id'])) {
            fn_set_notification('E', __('error'), __('addons.as_sberpay_api.refund_missing_transaction'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $processor = new AsSberPayApi($processor_data);
        if (!$processor->usesOrderBundle()) {
            fn_set_notification('W', __('warning'), __('addons.as_sberpay_api.receipt_fiscal_disabled'));

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $sync = $processor->refreshClosingReceiptMeta(
            $order_id,
            (string) $order_info['payment_info']['transaction_id'],
            'admin_refresh'
        );

        if (empty($sync['ok'])) {
            fn_set_notification(
                'E',
                __('error'),
                !empty($sync['message'])
                    ? (string) $sync['message']
                    : __('addons.as_sberpay_api.receipt_status_failed')
            );

            return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
        }

        $payment_meta = fn_as_sberpay_api_get_payment_meta($order_id);

        $prepayment_label = fn_as_sberpay_api_get_receipt_status_display_label(null);
        if (!empty($payment_meta['fiscal_snapshot'])) {
            $prepayment_label = !empty($sync['prepayment_found'])
                ? fn_as_sberpay_api_get_receipt_status_display_label($sync['prepayment_status'] ?? '')
                : fn_as_sberpay_api_get_receipt_status_display_label('not_sent');
        }

        $closing_label = !empty($sync['found'])
            ? fn_as_sberpay_api_get_receipt_status_display_label($sync['status'] ?? '')
            : fn_as_sberpay_api_get_receipt_status_display_label('not_sent');

        $refund_label = fn_as_sberpay_api_get_receipt_status_display_label(null);
        if (fn_as_sberpay_api_order_had_refund($payment_meta) || !empty($sync['refund_found'])) {
            $refund_label = !empty($sync['refund_found'])
                ? fn_as_sberpay_api_get_receipt_status_display_label($sync['refund_status'] ?? '')
                : fn_as_sberpay_api_get_receipt_status_display_label('not_sent');
        }

        fn_set_notification(
            'N',
            __('notice'),
            __('addons.as_sberpay_api.receipt_status_refresh_summary', [
                '[prepayment]' => $prepayment_label,
                '[closing]' => $closing_label,
                '[refund]' => $refund_label,
            ])
        );

        $expect_closing = fn_as_sberpay_api_is_closing_receipt_send_enabled()
            && (($order_info['status'] ?? '') === 'C' || !empty($payment_meta['closing_receipt']));
        if ($expect_closing && empty($sync['found'])) {
            fn_set_notification('W', __('warning'), __('addons.as_sberpay_api.receipt_closing_not_found'));
        }

        if (fn_as_sberpay_api_order_had_refund($payment_meta) && empty($sync['refund_found'])) {
            fn_set_notification('W', __('warning'), __('addons.as_sberpay_api.receipt_refund_not_found'));
        }

        return [CONTROLLER_STATUS_REDIRECT, 'orders.details?order_id=' . $order_id];
    }
}

