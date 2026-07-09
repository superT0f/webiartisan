<?php
require_once __DIR__ . '/../lib/StripeSubscriptionService.php';

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    return;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!$signature) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature manquante']);
    return;
}

try {
    $service = new StripeSubscriptionService();
    $event = $service->constructEvent($payload, $signature);
} catch (Throwable $e) {
    error_log('[STRIPE-WEBHOOK] Signature invalid: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Signature invalide']);
    return;
}

$eventId = $event->id;
$existing = $pdo->prepare("SELECT id FROM artisan_subscription_events WHERE stripe_event_id = ?");
$existing->execute([$eventId]);
if ($existing->fetch()) {
    echo json_encode(['received' => true, 'duplicate' => true]);
    return;
}

$pdo->prepare("
    INSERT INTO artisan_subscription_events (artisan_id, stripe_event_id, event_type, payload)
    VALUES (?, ?, ?, ?)
")->execute([
    0,
    $eventId,
    $event->type,
    json_encode($event->data->object->toArray()),
]);

switch ($event->type) {
    case 'checkout.session.completed':
        handleCheckoutSessionCompleted($pdo, $event->data->object);
        break;
    case 'customer.subscription.updated':
    case 'customer.subscription.deleted':
        handleSubscriptionUpdated($pdo, $event->data->object);
        break;
}

echo json_encode(['received' => true]);

function handleCheckoutSessionCompleted(PDO $pdo, \Stripe\Checkout\Session $session): void
{
    $metadata = $session->metadata->toArray();
    $artisanId = (int)($metadata['artisan_id'] ?? 0);
    if (!$artisanId) return;

    $customerId = $session->customer;
    $subscriptionId = $session->subscription;

    if (!$subscriptionId) return;

    $subscription = \Stripe\Subscription::retrieve($subscriptionId);

    $pdo->prepare("
        UPDATE local_artisans
        SET plan = 'premium',
            stripe_customer_id = ?,
            stripe_subscription_id = ?,
            subscription_status = ?,
            subscription_period_end = FROM_UNIXTIME(?)
        WHERE id = ?
    ")->execute([
        $customerId,
        $subscriptionId,
        $subscription->status,
        $subscription->current_period_end,
        $artisanId,
    ]);
}

function handleSubscriptionUpdated(PDO $pdo, \Stripe\Subscription $subscription): void
{
    $stmt = $pdo->prepare("SELECT id FROM local_artisans WHERE stripe_subscription_id = ?");
    $stmt->execute([$subscription->id]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$artisan) return;

    $plan = $subscription->status === 'active' ? 'premium' : 'free';

    $pdo->prepare("
        UPDATE local_artisans
        SET plan = ?,
            subscription_status = ?,
            subscription_period_end = FROM_UNIXTIME(?),
            subscription_canceled_at = ?
        WHERE id = ?
    ")->execute([
        $plan,
        $subscription->status,
        $subscription->current_period_end,
        $subscription->canceled_at ? date('Y-m-d H:i:s', $subscription->canceled_at) : null,
        $artisan['id'],
    ]);
}
