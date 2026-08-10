# 🎮 RetroGame

[![CI](https://github.com/gjhonic/RetroGame/actions/workflows/ci.yml/badge.svg)](https://github.com/gjhonic/RetroGame/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4-777bb4?logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)

Веб-приложение на Symfony.

## 🧱 Стек

- PHP 8.4, Symfony 8.1 (Twig, Forms, Validator, Doctrine ORM, Security, Mailer)
- Composer — управление зависимостями
- PostgreSQL — база данных (локально в WSL, см. `make db-start`)
- PHPStan (уровень 8) — статический анализ
- PHP_CodeSniffer (PSR-12) — стиль кода
- GitHub Actions — CI

## 🚀 Быстрый старт

Требования: PHP 8.4+, Composer, [Symfony CLI](https://symfony.com/download),
Node.js 22+ (для сборки фронтенда и `npm run test` — `jsdom`, на котором
работает Vitest, требует Node 22+; версия закреплена в `.nvmrc`, `nvm use`
переключит автоматически).

```bash
git clone git@github.com:gjhonic/RetroGame.git
cd RetroGame
composer install
make server-start
```

Приложение будет доступно на [http://127.0.0.1:8001](http://127.0.0.1:8001)
(`make server-start` явно передаёт `--port=8001` — ключ `port` в
`.symfony.local.yaml` Symfony CLI на практике не читает). При запуске
голым `symfony serve`/через IDE указывайте порт так же явно:
`symfony server:start --port=8001`.

## 🛠️ Команды

Полный список команд: `make help`.

| Команда                  | Описание                                          |
|---------------------------|----------------------------------------------------|
| `make server-start`       | Запуск локального Symfony-сервера                  |
| `make server-stop`        | Остановка сервера                                   |
| `make db-start`           | Запуск PostgreSQL-сервера в WSL                     |
| `make db-stop`            | Остановка PostgreSQL-сервера в WSL                  |
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

## 🚢 Деплой

После зелёного CI пуш в `main` автоматически выкладывает прод-релиз на VPS
(джоб `deploy` в том же [`.github/workflows/ci.yml`](.github/workflows/ci.yml):
сборка `composer install --no-dev` + `npm run build`, атомарное переключение
релиза по SSH). Настройка сервера с нуля и структура каталогов — в
[`docs/DEPLOY.md`](docs/DEPLOY.md), шаблоны конфигов — в [`deploy/`](deploy/).

## 🕹️ Импорт игр из Steam

Источник данных об играх — Steam Web API: список игр через `IStoreService/GetAppList`
(нужен бесплатный ключ), детали каждой игры — через недокументированный, но открытый
Store API `appdetails` (обложки, скриншоты, жанры, категории, платформы,
разработчики/издатели, Metacritic и т.д.).

1. Получить бесплатный ключ: https://steamcommunity.com/dev/apikey
2. Прописать `STEAM_API_KEY` в `.env.local` (не коммитится)
3. Запустить:

```bash
php bin/console app:games:import --limit=20 --last-appid=0 --delay-ms=1500
```

Команда сохраняет игры в БД (создаёт/обновляет по `steamAppId`, скачивает обложку
в `public/uploads/games/`) и печатает итог по каждой игре из порции.
`--limit` — размер порции, `--last-appid` — курсор постраничности (по умолчанию
продолжает автоматически с прошлого запуска, курсор хранится в БД), `--delay-ms` —
пауза между запросами к недокументированному `appdetails`, чтобы не словить бан по IP.

## 👤 Пользователи и админка

У пользователя одна роль: `ROLE_USER`, `ROLE_MODERATOR` или `ROLE_ADMIN`
(иерархия — админ включает права модератора, модератор — обычного пользователя,
см. `role_hierarchy` в `config/packages/security.yaml`). Админка (`/admin`)
доступна модераторам и админам, вход — по email/паролю на `/admin/login`.

Завести/обновить администратора по умолчанию:

1. Прописать `ADMIN_PASSWORD` в `.env.local` (не коммитится); email по умолчанию
   задан в `.env` (`ADMIN_EMAIL`), можно переопределить там же.
2. Запустить:

```bash
php bin/console app:user:create-admin
```

Команда идемпотентна: если пользователь с таким email уже есть — ему выставят
роль админа и новый пароль, иначе создадут нового. Email/пароль можно передать
явно опциями `--email`/`--password`, не трогая `.env`.

## 📁 Структура проекта

```
src/Command/      — консольные команды
src/Controller/   — контроллеры
src/Entity/       — сущности Doctrine
src/Repository/   — репозитории Doctrine
src/Service/      — сервисы (импорт из Steam, загрузка изображений и т.д.)
config/           — конфигурация бандлов, роутинга, сервисов
templates/        — Twig-шаблоны
tests/            — тесты (PHPUnit)
migrations/       — миграции БД
```

## 🤖 Документация для Claude

Базовая информация о проекте и правила разработки для AI-ассистента — в [`CLAUDE.md`](CLAUDE.md) и [`.claude/rules/`](.claude/rules).
