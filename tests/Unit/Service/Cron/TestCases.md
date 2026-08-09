# Тест-кейсы: учёт запусков кронов

Список проверяемых сценариев для `src/Service/Cron/`. При добавлении нового
кейса — дописывайте сюда строку с методом, где он проверяется.

## CronLogReaderTest.php

| Кейс | Метод теста |
|---|---|
| Лог-файл существует — возвращается его содержимое | `testReadReturnsFileContentWhenLogExists` |
| У запуска нет `logPath` — возвращается `null` | `testReadReturnsNullWhenRunHasNoLogPath` |
| `logPath` задан, но файла на диске нет — возвращается `null` | `testReadReturnsNullWhenLogFileIsMissing` |
