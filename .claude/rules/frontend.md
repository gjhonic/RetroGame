# Правила: фронтенд (Vue + API)

Стандарт интерактивных страниц: тонкий Twig-шаблон без данных + Vue 3 SFC, сам забирающий данные через JSON API. Серверный рендеринг данных в Twig не используется. Пример: `src/Controller/Cabinet/GameController.php` + `templates/cabinet/game/` + `assets/vue/Cabinet/GameCatalog.vue`/`GameDetail.vue` + `src/Controller/Api/Public/GameApiController.php`.

Twig-контроллеры и Vue-компоненты живут в двух зонах (namespace/подпапка = зона):

- **Cabinet** — единый шаблон для всего сайта, включая неавторизованных пользователей (например, каталог игр доступен всем, а добавлять тэйки — только вошедшим, см. `GameController` doc-комментарий). `src/Controller/Cabinet/*` + `assets/vue/Cabinet/*`.
- **Admin** — `ROLE_MODERATOR`+. `src/Controller/Admin/*` + `assets/vue/Admin/*`.

Отдельного модуля "Public" нет (упразднён в RG-9) — доступ внутри Cabinet-страниц регулируется на уровне данных/пропов (`is_granted(...)` в Twig прокидывается в Vue как проп, например `isAuthenticated`), а не отдельными контроллерами.

## Структура

- `src/Controller/Api/` — JSON-контроллеры по трём областям (подпапка = namespace, своя секция Swagger `/api/doc/{area}`); эти области — про авторизацию API, а не про то, из какого Twig-раздела (Cabinet/Admin) идёт запрос:
  - `Api/Public/` — без авторизации (`PUBLIC_ACCESS`). Пример: `Public/GameApiController.php`, `/api/games...` (вызывается со страниц Cabinet).
  - `Api/Cabinet/` — `ROLE_USER`, `/api/cabinet/...`. Пример: `Cabinet/GameApiController.php`.
  - `Api/Admin/` — `ROLE_MODERATOR` (наследует `ROLE_ADMIN`), `/api/admin/...`. Пример: `Admin/GameApiController.php`.
  - Права — централизованно в `access_control` (`config/packages/security.yaml`) по префиксу пути, `#[IsGranted]` не нужен (но учитывать при переносе роута между областями).
  - Один класс-контроллер на ресурс, ответ через `$this->json(...)`. Логику (импорт, вычисления) — в `src/Service/`, в контроллере — только выборка + маппинг.
- `src/Controller/Cabinet/`, `src/Controller/Admin/` — только рендер тонкой Twig-обёртки (`extends cabinet/base.html.twig` или `admin/base.html.twig`), **без запросов к БД** (ни данные, ни 404, ни `<title>`). Параметры маршрута (`slug` и т.п.) прокидываются в шаблон как есть. Пример: `Cabinet/GameController::show()`.
- 404 и `<title>` — на клиенте: API кидает `createNotFoundException`, Vue-компонент показывает ошибку и выставляет `document.title` после загрузки (см. `GameDetail.vue`). Страница всегда отвечает `200` — осознанный компромисс ради тонких контроллеров.
- `assets/vue/*.vue` — корневые компоненты (`symfony/ux-vue`), монтируются через `{{ vue_component('Name', {...}) }}`. Пропсы — только простые идентификаторы (`slug`, флаги вроде `isAuthenticated`), не готовые данные.
- Подпапки `assets/vue/{Cabinet,Admin}/` — по зоне использования, чтобы не путать одноимённые компоненты. Имя в `vue_component()` включает подпапку: `vue_component('Cabinet/GameCatalog')` (регистрация в `assets/app.js` через `import.meta.glob('./vue/**/*.vue')`).
- Данные — через `fetch()` к `/api/...` в `onMounted`, состояния `loading`/`error` обязательны.

## Формат ошибок

Любое исключение на `/api/...` приводится `App\EventListener\ApiExceptionListener` к единому JSON `{"message": "..."}` (вместо HTML-страницы Symfony) — с тем же статус-кодом, что и у `HttpExceptionInterface` (404 из `createNotFoundException` и т.п.). Vue при `fetch()` может рассчитывать на это поле для показа ошибки пользователю.

## Сборка

Vite через `symfony/reprise` (`vite.config.ts`), не AssetMapper. `make assets-install`/`assets-build`; `server-start` уже зависит от `assets-build`.

## Документация API

`nelmio/api-doc-bundle`, Swagger UI `/api/doc`, схема `/api/doc.json`. У каждого экшена — атрибуты `OpenApi\Attributes` (`#[OA\Tag]`, `#[OA\Parameter]`, `#[OA\Response]`).

## Тесты

См. [tests.md](tests.md) — API-контроллеры юнит-тестами с моком репозитория, без реальной БД.
