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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

class AsSberPayApi
{
    /** URL-ы API */
    const TEST_URL = 'https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/';
    const PROD_URL = 'https://epay.sberbank.ru/ecomm/gw/partner/api/v1/';

    /** @var string Логин API (-api) */
    private $login;

    /** @var string Пароль API */
    private $password;

    /** @var string Базовый URL API */
    private $base_url;

    /** @var bool Тестовый режим */
    private $test_mode;

    /** @var bool Логирование */
    private $logging;

    /** @var bool Отправлять корзину (54-ФЗ) */
    private $send_order;

    /** @var int Система налогообложения */
    private $tax_system;

    /** @var int Тип НДС по умолчанию */
    private $tax_type;

    /** @var string Версия ФФД (v1_05 / v1_2) */
    private $ffd_version;

    /** @var int Тип оплаты (paymentMethod) */
    private $payment_method_type;

    /** @var int Тип предмета расчёта (paymentObject) */
    private $payment_object_type;

    /** @var string Статус заказа при успешной оплате */
    private $confirmed_status;

    /** @var bool Двустадийные платежи */
    private $two_staging;

    /** @var int Код последней ошибки */
    private $error_code = 0;

    /** @var string Текст последней ошибки */
    private $error_text = '';

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

        $this->login    = !empty($p['login']) ? $p['login'] : '';
        $this->password = !empty($p['password']) ? $p['password'] : '';
        $this->test_mode = (!empty($p['mode']) && $p['mode'] === 'live') ? false : true;
        $this->base_url  = $this->test_mode ? self::TEST_URL : self::PROD_URL;

        $this->logging  = !empty($p['logging']) && $p['logging'] === 'Y';
        $this->send_order = !empty($p['send_order']) && $p['send_order'] === 'Y';

        $this->tax_system = !empty($p['tax_system']) ? (int) $p['tax_system'] : 0;
        $this->tax_type   = !empty($p['tax_type']) ? (int) $p['tax_type'] : 0;
        $this->ffd_version = !empty($p['ffd_version']) ? $p['ffd_version'] : 'v1_05';
        $this->payment_method_type = !empty($p['payment_method_type']) ? (int) $p['payment_method_type'] : 1;
        $this->payment_object_type = !empty($p['payment_object_type']) ? (int) $p['payment_object_type'] : 1;

        $this->confirmed_status = !empty($p['confirmed_order_status']) ? $p['confirmed_order_status'] : 'P';
        $this->two_staging = !empty($p['two_staging']) && $p['two_staging'] == '1';
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

        $args = [
            'userName'    => $this->login,
            'password'    => $this->password,
            'orderNumber' => $order_number,
            'amount'      => $this->formatAmount($order_info['total']),
            'returnUrl'   => fn_url("payment_notification.return?payment=as_sberpay_api&action=return&ordernumber={$order_id}", AREA, $protocol),
            'failUrl'     => fn_url("payment_notification.error?payment=as_sberpay_api&ordernumber={$order_id}", AREA, $protocol),
            'dynamicCallbackUrl' => fn_url("payment_notification.return?payment=as_sberpay_api&payment_id={$order_info['payment_id']}&action=callback", AREA, $protocol),
            'jsonParams'  => [
                'CMS' => PRODUCT_NAME . ' ' . PRODUCT_VERSION,
                'Module-Version' => '1.0.0',
                'sberbankOnlineAttributes' => ['language' => 'ru'],
            ],
        ];

        // Телефон клиента
        if (!empty($order_info['phone'])) {
            $phone = $this->cleanPhone($order_info['phone']);
            $args['orderPayerData'] = ['mobilePhone' => $phone];
        }

        // clientId для повторных оплат
        if (!empty($order_info['user_id'])) {
            $email = !empty($order_info['email']) ? $order_info['email'] : '';
            $site = parse_url(fn_url(''), PHP_URL_HOST);
            $args['clientId'] = md5($order_info['user_id'] . $email . $site);
        }

        // Корзина для фискализации (54-ФЗ → АТОЛ)
        if ($this->send_order) {
            $bundle = $this->buildOrderBundle($order_info);
            if ($bundle) {
                $args['taxSystem'] = $this->tax_system;
                $args['orderBundle'] = $bundle;
            }
        }

        $endpoint = $this->two_staging ? 'registerPreAuth.do' : 'register.do';
        $response = $this->request($endpoint, $args);

        $this->log([
            'endpoint' => $endpoint,
            'request'  => array_merge($args, ['password' => '***']),
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
            'orderId'  => $gateway_order_id,
        ];

        $response = $this->request('getOrderStatusExtended.do', $args);

        $this->log([
            'gateway_order_id' => $gateway_order_id,
            'response'         => $response,
        ], 'getOrderStatusExtended');

        return $response;
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
            'orderId'  => $gateway_order_id,
        ];

