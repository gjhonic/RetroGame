# Тест-кейсы: JSON API каталога игр

Список проверяемых сценариев для `src/Controller/Api/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GameApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список игр: форма ответа (`items/total/page/totalPages`), нормализация `coverImageUrl` (путь есть → с ведущим `/`, пути нет → `null`) | `testListReturnsNormalizedItemsAndPagination` |
| Параметр `page` не передан: используется первая страница, `findBy` вызывается с `offset=0` | `testListDefaultsToFirstPageWhenPageIsMissing` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Детали игры: разработчики/издатели/жанры/платформы возвращаются как массивы имён (`NamedEntityInterface::getName()`) | `testShowReturnsFullDetailWithRelatedEntityNames` |
| `screenshotUrls` не заданы (`null` в сущности) → в ответе пустой массив, а не `null` | `testShowNormalizesMissingScreenshotUrlsToEmptyArray` |
| Несуществующий slug → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownSlug` |
