# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test \
                frontend-install frontend-dev frontend-build db-setup \
                lint cs-fix phpstan psalm analyse

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash so up and down arrows go to previous commands
	@$(PHP_CONT) bash

test: ## Start tests with Pest, pass the parameter "c=" to add options, example: make test c="--filter calculator"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php vendor/bin/pest $(c)

test-browser: ## Run browser tests (Playwright), pass c= for options
	@$(eval c ?=)
	@$(DOCKER_COMP) exec php vendor/bin/pest tests/Browser/ $(c)

playwright-install: ## Install Playwright browsers inside the PHP container
	@$(DOCKER_COMP) exec php bash -c "npm install playwright@latest && npx playwright install chromium --with-deps"


## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

## —— Database 🗄️  ——————————————————————————————————————————————————————————————
db-setup: ## Create DB schema and run migrations (first run)
	@$(SYMFONY) doctrine:database:create --if-not-exists
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction

## —— Qualité de code 🔍 ————————————————————————————————————————————————————————
lint: ## PHP CS Fixer — vérifie le style sans modifier (rapport diff)
	@$(PHP_CONT) vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes --format=txt

cs-fix: ## PHP CS Fixer — applique les corrections de style
	@$(PHP_CONT) vendor/bin/php-cs-fixer fix --allow-risky=yes

phpstan: ## PHPStan — analyse statique (niveau 5)
	@$(PHP_CONT) vendor/bin/phpstan analyse --memory-limit=512M

psalm: ## Psalm — analyse statique complémentaire
	@$(PHP_CONT) vendor/bin/psalm --no-progress

analyse: lint phpstan psalm ## Lance tous les contrôles qualité

## —— Frontend 🎨 ——————————————————————————————————————————————————————————————
frontend-logs: ## Tail logs du container node (Vite)
	@$(DOCKER_COMP) logs --tail=50 --follow node

frontend-build: ## Build Vue app into public/app/ (dans le container node)
	@$(DOCKER_COMP) exec node npm run build

frontend-sh: ## Shell dans le container node
	@$(DOCKER_COMP) exec node sh
