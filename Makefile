# Makefile

.PHONY: help server-start server-stop db-start db-stop test test-phpstan test-phpcs test-unit test-audit test-lint-yaml test-lint-container fix-cs ci

.DEFAULT_GOAL := help

#### Predefined global variables/functions
# Colors for SH scripts. See https://www.shellhacks.com/bash-colors/
CE           = \033[0m
C_YELLOW     = \033[0;33m
C_GREEN      = \033[0;32m

# Директория/файл для точечной проверки PHPStan и PHPCS, например: make test-phpstan DIR=src/Controller
DIR ?= src tests

## 🖥️  Symfony Server

server-start: ## 🚀 Запуск Symfony сервера на http://127.0.0.1:8000
	symfony server:start

server-stop: ## 🛑 Остановка Symfony сервера
	symfony server:stop

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
