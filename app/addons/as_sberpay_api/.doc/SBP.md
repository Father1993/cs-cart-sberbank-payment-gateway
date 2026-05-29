Особенности работы с СБП на шлюзе электронной коммерции

#

Сценарий
Действие
Результат
1 Регистрация заказа
Создание заказа
для последующей
оплаты по СБП
через приложение
банка либо для
последующей
оплаты по ранее
созданной
подписке
Запрос register.do, с указанием в
jsonParams
"qrType" : "DYNAMIC_QR_SBP",
"sbp.scenario" : "C2B"
"description": "регистрация заказа СБП с
максимальным набором полей" (пример)
"amount": <Сумма заказа в копейках>
Description – необязательное поле при
оплате через приложение банка. Если
указано, то содержание отображается
клиенту-ФЛ в СБОЛ (а также в
приложениях других банков) в поле
«назначение платежа», и при оплате заказа
передается партнеру в реестре в поле ID1
(Доп. информация_1)
Если планируется оплата по подписке, то
поле Description должно быть обязательно
заполнено.
{
"errorCode": "0",
"externalParams": {
"qrcId":
"AD10001RQ10OT70E8LEOT61G16J41I09
",
"sbpPayload":
"https://qr.nspk.ru/AD10001RQ10OT70E8L
EOT61G16J41I09?type=02&bank=10000000
0111&sum=1400&cur=RUB&crc=62E3",
"sbolDeepLink":
"sberpay://invoicing/v2?bankInvoiceId=a07e
c65536924addb531622df735b60a&operation
Type=Web2App&option=Connect"
},
"orderId": "a07ec655-3692-4add-b531-
622df735b60a",
"formUrl":
"https://ift.payecom.ru/pay_ru?orderId=a07ec
655-3692-4add-b531-622df735b60a",
"errorMessage": "Обработка запроса
} прошла без системных ошибок"
Тип динамического QR СБП: 02 (type=02)
2
Создание
подписки СБП
Запрос register.do, с указанием в
jsonParams
"qrType" : "SUBSCRIPTION_SBP",
"sbp.scenario" : "C2B_SUBSCRIPTION",
"sbp.subscriptionPurpose" : "Для оплаты
услуг" (пример)
{
"orderId": "06dc6ea3-c932-4b42-b9d9-
6273022627b9",
"formUrl":
"https://ift.payecom.ru/pay_ru?orderId=06dc6
ea3-c932-4b42-b9d9-6273022627b9",
"errorCode": "0",
"errorMessage": "Обработка запроса
прошла без системных ошибок",
"externalParams": {
"qrcId":
"AB1S002LBQ7PDTTE88HQ1SE2PVFVTP
04",
"sbpPayload":
"https://sub.nspk.ru/AB1S002LBQ7PDTTE8
8HQ1SE2PVFVTP04?type=03&bank=10000
0000111&crc=3B02",
"sbolDeepLink":
"sberpay://invoicing/v2?bankInvoiceId=06dc
6ea3c9324b42b9d96273022627b9&operation
} } Type=Web2App&option=Connect"
Тип динамического QR СБП: 03 (type=03)
3
Создание заказа
для оплаты по
СБП с
возможностью
создать подписку
после оплаты
"qrType" : "DYNAMIC_QR_SBP",
"sbp.scenario" :
"C2B_SUBSCRIPTION_WITH_PAYMENT
",
"sbp.subscriptionPurpose" : "Для оплата
услуг ",
"description": "регистрация заказа СБП"
Ответ на register.do аналогичен п.1
Тип динамического QR СБП: 02 (type=02)
4
Отображение в
СБОЛ кнопки
«обратно к
заказу» для
возврата в
В register.do
заполняется обязательное поле returnUrl.
Если !=””, то передается в НСПК при
регистрации QR СБП, и при успешной
оплате в СБОЛ (ДБО другого банка) будет
В returnUrl указан адрес
приложение/на
сайт ТСП
показана кнопка «обратно к заказу»
(возврат в магазин), при нажатии на
которую будет выполнен переход по
указанному адресу.
Если =””, то url подставляется из настроек
мерча (адрес возврата после платежа, веб-
страница ТСТ)
Если =”” и адреса в настройках обнулены,
то при регистрации QR СБП параметр не
передается, на экране СБОЛ не будет
кнопки «обратно к заказу».
Описание с сайта НСПК:
Содержит ссылку для автоматического
возврата Плательщика из приложения
Банка в приложение или на сайт ТСП.
Допускаются только символы в кодировке
ASCII. Формат должен соответствовать
спецификации RFC-3986
Если заполнено, должно содержать домен.
Примеры:
https://pay.mos.ru/mospaynew/newportal/pub
lic/result?requestUID=21445743-79b2-408d-
8919-
b33bfd64030e&packageUID=b5d2e493-
5592-4b93-9be1-927c018983c5
ozon://ozon.ru/thank_you/46601856-
0076?clearBackStack=true
Адрес не указан
5 Оплата заказа, возврат заказа
Оплата по
платежной ссылке
СБП (QR СБП)
SbpPayload, полученный по п.1,
считывается в приложении банка-
плательщика, клиент подтверждает оплату
Статусы заказа в ответ на
getOrderStatusExtended.do (gose)
orderStatus = 2
actionCode = 0
6
Оплата по
платежной ссылке
СБП (QR СБП) с
последующим
созданием
подписки
SbpPayload, полученный по п.3,
считывается в приложении банка-
плательщика, клиент подтверждает
оплату.
После успешной оплаты клиент создает
подписку
orderStatus = 2
actionCode = 0
Переданы параметры по подписке в блоке
attributes (пример)
"subscriptionId":
"3cbf9ed8cbb9442abdc1e2ff514ebb2e",
"memberId": "100000000111
7
Создание
подписки СБП
SbpPayload, полученный по п.2,
считывается в приложении банка-
плательщика, клиент
создает подписку
orderStatus = 2
actionCode = 0
Переданы параметры по подписке в блоке
attributes (пример)
"subscriptionId":
"3cbf9ed8cbb9442abdc1e2ff514ebb2e",
"memberId": "100000000111
8
Оплата по
подписке
После выполнения п.1 вызывается метод
paymentOrderBySubscription с
параметрами
Идентификатор заказа
"orderId"
orderStatus = 2
actionCode = 0
Идентификатор подписки, по которой
должна быть выполнена оплата
"subscriptionId":
"3cbf9ed8cbb9442abdc1e2ff514ebb2e",
Идентификатор банка-плательщика, в
котором создана подписка
"memberId": "100000000111"
9
Возврат
refund.do
"orderId":
"amount": **\_\_** (возможны частичные
возвраты, сумма указывается в копейках)
orderStatus = 4
actionCode = 0
Комментарии к процессу оплаты по QR CБП (платежной ссылке СБП):

