#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://localhost:8080/api"
FAILED=0

check() {
  local code=$1 desc=$2 expected=$3
  if [[ "$code" == "$expected" ]]; then
    echo "✅ $desc -> $code"
  else
    echo "❌ $desc -> $code (expected $expected)"
    FAILED=1
  fi
}

# Ensure the demo recipe used below is published (previous runs may have archived it)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_recipes SET status='published' WHERE slug='tarte-aux-pommes-normandes';" >/dev/null 2>&1 || true)
fi

echo "== Public endpoints =="
for endpoint in "/" "/cities/livry" "/artisans?city=livry" "/cities/livry/pois" "/cities/livry/schedules" "/prospects?city=livry" "/recipes?city=livry" "/recipes/tarte-aux-pommes-normandes"; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}${endpoint}" || true)
  check "$code" "GET $endpoint" "200"
done

echo ""
echo "== Artisan detail enrichment =="
ARTISAN_JSON=$(curl -s "${BASE_URL}/artisans/1" || true)
if echo "$ARTISAN_JSON" | python3 -c "import sys,json; d=json.load(sys.stdin).get('data',{}); sys.exit(0 if 'recipes' in d and 'nearby' in d else 1)" 2>/dev/null; then
  echo "✅ GET /artisans/1 contains recipes and nearby"
else
  echo "❌ GET /artisans/1 missing recipes or nearby"
  FAILED=1
fi

echo ""
echo "== Admin auth (requires local MySQL) =="
# Create temporary test accounts; ignore conflicts on re-runs
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"Admin Bot","city_slug":"livry","category_slug":"plombier","email":"admin-bot@example.com","phone":"02 00 00 00 10","password":"adminpass123"}' >/dev/null || true
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"User Bot","city_slug":"livry","category_slug":"plombier","email":"user-bot@example.com","phone":"02 00 00 00 11","password":"userpass123"}' >/dev/null || true

# Activate and set admin flag
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_artisans SET status='active', is_admin=1 WHERE email='admin-bot@example.com'; UPDATE local_artisans SET status='active', is_admin=0 WHERE email='user-bot@example.com';" >/dev/null 2>&1 || true)
fi

ADMIN_TOKEN=$(curl -s -X POST "${BASE_URL}/artisans/login" -H 'Content-Type: application/json' \
  -d '{"email":"admin-bot@example.com","password":"adminpass123"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)
USER_TOKEN=$(curl -s -X POST "${BASE_URL}/artisans/login" -H 'Content-Type: application/json' \
  -d '{"email":"user-bot@example.com","password":"userpass123"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

if [[ -n "$ADMIN_TOKEN" && -n "$USER_TOKEN" ]]; then
  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/artisans/me/admin-recipes" || true)
  check "$code" "Admin recipes without token" "401"

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/artisans/me/admin-recipes" -H "X-Artisan-Token: $USER_TOKEN" || true)
  check "$code" "Admin recipes with non-admin" "403"

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/artisans/me/admin-recipes" -H "X-Artisan-Token: $ADMIN_TOKEN" || true)
  check "$code" "Admin recipes with admin" "200"

  FIRST_ID=$(curl -s "${BASE_URL}/artisans/me/admin-recipes" -H "X-Artisan-Token: $ADMIN_TOKEN" | python3 -c "import sys,json; data=json.load(sys.stdin).get('data',[]); print(data[0]['id'] if data else '')" || true)
  if [[ -n "$FIRST_ID" ]]; then
    code=$(curl -s -o /dev/null -w "%{http_code}" -X PUT "${BASE_URL}/artisans/me/admin-recipes/${FIRST_ID}/archive" -H "X-Artisan-Token: $USER_TOKEN" || true)
    check "$code" "Archive recipe with non-admin" "403"

    code=$(curl -s -o /dev/null -w "%{http_code}" -X PUT "${BASE_URL}/artisans/me/admin-recipes/${FIRST_ID}/archive" -H "X-Artisan-Token: $ADMIN_TOKEN" || true)
    check "$code" "Archive recipe with admin" "200"

    # Restore recipe so public endpoint tests remain idempotent across runs
    if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
      (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
        -e "UPDATE local_recipes SET status='published' WHERE id=${FIRST_ID};" >/dev/null 2>&1 || true)
    fi
  fi
fi

echo ""
if [[ "$FAILED" -ne 0 ]]; then
  echo "❌ Some API tests failed."
  exit 1
fi

echo "✅ All API integration tests passed."
