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
  code=$(curl -sS --max-time 10 --connect-timeout 5 -o "$tmp" -w "%{http_code}" "$@") || rc=$?
  [[ ${rc:-0} -ne 0 ]] && code=000
  JSON_BODY=$(cat "$tmp" 2>/dev/null || true)
  rm -f "$tmp"
  LAST_HTTP_CODE=$code
}

reset_rate_limit() {
  local endpoint="$1"
  if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM api_rate_limits WHERE endpoint = '$endpoint';" >/dev/null 2>&1 || true)
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
echo "== Spin wheel =="

# Create active test artisan if not exists
curl -s "${BASE_URL}/artisans/register" -X POST -H 'Content-Type: application/json' \
  -d '{"company_name":"Spin Artisan","city_slug":"livry","category_slug":"boulangerie","email":"spin-artisan@example.com","phone":"02 00 00 00 20","password":"spinpass123"}' >/dev/null || true

if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_artisans SET status='active' WHERE email='spin-artisan@example.com';" >/dev/null 2>&1 || true)
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
  # daily limits and previous wins through ON DELETE CASCADE.
  if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
      -e "REPLACE INTO local_users (id, email, session_token, session_exp) VALUES (99999, 'spin-user@example.com', 'test-session-token-12345', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1 || true)
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
AVATAR_DIR="$SCRIPT_DIR/../sites/api/public/avatars/neutral"
MALE_AVATAR_DIR="$SCRIPT_DIR/../sites/api/public/avatars/male"
UPLOAD_DIR="$SCRIPT_DIR/../sites/api/uploads/avatars"

# Ensure the test consumer user exists independently of the spin-wheel tests.
MYSQL_AVAILABLE=0

cleanup_profile_tests() {
  if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
    (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
      -e "DELETE FROM local_user_actions WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_cooldowns WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_badges WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_streaks WHERE user_id = $TEST_USER_ID; DELETE FROM local_users WHERE id = $TEST_USER_ID;") >/dev/null
  fi

  rm -f "$AVATAR_DIR/locked-test.png" "$AVATAR_DIR/locked-test.png.json"
  rm -f "$MALE_AVATAR_DIR/test-gender.png" "$MALE_AVATAR_DIR/test-gender.png.json"
  if [[ -n "$TEST_USER_ID" && -n "$UPLOAD_DIR" ]]; then
    rm -f "$UPLOAD_DIR/${TEST_USER_ID}_"*
  fi
}

trap cleanup_profile_tests EXIT INT TERM

if command -v docker >/dev/null 2>&1 && docker compose ps | grep -q mysql; then
  if (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "SELECT 1;" >/dev/null 2>&1); then
    MYSQL_AVAILABLE=1
  fi
fi

if [[ "$MYSQL_AVAILABLE" -eq 1 ]]; then
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "REPLACE INTO local_users (id, email, session_token, session_exp) VALUES ($TEST_USER_ID, 'profile-user@example.com', '$PROFILE_USER_TOKEN', DATE_ADD(NOW(), INTERVAL 1 DAY));" >/dev/null 2>&1)
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

  echo ""
  echo "== Gamification =="

  # Ensure a clean gamification state for this test user
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_users SET xp=0, level=1 WHERE id=$TEST_USER_ID; DELETE FROM local_user_actions WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_cooldowns WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_badges WHERE user_id = $TEST_USER_ID; DELETE FROM local_user_streaks WHERE user_id = $TEST_USER_ID;" >/dev/null 2>&1)

  # Prepare a magic-link token to exercise consumer auth (which updates the streak)
  (cd "$SCRIPT_DIR/.." && docker compose exec -T mysql mysql -u webiartisan -pwebiartisan_dev webiartisan \
    -e "UPDATE local_users SET magic_token='test-magic-token-$TEST_USER_ID', magic_token_exp=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=$TEST_USER_ID;") >/dev/null 2>&1

  curl_json_status -X POST "${BASE_URL}/users/auth?token=test-magic-token-$TEST_USER_ID" \
    -H "Content-Type: application/json"
  check "$LAST_HTTP_CODE" "POST /users/auth" "200"
  if [[ "$LAST_HTTP_CODE" == "200" ]]; then
    assert_json "$JSON_BODY" "d.get('success')" "consumer auth should succeed"
  fi
  SESSION_TOKEN=$(echo "$JSON_BODY" | python3 -c "import sys,json; print(json.load(sys.stdin).get('token',''))" || true)

  if [[ -n "$SESSION_TOKEN" ]]; then
    echo "--- Test /users/me gamification ---"
    curl_json_status -H "Authorization: Bearer $SESSION_TOKEN" "${BASE_URL}/users/me"
    check "$LAST_HTTP_CODE" "GET /users/me" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "'level' in d.get('data',{}) and d.get('data',{}).get('level') == 1" "user level should be 1"
      assert_json "$JSON_BODY" "'xp' in d.get('data',{}) and isinstance(d.get('data',{}).get('xp'), int)" "user xp should be an integer"
      assert_json "$JSON_BODY" "'current_streak' in d.get('data',{}) and d.get('data',{}).get('current_streak') >= 1" "user streak should be at least 1 after auth"
    fi
    echo "✅ /users/me returns gamification profile"

    echo "--- Test /actions artisan_view ---"
    curl_json_status -X POST -H "Authorization: Bearer $SESSION_TOKEN" -H "Content-Type: application/json" \
      -d '{"action":"artisan_view","resource_key":"artisan:1"}' "${BASE_URL}/actions"
    check "$LAST_HTTP_CODE" "POST /actions artisan_view" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "action should succeed"
      assert_json "$JSON_BODY" "d.get('data',{}).get('xp_gained') == 5" "artisan_view should give 5 XP"
    fi
    echo "✅ /actions artisan_view awards 5 XP"

    echo "--- Test cooldown ---"
    curl_json_status -X POST -H "Authorization: Bearer $SESSION_TOKEN" -H "Content-Type: application/json" \
      -d '{"action":"artisan_view","resource_key":"artisan:1"}' "${BASE_URL}/actions"
    check "$LAST_HTTP_CODE" "POST /actions artisan_view duplicate" "200"
    if [[ "$LAST_HTTP_CODE" == "200" ]]; then
      assert_json "$JSON_BODY" "d.get('success')" "cooldown response should still report success"
      assert_json "$JSON_BODY" "d.get('data',{}).get('xp_gained') == 0" "duplicate action should give 0 XP"
    fi
    echo "✅ duplicate artisan_view is on cooldown"

    echo "--- Test internal action rejection ---"
    curl_json_status -X POST -H "Authorization: Bearer $SESSION_TOKEN" -H "Content-Type: application/json" \
      -d '{"action":"daily_visit"}' "${BASE_URL}/actions"
    check "$LAST_HTTP_CODE" "POST /actions internal action" "400"
    if [[ "$LAST_HTTP_CODE" == "400" ]]; then
      assert_json "$JSON_BODY" "d.get('success') is False" "internal action should be rejected"
    fi
    echo "✅ /actions rejects internal actions"

    echo "--- Test /actions rejects unknown actions ---"
    curl_json_status -X POST -H "Authorization: Bearer $SESSION_TOKEN" -H "Content-Type: application/json" \
      -d '{"action":"nonexistent_action"}' "${BASE_URL}/actions"
    check "$LAST_HTTP_CODE" "POST /actions unknown action" "400"
    if [[ "$LAST_HTTP_CODE" == "400" ]]; then
      assert_json "$JSON_BODY" "d.get('success') is False" "unknown action should be rejected"
    fi
    echo "✅ /actions rejects unknown actions"
  else
    fail "consumer auth did not return a session token"
  fi

  # cleanup_profile_tests is invoked by the EXIT trap
fi

if [[ "$FAILED" -ne 0 ]]; then
  echo "❌ Some API tests failed."
  exit 1
fi

echo "✅ All API integration tests passed."
