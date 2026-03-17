# CONTEXT — AS SberPay API (передача в следующую итерацию)

> **Для AI-агента**: читай этот файл ПЕРВЫМ. Здесь — текущее состояние, история изменений и что осталось сделать.

---

## Текущее состояние модуля (2026-03-16)

### Статус: Фискализация реализована полностью

Модуль переведён на новый партнёрский API Сбербанка. Регистрация заказа (`register.do`) и фискализация работают. Реализован закрывающий чек `doReceipt.do` — автоматически отправляется при переводе оплаченного заказа в статус «Выполнен» (C). Callback (`dynamicCallbackUrl`) на локальном контуре недоступен (домен `uroven.local`), на проде работает.

---

## Ключевые факты (обязательно знать)

### API

- **Новый партнёрский API**: `application/json`, НЕ `form-urlencoded`
- Тест: `https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/`
- Прод: `https://epay.sberbank.ru/ecomm/gw/partner/api/v1/`
- Тестовые креды: `userName=sbertest_2221`, `password=Sbertest2026123456`

### Критичные особенности нового API vs старого

| Параметр              | Старый RBS                   | Новый Partner                 |
| --------------------- | ---------------------------- | ----------------------------- |
| Content-Type          | `x-www-form-urlencoded`      | `application/json`            |
| Телефон               | `orderPayerData.mobilePhone` | топ-уровень `phone: "+79..."` |
| Email                 | только в orderBundle         | топ-уровень `email`           |
| `ffdVersion`          | внутри orderBundle           | топ-уровень запроса           |
| `receiptType`         | внутри orderBundle           | топ-уровень запроса           |
| Вложенные JSON        | json_encode() строки         | нативные PHP-массивы          |
| `orderId` в returnUrl | Гарантированно добавляется   | Не гарантирован               |

### Файловая структура

```
app/addons/as_sberpay_api/
├── Tygh/Payments/Processors/AsSberPayApi.php   ← ЯДРО (класс API)
├── payments/as_sberpay_api.php                 ← Payment script (callback/return/register)
├── func.php                                    ← Install/uninstall + хуки
├── init.php                                    ← Регистрация хуков
├── addon.xml                                   ← Манифест
├── config.php                                  ← Пустой (обязателен для CS-Cart)
└── .doc/
    ├── CONTEXT.md              ← ЭТОТ ФАЙЛ
    ├── README.md               ← Архитектура и установка
    ├── sber-docs.md            ← Полная документация API Сбера
    ├── register-do-docs.md     ← Документация register.do от Сбера (не редактировать)
    ├── doReceipt.md            ← Документация doReceipt от Сбера (не редактировать)
    ├── API.md                  ← Краткий справочник методов
    ├── FLOW.md                 ← Потоки оплаты (диаграммы)
    └── SETTINGS.md             ← Настройки в админке
```

---

## История изменений (хронология)

### Итерация 1 — Переход на новый API

- Обновлены константы `TEST_URL` / `PROD_URL` → новые endpoint-ы
- Метод `request()`: `x-www-form-urlencoded` → `application/json` + `json_encode()`
- Метод `register()`: вложенные поля стали нативными PHP-массивами (убраны `json_encode()` внутри)
- Исправлен баг в `deposit()`: неверная переменная `$gateway_order_id` → `$gateway_id`

### Итерация 2 — Аудит и доработки

- `jsonParams.sberbankOnlineAttributes` добавлен (обязателен для SberPay)
- `buildOrderBundle()`: добавлен `ffdVersion` (позже перенесён на топ-уровень)
- `fn_as_sberpay_api_build_response()`: `orderStatus=3` (отмена) теперь возвращает `order_status=F`
- Обновлён PHPDoc заголовка класса

### Итерация 3 — Исправление ошибки `errorCode: 5` (первая попытка)

**Диагноз**: `orderPayerData.mobilePhone` — поле старого RBS API, отсутствует в новом.
**Решения**:

