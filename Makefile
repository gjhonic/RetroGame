# Makefile

.PHONY: help server-start server-stop db-start db-stop composer-install assets-install assets-build cache-clear build clean rebuild test test-phpstan test-phpcs test-unit test-audit test-lint-yaml test-lint-container fix-cs ci

.DEFAULT_GOAL := help

#### Predefined global variables/functions
# Colors for SH scripts. See https://www.shellhacks.com/bash-colors/
CE           = \033[0m
C_YELLOW     = \033[0;33m
C_GREEN      = \033[0;32m

# Директория/файл для точечной проверки PHPStan и PHPCS, например: make test-phpstan DIR=src/Controller
DIR ?= src tests

## 🖥️  Symfony Server

# Порт зафиксирован явным флагом: ключ port в .symfony.local.yaml Symfony CLI
# (v5.17.1) на практике не читает, он всегда стартует на 8000 по умолчанию.
server-start: assets-build ## 🚀 Запуск Symfony сервера на http://127.0.0.1:8001
	symfony server:start --port=8001

server-stop: ## 🛑 Остановка Symfony сервера
	symfony server:stop

## 🎨 Frontend (Vite/Reprise)

assets-install: ## 📥 Установка npm-зависимостей фронтенда
	npm install

assets-build: ## 🏗️ Сборка фронтенд-ассетов (Vite) в public/build
	npm run build

## 📦 PHP-зависимости

composer-install: ## 📥 Установка PHP-зависимостей (composer)
	composer install

## 🔧 Сборка проекта

build: composer-install assets-install assets-build cache-clear ## 🔧 Установить все зависимости (composer+npm) и собрать фронтенд — "почини и запусти"
	@printf "$(C_GREEN)✅ Проект собран: PHP- и npm-зависимости установлены, фронтенд собран.$(CE)\n"

clean: ## 🗑️ Удалить vendor/node_modules/сборки/кэш (для чистой пересборки)
	rm -rf vendor node_modules public/build assets/vendor var/cache

rebuild: clean build ## ♻️ Полная пересборка с нуля: чистит vendor/node_modules/build/кэш и пересобирает всё заново
	@printf "$(C_GREEN)✅ Проект пересобран с нуля.$(CE)\n"

cache-clear: ## 🧹 Очистка кэша Symfony
	php bin/console cache:clear

## 🗄️  PostgreSQL в WSL

db-start: ## ▶️ Запуск PostgreSQL-сервера в WSL
	sudo service postgresql start

db-stop: ## ⏹️ Остановка PostgreSQL-сервера в WSL
	sudo service postgresql stop

## ✅ Тесты

test: test-phpstan test-phpcs test-unit test-audit test-lint-yaml test-lint-container ## 🧪 Запуск всех локальных проверок
	@printf "$(C_GREEN)✅ Все проверки пройдены, всё отлично!$(CE)\n"

test-phpstan: ## 📦 PHPStan — статический анализ (DIR=путь для конкретной папки/файла)
	vendor/bin/phpstan analyse -c phpstan.dist.neon $(DIR)

test-phpcs: ## 🔍 Code Sniffer — проверка стиля (DIR=путь для конкретной папки/файла)
	vendor/bin/phpcs $(DIR)

test-unit: ## 🧪 PHPUnit — юнит-тесты
	bin/phpunit

test-audit: ## 🛡️ Composer Audit — проверка зависимостей на уязвимости
	composer audit

test-lint-yaml: ## 📄 Lint YAML — проверка синтаксиса конфигов
	php bin/console lint:yaml config

test-lint-container: ## 🧩 Lint Container — проверка DI-контейнера
	php bin/console lint:container

## 🧹 Автоисправления

fix-cs: ## 🛠️ Code Sniffer Beautifier — автоисправление стиля (DIR=путь для конкретной папки/файла)
	vendor/bin/phpcbf $(DIR)

## 🤖 CI

ci: ## 🤖 Локальная симуляция CI-пайплайна (без пуша на GitHub)
	composer install --no-interaction --prefer-dist
	php bin/console about --env=prod
	$(MAKE) test
	@printf "$(C_GREEN)🎉 CI пройден локально, можно пушить!$(CE)\n"

## 📖 Помощь

help: ## ❓ Показать доступные команды
	@echo ""
	@echo "  ╔═══════════════════════════╗"
	@echo "  ║       RETRO GAME 🎮       ║"
	@echo "  ╚═══════════════════════════╝"
	@awk 'BEGIN {FS = ":.*?## "} \
		/^## / {printf "\n$(C_YELLOW)%s$(CE)\n", substr($$0, 4)} \
		/^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[36m%-21s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo ""
