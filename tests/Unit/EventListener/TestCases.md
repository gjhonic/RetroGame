# Тест-кейсы: слушатели событий

Список проверяемых сценариев для `src/EventListener/`. При добавлении нового
кейса — дописывайте сюда строку с методом, где он проверяется.

## LoginSuccessListenerTest.php

`LoginSuccessEvent` общий для `form_login` (веб-панель) и `json_login`/JWT
(мобильный API) — см. `security.yaml`.

| Кейс | Метод теста |
|---|---|
| Успешный вход `App\Entity\User` → `lastLoginAt` обновлён, запись в журнале действий (`user.login`, `Success`) | `testUpdatesLastLoginAndLogsSuccess` |
| Пользователь не `App\Entity\User` (посторонний `UserInterface`) → ничего не делает | `testDoesNothingForNonAppUser` |

## LoginFailureListenerTest.php

`LoginFailureEvent` тоже общий для `form_login`/`json_login` — на этот момент
пользователь не аутентифицирован, поэтому запись без владельца, email
пытаются извлечь из тела запроса (форма или JSON — оба формата используются
разными firewalls, см. `security.yaml`).

| Кейс | Метод теста |
|---|---|
| Email из form-encoded тела (`form_login`) | `testLogsFailureWithEmailFromFormRequest` |
| Email из JSON-тела (`json_login`) | `testLogsFailureWithEmailFromJsonRequest` |
| Email не удалось извлечь (невалидный JSON) → `null` в деталях, запись всё равно создаётся | `testLogsNullEmailWhenItCannotBeExtracted` |

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
