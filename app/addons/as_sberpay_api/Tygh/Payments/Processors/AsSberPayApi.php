<?php

/**
 * AS SberPay API — основной класс процессора.
 *
 * Новый партнёрский REST API Сбербанка (application/json):
 *   - Тест:  ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/
 *   - Прод:  epay.sberbank.ru/ecomm/gw/partner/api/v1/
 *
 * Фискализация (54-ФЗ) через orderBundle → Сбер → АТОЛ → ФНС.
 */

namespace Tygh\Payments\Processors;

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

class AsSberPayApi
{
    /**
     * URL-ы API
     */
    const TEST_URL = 'https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/';

    const PROD_URL = 'https://epay.sberbank.ru/ecomm/gw/partner/api/v1/';

    const TEST_OFD_URL = 'https://ecomtest.sberbank.ru/ecomm/gw/partner/api/ofd/v1/';

    const PROD_OFD_URL = 'https://epay.sberbank.ru/ecomm/gw/partner/api/ofd/v1/';

    /**
     * paymentMethod (Тэг ФФД 1214) — признак способа расчёта
     */
    const PM_FULL_PREPAYMENT = 'full_prepayment';

    const PM_PREPAYMENT = 'prepayment';
    const PM_ADVANCE = 'advance';
    const PM_FULL_PAYMENT = 'full_payment';
    const PM_PARTIAL_PAYMENT = 'partial_payment';
    const PM_CREDIT = 'credit';
    const PM_CREDIT_PAYMENT = 'credit_payment';

    /**
     * paymentObject (Тэг ФФД 1212) — признак предмета расчёта
     */
    const PO_COMMODITY = 'commodity';

    const PO_EXCISE = 'excise';
    const PO_JOB = 'job';
    const PO_SERVICE = 'service';
    const PO_PAYMENT = 'payment';
    const PO_ANOTHER = 'another';

    /**
     * Коды taxType (Тэг ФФД 1199) согласно документации register.do
     */
    const TAX_NONE = 0;

    const TAX_0 = 1;
    const TAX_10 = 2;
    const TAX_10_110 = 4;
    const TAX_20 = 6;
    const TAX_20_120 = 7;
    const TAX_5 = 8;
    const TAX_5_105 = 9;
    const TAX_7 = 10;
    const TAX_7_107 = 11;
    const TAX_22 = 12;
    const TAX_22_122 = 13;

    /**
     * @var string Логин API
     */
    private $login;

    /**
     * @var string Пароль API
     */
    private $password;

    /**
     * @var string Базовый URL API
     */
    private $base_url;

    /**
     * @var bool Тестовый режим
     */
    private $test_mode;

    /**
     * @var bool Логирование
     */
    private $logging;

    /**
     * @var bool Отправлять корзину (54-ФЗ)
     */
    private $send_order;

    /**
     * @var int Система налогообложения
     */
    private $tax_system;

    /**
     * @var int Тип НДС по умолчанию
     */
    private $tax_type;

    /**
     * @var string Версия ФФД (v1_05 / v1_2)
     */
    private $ffd_version;

    /**
     * @var string Признак способа расчёта (paymentMethod, Тег ФФД 1214)
     */
    private $payment_method;

    /**
     * @var string Статус заказа при успешной оплате
     */
    private $confirmed_status;

    /**
     * @var bool Двустадийные платежи
     */
    private $two_staging;

    /**
     * @var array Данные компании для чека (company)
     */
    private $company = [];

    /**
     * @var int Код последней ошибки
     */
    private $error_code = 0;

    /**
     * @var string Текст последней ошибки
     */
    private $error_text = '';

    /**
     * @var array Контекст последней успешной/последней попытки register.do.
     */
    private $last_register_context = [];

