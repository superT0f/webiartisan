<?php
require_once __DIR__ . '/../vendor/autoload.php';

class StripeSubscriptionService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $monthlyPriceId;

    public function __construct()
    {
        $this->secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $this->monthlyPriceId = $_ENV['STRIPE_PREMIUM_MONTHLY_PRICE_ID'] ?? '';

        if (!$this->secretKey && !$this->webhookSecret) {
            throw new RuntimeException('Stripe configuration missing: STRIPE_SECRET_KEY or STRIPE_WEBHOOK_SECRET is required');
        }

        if ($this->secretKey) {
            \Stripe\Stripe::setApiKey($this->secretKey);
        }
    }

    public function createCheckoutSession(array $artisan, string $returnUrl): string
    {
        $allowed = [
            'https://artisans-livry.prigent.tech/espace',
            'https://artisans-combs.prigent.tech/espace',
            'https://artisans-vert-saint-denis.prigent.tech/espace',
        ];
        if (!in_array($returnUrl, $allowed, true)) {
            throw new InvalidArgumentException('Invalid return URL');
        }

        if (!$this->monthlyPriceId) {
            throw new RuntimeException('Stripe configuration missing: STRIPE_PREMIUM_MONTHLY_PRICE_ID is required');
        }

        $successUrl = rtrim($returnUrl, '/') . '?subscription=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = rtrim($returnUrl, '/') . '?subscription=cancel';

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $this->monthlyPriceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $artisan['email'] ?? null,
            'metadata' => [
                'artisan_id' => (string)$artisan['id'],
            ],
        ]);

        return $session->url;
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        $allowed = [
            'https://artisans-livry.prigent.tech/espace',
            'https://artisans-combs.prigent.tech/espace',
            'https://artisans-vert-saint-denis.prigent.tech/espace',
        ];
        if (!in_array($returnUrl, $allowed, true)) {
            throw new InvalidArgumentException('Invalid return URL');
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function constructEvent(string $payload, string $signature): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    }

    public function getMonthlyPriceId(): string
    {
        return $this->monthlyPriceId;
    }
}
