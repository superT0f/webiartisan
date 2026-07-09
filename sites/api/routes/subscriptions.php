<?php
require_once __DIR__ . '/../lib/StripeSubscriptionService.php';
require_once __DIR__ . '/../lib/ArtisanAuth.php';

$artisan = artisan_require_auth($pdo);
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method !== 'POST' && $method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    return;
}

if ($action === 'checkout' && $method === 'POST') {
    handleSubscriptionCheckout($pdo, $artisan, $body);
} elseif ($action === 'portal' && $method === 'POST') {
    handleSubscriptionPortal($pdo, $artisan, $body);
} elseif ($action === 'status' && $method === 'GET') {
    handleSubscriptionStatus($pdo, $artisan);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
}

function handleSubscriptionCheckout(PDO $pdo, array $artisan, array $body): void
{
    $returnUrl = $body['return_url'] ?? ($_SERVER['HTTP_ORIGIN'] ?? '') . '/espace';
    if (!in_array($returnUrl, getAppConfig()['subscription_return_urls'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL de retour invalide']);
        return;
    }

    try {
        $service = new StripeSubscriptionService();
        $url = $service->createCheckoutSession($artisan, $returnUrl);
        echo json_encode(['success' => true, 'url' => $url]);
    } catch (Throwable $e) {
        error_log('[SUBSCRIPTION-CHECKOUT] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du paiement']);
    }
}

function handleSubscriptionPortal(PDO $pdo, array $artisan, array $body): void
{
    if (empty($artisan['stripe_customer_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aucun abonnement actif']);
        return;
    }

    $returnUrl = $body['return_url'] ?? ($_SERVER['HTTP_ORIGIN'] ?? '') . '/espace';
    if (!in_array($returnUrl, getAppConfig()['subscription_return_urls'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'URL de retour invalide']);
        return;
    }

    try {
        $service = new StripeSubscriptionService();
        $url = $service->createPortalSession($artisan['stripe_customer_id'], $returnUrl);
        echo json_encode(['success' => true, 'url' => $url]);
    } catch (Throwable $e) {
        error_log('[SUBSCRIPTION-PORTAL] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'ouverture du portail']);
    }
}

function handleSubscriptionStatus(PDO $pdo, array $artisan): void
{
    echo json_encode([
        'success' => true,
        'data' => [
            'plan' => $artisan['plan'],
            'subscription_status' => $artisan['subscription_status'],
            'subscription_period_end' => $artisan['subscription_period_end'],
        ],
    ]);
}
