# Правила: как писать тесты

## TestCases.md

Для каждой директории с юнит-тестами сервиса (например, `tests/Unit/Service/<Модуль>/`)
рядом с тестами ведётся файл `TestCases.md` — таблица «какой сценарий проверяется
→ в каком методе теста». При добавлении нового теста — добавляйте строку в таблицу.

Пример: [`tests/Unit/Service/Steam/TestCases.md`](../../tests/Unit/Service/Steam/TestCases.md).

## Unit vs functional

В проекте — только юнит-тесты (`tests/Unit/`), без реальной БД: в CI нет
поднятого Postgres (см. `.github/workflows/ci.yml`), поэтому
функциональные тесты с настоящим Doctrine не заводим.

- **Сервисы** (`src/Service/`) — зависимости мокаются (`createMock`),
  пример: `tests/Unit/Service/Steam/GameImportServiceTest.php`.
- **API-контроллеры** (`src/Controller/Api/`) — тестируются напрямую, без
  Kernel: `new SomeApiController()`, затем
  `setContainer(new \Symfony\Component\DependencyInjection\Container())`
  (пустой контейнер — `AbstractController::json()` проверяет
  `container->has('serializer')` и при `false` отдаёт обычный
  `JsonResponse` без похода в DI), репозитории — `createMock`. Пример:
  `tests/Unit/Controller/Api/GameApiControllerTest.php`.
- Если тест одновременно использует мок и как стаб (`method()->willReturn()`
  без `expects()`), и как мок (проверка вызовов) — добавляйте класс-атрибут
  `#[AllowMockObjectsWithoutExpectations]`, как в примерах выше.

## Frontend (Vue)

Компоненты `assets/vue/**/*.vue` покрываются тестами в `tests/Frontend/`
(структура повторяет `assets/vue/`: `tests/Frontend/Admin/`,
`tests/Frontend/Public/`), стек — Vitest + `@vue/test-utils`
(`jsdom`-окружение, конфиг `vitest.config.ts` в корне, запуск
`npm run test`). `global.fetch` мокается через хелперы
`tests/Frontend/support/mockFetch.js`, реального похода в API нет.
`TestCases.md` ведётся так же, как для PHP-юнит-тестов (см. выше) —
отдельным файлом в каждой директории с тестами, пример:
[`tests/Frontend/Admin/TestCases.md`](../../tests/Frontend/Admin/TestCases.md).

## Остальное

Дополнять по мере разработки: фикстуры, именование, покрытие и т.д.
