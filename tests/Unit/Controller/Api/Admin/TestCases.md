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
