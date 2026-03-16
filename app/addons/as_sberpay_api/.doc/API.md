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
    "CMS": "CS-Cart 4.18",
    "Module-Version": "1.0.0",
    "sberbankOnlineAttributes": {"language": "ru"}
  },
  "orderBundle": {...},
  "taxSystem": 0
}
```

**Ответ (успех):**
```json
{
  "errorCode": "0",
  "orderId": "a5b1c2d3-...",
  "formUrl": "https://ecomtest.sberbank.ru/pp/pay_ru?orderId=a5b1c2d3-...",
  "externalParams": {
    "sbolDeepLink": "sberpay://invoicing/v2?bankInvoiceId=...&operationType=Web2App&option=Connect"
  }
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

| Код | Значение                               | Действие CS-Cart    |
|-----|----------------------------------------|---------------------|
| 0   | Зарегистрирован, не оплачен            | —                   |
| 1   | Предавторизованная сумма захолдирована | Статус = confirmed  |
| 2   | Полная авторизация (оплачен)           | Статус = confirmed  |
| 3   | Авторизация отменена                   | Статус = F (Failed) |
| 4   | Операция возврата                      | Обновить payment_info |
| 5   | ACS авторизация инициирована           | —                   |
| 6   | Авторизация отклонена                  | Статус = F (Failed) |

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

**Ответ:**
```json
{"errorCode": "0", "errorMessage": "Success"}
```

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

---

## orderBundle (54-ФЗ / АТОЛ)

Передаётся в `register.do` при включённой фискализации.
Обязательные поля: `ffdVersion` и `receiptType`.

```json
{
  "ffdVersion": "1.05",
  "receiptType": "income",
  "orderCreationDate": 1700000000,
  "customerDetails": {
    "email": "test@test.ru",
    "phone": "79001234567"
  },
  "cartItems": {
    "items": [
      {
        "positionId": 1,
        "name": "Товар 1",
        "quantity": {"value": 2, "measure": "шт"},
        "itemAmount": 20000,
        "itemCode": "SKU001.1",
        "itemPrice": 10000,
        "tax": {"taxType": 6},
        "itemAttributes": {
          "attributes": [
            {"name": "paymentMethod", "value": 1},
            {"name": "paymentObject", "value": 1}
          ]
        }
      }
    ]
  }
}
```

> Для ФФД 1.2 поле `quantity.measure` = `0` (числовой код), для ФФД 1.05 = `"шт"` (строка).

### taxType (НДС)

| Код | Ставка        |
|-----|---------------|
| 0   | Без НДС       |
| 1   | НДС 0%        |
| 2   | НДС 10%       |
| 6   | НДС 20%       |
| 7   | НДС 22%       |
| 10  | НДС 5%        |
| 12  | НДС 7%        |
| 9   | НДС 22/122    |

### taxSystem (Система налогообложения)

| Код | Система          |
|-----|------------------|
| 0   | Общая            |
| 1   | УСН доход        |
| 2   | УСН доход-расход |
| 3   | ЕНВД             |
| 4   | ЕСХН             |
| 5   | Патент           |

### paymentMethod (Тип оплаты)

| Код | Тип                         |
|-----|-----------------------------|
| 1   | Полная предоплата           |
| 2   | Частичная предоплата        |
| 3   | Аванс                       |
| 4   | Полная оплата               |
| 5   | Частичная оплата с кредитом |
| 6   | Передача без оплаты (кредит)|
| 7   | Оплата кредита              |

### paymentObject (Тип предмета расчёта)

| Код | Тип                        |
|-----|----------------------------|
| 1   | Товар                      |
| 2   | Подакцизный товар          |
| 3   | Работа                     |
| 4   | Услуга                     |
| 7   | Лотерейный билет           |
| 9   | Предоставление РИД         |
| 10  | Платёж                     |
| 11  | Агентское вознаграждение   |
| 12  | Составной предмет расчёта  |
| 13  | Иной предмет расчёта       |

---

## Коды ошибок (частые)

| Код | Описание                          |
|-----|-----------------------------------|
| 0   | Успех                             |
| 1   | Заказ уже обработан               |
| 5   | Доступ запрещён (Access denied)   |
| 6   | Неверный номер заказа             |
| 7   | Системная ошибка                  |
| 12  | Пустая сумма                      |
