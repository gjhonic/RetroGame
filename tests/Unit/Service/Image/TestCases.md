# Тест-кейсы: скачивание изображений игр

Список проверяемых сценариев для `src/Service/Image/`. При добавлении
нового кейса — дописывайте сюда строку с методом, где он проверяется.

## GameImageDownloaderTest.php

| Кейс | Метод теста |
|---|---|
| Успешное скачивание: файл сохраняется через `Filesystem::dumpFile`, возвращается относительный путь | `testDownloadCoverSavesFileAndReturnsRelativePath` |
| Расширение файла берётся из URL (например, `.png`) | `testDownloadCoverGuessesExtensionFromUrl` |
| URL без расширения — по умолчанию используется `.jpg` | `testDownloadCoverDefaultsToJpgWhenUrlHasNoExtension` |
| Сетевая ошибка при скачивании: возвращается `null`, файл не сохраняется | `testDownloadCoverReturnsNullOnTransportException` |
