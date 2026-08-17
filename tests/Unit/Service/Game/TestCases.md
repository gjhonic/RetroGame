# Тест-кейсы: реакции/избранное/статус прохождения игр

Список проверяемых сценариев для `src/Service/Game/`. При добавлении нового
кейса — дописывайте сюда строку с методом, где он проверяется.

## GameReactionServiceTest.php

| Кейс | Метод теста |
|---|---|
| Голоса ещё нет: создаётся новая реакция | `testSetReactionCreatesNewReactionWhenNoneExists` |
| Голос уже есть: тип меняется у существующей реакции, новая не создаётся | `testSetReactionChangesTypeOfExistingReaction` |
| Голос есть: снимается (`remove` + `flush`) | `testRemoveReactionRemovesExistingReaction` |
| Голоса нет: снятие — no-op, `remove`/`flush` не вызываются | `testRemoveReactionIsNoOpWhenReactionDoesNotExist` |

## GameFavoriteServiceTest.php

| Кейс | Метод теста |
|---|---|
| Игры ещё нет в избранном: создаётся новая запись | `testAddFavoriteCreatesNewFavoriteWhenNoneExists` |
| Игра уже в избранном: повторное добавление — no-op, `persist`/`flush` не вызываются | `testAddFavoriteIsNoOpWhenAlreadyFavorited` |
| Игра в избранном: убирается (`remove` + `flush`) | `testRemoveFavoriteRemovesExistingFavorite` |
| Игры нет в избранном: снятие — no-op, `remove`/`flush` не вызываются | `testRemoveFavoriteIsNoOpWhenFavoriteDoesNotExist` |

## GameStatusServiceTest.php

| Кейс | Метод теста |
|---|---|
| Статуса ещё нет: создаётся новый | `testSetStatusCreatesNewStatusWhenNoneExists` |
| Статус уже есть: меняется у существующей записи, новая не создаётся | `testSetStatusChangesExistingStatus` |
| Статус есть: снимается (`remove` + `flush`) | `testRemoveStatusRemovesExistingStatus` |
| Статуса нет: снятие — no-op, `remove`/`flush` не вызываются | `testRemoveStatusIsNoOpWhenStatusDoesNotExist` |

## GameMapperTest.php

| Кейс | Метод теста |
|---|---|
| У игры нет скрытого жанра — `isHiddenFromPublic` возвращает false | `testIsHiddenFromPublicReturnsFalseWhenGameHasNoHiddenGenre` |
| У игры есть жанр "Сексуальный контент" — `isHiddenFromPublic` возвращает true | `testIsHiddenFromPublicReturnsTrueWhenGameHasHiddenGenre` |
