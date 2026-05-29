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
  Параметры: ordernumber, mdOrder (orderId может отсутствовать)
           │
           ▼
payments/as_sberpay_api.php
  action=return / mode=return / mode=error
           │
           ▼
fn_get_order_info($order_id)
          │
          ▼
Берём transaction_id из payment_info
и, если в URL есть mdOrder/orderId,
дополнительно сверяем его
          │
          ▼
getOrderStatusExtended()
          │
          ▼
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

## 6. Цепочка фискализации (первый чек — предоплата)

```
[CS-Cart]
  orderBundle (JSON)
  paymentMethod = full_prepayment
  в параметрах register.do
         │
         ▼
[Сбербанк] → принимает orderBundle
         │
         ▼
[АТОЛ Онлайн] → формирует чек (предоплата)
         │
         ▼
[ФНС] → чек зарегистрирован

CS-Cart НЕ взаимодействует с АТОЛ напрямую!
Вся фискализация через Сбер.
```

---

## 7. Закрывающий чек (doReceipt — при выдаче товара)

```
Админ переводит заказ в статус C (Выполнен)
         │
         ▼
CS-Cart: fn_change_order_status()
         │
         ▼
hook: change_order_status_post
         │
         ▼
fn_as_sberpay_api_change_order_status_post()

Guard-проверки:
  1. status_to === 'C'?             → нет: exit
  2. payment_info.transaction_id?   → нет: exit
  3. processor = as_sberpay_api.php?→ нет: exit
  4. closing_receipt succeeded (meta)? → да: exit
         │
         ▼
AsSberPayApi::doReceipt($order_info)
  getReceiptStatus (OFD) → любой sell после предоплаты с receiptStatus 1/2/3? → skip
  buildOrderBundleFromSnapshot($snapshot, PM_FULL_PAYMENT, 'SELL', 2)
  POST doReceipt
  URL: /partner/api/ofd/v1/doReceipt
         │
         ▼
[Сбербанк] → принимает orderBundle
         │
         ▼
[АТОЛ Онлайн] → формирует закрывающий чек (full_payment)
         │
         ▼
[ФНС] → чек зарегистрирован
```

---

## 8. SberPay Web SDK (второй способ оплаты)

```
Checkout: выбран способ с checkout_mode = sberpay_sdk
         │
         ▼
register.do + jsonParams.sberpay.backurl + sberbankOnlineAttributes
         │
         ▼
Сохранение transaction_id, fiscal_snapshot, clear cart
         │
         ▼
Redirect → as_sberpay_api.pay?order_id=N
         │
         ▼
Landing pay.tpl + UMD widget (sberpay-widget.umd.cjs)
         │
         ▼
widget.open({ bankInvoiceId, backUrl })
         │
         ▼
Оплата на сайте (desktop: drawer, mobile: tab)
         │
         ▼
Redirect backUrl?state=success&bankInvoiceId=...
         │
         ▼
RETURN handler (тот же action=return) + getOrderStatusExtended
         │
         ▼
fn_finish_payment → страница результата

Параллельно: callback dynamicCallbackUrl (без изменений)
```

**Два способа на checkout:** hosted (редирект formUrl) и sberpay_sdk (виджет) — отдельные записи в «Способы оплаты», один процессор.

---

## 9. СБП C2B (третий способ оплаты, v1.3.0)

```
Checkout: выбран способ с checkout_mode = sbp_c2b
         │
         ▼
register.do + jsonParams (qrType=DYNAMIC_QR_SBP, sbp.scenario=C2B)
         │
         ▼
Guard: orderId + externalParams.sbpPayload (через extractRegisterExternalParams)
  fail → fn_finish_payment(F), корзина НЕ очищается, orders.details
  ok   → payment_info: transaction_id, sbp_payload, qrc_id; fiscal_snapshot; clear cart
         │
         ▼
Redirect → as_sberpay_api.sbp?order_id=N
         │
         ▼
Landing pay_sbp.tpl
  desktop: QR (qrcode.min.js) + <a target=_blank> на sbpPayload (qr.nspk.ru)
  mobile: auto-redirect sbpPayload + кнопка «Открыть оплату»
         │
         ▼
Оплата в приложении банка (НСПК)
         │
         ▼
callback dynamicCallbackUrl + returnUrl (без изменений)
         │
         ▼
fn_finish_payment → checkout.complete или orders.details

Cancel на landing: локальный fail-flow (без отмены в банке)
  уже P → checkout.complete
  уже F → orders.details (идемпотентно)
  иначе → fn_finish_payment(F) → orders.details (repay)
Поздний success после cancel: callback с orderStatus=2 переводит заказ в P
```

**Три режима checkout:** `hosted` | `sberpay_sdk` | `sbp_c2b` — отдельные способы оплаты, один процессор.

**Gate G1:** СБП C2B должен быть включён у Сбера (Support_ecomm@sberbank.ru); smoke register — `sbpPayload` + `qrcId` в логе.

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

### orderId в returnUrl не гарантирован

Новый Partner API может вернуть только `mdOrder` или вообще не передать `orderId` в URL.
Поэтому return-поток опирается на сохранённый `payment_info.transaction_id`.

### Callback может прийти 1-10 раз

Модуль проверяет: если заказ уже в confirmed_status → пропускает.
Это предотвращает повторную обработку.