<?php
/**
 * WebIArtisan API — Route : Utilisateurs (consommateurs)
 *
 * POST /users/magic-link        — envoie un lien magique
 * POST /users/auth?token=...    — valide le token et crée une session
 * GET  /users/me                — infos utilisateur connecté
 */

require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/UserAuth.php';

switch ($method) {
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'magic-link') {
            user_magic_link($pdo, $body);
        } elseif ($action === 'auth') {
            user_auth($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'GET':
        if ($action === 'me') {
            user_me($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function user_magic_link(PDO $pdo, array $body): void
{
    $email = strtolower(trim($body['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email invalide']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM local_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $pdo->prepare("INSERT INTO local_users (email) VALUES (?)")->execute([$email]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    $token = bin2hex(random_bytes(32));
    $exp = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare("
        UPDATE local_users
        SET magic_token = ?, magic_token_exp = ?
        WHERE id = ?
    ")->execute([$token, $exp, $userId]);

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $base = ($origin && filter_var($origin, FILTER_VALIDATE_URL))
        ? $origin
        : 'https://artisans-livry.prigent.tech';
    $link = rtrim($base, '/') . '/roue?token=' . urlencode($token);

    $subject = 'Votre lien pour tourner la roue des artisans';
    $html = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
  <h2 style="color: #1a1a2e;">Bonjour,</h2>
  <p>Voici votre lien sécurisé pour tourner la roue des artisans de Livry :</p>
  <div style="text-align: center; margin: 24px 0;">
    <a href="{$link}" style="display: inline-block; background: #1a1a2e; color: #fff; padding: 14px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;">Tourner la roue</a>
  </div>
  <p style="color: #888; font-size: 13px;">Ce lien est valable 1 heure. Si vous ne l'avez pas demandé, ignorez cet email.</p>
</body></html>
HTML;

    $sent = send_html_email($email, $subject, $html, null, 'WebIArtisan');
    if (!$sent) {
        error_log("[USER-MAGIC-LINK] Échec envoi email à {$email}");
    }
    error_log("[USER-MAGIC-LINK] {$link}");

    echo json_encode([
        'success' => true,
        'message' => 'Si votre email est valide, vous recevrez un lien de connexion.',
    ]);
}

function user_auth(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, email
        FROM local_users
        WHERE magic_token = ? AND magic_token_exp > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Lien invalide ou expiré']);
        return;
    }

    $sessionToken = bin2hex(random_bytes(32));
    $sessionExp = date('Y-m-d H:i:s', strtotime('+30 days'));

    $pdo->prepare("
        UPDATE local_users
        SET session_token = ?, session_exp = ?,
            magic_token = NULL, magic_token_exp = NULL
        WHERE id = ?
    ")->execute([$sessionToken, $sessionExp, $user['id']]);

    echo json_encode([
        'success' => true,
        'token'   => $sessionToken,
        'data'    => ['id' => (int)$user['id'], 'email' => $user['email']],
    ]);
}

function user_me(PDO $pdo): void
{
    $user = user_require_auth($pdo);
    require_once __DIR__ . '/../lib/Gamification.php';
    $profile = gamificationUserProfile($pdo, (int)$user['id']);
    if ($profile === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
        return;
    }
    echo json_encode(['success' => true, 'data' => $profile]);
}
