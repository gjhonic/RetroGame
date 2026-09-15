# Тест-кейсы: импорт игр из Steam

Список проверяемых сценариев для `src/Service/Steam/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## SteamClientTest.php

| Кейс | Метод теста |
|---|---|
| Первый запрос без региона успешен: повторный запрос с `cc` не выполняется | `testFetchAppDetailsReturnsDataFromFirstRequestWithoutRetrying` |
| Первый запрос без региона не дал данных: делается повторный запрос с `cc=us`, его результат возвращается | `testFetchAppDetailsRetriesWithUsRegionWhenFirstRequestHasNoData` |
| Оба запроса (без региона и с `cc=us`) не дали данных: результат `null` | `testFetchAppDetailsReturnsNullWhenBothRegionsHaveNoData` |
| Сетевая ошибка при первом запросе: сразу бросается `SteamApiException`, повтора с `cc=us` не происходит | `testFetchAppDetailsThrowsOnTransportErrorWithoutRetrying` |
| `fetchAppDetailsForRussia()`: запрос уходит сразу с `cc=ru` | `testFetchAppDetailsForRussiaRequestsWithRuRegion` |
| `fetchAppDetailsForRussia()`: `success=false` для RU → `null` без повторного запроса в другом регионе | `testFetchAppDetailsForRussiaReturnsNullWithoutRetryingOtherRegion` |
| `fetchAppDetailsForRussia()`: сетевая ошибка → `SteamApiException` | `testFetchAppDetailsForRussiaThrowsOnTransportError` |

## GameImportServiceTest.php

| Кейс | Метод теста |
|---|---|
| Новая игра: создаются и сохраняются `Game` + `SteamGame`, поля игры заполняются из ответа Steam | `testImportNextBatchCreatesNewGameAndSteamGameOnSuccess` |
| Игра уже есть в БД: используется существующая `SteamGame`, новые сущности не создаются (`persist` не вызывается) | `testImportNextBatchReusesExistingSteamGameWithoutPersisting` |
| Steam ответил, но данных нет (`success=false`): запись помечается `failed` с дефолтным сообщением | `testImportNextBatchMarksFailureWhenDetailsAreNull` |
| Запрос к Steam упал с ошибкой (`SteamApiException`): запись помечается `failed` с текстом ошибки | `testImportNextBatchMarksFailureWhenSteamApiExceptionIsThrown` |
| Steam вернул пустую порцию: результат пустой, БД и rate limiter не трогаются | `testImportNextBatchReturnsEmptyResultWhenNoApps` |
| Пауза между запросами: вызывается ровно N-1 раз (после последней игры паузы нет) | `testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne` |
| Коллизия slug: если название уже занято другой игрой, к slug добавляется appid | `testImportNextBatchAppendsAppIdToSlugOnCollision` |
| Слаггер вернул пустую строку (например, название без ascii-символов): slug строится только из appid | `testImportNextBatchFallsBackToAppIdWhenSluggerReturnsEmptyString` |
| Курсор не передан (`null`): берётся appid из сохранённого `SteamImportCursor` | `testImportNextBatchUsesPersistedCursorWhenLastAppIdIsNull` |
| Курсор передан явно: сохранённый в БД курсор игнорируется, используется переданное значение | `testImportNextBatchIgnoresPersistedCursorWhenLastAppIdIsGivenExplicitly` |
| После успешной порции курсор сдвигается на `lastAppId`, полученный от Steam | `testImportNextBatchAdvancesCursorAfterSuccessfulBatch` |
| Пустая порция (конец каталога): курсор не сдвигается дальше текущего значения | `testImportNextBatchDoesNotAdvanceCursorWhenPageIsEmpty` |
| Успешная загрузка: обложка скачивается через `GameImageDownloader`, локальный путь сохраняется в `Game::coverImagePath` | `testImportNextBatchStoresDownloadedCoverImagePath` |
| Нет `header_image` в ответе Steam: скачивание не запускается, `coverImagePath` остаётся `null` | `testImportNextBatchLeavesCoverImagePathNullWhenNoHeaderImage` |
| Полный ответ Steam: developers/publishers/genres/platforms/screenshotUrls/releaseDate корректно переносятся в `Game` | `testImportNextBatchExtractsDevelopersPublishersGenresPlatformsAndScreenshots` |
| Минимальный ответ Steam (только name): все новые поля остаются `null`, а не пустым массивом | `testImportNextBatchLeavesNewFieldsNullWhenAbsentFromResponse` |
| Популярность: `recommendations.total` из ответа Steam переносится в `Game::popularity` | `testImportNextBatchExtractsPopularityFromRecommendationsTotal` |
| `type=dlc` и базовая игра (`fullgame.appid`) уже импортирована: создаётся `Dlc`, сразу привязанная к `Game` | `testImportNextBatchCreatesDlcLinkedToExistingBaseGame` |
| `type=dlc`, базовая игра ещё не импортирована: `Dlc.game` остаётся `null`, appid сохраняется в `pendingBaseGameSteamAppId` | `testImportNextBatchStoresDlcAsPendingWhenBaseGameNotImportedYet` |
| Базовая игра импортирована после ранее сохранённого "ожидающего" DLC: `Dlc` доотвязывается автоматически | `testImportNextBatchRelinksPendingDlcsWhenBaseGameIsImported` |
| `type` не `game`/`dlc` (например `movie`): ни `Game`, ни `Dlc` не создаются, персистится только `SteamGame` | `testImportNextBatchDoesNotCreateGameOrDlcForOtherTypes` |

## ImportResultTest.php

| Кейс | Метод теста |
|---|---|
| Подсчёт записей по статусу учитывает только совпадающие | `testCountByStatusCountsOnlyMatchingEntries` |
| Подсчёт по статусу для пустого списка возвращает 0 | `testCountByStatusReturnsZeroForEmptyList` |

## PriceImportServiceTest.php

Единая точка входа — `fetchAndStorePrice()` — используется и пачечным
`importNextBatch()` (крон), и точечным `importPriceForGame()` (кнопка в
админке); большинство исходов (цена/бесплатно/недоступно/сетевая ошибка)
проверены на обоих входах.

| Кейс | Метод теста |
|---|---|
| Есть `price_overview.final`: снимок помечается платным, `priceKopecks` верен, у `SteamGame` `availableInRussia=true` | `testImportNextBatchMarksPricedGameWhenPriceOverviewPresent` |
| `is_free=true`: снимок помечается бесплатным, `priceKopecks=0`, у `SteamGame` проставляется `priceFree=true` (исключает игру из будущих пачек, см. `SteamGameRepository::findBatchForPriceImport()`) | `testImportNextBatchMarksFreeGameWhenIsFreeTrue` |
| Платная игра: `SteamGame::priceFree` остаётся `false` | `testImportNextBatchLeavesPriceFreeFalseWhenGameIsPaid` |
| Steam ответил `success=false` для RU (`details=null`): снимок помечается недоступным, у `SteamGame` `availableInRussia=false` | `testImportNextBatchMarksUnavailableWhenDetailsAreNull` |
| Игра ранее была недоступна (`availableInRussia=false`), в этот раз появилась цена: статус на `SteamGame` восстанавливается в `true` (в отличие от `priceFree`, доступность не одноразовая метка — обновляется в обе стороны) | `testImportNextBatchRestoresSteamGameAvailabilityWhenGameBecomesAvailableAgain` |
| Ответ есть, но нет `price_overview` и не бесплатная: снимок помечается недоступным | `testImportNextBatchMarksUnavailableWhenNoPriceOverviewAndNotFree` |
| `SteamApiException` на одной игре в пачке: она пропускается (`skippedCount`), обработка остальных продолжается | `testImportNextBatchSkipsGameOnSteamApiExceptionAndContinuesWithRest` |
| Снимок за сегодня уже существует: обновляется та же сущность, `persist()` не вызывается повторно | `testImportNextBatchUpdatesExistingPriceRecordForSameDayInsteadOfCreatingNew` |
| Пауза между запросами — случайное значение от `RandomDelayRangeInterface`, N-1 раз (после последней игры паузы нет) | `testImportNextBatchDelaysBetweenItemsUsingRandomDelayRangeValue` |
| Курсор не в начале: выборка стартует с `(lastPopularity, lastSteamGameId)` | `testImportNextBatchUsesPersistedCursorAsStartingPoint` |
| После пачки курсор сдвигается на `popularity`/id последней обработанной игры (порядок обхода — по убыванию `Game::popularity`, см. `SteamGameRepository::findBatchForPriceImport()`) | `testImportNextBatchAdvancesCursorToLastProcessedGamePopularityAndId` |
| У последней обработанной игры нет `popularity` (`null`): в курсор сохраняется `-1` — не `null`, иначе обход зациклился бы на верхушке списка | `testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone` |
| Пустая порция в середине дня (конец каталога достигнут раньше, чем истёк день): результат пустой, курсор НЕ сбрасывается на начало — "докрутка" в тот же день больше не происходит, кого не успели импортировать, тех не успели | `testImportNextBatchStopsWithoutWrappingWhenCatalogEndReachedMidDay` |
| Первый запуск в новый календарный день (`updatedAt` курсора — вчера): курсор сбрасывается в начало списка (`lastPopularity=null`) ещё до выборки, `startedNewDay=true`, даже если вчера курсор был далеко не в начале | `testImportNextBatchResetsCursorOnFirstRunOfNewDay` |
| Каталог совсем пуст (пустая выборка): пустой результат, БД/rate limiter не трогаются | `testImportNextBatchReturnsEmptyResultWhenCatalogIsCompletelyEmpty` |
| `importPriceForGame()`: платная игра | `testImportPriceForGameReturnsPricedResult` |
| `importPriceForGame()`: бесплатная игра, у `SteamGame` проставляется `priceFree=true` | `testImportPriceForGameReturnsFreeResult` |
| `importPriceForGame()`: недоступна в РФ, у `SteamGame` `availableInRussia=false` | `testImportPriceForGameReturnsUnavailableResult` |
| `importPriceForGame()`: `SteamApiException` → `null`, ничего не персистится | `testImportPriceForGameReturnsNullOnSteamApiException` |

## PriceImportResultTest.php

| Кейс | Метод теста |
|---|---|
| Счётчики `countFree()`/`countUnavailable()`/`countPriced()` учитывают только совпадающие записи | `testCountersCountOnlyMatchingEntries` |
| Счётчики для пустого списка возвращают 0 | `testCountersReturnZeroForEmptyList` |

## RandomIntDelayRangeTest.php

| Кейс | Метод теста |
|---|---|
| `next($min, $max)` всегда возвращает значение из диапазона `[$min, $max]` | `testNextReturnsValueWithinRange` |
| `next($n, $n)` возвращает `$n` | `testNextReturnsExactValueWhenMinEqualsMax` |

## SteamReleaseDateParserTest.php

| Кейс | Метод теста |
|---|---|
| Распознаёт русские сокращения месяцев (в т.ч. «мая» — единственную форму, отличающуюся от именительного падежа в 3-й букве) и даты без «г.» на конце | `testParseRecognizesRussianSteamDateFormat` (data provider) |
| Нераспознаваемый ввод (`null`, пустая строка, только год, «Скоро», неизвестный месяц) → `null` | `testParseReturnsNullForUnrecognizedInput` (data provider) |