1. Партнер создает заказ методом register.do, указывая в jsonParams : "qrType" : "DYNAMIC_QR_SBP",
   "sbp.scenario" : "C2B", поле "description" заполняется опционально.
2. При обработке запроса на создание заказа Сбер получает в НСПК платежную ссылку СБП (SbpPayload) и передает ее
   партнеру в ответ на register.do;
3. Партнер использует SbpPayload в своем app/mWEB, передавая его в виджет НСКП https://sbp.nspk.ru/file/sbp_SDK.zip
   (либо используя свой виджет), или формирует и отображает клиенту QR-код;
4. Клиент-физическое лицо выбирает свой банк в списке банков-эмитентов, сформированном виджетом, или сканирует
   QR-код, далее переходит в приложение своего банка и подтверждает оплату.
5. После проведения оплаты через контур НСПК, Сбер получает подтверждение о получении платежа, и присваивает
   заказу статусы, соответствующие результату оплаты.
6. Сбер направляет партнеру информацию о статусе заказа в ответ на запрос getOrderStatusExtended.do (gose), либо в
   callback
   Комментарии к процессу создания подписки:
7. Партнер создает заказ методом register.do, указывая в jsonParams : qrType" : "SUBSCRIPTION_SBP",
   "sbp.scenario" : "C2B_SUBSCRIPTION", "sbp.subscriptionPurpose" : "Для оплаты услуг" (пример)
8. При обработке запроса на создание заказа Сбер получает в НСПК платежную ссылку СБП (SbpPayload) и передает ее
   партнеру в ответ на register.do;
9. Партнер использует SbpPayload в своем app/mWEB, передавая его в виджет НСКП (либо используя свой виджет);
10. Клиент-физическое лицо выбирает свой банк в списке банков-эмитентов, сформированном виджетом, переходит в
    приложение своего банка, подтверждает создание подписки.
