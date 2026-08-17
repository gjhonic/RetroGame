# Тест-кейсы: посты об играх (OurGamePost)

Список проверяемых сценариев для `src/Service/OurGamePost/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## OurGamePostMapperTest.php

| Кейс | Метод теста |
|---|---|
| `toAdminListItem` — игра/автор/тип/статус/дата/картинка/краткое описание | `testToAdminListItemMapsFields` |
| `toAdminListItem` без картинки — `imageUrl: null`, статус по умолчанию `draft` | `testToAdminListItemReturnsNullImageUrlWhenNotSet` |
| `toDetail` — полное описание + `createdAt`/`updatedAt` | `testToDetailIncludesFullDescriptionAndTimestamps` |

## OurGamePostCrudServiceTest.php

| Кейс | Метод теста |
|---|---|
| `create()` сохраняет пост с игрой/автором/полями из запроса | `testCreatePersistsPostWithAuthorAndGame` |
| `create()` с несуществующим `gameId` → `OurGameNotFoundException`, `persist()` не вызывается | `testCreateThrowsExceptionWhenGameNotFound` |
| `update()` переназначает игру и поля | `testUpdateReassignsGameAndFields` |
| `update()` с несуществующим `gameId` → `OurGameNotFoundException` | `testUpdateThrowsExceptionWhenGameNotFound` |
| `delete()` удаляет пост и его картинку через `OurGamePostImageStorage::removeAllForPost()` | `testDeleteRemovesPostAndItsImage` |

## OurGamePostImageStorageTest.php

Работает с реальной файловой системой (временный каталог) — см.
`OurGameImageStorageTest` (тот же паттерн).

| Кейс | Метод теста |
|---|---|
| `store()` кладёт файл в `uploads/our_game_posts/{postId}/image/` | `testStoreMovesFileUnderPostSubdir` |
| `store()` с `previousRelativePath` удаляет старый файл | `testStoreRemovesPreviousFileWhenGiven` |
| `remove()` удаляет существующий файл, игнорирует отсутствующий/`null` | `testRemoveDeletesExistingFileAndIgnoresMissingOne` |
| `removeAllForPost()` удаляет всю директорию поста | `testRemoveAllForPostDeletesWholePostDirectory` |
| `storeContentImage()` кладёт файл в `uploads/our_game_posts/{postId}/content/`, старые файлы не трогает (в тексте может быть много картинок) | `testStoreContentImageMovesFileUnderContentSubdirAndKeepsPreviousOnes` |
