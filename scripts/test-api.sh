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

fail() {
  echo "❌ $1"
  FAILED=1
}

assert_json() {
  local json="$1"
  local expr="$2"
  local msg="$3"
  python3 -c "import sys,json; d=json.loads(sys.argv[1]); assert $expr, sys.argv[2]" "$json" "$msg" 2>/dev/null || fail "$msg"
}

# Globals populated by curl_json_status
JSON_BODY=""
LAST_HTTP_CODE=""

curl_json_status() {
  local tmp
  tmp=$(mktemp)
  local code rc
  code=$(curl -sS --max-time 60 --connect-timeout 5 -o "$tmp" -w "%{http_code}" "$@") || rc=$?
  [[ ${rc:-0} -ne 0 ]] && code=000
  JSON_BODY=$(cat "$tmp" 2>/dev/null || true)
  rm -f "$tmp"
  LAST_HTTP_CODE=$code
}

reset_rate_limit() {
  local endpoint="$1"
  if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM api_rate_limits WHERE endpoint = '$endpoint';" >/dev/null 2>&1 || true)
  fi
}

# Ensure the demo recipe used below is published (previous runs may have archived it)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
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
if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
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
    if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
      (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
        -e "UPDATE local_recipes SET status='published' WHERE id=${FIRST_ID};" >/dev/null 2>&1 || true)
    fi
  fi
fi

echo ""
echo "== Spin wheel =="

# Create active test artisan if not exists
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"Spin Artisan","city_slug":"livry","category_slug":"boulangerie","email":"spin-artisan@example.com","phone":"02 00 00 00 20","password":"spinpass123"}' >/dev/null || true

if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_artisans SET status='active', plan='premium' WHERE email='spin-artisan@example.com';" >/dev/null 2>&1 || true)
fi

SPIN_ARTISAN_TOKEN=$(curl -s -X POST "${BASE_URL}/artisans/login" -H 'Content-Type: application/json' \
  -d '{"email":"spin-artisan@example.com","password":"spinpass123"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

if [[ -n "$SPIN_ARTISAN_TOKEN" ]]; then
  # Create offer
  curl -s -X POST "${BASE_URL}/artisans/me/spin-offers" \
    -H "Content-Type: application/json" \
    -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN" \
    -d '{"label":"-10% en magasin","description":"Remise immédiate","stock_total":10}' >/dev/null

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/artisans/me/spin-offers" -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN")
  check "$code" "Artisan spin offers list" "200"

  # Create user and session directly in DB. Use REPLACE so re-runs reset
  # the user row; also explicitly clear spin limits/wins in case the FK
  # does not cascade (defensive against repeated runs the same day).
  if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    SPIN_SESSION_HASH=$(printf '%s' 'test-session-token-12345' | sha256sum | cut -d' ' -f1)
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM local_spin_daily_limits WHERE user_id = 99999; DELETE FROM local_spin_wins WHERE user_id = 99999; REPLACE INTO local_users (id, email, session_token, session_exp) VALUES (99999, 'spin-user@example.com', '$SPIN_SESSION_HASH', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1 || true)
  fi

  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/spin/offers?city=livry")
  check "$code" "Public spin offers" "200"

  SPIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/spin" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer test-session-token-12345" \
    -d '{"city_slug":"livry"}')
  if echo "$SPIN_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); sys.exit(0 if d.get('success') and d.get('data',{}).get('code','').startswith('LIV-') else 1)" 2>/dev/null; then
    echo "✅ POST /spin returns a winning code"
  else
    echo "❌ POST /spin did not return a winning code: $SPIN_RESPONSE"
    FAILED=1
  fi

  WIN_CODE=$(echo "$SPIN_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('code',''))" || true)

  if [[ -n "$WIN_CODE" ]]; then
    code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/artisans/me/spin-wins/${WIN_CODE}/validate" -H "X-Artisan-Token: $SPIN_ARTISAN_TOKEN")
    check "$code" "Validate spin win" "200"
  fi
fi

echo ""
echo "== User profile / avatar =="

TEST_USER_ID=999999
if command -v openssl >/dev/null 2>&1; then
  PROFILE_USER_TOKEN="profile-$(openssl rand -hex 16)"
else
  PROFILE_USER_TOKEN="profile-$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
fi
PROFILE_USER_HASH=$(printf '%s' "$PROFILE_USER_TOKEN" | sha256sum | cut -d' ' -f1)
AVATAR_DIR="$SCRIPT_DIR/../sites/api/public/avatars/neutral"
MALE_AVATAR_DIR="$SCRIPT_DIR/../sites/api/public/avatars/male"
UPLOAD_DIR="$SCRIPT_DIR/../sites/api/uploads/avatars"

# Ensure the test consumer user exists independently of the spin-wheel tests.
MYSQL_AVAILABLE=0

