# Тест-кейсы: JSON API каталога игр в админке

Список проверяемых сценариев для `src/Controller/Api/Admin/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## AuditLogApiControllerTest.php

Журнал действий (`/admin/audit-logs`) — доступен только `ROLE_ADMIN`
(строже, чем остальная админка на `ROLE_MODERATOR`), см.
`src/Controller/Admin/AuditLogController.php`.

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `createdAt DESC`, `perPage=25`), `details` не включён в списочный ответ | `testListReturnsItemsWithDefaultPaginationAndSort` |
| `filters[...]`/`dateFrom`/`dateTo`/`sortBy`/`sortDir` передаются в репозиторий | `testListPassesFiltersAndDateRangeToRepository` |
| Запрошенная страница выходит за `totalPages` → клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Справочник уникальных значений `action` для фильтра | `testActionsReturnsDistinctActions` |
| Детали записи включают полный `details` (JSON) | `testShowReturnsDetailWithJsonDetails` |
| Несуществующий ID → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |

## OurGameApiControllerTest.php

`OurGameRequestFactory` собран из реального `Serializer`+`Validator` (по образцу
`RegistrationApiControllerTest`), т.к. это простой класс без побочных эффектов —
моки только на `OurGameRepository`/`OurGameCrudService`/`OurGameImageUploadService`.

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `name ASC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[...]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий | `testListPassesFiltersAndSortingToRepository` |
| Неизвестный `sortBy` → сортировка по `name`; `perPage` вне диапазона клампится | `testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage` |
| `POST` с валидными данными — создаёт игру через `OurGameCrudService`, `201` | `testCreateReturnsCreatedGameOnValidRequest` |
| `POST` с пустым названием — `422`, сервис не вызывается | `testCreateReturnsValidationErrorsForBlankName` |
| Детали игры по `id` (включая `downloadLinks`) | `testShowReturnsFullDetail` |
| Несуществующий `id` в `show()` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| `PATCH` с несуществующим `id` → `NotFoundHttpException` | `testUpdateThrowsNotFoundExceptionForUnknownId` |
| `PATCH` с валидными данными — обновляет игру через `OurGameCrudService` | `testUpdateReturnsUpdatedGame` |
| `DELETE` — удаляет игру через `OurGameCrudService`, `204` | `testDeleteRemovesGameAndReturns204` |
| `DELETE` с несуществующим `id` → `NotFoundHttpException` | `testDeleteThrowsNotFoundExceptionForUnknownId` |
| `POST /{id}/cover` с файлом — сохраняет обложку через `OurGameImageUploadService` | `testUploadCoverStoresFileAndReturnsUpdatedGame` |
| `POST /{id}/cover` без файла — `422`, сервис не вызывается | `testUploadCoverReturnsValidationErrorWhenNoFile` |
| `POST /{id}/cover` с файлом, превышающим `upload_max_filesize` (`UploadedFile::isValid()` → false) — `422` с текстом ошибки, сервис не вызывается | `testUploadCoverReturnsValidationErrorWhenFileExceedsUploadLimit` |
| `DELETE /{id}/screenshots` передаёт `url` из тела запроса в сервис как есть | `testRemoveScreenshotPassesUrlFromRequestBody` |

## OurGameDownloadLinkApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| `POST` с валидными данными — создаёт ссылку через `OurGameDownloadLinkCrudService`, `201` | `testCreateReturnsCreatedLinkOnValidRequest` |
| `POST` с некорректной платформой/URL — `422` с ошибками по обоим полям | `testCreateReturnsValidationErrorsForInvalidPayload` |
| `POST` с несуществующей игрой → `NotFoundHttpException` | `testCreateThrowsNotFoundExceptionForUnknownGame` |
| `PATCH` с валидными данными — обновляет платформу/URL | `testUpdateReturnsUpdatedLink` |
| `PATCH`/`DELETE`/`.../image` — ссылка, принадлежащая другой игре, не найдена (`ourGameId` из URL сверяется с `link->getOurGame()`) | `testUpdateThrowsNotFoundExceptionWhenLinkBelongsToAnotherGame` |
| `POST .../image` с файлом — сохраняет иконку через `OurGameImageUploadService` | `testUploadImageStoresFileAndReturnsUpdatedLink` |
| `POST .../image` без файла — `422`, сервис не вызывается | `testUploadImageReturnsValidationErrorWhenNoFile` |
| `DELETE` — удаляет ссылку через `OurGameDownloadLinkCrudService`, `204` | `testDeleteRemovesLinkAndReturns204` |
| `DELETE` с несуществующей ссылкой → `NotFoundHttpException` | `testDeleteThrowsNotFoundExceptionForUnknownLink` |

