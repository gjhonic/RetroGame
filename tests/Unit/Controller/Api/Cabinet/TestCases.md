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