cleanup_profile_tests() {
  if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM local_user_actions WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_cooldowns WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_badges WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_streaks WHERE user_id = $TEST_USER_ID; DELETE FROM local_users WHERE id = $TEST_USER_ID;") >/dev/null 2>&1
  fi

  rm -f "$AVATAR_DIR/locked-test.png" "$AVATAR_DIR/locked-test.png.json"
  rm -f "$MALE_AVATAR_DIR/test-gender.png" "$MALE_AVATAR_DIR/test-gender.png.json"
  if [[ -n "$TEST_USER_ID" && -n "$UPLOAD_DIR" ]]; then
    rm -f "$UPLOAD_DIR/${TEST_USER_ID}_"*
  fi
}

trap cleanup_profile_tests EXIT INT TERM

if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  if (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "SELECT 1;" >/dev/null 2>&1); then
    MYSQL_AVAILABLE=1
  fi
fi

if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "REPLACE INTO local_users (id, email, session_token, session_exp) VALUES ($TEST_USER_ID, 'profile-user@example.com', '$PROFILE_USER_HASH', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1)
fi

echo "--- Test /avatars ---"
curl_json_status "${BASE_URL}/avatars?gender=male"
check "$LAST_HTTP_CODE" "GET /avatars male" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "avatars endpoint should succeed"
  assert_json "$JSON_BODY" "isinstance(d.get('data'), list)" "avatars data should be a list"
  assert_json "$JSON_BODY" "len(d.get('data',[])) > 0" "avatars list should not be empty"
fi
echo "✅ /avatars returns avatars"

echo "--- Test /avatars neutral and invalid gender ---"
curl_json_status "${BASE_URL}/avatars?gender=neutral"
check "$LAST_HTTP_CODE" "GET /avatars neutral gender" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "neutral avatars endpoint should succeed"
fi
curl_json_status "${BASE_URL}/avatars?gender=invalid"
check "$LAST_HTTP_CODE" "GET /avatars invalid gender" "200"
echo "✅ /avatars handles neutral and invalid gender"

if [[ "$MYSQL_AVAILABLE" -eq 0 ]]; then
  echo "⚠️  Skipping user profile/avatar tests (Docker/MySQL unavailable)"
