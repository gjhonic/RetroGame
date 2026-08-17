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
| Ник уже занят другим пользователем: бросается `NicknameAlreadyTakenException`, пользователь не сохраняется | `testRegisterThrowsWhenNicknameAlreadyTaken` |

## ModeratorCreationServiceTest.php

| Кейс | Метод теста |
|---|---|
| Email свободен: создаётся User с ролью `ROLE_MODERATOR`, захешированным паролем и ником | `testCreateCreatesUserWithModeratorRoleAndHashedPassword` |
| Email уже занят: бросается `EmailAlreadyRegisteredException`, пользователь не сохраняется | `testCreateThrowsWhenEmailAlreadyRegistered` |

## UserMapperTest.php

| Кейс | Метод теста |
|---|---|
| Маппинг публичных полей, пароль в ответ не попадает | `testToPublicMapsFieldsWithoutPassword` |
| `toPublic` включает `isProfilePublic` | `testToPublicIncludesProfileVisibility` |
| `toPublicProfile` — nickname/avatarUrl/createdAt/followersCount/followingCount/isOwnProfile/isFollowing, без email и id (для чужого просмотра `/profile/{nickname}`) | `testToPublicProfileMapsNicknameAvatarAndCreatedAtWithoutEmail` |
| `toProfileSummary` — только nickname/avatarUrl (для списка подписчиков) | `testToProfileSummaryMapsNicknameAndAvatarOnly` |
| `toAdminListItem` — email/nickname/role/lastLoginAt, пароль в ответ не попадает | `testToAdminListItemMapsFieldsWithoutPassword` |
| `toDetail` расширяет `toAdminListItem` полями avatarUrl/updatedAt | `testToDetailExtendsAdminListItemWithAvatarAndUpdatedAt` |

## UpdateProfilePrivacyServiceTest.php

| Кейс | Метод теста |
|---|---|
| Открытие профиля: `isProfilePublic` устанавливается, `flush` вызывается | `testUpdateSetsProfileVisibilityAndFlushes` |
| Закрытие уже открытого профиля | `testUpdateCanCloseProfileAgain` |

## ProfileVisibilityServiceTest.php

Резолвит владельца `/profile/{nickname}` — не различает "не найден" и
"закрыт" (оба случая → `ProfileNotFoundException`), чтобы не палить
существование закрытых профилей.

| Кейс | Метод теста |
|---|---|
| Профиль открыт: гость видит его (`viewer: null`) | `testResolveReturnsUserWhenProfileIsPublic` |
| Профиль закрыт, но смотрит сам владелец — виден | `testResolveReturnsUserForOwnerEvenWhenProfileIsPrivate` |
| Профиль закрыт, смотрит другой авторизованный пользователь → `ProfileNotFoundException` | `testResolveThrowsWhenProfileIsPrivateAndViewerIsSomeoneElse` |
| Профиль закрыт, смотрит гость → `ProfileNotFoundException` | `testResolveThrowsWhenProfileIsPrivateAndViewerIsAnonymous` |
| Ник не найден → `ProfileNotFoundException` | `testResolveThrowsWhenNicknameNotFound` |

## FollowServiceTest.php

Подписка/отписка — идемпотентные операции, как и `GameFavoriteService`.
Самоподписка запрещена (`CannotFollowSelfException`), сравнение по `id`
(с защитой от ложного совпадения двух ещё не сохранённых сущностей
с `id === null`, см. также `ProfileVisibilityServiceTest`).

| Кейс | Метод теста |
|---|---|
| Подписки ещё нет — создаётся новая, `flush` вызывается | `testFollowCreatesNewFollowWhenNoneExists` |
| Подписка уже есть — no-op, `persist`/`flush` не вызываются | `testFollowIsNoOpWhenAlreadyFollowing` |
| Подписка на самого себя → `CannotFollowSelfException`, `persist` не вызывается | `testFollowThrowsWhenFollowingSelf` |
| Подписка есть — снимается (`remove` + `flush`) | `testUnfollowRemovesExistingFollow` |
| Подписки нет — снятие no-op, `remove`/`flush` не вызываются | `testUnfollowIsNoOpWhenFollowDoesNotExist` |

## UpdateNicknameServiceTest.php

Ник теперь уникален в БД (см. миграцию `Version20260813010000`) — сервис
проверяет занятость через `UserRepository::findOneByNickname` до сохранения.

| Кейс | Метод теста |
|---|---|
| Ник свободен — сохраняется, `flush` вызывается | `testUpdateSetsNicknameWhenFree` |
| Пробелы по краям обрезаются | `testUpdateTrimsWhitespace` |
| Пользователь сохраняет свой же текущий ник — не считается занятым | `testUpdateAllowsKeepingOwnCurrentNickname` |
| Ник занят другим пользователем → `NicknameAlreadyTakenException`, `flush` не вызывается | `testUpdateThrowsWhenNicknameTakenByAnotherUser` |

## ChangePasswordServiceTest.php

| Кейс | Метод теста |
|---|---|
| Текущий пароль верный: новый пароль хешируется и сохраняется | `testChangePasswordHashesNewPasswordWhenCurrentIsValid` |
| Текущий пароль неверный: бросается `InvalidCurrentPasswordException`, `flush` не вызывается | `testChangePasswordThrowsWhenCurrentPasswordIsInvalid` |

## AvatarUploadServiceTest.php

| Кейс | Метод теста |
|---|---|
| Файл перемещается в `uploads/avatars/{id}.{ext}`, `avatarUrl` обновляется | `testUploadMovesFileAndSetsAvatarUrl` |
| Старый аватар с другим расширением удаляется при загрузке нового | `testUploadRemovesPreviousAvatarWithDifferentExtension` |
