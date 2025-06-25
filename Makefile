# Makefile

.PHONY: help server-start server-stop db-start db-stop test test-phpstan test-phpcs

#### Predefined global variables/functions
# Colors for SH scripts. See https://www.shellhacks.com/bash-colors/
CE           = \033[0m
C_YELLOW     = \033[0;33m

## 🖥️  Symfony Server

server-start: ## 🚀 Запуск Symfony сервера на http://RetroGame:8000
	symfony server:start --allow-http --port=8000

server-stop: ## 🛑 Остановка Symfony сервера
	symfony server:stop

## 🗄️  MySQL в WSL

db-start: ## ▶️ Запуск MySQL-сервера в WSL
	sudo service mysql start

db-stop: ## ⏹️ Остановка MySQL-сервера в WSL
	sudo service mysql stop

## ✅ Тесты

test: test-phpstan test-phpcs ## 🧪 Запуск всех тестов

test-phpstan: ## 📦 PHPStan — статический анализ
	vendor/bin/phpstan analyse -c phpstan.neon --level=8

test-phpcs: ## 🔍 Code Sniffer — проверка стиля
	vendor/bin/phpcs --standard=.phpcs.xml src tests

## 🧹 Автоисправления

fix-cs: ## 🛠️ Code Style Fixer — автоисправление по PSR-12
	vendor/bin/php-cs-fixer fix

## 📖 Помощь

help: ## ❓ Показать доступные команды
	@echo ""
	@echo " _____  ______ _______ _____    ____     _____            __  __ ______ "
	@echo "|  __ \|  ____|__   __|  __ \  / __ \   / ____|     /\   |  \/  |  ____|"
	@echo "| |__) | |__     | |  | |__) || |  | | | /  __     /  \  | \  / | |__   "
	@echo "|  _  /|  __|    | |  |  _  / | |  | | | | |_ |   / /\ \ | |\/| |  __|  "
	@echo "| | \ \| |____   | |  | | \ \ \ |__| | | |__| |  / ____ \| |  | | |____ "
	@echo "|_|  \_\______|  |_|  |_|  \_\ \____/   \_____/ / /    \_\_|  |_|______|"
	@echo ""
	@echo "🧰 \033[1mКоманды:\033[0m"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo ""
