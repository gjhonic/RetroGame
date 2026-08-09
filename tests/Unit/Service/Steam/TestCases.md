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

## ImportResultTest.php

| Кейс | Метод теста |
|---|---|
| Подсчёт записей по статусу учитывает только совпадающие | `testCountByStatusCountsOnlyMatchingEntries` |
| Подсчёт по статусу для пустого списка возвращает 0 | `testCountByStatusReturnsZeroForEmptyList` |

## SteamReleaseDateParserTest.php

| Кейс | Метод теста |
|---|---|
| Распознаёт русские сокращения месяцев (в т.ч. «мая» — единственную форму, отличающуюся от именительного падежа в 3-й букве) и даты без «г.» на конце | `testParseRecognizesRussianSteamDateFormat` (data provider) |
| Нераспознаваемый ввод (`null`, пустая строка, только год, «Скоро», неизвестный месяц) → `null` | `testParseReturnsNullForUnrecognizedInput` (data provider) |
