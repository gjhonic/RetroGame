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
