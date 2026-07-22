.PHONY: help up down migrate seed build dev test-api push-api push-app push-livry build-combs push-combs build-vsd push-vsd deploy-all console console-exec e2e-test e2e-dashboard-dev push-e2e mysql db-apply test-php test-php-all e2e e2e-all

APP_VERSION := $(shell cat .version 2>/dev/null || echo 2.0.1)

help:
	@echo "WebiArtisan — Multi-villes"
	@echo "  make up              Start Docker Compose"
	@echo "  make down            Stop Docker Compose"
	@echo "  make migrate         Run SQL migrations"
	@echo "  make seed            Insert Livry demo data"
	@echo "  make dev             Start Vite dev server"
	@echo "  make build           Build frontend for Livry"
	@echo "  make test-api        Run API smoke tests"
	@echo "  make push-api        Push API PHP to Gandi"
	@echo "  make push-app        Deploy app.prigent.tech landing page"
	@echo "  make push-livry      Deploy Livry to Gandi"
	@echo "  make build-combs     Build Combs-la-Ville frontend"
	@echo "  make push-combs      Deploy Combs-la-Ville to Gandi"
	@echo "  make build-vsd       Build Vert-Saint-Denis frontend"
	@echo "  make push-vsd        Deploy Vert-Saint-Denis to Gandi"
	@echo "  make deploy-all      Build & push Livry, Combs and VSD"
	@echo "  make console         Open Gandi gPaas emergency console (SSH)"
	@echo "  make console-exec CMD='...'  Run one command on the emergency console"
	@echo ""
	@echo "  — Local dev (docker compose exec, marche sous podman) —"
	@echo "  make mysql           Shell MySQL interactif (conteneur)"
	@echo "  make db-apply FILE=sites/api/migrations/042_artisan_sessions.sql"
	@echo "                       Applique un fichier SQL via le conteneur mysql"
	@echo "  make test-php FILE=test_objects.php"
	@echo "                       Lance un test PHP de sites/api/tests"
	@echo "  make test-php-all    Lance tous les tests sites/api/tests/test_*.php"
	@echo "  make e2e FILE=game-objects-test.cjs"
	@echo "                       Lance un test e2e puppeteer (stack + vite dev requis)"
	@echo "  make e2e-all         Lance les 3 e2e jeu (map, objets, cadeaux)"

up:
	@docker compose up -d --build
	@echo "✅ Stack running on http://localhost"

down:
	@docker compose down

migrate:
	@bash scripts/migrate.sh
	@echo "✅ Migrations applied"

seed:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < data/seeds/livry.sql
	@echo "✅ Livry seed applied"

dev:
	@docker compose logs -f node

build:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-livry && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=livry VITE_CITY_NAME=Livry VITE_CITY_LAT=49.1081 VITE_CITY_LNG=-0.7658 VITE_CITY_CP=14240 VITE_APP_VERSION=$(APP_VERSION) npm run build"

test-api:
	@bash scripts/test-api.sh

push-api:
	@$(MAKE) -C sites/api push

push-app:
	@$(MAKE) -C sites/app-landing push

push-livry:
	@VITE_APP_VERSION=$(APP_VERSION) $(MAKE) -C sites/webiartisan-livry push

build-combs:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-combs && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=combs-la-ville VITE_CITY_NAME=Combs-la-Ville VITE_CITY_LAT=48.6614 VITE_CITY_LNG=2.5628 VITE_CITY_CP=77380 VITE_APP_VERSION=$(APP_VERSION) npm run build"

push-combs:
	@VITE_APP_VERSION=$(APP_VERSION) $(MAKE) -C sites/webiartisan-combs push

build-vsd:
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-vert-saint-denis && VITE_API_URL=https://api.prigent.tech VITE_CITY_SLUG=vert-saint-denis VITE_CITY_NAME=Vert-Saint-Denis VITE_CITY_LAT=48.5644 VITE_CITY_LNG=2.6186 VITE_CITY_CP=77240 VITE_APP_VERSION=$(APP_VERSION) npm run build"

push-vsd:
	@VITE_APP_VERSION=$(APP_VERSION) $(MAKE) -C sites/webiartisan-vert-saint-denis push

deploy-all: build push-livry build-combs push-combs build-vsd push-vsd

# --- Local dev (docker compose exec — fonctionne sous podman, pas de client
# --- mysql/php requis sur l'hôte) -------------------------------------------

mysql: ## Shell MySQL interactif dans le conteneur
	@docker compose exec mysql mysql -h 127.0.0.1 -uwebiartisan -pwebiartisan_dev webiartisan

db-apply: ## Applique un fichier SQL : make db-apply FILE=sites/api/migrations/XXX.sql
	@test -n "$(FILE)" || (echo "usage: make db-apply FILE=sites/api/migrations/XXX.sql" && exit 1)
	@docker compose exec -T mysql mysql -h 127.0.0.1 -uwebiartisan -pwebiartisan_dev webiartisan < $(FILE) && echo "✅ $(FILE) appliqué"

test-php: ## Lance un test PHP : make test-php FILE=test_objects.php
	@test -n "$(FILE)" || (echo "usage: make test-php FILE=test_objects.php" && exit 1)
	@docker compose exec -T php php /var/www/api/tests/$(FILE)

test-php-all: ## Lance tous les tests PHP de sites/api/tests
	@for f in sites/api/tests/test_*.php; do \
		echo "== $$(basename $$f)"; \
		docker compose exec -T php php /var/www/api/tests/$$(basename $$f) || exit 1; \
	done && echo "✅ Tous les tests PHP passent"

e2e: ## Lance un e2e puppeteer (stack + vite dev requis) : make e2e FILE=game-objects-test.cjs
	@test -n "$(FILE)" || (echo "usage: make e2e FILE=game-objects-test.cjs" && exit 1)
	@cd e2e && node $(FILE)

e2e-all: ## Lance les 3 e2e jeu (map, objets, cadeaux artisans)
	@cd e2e && node game-map-test.cjs && node game-objects-test.cjs && node artisan-gifts-test.cjs && echo "✅ Tous les e2e passent"

console:
	@echo "Admin web (Gandi Simple Hosting):"
	@echo "  https://admin.gandi.net/simplehosting/94bfdc39-ba8a-46ad-aff8-633112a25dfc/instances/c82ad26c-00cd-11ea-81f0-00163e108e85/administration"
	@python3 scripts/gpaas-console.py

console-exec:
	@python3 scripts/gpaas-console.py exec '$(CMD)'

e2e-test:
	@cd e2e && npm run test:prod

e2e-dashboard-dev:
	@cd e2e && npm run dashboard

push-e2e:
	@cd e2e/dashboard && npm run build
	@rsync -avz e2e/dashboard/dist/ sites/e2e-dashboard/htdocs/
	@$(MAKE) -C sites/e2e-dashboard push
