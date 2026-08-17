# Тест-кейсы: тэйки

Список проверяемых сценариев для `src/Service/Take/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## CreateTakeServiceTest.php

| Кейс | Метод теста |
|---|---|
| Игра найдена: тэйк сохраняется с автором/игрой/текстом | `testCreateSavesTakeWhenGameExists` |
| Игра не найдена: бросается `GameNotFoundException`, `persist`/`flush` не вызываются | `testCreateThrowsWhenGameNotFound` |

## CreateTakeCommentServiceTest.php

| Кейс | Метод теста |
|---|---|
| Комментарий сохраняется с тэйком/автором/текстом | `testCreateSavesCommentWithTakeAuthorAndText` |

## TakeReactionServiceTest.php

| Кейс | Метод теста |
|---|---|
| Голоса ещё нет: создаётся новая реакция | `testSetReactionCreatesNewReactionWhenNoneExists` |
| Голос уже есть: тип меняется у существующей реакции, новая не создаётся | `testSetReactionChangesTypeOfExistingReaction` |
| Голос есть: снимается (`remove` + `flush`) | `testRemoveReactionRemovesExistingReaction` |
| Голоса нет: снятие — no-op, `remove`/`flush` не вызываются | `testRemoveReactionIsNoOpWhenReactionDoesNotExist` |

## TakeMapperTest.php

| Кейс | Метод теста |
|---|---|
| Маппинг полей тэйка в список, включая счётчики лайков/дизлайков/комментариев (`myReaction: null` по умолчанию) | `testToListItemMapsFieldsIncludingCounts` |
| `myReaction` передаётся и попадает в ответ | `testToListItemIncludesMyReactionWhenPassed` |
| Детали тэйка включают смаппленные комментарии | `testToDetailIncludesMappedComments` |
| Маппинг полей комментария | `testToCommentMapsFields` |