## OurGamePostApiControllerTest.php

`OurGamePostRequestFactory` собран из реального `Serializer`+`Validator` (по образцу
`OurGameApiControllerTest`) — моки только на
`OurGamePostRepository`/`OurGamePostCrudService`/`OurGamePostImageUploadService`.
Автор поста — `#[CurrentUser]`, в тестах передаётся напрямую как аргумент.

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `postedAt DESC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[...]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий | `testListPassesFiltersAndSortingToRepository` |
| `POST` с валидными данными — создаёт пост через `OurGamePostCrudService` с текущим пользователем автором, `201` | `testCreateReturnsCreatedPostOnValidRequest` |
| `POST` с пустым `shortDescription` — `422`, сервис не вызывается | `testCreateReturnsValidationErrorForBlankShortDescription` |
| `POST` с несуществующей игрой (`OurGameNotFoundException` из сервиса) → `422` с ошибкой по `gameId` | `testCreateReturnsValidationErrorWhenGameNotFound` |
| Детали поста по `id` | `testShowReturnsPostDetail` |
| Несуществующий `id` в `show()` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| `PATCH` с валидными данными — обновляет пост через `OurGamePostCrudService` | `testUpdateSavesPostAndReturnsUpdatedData` |
| `PATCH` с несуществующим `id` → `NotFoundHttpException` | `testUpdateThrowsNotFoundExceptionForUnknownId` |
| `DELETE` — удаляет пост через `OurGamePostCrudService`, `204` | `testDeleteRemovesPostAndReturns204` |
| `DELETE` с несуществующим `id` → `NotFoundHttpException` | `testDeleteThrowsNotFoundExceptionForUnknownId` |
| `POST /{id}/image` с файлом — сохраняет картинку через `OurGamePostImageUploadService` | `testUploadImageStoresFileAndReturnsUpdatedPost` |
| `POST /{id}/image` без файла — `422`, сервис не вызывается | `testUploadImageReturnsValidationErrorWhenNoFile` |
| `POST /{id}/content-images` с файлом — сохраняет картинку из редактора, возвращает `{url}` | `testUploadContentImageStoresFileAndReturnsUrl` |
| `POST /{id}/content-images` без файла — `422`, сервис не вызывается | `testUploadContentImageReturnsValidationErrorWhenNoFile` |

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

## SteamGameApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список Steam-записей: страница по умолчанию (сортировка `steamAppId ASC`, `perPage=25`), название/обложка связанной игры в ответе | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[...]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий (значения фильтров триммятся, неизвестные ключи фильтров отбрасываются) | `testListPassesFiltersAndSortingToRepository` |
| Неизвестный `sortBy` → сортировка по `steamAppId`; `perPage` вне диапазона клампится до максимума | `testListFallsBackToSteamAppIdSortingForUnknownSortByAndClampsPerPage` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Детали записи по `id`: ссылка на игру (`gameId`/`gameName`) и `rawData` возвращаются как есть | `testShowReturnsFullDetailWithGameLinkAndRawData` |
| Несуществующий `id` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |

## UserApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список пользователей: страница по умолчанию (сортировка `email ASC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `filters[...]`/`sortBy`/`sortDir`/`perPage` из query передаются в репозиторий (значения фильтров триммятся, неизвестные ключи фильтров отбрасываются) | `testListPassesFiltersAndSortingToRepository` |
| Неизвестный `sortBy` → сортировка по `email`; `perPage` вне диапазона клампится до максимума | `testListFallsBackToEmailSortingForUnknownSortByAndClampsPerPage` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Детали пользователя по `id` | `testShowReturnsFullDetail` |
| Несуществующий `id` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| `POST /moderators` с валидными данными — создаёт модератора через `ModeratorCreationService`, `201` | `testCreateModeratorReturnsCreatedUserOnValidRequest` |
| `POST /moderators` с некорректным телом — `422` с ошибками по каждому полю, сервис не вызывается | `testCreateModeratorReturnsValidationErrorsForInvalidPayload` |
| `POST /moderators` с занятым email — `ConflictHttpException` | `testCreateModeratorThrowsConflictWhenEmailAlreadyRegistered` |

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
| Список: элементы содержат `cronName`/`cronColor` подобранного по `command` крона (связи в БД нет, см. `CronRepository::findAllIndexedByCommand()`) | `testListIncludesMatchingCronNameAndColor` |

## CronApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список: перед выдачей вызывается `CronSyncService::sync()`, в ответе — `lastRun` по данным `CronRunRepository::findLatest()` | `testListSyncsAndReturnsCronsWithLastRun` |
| Крон ещё ни разу не запускался — `lastRun: null` | `testListReturnsNullLastRunWhenCronNeverRan` |
| Детали крона по `id` | `testShowReturnsCronDetail` |
| Несуществующий `id` в `show()` → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| `PATCH` с корректным `#RRGGBB` — цвет сохраняется, `flush()` вызывается | `testUpdateSetsValidColorAndFlushes` |
| `PATCH` с некорректным форматом цвета — `422` с ошибкой, `flush()` не вызывается | `testUpdateRejectsInvalidColorFormat` |
| `PATCH` с `name` — название триммится и сохраняется, `flush()` вызывается | `testUpdateSetsNameAndFlushes` |
| `PATCH` с пустой/пробельной строкой в `name` — название сбрасывается в `null` | `testUpdateWithBlankNameClearsIt` |
| `PATCH` с нестроковым `name` — `422` с ошибкой, `flush()` не вызывается | `testUpdateRejectsNonStringName` |
| `PATCH` одновременно с `name` и `color` — оба поля сохраняются | `testUpdateSetsNameAndColorTogether` |
| `PATCH` с несуществующим `id` → `NotFoundHttpException` | `testUpdateThrowsNotFoundExceptionForUnknownId` |

## UserReportApiControllerTest.php

Список отчётов пользователей о проблемах (`user_report`) — только чтение,
создание доступно всем через `Api/Public/UserReportApiController`.

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `createdAt DESC`, `perPage=25`) | `testListReturnsItemsWithDefaultPaginationAndSort` |
| `filters[type]`/`sortBy`/`sortDir` передаются в репозиторий | `testListPassesTypeFilterAndSortToRepository` |
| Запрошенная страница выходит за `totalPages` → клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |

## ScoreDieAgainApiControllerTest.php

Админский список/сброс таблицы лидеров DIE//AGAIN (`score_die_again`) —
переиспользует `ScoreDieAgainRepository`/`ScoreDieAgainMapper` из публичного
API (`Api/Public/ScoreDieAgainApiControllerTest`), но с `perPage`-пагинацией
(как у `DeveloperApiController`) вместо фиксированного `PER_PAGE`, плюс
`DELETE` для полной очистки таблицы.

| Кейс | Метод теста |
|---|---|
| Список: страница по умолчанию (сортировка `kills DESC`, `perPage=25`) | `testListReturnsPageWithDefaultSortingAndPagination` |
| `sortBy`/`sortDir`/`perPage` из query передаются в репозиторий | `testListPassesSortingAndPerPageToRepository` |
| `perPage` вне диапазона клампится до максимума (100) | `testListClampsPerPageToMax` |
| Запрошенная страница выходит за `totalPages` → клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| `DELETE` — удаляет все результаты через `ScoreDieAgainRepository::deleteAll()`, возвращает `{deleted: N}` | `testResetDeletesAllResultsAndReturnsDeletedCount` |