    /**
     * @param array $processor_data Данные процессора из БД
     */
    public function __construct($processor_data)
    {
        $p = $processor_data['processor_params'];
        if (is_string($p)) {
            $p = @unserialize($p);
            if (!is_array($p)) {
                $p = [];
            }
        }

        $this->login = !empty($p['login']) ? $p['login'] : '';
        $this->password = !empty($p['password']) ? $p['password'] : '';
        $this->test_mode = (!empty($p['mode']) && $p['mode'] === 'live') ? false : true;
        $this->base_url = $this->test_mode ? self::TEST_URL : self::PROD_URL;

        $this->logging = !empty($p['logging']) && $p['logging'] === 'Y';
        $this->send_order = !empty($p['send_order']) && $p['send_order'] === 'Y';

        $this->tax_system = !empty($p['tax_system']) ? (int) $p['tax_system'] : 0;
        $this->tax_type = isset($p['tax_type']) && $p['tax_type'] !== '' ? (int) $p['tax_type'] : self::TAX_22;
        $this->ffd_version = !empty($p['ffd_version']) ? $p['ffd_version'] : 'v1_05';
        $this->payment_method = !empty($p['payment_method']) ? $p['payment_method'] : self::PM_FULL_PREPAYMENT;

        $this->confirmed_status = !empty($p['confirmed_order_status']) ? $p['confirmed_order_status'] : 'P';
        $this->two_staging = !empty($p['two_staging']) && $p['two_staging'] == '1';

        $this->company = [
            'email' => isset($p['company_email']) ? (string) $p['company_email'] : '',
            'sno' => isset($p['company_sno']) ? (string) $p['company_sno'] : 'osn',
            'inn' => isset($p['company_inn']) ? (string) $p['company_inn'] : '',
            'paymentAddress' => isset($p['company_payment_address']) ? (string) $p['company_payment_address'] : '',
        ];
    }

    // =========================================================================
    //  Публичные методы API
    // =========================================================================

    /**
     * Регистрация заказа в Сбере (register.do / registerPreAuth.do).
     *
     * @param array  $order_info Информация о заказе CS-Cart
     * @param string $protocol   Протокол URL (current/https)
     * @return array Ответ API
     */
    public function register($order_info, $protocol = 'current')
    {
        $order_id = $order_info['order_id'];
        $order_number = $order_id . '_' . substr(md5($order_id . TIME), 0, 3);
        $amount = $this->formatAmount($order_info['total']);
        $bundle = null;

        // При фискализации (orderBundle) — минимальный набор полей как в эталоне документации.
        if ($this->send_order) {
            $bundle = $this->buildOrderBundle($order_info);
            if (!$bundle) {
                $this->send_order = false;
            }
        }

        if ($this->send_order && !empty($bundle)) {
            $args = [
                'userName' => $this->login,
                'password' => $this->password,
                'orderNumber' => $order_number,
                'amount' => $amount,
                'returnUrl' => fn_url("payment_notification.return?payment=as_sberpay_api&action=return&ordernumber={$order_id}", AREA, $protocol),
                'email' => !empty($order_info['email']) ? $order_info['email'] : '',
                'orderBundle' => $bundle,
            ];
        } else {
            $args = [
                'userName' => $this->login,
                'password' => $this->password,
                'orderNumber' => $order_number,
                'amount' => $amount,
                'returnUrl' => fn_url("payment_notification.return?payment=as_sberpay_api&action=return&ordernumber={$order_id}", AREA, $protocol),
                'failUrl' => fn_url("payment_notification.error?payment=as_sberpay_api&ordernumber={$order_id}", AREA, $protocol),
                'dynamicCallbackUrl' => fn_url("payment_notification.return?payment=as_sberpay_api&payment_id={$order_info['payment_id']}&action=callback", AREA, $protocol),
            ];
            if (!empty($order_info['phone'])) {
                $args['phone'] = $this->cleanPhone($order_info['phone']);
            }
            if (!empty($order_info['email'])) {
                $args['email'] = $order_info['email'];
            }
            if (!empty($order_info['user_id'])) {
                $email = !empty($order_info['email']) ? $order_info['email'] : '';
                $site = parse_url(fn_url(''), PHP_URL_HOST);
                $args['clientId'] = md5($order_info['user_id'] . $email . $site);
            }
        }

        $this->last_register_context = [
            'order_id' => (int) $order_id,
            'order_number' => (string) $order_number,
            'amount' => (int) $amount,
            'order_bundle' => is_array($bundle) ? $bundle : [],
            'uses_order_bundle' => $this->send_order && !empty($bundle),
        ];

        $endpoint = $this->two_staging ? 'registerPreAuth.do' : 'register.do';
        $response = $this->request($endpoint, $args);

        $this->log([
            'endpoint' => $endpoint,
            'request' => array_merge($args, ['password' => '***']),
            'response' => $response,
        ], 'register');

        return $response;
    }

