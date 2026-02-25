# Потоки оплаты — AS SberPay API

## 1. Основной поток (одностадийный платёж)

```
Клиент нажимает "Оформить заказ"
           │
           ▼
[CS-Cart] создаёт заказ со статусом O (Open)
           │
           ▼
payments/as_sberpay_api.php (блок else)
           │
           ▼
AsSberPayApi::register($order_info)
  → POST register.do (JSON, jsonParams.sberbankOnlineAttributes)
  → Получаем orderId + formUrl + sbolDeepLink
           │
           ▼
fn_update_order_payment_info() — сохраняем orderId как transaction_id
fn_create_payment_form() — редирект на formUrl
           │
           ▼
[Сбербанк] Платёжная форма
  Клиент вводит данные карты
           │
     ┌─────┴──────┐
     │            │
  Успех       Ошибка
     │            │
     ▼            ▼
  returnUrl    failUrl
```

---

## 2. Callback (серверное уведомление)

```
[Сбербанк] → POST dynamicCallbackUrl
  Параметры: orderId, mdOrder
           │
           ▼
payments/as_sberpay_api.php
  action=callback
           │
           ▼
fn_get_processor_data($payment_id)
           │
           ▼
AsSberPayApi::getOrderStatusExtended($gateway_id)
           │
           ▼
Проверки:
  1. orderNumber → order_id
  2. transaction_id совпадает?
  3. Заказ уже оплачен? (идемпотентность)
           │
           ▼
fn_as_sberpay_api_build_response()
  orderStatus=1 → confirmed (холд)
  orderStatus=2 → confirmed (оплачен)
  orderStatus=3 → F (отмена)
  orderStatus=4 → обновить payment_info (возврат)
  иначе        → F (failed)
           │
           ▼
fn_finish_payment() → обновление статуса заказа
fn_order_placement_routines('save') → без редиректа
exit
```

---

## 3. Return (клиент вернулся)

```
[Сбербанк] → GET returnUrl
  Параметры: orderId, ordernumber
           │
           ▼
payments/as_sberpay_api.php
  action=return / mode=return / mode=error
           │
           ▼
fn_get_order_info($order_id)
           │
           ▼
Проверка: transaction_id == $_REQUEST['orderId']
           │
     ┌─────┴──────┐
   Нет           Да
     │            │
     ▼            ▼
  reason_text   getOrderStatusExtended()
  = 'Неверный     │
   ID'            ▼
               build_response()
           │
           ▼
fn_finish_payment()
fn_order_placement_routines('route') → показать клиенту результат
exit
```

---

## 4. Двустадийный платёж

```
registerPreAuth.do → холд
         │
         ▼
   orderStatus = 1 (захолдирован)
         │
    Админ решает:
    ┌─────┴──────┐
    │            │
 deposit.do   reverse.do
 (списать)    (отменить)
    │            │
    ▼            ▼
 status=2     status=3
 (оплачен)   (отменён)
```

---

## 5. Возврат средств

```
Админ в панели управления
         │
    ┌────┴────┐
    │         │
Полный    Частичный
    │         │
    ▼         ▼
refund.do  refund.do
(amount=0) (amount=N)
    │         │
    ▼         ▼
getOrderStatusExtended()
         │
         ▼
fn_update_order_payment_info()
  gateway_status
  gateway_refunded
```

---

## 6. Цепочка фискализации

```
[CS-Cart]
  orderBundle (JSON)
  в параметрах register.do
         │
         ▼
[Сбербанк] → принимает orderBundle
         │
         ▼
[АТОЛ Онлайн] → формирует чек
         │
         ▼
[ФНС] → чек зарегистрирован

CS-Cart НЕ взаимодействует с АТОЛ напрямую!
Вся фискализация через Сбер.
```

---

## Важные нюансы

### orderNumber

Формат: `{order_id}_{hash}` (например `123_a1b`)
- hash = первые 3 символа md5 от order_id + timestamp
- Нужен потому что Сбер не принимает повторный orderNumber
- При извлечении order_id: `explode('_', $orderNumber)[0]`

### Callback может прийти раньше Return

Поэтому callback — основной обработчик.
Return просто перепроверяет и показывает результат клиенту.

### Callback может прийти 1-10 раз

Модуль проверяет: если заказ уже в confirmed_status → пропускает.
Это предотвращает повторную обработку.