# CLAUDE.md

RetroGame — веб-приложение на Symfony (PHP 8.4).

## Язык

Отвечай всегда на русском.

## Стек

PHP 8.4, Symfony 8.1 (Twig, Forms, Validator, Doctrine ORM, Security, Mailer), Composer, Vue 3 + Vite (`symfony/reprise`, `symfony/ux-vue`) для интерактивных публичных страниц, `nelmio/api-doc-bundle` (Swagger `/api/doc`), PHPStan level 8, PHP_CodeSniffer (PSR-12), Symfony CLI.

## Структура

- `src/Controller/Public` — тонкие Twig-контроллеры
- `src/Controller/Api` — JSON API
- `src/Entity`, `src/Repository` — Doctrine
- `config/`, `templates/`, `assets/vue/`, `tests/` (PHPUnit), `migrations/`

## Команды

Через `Makefile` (`make help` — полный список):

- `server-start`/`server-stop` — локальный сервер (собирает фронтенд)
- `assets-install`/`assets-build` — npm/Vite
- `test` — все проверки (PHPStan + PHPCS)
- `test-phpstan [DIR=путь]` (default `src tests`), `test-phpcs [DIR=путь]`, `fix-cs [DIR=путь]`

Перед завершением задачи — прогонять `make test` для затронутых директорий.

## Правила

Подробности в `.claude/rules/`: [modules.md](.claude/rules/modules.md) (модули/классы), [frontend.md](.claude/rules/frontend.md) (Vue+API), [tests.md](.claude/rules/tests.md) (тесты), [git.md](.claude/rules/git.md) (ветки/PR). Локальные правила (не коммитятся) — `.claude/rules/local/`.
