# Makefile

.PHONY: help server-start server-stop test test-phpstan test-phpcs test-audit test-lint-yaml test-lint-container fix-cs ci

#### Predefined global variables/functions
# Colors for SH scripts. See https://www.shellhacks.com/bash-colors/
CE           = \033[0m
C_YELLOW     = \033[0;33m
C_GREEN      = \033[0;32m

# Директория/файл для точечной проверки PHPStan и PHPCS, например: make test-phpstan DIR=src/Controller
DIR ?= src tests

## 🖥️  Symfony Server

server-start: ## 🚀 Запуск Symfony сервера на http://127.0.0.1:8000
	symfony server:start --allow-http --port=8000

server-stop: ## 🛑 Остановка Symfony сервера
	symfony server:stop

## ✅ Тесты

test: test-phpstan test-phpcs test-audit test-lint-yaml test-lint-container ## 🧪 Запуск всех локальных проверок
	@printf "$(C_GREEN)✅ Все проверки пройдены, всё отлично!$(CE)\n"

test-phpstan: ## 📦 PHPStan — статический анализ (DIR=путь для конкретной папки/файла)
	vendor/bin/phpstan analyse -c phpstan.dist.neon $(DIR)

test-phpcs: ## 🔍 Code Sniffer — проверка стиля (DIR=путь для конкретной папки/файла)
	vendor/bin/phpcs $(DIR)

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
	@echo "  ║       RETRO GAME 🎮        ║"
	@echo "  ╚═══════════════════════════╝"
	@echo ""
	@printf "🧰 \033[1mКоманды:\033[0m\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-21s\033[0m %s\n", $$1, $$2}'
	@echo ""
