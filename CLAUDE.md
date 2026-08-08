# CLAUDE.md

Базовая информация о проекте для Claude. Дополняется по мере разработки.

## О проекте

RetroGame — веб-приложение на Symfony (PHP 8.4).

## Язык общения

Отвечай пользователю всегда на русском языке, независимо от языка вопроса.

## Стек

- PHP 8.4, Symfony 8.1 (webapp pack: Twig, Forms, Validator, Doctrine ORM, Security, Mailer)
- Composer — управление зависимостями
- Vue 3 + Vite (`symfony/reprise`, `symfony/ux-vue`) — интерактивные публичные страницы
- `nelmio/api-doc-bundle` — Swagger-документация JSON API (`/api/doc`)
- PHPStan (уровень 8) — статический анализ
- PHP_CodeSniffer (PSR-12) — стиль кода
- Symfony CLI — локальный сервер

## Структура

- `src/Controller/Public` — контроллеры страниц (тонкие Twig-обёртки)
- `src/Controller/Api` — JSON API-контроллеры
- `src/Entity` — сущности Doctrine
- `src/Repository` — репозитории Doctrine
- `config/` — конфигурация бандлов, роутинга, сервисов
- `templates/` — Twig-шаблоны
- `assets/vue/` — Vue 3 SFC-компоненты
- `tests/` — тесты (PHPUnit)
- `migrations/` — миграции БД

## Команды

Все основные команды — через `Makefile`. Полный список: `make help`.

- `make server-start` / `make server-stop` — локальный сервер (сначала собирает фронтенд)
- `make assets-install` / `make assets-build` — npm-зависимости / сборка Vite
- `make test` — все проверки (PHPStan + PHPCS)
- `make test-phpstan [DIR=путь]` — статический анализ (по умолчанию `src tests`)
- `make test-phpcs [DIR=путь]` — проверка стиля
- `make fix-cs [DIR=путь]` — автоисправление стиля

Перед тем как считать задачу завершённой, прогоняй `make test` для затронутых директорий.

## Правила и стандарты

Подробные правила разработки — в `.claude/rules/`:

- [`.claude/rules/modules.md`](.claude/rules/modules.md) — как писать модули/классы
- [`.claude/rules/frontend.md`](.claude/rules/frontend.md) — стандарт Vue + API для публичных страниц
- [`.claude/rules/tests.md`](.claude/rules/tests.md) — как писать тесты
- [`.claude/rules/git.md`](.claude/rules/git.md) — именование веток, правила PR

Локальные правила разработчика (не коммитятся) — `.claude/rules/local/`.

## Примечания

Этот файл и файлы в `.claude/rules/` пока являются заготовками и будут наполняться по ходу разработки проекта.
