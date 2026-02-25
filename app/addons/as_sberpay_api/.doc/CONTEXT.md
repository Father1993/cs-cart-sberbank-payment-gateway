# CONTEXT — AS SberPay API (передача в следующую итерацию)

> **Для AI-агента**: читай этот файл ПЕРВЫМ. Здесь — текущее состояние, история изменений и что осталось сделать.

---

## Текущее состояние модуля (2026-02-25)

### Статус: В тестировании на dev-контуре

Модуль переведён на новый партнёрский API Сбербанка. Базовый платёжный флоу реализован. Последняя ошибка при тестировании (`errorCode: 5`) диагностирована и исправлена — результат последнего теста ещё не подтверждён.

---

## Ключевые факты (обязательно знать)

### API
- **Новый партнёрский API**: `application/json`, НЕ `form-urlencoded`
- Тест: `https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/`
- Прод: `https://epay.sberbank.ru/ecomm/gw/partner/api/v1/`
- Тестовые креды: `userName=sbertest_2221`, `password=Sbertest2026123456`

### Критичные особенности нового API vs старого
| Параметр | Старый RBS | Новый Partner |
|----------|------------|---------------|
| Content-Type | `x-www-form-urlencoded` | `application/json` |
| Телефон | `orderPayerData.mobilePhone` | топ-уровень `phone: "+79..."` |
| Email | только в orderBundle | топ-уровень `email` |
| `ffdVersion` | внутри orderBundle | топ-уровень запроса |
| `receiptType` | внутри orderBundle | топ-уровень запроса |
| Вложенные JSON | json_encode() строки | нативные PHP-массивы |

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

### Итерация 3 — Исправление ошибки `errorCode: 5`
**Диагноз**: `orderPayerData.mobilePhone` — поле старого RBS API, отсутствует в новом.
**Решения**:
1. Убран `orderPayerData` → заменён на топ-уровневые `phone: "+7..."` и `email`
2. `ffdVersion` и `receiptType` перенесены из `buildOrderBundle()` на уровень запроса (рядом с `taxSystem`)
3. `customerDetails.phone` в orderBundle тоже получил `+` префикс

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
    "sberbankOnlineAttributes": {"language": "ru"}
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

### 1. `receiptType: "income"` — значение не верифицировано
Документация Сбера не даёт явного списка допустимых значений в sber-docs.md.
Возможные альтернативы: `"sell"`, `"INCOME"`, `1` (integer).
**Если следующий тест даст ошибку из-за этого поля — попробовать `"sell"`**.

### 2. `ffdVersion: "1.05"` — формат предположительно верный
Убедиться что Сбер принимает строку `"1.05"`, а не `"FFD105"` или число.

### 3. `orderBundle` без АТОЛ — тест может падать
Тестовый аккаунт `sbertest_2221` может не иметь подключённой АТОЛ-кассы.
Если ошибка возникает при `send_order=Y` — ОТКЛЮЧИТЬ его для базового теста оплаты картой.
Фискализацию тестировать отдельно с аккаунтом, где АТОЛ настроен.

### 4. Возвраты через `refund.do` — не тестировались
Метод реализован, но тест не проводился.

### 5. Двустадийные платежи (`deposit.do`) — не тестировались
Метод реализован (баг исправлен), тест не проводился.

### 6. SberPay нативно (sbolDeepLink) — не реализовано
Сейчас модуль редиректит на `formUrl` — клиент видит кнопку SberPay на странице Сбера.
Нативный сценарий (QR, deeplink, `paymentSberPay.do`) — не реализован, опционально.

---

## Статусы заказа → действия CS-Cart

| orderStatus | Значение | CS-Cart |
|-------------|----------|---------|
| 1 | Холд (предавторизация) | Статус = confirmed |
| 2 | Оплачен | Статус = confirmed |
| 3 | Отменён | Статус = F (Failed) |
| 4 | Возврат | Обновить payment_info |
| 6 | Отклонён | Статус = F (Failed) |

---

## Что работает корректно

- Регистрация заказа (`register.do` / `registerPreAuth.do`)
- Callback (серверное уведомление от Сбера) с идемпотентностью
- Return (клиентский редирект после оплаты)
- Проверка статуса (`getOrderStatusExtended.do`)
- Маппинг orderStatus → CS-Cart статус
- Маскировка пароля в логах
- Логирование в `var/logs/as_sberpay_api/`
- `refund.do`, `reverse.do`, `deposit.do` — реализованы

---

## Коды ошибок нового API

> Внимание: коды ошибок нового API отличаются от старого RBS!
> В старом API `errorCode: 5` = "Access denied".
> В новом API `errorCode: 5` = "Error, value of the request parameter" (ошибка значения параметра).

| errorCode | Описание |
|-----------|----------|
| 0 | Успех |
| 1 | Заказ с таким orderNumber уже обработан |
| 5 | Ошибка значения параметра (в новом API) |
| 7 | Системная ошибка |
| 12 | Пустая сумма |

---

## Быстрый старт для нового AI-агента

1. **Источник истины по API**: `sber-docs.md` — читать обязательно
2. **Ядро модуля**: `AsSberPayApi.php` — класс, все методы API там
3. **Payment script**: `payments/as_sberpay_api.php` — callback/return/register логика
4. **Не трогать**: `func.php`, `init.php`, `addon.xml` — установка/хуки, всё корректно
5. **Тестовые креды**: `sbertest_2221` / `Sbertest2026123456` / среда `ecomtest.sberbank.ru`
6. **Главное правило**: всё `application/json`, все вложенные поля — нативные PHP-массивы
