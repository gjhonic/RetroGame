# Правила: как писать модули

## Структура папок

- Интерфейсы лежат в подпапке `Interfaces/` внутри своего модуля
  (например, `src/Entity/Interfaces/NamedEntityInterface.php`,
  `src/Service/Steam/Interfaces/RateLimiterInterface.php`), namespace
  дополняется сегментом `Interfaces`.
- Исключения (`Exception`) лежат в подпапке `Exceptions/` внутри своего
  модуля (например, `src/Service/Steam/Exceptions/SteamApiException.php`),
  namespace дополняется сегментом `Exceptions`.

## Doctrine Entity

- Маппинг через PHP-атрибуты (`#[ORM\...]`), не аннотации/XML/YAML.
- Свойства `private`, доступ через `get`/`is` и `set` (fluent-сеттеры, `return static`).
- Дата/время — только `\DateTimeImmutable` (тип колонки `datetime_immutable` / `date_immutable`).
- `createdAt`/`updatedAt` проставляются в конструкторе; для обновления — метод `touch()`.
- Внешний ID источника данных (например, `rawgId` для RAWG) — `nullable: true, unique: true`,
  чтобы сущность можно было создать и вручную, без привязки к внешнему API.
- Миграции — вручную в `migrations/`, если локально нет поднятой БД для `make:migration`
  (см. `make db-start` — Postgres в WSL). Проверка маппинга без БД: `bin/console doctrine:schema:validate --skip-sync`.
- Для устранения ложных срабатываний PHPStan на Doctrine-сущностях (например,
  `$id` "never assigned") подключён `phpstan/phpstan-doctrine` — не подавлять такие
  ошибки через `ignoreErrors`.
