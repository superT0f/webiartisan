#!/bin/bash
# ============================================
# WebIArtisan API Tests — Brand Center
# Run: ./sites/api/tests/brand_test.sh
# ============================================

set -e

API_URL="https://api.prigent.tech"
TEST_EMAIL="test_$(date +%s)@example.com"
TEST_PASSWORD="test1234"
TEST_COMPANY="Test Company $(date +%s)"

echo "=========================================="
echo "  Brand Center API Tests"
echo "=========================================="
echo ""

# 1. Register new user
echo "1. Testing auth/register..."
REGISTER_RESPONSE=$(curl -s -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASSWORD\",\"company_name\":\"$TEST_COMPANY\"}")

if echo "$REGISTER_RESPONSE" | grep -q '"success":true'; then
    echo "   ✅ Register OK"
    TOKEN=$(echo "$REGISTER_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
    TENANT_ID=$(echo "$REGISTER_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['user']['tenant_id'])")
else
    echo "   ❌ Register failed:"
    echo "$REGISTER_RESPONSE"
    exit 1
fi

# 2. Get brand (should return defaults)
echo "2. Testing brand/get (defaults)..."
BRAND_RESPONSE=$(curl -s -X GET "$API_URL/brand/get" \
  -H "Authorization: Bearer $TOKEN")

if echo "$BRAND_RESPONSE" | grep -q '"success":true'; then
    COMPANY_NAME=$(echo "$BRAND_RESPONSE" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['data']['company_name'])")
    if [ "$COMPANY_NAME" = "$TEST_COMPANY" ]; then
        echo "   ✅ Brand get returns correct company name"
    else
        echo "   ⚠️ Company name mismatch: expected '$TEST_COMPANY', got '$COMPANY_NAME'"
    fi
else
    echo "   ❌ Brand get failed"
    echo "$BRAND_RESPONSE"
    exit 1
fi

# 3. Save brand
echo "3. Testing brand/save..."
SAVE_RESPONSE=$(curl -s -X POST "$API_URL/brand/save" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "company_name":"Updated Company",
    "slogan":"Nouveau slogan test",
    "colors":{"primary":"#ff6600","secondary":"#004e92"},
    "style":{"font":"Montserrat","tone":"friendly"},
    "contact":{"phone":"01 23 45 67 89","email":"test@test.com","address":"123 Rue Test"}
  }')

if echo "$SAVE_RESPONSE" | grep -q '"success":true'; then
    echo "   ✅ Brand save OK"
else
    echo "   ❌ Brand save failed"
    echo "$SAVE_RESPONSE"
    exit 1
fi

# 4. Verify saved brand
echo "4. Testing brand/get (after save)..."
BRAND_RESPONSE2=$(curl -s -X GET "$API_URL/brand/get" \
  -H "Authorization: Bearer $TOKEN")

SLOGAN=$(echo "$BRAND_RESPONSE2" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['data']['slogan'])")
PRIMARY_COLOR=$(echo "$BRAND_RESPONSE2" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['data']['colors']['primary'])")

if [ "$SLOGAN" = "Nouveau slogan test" ] && [ "$PRIMARY_COLOR" = "#ff6600" ]; then
    echo "   ✅ Brand correctly saved and retrieved"
else
    echo "   ⚠️ Brand data mismatch"
    echo "   Expected slogan: 'Nouveau slogan test', got: '$SLOGAN'"
    echo "   Expected primary: '#ff6600', got: '$PRIMARY_COLOR'"
fi

# 5. Test without auth (should fail)
echo "5. Testing brand/get without auth..."
UNAUTH_RESPONSE=$(curl -s -X GET "$API_URL/brand/get" -w "\nHTTP_CODE:%{http_code}")

if echo "$UNAUTH_RESPONSE" | grep -q "HTTP_CODE:401"; then
    echo "   ✅ Correctly rejects unauthenticated request"
else
    echo "   ⚠️ Expected 401, got different response"
fi

echo ""
echo "=========================================="
echo "  All tests completed!"
echo "  Test user: $TEST_EMAIL"
echo "  Tenant ID: $TENANT_ID"
echo "=========================================="
