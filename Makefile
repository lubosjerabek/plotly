.PHONY: up build down reset deploy check test test-file logs shell help

COMPOSE  = docker-compose
APP      = plotly-app
PYTEST   = ./venv/bin/pytest
TESTS    = tests

# ── Stack ─────────────────────────────────────────────────────────────────────

up: ## Start the stack without rebuilding the image
	$(COMPOSE) up -d

build: ## Rebuild the Docker image and start the stack
	$(COMPOSE) up --build -d

down: ## Stop the stack
	$(COMPOSE) down

reset: ## Stop the stack and delete all data volumes (clean slate)
	$(COMPOSE) down -v

# ── Deploy ────────────────────────────────────────────────────────────────────
# Fast path: copy PHP source directly into the running container.
# No image rebuild required — useful when iterating on PHP / templates / lang.
# Requires the stack to be running (`make up` or `make build` first).

deploy: ## Copy PHP source into the running container (fast, no rebuild)
	docker cp index.php   $(APP):/var/www/html/
	docker cp config.php  $(APP):/var/www/html/
	docker cp favicon.php $(APP):/var/www/html/
	docker cp templates   $(APP):/var/www/html/
	docker cp lang        $(APP):/var/www/html/
	docker exec $(APP) chown -R www-data:www-data /var/www/html
	@echo "deployed"

# ── Tests ─────────────────────────────────────────────────────────────────────

test: ## Run the full test suite (stack must be running)
	$(PYTEST) $(TESTS) -v

test-file: ## Run one test file: make test-file FILE=tests/test_ics.py
	$(PYTEST) $(FILE) -v

check: deploy test ## Deploy current source then run the full test suite

# ── Ops ───────────────────────────────────────────────────────────────────────

logs: ## Tail the app container logs
	$(COMPOSE) logs -f app

shell: ## Open a shell inside the app container
	docker exec -it $(APP) bash

# ── Help ──────────────────────────────────────────────────────────────────────

help: ## List all targets with descriptions
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
