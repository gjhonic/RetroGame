# Тест-кейсы: обнаружение кронов

Список проверяемых сценариев для `src/Cron/`. При добавлении нового кейса —
дописывайте сюда строку с методом, где он проверяется.

## CronDiscoveryServiceTest.php

| Кейс | Метод теста |
|---|---|
| Из списка команд возвращаются имена только тех, что помечены `#[AsTrackedCron]` | `testDiscoverTrackedCommandNamesReturnsOnlyCommandsWithAttribute` |
| Пустой список команд — пустой результат | `testDiscoverTrackedCommandNamesReturnsEmptyArrayForNoCommands` |
