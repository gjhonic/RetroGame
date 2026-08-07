# 🎮 RetroGame

[![CI](https://github.com/gjhonic/RetroGame/actions/workflows/ci.yml/badge.svg)](https://github.com/gjhonic/RetroGame/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777bb4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)

Веб-приложение на Symfony.

## 🧱 Стек

- PHP 8.4, Symfony 8.1 (Twig, Forms, Validator, Doctrine ORM, Security, Mailer)
- Composer — управление зависимостями
- PostgreSQL — база данных (см. `compose.yaml`)
- PHPStan (уровень 8) — статический анализ
- PHP_CodeSniffer (PSR-12) — стиль кода
- GitHub Actions — CI

## 🚀 Быстрый старт

Требования: PHP 8.4+, Composer, [Symfony CLI](https://symfony.com/download).

```bash
git clone git@github.com:gjhonic/RetroGame.git
cd RetroGame
composer install
make server-start
```

Приложение будет доступно на [http://127.0.0.1:8000](http://127.0.0.1:8000).

## 🛠️ Команды

Полный список команд: `make help`.

| Команда                  | Описание                                          |
|---------------------------|----------------------------------------------------|
| `make server-start`       | Запуск локального Symfony-сервера                  |
| `make server-stop`        | Остановка сервера                                   |
| `make test`               | Запуск всех локальных проверок                      |
| `make test-phpstan`       | Статический анализ (PHPStan)                        |
| `make test-phpcs`         | Проверка стиля кода (PHP_CodeSniffer)               |
| `make test-unit`          | Юнит-тесты (PHPUnit)                                |
| `make test-audit`         | Проверка зависимостей на уязвимости (Composer Audit) |
| `make test-lint-yaml`     | Проверка синтаксиса YAML-конфигов                   |
| `make test-lint-container`| Проверка DI-контейнера                              |
| `make fix-cs`             | Автоисправление стиля кода                          |
| `make ci`                 | Локальная симуляция CI-пайплайна                    |

У `test-phpstan`, `test-phpcs` и `fix-cs` есть параметр `DIR` для точечной проверки:

```bash
make test-phpstan DIR=src/Controller
```

## ✅ Качество кода и CI

При каждом push и Pull Request в `main` автоматически запускается пайплайн ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)): проверка сборки приложения, PHPStan, PHPCS, Composer Audit и lint конфигов.

Ветка `main` защищена: слияние возможно только после успешного прохождения CI и минимум одного апрува от разработчика. Прямой push в `main` запрещён — все изменения вносятся через Pull Request.

Перед тем как открыть PR, рекомендуется прогнать `make ci` локально — она полностью повторяет проверки из пайплайна.

## 📁 Структура проекта

```
src/Controller/   — контроллеры
src/Entity/       — сущности Doctrine
src/Repository/   — репозитории Doctrine
config/           — конфигурация бандлов, роутинга, сервисов
templates/        — Twig-шаблоны
tests/            — тесты (PHPUnit)
migrations/       — миграции БД
```

## 🤖 Документация для Claude

Базовая информация о проекте и правила разработки для AI-ассистента — в [`CLAUDE.md`](CLAUDE.md) и [`.claude/rules/`](.claude/rules).
