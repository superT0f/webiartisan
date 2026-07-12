#!/usr/bin/env bash
set -euo pipefail

# Run all SQL migrations in sites/api/migrations/ in numeric order.
# Usage:
#   scripts/migrate.sh              # uses docker compose mysql service
#   DB_HOST=localhost DB_USER=... DB_PASS=... DB_NAME=... scripts/migrate.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MIGRATIONS_DIR="${SCRIPT_DIR}/../sites/api/migrations"

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-webiartisan}"
DB_USER="${DB_USER:-webiartisan}"
DB_PASS="${DB_PASS:-webiartisan_dev}"

if ! ls "${MIGRATIONS_DIR}"/*.sql >/dev/null 2>&1; then
  echo "❌ No migration files found in ${MIGRATIONS_DIR}"
  exit 1
fi

# Ensure MySQL is reachable
wait_for_mysql() {
  local retries=30
  while [[ $retries -gt 0 ]]; do
    if mysqladmin -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" ping >/dev/null 2>&1; then
      return 0
    fi
    retries=$((retries - 1))
    sleep 1
  done
  echo "❌ Could not connect to MySQL at ${DB_HOST}:${DB_PORT}"
  return 1
}

wait_for_mysql

# Run migrations in numeric order
for migration in $(ls -1 "${MIGRATIONS_DIR}"/*.sql | sort); do
  echo "▶ Applying $(basename "${migration}")"
  mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${migration}"
done

echo "✅ All migrations applied"
