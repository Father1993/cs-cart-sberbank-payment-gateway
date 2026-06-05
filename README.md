# AS SberPay API

Модуль оплаты для **CS-Cart** и **CS-Cart Multi-Vendor**: интеграция с интернет-эквайрингом Сбербанка через **новый партнёрский REST API** (`application/json`). Поддерживаются регистрация заказа, серверный callback, возврат покупателя после оплаты, возвраты/отмены, фискализация **54‑ФЗ** (включая закрывающий чек **`doReceipt`** при переводе заказа в статус «Выполнен»).

**Версия модуля:** `1.3.2` (см. `addon.xml`).

**Режимы оплаты на витрине** (отдельные способы оплаты с одним процессором):

| `checkout_mode` | Поведение |
| ----------------- | --------- |
| `hosted` | Редирект на платёжную страницу Сбера (`formUrl`) — по умолчанию |
| `sberpay_sdk` | Landing на сайте + **SberPay Web SDK** (виджет) |
| `sbp_c2b` | Landing + **СБП C2B**: QR на desktop, выбор банка / deep link на mobile |

Для SDK и СБП у ТП Сбера должны быть включены соответствующие продукты (запрос в `Support_ecomm@sberbank.ru`). Для СБП C2B в ответе `register.do` должны приходить `externalParams.sbpPayload` и `qrcId`.

