# Тест-кейсы: пользователи

Список проверяемых сценариев для `src/Service/User/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## CreateAdminUserServiceTest.php

| Кейс | Метод теста |
|---|---|
| Email не найден — создаётся новый пользователь с ролью admin | `testCreatesNewAdminWhenEmailNotFound` |
| Email уже существует — пользователю меняют роль/пароль, новый не создаётся | `testPromotesExistingUserToAdminWithoutCreatingNew` |

## UserRegistrationServiceTest.php

| Кейс | Метод теста |
|---|---|
| Email свободен: создаётся User с захешированным паролем и ником | `testRegisterCreatesUserWithHashedPassword` |
| Email уже занят: бросается `EmailAlreadyRegisteredException`, пользователь не сохраняется | `testRegisterThrowsWhenEmailAlreadyRegistered` |

## UserMapperTest.php

| Кейс | Метод теста |
|---|---|
| Маппинг публичных полей, пароль в ответ не попадает | `testToPublicMapsFieldsWithoutPassword` |
