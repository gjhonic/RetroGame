# Тест-кейсы: JSON API личного кабинета

Список проверяемых сценариев для `src/Controller/Api/Cabinet/`. При
добавлении нового кейса — дописывайте сюда строку с методом, где он
проверяется.

## ProfileApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Данные текущего пользователя возвращаются через `UserMapper` | `testShowReturnsCurrentUser` |
| Валидный запрос смены пароля → `200`, сервис вызван | `testChangePasswordReturnsSuccessOnValidRequest` |
| Новый пароль короче 8 символов → `422` с ошибкой по `newPassword`, сервис не вызывается | `testChangePasswordReturnsValidationErrorsForShortPassword` |
| Неверный текущий пароль (`InvalidCurrentPasswordException` из сервиса) → `422` с ошибкой по `currentPassword` | `testChangePasswordReturnsValidationErrorWhenCurrentPasswordIsInvalid` |
| Валидный JPG 100×100 → `200`, сервис вызван | `testUploadAvatarReturnsUpdatedUserOnValidFile` |
| Изображение больше 400×400 → `422` с ошибкой по `file`, сервис не вызывается | `testUploadAvatarReturnsValidationErrorWhenImageTooLarge` |

## TakeApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Валидный запрос на создание тэйка → `201`, сервис вызван | `testCreateReturnsCreatedTakeOnValidRequest` |
| Текст длиннее 1000 символов → `422` с ошибкой по `text`, сервис не вызывается | `testCreateReturnsValidationErrorForTooLongText` |
| Текст содержит HTML-теги → `422` с ошибкой по `text`, сервис не вызывается | `testCreateReturnsValidationErrorForHtmlInText` |
| Игра не найдена (`GameNotFoundException` из сервиса) → `422` с ошибкой по `gameId` | `testCreateReturnsValidationErrorWhenGameNotFound` |
| Валидный комментарий → `201`, сервис вызван | `testCreateCommentReturnsCreatedCommentOnValidRequest` |
| Комментарий к несуществующему тэйку → `NotFoundHttpException`, сервис не вызывается | `testCreateCommentThrowsNotFoundExceptionForUnknownTake` |
| Установка реакции (`like`) → `200` с обновлёнными счётчиками | `testSetReactionReturnsUpdatedCounts` |
| Некорректный тип реакции → `422`, сервис не вызывается | `testSetReactionReturnsValidationErrorForInvalidType` |
| Реакция на несуществующий тэйк → `NotFoundHttpException` | `testSetReactionThrowsNotFoundExceptionForUnknownTake` |
| Снятие реакции → `200` с обновлёнными счётчиками и `type: null` | `testRemoveReactionReturnsUpdatedCounts` |
| Снятие реакции с несуществующего тэйка → `NotFoundHttpException` | `testRemoveReactionThrowsNotFoundExceptionForUnknownTake` |

Своих игр (`OurGame`) здесь больше нет — отдельной кабинетной страницы для
них нет, ссылка "Наши игры" в сайдбаре кабинета ведёт на публичную витрину
`/our-games` (данные не зависят от пользователя, по аналогии с тем, как
каталог обычных игр не имеет отдельного кабинетного API для чтения —
см. `Api/Public/OurGameApiControllerTest`). Обычные игры (`Game`) — иначе:
сам каталог/детали читаются через публичный `Api/Public/GameApiController`,
а вот персональные лайк/дизлайк/избранное/статус прохождения — через
`Api/Cabinet/GameApiController` ниже, т.к. они привязаны к текущему
пользователю.

## GameApiControllerTest.php

| Кейс | Метод теста |
|---|---|
| Установка реакции (`like`) → `200` с обновлёнными счётчиками | `testSetReactionReturnsUpdatedCounts` |
| Некорректный тип реакции → `422`, сервис не вызывается | `testSetReactionReturnsValidationErrorForInvalidType` |
| Реакция на несуществующую игру → `NotFoundHttpException` | `testSetReactionThrowsNotFoundExceptionForUnknownSlug` |
| Снятие реакции → `200` с обновлёнными счётчиками и `type: null` | `testRemoveReactionReturnsUpdatedCounts` |
| Снятие реакции с несуществующей игры → `NotFoundHttpException` | `testRemoveReactionThrowsNotFoundExceptionForUnknownSlug` |
| Добавление в избранное → `200` с `favorite: true` | `testAddFavoriteReturnsTrue` |
| Добавление в избранное несуществующей игры → `NotFoundHttpException` | `testAddFavoriteThrowsNotFoundExceptionForUnknownSlug` |
| Удаление из избранного → `200` с `favorite: false` | `testRemoveFavoriteReturnsFalse` |
| Удаление из избранного несуществующей игры → `NotFoundHttpException` | `testRemoveFavoriteThrowsNotFoundExceptionForUnknownSlug` |
| Установка статуса прохождения (`completed`) → `200` с сохранённым статусом | `testSetStatusReturnsSavedStatus` |
| Некорректный статус → `422`, сервис не вызывается | `testSetStatusReturnsValidationErrorForInvalidStatus` |
| Установка статуса для несуществующей игры → `NotFoundHttpException` | `testSetStatusThrowsNotFoundExceptionForUnknownSlug` |
| Снятие статуса → `200` с `status: null` | `testRemoveStatusReturnsNull` |
| Снятие статуса с несуществующей игры → `NotFoundHttpException` | `testRemoveStatusThrowsNotFoundExceptionForUnknownSlug` |
