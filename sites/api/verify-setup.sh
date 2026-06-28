#!/bin/bash
echo "=== WebIArtisan Client Portal Setup Verification ==="
echo

# Check if all required files exist
echo "1. Checking required files..."

files=(
    "migrations/017_client_portal_devis_tokens.sql"
    "migrations/018_client_portal_payments.sql"
    "routes/client-public.php"
    "routes/gestion.php"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file exists"
    else
        echo "   ❌ $file missing"
    fi
done

echo
echo "2. Checking PHP syntax..."

php_files=(
    "routes/client-public.php"
    "routes/gestion.php"
)

for file in "${php_files[@]}"; do
    if php -l "$file" > /dev/null 2>&1; then
        echo "   ✅ $file syntax OK"
    else
        echo "   ❌ $file syntax error"
        php -l "$file"
    fi
done

echo
echo "3. Checking Vue components..."

vue_components=(
    "../web/builder/src/views/ClientPortal.vue"
    "../web/builder/src/components/client/ClientHeader.vue"
    "../web/builder/src/components/client/DevisTab.vue"
    "../web/builder/src/components/client/SignatureCanvas.vue"
    "../web/builder/src/components/client/ChantierTab.vue"
    "../web/builder/src/components/client/PaiementTab.vue"
)

for component in "${vue_components[@]}"; do
    if [ -f "$component" ]; then
        echo "   ✅ $(basename $component) exists"
    else
        echo "   ❌ $(basename $component) missing"
    fi
done

echo
echo "4. Checking router configuration..."

if grep -q "ClientPortal" "../web/builder/src/router/index.js"; then
    echo "   ✅ ClientPortal route added"
else
    echo "   ❌ ClientPortal route missing"
fi

if grep -q "client.*token" "../web/builder/src/router/index.js"; then
    echo "   ✅ Client token route pattern configured"
else
    echo "   ❌ Client token route pattern missing"
fi

echo
echo "5. Checking API route configuration..."

if grep -q "'client'" "index.php"; then
    echo "   ✅ Client route added to public routes"
else
    echo "   ❌ Client route missing from public routes"
fi

if grep -q "'client-links'" "index.php"; then
    echo "   ✅ Client-links route added to protected routes"
else
    echo "   ❌ Client-links route missing from protected routes"
fi

echo
echo "6. Checking webhook configuration..."

if grep -q "checkout.session.completed" "routes/webhooks.php"; then
    echo "   ✅ Stripe checkout webhook configured"
else
    echo "   ❌ Stripe checkout webhook missing"
fi

echo
echo "7. Checking frontend build..."

cd "../web/builder"
if npm run build > /dev/null 2>&1; then
    echo "   ✅ Frontend builds successfully"
else
    echo "   ❌ Frontend build failed"
fi

echo
echo "=== Summary ==="
echo "Client portal setup verification complete!"
echo
echo "Next steps:"
echo "1. Run the database migrations:"
echo "   mysql -u username -p database_name < migrations/017_client_portal_devis_tokens.sql"
echo "   mysql -u username -p database_name < migrations/018_client_portal_payments.sql"
echo
echo "2. Configure Stripe keys in .env file"
echo
echo "3. Test the endpoints:"
echo "   - GET /api/client/{token}"
echo "   - POST /api/client/{token}/sign"
echo "   - POST /api/client/{token}/pay"
echo
echo "4. Access the client portal:"
echo "   - Generate a token via POST /api/gestion/devis/{id} (action: send-client-link)"
echo "   - Visit https://web.prigent.tech/client/{token}"