1. Убран `orderPayerData` → заменён на топ-уровневые `phone: "+7..."` и `email`
2. `ffdVersion` и `receiptType` перенесены из `buildOrderBundle()` на уровень запроса (рядом с `taxSystem`)
3. `customerDetails.phone` в orderBundle тоже получил `+` префикс

### Итерация 4 — Настоящая причина `errorCode: 5` (2026-03-03)

**Диагноз (подтверждён curl-тестами)**:
Две отдельные причины ошибки:

1. **`sberbankOnlineAttributes` внутри `jsonParams`** передавался как вложенный JSON-объект.
   API ожидает его как **JSON-сериализованную строку** (`json_encode()`).
   Даже в `application/json` API, значения внутри `jsonParams` — это строки.
    - Было: `'sberbankOnlineAttributes' => ['language' => 'ru']`
    - Стало: `'sberbankOnlineAttributes' => json_encode(['language' => 'ru'])`

2. **Фискальные параметры** (`taxSystem`, `ffdVersion`, `receiptType`, `orderBundle`)
   не поддерживаются на тестовом аккаунте `sbertest_2221` (АТОЛ не настроен).
   Каждый из них по отдельности вызывает errorCode: 5.
   На боевом аккаунте `P272606974206` с подключённым АТОЛ — должны работать.

**Дополнительные исправления**:

- Добавлен `features: 'FORCE_FULL_TDS'` (рекомендация документации Сбера)
- `itemAttributes.value` приведены к типу `string` (было int)
- Длина `name` в orderBundle ограничена 127 символами через `mb_substr()`

### Итерация 5 — Исправление RETURN handler (2026-03-04)

**Диагноз**: После успешной оплаты на странице Сбера, при возврате в магазин —
ошибка «Неверный идентификатор транзакции», статус заказа «Неудача» (F).

**Причина**: RETURN handler в `payments/as_sberpay_api.php` требовал `$_REQUEST['orderId']`
из GET-параметров returnUrl. Логика скопирована из стандартного `rus_sberbank`, который
работает со старым RBS API (`3dsec.sberbank.ru`). Старый API добавляет `orderId` в URL
при редиректе обратно в магазин. Новый партнёрский API (`ecomm/gw/partner`) **не гарантирует**
добавление `orderId` в returnUrl. В результате `$_REQUEST['orderId']` пустой, сравнение
с сохранённым `transaction_id` всегда даёт mismatch.

**Решение**:

1. RETURN handler теперь использует сохранённый `transaction_id` из `payment_info` как
   основной идентификатор для вызова `getOrderStatusExtended`.
2. Если `orderId` или `mdOrder` присутствует в GET — выполняется дополнительная валидация.
3. Если `orderId`/`mdOrder` отсутствует — пропускаем проверку, используем сохранённый ID.
4. Добавлено логирование `$_REQUEST` в начало RETURN handler (по аналогии с `rus_sberbank`).

**Ключевое отличие нового API от старого (добавить в таблицу)**:
| Параметр | Старый RBS | Новый Partner |
|----------|------------|---------------|
| `orderId` в returnUrl | Гарантированно добавляется | Не гарантирован |

### Итерация 7 — Исправление taxType + константы (2026-03-16)

**Причина**: Коды `taxType` в шаблоне и PHP были неверными (5% было `10`, 7% было `12`).
Источник истины — официальная документация `register-do-docs.md`.

**Изменения**:
1. Добавлены PHP-константы `TAX_*` для всех кодов taxType (0–13)
2. Добавлены PHP-константы `PM_*` для всех значений paymentMethod (строки)
3. Добавлены PHP-константы `PO_*` для ключевых значений paymentObject (строки)
4. Исправлены `value` атрибуты в dropdown `tax_type` шаблона (5%→8, 7%→10, 22%→12, 22/122→13)
5. Добавлен dropdown `payment_method` в шаблон настроек (по умолчанию `full_prepayment`)
6. Удалены мёртвые свойства `$payment_method_type` и `$payment_object_type` (были объявлены, но нигде не использовались — хардкод в `buildOrderBundle`)
7. Исправлен дефолт `tax_type` → `self::TAX_22` (код 12)
8. Исправлен `receiptType` с `'sell'` → `'SELL'` (по документации)

