# Правила: фронтенд (Vue + API)

Стандарт публичных интерактивных страниц: тонкий Twig-шаблон без данных + Vue 3 SFC, сам забирающий данные через JSON API. Серверный рендеринг данных в Twig не используется. Пример: `src/Controller/Public/GameController.php` + `templates/public/game/` + `assets/vue/Public/GameCatalog.vue`/`GameDetail.vue` + `src/Controller/Api/Public/GameApiController.php`.

## Структура

- `src/Controller/Api/` — JSON-контроллеры по трём областям (подпапка = namespace, своя секция Swagger `/api/doc/{area}`):
  - `Api/Public/` — без авторизации (`PUBLIC_ACCESS`). Пример: `Public/GameApiController.php`, `/api/games...`.
  - `Api/Cabinet/` — `ROLE_USER`, `/api/cabinet/...` (эндпоинтов пока нет, `access_control` настроен).
  - `Api/Admin/` — `ROLE_MODERATOR` (наследует `ROLE_ADMIN`), `/api/admin/...`. Пример: `Admin/GameApiController.php`.
  - Права — централизованно в `access_control` (`config/packages/security.yaml`) по префиксу пути, `#[IsGranted]` не нужен (но учитывать при переносе роута между областями).
  - Один класс-контроллер на ресурс, ответ через `$this->json(...)`. Логику (импорт, вычисления) — в `src/Service/`, в контроллере — только выборка + маппинг.
- `src/Controller/Public/` — только рендер тонкой Twig-обёртки (`extends base.html.twig`), **без запросов к БД** (ни данные, ни 404, ни `<title>`). Параметры маршрута (`slug` и т.п.) прокидываются в шаблон как есть. Пример: `GameController::show()`.
- 404 и `<title>` — на клиенте: API кидает `createNotFoundException`, Vue-компонент показывает ошибку и выставляет `document.title` после загрузки (см. `GameDetail.vue`). Страница всегда отвечает `200` — осознанный компромисс ради тонких контроллеров.
- `assets/vue/*.vue` — корневые компоненты (`symfony/ux-vue`), монтируются через `{{ vue_component('Name', {...}) }}`. Пропсы — только простые идентификаторы (`slug`), не готовые данные.
- Подпапки `assets/vue/<Модуль>/` по месту использования (`Public/`, позже `Admin/` и т.п.) — чтобы не путать одноимённые компоненты. Имя в `vue_component()` включает подпапку: `vue_component('Public/GameCatalog')` (регистрация в `assets/app.js` через `import.meta.glob('./vue/**/*.vue')`).
- Данные — через `fetch()` к `/api/...` в `onMounted`, состояния `loading`/`error` обязательны.

## Сборка

Vite через `symfony/reprise` (`vite.config.ts`), не AssetMapper. `make assets-install`/`assets-build`; `server-start` уже зависит от `assets-build`.

## Документация API

`nelmio/api-doc-bundle`, Swagger UI `/api/doc`, схема `/api/doc.json`. У каждого экшена — атрибуты `OpenApi\Attributes` (`#[OA\Tag]`, `#[OA\Parameter]`, `#[OA\Response]`).

## Тесты

См. [tests.md](tests.md) — API-контроллеры юнит-тестами с моком репозитория, без реальной БД.
