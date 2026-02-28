# =============================================================================
# Onesiforo — Docker Development Commands
# =============================================================================
# Usage: make <target>
# Run `make help` to see all available commands.
# =============================================================================

.PHONY: help setup up down restart build logs \
        shell tinker \
        test lint analyse \
        migrate seed fresh \
        queue-restart reverb-restart \
        npm-install npm-build \
        clean

# Default target
.DEFAULT_GOAL := help

# Docker compose command
COMPOSE := docker compose

# ---------------------------------------------------------------------------
# Setup & Lifecycle
# ---------------------------------------------------------------------------

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

setup: ## First-time setup: build, start, install dependencies, migrate, build frontend
	@echo "============================================"
	@echo "  Onesiforo — First Time Setup"
	@echo "============================================"
	@if [ ! -f .env ]; then \
		echo "Copying .env.docker to .env..."; \
		cp .env.docker .env; \
	fi
	@if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then \
		echo "Generating APP_KEY..."; \
		KEY=$$(php -r "echo 'base64:' . base64_encode(random_bytes(32));"); \
		sed -i.bak "s|^APP_KEY=.*|APP_KEY=$$KEY|" .env && rm -f .env.bak; \
	fi
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "============================================"
	@echo "  Setup complete!"
	@echo "  App:     http://localhost:8000"
	@echo "  Vite:    http://localhost:5174"
	@echo "  Mailpit: http://localhost:8026"
	@echo "  Reverb:  ws://localhost:8085"
	@echo "============================================"

up: ## Start all services
	$(COMPOSE) up -d

down: ## Stop all services
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) down
	$(COMPOSE) up -d

build: ## Rebuild all Docker images
	$(COMPOSE) build --no-cache

logs: ## Tail logs from all services
	$(COMPOSE) logs -f

logs-%: ## Tail logs from a specific service (e.g., make logs-app)
	$(COMPOSE) logs -f $*

# ---------------------------------------------------------------------------
# Shell Access
# ---------------------------------------------------------------------------

shell: ## Open a shell in the app container
	$(COMPOSE) exec app sh

tinker: ## Open Laravel Tinker
	$(COMPOSE) exec app php artisan tinker

# ---------------------------------------------------------------------------
# Testing & Code Quality
# ---------------------------------------------------------------------------

test: ## Run Pest tests
	$(COMPOSE) exec app php artisan test --compact

test-filter: ## Run filtered tests (usage: make test-filter F=testName)
	$(COMPOSE) exec app php artisan test --compact --filter=$(F)

lint: ## Run Laravel Pint code formatter
	$(COMPOSE) exec app vendor/bin/pint --dirty --format agent

lint-fix: ## Run Laravel Pint on all files
	$(COMPOSE) exec app vendor/bin/pint --format agent

analyse: ## Run PHPStan static analysis
	$(COMPOSE) exec app vendor/bin/phpstan analyse --memory-limit=2G

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

migrate: ## Run database migrations
	$(COMPOSE) exec app php artisan migrate --no-interaction

seed: ## Run database seeders
	$(COMPOSE) exec app php artisan db:seed --no-interaction

fresh: ## Drop all tables and re-run migrations + seeders
	$(COMPOSE) exec app php artisan migrate:fresh --seed --no-interaction

# ---------------------------------------------------------------------------
# Service Management
# ---------------------------------------------------------------------------

queue-restart: ## Restart the queue worker
	$(COMPOSE) restart queue

reverb-restart: ## Restart the Reverb WebSocket server
	$(COMPOSE) restart reverb

# ---------------------------------------------------------------------------
# Node / Frontend
# ---------------------------------------------------------------------------

npm-install: ## Install Node.js dependencies
	$(COMPOSE) exec vite npm install

npm-build: ## Build frontend assets for production
	$(COMPOSE) exec vite npm run build

# ---------------------------------------------------------------------------
# Cleanup
# ---------------------------------------------------------------------------

clean: ## Stop services and remove volumes (WARNING: destroys data)
	$(COMPOSE) down -v --remove-orphans
