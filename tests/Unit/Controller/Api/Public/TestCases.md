# Тест-кейсы: JSON API каталога игр

Список проверяемых сценариев для `src/Controller/Api/Public/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GameApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список игр: форма ответа (`items/total/page/totalPages`), нормализация `coverImageUrl` (путь есть → с ведущим `/`, пути нет → `null`) | `testListReturnsNormalizedItemsAndPagination` |
| Параметр `page` не передан: используется первая страница, `findBy` вызывается с `offset=0` | `testListDefaultsToFirstPageWhenPageIsMissing` |
| Запрошенная страница выходит за `totalPages`: значение клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Фильтры (`name`/`genre`/`releaseYearFrom`) и сортировка передаются в репозиторий обрезанными от пробелов, неизвестный ключ фильтра игнорируется | `testListPassesFiltersAndSortingToRepository` |
| Неизвестное поле/направление сортировки → используется сортировка по умолчанию (`popularity`, `DESC`) | `testListFallsBackToDefaultSortForUnknownSortField` |
| Справочник фильтров: жанры/платформы (id+name) и диапазон годов выхода | `testFiltersReturnsGenresPlatformsAndReleaseYearRange` |
| Жанр "Сексуальный контент" не попадает в справочник фильтров (`GameMapper::HIDDEN_PUBLIC_GENRE_NAMES`) | `testFiltersExcludesHiddenPublicGenre` |
| Нет игр с известной датой выхода → диапазон годов `null` | `testFiltersReturnsNullReleaseYearRangeWhenNoGamesHaveReleaseDate` |
| Детали игры: разработчики/издатели/жанры/платформы возвращаются как массивы имён (`NamedEntityInterface::getName()`), счётчики лайков/дизлайков, для гостя `myReaction/myFavorite/myStatus` пустые | `testShowReturnsFullDetailWithRelatedEntityNames` |
| `screenshotUrls` не заданы (`null` в сущности) → в ответе пустой массив, а не `null` | `testShowNormalizesMissingScreenshotUrlsToEmptyArray` |
| Авторизованный пользователь: `myReaction`/`myFavorite`/`myStatus` подмешиваются из соответствующих репозиториев | `testShowIncludesCurrentUserReactionFavoriteAndStatus` |
| Несуществующий slug → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownSlug` |
| Игра с жанром "Сексуальный контент" → `NotFoundHttpException` (скрыта из публичной части) | `testShowThrowsNotFoundExceptionForGameWithHiddenGenre` |

## RegistrationApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Валидный запрос → `201` с данными созданного пользователя | `testRegisterReturnsCreatedUserOnValidRequest` |
| Невалидный email/пароль/ник → `422` с ошибками по каждому полю | `testRegisterReturnsValidationErrorsForInvalidPayload` |
| Email уже занят (`EmailAlreadyRegisteredException` из сервиса) → `ConflictHttpException` (409 через `ApiExceptionListener`) | `testRegisterThrowsConflictWhenEmailAlreadyRegistered` |

## TakeApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список тэйков: счётчики лайков/дизлайков/комментариев подмешиваются в каждый пункт | `testListReturnsItemsWithReactionAndCommentCounts` |
| Фильтр `filters[game]` передаётся в репозиторий обрезанным от пробелов | `testListPassesGameFilterToRepository` |
| Авторизованный пользователь: `myReaction` подмешивается из `findTypesForTakesAndUser` | `testListIncludesMyReactionForCurrentUser` |
| Детали тэйка включают первую страницу комментариев и счётчики реакций (`myReaction: null` для гостя) | `testShowReturnsDetailWithComments` |
| Авторизованный пользователь: `myReaction` тэйка берётся из `findOneByTakeAndUser` | `testShowIncludesMyReactionForCurrentUser` |
| Несуществующий ID тэйка → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnknownId` |
| Постраничные комментарии тэйка | `testCommentsReturnsPaginatedList` |
| Комментарии несуществующего тэйка → `NotFoundHttpException` | `testCommentsThrowsNotFoundExceptionForUnknownTake` |

## OurGameApiControllerTest.php

Используется `Public/OurGameList.vue`/`Public/OurGameDetail.vue` — отдельной
кабинетной страницы/API для своих игр нет, ссылка "Наши игры" в сайдбаре
кабинета ведёт на публичную витрину (аналогично `GameApiController`).

| Кейс | Метод теста |
|---|---|
| Список отдаёт опубликованные игры из `OurGameRepository::findPublishedForPublic()` | `testListReturnsPublishedGamesFromRepository` |
| Детали игры по slug опубликованной игры | `testShowReturnsGameDetailForPublishedSlug` |
| Slug не найден или игра не опубликована → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionWhenGameNotFoundOrNotPublished` |

## OurGamePostApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Список: только опубликованные посты, страница по умолчанию (`postedAt DESC`, `perPage=20`) | `testListReturnsPublishedPostsWithDefaultPagination` |
| Фильтр `filters[game]` передаётся в репозиторий | `testListPassesGameFilterToRepository` |
| Детали опубликованного поста по `id` | `testShowReturnsPublishedPostDetail` |
| Пост не найден или не опубликован → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionForUnpublishedOrMissingPost` |

## ScoreDieAgainApiControllerTest.php

Таблица лидеров внешней игры DIE//AGAIN (`/our-games/die-again`) — RetroGame
используется только как хранилище результатов, своей игровой логики тут нет.

| Кейс | Метод теста |
|---|---|
| Таблица лидеров: постраничная навигация, сортировка по умолчанию (`kills`, `DESC`) | `testListReturnsItemsWithPaginationAndDefaultSort` |
| Запрошенная страница выходит за `totalPages` → клампится до последней доступной | `testListClampsRequestedPageToTotalPages` |
| Параметры `sortBy`/`sortDir` передаются в репозиторий | `testListPassesCustomSortToRepository` |
| Валидный запрос → `201` с сохранённым результатом | `testCreateReturnsCreatedScoreOnValidRequest` |
| Пустой ник → `422` | `testCreateReturnsValidationErrorForBlankNickname` |
| HTML-теги в нике → `422` | `testCreateReturnsValidationErrorForHtmlInNickname` |
| Отрицательное количество убийств → `422` | `testCreateReturnsValidationErrorForNegativeKills` |
| Невалидное тело запроса (не JSON) → `400` | `testCreateReturnsBadRequestForInvalidJsonBody` |

## ProfileApiControllerTest.php

Публичный профиль `/profile/{nickname}` — виден только если владелец открыл
его в настройках приватности (или смотрит сам владелец), иначе
`ProfileVisibilityService` бросает `ProfileNotFoundException`, здесь просто
проверяется её перехват и превращение в `NotFoundHttpException`; сама логика
видимости — в `tests/Unit/Service/User/ProfileVisibilityServiceTest.php`.

| Кейс | Метод теста |
|---|---|
| Профиль виден → публичные данные (без email), `followersCount`, `followingCount`, `isOwnProfile: false`, `isFollowing: null` для гостя | `testShowReturnsPublicProfileForVisibleUser` |
| Профиль не виден → `NotFoundHttpException` | `testShowThrowsNotFoundExceptionWhenProfileIsNotVisible` |
| Смотрит сам владелец → `isOwnProfile: true`, `isFollowing: null`, в `UserFollowRepository::findOneByFollowerAndFollowed` не ходим | `testShowMarksOwnProfileAndDoesNotExposeFollowState` |
| Смотрит другой авторизованный пользователь → `isFollowing` берётся из `UserFollowRepository` | `testShowIncludesIsFollowingForAuthorizedViewer` |
| Список подписчиков видимого профиля (только nickname/avatarUrl, без email) | `testFollowersReturnsPageOfFollowerSummaries` |
| Список подписчиков невидимого профиля → `NotFoundHttpException`, репозиторий не вызывается | `testFollowersThrowsNotFoundExceptionWhenProfileIsNotVisible` |
| Список подписок (на кого подписан) видимого профиля (только nickname/avatarUrl, без email) | `testFollowingReturnsPageOfFollowedSummaries` |
| Список подписок невидимого профиля → `NotFoundHttpException`, репозиторий не вызывается | `testFollowingThrowsNotFoundExceptionWhenProfileIsNotVisible` |
| Любимые игры видимого профиля | `testFavoritesReturnsPageForVisibleUser` |
| Любимые игры невидимого профиля → `NotFoundHttpException`, репозиторий не вызывается | `testFavoritesThrowsNotFoundExceptionWhenProfileIsNotVisible` |
| Игры со статусом `in_progress` видимого профиля | `testGamesByStatusReturnsPageForValidStatus` |
| Без `status` → `400`, репозиторий не вызывается | `testGamesByStatusReturnsBadRequestForMissingStatus` |
| Игры невидимого профиля → `NotFoundHttpException` | `testGamesByStatusThrowsNotFoundExceptionWhenProfileIsNotVisible` |
