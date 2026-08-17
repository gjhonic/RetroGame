# Правила: тесты

## TestCases.md

В каждой директории с юнит-тестами (`tests/Unit/Service/<Модуль>/` и т.п.) рядом с тестами — файл `TestCases.md`: таблица «сценарий → метод теста», пополняется с каждым новым тестом. Пример: [`tests/Unit/Service/Steam/TestCases.md`](../../tests/Unit/Service/Steam/TestCases.md).

## Unit vs functional

Только юнит-тесты (`tests/Unit/`), без реальной БД — в CI нет Postgres (`.github/workflows/ci.yml`).

- **Сервисы** (`src/Service/`) — зависимости через `createMock`. Пример: `tests/Unit/Service/Steam/GameImportServiceTest.php`.
- **API-контроллеры** (`src/Controller/Api/`) — без Kernel: `new SomeApiController()` + `setContainer(new Container())` (пустой контейнер → `AbstractController::json()` не находит `serializer` и отдаёт обычный `JsonResponse`), репозитории — `createMock`. Пример: `tests/Unit/Controller/Api/GameApiControllerTest.php`.
- Мок одновременно как стаб (`willReturn` без `expects`) и как мок (проверка вызовов) — добавить атрибут `#[AllowMockObjectsWithoutExpectations]`.

## Frontend (Vue)

`assets/vue/**/*.vue` покрывается тестами в `tests/Frontend/` (структура зеркалит `assets/vue/`), стек Vitest + `@vue/test-utils` (jsdom, `vitest.config.ts`, `npm run test`). `global.fetch` мокается через `tests/Frontend/support/mockFetch.js`. `TestCases.md` ведётся так же (пример: [`tests/Frontend/Admin/TestCases.md`](../../tests/Frontend/Admin/TestCases.md)).
