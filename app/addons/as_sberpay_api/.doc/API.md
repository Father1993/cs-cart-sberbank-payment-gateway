# API Сбербанка — REST интерфейс

Документация: https://ecomtest.sberbank.ru/doc

## Общие сведения

- Формат запросов: `application/json`
- Формат ответов: `JSON`
- Кодировка: `UTF-8`
- Авторизация: параметры `userName` + `password` в теле каждого запроса

## URL-ы

| Среда  | URL                                                     |
|--------|---------------------------------------------------------|
| Тест   | `https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/` |
| Боевая | `https://epay.sberbank.ru/ecomm/gw/partner/api/v1/`     |

## Требования

- Исходящий доступ к шлюзу, порт 443
- TLS 1.2

---

## Методы API

### 1. register.do — Регистрация заказа (одностадийный)

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderNumber": "123_a1b",
  "amount": 10000,
  "returnUrl": "https://site.ru/return",
  "failUrl": "https://site.ru/fail",
  "dynamicCallbackUrl": "https://site.ru/callback",
  "jsonParams": {
    "CMS": "Multi-Vendor 4.18.4",
    "Module-Version": "1.0.0",
    "sberbankOnlineAttributes": "{\"language\":\"ru\"}"
  },
  "orderBundle": {...}
}
```

**Ответ (успех):**
```json
{
  "errorCode": "0",
  "orderId": "a5b1c2d3-...",
  "formUrl": "https://ecomtest.sberbank.ru/pp/pay_ru?orderId=a5b1c2d3-..."
}
```

**Ответ (ошибка):**
```json
{
  "errorCode": "1",
  "errorMessage": "Заказ с таким номером уже обработан"
}
```

### 2. registerPreAuth.do — Регистрация с предавторизацией (двустадийный)

Аналогичен `register.do`, но сумма только холдируется.
Для фактического списания нужен `deposit.do`.

### 3. getOrderStatusExtended.do — Расширенный статус

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderId": "a5b1c2d3-..."
}
```

**Ответ:**
```json
{
  "orderNumber": "123_a1b",
  "orderStatus": 2,
  "actionCodeDescription": "",
  "paymentAmountInfo": {
    "paymentState": "DEPOSITED",
    "approvedAmount": 10000,
    "depositedAmount": 10000,
    "refundedAmount": 0
  }
}
```

**Коды orderStatus:**

| Код | Значение                               | Действие CS-Cart      |
|-----|----------------------------------------|-----------------------|
| 0   | Зарегистрирован, не оплачен            | —                     |
| 1   | Предавторизованная сумма захолдирована | Статус = confirmed    |
| 2   | Полная авторизация (оплачен)           | Статус = confirmed    |
| 3   | Авторизация отменена                   | Статус = F (Failed)   |
| 4   | Операция возврата                      | Обновить payment_info |
| 5   | ACS авторизация инициирована           | —                     |
| 6   | Авторизация отклонена                  | Статус = F (Failed)   |

### 4. refund.do — Возврат

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderId": "a5b1c2d3-...",
  "amount": 5000
}
```

`amount` в копейках. `0` = полный возврат.

### 5. reverse.do — Отмена (до списания)

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderId": "a5b1c2d3-..."
}
```

