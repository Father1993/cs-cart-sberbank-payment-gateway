# AS SberPay API — Модуль оплаты для CS-Cart 4.18+

## Описание

Модуль интернет-эквайринга Сбербанка через REST API.

> **Для AI-агентов**: начни с **[CONTEXT.md](CONTEXT.md)** — текущее состояние, история изменений, что не протестировано.
> Полная документация API — **[sber-docs.md](sber-docs.md)**.
> Поддерживает фискализацию (54-ФЗ) через цепочку: CS-Cart → Сбер → АТОЛ → ФНС.

---

## Архитектура

### Структура файлов

```
app/addons/as_sberpay_api/
├── addon.xml                          # Манифест аддона (scheme 3.0)
├── config.php                         # Конфигурация (пустой, обязателен)
├── init.php                           # Регистрация хуков
├── func.php                           # Install/uninstall, хуки, мета, snapshot, refund context, build_response
├── controllers/
│   └── backend/
│       └── as_sberpay_api.php         # Возврат из админки (refund)
├── payments/
│   └── as_sberpay_api.php             # Основной payment script (callback/return/register)
├── Tygh/
│   └── Payments/
│       └── Processors/
│           └── AsSberPayApi.php       # Класс процессора (ядро)
├── .doc/
│   ├── CONTEXT.md                     # ← ЧИТАТЬ ПЕРВЫМ: состояние, история изменений
│   ├── README.md                      # Архитектура и установка
│   ├── sber-docs.md                   # Полная API-документация Сбера
│   ├── register-do-docs.md            # Документация register.do от Сбера (не редактировать)
│   ├── doReceipt.md                   # Документация doReceipt от Сбера (не редактировать)
│   ├── refund_payment-sber.md         # Документация refund.do (Сбер)
│   ├── keys_and_value_for_refund_order.md  # Поля orderBundle / возврат
│   ├── API.md                         # Краткий справочник методов и форматов
│   ├── FLOW.md                        # Потоки оплаты (диаграммы)
│   └── SETTINGS.md                    # Настройки в админке

design/backend/templates/addons/as_sberpay_api/views/payments/components/cc_processors/
└── as_sberpay_api.tpl                 # Шаблон настроек в админке

var/langs/ru/addons/as_sberpay_api.po  # Русский перевод
var/langs/en/addons/as_sberpay_api.po  # Английский перевод
```

### Принцип работы

```
┌─────────────┐     register.do      ┌──────────────┐
│   CS-Cart   │ ──────────────────→  │  Сбербанк    │
│  (checkout) │                      │  (gateway)   │
└──────┬──────┘                      └──────┬───────┘
       │                                    │
       │  ← formUrl (redirect)              │
       │                                    │
       │     Клиент оплачивает              │
       │     на форме Сбера                 │
       │                                    │
       │  callback (серверное)         ──────┘
       │  + return  (клиентское)             │
       ▼                                    │
┌─────────────┐  getOrderStatus     ┌───────┴───────┐
│   CS-Cart   │ ──────────────────→  │  Сбербанк    │
│  (callback) │ ←────────────────── │  (status)    │
└──────┬──────┘                      └──────┬───────┘
       │                                    │
       │  fn_finish_payment()               │
       │  Статус заказа обновлён            │  orderBundle
       │                                    ▼
       │                             ┌──────────────┐
       │                             │  АТОЛ Онлайн │
       │                             │  (чек)       │
       │                             └──────┬───────┘
       │                                    │
       │                                    ▼
       │                             ┌──────────────┐
       │                             │     ФНС      │
       │                             └──────────────┘
```

---

## Ключевые решения

### 1. Формат запросов

Новый партнёрский API принимает `application/json`.
Данные отправляются через `json_encode()`, ответ также приходит в JSON.

### 2. Авторизация

Через параметры `userName` и `password` в теле каждого запроса.
Формат логина зависит от договора (`P1234567890` или `P1234567890-api`) — уточнять у Сбера.

### 3. Фискализация (54-ФЗ)

Модуль НЕ интегрируется с АТОЛ напрямую.
Вместо этого формирует `orderBundle` (JSON) и передаёт его в Сбер при регистрации.
Сбер сам отправляет данные в АТОЛ Онлайн → АТОЛ бьёт чек → ФНС.

**Два чека:**
- Первый (предоплата): `register.do` с `paymentMethod=full_prepayment` — при оплате
- Закрывающий (полный расчёт): `doReceipt` через OFD endpoint с `paymentMethod=full_payment` — автоматически при переводе заказа в статус «Выполнен» (C)

### 4. Callback vs Return

- **callback** — серверное уведомление от Сбера (надёжное, приоритетное)
- **return** — редирект клиента обратно в магазин (может не дойти)

Оба обрабатываются, но callback — основной источник правды.

### 5. Идемпотентность

Callback может приходить повторно. Модуль проверяет:

- Совпадение `transaction_id`
- Текущий статус заказа (не обрабатывает повторно если уже оплачен)

### 6. Двустадийные платежи

- Одностадийные: `register.do` → оплата сразу
- Двустадийные: `registerPreAuth.do` → холд → `deposit.do` (подтверждение)

### 7. Неизменяемый fiscal snapshot и возвраты (`refund.do`)

После успешного `register.do` (когда в запрос уходит фискальный `orderBundle`) модуль сохраняет в таблице **`?:sberpay_order_meta`** снимок корзины, ушедший в шлюз: **`fiscal_snapshot`** (исходный `order_bundle`, денормализованные `items`, суммы в копейках, `gateway_order_id`, `order_number` и т.д.). Это **канонический** источник для чека возврата при **полном** возврате остатка.

