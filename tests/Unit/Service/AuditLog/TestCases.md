# Тест-кейсы: журнал действий

Список проверяемых сценариев для `src/Service/AuditLog/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## AuditLoggerTest.php

| Кейс | Метод теста |
|---|---|
| Запись с автором и деталями — `persist`+`flush` вызваны, поля сохранены | `testLogPersistsAndFlushesAuditLogWithUserAndDetails` |
| Автор `null` и детали `null` — допустимо (анонимные/системные действия) | `testLogAllowsNullUserAndNullDetails` |
