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
    db_query('DROP TABLE IF EXISTS ?:sberpay_receipt_audit');
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
        'CREATE TABLE IF NOT EXISTS ?:sberpay_order_meta (
            order_id INT(11) NOT NULL,
            meta MEDIUMTEXT NOT NULL,
            updated_at INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
}

/**
 * Создаёт временное хранилище результатов аудита чеков.
 */
function fn_as_sberpay_api_ensure_receipt_audit_table()
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    db_query(
        'CREATE TABLE IF NOT EXISTS ?:sberpay_receipt_audit (
            audit_id INT(11) NOT NULL AUTO_INCREMENT,
            order_id INT(11) NOT NULL DEFAULT 0,
            company_id INT(11) NOT NULL DEFAULT 0,
            gateway_order_id VARCHAR(64) NOT NULL DEFAULT \'\',
            receipt_id VARCHAR(128) NOT NULL DEFAULT \'\',
            detected_role VARCHAR(32) NOT NULL DEFAULT \'\',
            receipt_type VARCHAR(64) NOT NULL DEFAULT \'\',
            operation_type VARCHAR(64) NOT NULL DEFAULT \'\',
            receipt_status INT(11) NOT NULL DEFAULT 0,
            ofd_url TEXT NOT NULL,
            expected_receipt_type VARCHAR(64) NOT NULL DEFAULT \'\',
            expected_payment_method VARCHAR(64) NOT NULL DEFAULT \'\',
            expected_payment_type TINYINT(3) NOT NULL DEFAULT 0,
            needs_review CHAR(1) NOT NULL DEFAULT \'N\',
            raw_receipt MEDIUMTEXT NOT NULL,
            expected_data MEDIUMTEXT NOT NULL,
            error TEXT NOT NULL,
            checked_at INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (audit_id),
            KEY idx_order_id (order_id),
            KEY idx_checked_at (checked_at),
            KEY idx_needs_review (needs_review)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
}

/**
 * Достаёт значение именованного атрибута из массива Сбера.
 */
