<?php
/**
 * RateLimit Middleware
 * Simple MySQL-based sliding window rate limiter.
 *
 * Limits par bucket (requêtes / 60s par IP) :
 *   login       → 10  (partagé entre les endpoints sensibles, voulu)
 *   public/hit  → 60
 *   public:<module> → 120 par module (buckets séparés pour éviter qu'un
 *   endpoint en rafale sature les autres)
 */

class RateLimit
{
    private PDO $pdo;

    private const LIMITS = [
        'login'      => 10,
        'public/hit' => 60,
        'public'     => 120,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie et incrémente le compteur.
     * Retourne false si la limite est dépassée, true sinon.
     */
    public function check(string $endpoint, string $ip): bool
    {
        $limit = $this->getLimit($endpoint);
        $window = (int) (time() / 60) * 60; // Fenêtre de 60s

        // Nettoyage probabiliste (~2% des requêtes)
        if (random_int(1, 50) === 1) {
            $this->cleanup($window);
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_rate_limits (ip, endpoint, window_start, count)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE count = count + 1'
            );
            $stmt->execute([$ip, $endpoint, $window]);

            $stmt = $this->pdo->prepare(
                'SELECT count FROM api_rate_limits
                 WHERE ip = ? AND endpoint = ? AND window_start = ?'
            );
            $stmt->execute([$ip, $endpoint, $window]);
            $count = (int) $stmt->fetchColumn();

            return $count <= $limit;
        } catch (\PDOException $e) {
            // En cas d'erreur DB (ex: table absente), on laisse passer
            return true;
        }
    }

    /**
     * Envoie une réponse 429 et stoppe l'exécution.
     */
    public function deny(string $endpoint): void
    {
        $limit = $this->getLimit($endpoint);
        http_response_code(429);
        header('Retry-After: 60');
        header('X-RateLimit-Limit: ' . $limit);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Trop de requêtes. Réessayez dans 60 secondes.',
            'code'    => 'rate_limited',
        ]);
        exit;
    }

    private function getLimit(string $endpoint): int
    {
        return self::LIMITS[$endpoint] ?? self::LIMITS['public'];
    }

    private function cleanup(int $currentWindow): void
    {
        // Supprime les fenêtres de plus de 2 minutes
        $old = $currentWindow - 120;
        $this->pdo->prepare(
            'DELETE FROM api_rate_limits WHERE window_start < ?'
        )->execute([$old]);
    }
}

/**
 * Résout l'IP client réelle.
 * X-Forwarded-For n'est fiable que si le pair direct est un proxy de confiance
 * (IP privée/réservée = Varnish sur l'infra gPaas). Dans ce cas, la dernière
 * entrée du XFF est celle ajoutée par Varnish ; les précédentes peuvent être
 * forgées par le client.
 */
function clientIp(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $isTrustedProxy = filter_var(
        $remote,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false;

    if ($isTrustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $last = end($parts);
        if ($last && filter_var($last, FILTER_VALIDATE_IP)) {
            return $last;
        }
    }
    return $remote;
}

/**
 * Helper — applique le rate limit et stoppe si dépassé.
 */
function applyRateLimit(PDO $pdo, string $endpoint): void
{
    $ip = clientIp();

    $rl = new RateLimit($pdo);
    if (!$rl->check($endpoint, $ip)) {
        $rl->deny($endpoint);
    }
}