Официальная спецификация шлюза: [документация Сбербанка](https://ecomtest.sberbank.ru/doc).

---

## Важно (Security)

- **Никогда не коммитьте** реальные merchant credentials, пароли API, сертификаты, приватные ключи и файлы логов платежей.
- Все примеры в документации и issue должны использовать **вымышленные** значения (`example.com`, `MERCHANT_LOGIN_PLACEHOLDER`, `***`).
- Перед использованием в **production** выполните **security review** и настройку в соответствии с PCI DSS / политикой вашей организации. См. [SECURITY.md](SECURITY.md).

---

## Requirements

| Компонент | Версия / условие                                                   |
| --------- | ------------------------------------------------------------------ |
| CS-Cart   | **4.18+**                                                          |
| Редакция  | **ULTIMATE** или **MULTIVENDOR**                                   |
| PHP       | Совместимая с вашей установкой CS-Cart (проверьте требования ядра) |
| Эквайринг | Договор и учётные данные в системе Сбербанка                       |

В дереве исходников этого репозитория **нет** зашитых кредов и внутренних URL компании — параметры задаются только в админке способа оплаты.

---

## Installation

1. Разместите файлы в корне установки CS-Cart (см. [Структура репозитория](#структура-репозитория)) **или** используйте симлинки через [cscart-sdk](https://github.com/cscart/sdk):

    ```bash
    cscart-sdk addon:symlink as_sberpay_api /path/to/this/repository /path/to/cs-cart --templates-to-design
    ```

    Аргументы: идентификатор аддона, каталог репозитория с подпутём `app/addons/as_sberpay_api/`, корень CS-Cart. Для старых версий CS-Cart может понадобиться `-r`.

2. **Администрирование → Модули** — установите **AS SberPay API** при необходимости.

3. **Администрирование → Способы оплаты** — создайте способ оплаты с процессором **SberPay API**.

**Callback:** URL серверных уведомлений должен быть доступен из интернета по HTTPS (стандартное требование эквайринга).

### Структура репозитория

| Путь                                              | Назначение                                                          |
| ------------------------------------------------- | ------------------------------------------------------------------- |
| `app/addons/as_sberpay_api/`                      | PHP: процессор, payment script, `func.php`, `init.php`, `addon.xml`, контроллеры `frontend` / `backend` |
| `design/backend/templates/addons/as_sberpay_api/` | Шаблоны админки                                                     |
| `var/themes_repository/responsive/templates/addons/as_sberpay_api/` | Витрина: landing SberPay SDK и СБП (`pay.tpl`, `pay_sbp.tpl`) |
| `js/addons/as_sberpay_api/`                       | `sberpay_widget.js`, `sbp_pay.js`, vendor (QR, UMD SDK)             |
| `var/langs/{ru,en}/addons/`                       | Переводы `.po`                                                      |

При установке аддона CS-Cart копирует шаблоны из `var/themes_repository/` в активную тему. JS подключается с `{$config.current_location}/js/addons/as_sberpay_api/` — каталог `js/` должен лежать в **корне** установки CS-Cart (рядом с `app/`).

---

## Configuration

Настройка: **Администрирование → Способы оплаты → [SberPay API] → Настроить**.

| Параметр                | Описание                                                                                                                                                                                                                         |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Режим checkout**      | `hosted`, `sberpay_sdk` или `sbp_c2b`. Для параллельного отображения на checkout создайте **отдельные** способы оплаты с одним процессором и разным режимом.                                                                     |
| **Логин / пароль**      | Учётные данные API из договора. Формат логина (`P…` или суффикс `-api`) уточняйте в поддержке Сбера при ошибках авторизации. Смена пароля — в [документации Сбера](https://ecomtest.sberbank.ru/doc#tag/changePasswordServices). |
| **Режим (test / live)** | Тест: `ecomtest.sberbank.ru`; бой: `epay.sberbank.ru` и отдельный OFD-контур для чеков.                                                                                                                                          |
| **Стадийность**         | Одностадийные: `register.do`; двухстадийные: `registerPreAuth.do` + `deposit.do` / отмена.                                                                                                                                       |
| **Статус при успехе**   | Статус заказа после подтверждённой оплаты.                                                                                                                                                                                       |
| **Логирование**         | Запись в `var/logs/as_sberpay_api/sberpay_YYYY-MM.log`. Только для отладки.                                                                                                                                                      |
| **Показывать ссылки на чеки ОФД** | Настройка аддона (Модули → AS SberPay API): ссылки на фискальные чеки в карточке заказа.                                                                                                                          |

**Фискализация (54‑ФЗ):** включите отправку корзины на шлюз (`send_order`), если нужны чеки через связку Сбер → ОФД; закрывающий чек уходит при статусе заказа **«Выполнен»** (`C`). На тестовом терминале без ОФД фискальные поля часто дают `errorCode: 5` — это ограничение среды.

Переменные окружения ядром аддона не используются; для локальных скриптов см. шаблон [.env.example](.env.example).

---

## Security notes

- Креды хранятся в БД CS-Cart (настройки способа оплаты), не в репозитории.
- При включённом логировании в файлы попадают логин мерчанта и ответы API (в т.ч. маскированные PAN и др.) — **не публикуйте** логи.
- HTTP-клиент к API использует **проверку TLS-сертификата** сервера.
- Callback обрабатывает запрос с `payment_id` и сверяет `orderId`/`mdOrder` с сохранённым `transaction_id` заказа. Дополнительные меры (IP allow list, заголовки) — по регламенту вашей инфраструктуры и документации Сбера.
- Подробнее: [SECURITY.md](SECURITY.md).

---

## Checkout flows (кратко)

### Hosted (классика)

`register.do` → редирект на `formUrl` → callback / return → `fn_finish_payment`.

### SberPay Web SDK

После `register.do` покупатель попадает на `as_sberpay_api.pay?order_id=…` (`pay.tpl` + `sberpay_widget.js` + vendor SDK). Статус опрашивается через return/callback; отмена на landing — `as_sberpay_api.cancel` без отмены в банке (повторная оплата с карточки заказа).

### СБП C2B

`register.do` с `jsonParams` (`qrType=DYNAMIC_QR_SBP`, `sbp.scenario=C2B`). Метаданные `sbp_payload` / `qrc_id` — в таблице `?:sberpay_order_meta`. Landing: `as_sberpay_api.sbp` (`pay_sbp.tpl` + `sbp_pay.js`): QR, виджет банков НСПК (`widget.cbrpay.ru`), polling `as_sberpay_api.sbp_status` каждые 3 с, таймаут 10 мин → expire и статус «Неудача».

## Development

- Ядро: `Tygh\Payments\Processors\AsSberPayApi` — HTTP JSON, `orderBundle`, методы API, ветвление по `checkout_mode`.
- Сценарии оплаты: `app/addons/as_sberpay_api/payments/as_sberpay_api.php` (callback, return, register).
- Витрина: `app/addons/as_sberpay_api/controllers/frontend/as_sberpay_api.php`.
- Хуки и метаданные: `func.php`, `init.php`, таблица `?:sberpay_order_meta`.
- У нового партнёрского API параметр `orderId` в URL возврата **не гарантирован**; используется сохранённый `transaction_id`.

Правила вкладов: [CONTRIBUTING.md](CONTRIBUTING.md). Чек-лист релиза: [docs/release-checklist.md](docs/release-checklist.md).

---

## Troubleshooting

| Симптом                                  | Что проверить                                                                                                                |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Callback не срабатывает                  | Публичный HTTPS, корректный `payment_id` в URL, firewall.                                                                    |
| `errorCode: 5` при фискализации на тесте | Тестовый терминал без подключённой ОФД — проверка полного цикла на боевом с кассой.                                          |
| Ошибки TLS                               | Корпоративный прокси/MITM: добавьте корневые CA в доверенные для PHP/cURL, не отключайте проверку без крайней необходимости. |
| «Неверный идентификатор транзакции»      | Рассинхрон `transaction_id`; смотрите логи процессора на время return/callback.                                              |
| СБП: нет QR / пустой landing             | Нет `sbpPayload` в `register` — не включён СБП C2B у ТП; проверьте режим `sbp_c2b` и лог `register`.                        |
| SberPay SDK не открывается               | Продукт Web SDK не включён у ТП; отдельный способ с `sberpay_sdk`; консоль браузера и `sberpay_widget.js`.                   |

---

## CI / деплой

Рабочие pipeline с секретами не храните в публичном репозитории. Пример только с переменными CI/CD: [`.gitlab-ci.example.yml`](.gitlab-ci.example.yml).

---

## Author

**Andrej Spinej**

---

## License

Проект распространяется под лицензией **MIT**. Текст: [LICENSE](LICENSE).

---
