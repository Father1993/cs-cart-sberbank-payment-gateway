Получение информации о заказе [getOrderStatusExtended]
Запрос предназначен для получения полных данных по ранее зарегистрированному заказу независимо от его статуса.

Request Body schema: application/json
required
Запрос получения информации о заказе

One of objectobject
orderId
required
string <uuid> (orderId) = 36 characters ^[a-f0-9\-]+$
Уникальный номер заказа в Платёжном шлюзе.

userName
required
string (userName) [ 1 .. 30 ] characters ^[A-Za-z0-9-_-]+$
Логин Партнера, полученный при подключении к ПШ

password
required
string (password) [ 1 .. 36 ] characters ^[ -~]+$
Пароль Партнера, полученный при подключении к ПШ

language	
string (language) = 2 characters ^[a-z]+$
Default: "ru"
Язык в кодировке ISO 639-1 (ru). Если не указан, будет использовано значение по умолчанию, указанное в настройках Партнера

Responses
200 Запрос обработан
404 Некорректный адрес запроса
405 Неподдерживаемый метод
429 Превышен порог запросов от Партнера

post
/ecomm/gw/partner/api/v1/getOrderStatusExtended.do
Тестовая среда

https://ecomtest.sberbank.ru/ecomm/gw/partner/api/v1/getOrderStatusExtended.do
Request samples
Payload
Content type
application/json

Copy
{
"userName": "testUserName",
"password": "testPassword",
"orderId": "a67b0ced-c9a4-4cfb-bce3-b9595afaafc1"
}
Response samples
200404405429
Content type
application/json
Example

Заказ (в примере - кредитный) оплачен
Заказ (в примере - кредитный) оплачен

Copy
Expand allCollapse all
{
"orderNumber": "012",
"orderStatus": 2,
"actionCode": 0,
"errorCode": "0",
"errorMessage": "Обработка запроса прошла без системных ошибок",
"amount": 475000,
"currency": 643,
"date": 1710406607164,
"depositedDate": 1710406899202,
"orderDescription": "Описание заказа",
"cardAuthInfo": {
"paymentSystem": "UNDEFINED",
"paymentWay": "IPOS"
},
"authDateTime": 1710406899202,
"terminalId": 20157880,
"paymentAmountInfo": {
"approvedAmount": 475000,
"depositedAmount": 475000,
"refundedAmount": 0,
"paymentState": "DEPOSITED}"
},
"bankInfo": { },
"attributes": [
{}
],
"orderBundle": {
"cartItems": {},
"payments": [ ],
"vats": [ ],
"sectoralCheckProps": [ ],
"installments": {}
}
}

пример другой на свякий случай:
application/json
Example

Заказ с оплатой электронным сертификатом
Заказ с оплатой электронным сертификатом

Copy
Expand allCollapse all
{
"errorCode": "0",
"errorMessage": "Обработка запроса прошла без системных ошибок",
"actionCode": 0,
"amount": 19900,
"attributes": [
{}
],
"authDateTime": 1675169010957,
"ip": "192.168.0.1",
"authRefNum": "303112098637",
"bankInfo": { },
"cardAuthInfo": {
"approvalCode": "433187",
"cardHolderName": "PETR IVANOV",
"expiration": "202512",
"maskedPan": "220138*******0047",
"paymentSystem": "MIR",
"paymentWay": "CARD",
"secureAuthInfo": {},
"currency": "643",
"date": 1675169008805,
"depositedDate": 1675169010957
},
"orderNumber": "e2574f1785324f1592d9029cb05adbbd",
"orderStatus": 2,
"paymentAmountInfo": {
"approvedAmount": 0,
"depositedAmount": 0,
"refundedAmount": 0,
"paymentState": "DEPOSITED",
"approvedAmountCertificate": 19900,
"depositedAmountCertificate": 19900,
"refundedAmountCertificate": 0
},
"terminalId": "20235777",
"transactionAttributes": [
{}
]
}