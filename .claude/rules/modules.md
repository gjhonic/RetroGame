# Правила: модули

## Папки

- Интерфейсы — в подпапке `Interfaces/` внутри модуля (`src/Entity/Interfaces/NamedEntityInterface.php`, `src/Service/Steam/Interfaces/RateLimiterInterface.php`), namespace + `Interfaces`.
- Исключения — в `Exceptions/` внутри модуля (`src/Service/Steam/Exceptions/SteamApiException.php`), namespace + `Exceptions`.

## Controller

Только public action-методы (один на маршрут), никаких private/protected — вспомогательную логику (маппинг, вычисления) выносить в `src/Service/...`, инжектить в action. Пример: `GameApiController` + `src/Service/Game/GameMapper.php`.

## Внешние API-клиенты

- Каждый метод `src/Service/<Модуль>/*Client.php`, делающий HTTP-запрос, оборачивает `Symfony\Contracts\HttpClient\Exception\ExceptionInterface` в доменное исключение модуля (`Exceptions/`, например `SteamApiException`) с контекстом запроса (какой эндпоинт, какие параметры). Пример: `SteamClient::requestAppDetails()`/`fetchGameAppList()`.
- Вызывающий сервис не даёт сетевой ошибке уронить весь батч/команду целиком: если можно продолжить с частичным результатом — исключение ловится и превращается в результат с описанием ошибки (например `ImportResult::$failureMessage`), а не пробрасывается дальше. Пример: `GameImportService::importNextBatch()`.

## Doctrine Entity

- Маппинг через PHP-атрибуты (`#[ORM\...]`).
- Свойства `private`, доступ через `get`/`is`/`set` (fluent, `return static`).
- Дата/время — только `\DateTimeImmutable` (`datetime_immutable`/`date_immutable`).
- `createdAt`/`updatedAt` — в конструкторе; обновление через `touch()`.
- Внешний ID источника (например `rawgId`) — `nullable: true, unique: true` (сущность создаваема и без внешнего API).
- Миграции — вручную в `migrations/`, если нет локальной БД для `make:migration` (`make db-start` — Postgres в WSL). Проверка без БД: `bin/console doctrine:schema:validate --skip-sync`.
- Подключён `phpstan/phpstan-doctrine` (для ложных срабатываний типа `$id` "never assigned") — не подавлять такие ошибки через `ignoreErrors`.
