# Архитектура: карта модулей

Общий стиль слоёв см. в [modules.md](modules.md) (Controller/Entity-конвенции) и [frontend.md](frontend.md) (Vue+API). Здесь — что за что отвечает предметно, чтобы быстро понять, куда добавлять новый код.

## Зоны доступа

Три зоны, сквозные для Twig-контроллеров, Vue-компонентов и API (см. [frontend.md](frontend.md)):

- **Cabinet** — единый раздел сайта для всех, включая неавторизованных (каталог игр публичный, тэйки может добавлять только вошедший).
- **Admin** — модерация/администрирование, `ROLE_MODERATOR`+.
- **Api/Public** — API без авторизации, используется со страниц Cabinet для контента, доступного всем.

## Модули (`src/Service/<Модуль>`, `src/Entity/<Модуль>*`)

- **Steam** (`Service/Steam`) — интеграция со Steam Web API: `SteamClient` (HTTP), `GameImportService`/`PriceImportService` (постраничный импорт каталога и цен по курсору `SteamImportCursor`/`SteamPriceImportCursor`), `SteamReleaseDateParser`. Источник данных для модуля **Game**.
- **SteamGame** (`Service/SteamGame`, `Entity/SteamGame`) — сырые данные, полученные от Steam до модерации/публикации как `Game`. `SteamGameMapper` + `Admin/SteamGameApiController` — модерация в админке.
- **Game** (`Service/Game`, `Entity/Game`) — опубликованные игры каталога: избранное (`GameFavoriteService`), реакции (`GameReactionService`), статус (`GameStatusService`). Связан с `Developer`, `Publisher`, `Genre`, `Platform`, `Dlc`.
- **GamePrice** (`Service/GamePrice`, `Entity/GamePrice`) — история цен игры (график на карточке), наполняется `Steam/PriceImportService`.
- **OurGame** (`Service/OurGame`, `Entity/OurGame`) — собственные игры проекта (не из Steam): CRUD, загрузка изображений (`OurGameImageStorage`/`OurGameImageUploadService`), генерация слага, ссылки на скачивание (`OurGameDownloadLink`).
- **OurGamePost** (`Service/OurGamePost`, `Entity/OurGamePost`) — новости/посты о собственных играх, аналогичная структура CRUD+изображения.
- **Take** (`Service/Take`, `Entity/Take`) — пользовательские тэйки (мнения) под игрой: создание, комментарии (`TakeComment`), реакции (`TakeReaction`).
- **ScoreDieAgain** (`Service/ScoreDieAgain`, `Entity/ScoreDieAgain`) — таблица рекордов отдельной мини-игры.
- **User** (`Service/User`, `Entity/User`) — регистрация, смена пароля/никнейма, аватар, приватность профиля, подписки (`UserFollow`/`FollowService`), создание модератора.
- **UserReport** (`Service/UserReport`, `Entity/UserReport`) — жалобы пользователей (на тэйки и т.п.), очередь модерации в админке.
- **AuditLog** (`Service/AuditLog`, `Entity/AuditLog`) — журнал действий (`AuditLogger::log()`), вызывается из мест, которые нужно аудировать (например, действия модераторов).
- **Cron** (`Service/Cron`, `Entity/Cron`/`CronRun`) — справочник и история запусков консольных команд, помеченных `#[AsTrackedCron]` (`src/Cron/Attribute`); `CronDiscoveryService` находит команды по атрибуту, `CronSyncService` синхронизирует справочник, `CronLogReader` — чтение логов конкретного запуска. Отображается в `/admin/cron*`.
- **Image** (`Service/Image`) — общие утилиты загрузки изображений (`GameImageDownloader`), используется модулем Game при импорте из Steam.

## Импорт данных (крон)

`src/Command/Import/*` — консольные команды-краны (`ImportGamesCommand`, `ImportGamePricesCommand`), тонкие: разбор аргументов + вызов сервиса импорта + вывод результата через `SymfonyStyle`. Вся логика — в `Service/Steam/*ImportService`. Обработка сетевых ошибок — по паттерну «внешние API-клиенты» из [modules.md](modules.md).

## Аутентификация и события

- `src/Security/` — `AccessDeniedHandler`/`AuthenticationEntryPoint`, единый JSON-формат 401/403 для API (согласуется с `ApiExceptionListener`, см. [frontend.md](frontend.md#формат-ошибок)).
- `src/EventListener/` — сквозные подписчики: `ApiExceptionListener` (формат ошибок API), `CronTrackingListener` (фиксирует запуск/результат команд-кронов в `CronRun`), `LoginSuccessListener`/`LoginFailureListener` (аудит входов через `AuditLogger`).
