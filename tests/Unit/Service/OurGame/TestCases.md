# Тест-кейсы: свои игры (OurGame)

Список проверяемых сценариев для `src/Service/OurGame/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## OurGameMapperTest.php

| Кейс | Метод теста |
|---|---|
| `toAdminListItem` — название/slug/статус/обложка/версия/дата выхода/жанры | `testToAdminListItemMapsFieldsAndGenreNames` |
| `toAdminListItem` без обложки — `coverImageUrl: null`, статус по умолчанию `draft` | `testToAdminListItemReturnsNullCoverImageUrlWhenNotSet` |
| `toDetail` — описание/баннер/скриншоты (с `/`)/трейлер/`genreIds`/ссылки на скачивание | `testToDetailIncludesScreenshotsGenreIdsAndDownloadLinks` |
| `toDownloadLinkItem` — платформа/ссылка/иконка | `testToDownloadLinkItemMapsFields` |

## OurGameSlugGeneratorTest.php

| Кейс | Метод теста |
|---|---|
| Свободный slug — просто транслитерация названия | `testGenerateReturnsSlugifiedNameWhenFree` |
| Коллизия — добавляется числовой суффикс (`-2`) | `testGenerateAppendsIncrementingSuffixOnCollision` |
| При редактировании собственный текущий slug игры не считается занятым | `testGenerateIgnoresCollisionWithTheGameBeingEdited` |

## OurGameCrudServiceTest.php

| Кейс | Метод теста |
|---|---|
| `create()` — persist+flush, поля из запроса, сгенерированный slug | `testCreatePersistsGameWithGeneratedSlug` |
| `create()` — жанры назначаются по `genreIds` из запроса | `testCreateAssignsGenresByRequestedIds` |
| `update()` — slug пересчитывается только при смене названия | `testUpdateRegeneratesSlugOnlyWhenNameChanges` |
| `update()` — slug не трогается, если название не изменилось | `testUpdateKeepsSlugWhenNameUnchanged` |
| `update()` — жанры, отсутствующие в новом списке `genreIds`, убираются | `testUpdateRemovesGenresNoLongerRequested` |
| `delete()` — remove+flush сущности и удаление всех её картинок с диска | `testDeleteRemovesGameAndItsImages` |

## OurGameDownloadLinkCrudServiceTest.php

| Кейс | Метод теста |
|---|---|
| `create()` — ссылка создаётся и привязывается к игре | `testCreatePersistsLinkForGame` |
| `update()` — платформа и URL обновляются | `testUpdateChangesPlatformAndUrl` |
| `delete()` — ссылка удаляется вместе с её иконкой на диске | `testDeleteRemovesLinkAndItsImage` |

## OurGameImageStorageTest.php

Работает с реальной файловой системой (временный каталог вместо `public/`) —
`UploadedFile::move()` выполняет настоящую операцию с файлом, мокать её не
имеет смысла (см. `AvatarUploadServiceTest`, тот же паттерн).

| Кейс | Метод теста |
|---|---|
| `store()` — файл кладётся в `uploads/our_games/{id}/{subdir}/<random>.<ext>` | `testStoreMovesFileUnderOurGameSubdir` |
| `store()` с `$previousRelativePath` — старый файл удаляется | `testStoreRemovesPreviousFileWhenGiven` |
| `remove()` — удаляет существующий файл, безопасно игнорирует отсутствующий/`null` | `testRemoveDeletesExistingFileAndIgnoresMissingOne` |
| `removeAllForGame()` — удаляет всю директорию файлов игры | `testRemoveAllForGameDeletesWholeGameDirectory` |