    /**
     * Расширенный статус заказа (getOrderStatusExtended.do).
     *
     * @param string $gateway_order_id ID заказа в Сбере
     * @return array Ответ API
     */
    public function getOrderStatusExtended($gateway_order_id)
    {
        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
        ];

        $response = $this->request('getOrderStatusExtended.do', $args);

        $this->log([
            'gateway_order_id' => $gateway_order_id,
            'response' => $response,
        ], 'getOrderStatusExtended');

        return $response;
    }

    /**
     * Статус чеков OFD (getReceiptStatus).
     *
     * @param string $gateway_order_id ID заказа в Сбере
     * @return array Ответ API
     */
    public function getReceiptStatus($gateway_order_id)
    {
        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
        ];

        $ofd_base_url = $this->test_mode ? self::TEST_OFD_URL : self::PROD_OFD_URL;
        $response = $this->request('getReceiptStatus', $args, $ofd_base_url);

        $this->log([
            'gateway_order_id' => $gateway_order_id,
            'response' => $response,
        ], 'getReceiptStatus');

        return $response;
    }

    /**
     * Запрашивает OFD getReceiptStatus и сохраняет meta закрывающего чека.
     *
     * @return array{ok: bool, found?: bool, status?: string, ofd_receipt_status?: int, message?: string}
     */
    public function refreshClosingReceiptMeta(int $order_id, string $gateway_order_id, $update_source = 'ofd_poll_doreceipt')
    {
        if ($gateway_order_id === '') {
            return ['ok' => false, 'message' => 'Missing gateway order id'];
        }

        $ofd_response = $this->getReceiptStatus($gateway_order_id);
        if ($this->isError()) {
            return ['ok' => false, 'message' => $this->getErrorText()];
        }

        $ofd_receipt_status = $this->getClosingSellReceiptStatus($ofd_response);
        if ($ofd_receipt_status === null) {
            return ['ok' => true, 'found' => false];
        }

        $status = $this->normalizeClosingReceiptStatus($ofd_receipt_status);
        if ($order_id > 0) {
            fn_as_sberpay_api_save_closing_receipt_meta($order_id, [
                'status' => $status,
                'gateway_order_id' => $gateway_order_id,
                'source' => 'ofd_getReceiptStatus',
                'update_source' => (string) $update_source,
                'ofd_receipt_status' => $ofd_receipt_status,
                'updated_at' => TIME,
            ]);
        }

        $this->log([
            'order_id' => $order_id,
            'gateway_order_id' => $gateway_order_id,
            'ofd_receipt_status' => $ofd_receipt_status,
            'status' => $status,
            'update_source' => $update_source,
        ], 'refreshClosingReceiptMeta');

        return [
            'ok' => true,
            'found' => true,
            'status' => $status,
            'ofd_receipt_status' => $ofd_receipt_status,
        ];
    }

    /**
     * Возврат средств (refund.do).
     *
     * @param string $gateway_order_id ID заказа в Сбере
     * @param int    $amount           Сумма в копейках (0 = полный возврат)
     * @return array Ответ API
     */
    public function refund($gateway_order_id, $amount = 0)
    {
        return $this->financialRequest('refund.do', $gateway_order_id, $amount);
    }

    /**
     * Полный возврат заказа через refund.do c фискализацией SELL_REFUND.
     *
     * @param array  $order_info          Заказ CS-Cart
     * @param string $external_refund_id  Идемпотентный ключ возврата
     * @return array Ответ API
     */
    public function refundOrder(array $order_info, $external_refund_id = '', array $payment_meta = [])
    {
        $order_id = (int) ($order_info['order_id'] ?? 0);
        $gateway_order_id = (string) ($order_info['payment_info']['transaction_id'] ?? '');
        $amount = $this->formatAmount($order_info['total'] ?? 0);

        if (!$gateway_order_id || $amount <= 0) {
            $this->error_code = 997;
            $this->error_text = 'Refund skipped: invalid order data';

            return [];
        }

        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
            'amount' => $amount,
        ];

        if ($external_refund_id !== '') {
            $args['jsonParams'] = [
                'externalRefundId' => $external_refund_id,
            ];
        }

        if ($this->send_order) {
            $bundle = $this->buildSafeFullRefundBundle($order_info, $amount, $payment_meta);
            if (empty($bundle)) {
                $this->log([
                    'order_id' => $order_id,
                    'gateway_order_id' => $gateway_order_id,
                    'amount' => $amount,
                    'error_code' => $this->error_code,
                    'error_text' => $this->error_text,
                ], 'refundOrder: skipped unsafe snapshot bundle');

                return [];
            }

            $args['orderBundle'] = $bundle;
        }

        $response = $this->request('refund.do', $args);
        $this->log([
            'order_id' => $order_id,
            'gateway_order_id' => $gateway_order_id,
            'amount' => $amount,
            'external_refund_id' => $external_refund_id,
            'request' => array_merge($args, ['password' => '***']),
            'response' => $response,
        ], 'refundOrder');

        return $response;
    }

    /**
     * Отмена заказа (reverse.do).
     *
     * @param string $gateway_order_id ID заказа в Сбере
     * @return array Ответ API
     */
    public function reverse($gateway_order_id)
    {
        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
        ];

        $response = $this->request('reverse.do', $args);
        $this->log(['gateway_order_id' => $gateway_order_id, 'response' => $response], 'reverse');

        return $response;
    }

    /**
     * Закрывающий чек (doReceipt.do) — полный расчёт после выдачи товара.
     *
     * @param array $order_info Заказ CS-Cart
     * @return array Ответ API
     */
    public function doReceipt($order_info, array $payment_meta = [])
    {
        $order_id = $order_info['order_id'] ?? 0;

        $this->log([
            'order_id' => $order_id,
            'status' => $order_info['status'] ?? '',
            'transaction_id' => $order_info['payment_info']['transaction_id'] ?? '',
        ], 'doReceipt: start');

        if (!$this->send_order) {
            $this->log(['order_id' => $order_id], 'doReceipt: skipped send_order');
            return [];
        }

        $gateway_order_id = $order_info['payment_info']['transaction_id'] ?? '';
        if (!$gateway_order_id) {
            $this->log(['order_id' => $order_id], 'doReceipt: skipped no transaction_id');
            return [];
        }

        if (!$payment_meta && $order_id > 0) {
            $payment_meta = fn_as_sberpay_api_get_payment_meta((int) $order_id);
        }

        if (!empty($payment_meta['closing_receipt']['status']) && $payment_meta['closing_receipt']['status'] === 'succeeded') {
            $this->log(['order_id' => $order_id], 'doReceipt: skipped already succeeded');
            return [];
        }

        $sync = $this->refreshClosingReceiptMeta((int) $order_id, (string) $gateway_order_id, 'ofd_poll_doreceipt');
        if (!empty($sync['ok']) && !empty($sync['found'])
            && in_array($sync['status'] ?? '', ['succeeded', 'pending'], true)
        ) {
            $this->log([
                'order_id' => $order_id,
                'closing_receipt_status' => $sync['ofd_receipt_status'] ?? null,
            ], 'doReceipt: skipped closing receipt exists in OFD');

            return [];
        }

        $snapshot = !empty($payment_meta['fiscal_snapshot']) && is_array($payment_meta['fiscal_snapshot'])
            ? $payment_meta['fiscal_snapshot']
            : [];
        if (!$snapshot) {
            $this->log(['order_id' => $order_id], 'doReceipt: skipped missing fiscal snapshot');
            return [];
        }

        $status_response = $this->getOrderStatusExtended($gateway_order_id);
        if ($this->isError()) {
            $this->log([
                'order_id' => $order_id,
                'gateway_order_id' => $gateway_order_id,
                'error_code' => $this->error_code,
                'error_text' => $this->error_text,
            ], 'doReceipt: skipped status check error');

            return [];
        }

        fn_as_sberpay_api_save_payment_meta((int) $order_id, $status_response, $gateway_order_id);

        $gateway_status = isset($status_response['orderStatus']) ? (int) $status_response['orderStatus'] : -1;
        if (!in_array($gateway_status, [1, 2], true)) {
            $this->log([
                'order_id' => $order_id,
                'gateway_order_id' => $gateway_order_id,
                'order_status' => $gateway_status,
            ], 'doReceipt: skipped unpaid order');

            return [];
        }

        $bundle = $this->buildOrderBundleFromSnapshot($snapshot, self::PM_FULL_PAYMENT, 'SELL', 2);
        if (!$bundle) {
            $this->log(['order_id' => $order_id], 'doReceipt: skipped empty bundle');
            return [];
        }

        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
            'orderBundle' => $bundle,
        ];

        if (!empty($order_info['email'])) {
            $args['email'] = $order_info['email'];
        }

        $this->log([
            'order_id' => $order_id,
            'request' => array_merge($args, ['password' => '***']),
        ], 'doReceipt: request');

        $ofd_base_url = $this->test_mode ? self::TEST_OFD_URL : self::PROD_OFD_URL;
        $response = $this->request('doReceipt', $args, $ofd_base_url);
        $this->log(['order_id' => $order_id, 'response' => $response], 'doReceipt');

        $is_success = !$this->isError()
            && (
                (isset($response['errorCode']) && (string) $response['errorCode'] === '0')
                || (
                    !empty($response['orderId'])
                    && !empty($response['orderNumber'])
                    && !empty($response['message'])
                )
            );

        fn_as_sberpay_api_save_closing_receipt_meta((int) $order_id, [
            'status' => $is_success ? 'succeeded' : 'failed',
            'gateway_order_id' => $gateway_order_id,
            'source' => 'doReceipt',
            'update_source' => 'doReceipt',
            'error_code' => (string) ($response['errorCode'] ?? $this->error_code),
            'error_message' => (string) ($response['errorMessage'] ?? $this->error_text),
            'updated_at' => TIME,
        ]);

        if ($is_success) {
            fn_as_sberpay_api_save_closing_receipt_snapshot((int) $order_id, $snapshot, $bundle, $gateway_order_id);
        }

        return $response;
    }

    /**
     * Завершение платежа (deposit.do) — для двустадийных.
     *
     * @param string $gateway_order_id ID заказа в Сбере
     * @param int    $amount           Сумма в копейках (0 = полная сумма)
     * @return array Ответ API
     */
    public function deposit($gateway_id, $amount = 0)
    {
        return $this->financialRequest('deposit.do', $gateway_id, $amount);
    }

    // =========================================================================
    //  Геттеры
    // =========================================================================

    public function isError()
    {
        return !empty($this->error_code);
    }

    public function getErrorCode()
    {
        return $this->error_code;
    }

    public function getErrorText()
    {
        return $this->error_text;
    }

    public function getConfirmedStatus()
    {
        return $this->confirmed_status;
    }

    public function getLastRegisterContext()
    {
        return $this->last_register_context;
    }

    public function isLogging()
    {
        return $this->logging;
    }

    public function usesOrderBundle()
    {
        return $this->send_order;
    }

    // =========================================================================
    //  Логирование
    // =========================================================================

    /**
     * Записывает лог в файл, если логирование включено.
     *
     * @param mixed  $data  Данные
     * @param string $title Заголовок
     */
    public function log($data, $title = '')
    {
        if (!$this->logging) {
            return;
        }

        $dir = Registry::get('config.dir.var') . 'logs/as_sberpay_api/';
        fn_mkdir($dir);

        $file = $dir . 'sberpay_' . date('Y-m') . '.log';
        $entry = 'TIME: ' . date('Y-m-d H:i:s') . " [{$title}]\n"
            . print_r($data, true) . "\n"
            . str_repeat('=', 80) . "\n";

        error_log($entry, 3, $file);
    }

    // =========================================================================
    //  Приватные методы
    // =========================================================================

    /**
     * Итоговый статус закрывающего sell-чека в OFD или null, если doReceipt нужен.
     *
     * @param array $response Ответ getReceiptStatus
     * @return int|null receiptStatus (1–5) или null
     */
    private function getClosingSellReceiptStatus(array $response)
    {
        $receipts = !empty($response['receipts']) && is_array($response['receipts']) ? $response['receipts'] : [];
        $sell_receipts = [];

        foreach ($receipts as $receipt) {
            if (!is_array($receipt)) {
                continue;
            }

            if (strtolower((string) ($receipt['receiptType'] ?? '')) === 'sell') {
                $sell_receipts[] = $receipt;
            }
        }

        if (count($sell_receipts) < 2) {
            return null;
        }

        $extra_receipts = array_slice($sell_receipts, 1);

        foreach ($extra_receipts as $receipt) {
            if ((int) ($receipt['receiptStatus'] ?? -1) === 3) {
                return 3;
            }
        }

        foreach ($extra_receipts as $receipt) {
            $status = (int) ($receipt['receiptStatus'] ?? -1);
            if (in_array($status, [4, 5], true)) {
                return $status;
            }
        }

        foreach ($extra_receipts as $receipt) {
            $status = (int) ($receipt['receiptStatus'] ?? -1);
            if (in_array($status, [1, 2], true)) {
                return $status;
            }
        }

        return null;
    }

    /**
     * @param int $ofd_receipt_status receiptStatus из OFD (1–5)
     */
    private function normalizeClosingReceiptStatus($ofd_receipt_status)
    {
        $ofd_receipt_status = (int) $ofd_receipt_status;

        if ($ofd_receipt_status === 3) {
            return 'succeeded';
        }

        if (in_array($ofd_receipt_status, [4, 5], true)) {
            return 'failed';
        }

        return 'pending';
    }

    /**
     * HTTP POST запрос к API Сбера (application/json).
     *
     * @param string      $endpoint Метод API (register.do, doReceipt и т.д.)
     * @param array       $data     Параметры запроса
     * @param string|null $base_url Базовый URL, если нужен отличный от платёжного API
     * @return array Ответ (декодированный JSON)
     */
    private function request($endpoint, $data, $base_url = null)
    {
        $url = ($base_url ?: $this->base_url) . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            $this->error_code = 999;
            $this->error_text = 'CURL error: ' . $err;
            return ['errorCode' => 999, 'errorMessage' => $err];
        }

        curl_close($ch);

        $response = json_decode($raw, true);
        if (!is_array($response)) {
            $this->error_code = 998;
            $this->error_text = 'Invalid JSON response';
            return ['errorCode' => 998, 'errorMessage' => 'Invalid JSON: ' . substr($raw, 0, 500)];
        }

        if (!empty($response['errorCode'])) {
            $this->error_code = $response['errorCode'];
            $this->error_text = !empty($response['errorMessage']) ? $response['errorMessage'] : 'Unknown error';
        } else {
            $this->error_code = 0;
            $this->error_text = '';
        }

        return $response;
    }

    /**
     * Финансовый запрос (refund / deposit).
     */
    private function financialRequest($endpoint, $gateway_order_id, $amount)
    {
        $args = [
            'userName' => $this->login,
            'password' => $this->password,
            'orderId' => $gateway_order_id,
            'amount' => $amount,
        ];

        $response = $this->request($endpoint, $args);
        $this->log(['gateway_order_id' => $gateway_order_id, 'amount' => $amount, 'response' => $response], $endpoint);

        return $response;
    }

    /**
     * Возвращает snapshot-based orderBundle только для безопасного полного refund.
     *
     * Если сумма админского возврата не совпадает с текущим refundable остатком
     * или snapshot не позволяет честно собрать bundle, refund с сайта блокируется.
     */
    private function buildSafeFullRefundBundle(array $order_info, $amount, array $payment_meta = [])
    {
        $order_id = (int) ($order_info['order_id'] ?? 0);

        if (!$payment_meta && $order_id > 0) {
            $payment_meta = fn_as_sberpay_api_get_payment_meta($order_id);
        }

        $active_snapshot = fn_as_sberpay_api_get_active_fiscal_snapshot($payment_meta);
        if (!$active_snapshot) {
            $this->error_code = 996;
            $this->error_text = !empty($payment_meta['closing_receipt']['status']) && $payment_meta['closing_receipt']['status'] === 'succeeded'
                ? 'Refund skipped: closing receipt snapshot is missing'
                : 'Refund skipped: fiscal snapshot is missing';

            return [];
        }

        $refund_context = fn_as_sberpay_api_build_refund_context($payment_meta);
        if (!$refund_context) {
            $this->error_code = 995;
            $this->error_text = 'Refund skipped: refund context is unavailable';

            return [];
        }

        $refundable_amount_minor = (int) ($refund_context['refundable_amount_minor'] ?? 0);
        if ((int) $amount !== $refundable_amount_minor) {
            $this->error_code = 994;
            $this->error_text = 'Refund skipped: only safe full snapshot refund is supported';

            return [];
        }

        $bundle = !empty($refund_context['refund_order_bundle']) && is_array($refund_context['refund_order_bundle'])
            ? $refund_context['refund_order_bundle']
            : [];

        if (empty($refund_context['refund_order_bundle_ready']) || !$bundle) {
            $this->error_code = 993;
            $this->error_text = 'Refund skipped: snapshot refund bundle must be rebuilt outside the site';

            return [];
        }

        return $bundle;
    }

    /**
     * Формирование orderBundle для 54-ФЗ по формату документации Сбера (ФФД 1.05).
     *
     * @param array $order_info Заказ CS-Cart
     * @return array|null
     */
    private function buildOrderBundle($order_info, $payment_method = null, $receipt_type = 'SELL', $payment_type = 1)
    {
        $pm = $payment_method ?? $this->payment_method;
        $total_kopecks = $this->formatAmount($order_info['total']);
        $items = [];
        $pos = 1;
        $unit_names = $this->getOrderProductUnitNames($order_info);

        foreach ($order_info['products'] as $product) {
            $qty = !empty($product['amount']) ? (float) $product['amount'] : 1;
            $price = !empty($product['price']) ? (int) round($product['price'] * 100) : 0;
            $name = !empty($product['product']) ? mb_substr(strip_tags($product['product']), 0, 127) : 'Товар';

            $raw_code = isset($product['product_code']) && $product['product_code'] !== ''
                ? (string) $product['product_code']
                : 'P';
            $sanitized_code = preg_replace('/[^0-9A-Za-z_-]/u', '', $raw_code);
            if ($sanitized_code === '') {
                $sanitized_code = 'P';
            }
            $item_code = mb_substr($sanitized_code, 0, 32) . '-' . $pos;

            $items[] = [
                'positionId' => (string) $pos,
                'itemCode' => $item_code,
                'name' => $name,
                'quantity' => ['value' => $qty],
                'measurementUnit' => $this->resolveMeasurementUnit($product, $unit_names),
                'itemPrice' => $price,
                'itemAmount' => (int) round($price * $qty),
                'paymentMethod' => $pm,
                'paymentObject' => self::PO_COMMODITY,
                'tax' => ['taxType' => $this->tax_type],
            ];
            $pos++;
        }

        $surcharge = !empty($order_info['payment_surcharge']) ? (float) $order_info['payment_surcharge'] : 0;
        if ($surcharge > 0) {
            $sum_k = (int) round($surcharge * 100);
            $items[] = [
                'positionId' => (string) $pos,
                'itemCode' => 'Surcharge-' . $pos,
                'name' => mb_substr(
                    !empty($order_info['payment_method']['surcharge_title'])
                        ? $order_info['payment_method']['surcharge_title']
                        : 'Наценка за оплату',
                    0,
                    127
                ),
                'quantity' => ['value' => 1],
                'measurementUnit' => 'услуга',
                'itemPrice' => $sum_k,
                'itemAmount' => $sum_k,
                'paymentMethod' => $pm,
                'paymentObject' => self::PO_SERVICE,
                'tax' => ['taxType' => $this->tax_type],
            ];
            $pos++;
        }

        $shipping = !empty($order_info['shipping_cost']) ? (float) $order_info['shipping_cost'] : 0;
        if ($shipping > 0) {
            $sum_k = (int) round($shipping * 100);
            $items[] = [
                'positionId' => (string) $pos,
                'itemCode' => 'Delivery-' . $pos,
                'name' => 'Услуга доставки',
                'quantity' => ['value' => 1],
                'measurementUnit' => 'услуга',
                'itemPrice' => $sum_k,
                'itemAmount' => $sum_k,
                'paymentMethod' => $pm,
                'paymentObject' => self::PO_SERVICE,
                'tax' => ['taxType' => $this->tax_type],
            ];
        }

        $ffd = ($this->ffd_version === 'v1_2') ? '1.2' : '1.05';

        return [
            'ffdVersion' => (string) $ffd,
            'receiptType' => (string) $receipt_type,
            'company' => [
                'email' => $this->company['email'],
                'sno' => $this->company['sno'],
                'inn' => $this->company['inn'],
                'paymentAddress' => $this->company['paymentAddress'],
            ],
            'payments' => [
                [
                    'type' => (int) $payment_type > 0 ? (int) $payment_type : 1,
                    'sum' => $total_kopecks,
                ],
            ],
            'total' => $total_kopecks,
            'cartItems' => ['items' => $items],
        ];
    }

    /**
     * Переиспользует snapshot первого чека для закрывающего чека полного расчёта.
     */
    private function buildOrderBundleFromSnapshot(array $snapshot, $payment_method = null, $receipt_type = 'SELL', $payment_type = 1)
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

        if (!$items && !empty($order_bundle['cartItems']['items']) && is_array($order_bundle['cartItems']['items'])) {
            $items = array_values($order_bundle['cartItems']['items']);
        }

        if (!$items) {
            return [];
        }

        $pm = $payment_method ?? $this->payment_method;
        foreach ($items as &$item) {
            $item['paymentMethod'] = $pm;
        }
        unset($item);

        $total_kopecks = isset($order_bundle['total'])
            ? (int) $order_bundle['total']
            : fn_as_sberpay_api_get_snapshot_items_total_minor([
                'items' => $items,
                'order_bundle' => $order_bundle,
            ]);

        $order_bundle['ffdVersion'] = !empty($order_bundle['ffdVersion'])
            ? (string) $order_bundle['ffdVersion']
            : (($this->ffd_version === 'v1_2') ? '1.2' : '1.05');
        $order_bundle['receiptType'] = (string) $receipt_type;
        $order_bundle['company'] = !empty($snapshot['company']) && is_array($snapshot['company'])
            ? $snapshot['company']
            : (!empty($order_bundle['company']) && is_array($order_bundle['company']) ? $order_bundle['company'] : []);
        $order_bundle['payments'] = [[
            'type' => (int) $payment_type > 0 ? (int) $payment_type : 1,
            'sum' => $total_kopecks,
        ]];
        $order_bundle['total'] = $total_kopecks;
        $order_bundle['cartItems'] = ['items' => $items];

        return $order_bundle;
    }

    /**
     * Подтягивает unit_name по товарам заказа, если поле не попало в order_info.
     */
    private function getOrderProductUnitNames(array $order_info)
    {
        $product_ids = [];

        foreach ((array) $order_info['products'] as $product) {
            if (!empty($product['product_id'])) {
                $product_ids[] = (int) $product['product_id'];
            }
        }

        $product_ids = array_values(array_unique(array_filter($product_ids)));
        if (!$product_ids) {
            return [];
        }

        return db_get_hash_single_array(
            'SELECT product_id, unit_name FROM ?:product_descriptions WHERE product_id IN (?n) AND lang_code = ?s',
            ['product_id', 'unit_name'],
            $product_ids,
            !empty($order_info['lang_code']) ? $order_info['lang_code'] : CART_LANGUAGE
        );
    }

    /**
     * Возвращает единицу измерения для товарной позиции чека.
     */
    private function resolveMeasurementUnit(array $product, array $unit_names)
    {
        $unit = '';

        if (!empty($product['unit_name'])) {
            $unit = (string) $product['unit_name'];
        } elseif (!empty($product['product_id']) && isset($unit_names[(int) $product['product_id']])) {
            $unit = (string) $unit_names[(int) $product['product_id']];
        }

        $unit = trim(preg_replace('/\s+/u', ' ', $unit));

        return $unit !== '' ? mb_substr($unit, 0, 16) : 'шт';
    }

    /**
     * Сумма в копейках.
     */
    private function formatAmount($total)
    {
        return (int) round(fn_format_price_by_currency($total) * 100);
    }

    /**
     * Очистка телефона (только цифры, макс 15 символов).
     */
    private function cleanPhone($phone)
    {
        return substr(preg_replace('/\D+/', '', $phone), 0, 15);
    }
}