### 6. deposit.do — Завершение (для двустадийных)

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderId": "a5b1c2d3-...",
  "amount": 10000
}
```

`amount: 0` = полная сумма заказа.

### 7. doReceipt.do — Закрывающий чек (54-ФЗ)

Используется для пробития чека полного расчёта отдельно от платежа.
В модуле вызывается автоматически при переводе заказа в статус **Выполнен (C)**.

**Запрос:**
```json
{
  "userName": "P1234567890",
  "password": "xxx",
  "orderId": "a5b1c2d3-...",
  "email": "customer@example.ru",
  "orderBundle": {
    "ffdVersion": "1.05",
    "receiptType": "SELL",
    "company": {...},
    "payments": [{"type": 1, "sum": 10000}],
    "total": 10000,
    "cartItems": {
      "items": [
        {
          "positionId": "1",
          "itemCode": "SKU001-1",
          "name": "Товар",
          "quantity": {"value": 1},
          "measurementUnit": "шт.",
          "itemPrice": 10000,
          "itemAmount": 10000,
          "paymentMethod": "full_payment",
          "paymentObject": "commodity",
          "tax": {"taxType": 12}
        }
      ]
    }
  }
}
```

---

## orderBundle (54-ФЗ / АТОЛ)

Передаётся в `register.do` и `doReceipt.do` при включённой фискализации.

```json
{
  "ffdVersion": "1.05",
  "receiptType": "SELL",
  "orderCreationDate": 1700000000000,
  "company": {
    "email": "shop@example.ru",
    "sno": "osn",
    "inn": "1234567890",
    "paymentAddress": "https://site.ru"
  },
  "payments": [{"type": 1, "sum": 10000}],
  "total": 10000,
  "cartItems": {
    "items": [
      {
        "positionId": "1",
        "itemCode": "SKU001-1",
        "name": "Товар 1",
        "quantity": {"value": 2},
        "measurementUnit": "шт.",
        "itemPrice": 5000,
        "itemAmount": 10000,
        "paymentMethod": "full_prepayment",
        "paymentObject": "commodity",
        "tax": {"taxType": 12}
      }
    ]
  }
}
```

> `orderCreationDate` передаётся в **миллисекундах** (`time() * 1000`).

### taxType (НДС, Тэг ФФД 1199)

| Код | Ставка      |
|-----|-------------|
| 0   | Без НДС     |
| 1   | НДС 0%      |
| 2   | НДС 10%     |
| 4   | НДС 10/110  |
| 6   | НДС 20%     |
| 7   | НДС 20/120  |
| 8   | НДС 5%      |
| 9   | НДС 5/105   |
| 10  | НДС 7%      |
| 11  | НДС 7/107   |
| 12  | НДС 22%     |
| 13  | НДС 22/122  |

Константы в классе: `TAX_NONE`, `TAX_0`, `TAX_10`, `TAX_10_110`, `TAX_20`, `TAX_20_120`, `TAX_5`, `TAX_5_105`, `TAX_7`, `TAX_7_107`, `TAX_22`, `TAX_22_122`.

### taxSystem (Система налогообложения)

| Код | Система          |
|-----|------------------|
| 0   | Общая (ОСН)      |
| 1   | УСН доход        |
| 2   | УСН доход-расход |
| 3   | ЕНВД             |
| 4   | ЕСХН             |
| 5   | Патент           |

### paymentMethod (Тэг ФФД 1214 — признак способа расчёта)

Строковые значения (константы `PM_*` в классе):

| Константа           | Значение            | Описание                        |
|---------------------|---------------------|---------------------------------|
| `PM_FULL_PREPAYMENT`| `full_prepayment`   | Полная предоплата               |
| `PM_PREPAYMENT`     | `prepayment`        | Частичная предоплата            |
| `PM_ADVANCE`        | `advance`           | Аванс                           |
| `PM_FULL_PAYMENT`   | `full_payment`      | Полный расчёт (закрывающий чек) |
| `PM_PARTIAL_PAYMENT`| `partial_payment`   | Частичный расчёт и кредит       |
| `PM_CREDIT`         | `credit`            | Кредит                          |
| `PM_CREDIT_PAYMENT` | `credit_payment`    | Оплата кредита                  |

Для интернет-магазина:
- Первый чек (при оплате): `full_prepayment`
- Закрывающий чек (при выдаче товара): `full_payment`

### paymentObject (Тэг ФФД 1212 — признак предмета расчёта)

Строковые значения (константы `PO_*` в классе):

| Константа      | Значение    | Описание              |
|----------------|-------------|-----------------------|
| `PO_COMMODITY` | `commodity` | Товар                 |
| `PO_SERVICE`   | `service`   | Услуга (доставка)     |
| `PO_JOB`       | `job`       | Работа                |
| `PO_EXCISE`    | `excise`    | Подакцизный товар     |
| `PO_PAYMENT`   | `payment`   | Платёж (аванс/предоп) |
| `PO_ANOTHER`   | `another`   | Иной предмет расчёта  |

В модуле: товары → `commodity`, доставка/наценка → `service`.

---

## Коды ошибок

| Код | Описание                                        |
|-----|-------------------------------------------------|
| 0   | Успех                                           |
| 1   | Заказ с таким orderNumber уже обработан         |
| 5   | Ошибка значения параметра (в новом Partner API) |
| 7   | Системная ошибка                                |
| 12  | Пустая сумма                                    |

> В новом Partner API `errorCode: 5` ≠ "Access denied" (это поведение старого RBS API).