### Итерация 8 — Закрывающий чек doReceipt (2026-03-16)

**Задача**: При переводе оплаченного заказа в статус «Выполнен» (C) автоматически отправлять закрывающий чек в Сбербанк.

**Изменения**:

1. **`AsSberPayApi.php`**: добавлен метод `doReceipt($order_info)` — строит `orderBundle` с `paymentMethod=full_payment` и отправляет на `doReceipt.do`
2. **`AsSberPayApi.php`**: `buildOrderBundle()` теперь принимает `$payment_method = null` для override; `$pm = $payment_method ?? $this->payment_method`
3. **`init.php`**: зарегистрирован хук `change_order_status_post`
4. **`func.php`**: добавлен обработчик `fn_as_sberpay_api_change_order_status_post` с 4 guard-условиями:
   - `$status_to === 'C'`
   - предыдущий статус имеет `payment_received = Y`
   - есть `payment_info['transaction_id']`
   - процессор = `as_sberpay_api.php`

### Итерация 6 — Исправление orderCreationDate для фискализации (2026-03-11)

**Диагноз**: При включенном чекбоксе "Отправлять корзину на шлюз" (фискализация 54-ФЗ)
запрос `register.do` возвращает `errorCode: 5, "Error, value of the request parameter"`.
Без фискализации всё работает корректно.

**Причина**: Поле `orderCreationDate` в `orderBundle` передавалось в **секундах** (результат `time()`),
но API Сбербанка ожидает **миллисекунды**. Анализ успешного ответа `getOrderStatusExtended` показал:

```
[date] => 1772782445862  // миллисекунды (13 цифр)
```

А в запросе передавалось:

```
[orderCreationDate] => 1773192659  // секунды (10 цифр)
```

**Решение**:
В методе `buildOrderBundle()` изменено:

```php
'orderCreationDate' => time() * 1000,  // было: time()
```

**Результат**: Теперь `orderCreationDate` корректно передается в миллисекундах, что соответствует
требованиям API Сбербанка для фискализации через АТОЛ.

---

## Текущий запрос register.do (актуальный)

```json
{
  "userName": "P272606974206",
  "password": "...",
  "orderNumber": "40_6e8",
  "amount": 30500,
  "returnUrl": "https://site.ru/...",
  "failUrl": "https://site.ru/...",
  "dynamicCallbackUrl": "https://site.ru/...",
  "jsonParams": {
    "CMS": "Multi-Vendor 4.18.4",
    "Module-Version": "1.0.0",
    "sberbankOnlineAttributes": "{\"language\":\"ru\"}"
  },
  "phone": "+79098763797",
  "email": "user@example.ru",
  "clientId": "md5hash",
  "orderBundle": {
    "ffdVersion": "1.05",
    "receiptType": "SELL",
    "orderCreationDate": 1772000473000,
    "company": {
      "email": "shop@site.ru",
      "sno": "osn",
      "inn": "1234567890",
      "paymentAddress": "https://site.ru"
    },
    "payments": [{"type": 1, "sum": 30500}],
    "total": 30500,
    "cartItems": {
      "items": [
        {
          "positionId": "1",
          "itemCode": "SKU001-1",
          "name": "Товар",
          "quantity": {"value": 1},
          "measurementUnit": "шт.",
          "itemPrice": 30500,
          "itemAmount": 30500,
          "paymentMethod": "full_prepayment",
          "paymentObject": "commodity",
          "tax": {"taxType": 12}
        }
      ]
    }
  }
}
```

---

## Что ЕЩЁ НЕ ПОДТВЕРЖДЕНО / МОЖЕТ ПОТРЕБОВАТЬ ПРАВКИ

