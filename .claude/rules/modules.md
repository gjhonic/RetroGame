# Правила: модули

## Папки

- Интерфейсы — в подпапке `Interfaces/` внутри модуля (`src/Entity/Interfaces/NamedEntityInterface.php`, `src/Service/Steam/Interfaces/RateLimiterInterface.php`), namespace + `Interfaces`.
- Исключения — в `Exceptions/` внутри модуля (`src/Service/Steam/Exceptions/SteamApiException.php`), namespace + `Exceptions`.

## Controller

Только public action-методы (один на маршрут), никаких private/protected — вспомогательную логику (маппинг, вычисления) выносить в `src/Service/...`, инжектить в action. Пример: `GameApiController` + `src/Service/Game/GameMapper.php`.

## Doctrine Entity

- Маппинг через PHP-атрибуты (`#[ORM\...]`).
- Свойства `private`, доступ через `get`/`is`/`set` (fluent, `return static`).
- Дата/время — только `\DateTimeImmutable` (`datetime_immutable`/`date_immutable`).
- `createdAt`/`updatedAt` — в конструкторе; обновление через `touch()`.
- Внешний ID источника (например `rawgId`) — `nullable: true, unique: true` (сущность создаваема и без внешнего API).
- Миграции — вручную в `migrations/`, если нет локальной БД для `make:migration` (`make db-start` — Postgres в WSL). Проверка без БД: `bin/console doctrine:schema:validate --skip-sync`.
- Подключён `phpstan/phpstan-doctrine` (для ложных срабатываний типа `$id` "never assigned") — не подавлять такие ошибки через `ignoreErrors`.
