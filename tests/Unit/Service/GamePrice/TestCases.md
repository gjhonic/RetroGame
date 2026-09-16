# Тест-кейсы: маппинг цены игры для API

Список проверяемых сценариев для `src/Service/GamePrice/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GamePriceMapperTest.php

`store`/`storeUrl` не хранятся в `GamePrice` — источник цены сейчас всегда
Steam, поэтому магазин/ссылка выводятся на лету из `steamAppId`, переданного
вызывающей стороной (см. `Api/Admin` и `Api/Public` `GameApiController`).

| Кейс | Метод теста |
|---|---|
| `steamAppId` передан: `store='Steam'`, `storeUrl` собран из appid | `testToApiIncludesSteamStoreAndUrlWhenAppIdGiven` |
| `steamAppId` не передан: `store`/`storeUrl` — `null` | `testToApiLeavesStoreAndUrlNullWithoutAppId` |

## GamePriceCleanupServiceTest.php

Удаление промежуточных снимков цены — внутри непрерывной серии одинаковой
цены по игре сохраняется только первая и последняя запись (цена "до" и
"после" изменения).

| Кейс | Метод теста |
|---|---|
| Серия из 4 одинаковых цен: удаляются 2 средние записи, крайние остаются | `testCleanupRemovesMiddleRecordsOfUnchangedPriceRunKeepingFirstAndLast` |
| Цена изменилась на соседний день (серии по 1 записи): ничего не удаляется | `testCleanupKeepsBoundaryRecordsOnPriceChange` |
| Две серии подряд (до и после изменения цены), каждая по 3 записи: удаляется по одной средней записи в каждой | `testCleanupKeepsBoundariesOfEachRunAroundPriceChangeInLongerHistory` |
| Несколько игр в выборке: серии обрабатываются независимо по каждой игре | `testCleanupProcessesEachGameIndependently` |
| Серия из записей "недоступно в РФ" (`priceKopecks = null`) группируется как обычная цена | `testCleanupTreatsUnavailableInRussiaAsRegularPriceValueForGrouping` |
| Пустая выборка за период: `deletedCount`/`processedGamesCount` — 0, `remove()` не вызывается | `testCleanupReturnsZeroResultWhenNothingInRange` |
| Граница `from` вычисляется как `$now - $weeks` недель и передаётся в репозиторий | `testCleanupPassesComputedFromDateBasedOnWeeksAndNow` |