else
  reset_rate_limit 'login'

  mkdir -p "$AVATAR_DIR"
  if [[ -f "$AVATAR_DIR/default.png" ]]; then
    cp "$AVATAR_DIR/default.png" "$AVATAR_DIR/locked-test.png"
    echo '{"id":"locked-test","unlock_level":999}' > "$AVATAR_DIR/locked-test.png.json"
  else
    echo "⚠️  Skipping locked-avatar test: default.png fixture missing"
  fi

  mkdir -p "$MALE_AVATAR_DIR"
  if [[ -f "$MALE_AVATAR_DIR/default.png" ]]; then
    cp "$MALE_AVATAR_DIR/default.png" "$MALE_AVATAR_DIR/test-gender.png"
  elif [[ -f "$AVATAR_DIR/default.png" ]]; then
    cp "$AVATAR_DIR/default.png" "$MALE_AVATAR_DIR/test-gender.png"
  else
    echo "⚠️  Skipping gender-restriction test: default.png fixture missing"
  fi
  if [[ -f "$MALE_AVATAR_DIR/test-gender.png" ]]; then
    echo '{"id":"test-gender","unlock_level":1}' > "$MALE_AVATAR_DIR/test-gender.png.json"
  fi

  echo "--- Test PUT /users/me ---"
  curl_json_status -X PUT -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"display_name":"Test User","avatar_gender":"female"}' "$BASE_URL/users/me"
  check "$LAST_HTTP_CODE" "PUT /users/me profile update" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "profile update should succeed"
    assert_json "$JSON_BODY" "d.get('data',{}).get('display_name') == 'Test User'" "display_name should be updated"
  fi
  echo "✅ PUT /users/me"

  echo "--- Test POST /users/me/avatar (library) ---"
  curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"avatar_id":"default","avatar_url":"/avatars/neutral/default.png"}' "$BASE_URL/users/me/avatar"
  check "$LAST_HTTP_CODE" "POST /users/me/avatar (library)" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "avatar selection should succeed"
    assert_json "$JSON_BODY" "d.get('data',{}).get('avatar_url') == '/avatars/neutral/default.png'" "avatar_url should be updated"
  fi
  echo "✅ POST /users/me/avatar (library)"

  echo "--- Test PUT /users/me with empty display_name ---"
  curl_json_status -X PUT -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"display_name":""}' "$BASE_URL/users/me"
  check "$LAST_HTTP_CODE" "PUT /users/me empty display_name" "400"
  if [[ "$LAST_HTTP_CODE" == "400" ]]; then
    assert_json "$JSON_BODY" "d.get('success') is False" "empty display_name should be rejected"
  fi
  echo "✅ PUT /users/me rejects empty display_name"

  echo "--- Test POST /users/me/avatar with non-image data ---"
  curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"base64_image":"data:text/plain;base64,SGVsbG8gV29ybGQ="}' "$BASE_URL/users/me/avatar"
  check "$LAST_HTTP_CODE" "POST /users/me/avatar non-image data" "400"
  if [[ "$LAST_HTTP_CODE" == "400" ]]; then
    assert_json "$JSON_BODY" "d.get('success') is False" "non-image upload should be rejected"
  fi
  echo "✅ POST /users/me/avatar rejects non-image upload"

  echo "--- Test POST /users/me/avatar with locked avatar ---"
  curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"avatar_id":"locked-test"}' "$BASE_URL/users/me/avatar"
  check "$LAST_HTTP_CODE" "POST /users/me/avatar locked avatar" "403"
  if [[ "$LAST_HTTP_CODE" == "403" ]]; then
    assert_json "$JSON_BODY" "d.get('success') is False" "locked avatar should be rejected"
  fi
  echo "✅ POST /users/me/avatar rejects locked avatar"

  reset_rate_limit 'login'

  echo "--- Test POST /users/me/avatar gender restriction ---"
  if [[ -f "$MALE_AVATAR_DIR/test-gender.png" ]]; then
    curl_json_status -X PUT -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
      -d '{"avatar_gender":"female"}' "$BASE_URL/users/me"
    check "$LAST_HTTP_CODE" "PUT /users/me set gender female" "200"

    curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
      -d '{"avatar_id":"test-gender"}' "$BASE_URL/users/me/avatar"
    check "$LAST_HTTP_CODE" "POST /users/me/avatar gender restriction" "403"
    if [[ "$LAST_HTTP_CODE" == "403" ]]; then
      assert_json "$JSON_BODY" "d.get('success') is False" "male-only avatar should be rejected for female user"
    fi

    curl_json_status -X PUT -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
      -d '{"avatar_gender":"male"}' "$BASE_URL/users/me"
    check "$LAST_HTTP_CODE" "PUT /users/me set gender male" "200"

    curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
      -d '{"avatar_id":"test-gender"}' "$BASE_URL/users/me/avatar"
    check "$LAST_HTTP_CODE" "POST /users/me/avatar gender accept" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "male-only avatar should succeed for male user"
    fi
    echo "✅ POST /users/me/avatar enforces gender restrictions"
  else
    echo "⚠️  Skipping gender-restriction test: fixture missing"
  fi

  # Reset gender so default-avatar URL assertions are deterministic
  curl_json_status -X PUT -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"avatar_gender":"neutral"}' "$BASE_URL/users/me"
  check "$LAST_HTTP_CODE" "PUT /users/me reset gender neutral" "200"

  echo "--- Test POST /users/me/avatar ignores malicious avatar_url ---"
  curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"avatar_id":"default","avatar_url":"/avatars/neutral/malicious.png"}' "$BASE_URL/users/me/avatar"
  check "$LAST_HTTP_CODE" "POST /users/me/avatar ignores malicious avatar_url" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "malicious avatar_url should be ignored"
    assert_json "$JSON_BODY" "d.get('data',{}).get('avatar_url') == '/avatars/neutral/default.png'" "server should return metadata avatar_url"
  fi
  echo "✅ POST /users/me/avatar ignores malicious avatar_url"

  echo "--- Test POST /users/me/avatar (custom upload) ---"
  curl_json_status -X POST -H "Authorization: Bearer $PROFILE_USER_TOKEN" -H "Content-Type: application/json" \
    -d '{"base64_image":"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII="}' "$BASE_URL/users/me/avatar"
  check "$LAST_HTTP_CODE" "POST /users/me/avatar custom upload" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "custom upload should succeed"
  fi
  echo "✅ POST /users/me/avatar accepts custom upload"

  # cleanup_profile_tests is invoked by the EXIT trap
fi

echo ""
echo "== Gamification =="

# Use the same test consumer user as the profile section
GAMIFICATION_USER_ID=999999
if command -v openssl >/dev/null 2>&1; then
  GAMIFICATION_TOKEN="gamification-$(openssl rand -hex 16)"
else
  GAMIFICATION_TOKEN="gamification-$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
fi
GAMIFICATION_HASH=$(printf '%s' "$GAMIFICATION_TOKEN" | sha256sum | cut -d' ' -f1)

if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "REPLACE INTO local_users (id, email, session_token, session_exp) VALUES ($GAMIFICATION_USER_ID, 'gamification-user@example.com', '$GAMIFICATION_HASH', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1)
fi

# Public events list
curl_json_status "${BASE_URL}/gamification/events"
check "$LAST_HTTP_CODE" "GET /gamification/events" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "events endpoint should succeed"
  assert_json "$JSON_BODY" "isinstance(d.get('data'), list)" "events data should be a list"
  assert_json "$JSON_BODY" "any(e.get('key') == 'testimonial_view' for e in d.get('data',[]))" "events should include testimonial_view"
  assert_json "$JSON_BODY" "any(e.get('key') == 'testimonial_post' for e in d.get('data',[]))" "events should include testimonial_post"
  assert_json "$JSON_BODY" "any(e.get('key') == 'game_play' for e in d.get('data',[]))" "events should include game_play"
  assert_json "$JSON_BODY" "any(e.get('key') == 'game_win' for e in d.get('data',[]))" "events should include game_win"
fi

# XP endpoint requires auth
curl_json_status -X POST "${BASE_URL}/gamification/xp" -H 'Content-Type: application/json' -d '{"action":"artisan_view","resource_key":"artisan:1"}'
check "$LAST_HTTP_CODE" "POST /gamification/xp without token" "401"