function fn_as_sberpay_api_get_named_value(array $items, $name)
{
    $name = (string) $name;

    foreach ($items as $item) {
        if ((string) ($item['name'] ?? '') === $name) {
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
 * Банковский orderId из query return (hosted: orderId/mdOrder, SDK Web: bankInvoiceId).
 */
function fn_as_sberpay_api_get_request_gateway_id(array $request)
{
    foreach (['orderId', 'mdOrder', 'bankInvoiceId'] as $key) {
        if (!empty($request[$key])) {
            return (string) $request[$key];
        }
    }

    return '';
}

/**
 * Текст ошибки для пользователя по state из SberPay Web SDK backUrl.
 */
function fn_as_sberpay_api_get_sdk_return_reason(array $request)
{
    $state = !empty($request['state']) ? strtolower((string) $request['state']) : '';

    if ($state === 'cancel') {
        return __('addons.as_sberpay_api.sdk_return_cancel');
    }

    if ($state === 'return') {
        return __('addons.as_sberpay_api.sdk_return_failed');
    }

    return '';
}

/**
 * Сумма для SDK landing: рубли + копейки + знак ₽ (plain text, без HTML-сущностей).
 *
 * @param float|int|string $total
 * @param string           $currency_code
 * @return array{full: string, rubles: string, kopecks: string, has_kopecks: bool, currency: string}
 */
function fn_as_sberpay_api_format_sdk_amount($total, $currency_code = '')
{
    $currencies = \Tygh\Registry::get('currencies');

    if ($currency_code === '') {
        $currency_code = defined('CART_SECONDARY_CURRENCY') ? CART_SECONDARY_CURRENCY : CART_PRIMARY_CURRENCY;
    }

    if (empty($currencies[$currency_code])) {
        $currency_code = CART_PRIMARY_CURRENCY;
    }

    $currency = $currencies[$currency_code];
    $decimals = (int) $currency['decimals'];
    $amount = round((float) $total, $decimals > 0 ? $decimals : 0);

    if (in_array(strtoupper($currency_code), ['RUB', 'RUR'], true)) {
        $rubles_int = (int) floor($amount);
        $kopecks_int = (int) round(($amount - $rubles_int) * 100);

        if ($kopecks_int === 100) {
            $rubles_int++;
            $kopecks_int = 0;
        }

        $rubles = number_format($rubles_int, 0, '', ' ');
        $has_kopecks = $kopecks_int > 0;
        $kopecks = $has_kopecks ? (',' . str_pad((string) $kopecks_int, 2, '0', STR_PAD_LEFT)) : '';
        $full = $has_kopecks ? ($rubles . $kopecks . ' ₽') : ($rubles . ' ₽');

        return [
            'full' => $full,
            'rubles' => $rubles,
            'kopecks' => $kopecks,
            'has_kopecks' => $has_kopecks,
            'currency' => '₽',
        ];
    }

    $dec_sep = (string) ($currency['decimals_separator'] ?: '.');
    $thousands_sep = html_entity_decode(strip_tags((string) $currency['thousands_separator']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($thousands_sep === '') {
        $thousands_sep = ' ';
    }

    $value = number_format($amount, $decimals, $dec_sep, $thousands_sep);
    $chunks = explode($dec_sep, $value, 2);
    $rubles = $chunks[0];
    $kopecks = '';
    $has_kopecks = false;

    if ($decimals > 0 && isset($chunks[1]) && (int) $chunks[1] !== 0) {
        $kopecks = $dec_sep . str_pad($chunks[1], $decimals, '0', STR_PAD_RIGHT);
        $has_kopecks = true;
    }

    $symbol = html_entity_decode(strip_tags((string) $currency['symbol']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $full = ($currency['after'] === 'Y') ? ($rubles . $kopecks . ' ' . $symbol) : ($symbol . $rubles . $kopecks);

    return [
        'full' => $full,
        'rubles' => $rubles,
        'kopecks' => $kopecks,
        'has_kopecks' => $has_kopecks,
        'currency' => $symbol,
    ];
}

/**
 * Контекст landing-оплаты (SDK / SBP) для заказа или пустой массив.
 *
 * @param int          $order_id
 * @param array<string> $modes
 * @return array{order_info: array, processor_data: array, processor: \Tygh\Payments\Processors\AsSberPayApi, gateway_id: string, sbp_payload: string}
 */
function fn_as_sberpay_api_resolve_landing_pay_order($order_id, array $modes)
{
    $order_id = (int) $order_id;
    if (!$order_id) {
        return [];
    }

    $order_info = fn_get_order_info($order_id);
    if (!$order_info) {
        return [];
    }

    $processor_data = fn_get_processor_data($order_info['payment_id']);
    if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
        return [];
    }

    $processor = new \Tygh\Payments\Processors\AsSberPayApi($processor_data);
    $mode_ok = ($processor->isSberPaySdk() && in_array('sberpay_sdk', $modes, true)) ||
        ($processor->isSbpC2b() && in_array('sbp_c2b', $modes, true));
    if (!$mode_ok) {
        return [];
    }

    $gateway_id = (string) ($order_info['payment_info']['transaction_id'] ?? '');
    if ($gateway_id === '') {
        return [];
    }

    $sbp_payload = '';
    if ($processor->isSbpC2b()) {
        $payment_meta = fn_as_sberpay_api_get_payment_meta($order_id);
        $sbp_payload = (string) ($payment_meta['sbp_payload'] ?? '');
        if ($sbp_payload === '') {
            $sbp_payload = (string) ($order_info['payment_info']['sbp_payload'] ?? '');
        }
        if ($sbp_payload === '') {
            return [];
        }
    }

    return [
        'order_info' => $order_info,
        'processor_data' => $processor_data,
        'processor' => $processor,
        'gateway_id' => $gateway_id,
        'sbp_payload' => $sbp_payload,
    ];
}

/**
 * Контекст SDK-landing для заказа или пустой массив.
 *
 * @return array{order_info: array, processor_data: array, processor: \Tygh\Payments\Processors\AsSberPayApi, gateway_id: string}
 */
function fn_as_sberpay_api_resolve_sdk_pay_order($order_id)
{
    return fn_as_sberpay_api_resolve_landing_pay_order($order_id, ['sberpay_sdk']);
}

/**
 * Контекст SBP-landing для заказа или пустой массив.
 *
 * @return array{order_info: array, processor_data: array, processor: \Tygh\Payments\Processors\AsSberPayApi, gateway_id: string, sbp_payload: string}
 */
function fn_as_sberpay_api_resolve_sbp_pay_order($order_id)
{
    return fn_as_sberpay_api_resolve_landing_pay_order($order_id, ['sbp_c2b']);
}

/**
 * Контекст poll/expire СБП: без sbp_payload (достаточно transaction_id).
 *
 * @return array{order_info: array, processor_data: array, processor: \Tygh\Payments\Processors\AsSberPayApi, gateway_id: string}
 */
function fn_as_sberpay_api_resolve_sbp_status_order($order_id)
{
    $order_id = (int) $order_id;
    if (!$order_id) {
        return [];
    }

    $order_info = fn_get_order_info($order_id);
    if (!$order_info) {
        return [];
    }

    $processor_data = fn_get_processor_data($order_info['payment_id']);
    if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
        return [];
    }

    $processor = new \Tygh\Payments\Processors\AsSberPayApi($processor_data);
    if (!$processor->isSbpC2b()) {
        return [];
    }

    $gateway_id = (string) ($order_info['payment_info']['transaction_id'] ?? '');
    if ($gateway_id === '') {
        return [];
    }

    return [
        'order_info' => $order_info,
        'processor_data' => $processor_data,
        'processor' => $processor,
        'gateway_id' => $gateway_id,
    ];
}

/**
 * Заказ СБП уже оплачен (статус CS-Cart, payment_info или meta шлюза).
 */
function fn_as_sberpay_api_is_sbp_payment_settled(array $order_info, $processor, $order_id = 0)
{
    $confirmed = $processor->getConfirmedStatus();
    if (($order_info['status'] ?? '') === $confirmed) {
        return true;
    }

    $payment_info = !empty($order_info['payment_info']) && is_array($order_info['payment_info'])
        ? $order_info['payment_info']
        : [];

    $gateway_state = strtoupper((string) ($payment_info['gateway_status'] ?? ''));
    if (in_array($gateway_state, ['DEPOSITED', 'APPROVED'], true)) {
        return true;
    }

    if (!empty($payment_info['gateway_deposited']) && (float) $payment_info['gateway_deposited'] > 0) {
        return true;
    }

    if ($order_id) {
        $meta = fn_as_sberpay_api_get_payment_meta((int) $order_id);
        if (in_array((int) ($meta['order_status'] ?? -1), [1, 2], true)) {
            return true;
        }
    }

    return false;
}

/**
 * URL редиректа после успешной оплаты СБП.
 */
function fn_as_sberpay_api_get_sbp_complete_url($order_id, $protocol = 'current')
{
    if ($protocol === 'current') {
        $protocol = (defined('HTTPS') && HTTPS) ? 'https' : 'http';
    }

    return fn_url('checkout.complete?order_id=' . (int) $order_id, AREA, $protocol);
}

/**
 * Список банков НСПК для виджета СБП (proxy widget.cbrpay.ru, SBP.md п.3).
 *
 * @return array{members?: array, version?: string}
 */
function fn_as_sberpay_api_fetch_sbp_widget_members($sbp_payload, $platform = 'android')
{
    $sbp_payload = trim((string) $sbp_payload);
    if ($sbp_payload === '' || strpos($sbp_payload, 'http') !== 0) {
        return ['members' => []];
    }

    if (!in_array($platform, ['ios', 'android', 'desktop'], true)) {
        $platform = 'android';
    }

    $client = preg_replace('/[^a-z0-9]/i', '', (string) \Tygh\Registry::get('config.http_host'));
    if (strlen($client) < 2) {
        $client = 'uroven';
    }
    $client = substr($client, 0, 64);

    $ch = curl_init('https://widget.cbrpay.ru/v1/members');
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-CLIENT: ' . $client,
            'X-PLATFORM: ' . $platform,
            'X-PAYLOAD: ' . $sbp_payload,
        ],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    if (!$raw) {
        return ['members' => []];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : ['members' => []];
}

/**
 * Сохраняет данные register СБП в meta (не в payment_info — parity с SDK для 1С).
 */
function fn_as_sberpay_api_save_sbp_register_meta($order_id, $sbp_payload, $qrc_id, $gateway_order_id)
{
    $meta = array_filter([
        'sbp_payload' => trim((string) $sbp_payload),
        'qrc_id' => trim((string) $qrc_id),
        'gateway_order_id' => trim((string) $gateway_order_id),
    ], static function ($value) {
        return $value !== '';
    });

    if (!$meta) {
        return;
    }

    fn_as_sberpay_api_save_meta((int) $order_id, $meta);
}

/**
 * Проверяет оплату СБП в шлюзе и финализирует заказ (идемпотентно, как callback).
 *
 * @return array{paid: bool, status?: string, redirect?: string}
 */
function fn_as_sberpay_api_try_finalize_sbp_payment($order_id)
{
    $order_id = (int) $order_id;
    $ctx = fn_as_sberpay_api_resolve_sbp_status_order($order_id);
    if (!$ctx) {
        return ['paid' => false, 'status' => 'invalid'];
    }

    /** @var \Tygh\Payments\Processors\AsSberPayApi $processor */
    $processor = $ctx['processor'];
    $order_info = $ctx['order_info'];
    $confirmed = $processor->getConfirmedStatus();
    $redirect = fn_as_sberpay_api_get_sbp_complete_url($order_id);

    if (fn_as_sberpay_api_is_sbp_payment_settled($order_info, $processor, $order_id)) {
        return ['paid' => true, 'status' => $confirmed, 'redirect' => $redirect];
    }

    $gateway_id = $ctx['gateway_id'];
    $response = $processor->getOrderStatusExtended($gateway_id);
    if ($processor->isError()) {
        return ['paid' => false, 'status' => (string) ($order_info['status'] ?? '')];
    }

    fn_as_sberpay_api_save_payment_meta($order_id, $response, $gateway_id);

    $order_status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;
    if (in_array($order_status, [1, 2], true) && $processor->usesOrderBundle()) {
        $processor->refreshClosingReceiptMeta($order_id, $gateway_id, 'payment_confirmed');
    }

    $pp_response = fn_as_sberpay_api_build_response($response, $processor);
    if (($pp_response['order_status'] ?? '') === $confirmed) {
        fn_finish_payment($order_id, $pp_response);
        fn_order_placement_routines('save', $order_id, [], false);

        return ['paid' => true, 'status' => $confirmed, 'redirect' => $redirect];
    }

    return [
        'paid' => false,
        'status' => (string) ($pp_response['order_status'] ?? ($order_info['status'] ?? '')),
    ];
}

/**
 * Истечение ожидания оплаты на landing: финальная проверка шлюза, иначе fail (O/N → F).
 *
 * @return array{paid?: bool, expired?: bool, status?: string, redirect?: string}
 */
function fn_as_sberpay_api_try_expire_sbp_payment($order_id)
{
    $order_id = (int) $order_id;
    $finalize = fn_as_sberpay_api_try_finalize_sbp_payment($order_id);
    if (!empty($finalize['paid'])) {
        return $finalize;
    }

    $ctx = fn_as_sberpay_api_resolve_sbp_status_order($order_id);
    if (!$ctx) {
        return ['expired' => false, 'status' => 'invalid'];
    }

    /** @var \Tygh\Payments\Processors\AsSberPayApi $processor */
    $processor = $ctx['processor'];
    $order_info = fn_get_order_info($order_id) ?: $ctx['order_info'];
    $confirmed = $processor->getConfirmedStatus();
    $status = (string) ($order_info['status'] ?? '');
    $redirect = fn_url('orders.details?order_id=' . $order_id, AREA, 'current');

    if (fn_as_sberpay_api_is_sbp_payment_settled($order_info, $processor, $order_id)) {
        return [
            'paid' => true,
            'status' => $confirmed,
            'redirect' => fn_as_sberpay_api_get_sbp_complete_url($order_id),
        ];
    }

    if ($status === $confirmed) {
        return [
            'paid' => true,
            'status' => $confirmed,
            'redirect' => fn_as_sberpay_api_get_sbp_complete_url($order_id),
        ];
    }

    if ($status === 'F') {
        return ['expired' => true, 'status' => 'F', 'redirect' => $redirect];
    }

    if (!in_array($status, ['O', 'N'], true)) {
        return ['expired' => false, 'status' => $status];
    }

    $reason = __('addons.as_sberpay_api.sbp_pay_expired');
    fn_finish_payment($order_id, [
        'order_status' => 'F',
        'reason_text' => $reason,
    ]);

    return ['expired' => true, 'status' => 'F', 'redirect' => $redirect];
}

/**
 * Редирект после неуспешной оплаты: заказ уже создан, корзина пуста — ведём на детали заказа.
 */
function fn_as_sberpay_api_route_after_payment($order_id, array $pp_response, $processor)
{
    $order_id = (int) $order_id;
    $confirmed_status = $processor->getConfirmedStatus();

    if (($pp_response['order_status'] ?? '') === $confirmed_status) {
        fn_order_placement_routines('route', $order_id, [], false);
        exit;
    }

    $reason = !empty($pp_response['reason_text'])
        ? (string) $pp_response['reason_text']
        : ($processor->isSbpC2b()
            ? __('addons.as_sberpay_api.sbp_return_failed')
            : __('addons.as_sberpay_api.sdk_return_failed'));

    fn_set_notification('W', __('important'), $reason);
    fn_redirect('orders.details?order_id=' . $order_id);
    exit;
}

/**
 * Отмена landing-оплаты (SDK / SBP): локальный fail-flow, без отмены в банке.
 *
 * @param int          $order_id
 * @param array<string> $modes
 * @return bool|string paid|already_failed|false
 */
function fn_as_sberpay_api_cancel_landing_payment($order_id, array $modes)
{
    $ctx = fn_as_sberpay_api_resolve_landing_pay_order($order_id, $modes);
    if (!$ctx) {
        return false;
    }

    $processor = $ctx['processor'];
    $confirmed = $processor->getConfirmedStatus();
    $status = $ctx['order_info']['status'] ?? '';

    if ($status === $confirmed) {
        return 'paid';
    }

    $reason = $processor->isSbpC2b()
        ? __('addons.as_sberpay_api.sbp_return_cancel')
        : __('addons.as_sberpay_api.sdk_return_cancel');

    if ($status === 'F') {
        fn_set_notification('W', __('important'), $reason);
        fn_redirect('orders.details?order_id=' . (int) $order_id);
        exit;
    }

    fn_finish_payment($order_id, [
        'order_status' => 'F',
        'reason_text' => $reason,
    ]);
    fn_as_sberpay_api_route_after_payment($order_id, [
        'order_status' => 'F',
        'reason_text' => $reason,
    ], $processor);

    return true;
}

/**
 * Отмена SDK-оплаты с переходом на страницу заказа.
 */
function fn_as_sberpay_api_cancel_sdk_payment($order_id)
{
    return fn_as_sberpay_api_cancel_landing_payment($order_id, ['sberpay_sdk']);
}

/**
 * Формирует pp_response на основе ответа getOrderStatusExtended.
 */
function fn_as_sberpay_api_build_response($response, $processor)
{
    $status = isset($response['orderStatus']) ? (int) $response['orderStatus'] : -1;
    $pai = !empty($response['paymentAmountInfo']) ? $response['paymentAmountInfo'] : [];

    if ($status === 1 || $status === 2) {
        return [
            'order_status' => $processor->getConfirmedStatus(),
            'gateway_status' => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved' => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded' => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    if ($status === 4) {
        return [
            'gateway_status' => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved' => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded' => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    if ($status === 3) {
        return [
            'order_status' => 'F',
            'gateway_status' => !empty($pai['paymentState']) ? $pai['paymentState'] : '',
            'gateway_approved' => !empty($pai['approvedAmount']) ? $pai['approvedAmount'] / 100 : 0,
            'gateway_deposited' => !empty($pai['depositedAmount']) ? $pai['depositedAmount'] / 100 : 0,
            'gateway_refunded' => !empty($pai['refundedAmount']) ? $pai['refundedAmount'] / 100 : 0,
        ];
    }

    return [
        'order_status' => 'F',
        'reason_text' => !empty($response['actionCodeDescription'])
            ? $response['actionCodeDescription']
            : (!empty($response['errorMessage']) ? $response['errorMessage'] : 'Оплата не прошла'),
    ];
}

/**
 * Сохраняет служебные данные возврата, не затрагивая базовую мету платежа.
 */
function fn_as_sberpay_api_save_refund_meta($order_id, array $refund_meta)
{
    if (!$refund_meta) {
        return;
    }

    fn_as_sberpay_api_save_meta((int) $order_id, [
        'refund' => array_filter($refund_meta, static function ($value) {
            return $value !== '' && $value !== null;
        }),
    ]);
}

/**
 * Сохраняет неизменяемый snapshot фискальной корзины, который ушёл в Сбер при оплате.
 */
function fn_as_sberpay_api_save_fiscal_snapshot($order_id, array $order_info, array $register_context, $gateway_order_id)
{
    $order_bundle = !empty($register_context['order_bundle']) && is_array($register_context['order_bundle'])
        ? $register_context['order_bundle']
        : [];

    if (!$order_bundle || empty($gateway_order_id)) {
        return;
    }

    $items = !empty($order_bundle['cartItems']['items']) && is_array($order_bundle['cartItems']['items'])
        ? array_values($order_bundle['cartItems']['items'])
        : [];

    $amount_minor = isset($register_context['amount']) ? (int) $register_context['amount'] : (int) ($order_bundle['total'] ?? 0);

    fn_as_sberpay_api_save_meta((int) $order_id, [
        'fiscal_snapshot' => [
            'provider' => 'sber',
            'snapshot_version' => 1,
            'created_at' => TIME,
            'order_id' => (int) $order_id,
            'payment_id' => (int) ($order_info['payment_id'] ?? 0),
            'company_id' => (int) ($order_info['company_id'] ?? 0),
            'order_number' => (string) ($register_context['order_number'] ?? ''),
            'gateway_order_id' => (string) $gateway_order_id,
            'transaction_id' => (string) $gateway_order_id,
            'amount_minor' => $amount_minor,
            'paid_total_minor' => $amount_minor,
            'refundable_total_minor' => $amount_minor,
            'currency' => (string) ($order_info['secondary_currency'] ?? $order_info['currency'] ?? CART_PRIMARY_CURRENCY),
            'order_bundle' => $order_bundle,
            'company' => !empty($order_bundle['company']) && is_array($order_bundle['company']) ? $order_bundle['company'] : [],
            'payments' => !empty($order_bundle['payments']) && is_array($order_bundle['payments']) ? array_values($order_bundle['payments']) : [],
            'total' => (int) ($order_bundle['total'] ?? $amount_minor),
            'items' => $items,
        ],
    ]);
}

/**
 * Абсолютный http(s) URL чека ОФД (без percent-encoding всей строки).
 */
function fn_as_sberpay_api_normalize_ofd_receipt_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    for ($i = 0; $i < 3; $i++) {
        if (preg_match('#^https?://#i', $url)) {
            break;
        }
        $decoded = rawurldecode($url);
        if ($decoded === $url) {
            return '';
        }
        $url = $decoded;
    }

    if (!preg_match('#^https?://#i', $url)) {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    return $url;
}

/**
 * @param array<string, mixed> $meta
 */
function fn_as_sberpay_api_sanitize_receipt_urls_in_meta(array &$meta)
{
    foreach (['prepayment_receipt', 'closing_receipt', 'refund_receipt'] as $key) {
        if (empty($meta[$key]['receipt_url'])) {
            continue;
        }
        $url = fn_as_sberpay_api_normalize_ofd_receipt_url($meta[$key]['receipt_url']);
        if ($url !== '') {
            $meta[$key]['receipt_url'] = $url;
        } else {
            unset($meta[$key]['receipt_url']);
        }
    }
}

/**
 * Извлекает receipt_url и receipt_id из элемента receipts[] ответа getReceiptStatus.
 *
 * Prod OFD v1: receipts[].payload.ofdReceiptUrl; fallback — корень чека и legacy ofd_receipt_url.
 */
function fn_as_sberpay_api_extract_receipt_url_fields(array $receipt)
{
    $payload = !empty($receipt['payload']) && is_array($receipt['payload']) ? $receipt['payload'] : [];
    $url_candidates = [
        $payload['ofdReceiptUrl'] ?? '',
        $payload['ofd_receipt_url'] ?? '',
        $receipt['ofdReceiptUrl'] ?? '',
        $receipt['ofd_receipt_url'] ?? '',
    ];

    $fields = [];
    foreach ($url_candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }

        $normalized = fn_as_sberpay_api_normalize_ofd_receipt_url($candidate);
        if ($normalized !== '') {
            $fields['receipt_url'] = $normalized;
            break;
        }
    }

    foreach (['receiptId', 'uuid', 'externalId'] as $id_key) {
        $receipt_id = trim((string) ($receipt[$id_key] ?? ''));
        if ($receipt_id !== '') {
            $fields['receipt_id'] = $receipt_id;
            break;
        }
    }

    return $fields;
}

/**
 * @param string $meta_key prepayment_receipt|closing_receipt|refund_receipt
 */
function fn_as_sberpay_api_merge_save_receipt_meta($order_id, $meta_key, array $receipt_meta)
{
    if (!$receipt_meta) {
        return;
    }

    $filtered = array_filter($receipt_meta, static function ($value) {
        return $value !== '' && $value !== null;
    });
    if (!$filtered) {
        return;
    }

    $stored = fn_as_sberpay_api_get_payment_meta((int) $order_id);
    $existing = !empty($stored[$meta_key]) && is_array($stored[$meta_key]) ? $stored[$meta_key] : [];

    fn_as_sberpay_api_save_meta((int) $order_id, [
        $meta_key => array_merge($existing, $filtered),
    ]);
}

/**
 * Сохраняет служебные данные закрывающего чека.
 */
function fn_as_sberpay_api_save_closing_receipt_meta($order_id, array $closing_receipt_meta)
{
    fn_as_sberpay_api_merge_save_receipt_meta((int) $order_id, 'closing_receipt', $closing_receipt_meta);
}

/**
 * Сохраняет служебные данные чека предоплаты.
 */
function fn_as_sberpay_api_save_prepayment_receipt_meta($order_id, array $prepayment_receipt_meta)
{
    fn_as_sberpay_api_merge_save_receipt_meta((int) $order_id, 'prepayment_receipt', $prepayment_receipt_meta);
}

/**
 * Сохраняет служебные данные фискального чека возврата (OFD sell_refund).
 */
function fn_as_sberpay_api_save_refund_receipt_meta($order_id, array $refund_receipt_meta)
{
    fn_as_sberpay_api_merge_save_receipt_meta((int) $order_id, 'refund_receipt', $refund_receipt_meta);
}

/**
 * Сохраняет snapshot закрывающего чека полного расчёта.
 */
function fn_as_sberpay_api_save_closing_receipt_snapshot($order_id, array $source_snapshot, array $order_bundle, $gateway_order_id)
{
    if (!$order_bundle || empty($gateway_order_id)) {
        return;
    }

    $items = !empty($order_bundle['cartItems']['items']) && is_array($order_bundle['cartItems']['items'])
        ? array_values($order_bundle['cartItems']['items'])
        : [];
    $payments = !empty($order_bundle['payments']) && is_array($order_bundle['payments'])
        ? array_values($order_bundle['payments'])
        : [];
    $amount_minor = isset($order_bundle['total']) ? (int) $order_bundle['total'] : 0;

    fn_as_sberpay_api_save_meta((int) $order_id, [
        'closing_receipt_snapshot' => [
            'provider' => 'sber',
            'snapshot_version' => 1,
            'created_at' => TIME,
            'order_id' => (int) $order_id,
            'gateway_order_id' => (string) $gateway_order_id,
            'transaction_id' => (string) $gateway_order_id,
            'amount_minor' => $amount_minor,
            'currency' => (string) ($source_snapshot['currency'] ?? ''),
            'order_number' => (string) ($source_snapshot['order_number'] ?? ''),
            'receipt_type' => (string) ($order_bundle['receiptType'] ?? 'SELL'),
            'order_bundle' => $order_bundle,
            'company' => !empty($order_bundle['company']) && is_array($order_bundle['company']) ? $order_bundle['company'] : [],
            'payments' => $payments,
            'total' => $amount_minor,
            'items' => $items,
            'status' => 'succeeded',
        ],
    ]);
}

/**
 * Считает сумму товарных строк immutable snapshot в копейках.
 */
function fn_as_sberpay_api_get_snapshot_items_total_minor(array $snapshot)
{
    $items = !empty($snapshot['items']) && is_array($snapshot['items'])
        ? array_values($snapshot['items'])
        : [];

    if (!$items && !empty($snapshot['order_bundle']['cartItems']['items']) && is_array($snapshot['order_bundle']['cartItems']['items'])) {
        $items = array_values($snapshot['order_bundle']['cartItems']['items']);
    }

    $total_minor = 0;
    foreach ($items as $item) {
        $total_minor += (int) ($item['itemAmount'] ?? 0);
    }

    return $total_minor;
}

/**
 * Возвращает актуальную фискальную основу заказа.
 */
function fn_as_sberpay_api_get_active_fiscal_snapshot(array $meta)
{
    $closing_receipt_succeeded = !empty($meta['closing_receipt']['status']) &&
        $meta['closing_receipt']['status'] === 'succeeded';

    if (!empty($meta['closing_receipt_snapshot']) && is_array($meta['closing_receipt_snapshot'])) {
        return $meta['closing_receipt_snapshot'];
    }

    if ($closing_receipt_succeeded) {
        return [];
    }

    if (!empty($meta['fiscal_snapshot']) && is_array($meta['fiscal_snapshot'])) {
        return $meta['fiscal_snapshot'];
    }

    return [];
}

/**
 * Возвращает refund-ready orderBundle на основе исходного snapshot
 * только для безопасного полного возврата всей фискальной корзины.
 *
 * Если сумма возврата не совпадает с полной суммой snapshot, то безопасный bundle
 * нельзя собрать без информации о ранее возвращённых строках.
 */
function fn_as_sberpay_api_build_refund_bundle_from_snapshot(array $snapshot, $target_amount_minor = null)
{
    $order_bundle = !empty($snapshot['order_bundle']) && is_array($snapshot['order_bundle'])
        ? $snapshot['order_bundle']
        : [];

    if (!$order_bundle) {
        return [];
    }

    $items = !empty($snapshot['items']) && is_array($snapshot['items'])
        ? array_values($snapshot['items'])
        : [];

    if (!$items) {
        $items = !empty($order_bundle['cartItems']['items']) && is_array($order_bundle['cartItems']['items'])
            ? array_values($order_bundle['cartItems']['items'])
            : [];
    }

    if (!$items) {
        return [];
    }

    $source_total_minor = fn_as_sberpay_api_get_snapshot_items_total_minor([
        'items' => $items,
        'order_bundle' => $order_bundle,
    ]);

    $target_amount_minor = $target_amount_minor === null
        ? $source_total_minor
        : (int) $target_amount_minor;

    if ($target_amount_minor <= 0 || $target_amount_minor !== $source_total_minor) {
        return [];
    }

    $payment_type = 1;
    if (!empty($snapshot['payments'][0]['type'])) {
        $payment_type = (int) $snapshot['payments'][0]['type'];
    } elseif (!empty($order_bundle['payments'][0]['type'])) {
        $payment_type = (int) $order_bundle['payments'][0]['type'];
    }

    $order_bundle['receiptType'] = 'SELL_REFUND';
    $order_bundle['company'] = !empty($snapshot['company']) && is_array($snapshot['company'])
        ? $snapshot['company']
        : (!empty($order_bundle['company']) && is_array($order_bundle['company']) ? $order_bundle['company'] : []);
    $order_bundle['payments'] = [[
        'type' => $payment_type > 0 ? $payment_type : 1,
        'sum' => $target_amount_minor,
    ]];
    $order_bundle['total'] = $target_amount_minor;
    $order_bundle['cartItems'] = ['items' => $items];

    return $order_bundle;
}

/**
 * Готовит блок данных, которого достаточно для прямого refund.do из 1С в Сбер.
 */
function fn_as_sberpay_api_build_refund_context(array $meta)
{
    $snapshot = fn_as_sberpay_api_get_active_fiscal_snapshot($meta);
    $closing_receipt_succeeded = !empty($meta['closing_receipt']['status']) &&
        $meta['closing_receipt']['status'] === 'succeeded';

    if (!$snapshot) {
        return $closing_receipt_succeeded
            ? [
                'provider' => 'sber',
                'refund_order_bundle_ready' => false,
                'requires_bundle_rebuild_in_1c' => true,
                'missing_closing_receipt_snapshot' => true,
                'refund_strategy' => 'direct_1c_to_sber',
                'refund_method' => 'refund.do',
            ]
            : [];
    }

    $gateway_order_id = (string) ($meta['gateway_order_id'] ?? $snapshot['gateway_order_id'] ?? $snapshot['transaction_id'] ?? '');
    $amount_minor = isset($snapshot['amount_minor']) ? (int) $snapshot['amount_minor'] : (int) ($snapshot['total'] ?? 0);
    $refunded_amount_minor = isset($meta['refunded_amount']) ? (int) round((float) $meta['refunded_amount'] * 100) : 0;
    $refundable_amount_minor = max(0, $amount_minor - $refunded_amount_minor);
    $refund_order_bundle = fn_as_sberpay_api_build_refund_bundle_from_snapshot($snapshot, $refundable_amount_minor);
    $has_snapshot_items = !empty($snapshot['items']) && is_array($snapshot['items']);
    $can_refund_from_1c = $gateway_order_id !== '' && $refundable_amount_minor > 0 && $has_snapshot_items;
    $needs_1c_bundle_rebuild = $can_refund_from_1c && empty($refund_order_bundle);

    return [
        'can_refund_from_1c' => $can_refund_from_1c,
        'provider' => 'sber',
        'refund_strategy' => 'direct_1c_to_sber',
        'refund_method' => 'refund.do',
        'order_number' => (string) ($snapshot['order_number'] ?? ''),
        'gateway_order_id' => $gateway_order_id,
        'transaction_id' => (string) ($snapshot['transaction_id'] ?? $gateway_order_id),
        'md_order' => (string) ($meta['md_order'] ?? ''),
        'bank_invoice_id' => (string) ($meta['bank_invoice_id'] ?? ''),
        'currency' => (string) ($snapshot['currency'] ?? $meta['currency'] ?? ''),
        'snapshot_version' => (int) ($snapshot['snapshot_version'] ?? 1),
        'amount_minor' => $amount_minor,
        'already_refunded_amount_minor' => $refunded_amount_minor,
        'refundable_amount_minor' => $refundable_amount_minor,
        'refund_order_bundle_ready' => !$needs_1c_bundle_rebuild,
        'requires_bundle_rebuild_in_1c' => $needs_1c_bundle_rebuild,
        'missing_closing_receipt_snapshot' => false,
        'external_refund_id_prefix' => 'refund-' . (string) ($snapshot['order_id'] ?? '') . '-',
        'external_refund_id_pattern' => 'refund-{order_id}-{unique_suffix}',
        'company' => !empty($snapshot['company']) && is_array($snapshot['company']) ? $snapshot['company'] : [],
        'payments' => !empty($snapshot['payments']) && is_array($snapshot['payments']) ? array_values($snapshot['payments']) : [],
        'items' => !empty($snapshot['items']) && is_array($snapshot['items']) ? array_values($snapshot['items']) : [],
        'order_bundle' => !empty($snapshot['order_bundle']) && is_array($snapshot['order_bundle']) ? $snapshot['order_bundle'] : [],
        'refund_order_bundle' => $refund_order_bundle,
    ];
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
    if (!is_array($decoded)) {
        return [];
    }

    fn_as_sberpay_api_sanitize_receipt_urls_in_meta($decoded);

    return $decoded;
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
            continue;
        }
        fn_as_sberpay_api_sanitize_receipt_urls_in_meta($rows[$order_id]);
    }

    return $rows;
}

/**
 * Возвращает параметры аудита чеков из request.
 */
function fn_as_sberpay_api_get_receipt_audit_params(array $request)
{
    $limit = !empty($request['limit']) ? (int) $request['limit'] : 50;
    $limit = max(1, min(200, $limit));

    return [
        'order_id' => !empty($request['order_id']) ? (int) $request['order_id'] : 0,
        'date_from' => trim((string) ($request['date_from'] ?? '')),
        'date_to' => trim((string) ($request['date_to'] ?? '')),
        'limit' => $limit,
        'only_review' => !empty($request['only_review']) && $request['only_review'] === 'Y' ? 'Y' : 'N',
    ];
}

/**
 * Выбирает SberPay-заказы для audit-only проверки чеков.
 */
function fn_as_sberpay_api_get_receipt_audit_order_ids(array $params)
{
    $condition = db_quote(' AND pp.processor_script = ?s', 'as_sberpay_api.php');
    $company_id = (int) \Tygh\Registry::get('runtime.company_id');

    if ($company_id) {
        $condition .= db_quote(' AND o.company_id = ?i', $company_id);
    }
    if (!empty($params['order_id'])) {
        $condition .= db_quote(' AND o.order_id = ?i', (int) $params['order_id']);
    }
    if (!empty($params['date_from'])) {
        $date_from = strtotime((string) $params['date_from']);
        if ($date_from) {
            $condition .= db_quote(' AND o.timestamp >= ?i', $date_from);
        }
    }
    if (!empty($params['date_to'])) {
        $date_to = strtotime((string) $params['date_to']);
        if ($date_to) {
            $condition .= db_quote(' AND o.timestamp <= ?i', $date_to + 86399);
        }
    }

    return db_get_fields(
        'SELECT o.order_id'
        . ' FROM ?:orders AS o'
        . ' INNER JOIN ?:sberpay_order_meta AS som ON som.order_id = o.order_id'
        . ' INNER JOIN ?:payments AS p ON p.payment_id = o.payment_id'
        . ' INNER JOIN ?:payment_processors AS pp ON pp.processor_id = p.processor_id'
        . ' WHERE 1 ?p'
        . ' ORDER BY o.timestamp DESC'
        . ' LIMIT ?i',
        $condition,
        (int) $params['limit']
    );
}

/**
 * Ожидаемые признаки чека по текущей корректной логике модуля.
 */
function fn_as_sberpay_api_get_receipt_audit_expected($role, array $meta)
{
    $expected = [
        'receipt_type' => '',
        'payment_method' => '',
        'payment_type' => 0,
        'snapshot_found' => false,
    ];

    if ($role === 'prepayment') {
        $snapshot = !empty($meta['fiscal_snapshot']) && is_array($meta['fiscal_snapshot'])
            ? $meta['fiscal_snapshot']
            : [];
        $bundle = !empty($snapshot['order_bundle']) && is_array($snapshot['order_bundle'])
            ? $snapshot['order_bundle']
            : [];
        $items = !empty($snapshot['items']) && is_array($snapshot['items'])
            ? array_values($snapshot['items'])
            : (!empty($bundle['cartItems']['items']) && is_array($bundle['cartItems']['items']) ? array_values($bundle['cartItems']['items']) : []);

        $expected['receipt_type'] = (string) ($bundle['receiptType'] ?? 'SELL');
        $expected['payment_method'] = (string) ($items[0]['paymentMethod'] ?? 'full_prepayment');
        $expected['payment_type'] = (int) ($snapshot['payments'][0]['type'] ?? $bundle['payments'][0]['type'] ?? 1);
        $expected['snapshot_found'] = !empty($snapshot);
    } elseif ($role === 'closing') {
        $snapshot = !empty($meta['closing_receipt_snapshot']) && is_array($meta['closing_receipt_snapshot'])
            ? $meta['closing_receipt_snapshot']
            : [];

        $expected['receipt_type'] = 'SELL';
        $expected['payment_method'] = 'full_payment';
        $expected['payment_type'] = 2;
        $expected['snapshot_found'] = !empty($snapshot);
    } elseif ($role === 'refund') {
        $refund_context = fn_as_sberpay_api_build_refund_context($meta);
        $bundle = !empty($refund_context['refund_order_bundle']) && is_array($refund_context['refund_order_bundle'])
            ? $refund_context['refund_order_bundle']
            : [];
        $items = !empty($bundle['cartItems']['items']) && is_array($bundle['cartItems']['items'])
            ? array_values($bundle['cartItems']['items'])
            : [];

        $expected['receipt_type'] = 'SELL_REFUND';
        $expected['payment_method'] = (string) ($items[0]['paymentMethod'] ?? '');
        $expected['payment_type'] = (int) ($bundle['payments'][0]['type'] ?? 0);
        $expected['snapshot_found'] = !empty($bundle);
    }

    return $expected;
}

/**
 * Определяет роль чека из ответа OFD.
 */
function fn_as_sberpay_api_detect_receipt_audit_role(array $receipt, &$sell_index)
{
    $receipt_type = strtolower((string) ($receipt['receiptType'] ?? ''));
    $operation_type = strtoupper((string) ($receipt['operationType'] ?? ''));

    if ($receipt_type === 'sell_refund' || $operation_type === 'REFUND') {
        return 'refund';
    }

    if ($receipt_type === 'sell') {
        $sell_index++;

        return $sell_index === 1 ? 'prepayment' : 'closing';
    }

    return 'unknown';
}

/**
 * Нужна ли ручная проверка строки аудита.
 */
function fn_as_sberpay_api_receipt_audit_needs_review(array $receipt, $role, array $expected)
{
    $receipt_type = strtolower((string) ($receipt['receiptType'] ?? ''));
    $status = (int) ($receipt['receiptStatus'] ?? 0);

    if ($status !== 3 || $role === 'unknown') {
        return true;
    }

    if ($role === 'prepayment') {
        return strtolower((string) $expected['receipt_type']) !== 'sell'
            || (string) $expected['payment_method'] !== 'full_prepayment'
            || (int) $expected['payment_type'] !== 1;
    }

    if ($role === 'closing') {
        return $receipt_type !== 'sell'
            || (string) $expected['payment_method'] !== 'full_payment'
            || (int) $expected['payment_type'] !== 2
            || empty($expected['snapshot_found']);
    }

    if ($role === 'refund') {
        return $receipt_type !== 'sell_refund' || empty($expected['snapshot_found']);
    }

    return false;
}

/**
 * Сохраняет одну строку audit-only проверки.
 */
function fn_as_sberpay_api_save_receipt_audit_row(array $row)
{
    fn_as_sberpay_api_ensure_receipt_audit_table();

    db_query('INSERT INTO ?:sberpay_receipt_audit ?e', [
        'order_id' => (int) ($row['order_id'] ?? 0),
        'company_id' => (int) ($row['company_id'] ?? 0),
        'gateway_order_id' => (string) ($row['gateway_order_id'] ?? ''),
        'receipt_id' => (string) ($row['receipt_id'] ?? ''),
        'detected_role' => (string) ($row['detected_role'] ?? ''),
        'receipt_type' => (string) ($row['receipt_type'] ?? ''),
        'operation_type' => (string) ($row['operation_type'] ?? ''),
        'receipt_status' => (int) ($row['receipt_status'] ?? 0),
        'ofd_url' => (string) ($row['ofd_url'] ?? ''),
        'expected_receipt_type' => (string) ($row['expected_receipt_type'] ?? ''),
        'expected_payment_method' => (string) ($row['expected_payment_method'] ?? ''),
        'expected_payment_type' => (int) ($row['expected_payment_type'] ?? 0),
        'needs_review' => !empty($row['needs_review']) ? 'Y' : 'N',
        'raw_receipt' => json_encode($row['raw_receipt'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'expected_data' => json_encode($row['expected_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'error' => (string) ($row['error'] ?? ''),
        'checked_at' => TIME,
    ]);
}

/**
 * Audit-only проверка одного заказа через OFD getReceiptStatus.
 */
function fn_as_sberpay_api_run_receipt_audit_for_order($order_id)
{
    fn_as_sberpay_api_ensure_receipt_audit_table();

    $order_id = (int) $order_id;
    $order_info = fn_get_order_info($order_id, false, false);
    if (!$order_info) {
        return ['checked' => 0, 'review' => 0, 'errors' => 1];
    }

    db_query('DELETE FROM ?:sberpay_receipt_audit WHERE order_id = ?i', $order_id);

    $processor_data = fn_get_processor_data((int) $order_info['payment_id']);
    $gateway_order_id = (string) ($order_info['payment_info']['transaction_id'] ?? '');
    if ($gateway_order_id === '') {
        $meta = fn_as_sberpay_api_get_payment_meta($order_id);
        $gateway_order_id = (string) ($meta['gateway_order_id'] ?? '');
    }

    if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php' || $gateway_order_id === '') {
        fn_as_sberpay_api_save_receipt_audit_row([
            'order_id' => $order_id,
            'company_id' => (int) ($order_info['company_id'] ?? 0),
            'gateway_order_id' => $gateway_order_id,
            'needs_review' => true,
            'error' => 'SberPay processor or gateway order id is missing',
        ]);

        return ['checked' => 0, 'review' => 1, 'errors' => 1];
    }

    $processor = new \Tygh\Payments\Processors\AsSberPayApi($processor_data);
    $response = $processor->getReceiptStatus($gateway_order_id);
    if ($processor->isError()) {
        fn_as_sberpay_api_save_receipt_audit_row([
            'order_id' => $order_id,
            'company_id' => (int) ($order_info['company_id'] ?? 0),
            'gateway_order_id' => $gateway_order_id,
            'needs_review' => true,
            'raw_receipt' => $response,
            'error' => $processor->getErrorText(),
        ]);

        return ['checked' => 0, 'review' => 1, 'errors' => 1];
    }

    $receipts = !empty($response['receipts']) && is_array($response['receipts']) ? $response['receipts'] : [];
    if (!$receipts) {
        fn_as_sberpay_api_save_receipt_audit_row([
            'order_id' => $order_id,
            'company_id' => (int) ($order_info['company_id'] ?? 0),
            'gateway_order_id' => $gateway_order_id,
            'needs_review' => true,
            'raw_receipt' => $response,
            'error' => 'No receipts returned by OFD',
        ]);

        return ['checked' => 0, 'review' => 1, 'errors' => 1];
    }

    $meta = fn_as_sberpay_api_get_payment_meta($order_id);
    $sell_index = 0;
    $stats = ['checked' => 0, 'review' => 0, 'errors' => 0];

    foreach ($receipts as $receipt) {
        if (!is_array($receipt)) {
            continue;
        }

        $role = fn_as_sberpay_api_detect_receipt_audit_role($receipt, $sell_index);
        $expected = fn_as_sberpay_api_get_receipt_audit_expected($role, $meta);
        $needs_review = fn_as_sberpay_api_receipt_audit_needs_review($receipt, $role, $expected);
        $url_fields = fn_as_sberpay_api_extract_receipt_url_fields($receipt);

        fn_as_sberpay_api_save_receipt_audit_row([
            'order_id' => $order_id,
            'company_id' => (int) ($order_info['company_id'] ?? 0),
            'gateway_order_id' => $gateway_order_id,
            'receipt_id' => (string) ($url_fields['receipt_id'] ?? ''),
            'detected_role' => $role,
            'receipt_type' => (string) ($receipt['receiptType'] ?? ''),
            'operation_type' => (string) ($receipt['operationType'] ?? ''),
            'receipt_status' => (int) ($receipt['receiptStatus'] ?? 0),
            'ofd_url' => (string) ($url_fields['receipt_url'] ?? ''),
            'expected_receipt_type' => (string) $expected['receipt_type'],
            'expected_payment_method' => (string) $expected['payment_method'],
            'expected_payment_type' => (int) $expected['payment_type'],
            'needs_review' => $needs_review,
            'raw_receipt' => $receipt,
            'expected_data' => $expected,
        ]);

        $stats['checked']++;
        if ($needs_review) {
            $stats['review']++;
        }
    }

    return $stats;
}

/**
 * Запускает audit-only проверку партии заказов.
 */
function fn_as_sberpay_api_run_receipt_audit(array $params)
{
    $order_ids = fn_as_sberpay_api_get_receipt_audit_order_ids($params);
    $result = [
        'orders' => count($order_ids),
        'checked' => 0,
        'review' => 0,
        'errors' => 0,
    ];

    foreach ($order_ids as $order_id) {
        $stats = fn_as_sberpay_api_run_receipt_audit_for_order((int) $order_id);
        $result['checked'] += (int) ($stats['checked'] ?? 0);
        $result['review'] += (int) ($stats['review'] ?? 0);
        $result['errors'] += (int) ($stats['errors'] ?? 0);
    }

    return $result;
}

/**
 * Возвращает последние результаты аудита для админской страницы.
 */
function fn_as_sberpay_api_get_receipt_audit_results(array $params)
{
    fn_as_sberpay_api_ensure_receipt_audit_table();

    $condition = '1';
    $company_id = (int) \Tygh\Registry::get('runtime.company_id');
    if ($company_id) {
        $condition .= db_quote(' AND company_id = ?i', $company_id);
    }
    if (!empty($params['order_id'])) {
        $condition .= db_quote(' AND order_id = ?i', (int) $params['order_id']);
    }
    if (!empty($params['only_review']) && $params['only_review'] === 'Y') {
        $condition .= db_quote(' AND needs_review = ?s', 'Y');
    }

    $rows = db_get_array(
        'SELECT * FROM ?:sberpay_receipt_audit'
        . ' WHERE ?p'
        . ' ORDER BY checked_at DESC, order_id DESC, audit_id ASC'
        . ' LIMIT ?i',
        $condition,
        (int) $params['limit']
    );

    foreach ($rows as &$row) {
        $row['expected_data_decoded'] = !empty($row['expected_data']) ? json_decode($row['expected_data'], true) : [];
        if (!is_array($row['expected_data_decoded'])) {
            $row['expected_data_decoded'] = [];
        }
    }
    unset($row);

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
    $payment_meta = fn_as_sberpay_api_get_payment_meta((int) $order_id);

    if ($processor->isLogging()) {
        $processor->log([
            'order_id' => $order_id,
            'status_from' => $status_from,
            'status_to' => $status_to,
            'payment_id' => $order_info['payment_id'] ?? 0,
            'transaction_id' => $order_info['payment_info']['transaction_id'],
        ], 'change_order_status_post');
    }

    if (in_array($payment_meta['closing_receipt']['status'] ?? '', ['succeeded', 'pending'], true)) {
        if ($processor->isLogging()) {
            $processor->log(['order_id' => $order_id], 'change_order_status_post: closing receipt already known');
        }

        return;
    }

    $processor->doReceipt($order_info, $payment_meta);
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
 * CSS-класс label для normalized closing receipt status.
 */
function fn_as_sberpay_api_get_receipt_status_label_class($status)
{
    $map = [
        'succeeded' => 'label-success',
        'pending' => 'label-warning',
        'failed' => 'label-danger',
    ];

    return $map[(string) $status] ?? '';
}

/**
 * Статус чека предоплаты для UI: meta OFD, иначе not_sent / legacy succeeded.
 */
function fn_as_sberpay_api_resolve_prepayment_status(array $meta, array $order, $confirmed_status)
{
    if (!empty($meta['prepayment_receipt']['status'])) {
        return (string) $meta['prepayment_receipt']['status'];
    }

    $gateway_paid = in_array((int) ($meta['order_status'] ?? -1), [1, 2, 4], true);
    $order_paid = ($order['status'] ?? '') === $confirmed_status;

    if (!$gateway_paid && !$order_paid) {
        return 'not_sent';
    }

    return 'succeeded';
}

/**
 * Был ли по заказу возврат средств (Сбер / сайт).
 */
function fn_as_sberpay_api_order_had_refund(array $meta)
{
    if (!empty($meta['refund']) && is_array($meta['refund'])) {
        return true;
    }

    if ((int) ($meta['order_status'] ?? -1) === 4) {
        return true;
    }

    if (!empty($meta['refunded_amount']) && (float) $meta['refunded_amount'] > 0) {
        return true;
    }

    return false;
}

/**
 * Статус фискального чека возврата для UI или null, если возврата не было.
 */
function fn_as_sberpay_api_resolve_refund_fiscal_status(array $meta)
{
    if (!empty($meta['refund_receipt']['status'])) {
        return (string) $meta['refund_receipt']['status'];
    }

    if (!fn_as_sberpay_api_order_had_refund($meta) && empty($meta['refund_receipt'])) {
        return null;
    }

    if (!empty($meta['refund']['status']) && $meta['refund']['status'] === 'failed') {
        return 'failed';
    }

    if (!empty($meta['refund']['status']) && $meta['refund']['status'] === 'succeeded') {
        return 'succeeded';
    }

    if (fn_as_sberpay_api_order_had_refund($meta)) {
        return 'not_sent';
    }

    return null;
}

/**
 * Label статуса чека для уведомлений админки (OFD refresh).
 */
function fn_as_sberpay_api_get_receipt_status_display_label($status_key)
{
    if ($status_key === null || $status_key === '') {
        return (string) __('addons.as_sberpay_api.receipt_status_na');
    }

    $lang_key = 'receipt_status_' . (string) $status_key;
    $allowed = [
        'receipt_status_not_sent',
        'receipt_status_pending',
        'receipt_status_succeeded',
        'receipt_status_failed',
    ];

    if (!in_array($lang_key, $allowed, true)) {
        return (string) __('addons.as_sberpay_api.receipt_status_not_sent');
    }

    return (string) __('addons.as_sberpay_api.' . $lang_key);
}

/**
 * Одна строка статуса чека для шаблона orders.details.
 */
function fn_as_sberpay_api_build_receipt_line_view($status, $updated_at = 0, $error_message = '')
{
    $status = (string) $status;
    $status_lang_key = 'receipt_status_' . $status;
    $allowed_status_keys = [
        'receipt_status_not_sent',
        'receipt_status_pending',
        'receipt_status_succeeded',
        'receipt_status_failed',
    ];

    if (!in_array($status_lang_key, $allowed_status_keys, true)) {
        $status = 'not_sent';
        $status_lang_key = 'receipt_status_not_sent';
    }

    return [
        'status' => $status,
        'status_label' => (string) __('addons.as_sberpay_api.' . $status_lang_key),
        'label_class' => fn_as_sberpay_api_get_receipt_status_label_class($status),
        'updated_at_formatted' => ($updated_at && $status !== 'not_sent')
            ? fn_date_format(
                (int) $updated_at,
                \Tygh\Registry::get('settings.Appearance.date_format') . ', '
                    . \Tygh\Registry::get('settings.Appearance.time_format')
            )
            : '',
        'error_message' => $status === 'failed' ? (string) $error_message : '',
    ];
}

/**
 * Включены ли ссылки на чеки в ОФД (настройка модуля).
 */
function fn_as_sberpay_api_ofd_receipt_links_enabled()
{
    if (\Tygh\Registry::get('addons.as_sberpay_api.status') !== 'A') {
        return false;
    }

    // Пустое значение после обновления до 1.3.2 без «Обновить» модуля = default Y из addon.xml.
    return \Tygh\Registry::get('addons.as_sberpay_api.show_ofd_receipt_links') !== 'N';
}

/**
 * Ссылки на чеки ОФД для UI (только валидные http/https URL из meta).
 *
 * @return list<array{type: string, url: string, title: string}>
 */
function fn_as_sberpay_api_build_ofd_receipt_links(array $meta)
{
    $map = [
        'prepayment' => ['key' => 'prepayment_receipt', 'title' => 'receipt_prepayment_title'],
        'closing'    => ['key' => 'closing_receipt', 'title' => 'receipt_closing_title'],
        'refund'     => ['key' => 'refund_receipt', 'title' => 'receipt_refund_title'],
    ];
    $links = [];

    foreach ($map as $type => $cfg) {
        $block = !empty($meta[$cfg['key']]) && is_array($meta[$cfg['key']]) ? $meta[$cfg['key']] : [];
        $url = fn_as_sberpay_api_normalize_ofd_receipt_url($block['receipt_url'] ?? '');
        if ($url === '') {
            continue;
        }

        $links[] = [
            'type'  => $type,
            'url'   => $url,
            'title' => (string) __('addons.as_sberpay_api.' . $cfg['title']),
        ];
    }

    return $links;
}

/**
 * Готовит блок UI статусов фискальных чеков для orders.details.
 */
function fn_as_sberpay_api_build_receipt_status_view(array $order, array $meta)
{
    if (empty($order['payment_id']) || empty($order['payment_info']['transaction_id'])) {
        return ['show' => false];
    }

    $processor_data = fn_get_processor_data((int) $order['payment_id']);
    if (($processor_data['processor_script'] ?? '') !== 'as_sberpay_api.php') {
        return ['show' => false];
    }

    $has_fiscal_data = !empty($meta['fiscal_snapshot']) ||
        !empty($meta['closing_receipt']) ||
        !empty($meta['prepayment_receipt']) ||
        !empty($meta['refund_receipt']) ||
        fn_as_sberpay_api_order_had_refund($meta);

    if (!$has_fiscal_data) {
        return ['show' => false];
    }

    $processor = new \Tygh\Payments\Processors\AsSberPayApi($processor_data);
    $closing = !empty($meta['closing_receipt']) && is_array($meta['closing_receipt'])
        ? $meta['closing_receipt']
        : [];
    $prepayment = !empty($meta['prepayment_receipt']) && is_array($meta['prepayment_receipt'])
        ? $meta['prepayment_receipt']
        : [];
    $refund_receipt = !empty($meta['refund_receipt']) && is_array($meta['refund_receipt'])
        ? $meta['refund_receipt']
        : [];
    $closing_status = (string) ($closing['status'] ?? 'not_sent');
    $prepayment_status = fn_as_sberpay_api_resolve_prepayment_status(
        $meta,
        $order,
        $processor->getConfirmedStatus()
    );
    $refund_fiscal_status = fn_as_sberpay_api_resolve_refund_fiscal_status($meta);
    $has_refund = $refund_fiscal_status !== null ||
        fn_as_sberpay_api_order_had_refund($meta) ||
        !empty($meta['refund_receipt']);

    return [
        'show' => true,
        'can_refresh' => $processor->usesOrderBundle(),
        'refresh_href' => 'as_sberpay_api.receipt_status?order_id=' . (int) $order['order_id'],
        'has_prepayment' => !empty($meta['fiscal_snapshot']),
        'prepayment' => fn_as_sberpay_api_build_receipt_line_view(
            $prepayment_status,
            (int) ($prepayment['updated_at'] ?? 0)
        ),
        'closing' => fn_as_sberpay_api_build_receipt_line_view(
            $closing_status,
            (int) ($closing['updated_at'] ?? 0),
            (string) ($closing['error_message'] ?? '')
        ),
        'has_refund' => $has_refund,
        'refund' => $refund_fiscal_status !== null
            ? fn_as_sberpay_api_build_receipt_line_view(
                $refund_fiscal_status,
                (int) ($refund_receipt['updated_at'] ?? 0)
            )
            : null,
    ];
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
        $refund_context = fn_as_sberpay_api_build_refund_context($meta);
        if ($refund_context) {
            $meta['sber_refund_context'] = $refund_context;
        }

        $order['sber_payment_meta'] = $meta;
        $order['sber_receipt_status_view'] = fn_as_sberpay_api_build_receipt_status_view($order, $meta);

        if (defined('AREA') && AREA !== 'C' && fn_as_sberpay_api_ofd_receipt_links_enabled()) {
            $links = fn_as_sberpay_api_build_ofd_receipt_links($meta);
            if ($links) {
                $order['sber_receipt_status_view']['receipt_links'] = $links;
                $line_keys = [
                    'prepayment' => 'prepayment',
                    'closing'    => 'closing',
                    'refund'     => 'refund',
                ];
                foreach ($links as $link) {
                    $line_key = $line_keys[$link['type']] ?? '';
                    if ($line_key !== '' && !empty($order['sber_receipt_status_view'][$line_key])) {
                        $order['sber_receipt_status_view'][$line_key]['ofd_url'] = $link['url'];
                    }
                }
            }
        }
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
            $meta = $meta_map[$order_id];
            $refund_context = fn_as_sberpay_api_build_refund_context($meta);
            if ($refund_context) {
                $meta['sber_refund_context'] = $refund_context;
            }

            $order['sber_payment_meta'] = $meta;
        }
    }
    unset($order);
}