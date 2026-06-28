<?php
/**
 * StripeService - Gestion des paiements via Stripe
 *
 * @package WebIArtisan\API\Services
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class StripeService
{
    private string $secretKey;
    private string $publishableKey;
    private string $webhookSecret;
    private string $currency;

    public function __construct()
    {
        $this->secretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->publishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
        $this->webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        $this->currency = $_ENV['STRIPE_CURRENCY'] ?? 'eur';

        if (empty($this->secretKey)) {
            throw new Exception('STRIPE_SECRET_KEY non configurée');
        }

        Stripe::setApiKey($this->secretKey);
    }

    /**
     * Récupère la clé publique Stripe
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Crée un PaymentIntent pour une facture
     *
     * @param float $amount Montant en euros
     * @param string $factureId ID interne de la facture
     * @param string $customerEmail Email du client
     * @param string $description Description du paiement
     * @return array PaymentIntent data
     */
    public function createPaymentIntent(
        float $amount,
        string $factureId,
        string $customerEmail,
        string $description
    ): array {
        // Stripe attend le montant en centimes
        $amountCents = (int) round($amount * 100);

        $metadata = [
            'facture_id' => $factureId,
            'app' => 'webiartisan',
        ];

        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $this->currency,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $metadata,
                'description' => $description,
                'receipt_email' => $customerEmail,
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Récupère les détails d'un PaymentIntent
     */
    public function getPaymentIntent(string $paymentIntentId): ?PaymentIntent
    {
        try {
            return PaymentIntent::retrieve($paymentIntentId);
        } catch (Exception $e) {
            error_log("Erreur récupération PaymentIntent: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie et parse le webhook Stripe
     *
     * @param string $payload Corps brut de la requête
     * @param string $signatureHeader Header Stripe-Signature
     * @return object|null Event Stripe ou null si invalide
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): ?object
    {
        if (empty($this->webhookSecret)) {
            error_log('STRIPE_WEBHOOK_SECRET non configurée');
            return null;
        }

        try {
            return Webhook::constructEvent(
                $payload,
                $signatureHeader,
                $this->webhookSecret
            );
        } catch (Exception $e) {
            error_log("Erreur webhook Stripe: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère un token de paiement sécurisé (JWT-like simple)
     *
     * @param string $factureId ID de la facture
     * @param int $expiryDays Nombre de jours avant expiration
     * @return string Token signé
     */
    public function generatePaymentToken(string $factureId, int $expiryDays = 30): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'default_secret';
        $expires = time() + ($expiryDays * 86400);

        $payload = json_encode([
            'fact' => $factureId,
            'exp' => $expires,
            'iat' => time(),
        ]);

        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode($payload) . '.' . $signature;
    }

    /**
     * Récupère le solde du compte Stripe
     */
    public function getBalance(): array
    {
        try {
            $balance = \Stripe\Balance::retrieve();
            $available = [];
            foreach ($balance->available as $b) {
                $available[] = [
                    'amount' => $b->amount / 100,
                    'currency' => strtoupper($b->currency),
                ];
            }
            $pending = [];
            foreach ($balance->pending as $b) {
                $pending[] = [
                    'amount' => $b->amount / 100,
                    'currency' => strtoupper($b->currency),
                ];
            }
            return ['success' => true, 'available' => $available, 'pending' => $pending];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Liste les PaymentIntents récents depuis Stripe
     */
    public function listRecentPaymentIntents(int $limit = 20): array
    {
        try {
            $intents = PaymentIntent::all(['limit' => $limit, 'expand' => ['data.latest_charge']]);
            $results = [];
            foreach ($intents->data as $pi) {
                $results[] = [
                    'id' => $pi->id,
                    'amount' => $pi->amount / 100,
                    'currency' => strtoupper($pi->currency),
                    'status' => $pi->status,
                    'description' => $pi->description,
                    'receipt_email' => $pi->receipt_email,
                    'metadata' => (array)$pi->metadata,
                    'created' => date('Y-m-d H:i:s', $pi->created),
                    'payment_method_type' => $pi->payment_method_types[0] ?? null,
                    'receipt_url' => $pi->latest_charge->receipt_url ?? null,
                ];
            }
            return ['success' => true, 'payment_intents' => $results, 'count' => count($results)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Liste les clients Stripe récents
     */
    public function listCustomers(int $limit = 20): array
    {
        try {
            $customers = \Stripe\Customer::all(['limit' => $limit]);
            $results = [];
            foreach ($customers->data as $c) {
                $results[] = [
                    'id' => $c->id,
                    'email' => $c->email,
                    'name' => $c->name,
                    'created' => date('Y-m-d H:i:s', $c->created),
                    'metadata' => (array)$c->metadata,
                ];
            }
            return ['success' => true, 'customers' => $results, 'count' => count($results)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crée un client Stripe
     */
    public function createCustomer(string $email, string $name = '', array $metadata = []): array
    {
        try {
            $params = ['email' => $email];
            if ($name) $params['name'] = $name;
            if ($metadata) $params['metadata'] = $metadata;

            $customer = \Stripe\Customer::create($params);
            return [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->name,
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Crée une Checkout Session Stripe (scénario demo)
     */
    public function createCheckoutSession(float $amount, string $productName, string $customerEmail, string $successUrl, string $cancelUrl): array
    {
        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $this->currency,
                        'product_data' => ['name' => $productName],
                        'unit_amount' => (int) round($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $customerEmail,
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => ['app' => 'webiartisan', 'demo' => 'true'],
            ]);

            return [
                'success' => true,
                'session_id' => $session->id,
                'checkout_url' => $session->url,
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Valide un token de paiement
     *
     * @param string $token Token à valider
     * @return string|null ID de la facture ou null si invalide
     */
    public function validatePaymentToken(string $token): ?string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'default_secret';

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signature] = $parts;

        $payload = base64_decode($payloadB64);
        if (!$payload) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!$data || !isset($data['exp']) || !isset($data['fact'])) {
            return null;
        }

        if ($data['exp'] < time()) {
            return null;
        }

        return $data['fact'];
    }
}
