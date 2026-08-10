# Тест-кейсы: JSON API каталога игр в админке

Список проверяемых сценариев для `src/Controller/Api/Admin/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GameApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список игр: страница по умолчанию (сортировка `name ASC`, `perPage=25`), нормализация `coverImageUrl`, разработчики/издатели/жанры в ответе | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[...]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий (значения фильтров триммятся, неизвестные ключи фильтров отбрасываются) | `testListPassesFiltersAndSortingToRepository` |
| Сортировка по `developers`/`publishers` (агрегаты по связанным сущностям) передаётся в репозиторий как есть | `testListSortsByDevelopersOrPublishersWhenRequested` |
| Неизвестный `sortBy` → сортировка по имени; `perPage` вне диапазона клампится до максимума | `testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Детали игры по `id`: разработчики/жанры возвращаются как массивы имён | `testShowReturnsFullDetailWithRelatedEntityNames` |
| Несуществующий `id` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |

## GenreApiControllerTest.php / DeveloperApiControllerTest.php / PublisherApiControllerTest.php

Справочники (жанры/разработчики/издатели) устроены одинаково — id, name,
gamesCount; набор кейсов идентичен во всех трёх файлах (отличаются только
данные в фикстурах).

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `name ASC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[name]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий (значение фильтра триммится, неизвестные ключи отбрасываются) | `testListPassesFiltersAndSortingToRepository` |
| Неизвестный `sortBy` → сортировка по имени; `perPage` вне диапазона клампится до максимума | `testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |

## StatsApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| `totals`/`gamesByYear`/`topGenres` (топ-6 по `gamesCount`)/`scoreDistribution` собираются из репозиториев и отдаются как есть | `testIndexReturnsTotalsAndAggregatedStats` |

## CronRunApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список запусков: страница по умолчанию (сортировка `startedAt DESC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[command]`/`filters[status]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий (значения фильтров триммятся) | `testListPassesFiltersAndSortingToRepository` |
| `dateFrom`/`dateTo` парсятся в `DateTimeImmutable` и передаются в репозиторий | `testListPassesDateRangeToRepository` |
| Неизвестный `sortBy` → сортировка по `startedAt` | `testListFallsBackToStartedAtSortingForUnknownSortBy` |
| Детали запуска: статус/аргументы/признак наличия лога | `testShowReturnsRunDetail` |
| Несуществующий `id` в `show()` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| Справочник `/commands` — список уникальных имён команд | `testCommandsReturnsDistinctCommandList` |
| `/timeline` передаёт `dateFrom`/`dateTo` из query в репозиторий как есть | `testTimelinePassesRequestedDateRangeToRepository` |
| `/timeline` без `dateFrom`/`dateTo` — подставляются сутки по умолчанию | `testTimelineDefaultsToLastDayWhenRangeNotProvided` |
| `/{id}/log` отдаёт текст лога через `CronLogReader` с `Content-Type: text/plain` | `testLogReturnsPlainTextContent` |
| `/{id}/log?download=1` добавляет `Content-Disposition: attachment` с именем файла | `testLogWithDownloadFlagSetsContentDisposition` |
| `/{id}/log` без файла лога → `NotFoundHttpException` | `testLogThrowsNotFoundExceptionWhenLogFileMissing` |

## CronApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список: перед выдачей вызывается `CronSyncService::sync()`, в ответе — `lastRun` по данным `CronRunRepository::findLatest()` | `testListSyncsAndReturnsCronsWithLastRun` |
| Крон ещё ни разу не запускался — `lastRun: null` | `testListReturnsNullLastRunWhenCronNeverRan` |
| Детали крона по `id` | `testShowReturnsCronDetail` |
| Несуществующий `id` в `show()` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| `PATCH` с корректным `#RRGGBB` — цвет сохраняется, `flush()` вызывается | `testUpdateColorSetsValidColorAndFlushes` |
| `PATCH` с некорректным форматом цвета — `422` с ошибкой, `flush()` не вызывается | `testUpdateColorRejectsInvalidFormat` |
| `PATCH` с несуществующим `id` → `NotFoundHttpException` | `testUpdateColorThrowsNotFoundExceptionForUnknownId` |
