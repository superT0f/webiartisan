.PHONY: help up down migrate seed build dev test-api push-livry

help:
	@echo "WebiArtisan — POC Livry"
	@echo "  make up          Start Docker Compose"
	@echo "  make down        Stop Docker Compose"
	@echo "  make migrate     Run SQL migrations"
	@echo "  make seed        Insert Livry demo data"
	@echo "  make dev         Start Vite dev server"
	@echo "  make build       Build frontend for production"
	@echo "  make test-api    Run API smoke tests"
	@echo "  make push-livry  Deploy Livry to Gandi"

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
	@docker compose run --rm node sh -c "cd /app/sites/webiartisan-livry && npm run build"

test-api:
	@bash scripts/test-api.sh

push-livry:
	@$(MAKE) -C sites/webiartisan-livry push
