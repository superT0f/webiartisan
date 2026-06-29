.PHONY: help up down migrate seed build dev test-api push-livry build-combs push-combs build-vsd push-vsd deploy-all

APP_VERSION := $(shell node -p "require('./package.json').version" 2>/dev/null || echo 1.1.0)

help:
	@echo "WebiArtisan — Multi-villes"
	@echo "  make up              Start Docker Compose"
	@echo "  make down            Stop Docker Compose"
	@echo "  make migrate         Run SQL migrations"
	@echo "  make seed            Insert Livry demo data"
	@echo "  make dev             Start Vite dev server"
	@echo "  make build           Build frontend for Livry"
	@echo "  make test-api        Run API smoke tests"
	@echo "  make push-livry      Deploy Livry to Gandi"
	@echo "  make build-combs     Build Combs-la-Ville frontend"
	@echo "  make push-combs      Deploy Combs-la-Ville to Gandi"
	@echo "  make build-vsd       Build Vert-Saint-Denis frontend"
	@echo "  make push-vsd        Deploy Vert-Saint-Denis to Gandi"
	@echo "  make deploy-all      Build & push Livry, Combs and VSD"

up:
	@docker compose up -d --build
	@echo "✅ Stack running on http://localhost"

down:
	@docker compose down

migrate:
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/025_artisans_local.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/026_b2b_recipes.sql
	@docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan < sites/api/migrations/027_spin_wheel.sql
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
