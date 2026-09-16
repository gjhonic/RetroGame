# Тест-кейсы: импорт наличия игр на ggsel.net

Список проверяемых сценариев для `src/Service/Ggsel/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GgselClientTest.php

| Кейс | Метод теста |
|---|---|
| Страница товара с непустым `offerCount`: `GgselSlugAttempt::isFound()===true`, ссылка из `<link rel="canonical">`, `reason==="найдено"` | `testFindBySlugReturnsFoundAttemptWithCanonicalUrlWhenPageHasNonEmptyOffer` |
| Ответ не 200 (например 404): не найдено, `reason==="HTTP 404"` | `testFindBySlugReturnsHttpStatusReasonOnNon200Status` |
| 200, но нет JSON-LD `Product`: не найдено, причина упоминает отсутствие данных о товаре (страница "не найдено" рендерится на том же шаблоне) | `testFindBySlugReturnsReasonWhenNoProductJsonLdPresent` |
| `offerCount = 0`: не найдено, причина упоминает `offerCount = 0` — категория есть, но пуста | `testFindBySlugReturnsReasonWhenOfferCountIsZero` |
| Нет `<link rel="canonical">`: URL берётся из `offers.url` | `testFindBySlugFallsBackToOffersUrlWhenNoCanonicalLink` |
| Сетевая ошибка (транспорт): `GgselApiException`, а не необработанное исключение | `testFindBySlugThrowsGgselApiExceptionOnTransportError` |

## GameImportServiceTest.php

| Кейс | Метод теста |
|---|---|
| Найдено по базовому слагу (без суффикса): создаётся и персистится `GgselGame`, в `attempts` — одна запись | `testImportNextBatchCreatesGgselGameWhenFoundByBaseSlug` |
| Базовый слаг не сработал: пробуется следующий кандидат из `SLUG_SUFFIXES` (реальный случай — Garry's Mod → "garrys-mod-keys"), в `attempts` — обе попытки с их `reason` | `testImportNextBatchTriesKeysSuffixWhenBaseSlugNotFound` |
| Результат содержит по одной записи `GgselCheckResult` на каждую игру, в порядке обхода, с корректным `isFound()`/`ggselGame` для найденных и ненайденных | `testImportNextBatchReturnsOneResultPerGameInOrderWithMixedOutcomes` |
| Ни один слаг-кандидат не подошёл: игра остаётся без `GgselGame`, `persist` не вызывается, в `attempts` — обе попытки с причиной | `testImportNextBatchLeavesGameUncheckedResultWhenNoCandidateSlugMatches` |
| `GgselApiException` на одной игре в пачке: она пропускается (в `attempts` — синтетическая запись "ошибка запроса"), обработка остальных продолжается | `testImportNextBatchSkipsGameOnGgselApiExceptionAndContinuesWithRest` |
| Первый слаг-кандидат сразу подошёл: остальные кандидаты не пробуются (`findBySlug()` вызывается ровно один раз) | `testImportNextBatchStopsTryingFurtherCandidateSlugsAfterFirstMatch` |
| Игра уже имеет `GgselGame` (ссылка изменилась): обновляется существующая запись, новая не создаётся | `testImportNextBatchUpdatesExistingGgselGameUrlInsteadOfCreatingNew` |
| Пауза между запросами: вызывается ровно N-1 раз (после последней игры паузы нет) | `testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne` |
| После пачки курсор сдвигается на popularity/id последней проверенной игры | `testImportNextBatchAdvancesCursorToLastCheckedGamePopularityAndId` |
| У последней проверенной игры нет popularity (`null`): в курсор сохраняется `-1` | `testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone` |
| Пачка пуста и курсор не в начале списка: курсор сбрасывается, повторная выборка тоже пуста — пустой результат с `wrapped=true`, `flush()` не вызывается | `testImportNextBatchReturnsEmptyResultWhenNothingLeftToCheckEvenAfterWrap` |
| Пачка пуста и курсор не в начале списка: курсор сбрасывается, повторная выборка находит игры — они проверяются в рамках этого же запуска, `wrapped=true` | `testImportNextBatchWrapsAndChecksGamesWhenBatchEmptyMidCursor` |
| Пачка пуста, курсор уже в начале списка: пустой результат без сброса/повтора, `wrapped=false` | `testImportNextBatchReturnsEmptyResultWithoutWrapWhenCursorAlreadyAtStart` |
| Слаггер вернул пустую строку (например, название без ascii-символов): `findBySlug()` не вызывается вовсе | `testImportNextBatchSkipsClientCallWhenSluggerReturnsEmptyString` |
