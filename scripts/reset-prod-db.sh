#!/usr/bin/env bash
# Reset production database for Livry POC.
# WARNING: this drops all local_* tables and recreates them from scratch.
set -euo pipefail

if [ "$#" -lt 4 ]; then
    echo "Usage: $0 <host> <user> <password> <database>"
    exit 1
fi

HOST=$1
USER=$2
PASS=$3
DB=$4

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MYSQL="mysql -h $HOST -u $USER -p$PASS $DB"

echo "⚠️  This will DROP all local_* tables in $DB@$HOST and recreate them from seeds."
read -p "Are you sure? (yes/no) " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo "🗑️  Dropping local tables..."
$MYSQL -e "
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS local_recipe_reports;
DROP TABLE IF EXISTS local_recipe_artisans;
DROP TABLE IF EXISTS local_recipe_steps;
DROP TABLE IF EXISTS local_recipe_ingredients;
DROP TABLE IF EXISTS local_recipes;
DROP TABLE IF EXISTS local_prospect_follow_ups;
DROP TABLE IF EXISTS local_prospects;
DROP TABLE IF EXISTS local_schedules;
DROP TABLE IF EXISTS local_pois;
DROP TABLE IF EXISTS local_reviews;
DROP TABLE IF EXISTS local_services;
DROP TABLE IF EXISTS local_artisans;
DROP TABLE IF EXISTS local_categories;
DROP TABLE IF EXISTS local_cities;
SET FOREIGN_KEY_CHECKS = 1;
"

echo "🏗️  Running migrations and seeds..."
$MYSQL < "$ROOT_DIR/sites/api/migrations/025_artisans_local.sql"
$MYSQL < "$ROOT_DIR/sites/api/migrations/026_b2b_recipes.sql"
$MYSQL < "$ROOT_DIR/data/seeds/livry.sql"
$MYSQL < "$ROOT_DIR/data/seeds/livry_prospects.sql"
$MYSQL < "$ROOT_DIR/data/seeds/livry_recipes.sql"

echo "✅ Database reset complete."