11. Банк-эмитент направляет в НСПК информацию о создании подписки.
12. НСПК направляет в Сбер уведомление о создании подписки, передавая идентификатор подписки SubscritionId,
    идентификатор банка-эмитента memberId.
13. Сбер сохраняет в заказе данные по подписке и передает партнеру информацию о созданной подписке в ответ на
    запрос getOrderStatusExtended.do (gose), либо в callback.
14. Партнер сохраняет SubscritionId и memberId в привязке к клиенту.
    Важно:
    
    Согласно правилам НСПК один клиент-физическое лицо в конкретном банке-эмитенте может создать только
    одну подписку для конкретного юридического лица.
    
    Если подписка уже создана, при попытке повторного создания подписки операция будет отклонена в банке-
    эмитенте, за исключением случая, если клиент-физическое лицо захочет поменять счет, привязанный к
    подписке. В этом случае подписка будет сохранена с новым счетом, но с тем же SubscritionId. Партнер получит
    callback (ответ на gose) с тем же SubscritionId.
    
    Удалить подписку может только клиент-физическое лицо в приложении своего банка.
    Комментарии к процессу оплаты по подписке:
15. Партнер создает заказ методом register.do, указывая в jsonParams : "qrType" : "DYNAMIC_QR_SBP",
    "sbp.scenario" : "C2B ", обязательно заполняя "description" .
16. Для оплаты по подписке партнер вызывает метод paymentOrderBySubscription, передавая в параметрах номер заказа
    orderId (получен на этапе регистрации заказа), SubscritionId и memberId;
17. Сбер направляет в НСПК запрос на оплату по подписке, передавая SubscritionId, memberId, qrcid;
18. НСПК направляет в банк-эмитент запрос на оплату по подписке, передавая SubscritionId, qrcid. Банк-эмитент
    проводит оплату, списывая средства со счета клиента-ФЛ, к которому оформлена подписка. Сбер получает от НСПК
    статус оплаты и отдает партнеру в gose/callback.
    Особенности процесса оплаты по СБП с последующим созданием подписки:
19. Партнер создает заказ методом register.do, указывая в jsonParams : "qrType" : "DYNAMIC_QR_SBP",
    "sbp.scenario" : "C2B_SUBSCRIPTION_WITH_PAYMENT". Назначение подписки "sbp.subscriptionPurpose" указывается
    обязательно, "description" - опционально.
20. По факту успешной оплаты, партнер получит callback / ответ на gose статусы заказа, подтверждающие оплату.
21. Если клиент-физическое лицо после успешной оплаты выберет в приложении своего банка действие «привязать счет»
    (или был выбран чек-бокс «привязать счет» на этапе подтверждения платежа), банк-эмитнет выполнит создание
    подписки.