        $response = $this->request('reverse.do', $args);
        $this->log(['gateway_order_id' => $gateway_order_id, 'response' => $response], 'reverse');

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

    public function isError()            { return !empty($this->error_code); }
    public function getErrorCode()       { return $this->error_code; }
    public function getErrorText()       { return $this->error_text; }
    public function getConfirmedStatus() { return $this->confirmed_status; }
    public function isLogging()          { return $this->logging; }

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
     * HTTP POST запрос к API Сбера (application/json).
     *
     * @param string $endpoint Метод API (register.do и т.д.)
     * @param array  $data     Параметры запроса
     * @return array Ответ (декодированный JSON)
     */
    private function request($endpoint, $data)
    {
        $url = $this->base_url . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($data),
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
            'orderId'  => $gateway_order_id,
            'amount'   => $amount,
        ];

        $response = $this->request($endpoint, $args);
        $this->log(['gateway_order_id' => $gateway_order_id, 'amount' => $amount, 'response' => $response], $endpoint);

        return $response;
    }

    /**
     * Формирование orderBundle для 54-ФЗ (фискализация через АТОЛ).
     *
     * @param array $order_info Заказ CS-Cart
     * @return array|null
     */
    private function buildOrderBundle($order_info)
    {
        $items = [];
        $pos = 1;

        foreach ($order_info['products'] as $product) {
            $qty   = !empty($product['amount']) ? (int) $product['amount'] : 1;
            $price = !empty($product['price']) ? (int) round($product['price'] * 100) : 0;
            $name  = !empty($product['product']) ? strip_tags($product['product']) : 'Товар';

            $measure = ($this->ffd_version === 'v1_2')
                ? ['value' => $qty, 'measure' => 0]
                : ['value' => $qty, 'measure' => 'шт'];

            $items[] = [
                'positionId' => $pos,
                'name'       => $name,
                'quantity'   => $measure,
                'itemAmount' => $price * $qty,
                'itemCode'   => ($product['product_code'] ?? 'P') . '.' . $pos,
                'itemPrice'  => $price,
                'tax'        => ['taxType' => $this->tax_type],
                'itemAttributes' => ['attributes' => [
                    ['name' => 'paymentMethod', 'value' => $this->payment_method_type],
                    ['name' => 'paymentObject', 'value' => $this->payment_object_type],
                ]],
            ];
            $pos++;
        }

        // Наценка за оплату
        $surcharge = !empty($order_info['payment_surcharge']) ? (float) $order_info['payment_surcharge'] : 0;
        if ($surcharge > 0) {
            $measure = ($this->ffd_version === 'v1_2')
                ? ['value' => 1, 'measure' => 0]
                : ['value' => 1, 'measure' => 'шт'];

            $items[] = [
                'positionId' => $pos,
                'name'       => !empty($order_info['payment_method']['surcharge_title'])
                    ? $order_info['payment_method']['surcharge_title']
                    : 'Наценка за оплату',
                'quantity'   => $measure,
                'itemAmount' => (int) round($surcharge * 100),
                'itemCode'   => 'Surcharge.' . $pos,
                'itemPrice'  => (int) round($surcharge * 100),
                'tax'        => ['taxType' => $this->tax_type],
                'itemAttributes' => ['attributes' => [
                    ['name' => 'paymentMethod', 'value' => $this->payment_method_type],
                    ['name' => 'paymentObject', 'value' => 4],
                ]],
            ];
            $pos++;
        }

        // Доставка
        $shipping = !empty($order_info['shipping_cost']) ? (float) $order_info['shipping_cost'] : 0;
        if ($shipping > 0) {
            $measure = ($this->ffd_version === 'v1_2')
                ? ['value' => 1, 'measure' => 0]
                : ['value' => 1, 'measure' => 'шт'];

            $items[] = [
                'positionId' => $pos,
                'name'       => 'Доставка',
                'quantity'   => $measure,
                'itemAmount' => (int) round($shipping * 100),
                'itemCode'   => 'Delivery.' . $pos,
                'itemPrice'  => (int) round($shipping * 100),
                'tax'        => ['taxType' => $this->tax_type],
                'itemAttributes' => ['attributes' => [
                    ['name' => 'paymentMethod', 'value' => $this->payment_method_type],
                    ['name' => 'paymentObject', 'value' => 4],
                ]],
            ];
        }

        $ffd = ($this->ffd_version === 'v1_2') ? '1.2' : '1.05';

        return [
            'ffdVersion'        => $ffd,
            'receiptType'       => 'income',
            'orderCreationDate' => time(),
            'customerDetails'   => [
                'email' => !empty($order_info['email']) ? $order_info['email'] : '',
                'phone' => $this->cleanPhone(!empty($order_info['phone']) ? $order_info['phone'] : ''),
            ],
            'cartItems' => ['items' => $items],
        ];
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