# Makefile

.PHONY: help server-start server-stop test test-phpstan test-phpcs fix-cs

#### Predefined global variables/functions
# Colors for SH scripts. See https://www.shellhacks.com/bash-colors/
CE           = \033[0m
C_YELLOW     = \033[0;33m

# Директория/файл для точечной проверки PHPStan и PHPCS, например: make test-phpstan DIR=src/Controller
DIR ?= src tests

## 🖥️  Symfony Server

server-start: ## 🚀 Запуск Symfony сервера на http://127.0.0.1:8000
	symfony server:start --allow-http --port=8000

server-stop: ## 🛑 Остановка Symfony сервера
	symfony server:stop

## ✅ Тесты

test: test-phpstan test-phpcs ## 🧪 Запуск всех проверок

test-phpstan: ## 📦 PHPStan — статический анализ (DIR=путь для конкретной папки/файла)
	vendor/bin/phpstan analyse -c phpstan.dist.neon $(DIR)

test-phpcs: ## 🔍 Code Sniffer — проверка стиля (DIR=путь для конкретной папки/файла)
	vendor/bin/phpcs $(DIR)

## 🧹 Автоисправления

fix-cs: ## 🛠️ Code Sniffer Beautifier — автоисправление стиля (DIR=путь для конкретной папки/файла)
	vendor/bin/phpcbf $(DIR)

## 📖 Помощь

help: ## ❓ Показать доступные команды
	@echo ""
	@echo "  ╔═══════════════════════════╗"
	@echo "  ║       RETRO GAME 🎮        ║"
	@echo "  ╚═══════════════════════════╝"
	@echo ""
	@printf "🧰 \033[1mКоманды:\033[0m\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""
