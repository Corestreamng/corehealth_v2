.PHONY: help up down build restart logs shell test lint audit migrate fresh seed

COMPOSE=docker compose

##@ General
help: ## Display this help message
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Docker
up: ## Start all services in detached mode
	$(COMPOSE) up -d

build: ## Build and start all services
	$(COMPOSE) up -d --build

down: ## Stop all services
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

logs: ## Tail logs from all services
	$(COMPOSE) logs -f

shell: ## Open a shell in the PHP container
	$(COMPOSE) exec php bash

##@ Testing
test: ## Run the full PHPUnit test suite
	$(COMPOSE) exec php vendor/bin/phpunit --testdox

test-local: ## Run tests locally (without Docker)
	vendor/bin/phpunit --testdox

test-coverage: ## Run tests with HTML coverage report
	vendor/bin/phpunit --coverage-html storage/coverage

##@ Code Quality
lint: ## Run PHP CS Fixer in dry-run mode (check only)
	tools/vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php

lint-fix: ## Run PHP CS Fixer and apply all fixes
	tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

audit: ## Run composer and npm security audits
	composer audit || true
	npm audit --audit-level=high || true

##@ Database
migrate: ## Run database migrations inside the Docker container
	$(COMPOSE) exec php php artisan migrate

migrate-local: ## Run database migrations locally
	php artisan migrate

fresh: ## Drop all tables and re-run migrations
	$(COMPOSE) exec php php artisan migrate:fresh

seed: ## Run database seeders
	$(COMPOSE) exec php php artisan db:seed

import-test-db: ## Import test database snapshot (local)
	mysql -h 127.0.0.1 -P 3306 -u root -ppassword _corehealth_db_v2_test < database/dumps/_corehealth_db_v2_test.sql

fresh-setup: ## Perform a clean end-to-end setup (Docker, dependencies, DB, assets)
	$(COMPOSE) up -d
	$(COMPOSE) exec php composer install --no-interaction
	$(COMPOSE) exec php php artisan key:generate --force
	$(COMPOSE) exec php php artisan migrate --seed
	npm ci && npm run dev

##@ Application
key: ## Generate application key
	$(COMPOSE) exec php php artisan key:generate

cache: ## Clear all caches
	$(COMPOSE) exec php php artisan optimize:clear

queue: ## Start queue worker
	$(COMPOSE) exec php php artisan queue:work

tinker: ## Start Tinker REPL
	$(COMPOSE) exec php php artisan tinker
