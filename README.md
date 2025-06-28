# 🎮 RetroGame - Платформа отслеживания цен на игры

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.2-000000?style=for-the-badge&logo=symfony&logoColor=white)](https://symfony.com)
[![Doctrine](https://img.shields.io/badge/Doctrine-ORM-FF6D00?style=for-the-badge&logo=doctrine&logoColor=white)](https://www.doctrine-project.org)
[![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)](LICENSE)

> **RetroGame** - это современная веб-платформа для отслеживания цен на игры в различных магазинах. Система автоматически собирает данные о ценах, анализирует историю изменений и помогает пользователям найти лучшие предложения.

## ✨ Возможности

### 🎯 Основной функционал
- **📊 Отслеживание цен** в реальном времени из Steam, Steambuy, Steampay
- **📈 История цен** с графиками и аналитикой
- **🔍 Поиск игр** по названию, жанру, цене
- **💰 Уведомления** о снижении цен
- **📱 Адаптивный дизайн** для всех устройств

### 🛠️ Технические возможности
- **🔄 Автоматический импорт** игр из API магазинов
- **📊 Аналитика цен** с детальной статистикой
- **🔐 Система аутентификации** и авторизации
- **📝 Административная панель** для управления контентом
- **⚡ Высокая производительность** благодаря кэшированию

## 🏗️ Архитектура

### Технологический стек
- **Backend**: PHP 8.1+, Symfony 6.2
- **Database**: MySQL/PostgreSQL с Doctrine ORM
- **Frontend**: Twig templates, Bootstrap 5
- **API**: RESTful API для интеграции с магазинами
- **Queue**: Symfony Messenger для фоновых задач

### Структура проекта
```
RetroGame/
├── src/
│   ├── Command/          # Консольные команды для импорта
│   ├── Controller/       # Контроллеры (Admin/Frontend)
│   ├── Entity/          # Модели данных
│   ├── Repository/      # Репозитории для работы с БД
│   ├── Service/         # Бизнес-логика
│   └── Form/           # Формы
├── templates/           # Twig шаблоны
├── public/             # Публичные файлы
├── migrations/         # Миграции БД
└── tests/             # Тесты
```

## 🚀 Быстрый старт

### Требования
- PHP 8.1 или выше
- Composer
- MySQL 8.0+ или PostgreSQL 13+
- Node.js 16+ (для сборки assets)

### Установка

1. **Клонируйте репозиторий**
```bash
git clone https://github.com/gjhonic/RetroGame.git
cd RetroGame
```

2. **Установите зависимости**
```bash
composer install
npm install
```

3. **Настройте окружение**
```bash
cp .env.example .env
# Отредактируйте .env файл с вашими настройками БД
```

4. **Создайте базу данных**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

5. **Запустите сервер разработки**
```bash
symfony server:start
```

### Импорт данных

Для загрузки игр из магазинов используйте команды:

```bash
# Импорт игр из Steam
php bin/console app:steam-get-games

# Импорт игр из Steambuy
php bin/console app:steambuy-get-games

# Импорт игр из Steampay
php bin/console app:steampay-get-games

# Обновление цен
php bin/console app:steam-update-prices
php bin/console app:steambuy-update-prices
php bin/console app:steampay-update-prices
```

## 📊 Модели данных

### Основные сущности
- **Game** - Игры с описанием, жанрами, датой выпуска
- **Shop** - Магазины (Steam, Steambuy, Steampay)
- **GameShop** - Связь игр с магазинами и ценами
- **GameShopPriceHistory** - История изменения цен
- **Genre** - Жанры игр
- **User** - Пользователи системы

### Интеграции
- **SteamApp** - Данные из Steam API
- **SteambuyApp** - Данные из Steambuy
- **SteampayApp** - Данные из Steampay

## 🔧 Конфигурация

### Переменные окружения
```env
# База данных
DATABASE_URL="mysql://user:password@localhost/retrogame"

# Настройки приложения
APP_ENV=prod
APP_SECRET=your_app_secret
```

### Настройка магазинов
В административной панели можно настроить:
- Список магазинов
- API ключи и endpoints
- Частоту обновления цен
- Фильтры для импорта игр

## 🧪 Тестирование

```bash
# Запуск тестов
php bin/phpunit

# Статический анализ кода
php bin/console phpstan:analyse

# Проверка стиля кода
php bin/console php-cs-fixer:fix
```

## 📈 Мониторинг и аналитика

### Метрики производительности
- Время ответа API
- Количество импортированных игр
- Частота обновления цен
- Статистика пользователей

### Логирование
- Логи импорта данных
- Ошибки API запросов
- Действия пользователей
- Системные события

## 🤝 Разработка

### Стандарты кода
- PSR-12 для PHP
- Symfony Coding Standards
- Документирование кода
- Типизация параметров и возвращаемых значений

### Git workflow
- Feature branches для новых функций
- Pull requests с код-ревью
- Автоматические тесты в CI/CD
- Семантическое версионирование

### Команды для разработки
```bash
# Создание новой сущности
php bin/console make:entity

# Создание миграции
php bin/console make:migration

# Создание контроллера
php bin/console make:controller

# Очистка кэша
php bin/console cache:clear
```

## 📝 Лицензия

Этот проект является проприетарным программным обеспечением. Все права защищены.

## 👥 Команда

- **Разработчик**: [Gjhonic](https://github.com/gjhonic)
- **Технологии**: PHP, Symfony, Doctrine, MySQL

## 📞 Поддержка

Если у вас есть вопросы или предложения:
- Создайте Issue в GitHub
- Напишите на email: [ваш-email@example.com]

---

<div align="center">
  <strong>🎮 RetroGame - Ваш путеводитель в мире игровых цен!</strong>
</div> 