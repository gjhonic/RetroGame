# Тест-кейсы: слушатели событий

Список проверяемых сценариев для `src/EventListener/`. При добавлении нового
кейса — дописывайте сюда строку с методом, где он проверяется.

## CronTrackingListenerTest.php

Перехват вывода команды в лог-файл (`TeeStreamFilter`) в этих тестах не
проверяется — вывод в тестах через `BufferedOutput` (не `StreamOutput`),
механизм проверен вручную реальным прогоном `app:games:import` (см. PR).

| Кейс | Метод теста |
|---|---|
| Команда с `#[AsTrackedCron]`: создаётся и персистится `CronRun`, создаётся директория лога | `testOnCommandCreatesAndPersistsRunForTrackedCommand` |
| Команда без атрибута — ничего не создаётся и не персистится | `testOnCommandDoesNothingForUntrackedCommand` |
| `onTerminate` с `exitCode=0` → статус `Success`, текущий запуск сбрасывается | `testOnTerminateMarksRunAsSuccessOnZeroExitCode` |
| `onTerminate` с `exitCode!=0` → статус `Failed`, exitCode сохранён | `testOnTerminateMarksRunAsFailedOnNonZeroExitCode` |
| `onError` перед `onTerminate` — сообщение исключения попадает в `errorMessage` | `testOnTerminateUsesErrorMessageCapturedByOnError` |
| `onTerminate` без предшествующего `onCommand` — `flush()` не вызывается | `testOnTerminateDoesNothingWithoutPriorCommand` |
| Зависшая `running`-запись, найденная `findStaleRunning()`, помечается `Failed` при старте следующего трекаемого крона | `testOnCommandHealsStaleRunningEntries` |
