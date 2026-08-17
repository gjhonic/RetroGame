# Тест-кейсы: учёт запусков кронов

Список проверяемых сценариев для `src/Service/Cron/`. При добавлении нового
кейса — дописывайте сюда строку с методом, где он проверяется.

## CronLogReaderTest.php

| Кейс | Метод теста |
|---|---|
| Лог-файл существует — возвращается его содержимое | `testReadReturnsFileContentWhenLogExists` |
| У запуска нет `logPath` — возвращается `null` | `testReadReturnsNullWhenRunHasNoLogPath` |
| `logPath` задан, но файла на диске нет — возвращается `null` | `testReadReturnsNullWhenLogFileIsMissing` |

## CronMapperTest.php

| Кейс | Метод теста |
|---|---|
| `toListItem` без последнего запуска — `lastRun: null`, `name`/`color` крона в ответе | `testToListItemWithoutLastRun` |
| `toListItem` с последним запуском — статус/время запуска в `lastRun` | `testToListItemWithLastRun` |
| `toDetail` — id/command/name/color/createdAt | `testToDetail` |

## CronSyncServiceTest.php

| Кейс | Метод теста |
|---|---|
| Обнаруженные команды, отсутствующие в справочнике, персистятся и сохраняются одним `flush()` | `testSyncPersistsOnlyCommandsMissingFromRepository` |
| Все обнаруженные команды уже есть в справочнике — `persist()`/`flush()` не вызываются | `testSyncDoesNothingWhenAllCommandsAlreadyKnown` |
