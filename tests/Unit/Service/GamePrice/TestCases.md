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
