<?php
/**
 * WebiArtisan API — Route : Inscriptions bêta (campagne QR / early access)
 *
 * POST /beta/signup {email, city?} — enregistre un email bêta + notifie l'admin
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/AppLogger.php';

if ($method !== 'POST' || ($action !== 'signup' && $action !== '')) {
    http_response_code($method === 'POST' ? 404 : 405);
    echo json_encode(['success' => false, 'error' => $method === 'POST' ? 'Endpoint inconnu' : 'Méthode non autorisée']);
    return;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email = strtolower(trim((string)($body['email'] ?? '')));
$city = trim((string)($body['city'] ?? '')) ?: null;

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email invalide']);
    return;
}

// Idempotent : un email déjà inscrit répond OK aussi (pas d'énumération)
$pdo->prepare("INSERT IGNORE INTO local_beta_signups (email, city) VALUES (?, ?)")
    ->execute([$email, $city]);

// Notification admin (premier compte admin trouvé), sans bloquer la réponse
try {
    $adminEmail = $pdo->query("SELECT email FROM local_artisans WHERE is_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1")
        ->fetchColumn();
    if ($adminEmail) {
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeCity = htmlspecialchars($city ?? '—', ENT_QUOTES, 'UTF-8');
        queueEmail(
            $adminEmail,
            '[Bêta] Nouvelle inscription — ' . $email,
            "<p>Nouvelle inscription à la bêta WebiArtisan :</p>"
            . "<p><strong>{$safeEmail}</strong><br>Ville : {$safeCity}</p>"
            . "<p>À ajouter à la liste des testeurs dans la Play Console (24-48 h), puis l'utilisateur suit le lien « Rejoindre le test ».</p>",
            null,
            null,
            $email,
            ['kind' => 'beta_signup', 'email' => $email, 'city' => $city]
        );
    }
} catch (Throwable $e) {
    error_log('[BETA] notification admin impossible : ' . $e->getMessage());
}

if (function_exists('app_log')) {
    app_log('info', '[BETA] signup', ['email' => $email, 'city' => $city]);
}

echo json_encode([
    'success' => true,
    'data' => [
        'message' => 'Inscription enregistrée ! Vous recevrez le lien d\'accès sous 24-48 h.',
        'testing_url' => 'https://play.google.com/apps/testing/tech.prigent.webiartisan',
    ],
]);
