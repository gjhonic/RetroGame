# Тест-кейсы: пользователи

Список проверяемых сценариев для `src/Service/User/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## CreateAdminUserServiceTest.php

| Кейс | Метод теста |
|---|---|
| Email не найден — создаётся новый пользователь с ролью admin | `testCreatesNewAdminWhenEmailNotFound` |
| Email уже существует — пользователю меняют роль/пароль, новый не создаётся | `testPromotesExistingUserToAdminWithoutCreatingNew` |