# XP endpoint records action for authenticated user
if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  curl_json_status -X POST -H "Authorization: Bearer $GAMIFICATION_TOKEN" -H 'Content-Type: application/json' \
    -d '{"action":"artisan_view","resource_key":"artisan:1"}' "${BASE_URL}/gamification/xp"
  check "$LAST_HTTP_CODE" "POST /gamification/xp artisan_view" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "xp record should succeed"
    assert_json "$JSON_BODY" "d.get('data',{}).get('xp_gained') == 5" "artisan_view should give 5 XP"
  fi

  # Second call should be on cooldown (429)
  curl_json_status -X POST -H "Authorization: Bearer $GAMIFICATION_TOKEN" -H 'Content-Type: application/json' \
    -d '{"action":"artisan_view","resource_key":"artisan:1"}' "${BASE_URL}/gamification/xp"
  check "$LAST_HTTP_CODE" "POST /gamification/xp duplicate" "429"
fi

echo ""
echo "== Testimonials & Services =="

# Public catalog
curl_json_status "${BASE_URL}/service-catalog"
check "$LAST_HTTP_CODE" "GET /service-catalog" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "service catalog should succeed"
fi

# Public testimonials list (empty OK)
curl_json_status "${BASE_URL}/testimonials?city=livry"
check "$LAST_HTTP_CODE" "GET /testimonials?city=livry" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "testimonials list should succeed"
fi

# Public templates
curl_json_status "${BASE_URL}/testimonials/templates"
check "$LAST_HTTP_CODE" "GET /testimonials/templates" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "testimonial templates should succeed"
fi

# Authenticated testimonial flow
TESTIMONIALS_USER_ID=999998
TESTIMONIALS_MAGIC_TOKEN="testimonials-magic-token-$TESTIMONIALS_USER_ID"

# Detect MySQL (docker or direct client on the mapped host port)
TESTIMONIALS_MYSQL_AVAILABLE=0
if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  TESTIMONIALS_MYSQL_AVAILABLE=1
  testimonials_mysql() {
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
      --init-command="SET time_zone = '+02:00';" -e "$1")
  }
elif command -v mysql >/dev/null 2>&1 && \
     mysql -h 127.0.0.1 -P 3307 -u webiartisan -pwebiartisan_dev webiartisan \
       --init-command="SET time_zone = '+02:00';" -e "SELECT 1;" >/dev/null 2>&1; then
  TESTIMONIALS_MYSQL_AVAILABLE=1
  testimonials_mysql() {
    mysql -h 127.0.0.1 -P 3307 -u webiartisan -pwebiartisan_dev webiartisan \
      --init-command="SET time_zone = '+02:00';" -e "$1"
  }
fi

cleanup_testimonials() {
  if [[ "${TESTIMONIALS_MYSQL_AVAILABLE:-0}" -eq 1 ]]; then
    testimonials_mysql "
      DELETE FROM local_testimonial_reports WHERE testimonial_id IN (
        SELECT id FROM local_testimonials WHERE user_id = $TESTIMONIALS_USER_ID
      );
      DELETE FROM local_testimonial_media WHERE testimonial_id IN (
        SELECT id FROM local_testimonials WHERE user_id = $TESTIMONIALS_USER_ID
      );
      DELETE FROM local_testimonials WHERE user_id = $TESTIMONIALS_USER_ID;
      DELETE FROM local_users WHERE id = $TESTIMONIALS_USER_ID;
    " >/dev/null 2>&1 || true
  fi
}

# Combine with the existing profile cleanup trap
combined_cleanup() {
  cleanup_profile_tests
  cleanup_testimonials
}
trap combined_cleanup EXIT INT TERM

TESTIMONIALS_SESSION_TOKEN=""
TESTIMONIALS_MAGIC_HASH=$(printf '%s' "$TESTIMONIALS_MAGIC_TOKEN" | sha256sum | cut -d' ' -f1)
if [[ "$TESTIMONIALS_MYSQL_AVAILABLE" -eq 1 ]]; then
  echo "--- Authenticated testimonials ---"
  testimonials_mysql "
    REPLACE INTO local_users (id, email, magic_token, magic_token_exp)
    VALUES ($TESTIMONIALS_USER_ID, 'testimonials-user@example.com', '$TESTIMONIALS_MAGIC_HASH', '2030-01-01 00:00:00');
  " >/dev/null 2>&1

  curl_json_status -X POST "${BASE_URL}/users/auth?token=$TESTIMONIALS_MAGIC_TOKEN" -H "Content-Type: application/json"
  TESTIMONIALS_SESSION_TOKEN=$(echo "$JSON_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)
fi

if [[ -n "$TESTIMONIALS_SESSION_TOKEN" ]]; then
  curl_json_status -X POST -H "Authorization: Bearer $TESTIMONIALS_SESSION_TOKEN" -H "Content-Type: application/json" \
    -d '{"artisan_id":1,"content":"Great service from the test suite!"}' "${BASE_URL}/testimonials"
  check "$LAST_HTTP_CODE" "POST /testimonials" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "posting testimonial should succeed"
  fi

  TESTIMONIAL_ID=$(echo "$JSON_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('id',''))" || true)
  if [[ -n "$TESTIMONIAL_ID" ]]; then
    # Phase 3 : un nouveau témoignage est 'pending' — l'approuver (comme le
    # ferait un admin) avant de tester le marquage utile, réservé aux
    # témoignages visibles publiquement.
    if [[ "$TESTIMONIALS_MYSQL_AVAILABLE" -eq 1 ]]; then
      testimonials_mysql "UPDATE local_testimonials SET status = 'approved' WHERE id = $TESTIMONIAL_ID;" >/dev/null 2>&1 || true
    fi
    curl_json_status -X POST -H "Authorization: Bearer $TESTIMONIALS_SESSION_TOKEN" -H "Content-Type: application/json" \
      "${BASE_URL}/testimonials/${TESTIMONIAL_ID}/helpful"
    check "$LAST_HTTP_CODE" "POST /testimonials/:id/helpful" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "marking helpful should succeed"
    fi
  fi
else
  echo "⚠️  Skipping authenticated testimonial tests (no consumer session)"
fi

echo ""
echo "== Mini-games =="

# Create/activate a mini-games test artisan
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"Games Artisan","city_slug":"livry","category_slug":"boulangerie","email":"games-artisan@example.com","phone":"02 00 00 00 30","password":"gamespass123"}' >/dev/null || true