22. По факту создания подписки партнер получит callback / ответ на gose с параметрами подписки.
23. Интервал между оплатой и созданием подписки зависит от реализации в банке-эмитенте сценария создания подписки
    и действий клиента-физического лица, и может достигать нескольких минут
    Получение статуса заказа
    Запрос статуса
    getOrderStatusExtended.do (gose)
    передаваемый параметр - orderId
    1 Примеры ответов (приведены основные данные)
    Успешная оплата по
    СБП через приложение
    банка (не подписка)
    {
    "errorCode": "0",
    "merchantOrderParams": [
    {
    "name": "qrType",
    "value": "DYNAMIC_QR_SBP"
    },
    {
    "name": "sbp.scenario",
    "value": "C2B"
    },
    "paymentAmountInfo": {
    "approvedAmount": 1600402,
    "depositedAmount": 1600402,
    "refundedAmount": 0,
    "paymentState": "DEPOSITED"
    },
    "transactionAttributes": [
    {
    "name": "SbolBankInvoiceId",
    "value": "bcacc95e80064f2996b7951e3070538f"
    }
    {
    "name": "memberId",
    "value": "100000000111"
    },
    {
    "name": "extTransactionId",
    "value": "B4295080208872010000120011360501"
    }
    ],
    "attributes": [
    {
    "name": "mdOrder",
    "value": "bcacc95e-8006-4f29-96b7-951e3070538f"
    },
    {
    "name": "qrcId",
    "value": "AD10007KKBGDEH9C8NIQ1QHPPFDNNH54"
    }
    ],
    "operations": [
    {
    "date": 1708508624608,
    "type": "AUTHORIZATION",
    "amount": 1600402,
    "referenceNumber": "405200490186",
    "approvalCode": "158594",
    "actionCode": 0
    }
    ],
    "orderNumber": "0863622194234",
    "orderStatus": 2,
    "actionCode": 0,
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 1600402,
    "currency": "643",
    "date": 1708508562538,
    "depositedDate": 1708508624608,
    "orderDescription": "регистрация заказа ",
    "authRefNum": "405200490186",
    "authDateTime": 1708508624608,
    } "terminalId": "20183665"
    2
    Успешная оплата через
    приложение банка с
    последующим
    созданием подписки
    {
    "errorCode": "0",
    { "merchantOrderParams": [
    "name": "qrType",
    "value": "DYNAMIC_QR_SBP"
    { },
    "name": "sbp.scenario",
    "value": "C2B_SUBSCRIPTION_WITH_PAYMENT"
    { },
    "name": "sbp.subscriptionPurpose",
    "value": "информация от ТСП с деталями привязки счета"
    { },
    "name": "app2app",
    } "value": "true"
    "paymentWay": "SBERPAY_SBP_C2B_DYN"
    },
    "paymentAmountInfo": {
    "approvedAmount": 16001,
    "depositedAmount": 16001,
    "refundedAmount": 0,
    "paymentState": "DEPOSITED"
    },
    { "transactionAttributes": [
    "name": "SbolBankInvoiceId",
    } "value": "bd1bcf4600bf4ab4a7e60c1f37eb9a75"
    ],
    { "attributes": [
    "name": "mdOrder",
    "value": "bd1bcf46-00bf-4ab4-a7e6-0c1f37eb9a75"
    { },
    "name": "qrcId",
    "value": "BD1P005QT97I6AC49KLRE09MBAE2JS7O"
    { },
    "name": "subscriptionId",
    "value": "d6422960fae848a09d179d43fbc400f9"
    { },
    "name": "memberId",
    } "value": "100000000111"
    ],
    { "operations": [
    "date": 1707121692503,
    "type": "AUTHORIZATION",
    "amount": 16001,
    "referenceNumber": "403600485418",
    "approvalCode": "026890",
    } "actionCode": 0
    ],
    "orderNumber": "7611775153674",
    "orderStatus": 2,
    "actionCode": 0,
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 16001,
    "currency": "643",
    "date": 1707121652896,
    "depositedDate": 1707121692503,
    "orderDescription": "регистрация заказа ",
    "authRefNum": "403600485418",
    "authDateTime": 1707121692503,
    } "terminalId": "20183665"
    3
    Успешная оплата по
    подписке
    {
    "orderNumber": "20060583",
    "orderStatus": 2,
    "actionCode": 0,
    "errorCode": "0",
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 1000,
    "currency": "643",
    "date": 1707121733416,
    "depositedDate": 1707121744397,
    "orderDescription": "Оплата по связке",
    "authRefNum": "403600485420",
    { "merchantOrderParams": [
    "name": "qrType",
    "value": "DYNAMIC_QR_SBP"
    { },
    "name": "sbp.scenario",
    } "value": "C2B"
    ],
    "paymentWay": "SBERPAY_SBP_C2B_DYN_SUBS"
    "authDateTime": 1707121744397,
    "terminalId": "20288435",
    "paymentAmountInfo": {
    "approvedAmount": 1000,
    "depositedAmount": 1000,
    "refundedAmount": 0,
    "paymentState": "DEPOSITED"
    },
    "bankInfo": {},
    { "transactionAttributes": [
    "name": "SbolBankInvoiceId",
    } "value": "e2df130855ff4283b21fb8d04b9ccfcb"
    ],
    { "attributes": [
    "name": "mdOrder",
    "value": "e2df1308-55ff-4283-b21f-b8d04b9ccfcb"
    { },
    "name": "qrcId",
    } "value": "BD10002NN2KCR7M39NABH4O95NIUODU3"
    ],
    { "operations": [
    "date": 1707121744397,
    "type": "AUTHORIZATION",
    "amount": 1000,
    "referenceNumber": "403600485420",
    "approvalCode": "423828",
    } ] } "actionCode": 0
    4
    Успешное создание
    подписки
    {
    "errorCode": "0",
    "merchantOrderParams": [
    {
    "name": "qrType",
    "value": "SUBSCRIPTION_SBP"
    },
    {
    "name": "sbp.scenario",
    "value": "C2B_SUBSCRIPTION"
    },
    {
    "name": "sbp.subscriptionPurpose",
    "value": "Ну-кась проверим-ка"
    },
    {
    "name": "app2app",
    "value": "true"
    }
    ],
    "bindingInfo": {
    "clientId": "client_1693389039"
    },
    "payerData": {
    "email": "test@example.com"
    },
    "transactionAttributes": [
    {
    "name": "SbolBankInvoiceId",
    "value": "803535bed0fa4c1a9944f2a3541345cc"
    }
    ],
    "attributes": [
    {
    "name": "mdOrder",
    "value": "803535be-d0fa-4c1a-9944-f2a3541345cc"
    },
    {
    "name": "qrcId",
    "value": "AB1S005HICG0UB1E90K883B4AR14G3SV"
    },
    {
    "name": "subscriptionId",
    "value": "9e53906ee00b478abb6d1be3fc8f23c0"
    },
    {
    "name": "memberId",
    "value": "100000000111"
    },
    {
    "name": "phone",
    "value": "*********0792"
    }
    ],
    "orderNumber": "0545747382784",
    "orderStatus": 2,
    "actionCode": 0,
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 0,
    "currency": "643",
    "date": 1712059648595,
    "orderDescription": "регистрация заказа с максимальным набором полей",
    "terminalId": "20183665"
    }
    5
    Оплата с созданием
    подписки, но подписка
    не создана
    {
    "errorCode": "0",
    { "merchantOrderParams": [
    "name": "qrType",
    "value": "DYNAMIC_QR_SBP"
    { },
    "name": "sbp.scenario",
    "value": "C2B_SUBSCRIPTION_WITH_PAYMENT"
    { },
    "name": "sbp.subscriptionPurpose",
    "value": "информация от ТСП с деталями привязки счета"
    { },
    "name": "app2app",
    } "value": "true"
    ],
    "paymentWay": "SBERPAY_SBP_C2B_DYN"
    "paymentAmountInfo": {
    "approvedAmount": 214,
    "depositedAmount": 214,
    "refundedAmount": 0,
    "paymentState": "DEPOSITED"
    },
    { "transactionAttributes": [
    "name": "SbolBankInvoiceId",
    } "value": "3c9d876236db4f578b02bc9f59563851"
    ],
    { "attributes": [
    "name": "mdOrder",
    "value": "3c9d8762-36db-4f57-8b02-bc9f59563851"
    { },
    "name": "qrcId",
    } "value": "AD1P002EP15654KL8NRA4RBI8CHKD53N"
    ],
    { "operations": [
    "date": 1709560873230,
    "type": "AUTHORIZATION",
    "amount": 214,
    "referenceNumber": "406400491910",
    "approvalCode": "850288",
    } "actionCode": 0
    ],
    "orderNumber": "8526268104654",
    "orderStatus": 2,
    "actionCode": 0,
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 214,
    "currency": "643",
    "date": 1709560827260,
    "depositedDate": 1709560873230,
    "orderDescription": "регистрация заказа ",
    "authRefNum": "406400491910",
    "authDateTime": 1709560873230,
    } "terminalId": "20183665"
    Отличие от п. 5 – не переданы subscriptionId, memberId
    6
    Если заказ не оплачен и
    истек
    {
    "orderNumber": "0360288122824",
    "orderStatus": 6,
    "actionCode": -2007,
    "actionCodeDescription": "Истек срок ожидания ввода данных",
    "errorCode": "0",
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 1566,
    "currency": "643",
    "date": 1710222871139,
    "orderDescription": "регистрация заказа ",
    "merchantOrderParams": [
    {
    "name": "qrType",
    "value": "DYNAMIC_QR_SBP"
    },
    {
    "name": "sbp.scenario",
    "value": "C2B"
    },
    "terminalId": "20183665",
    "transactionAttributes": [
    {
    "name": "SbolBankInvoiceId",
    "value": "26e7724616c94521a83870b345eccf23"
    }
    ],
    "attributes": [
    {
    "name": "mdOrder",
    "value": "26e77246-16c9-4521-a838-70b345eccf23"
    },
    {
    "name": "qrcId",
    "value": "AD10007ED5G6V6MD8R48TQ0DJUUP19HH"
    }
    } ]
    7
    заказ был создан для
    создания подписки, но
    подписка не создана
    (аналогично для заказа,
    который был создан, но
    не был оплачен, и срок
    жизни которого еще не
    истёк)
    {
    "errorCode": "0",
    { "merchantOrderParams": [
    "name": "qrType",
    "value": "SUBSCRIPTION_SBP"
    { },
    "name": "sbp.scenario",
    "value": "C2B_SUBSCRIPTION"
    { },
    "name": "sbp.subscriptionPurpose",
    "value": "Проверка"
    },
    { "transactionAttributes": [
    "name": "SbolBankInvoiceId",
    } "value": "bf3e0fe788104aa6af4374294d9657e1"
    ],
    { "attributes": [
    "name": "mdOrder",
    "value": "bf3e0fe7-8810-4aa6-af43-74294d9657e1"
    { },
    "name": "qrcId",
    } "value": "AB1S00316BNKKJDF9E6ALGP99QMBNTPP"
    ],
    "orderNumber": "7840349671674",
    "orderStatus": 0,
    "actionCode": -100,
    "actionCodeDescription": "Не было попыток оплаты",
    "errorMessage": "Обработка запроса прошла без системных ошибок",
    "amount": 0,
    "currency": "643",
    "date": 1710405230718,
    "orderDescription": "регистрация заказа с максимальным набором полей",
    } "terminalId": "20183665"
    8
    Попытка оплаты по
    подписке, которая
    удалена у эмитента
    {
    «errorCode»: «0»,
    { «merchantOrderParams»: [
    «name»: «qrType»,
    «value»: «DYNAMIC_QR_SBP»
    { },
    «name»: «sbp.scenario»,
    «value»: «C2B»
    },
    ],
    «paymentWay»: «SBERPAY_SBP_C2B_DYN_SUBS»
    { «transactionAttributes»: [
    «name»: «SbolBankInvoiceId»,
    } «value»: «2de620f35da34a84953653db127cbee3»
    ],
    { «attributes»: [
    «name»: «mdOrder»,
    «value»: «2de620f3-5da3-4a84-9536-53db127cbee3»
    { },
    «name»: «qrcId»,
    } «value»: «AD100060M4ECHP3T94OOP1LO1C1F1EST»
    ],
    { «operations»: [
    «date»: 1710506422007,
    «type»: «AUTHORIZATION»,
    «amount»: 130,
    «approvalCode»: «000000»,
    } «actionCode»: -5031
    ],
    «orderNumber»: «3544684621264»,
    «orderStatus»: 0,
    «actionCode»: -5031,
    «actionCodeDescription»: «Подписка не найдена.»,
    «errorMessage»: «Обработка запроса прошла без системных ошибок»,
    «amount»: 130,
    «currency»: «643»,
    «date»: 1710506408744,
    «orderDescription»: «регистрация заказа»,
    «authDateTime»: 1710506422007,
    } «terminalId»: «20288435»
    Пояснения по срокам жизни зкааза.
    Для опеделения срока жизни заказа в запросе register.do используются 2 параметра
24. sessionTimeoutSecs – время жизни заказа в секундах;
25. expirationDate – Дата окончания срока жизни заказа (Пример:"2023-12-31T23:59:59")
    Если в запросе присутствует параметр expirationDate, то значение параметра sessionTimeoutSecs не
    учитывается.
    Callback
    Уведомление по факту
    создания подписки
    https://ecomtest.sberbank.ru/callbackUrl
    additionalParams с параметрами подписки СБП:
    phone
    Маскированный Номер телефона Плательщика в формате \***\*\*\*\*\*\***XXXX
    (только если плательщик Сбера, иначе NULL)
    subscriptionId
    Идентификатор сохраненной подписки
    memberId
    Идентификатор Банка отправителя, где сохранена подписка
    extTransactionId
    Идентификатор Операции СБП C2B, если была оплата
    qrcId
    Идентификатор функциональной ссылки
    Важно для процесса тестирования
26. В случае, если требуется увидеть оформление новой подписки, необходимо предупредить об этом, чтобы на
    стороне СБЕРа (через СБОЛ) была удалена подписка. Т.к. согласно правилам НСПК один клиент в конкретном
    банке-эмитенте может создать только одну подписку для конкретного юридического лица.
27. Если подписка уже создана, то в сценариях «Создания подписки» и «Оплата с подпиской» в результате
    получится изменение счета, без изменения subscriptionId.
28. Удаление/изменение счета подписки возможно только в интерфейсе СБОЛа со стороны Клиента и никаких
    инструментов для этого, кроме СБОЛа, не предусмотрено.
