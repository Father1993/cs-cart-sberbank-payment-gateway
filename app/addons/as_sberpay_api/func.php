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

    fn_as_sberpay_api_ensure_meta_table();
}

/**
 * Удаление: убирает processor из БД
 */
function fn_as_sberpay_api_uninstall()
{
    db_query('DELETE FROM ?:payment_processors WHERE processor_script = ?s', 'as_sberpay_api.php');
    db_query('DROP TABLE IF EXISTS ?:sberpay_order_meta');
}

/**
 * Создаёт таблицу метаданных платежа, если модуль обновили без переустановки.
 */
function fn_as_sberpay_api_ensure_meta_table()
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    db_query(
        "CREATE TABLE IF NOT EXISTS ?:sberpay_order_meta (
            order_id INT(11) NOT NULL,
            meta MEDIUMTEXT NOT NULL,
            updated_at INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

/**
 * Достаёт значение именованного атрибута из массива Сбера.
 */
function fn_as_sberpay_api_get_named_value(array $items, $name)
{
    foreach ($items as $item) {
        if (($item['name'] ?? '') === $name) {
            return (string) ($item['value'] ?? '');
        }
    }

    return '';
}

/**
 * Нормализует полезные для 1С реквизиты успешного платежа Сбера.
 */
function fn_as_sberpay_api_prepare_payment_meta(array $response, $gateway_order_id = '')
{
    $status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;

    if (!in_array($status, [1, 2, 4], true)) {
        return [];
    }

    $payment_amount = $response['paymentAmountInfo'] ?? [];
    $card_auth = $response['cardAuthInfo'] ?? [];
    $meta = [
        'provider' => 'sber',
        'order_status' => $status,
        'gateway_order_id' => $gateway_order_id ?: fn_as_sberpay_api_get_named_value($response['attributes'] ?? [], 'mdOrder'),
        'bank_invoice_id' => fn_as_sberpay_api_get_named_value($response['transactionAttributes'] ?? [], 'SbolBankInvoiceId'),
        'md_order' => fn_as_sberpay_api_get_named_value($response['attributes'] ?? [], 'mdOrder'),
        'auth_ref_num' => (string) ($response['authRefNum'] ?? ''),
        'approval_code' => (string) ($card_auth['approvalCode'] ?? ''),
        'payment_state' => (string) ($payment_amount['paymentState'] ?? ''),
        'approved_amount' => isset($payment_amount['approvedAmount']) ? round($payment_amount['approvedAmount'] / 100, 2) : null,
        'deposited_amount' => isset($payment_amount['depositedAmount']) ? round($payment_amount['depositedAmount'] / 100, 2) : null,
        'refunded_amount' => isset($payment_amount['refundedAmount']) ? round($payment_amount['refundedAmount'] / 100, 2) : null,
        'currency' => isset($response['currency']) ? (int) $response['currency'] : null,
        'deposited_date' => isset($response['depositedDate']) ? (int) $response['depositedDate'] : null,
        'terminal_id' => (string) ($response['terminalId'] ?? ''),
        'masked_pan' => (string) ($card_auth['maskedPan'] ?? ''),
        'payment_system' => (string) ($card_auth['paymentSystem'] ?? ''),
        'payment_way' => (string) ($card_auth['paymentWay'] ?? ''),
    ];

    return array_filter($meta, static function ($value) {
        return $value !== '' && $value !== null;
    });
}

/**
 * Нормализует receipts из ответа OFD-сервиса Сбера.
 */
function fn_as_sberpay_api_prepare_receipt_meta(array $response)
{
    if (empty($response['receipts']) || !is_array($response['receipts'])) {
        return [];
    }

    $receipts = [];

    foreach ($response['receipts'] as $receipt) {
        $payload = $receipt['payload'] ?? [];
        $receipts[] = array_filter([
            'receipt_id' => (string) ($receipt['receiptId'] ?? ''),
            'receipt_status' => isset($receipt['receiptStatus']) ? (int) $receipt['receiptStatus'] : null,
            'receipt_type' => (string) ($receipt['receiptType'] ?? ''),
            'operation_type' => (string) ($receipt['operationType'] ?? ''),
            'receipt_datetime' => (string) ($payload['receiptDatetime'] ?? ''),
            'ofd_receipt_url' => (string) ($payload['ofdReceiptUrl'] ?? ''),
            'fn_number' => (string) ($payload['fnNumber'] ?? ''),
            'fiscal_document_number' => isset($payload['fiscalDocumentNumber']) ? (int) $payload['fiscalDocumentNumber'] : null,
            'fiscal_document_attribute' => isset($payload['fiscalDocumentAttribute']) ? (string) $payload['fiscalDocumentAttribute'] : '',
            'ecr_registration_number' => (string) ($payload['ecrRegistrationNumber'] ?? ''),
            'device_code' => (string) ($receipt['deviceCode'] ?? ''),
            'group_code' => (string) ($receipt['groupCode'] ?? ''),
            'external_id' => (string) ($receipt['externalId'] ?? ''),
        ], static function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    $successful_receipts = array_values(array_filter($receipts, static function ($receipt) {
        return (($receipt['receipt_status'] ?? null) === 3);
    }));

    $meta = ['receipts' => $receipts];
    if (!empty($successful_receipts[0])) {
        $meta['prepayment_receipt'] = $successful_receipts[0];
    }
    if (!empty($successful_receipts[1])) {
        $meta['full_payment_receipt'] = $successful_receipts[1];
    }

    return $meta;
}

/**
 * Сохраняет метаданные Сбера, не затирая уже сохранённые поля.
 */
function fn_as_sberpay_api_save_meta($order_id, array $meta)
{
    $order_id = (int) $order_id;
    if (!$order_id || !$meta) {
        return;
    }

    $stored_meta = fn_as_sberpay_api_get_payment_meta($order_id);
    $meta = array_merge($stored_meta, $meta);

    fn_as_sberpay_api_ensure_meta_table();

    db_query('REPLACE INTO ?:sberpay_order_meta ?e', [
        'order_id' => $order_id,
        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'updated_at' => TIME,
    ]);
}

/**
 * Сохраняет метаданные платежа Сбера отдельно от payment_info.
 */
function fn_as_sberpay_api_save_payment_meta($order_id, array $response, $gateway_order_id = '')
{
    $meta = fn_as_sberpay_api_prepare_payment_meta($response, $gateway_order_id);
    if (!$meta) {
        return;
    }

    fn_as_sberpay_api_save_meta($order_id, $meta);
}

/**
 * Сохраняет реквизиты чеков из getReceiptStatus.
 */
function fn_as_sberpay_api_save_receipt_meta($order_id, array $response)
{
    $meta = fn_as_sberpay_api_prepare_receipt_meta($response);
    if (!$meta) {
        return;
    }

    $stored_meta = fn_as_sberpay_api_get_payment_meta($order_id);
    if (!empty($stored_meta['receipts']) && count($stored_meta['receipts']) > count($meta['receipts'] ?? [])) {
        $meta['receipts'] = $stored_meta['receipts'];
    }

    fn_as_sberpay_api_save_meta($order_id, $meta);
}

/**
 * Возвращает число успешно сформированных чеков.
 */
function fn_as_sberpay_api_get_successful_receipts_count(array $response)
{
    $count = 0;

    foreach ((array) ($response['receipts'] ?? []) as $receipt) {
        if (isset($receipt['receiptStatus']) && (int) $receipt['receiptStatus'] === 3) {
            $count++;
        }
    }

    return $count;
}

/**
 * Обновляет мету чеков с небольшим retry, чтобы дождаться готовности OFD.
 */
function fn_as_sberpay_api_sync_receipt_meta($processor, $order_id, $gateway_order_id, $required_successful_receipts = 1, $max_attempts = 3, $sleep_seconds = 2)
{
    if (!$order_id || !$gateway_order_id) {
        return [];
    }

    $last_response = [];

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $last_response = $processor->getReceiptStatus($gateway_order_id);
        fn_as_sberpay_api_save_receipt_meta($order_id, $last_response);

        if (fn_as_sberpay_api_get_successful_receipts_count($last_response) >= $required_successful_receipts) {
            break;
        }

        if ($attempt < $max_attempts) {
            sleep($sleep_seconds);
        }
    }

    return $last_response;
}

/**
 * Возвращает метаданные платежа Сбера по заказу.
 */
function fn_as_sberpay_api_get_payment_meta($order_id)
{
    fn_as_sberpay_api_ensure_meta_table();

    $meta = db_get_field('SELECT meta FROM ?:sberpay_order_meta WHERE order_id = ?i', $order_id);
    if (!$meta) {
        return [];
    }

    $decoded = json_decode($meta, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Возвращает карту метаданных по нескольким заказам.
 */
function fn_as_sberpay_api_get_payment_meta_map(array $order_ids)
{
    $order_ids = array_values(array_unique(array_filter(array_map('intval', $order_ids))));
    if (!$order_ids) {
        return [];
    }

    fn_as_sberpay_api_ensure_meta_table();

    $rows = db_get_hash_array(
        'SELECT order_id, meta FROM ?:sberpay_order_meta WHERE order_id IN (?n)',
        'order_id',
        $order_ids
    );

    foreach ($rows as $order_id => $row) {
        $rows[$order_id] = json_decode($row['meta'], true);
        if (!is_array($rows[$order_id])) {
            unset($rows[$order_id]);
        }
    }

    return $rows;
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
    if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
        return;
    }

    $processor = new \Tygh\Payments\Processors\AsSberPayApi($processor_data);

    if ($processor->isLogging()) {
        $processor->log([
            'order_id' => $order_id,
            'status_from' => $status_from,
            'status_to' => $status_to,
            'payment_id' => $order_info['payment_id'] ?? 0,
            'transaction_id' => $order_info['payment_info']['transaction_id'],
        ], 'change_order_status_post');
    }

    $processor->doReceipt($order_info);
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

/**
 * Добавляет метаданные платежа Сбера в детальную информацию о заказе.
 */
function fn_as_sberpay_api_get_order_info(&$order, $additional_data)
{
    if (empty($order['order_id'])) {
        return;
    }

    $meta = fn_as_sberpay_api_get_payment_meta((int) $order['order_id']);
    if ($meta) {
        $order['sber_payment_meta'] = $meta;
    }
}

/**
 * Добавляет метаданные платежа Сбера в списки заказов.
 */
function fn_as_sberpay_api_get_orders_post($params, &$orders)
{
    if (!$orders) {
        return;
    }

    $meta_map = fn_as_sberpay_api_get_payment_meta_map(array_column($orders, 'order_id'));
    if (!$meta_map) {
        return;
    }

    foreach ($orders as &$order) {
        $order_id = (int) ($order['order_id'] ?? 0);
        if ($order_id && isset($meta_map[$order_id])) {
            $order['sber_payment_meta'] = $meta_map[$order_id];
        }
    }
    unset($order);
}