- **`fn_as_sberpay_api_build_refund_bundle_from_snapshot()`** в `func.php` строит **`orderBundle` с `receiptType = SELL_REFUND`** только если сумма возврата **строго совпадает** с суммой строк snapshot. Иначе возвращается пустой массив: сайт **не** подставляет фискально неверный partial-bundle (нельзя оставить полный `cartItems`, а в `payments.sum`/`total` подставить только остаток без учёта уже возвращённых позиций).

- **`fn_as_sberpay_api_build_refund_context()`** собирает блок **`sber_refund_context`** для внешних систем (в т.ч. 1С): флаги `refund_order_bundle_ready`, `requires_bundle_rebuild_in_1c`, готовый **`refund_order_bundle`** при безопасном полном возврате, эталонные **`items`/`order_bundle`**, суммы в копейках, шаблон **`externalRefundId`**. Блок подмешивается в заказ через хуки **`get_order_info`** и **`get_orders_post`** (попадает в REST API и вебхуки 1С).

- **Возврат из админки** идёт тем же путём, что и контракт для 1С: при включённой отправке корзины (`send_order`) **`AsSberPayApi::refundOrder()`** подставляет `orderBundle` только из **snapshot** (`buildSafeFullRefundBundle`). Контроллер **`controllers/backend/as_sberpay_api.php`** заранее блокирует сценарии без snapshot, с несовпадением суммы заказа и **`refundable_amount_minor`**, а также когда сайт сигнализирует **`requires_bundle_rebuild_in_1c`**. Частичный остаточный возврат из админки в шлюз **не** отправляется — нужна пересборка чека (например, в 1С).

- **Вспомогательная функция `fn_as_sberpay_api_build_response()`** (маппинг ответа `getOrderStatusExtended` в поля `payment_info`) объявлена в **`func.php`**, чтобы её можно было вызывать из backend-контроллера (payment script в админке не подключается).

Подробности по полям возврата для интеграторов: **`keys_and_value_for_refund_order.md`**, **`refund_payment-sber.md`**.

---

## URL-ы API

### Платёжные методы

| Среда    | URL                                                     |
| -------- | ------------------------------------------------------- |
| Тестовая | `https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/` |
| Боевая   | `https://epay.sberbank.ru/ecomm/gw/partner/api/v1/`     |

### Фискализация / OFD

| Среда    | URL                                                         |
| -------- | ----------------------------------------------------------- |
| Тестовая | `https://ecomtest.sberbank.ru/ecomm/gw/partner/api/ofd/v1/` |
| Боевая   | `https://epay.sberbank.ru/ecomm/gw/partner/api/ofd/v1/`     |

---

## Статусы заказа Сбера (orderStatus)

| Код | Значение                               | Действие CS-Cart      |
| --- | -------------------------------------- | --------------------- |
| 0   | Зарегистрирован, не оплачен            | —                     |
| 1   | Предавторизованная сумма захолдирована | Статус = confirmed    |
| 2   | Полная авторизация (оплачен)           | Статус = confirmed    |
| 3   | Авторизация отменена                   | Статус = F (Failed)   |
| 4   | Операция возврата                      | Обновить payment_info |
| 5   | ACS авторизация инициирована           | —                     |
| 6   | Авторизация отклонена                  | Статус = F (Failed)   |

---

## Логирование

При включённом логировании записи идут в:

```
var/logs/as_sberpay_api/sberpay_YYYY-MM.log
```

Логируются:

- Каждый запрос register (без пароля)
- Каждый ответ getOrderStatusExtended
- Все callback
- Вызов хука `change_order_status_post`
- Запрос и ответ `doReceipt`
- Все ошибки
- Все финансовые операции (refund/reverse/deposit)

---

## Установка

1. Скопировать файлы в соответствующие директории
2. Админка → Модули → Управление модулями → Найти "AS SberPay API" → Установить
3. Админка → Администрирование → Способы оплаты → Добавить
4. Выбрать процессор "SberPay API"
5. Заполнить логин, пароль, выбрать режим
6. Настроить фискализацию если нужно

---

## Тестирование

### Минимальный чеклист

- [ ] Успешный платёж (orderStatus = 2)
- [ ] Первый чек сформирован как предоплата (`full_prepayment`)
- [ ] В `?:sberpay_order_meta` появился **`fiscal_snapshot`** с `order_bundle`, `items`, `amount_minor`, `gateway_order_id`
- [ ] В выгрузке заказа (REST/вебхук) есть **`sber_payment_meta.sber_refund_context`**: для полного остатка **`refund_order_bundle_ready`**, готовый **`refund_order_bundle`** с `SELL_REFUND`
- [ ] Полный возврат из админки: в логе **`refundOrder`** тот же состав `cartItems`, что в snapshot; ответ `errorCode: 0`; повторный возврат блокируется
- [ ] Сценарий с частичным возвратом: **`requires_bundle_rebuild_in_1c = true`**, админский refund с `send_order` не уходит в шлюз без готового bundle
- [ ] Перевод заказа в `C` запускает `doReceipt`
- [ ] Второй чек сформирован как полный расчёт (`full_payment`)
- [ ] Неуспешный платёж (отмена на форме)
- [ ] Callback обработан корректно
- [ ] Return обработан корректно
- [ ] Повторный callback не дублирует статус
- [ ] Логи записываются
- [ ] orderBundle формируется (если включён)

### Тестовые карты Сбера

См. документацию: https://ecomtest.sberbank.ru/doc

---

## Безопасность

- Пароль хранится только в `cscart_payments.processor_params` (сериализованный)
- Пароль НЕ попадает в логи (заменяется на `***`)
- Callback проверяет `transaction_id` (защита от подмены)
- Идемпотентность: повторные callback не ломают состояние
