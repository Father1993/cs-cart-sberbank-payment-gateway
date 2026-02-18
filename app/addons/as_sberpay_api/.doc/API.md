# API Сбербанка — REST интерфейс

Документация: https://ecomtest.sberbank.ru/doc

## Общие сведения

- Формат запросов: `application/x-www-form-urlencoded`
- Формат ответов: `JSON`
- Кодировка: `UTF-8`
- Авторизация: параметры `userName` + `password` в теле запроса
- Спецсимволы в пароле: URL-encode (`qwe?rt%y` → `qwe%3Frt%25y`)

## URL-ы

| Среда    | URL                                                |
|----------|----------------------------------------------------|
| Тест     | `https://3dsec.sberbank.ru/payment/rest/`          |
| Боевая   | `https://securepayments.sberbank.ru/payment/rest/` |

## Требования

- Исходящий доступ к шлюзу, порт 443
- TLS 1.2
- Логин с суффиксом `-api`

---

## Методы API

### 1. register.do — Регистрация заказа (одностадийный)

**Запрос:**
```
POST /payment/rest/register.do

userName=xxx-api
&password=xxx
&orderNumber=123_abc
&amount=10000          (в копейках)
&returnUrl=https://...
&failUrl=https://...
&dynamicCallbackUrl=https://...
&jsonParams={...}
&orderBundle={...}     (для 54-ФЗ)
&taxSystem=0
```

**Ответ (успех):**
```json
{
  "orderId": "a5b1c2d3-...",
  "formUrl": "https://3dsec.sberbank.ru/payment/merchants/..."
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
```
POST /payment/rest/getOrderStatusExtended.do

userName=xxx-api
&password=xxx
&orderId=a5b1c2d3-...
```

**Ответ:**
```json
{
  "orderNumber": "123_abc",
  "orderStatus": 2,
  "actionCodeDescription": "",
  "paymentAmountInfo": {
    "paymentState": "DEPOSITED",
    "approvedAmount": 10000,
    "depositedAmount": 10000,
    "refundedAmount": 0
  },
  "ip": "1.2.3.4",
  "cardAuthInfo": {
    "pan": "411111**1111",
    "cardholderName": "TEST"
  }
}
```

### 4. refund.do — Возврат

**Запрос:**
```
userName=xxx-api
&password=xxx
&orderId=a5b1c2d3-...
&amount=5000           (частичный, в копейках; 0 = полный)
```

**Ответ:**
```json
{"errorCode": "0", "errorMessage": "Success"}
```

### 5. reverse.do — Отмена (до списания)

**Запрос:**
```
userName=xxx-api
&password=xxx
&orderId=a5b1c2d3-...
```

### 6. deposit.do — Завершение (для двустадийных)

**Запрос:**
```
userName=xxx-api
&password=xxx
&orderId=a5b1c2d3-...
&amount=10000          (0 = полная сумма)
```

---

## orderBundle (54-ФЗ / АТОЛ)

JSON-объект с корзиной товаров для фискализации.

```json
{
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

### taxType (НДС)

| Код | Ставка            |
|-----|-------------------|
| 0   | Без НДС           |
| 1   | НДС 0%            |
| 2   | НДС 10%           |
| 6   | НДС 20%           |
| 10  | НДС 5%            |
| 12  | НДС 7%            |

### taxSystem (Система налогообложения)

| Код | Система           |
|-----|-------------------|
| 0   | Общая             |
| 1   | УСН доход         |
| 2   | УСН доход-расход  |
| 3   | ЕНВД              |
| 4   | ЕСХН              |
| 5   | Патент            |

### paymentMethod (Тип оплаты)

| Код | Тип                                    |
|-----|----------------------------------------|
| 1   | Полная предоплата                      |
| 2   | Частичная предоплата                   |
| 3   | Аванс                                  |
| 4   | Полная оплата                          |
| 5   | Частичная оплата с кредитом            |
| 6   | Передача без оплаты (кредит)           |
| 7   | Оплата кредита                         |

### paymentObject (Тип предмета расчёта)

| Код | Тип                         |
|-----|-----------------------------|
| 1   | Товар                       |
| 2   | Подакцизный товар           |
| 3   | Работа                      |
| 4   | Услуга                      |
| 7   | Лотерейный билет            |
| 9   | Предоставление РИД          |
| 10  | Платёж                      |
| 11  | Агентское вознаграждение    |
| 12  | Составной предмет расчёта   |
| 13  | Иной предмет расчёта        |

---

## Коды ошибок (частые)

| Код | Описание                              |
|-----|---------------------------------------|
| 0   | Успех                                 |
| 1   | Заказ уже обработан                   |
| 5   | Доступ запрещён (Access denied)       |
| 6   | Неверный номер заказа                 |
| 7   | Системная ошибка                      |
| 12  | Пустая сумма                          |