if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_artisans SET status='active' WHERE email='games-artisan@example.com';" >/dev/null 2>&1 || true)
fi

GAMES_ARTISAN_TOKEN=$(curl -s -X POST "${BASE_URL}/artisans/login" -H 'Content-Type: application/json' \
  -d '{"email":"games-artisan@example.com","password":"gamespass123"}' | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

# Public endpoints
curl_json_status "${BASE_URL}/games/types"
check "$LAST_HTTP_CODE" "GET /games/types" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "game types should succeed"
  assert_json "$JSON_BODY" "all(e.get('key') in ('coupon','wheel') for e in d.get('data',[]))" "game types should only contain coupon and wheel"
fi

curl_json_status "${BASE_URL}/games?city=livry"
check "$LAST_HTTP_CODE" "GET /games?city=livry" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "game list should succeed"
fi

# Authenticated artisan CRUD
if [[ -n "$GAMES_ARTISAN_TOKEN" ]]; then
  curl_json_status -X POST "${BASE_URL}/artisans/me/games" \
    -H "Content-Type: application/json" \
    -H "X-Artisan-Token: $GAMES_ARTISAN_TOKEN" \
    -d '{"game_type_key":"coupon","title":"-10% de réduction","description":"Un coupon de bienvenue","config":{"reveal_text":"Découvrez votre réduction !"}}'
  check "$LAST_HTTP_CODE" "POST /artisans/me/games" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "create game should succeed"
  fi

  GAME_ID=$(echo "$JSON_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('id',''))" || true)

  if [[ -n "$GAME_ID" ]]; then
    curl_json_status "${BASE_URL}/games/${GAME_ID}"
    check "$LAST_HTTP_CODE" "GET /games/:id" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "get game should succeed"
    fi

    curl_json_status -H "X-Artisan-Token: $GAMES_ARTISAN_TOKEN" "${BASE_URL}/artisans/me/games"
    check "$LAST_HTTP_CODE" "GET /artisans/me/games" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "artisan game list should succeed"
    fi

    # Cleanup
    curl -s -o /dev/null -w "%{http_code}" -X DELETE "${BASE_URL}/artisans/me/games/${GAME_ID}" -H "X-Artisan-Token: $GAMES_ARTISAN_TOKEN" >/dev/null || true
  fi
fi

echo ""
echo "== Check-ins =="

CHECKIN_USER_ID=999990
if command -v openssl >/dev/null 2>&1; then
  CHECKIN_TOKEN="checkin-$(openssl rand -hex 16)"
else
  CHECKIN_TOKEN="checkin-$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')"
fi
CHECKIN_HASH=$(printf '%s' "$CHECKIN_TOKEN" | sha256sum | cut -d' ' -f1)

# POST /checkin requires auth
curl_json_status -X POST "${BASE_URL}/checkin" -H 'Content-Type: application/json' \
  -d '{"target_type":"artisan","target_id":1,"lat":49.1081,"lng":-0.7658}'
check "$LAST_HTTP_CODE" "POST /checkin without token" "401"

if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "DELETE FROM local_user_cooldowns WHERE user_id = $CHECKIN_USER_ID;
        DELETE FROM local_user_actions WHERE user_id = $CHECKIN_USER_ID;
        DELETE FROM local_checkins WHERE user_id = $CHECKIN_USER_ID;
        REPLACE INTO local_users (id, email, session_token, session_exp)
        VALUES ($CHECKIN_USER_ID, 'checkin-user@example.com', '$CHECKIN_HASH', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1 || true)

  CHECKIN_ARTISAN=$(cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan -sN \
    -e "SELECT id, latitude, longitude FROM local_artisans WHERE status='active' AND latitude IS NOT NULL LIMIT 1;")
  CHECKIN_ARTISAN_ID=$(echo "$CHECKIN_ARTISAN" | cut -f1)
  CHECKIN_LAT=$(echo "$CHECKIN_ARTISAN" | cut -f2)
  CHECKIN_LNG=$(echo "$CHECKIN_ARTISAN" | cut -f3)

  CHECKIN_POI=$(cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan -sN \
    -e "SELECT id, latitude, longitude FROM local_pois WHERE is_active=1 AND latitude IS NOT NULL LIMIT 1;")
  CHECKIN_POI_ID=$(echo "$CHECKIN_POI" | cut -f1)
  CHECKIN_POI_LAT=$(echo "$CHECKIN_POI" | cut -f2)
  CHECKIN_POI_LNG=$(echo "$CHECKIN_POI" | cut -f3)

  if [[ -n "$CHECKIN_ARTISAN_ID" && -n "$CHECKIN_LAT" ]]; then
    # Unknown target -> 404
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d '{"target_type":"artisan","target_id":999999999,"lat":49.1081,"lng":-0.7658}'
    check "$LAST_HTTP_CODE" "POST /checkin unknown target" "404"

    # Too far (~1.1 km north) -> 422
    FAR_LAT=$(python3 -c "print(float('$CHECKIN_LAT') + 0.01)")
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d "{\"target_type\":\"artisan\",\"target_id\":$CHECKIN_ARTISAN_ID,\"lat\":$FAR_LAT,\"lng\":$CHECKIN_LNG}"
    check "$LAST_HTTP_CODE" "POST /checkin too far" "422"
    if [[ "$LAST_HTTP_CODE" == "422" ]]; then
      assert_json "$JSON_BODY" "d.get('data',{}).get('distance_m',0) > 200" "distance_m should exceed 200"
    fi

    # First check-in of the day -> 100 XP
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d "{\"target_type\":\"artisan\",\"target_id\":$CHECKIN_ARTISAN_ID,\"lat\":$CHECKIN_LAT,\"lng\":$CHECKIN_LNG}"
    check "$LAST_HTTP_CODE" "POST /checkin first of day" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('data',{}).get('xp_awarded') == 100" "first check-in should award 100 XP"
      assert_json "$JSON_BODY" "bool(d.get('data',{}).get('next_spin_at'))" "response should include next_spin_at"
    fi

    # Immediate second check-in -> 429 cooldown
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d "{\"target_type\":\"artisan\",\"target_id\":$CHECKIN_ARTISAN_ID,\"lat\":$CHECKIN_LAT,\"lng\":$CHECKIN_LNG}"
    check "$LAST_HTTP_CODE" "POST /checkin cooldown" "429"

    # Backdate the recharge cooldown by 11 minutes -> 10 XP (daily still consumed)
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
      -e "UPDATE local_user_cooldowns SET last_at = DATE_SUB(NOW(), INTERVAL 11 MINUTE)
          WHERE user_id = $CHECKIN_USER_ID AND action_key = 'poi_spin';" >/dev/null 2>&1 || true)
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d "{\"target_type\":\"artisan\",\"target_id\":$CHECKIN_ARTISAN_ID,\"lat\":$CHECKIN_LAT,\"lng\":$CHECKIN_LNG}"
    check "$LAST_HTTP_CODE" "POST /checkin recharge" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('data',{}).get('xp_awarded') == 10" "recharge check-in should award 10 XP"
    fi

    # Status endpoint lists the artisan with daily_available false
    curl_json_status "${BASE_URL}/checkin/status?lat=$CHECKIN_LAT&lng=$CHECKIN_LNG" \
      -H "Authorization: Bearer $CHECKIN_TOKEN"
    check "$LAST_HTTP_CODE" "GET /checkin/status" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "any(t.get('target_type') == 'artisan' and t.get('target_id') == $CHECKIN_ARTISAN_ID and t.get('daily_available') is False for t in d.get('data',[]))" "status should list the artisan on daily cooldown"
    fi
  else
    echo "⚠️  Skipping artisan check-in tests: no geocoded artisan"
  fi

  if [[ -n "$CHECKIN_POI_ID" && -n "$CHECKIN_POI_LAT" ]]; then
    # POI check-in -> 100 XP
    curl_json_status -X POST "${BASE_URL}/checkin" -H "Authorization: Bearer $CHECKIN_TOKEN" -H 'Content-Type: application/json' \
      -d "{\"target_type\":\"poi\",\"target_id\":$CHECKIN_POI_ID,\"lat\":$CHECKIN_POI_LAT,\"lng\":$CHECKIN_POI_LNG}"
    check "$LAST_HTTP_CODE" "POST /checkin POI" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('data',{}).get('xp_awarded') == 100" "POI check-in should award 100 XP"
    fi
  else
    echo "⚠️  Skipping POI check-in test: no geocoded POI"
  fi

  # Cleanup check-in fixtures
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "DELETE FROM local_user_cooldowns WHERE user_id = $CHECKIN_USER_ID;
        DELETE FROM local_user_actions WHERE user_id = $CHECKIN_USER_ID;
        DELETE FROM local_checkins WHERE user_id = $CHECKIN_USER_ID;
        DELETE FROM local_users WHERE id = $CHECKIN_USER_ID;" >/dev/null 2>&1 || true)
else
  echo "⚠️  Skipping check-in tests (Docker/MySQL unavailable)"
fi

echo ""
echo "== Consumer auth =="

CONSUMER_USER_ID=999997
CONSUMER_EMAIL="consumer-test@example.com"
CONSUMER_MAGIC_TOKEN="consumer-magic-token-$CONSUMER_USER_ID"
CONSUMER_MAGIC_HASH=$(printf '%s' "$CONSUMER_MAGIC_TOKEN" | sha256sum | cut -d' ' -f1)

if command -v docker >/dev/null 2>&1 && docker compose ps 2>/dev/null | grep -q mysql; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "DELETE FROM local_users WHERE email = '$CONSUMER_EMAIL'; DELETE FROM local_user_actions WHERE user_id = $CONSUMER_USER_ID; DELETE FROM local_user_badges WHERE user_id = $CONSUMER_USER_ID; DELETE FROM local_user_streaks WHERE user_id = $CONSUMER_USER_ID;") >/dev/null 2>&1 || true
fi

