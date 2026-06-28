<?php
/**
 * PaymentModel - Gestion des paiements en base de données
 *
 * @package WebIArtisan\API\Models
 */

require_once __DIR__ . '/../config/database.php';

class PaymentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    /**
     * Crée un enregistrement de paiement
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO payments (
            facture_id, stripe_payment_intent_id, stripe_customer_id,
            amount, currency, status, receipt_email, created_at
        ) VALUES (
            :facture_id, :stripe_payment_intent_id, :stripe_customer_id,
            :amount, :currency, :status, :receipt_email, NOW()
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':facture_id' => $data['facture_id'],
            ':stripe_payment_intent_id' => $data['stripe_payment_intent_id'],
            ':stripe_customer_id' => $data['stripe_customer_id'] ?? null,
            ':amount' => $data['amount'],
            ':currency' => $data['currency'] ?? 'eur',
            ':status' => $data['status'] ?? 'pending',
            ':receipt_email' => $data['receipt_email'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour le statut d'un paiement
     */
    public function updateStatus(string $stripePaymentIntentId, string $status, array $extra = []): bool
    {
        $fields = ['status = :status'];
        $params = [':status' => $status, ':stripe_id' => $stripePaymentIntentId];

        if (isset($extra['paid_at'])) {
            $fields[] = 'paid_at = :paid_at';
            $params[':paid_at'] = $extra['paid_at'];
        }
        if (isset($extra['payment_method'])) {
            $fields[] = 'payment_method = :payment_method';
            $params[':payment_method'] = $extra['payment_method'];
        }
        if (isset($extra['receipt_url'])) {
            $fields[] = 'receipt_url = :receipt_url';
            $params[':receipt_url'] = $extra['receipt_url'];
        }
        if (isset($extra['error_message'])) {
            $fields[] = 'error_message = :error_message';
            $params[':error_message'] = $extra['error_message'];
        }

        $sql = "UPDATE payments SET " . implode(', ', $fields) . 
               ", updated_at = NOW() WHERE stripe_payment_intent_id = :stripe_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Récupère un paiement par son PaymentIntent ID
     */
    public function findByPaymentIntent(string $stripePaymentIntentId): ?array
    {
        $sql = "SELECT * FROM payments WHERE stripe_payment_intent_id = :stripe_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':stripe_id' => $stripePaymentIntentId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Récupère les paiements d'une facture
     */
    public function findByFacture(int $factureId): array
    {
        $sql = "SELECT * FROM payments WHERE facture_id = :facture_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':facture_id' => $factureId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le statut de paiement d'une facture
     */
    public function updateFacturePaymentStatus(int $factureId, string $status, ?string $paidAt = null): bool
    {
        $sql = "UPDATE factures SET 
                payment_status = :status, 
                updated_at = NOW()";
        
        $params = [':status' => $status, ':id' => $factureId];

        if ($paidAt) {
            $sql .= ", paid_at = :paid_at";
            $params[':paid_at'] = $paidAt;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Récupère une facture par ID
     */
    public function getFacture(int $factureId): ?array
    {
        $sql = "SELECT f.*, c.nom as client_nom, c.email as client_email, c.telephone as client_telephone
                FROM factures f
                LEFT JOIN clients c ON f.client_id = c.id
                WHERE f.id = :id LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $factureId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Enregistre le token de paiement sur une facture
     */
    public function setFacturePaymentToken(int $factureId, string $token, string $expiresAt, ?string $stripePaymentIntentId = null): bool
    {
        $sql = "UPDATE factures SET 
                payment_token = :token,
                payment_token_expires_at = :expires_at,
                stripe_payment_intent_id = :stripe_id,
                updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':stripe_id' => $stripePaymentIntentId,
            ':id' => $factureId,
        ]);
    }
}
