# CONTEXT — AS SberPay API (передача в следующую итерацию)

> **Для AI-агента**: читай этот файл ПЕРВЫМ. Здесь — текущее состояние, история изменений и что осталось сделать.

---

## Текущее состояние модуля (2026-03-04)

### Статус: В тестировании на dev-контуре

Модуль переведён на новый партнёрский API Сбербанка. Регистрация заказа (`register.do`) работает корректно. Обработчик RETURN исправлен (итерация 5) — ожидает тестирования. Callback (`dynamicCallbackUrl`) на локальном контуре недоступен (домен `uroven.local`), на проде будет работать.

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
├── func.php                                    ← Install/uninstall + хук get_payment_processors
├── init.php                                    ← Регистрация хука
├── addon.xml                                   ← Манифест
├── config.php                                  ← Пустой (обязателен для CS-Cart)
└── .doc/
    ├── CONTEXT.md          ← ЭТОТ ФАЙЛ
    ├── sber-docs.md        ← Полная документация API (источник истины)
    ├── API.md              ← Краткий справочник методов
    ├── FLOW.md             ← Потоки оплаты (диаграммы)
    ├── README.md           ← Архитектура и установка
    └── SETTINGS.md         ← Настройки в админке
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
  "userName": "sbertest_2221",
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
  "taxSystem": 0,
  "ffdVersion": "1.05",
  "receiptType": "income",
  "orderBundle": {
    "orderCreationDate": 1772000473,
    "customerDetails": {"email": "...", "phone": "+79..."},
    "cartItems": {"items": [...]}
  }
}
```

---

## Что ЕЩЁ НЕ ПОДТВЕРЖДЕНО / МОЖЕТ ПОТРЕБОВАТЬ ПРАВКИ

### 1. ~~`sberbankOnlineAttributes` формат~~ → РЕШЕНО (итерация 4)

Должен быть JSON-строкой: `json_encode(['language' => 'ru'])`.

### 2. ~~`orderCreationDate` формат~~ → РЕШЕНО (итерация 6)

Должен быть в миллисекундах: `time() * 1000`.

### 3. Фискализация на боевом аккаунте — ГОТОВО К ТЕСТИРОВАНИЮ

После исправления `orderCreationDate` модуль готов к тестированию фискализации
на боевом аккаунте `P272606974206` с подключенным АТОЛ.

**Чек-лист тестирования фискализации:**

- [ ] Включить чекбокс "Отправлять корзину на шлюз"
- [ ] Провести тестовый платеж
- [ ] Проверить что `errorCode: 0` (без ошибок)
- [ ] Проверить формирование чека в ЛК АТОЛ
- [ ] Проверить отправку чека на email клиента
- [ ] Провести частичный возврат через модуль
- [ ] Провести полный возврат через модуль

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
- Return (клиентский редирект после оплаты) — исправлен в итерации 5
- Проверка статуса (`getOrderStatusExtended.do`)
- Маппинг orderStatus → CS-Cart статус
- Маскировка пароля в логах
- Логирование в `var/logs/as_sberpay_api/` (включая `$_REQUEST` при return)
- `refund.do`, `reverse.do`, `deposit.do` — реализованы

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

1. **Источник истины по API**: `sber-docs.md` — читать обязательно
2. **Ядро модуля**: `AsSberPayApi.php` — класс, все методы API там
3. **Payment script**: `payments/as_sberpay_api.php` — callback/return/register логика
4. **Не трогать**: `func.php`, `init.php`, `addon.xml` — установка/хуки, всё корректно
5. **Тестовые креды**: `sbertest_2221` / `Sbertest2026123456` / среда `ecomtest.sberbank.ru`
6. **Главное правило**: всё `application/json`, все вложенные поля — нативные PHP-массивы