reset_rate_limit 'login'

curl_json_status -X POST "${BASE_URL}/users/magic-link" \
  -H 'Content-Type: application/json' \
  -H 'Origin: http://localhost:8080' \
  -d "{\"email\":\"$CONSUMER_EMAIL\",\"rememberMe\":true,\"redirect\":\"/carte\"}"
check "$LAST_HTTP_CODE" "POST /users/magic-link" "200"

if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_users SET magic_token = '$CONSUMER_MAGIC_HASH', magic_token_exp = '2030-01-01 00:00:00' WHERE email = '$CONSUMER_EMAIL';") >/dev/null 2>&1 || true
fi

if [[ -n "$CONSUMER_MAGIC_TOKEN" ]]; then
  curl_json_status -X POST "${BASE_URL}/users/auth?token=$CONSUMER_MAGIC_TOKEN&rememberMe=1"
  check "$LAST_HTTP_CODE" "POST /users/auth with rememberMe" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "consumer auth should succeed"
    assert_json "$JSON_BODY" "len(d.get('token','')) > 0" "consumer auth should return token"
  fi

  CONSUMER_SESSION_TOKEN=$(echo "$JSON_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

  if [[ -n "$CONSUMER_SESSION_TOKEN" ]]; then
    curl_json_status "${BASE_URL}/users/me" -H "Authorization: Bearer $CONSUMER_SESSION_TOKEN"
    check "$LAST_HTTP_CODE" "GET /users/me consumer" "200"
  fi
fi

echo ""
echo "== Phase 3 — authz & modération =="

# === Phase 3 — authz & modération ===

PHASE3_RECIPE_TITLE="TEST PHASE3 MODERATION $(date +%s)"

# Nettoyage des recettes de test Phase 3 (ingrédients/étapes supprimés en cascade)
cleanup_phase3() {
  if [[ "${MYSQL_AVAILABLE:-0}" -eq 1 ]]; then
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -h 127.0.0.1 -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM local_recipes WHERE title LIKE 'TEST PHASE3 MODERATION%';" >/dev/null 2>&1 || true)
  fi
}

# Combine with the existing cleanup traps
combined_cleanup() {
  cleanup_profile_tests
  cleanup_testimonials
  cleanup_phase3
}
trap combined_cleanup EXIT INT TERM

# Supprimer les restes d'exécutions interrompues pour garantir l'idempotence
cleanup_phase3

# Le bucket 'login' (10 req/60s) est partagé par register, gamification et
# /admin/* : repartir d'un compteur propre avant les tests de modération.
reset_rate_limit 'login'

# 1. Profil public artisan : pas de colonnes sensibles
curl_json_status "${BASE_URL}/artisans/1"
check "$LAST_HTTP_CODE" "GET /artisans/1 (phase 3)" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "'auth_token_hash' not in json.dumps(d)" "public artisan profile must not expose auth_token_hash"
  assert_json "$JSON_BODY" "'stripe_customer_id' not in json.dumps(d)" "public artisan profile must not expose stripe_customer_id"
fi

# 2. Profil gamification public sans email
#    L'utilisateur 999999 est créé par la section Gamification ci-dessus
curl_json_status "${BASE_URL}/gamification/999999/xp"
if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  check "$LAST_HTTP_CODE" "GET /gamification/999999/xp" "200"
fi
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "'email' not in d.get('data', {})" "public gamification profile must not expose email"
else
  assert_json "$JSON_BODY" "'email' not in json.dumps(d)" "gamification profile error must not leak email"
fi

# 3. Recette créée en pending et invisible publiquement
curl_json_status -X POST "${BASE_URL}/recipes" -H 'Content-Type: application/json' \
  -d "{\"city_slug\":\"livry\",\"title\":\"$PHASE3_RECIPE_TITLE\",\"description\":\"Recette de test Phase 3\",\"ingredients\":[{\"name\":\"Farine\",\"quantity\":100,\"unit\":\"g\"}],\"steps\":[{\"instruction\":\"Melanger tous les ingredients\"}]}"
check "$LAST_HTTP_CODE" "POST /recipes (soumission communautaire)" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "d.get('success')" "recipe submission should succeed"
fi

curl_json_status "${BASE_URL}/recipes?city=livry"
check "$LAST_HTTP_CODE" "GET /recipes?city=livry après soumission" "200"
if [[ "$LAST_HTTP_CODE" == "200" ]]; then
  assert_json "$JSON_BODY" "all(r.get('title') != '$PHASE3_RECIPE_TITLE' for r in d.get('data', []))" "pending recipe must not appear in public list"
fi

# 4. Modération des recettes : authz (401/403) puis approbation
if [[ -n "${ADMIN_TOKEN:-}" && -n "${USER_TOKEN:-}" && "$MYSQL_AVAILABLE" -eq 1 ]]; then
  curl_json_status "${BASE_URL}/admin/moderation/recipes"
  check "$LAST_HTTP_CODE" "GET /admin/moderation/recipes sans token" "401"

  curl_json_status "${BASE_URL}/admin/moderation/recipes" -H "X-Artisan-Token: $USER_TOKEN"
  check "$LAST_HTTP_CODE" "GET /admin/moderation/recipes non-admin" "403"

  curl_json_status "${BASE_URL}/admin/moderation/recipes" -H "X-Artisan-Token: $ADMIN_TOKEN"
  check "$LAST_HTTP_CODE" "GET /admin/moderation/recipes admin" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "any(r.get('title') == '$PHASE3_RECIPE_TITLE' for r in d.get('data', []))" "pending recipe should appear in moderation queue"
    PHASE3_RECIPE_ID=$(echo "$JSON_BODY" | python3 -c "import sys,json; rows=json.load(sys.stdin).get('data',[]); m=[r['id'] for r in rows if r.get('title')==sys.argv[1]]; print(m[0] if m else '')" "$PHASE3_RECIPE_TITLE" 2>/dev/null || true)
  fi

  if [[ -n "${PHASE3_RECIPE_ID:-}" ]]; then
    curl_json_status -X POST "${BASE_URL}/admin/moderation/recipes/${PHASE3_RECIPE_ID}/approve" -H "X-Artisan-Token: $ADMIN_TOKEN"
    check "$LAST_HTTP_CODE" "POST /admin/moderation/recipes/:id/approve" "200"

    curl_json_status "${BASE_URL}/recipes?city=livry"
    check "$LAST_HTTP_CODE" "GET /recipes?city=livry après approbation" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "any(r.get('title') == '$PHASE3_RECIPE_TITLE' for r in d.get('data', []))" "approved recipe should appear in public list"
    fi
  fi
else
  echo "⚠️  Skipping recipe moderation tests (admin fixture or MySQL unavailable)"
fi

# 5. Témoignage créé en pending : invisible publiquement, réponse explicite
if [[ -n "${TESTIMONIALS_SESSION_TOKEN:-}" ]]; then
  PHASE3_TESTIMONIAL_CONTENT="TEST PHASE3 temoignage en attente de moderation"
  curl_json_status -X POST -H "Authorization: Bearer $TESTIMONIALS_SESSION_TOKEN" -H "Content-Type: application/json" \
    -d "{\"artisan_id\":1,\"content\":\"$PHASE3_TESTIMONIAL_CONTENT\"}" "${BASE_URL}/testimonials"
  check "$LAST_HTTP_CODE" "POST /testimonials (phase 3)" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "testimonial submission should succeed"
    assert_json "$JSON_BODY" "'après validation' in d.get('data', {}).get('message', '')" "testimonial response should mention moderation"
  fi

  curl_json_status "${BASE_URL}/testimonials?city=livry"
  check "$LAST_HTTP_CODE" "GET /testimonials?city=livry (phase 3)" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "all(t.get('content') != '$PHASE3_TESTIMONIAL_CONTENT' for t in d.get('data', []))" "pending testimonial must not appear in public list"
  fi
else
  echo "⚠️  Skipping pending testimonial test (no consumer session)"
fi

# 6. Rate limit non contournable par XFF forgé — vérification statique
#    (la stack docker de dev n'a pas de reverse proxy de confiance en amont,
#    le comportement XFF ne peut pas y être reproduit par un test HTTP)
RATE_LIMIT_FILE="$SCRIPT_DIR/../sites/api/middleware/RateLimit.php"
if [[ -f "$RATE_LIMIT_FILE" ]] && grep -q 'function clientIp' "$RATE_LIMIT_FILE"; then
  echo "✅ clientIp() est défini dans middleware/RateLimit.php"
else
  fail "clientIp() introuvable dans middleware/RateLimit.php"
fi
if [[ -f "$RATE_LIMIT_FILE" ]] && grep -A10 'function applyRateLimit' "$RATE_LIMIT_FILE" | grep -q 'clientIp()'; then
  echo "✅ applyRateLimit() utilise clientIp()"
else
  fail "applyRateLimit() n'utilise pas clientIp()"
fi

# Nettoyage immédiat de la recette approuvée (le trap couvre les interruptions)
cleanup_phase3

if [[ "$FAILED" -ne 0 ]]; then
  echo "❌ Some API tests failed."
  exit 1
fi

echo "✅ All API integration tests passed."