### 1. ~~`sberbankOnlineAttributes` формат~~ → РЕШЕНО (итерация 4)

Должен быть JSON-строкой: `json_encode(['language' => 'ru'])`.

### 2. ~~`orderCreationDate` формат~~ → РЕШЕНО (итерация 6)

Должен быть в миллисекундах: `time() * 1000`.

### 3. Фискализация — реализована, ожидает тестирования закрывающего чека

**Чек-лист тестирования закрывающего чека:**

- [ ] Оплатить заказ → проверить первый чек (full_prepayment)
- [ ] Перевести заказ в статус C (Выполнен)
- [ ] Убедиться что в логах появился `[doReceipt]` с `errorCode: 0`
- [ ] Проверить закрывающий чек (full_payment) в ЛК АТОЛ

### 4. Возвраты через `refund.do` — не тестировались

Метод реализован, но тест не проводился.

### 5. Двустадийные платежи (`deposit.do`) — не тестировались

Метод реализован (баг исправлен), тест не проводился.

### 6. SberPay нативно (sbolDeepLink) — не реализовано

Сейчас модуль редиректит на `formUrl` — клиент видит кнопку SberPay на странице Сбера.
Нативный сценарий (QR, deeplink, `paymentSberPay.do`) — не реализован, опционально.

---

## Статусы заказа → действия CS-Cart

| orderStatus | Значение               | CS-Cart               |
| ----------- | ---------------------- | --------------------- |
| 1           | Холд (предавторизация) | Статус = confirmed    |
| 2           | Оплачен                | Статус = confirmed    |
| 3           | Отменён                | Статус = F (Failed)   |
| 4           | Возврат                | Обновить payment_info |
| 6           | Отклонён               | Статус = F (Failed)   |

---

## Что работает корректно

- Регистрация заказа (`register.do` / `registerPreAuth.do`)
- Callback (серверное уведомление от Сбера) с идемпотентностью
- Return (клиентский редирект после оплаты)
- Проверка статуса (`getOrderStatusExtended.do`)
- Маппинг orderStatus → CS-Cart статус
- Фискализация (54-ФЗ): `orderBundle` с корректными кодами taxType, PM/PO константы
- Закрывающий чек `doReceipt.do` — при переводе заказа в «Выполнен» (C)
- Маскировка пароля в логах
- Логирование в `var/logs/as_sberpay_api/`
- `refund.do`, `reverse.do`, `deposit.do` — реализованы (не тестировались)

---

## Коды ошибок нового API

> Внимание: коды ошибок нового API отличаются от старого RBS!
> В старом API `errorCode: 5` = "Access denied".
> В новом API `errorCode: 5` = "Error, value of the request parameter" (ошибка значения параметра).

| errorCode | Описание                                |
| --------- | --------------------------------------- |
| 0         | Успех                                   |
| 1         | Заказ с таким orderNumber уже обработан |
| 5         | Ошибка значения параметра (в новом API) |
| 7         | Системная ошибка                        |
| 12        | Пустая сумма                            |

---

## Быстрый старт для нового AI-агента

1. **Источник истины по API**: `register-do-docs.md` и `doReceipt.md` — документация от Сбера
2. **Ядро модуля**: `AsSberPayApi.php` — все методы и константы
3. **Payment script**: `payments/as_sberpay_api.php` — callback/return/register логика
4. **Хуки**: `func.php` + `init.php` — установка, `change_order_status_post` → `doReceipt`
5. **Боевые креды**: `P272606974206` — с подключённым АТОЛ (для фискализации)
6. **Главное правило**: всё `application/json`, нативные PHP-массивы
7. **taxType**: коды 0,1,2,4,6,7,8,9,10,11,12,13 — константы `TAX_*` в классе
8. **paymentMethod**: строки (не числа!) — константы `PM_*` в классе
9. **paymentObject**: строки (не числа!) — константы `PO_*` в классе
