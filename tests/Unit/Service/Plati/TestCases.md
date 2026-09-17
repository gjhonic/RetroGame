# Тест-кейсы: импорт наличия игр и цен на plati.market

Список проверяемых сценариев для `src/Service/Plati/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## PlatiClientTest.php

| Кейс | Метод теста |
|---|---|
| Ответ API маппится в `PlatiSearchItem[]`, запрос уходит с `owner=1` (plati.market) | `testSearchMapsItemsFromResponse` |
| Пустой `items.item`: пустой массив | `testSearchReturnsEmptyArrayWhenNoItemsInResponse` |
| Ключа `items` нет в ответе вовсе: пустой массив, а не ошибка | `testSearchReturnsEmptyArrayWhenItemsKeyMissing` |
| Сетевая ошибка (транспорт): `PlatiApiException`, а не необработанное исключение | `testSearchThrowsPlatiApiExceptionOnTransportError` |
| `price_rur` из ответа маппится в `PlatiSearchItem::$priceRur` | `testSearchMapsItemsFromResponse` |
| `price_rur` отсутствует в ответе: `priceRur` = null | `testSearchMapsMissingPriceRurToNull` |
| `seller_name` из ответа маппится в `PlatiSearchItem::$sellerName` | `testSearchMapsItemsFromResponse` |
| `seller_name` отсутствует в ответе: `sellerName` = `''` | `testSearchMapsMissingSellerNameToEmptyString` |

## GameMatcherTest.php

Общая логика сопоставления объявлений с игрой, вынесенная из
`GameImportService` — используется им и `PriceImportService`.

| Кейс | Метод теста |
|---|---|
| Объявления без названия игры в тексте отбрасываются | `testMatchingItemsKeepsOnlyItemsContainingGameNameInTitle` |
| Совпадение устойчиво к апострофу | `testMatchingItemsIsApostropheInsensitive` |
| Совпадения сортируются по убыванию продаж | `testTopMatchesReturnsMatchesSortedByMostSoldDescending` |
| Результат ограничен лимитом | `testTopMatchesLimitsResultCount` |
| Нет совпадений — пустой массив | `testTopMatchesReturnsEmptyArrayWhenNoItemsMatch` |
| Поиск конкретной карточки по ссылке — находит | `testFindByUrlReturnsMatchingItem` |
| Поиск конкретной карточки по ссылке — не находит | `testFindByUrlReturnsNullWhenNoItemHasThisUrl` |

## GameImportServiceTest.php

Поиск полнотекстовый (Digiseller), поэтому среди результатов отбираются
только те, чьё название реально содержит название игры (см.
`GameMatcher::matchingItems()`), а из совпадений сохраняется до 3 самых
продаваемых (`GameMatcher::topMatches()`) — по одной записи `PlatiGame`
на каждого продавца, а не только на первое место.

| Кейс | Метод теста |
|---|---|
| Среди 4 совпадающих объявлений сохраняются 3 самых продаваемых, в порядке убывания продаж, каждое персистится отдельной `PlatiGame` | `testImportNextBatchCreatesPlatiGamesFromUpToThreeBestSellingMatches` |
| Имя продавца из совпавшего объявления сохраняется в `PlatiGame::sellerName` | `testImportNextBatchStoresSellerNameFromMatchedItem` |
| Совпадение только одно: сохраняется один продавец, а не три | `testImportNextBatchImportsSingleSellerWhenOnlyOneMatchFound` |
| Совпадение устойчиво к апострофу (реальный случай — Garry's Mod ↔ "Garrys Mod" без апострофа в объявлении) | `testImportNextBatchMatchesViaApostropheInsensitiveNormalization` |
| Поиск вернул 0 результатов: не найдено, `reason` = "ничего не найдено по запросу" | `testImportNextBatchReturnsNotFoundWhenSearchHasNoResults` |
| Результаты есть, но ни один не совпадает по названию: не найдено, `reason` упоминает это | `testImportNextBatchReturnsNotFoundWhenNoResultsMatchGameName` |
| `PlatiApiException` на одной игре в пачке: она пропускается (`reason` упоминает "ошибка запроса"), обработка остальных продолжается | `testImportNextBatchSkipsGameOnPlatiApiExceptionAndContinuesWithRest` |
| Продавец уже сохранён по этой ссылке (сменилось имя продавца): обновляется существующая запись, новая не создаётся | `testImportNextBatchUpdatesExistingPlatiGameByUrlInsteadOfCreatingNew` |
| Пауза между запросами: вызывается ровно N-1 раз (после последней игры паузы нет) | `testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne` |
| После пачки курсор сдвигается на popularity/id последней проверенной игры | `testImportNextBatchAdvancesCursorToLastCheckedGamePopularityAndId` |
| У последней проверенной игры нет popularity (`null`): в курсор сохраняется `-1` | `testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone` |
| Пачка пуста и курсор не в начале списка: курсор сбрасывается, повторная выборка тоже пуста — пустой результат с `wrapped=true`, `flush()` не вызывается | `testImportNextBatchReturnsEmptyResultWhenNothingLeftToCheckEvenAfterWrap` |
| Пачка пуста и курсор не в начале списка: курсор сбрасывается, повторная выборка находит игры — они проверяются в рамках этого же запуска, `wrapped=true` | `testImportNextBatchWrapsAndChecksGamesWhenBatchEmptyMidCursor` |
| Пачка пуста, курсор уже в начале списка: пустой результат без сброса/повтора, `wrapped=false` | `testImportNextBatchReturnsEmptyResultWithoutWrapWhenCursorAlreadyAtStart` |

## PriceImportServiceTest.php

Импорт цен уже найденных продавцов (`PlatiGame`) — по образцу
`Steam\PriceImportServiceTest`. Курсор идёт по `PlatiGame`, а не по
`Game` (у одной игры несколько продавцов), и ежедневно сбрасывается (как
у Steam). Цена ищется среди повторных результатов поиска именно по
ссылке продавца (`GameMatcher::findByUrl()`), а не просто "самая
продаваемая" — иначе цена одного продавца перезаписала бы цену другого.

| Кейс | Метод теста |
|---|---|
| Совпадение по ссылке найдено, есть цена: снимок с `priceKopecks` = `priceRur * 100` | `testImportNextBatchMarksPricedGameWhenMatchFound` |
| Совпадений нет вовсе: снимок без цены (`isFound() === false`) | `testImportNextBatchMarksUnavailableWhenNoMatch` |
| Совпадение по ссылке есть, но у него нет `priceRur`: снимок без цены | `testImportNextBatchMarksUnavailableWhenMatchHasNoPrice` |
| Поиск нашёл только карточку другого продавца (другая ссылка): снимок без цены — цена чужого продавца не подставляется | `testImportNextBatchMarksUnavailableWhenSearchFindsOnlyOtherSellersUrl` |
| `PlatiApiException` на одном продавце в пачке: он пропускается (`skippedCount`), обработка остальных продолжается | `testImportNextBatchSkipsGameOnPlatiApiExceptionAndContinuesWithRest` |
| Снимок за сегодня уже есть: обновляется существующая запись, новая не создаётся | `testImportNextBatchUpdatesExistingPriceRecordForSameDayInsteadOfCreatingNew` |
| Пауза между запросами: вызывается ровно N-1 раз | `testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne` |
| Курсор из репозитория — стартовая точка выборки | `testImportNextBatchUsesPersistedCursorAsStartingPoint` |
| После пачки курсор сдвигается на popularity/id последней обработанной `PlatiGame` | `testImportNextBatchAdvancesCursorToLastProcessedGamePopularityAndId` |
| У последней игры нет popularity (`null`): в курсор сохраняется `-1` | `testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone` |
| Очередь пуста: пустой результат | `testImportNextBatchReturnsEmptyResultWhenQueueIsEmpty` |
| Первый запуск за новый день: курсор сбрасывается в начало списка | `testImportNextBatchResetsCursorOnFirstRunOfNewDay` |
