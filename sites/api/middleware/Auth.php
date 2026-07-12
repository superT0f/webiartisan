<?php
/**
 * Auth Middleware — Magic link (code par email) + remember me cookie.
 * Plus de mot de passe. Authentification style Supercell ID.
 */

class Auth {
    private string $secret;
    private const CODE_LENGTH = 6;
    private const CODE_EXPIRY = 600;       // 10 minutes
    private const JWT_EXPIRY = 86400;      // 24h
    private const SSO_EXPIRY = 300;        // 5 minutes (cross-domain handoff)
    private const REMEMBER_EXPIRY = 2592000; // 30 jours
    private const REMEMBER_COOKIE = 'wa_remember';

    public function __construct() {
        $config = getAppConfig();
        $this->secret = $config['jwt_secret'];
    }

    // ── JWT ────────────────────────────────────────

    /**
     * Generate a JWT token for a user.
     */
    public function generateToken(array $user): string {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        $payload = $this->base64UrlEncode(json_encode([
            'sub'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'iat'       => time(),
            'exp'       => time() + self::JWT_EXPIRY,
        ]));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    /**
     * Generate a short-lived SSO handoff token (60s, scope=sso).
     */
    public function generateSsoToken(array $user): string {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'scope'     => 'sso',
            'iat'       => time(),
            'exp'       => time() + self::SSO_EXPIRY,
        ]));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );
        return "$header.$payload.$signature";
    }

    /**
     * Verify an SSO handoff token. Returns payload only if scope === 'sso'.
     */
    public function verifySsoToken(string $token): ?array {
        $data = $this->verifyToken($token);
        if (!$data || ($data['scope'] ?? '') !== 'sso') return null;
        return $data;
    }

    /**
     * Verify and decode a JWT token.
     */
    public function verifyToken(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expectedSig, $signature)) return null;

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!$data) return null;

        if (isset($data['exp']) && $data['exp'] < time()) return null;

        return $data;
    }

    /**
     * Extract token from Authorization header.
     */
    public function getTokenFromHeader(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!$header && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Middleware: require authentication (JWT header, query param, ou remember cookie).
     */
    public function requireAuth(): array {
        // 1. Try JWT from header
        $token = $this->getTokenFromHeader();
        if ($token) {
            $user = $this->verifyToken($token);
            if ($user) {
                // Additional security check for demo account
                if ($this->isDemoAccount($user)) {
                    $this->enforceDemoSecurity($user);
                }
                return $user;
            }
        }

        // 2. Try JWT from query param (for external browser downloads like PDFs from Flutter)
        $token = $_GET['token'] ?? null;
        if ($token) {
            $user = $this->verifyToken($token);
            if ($user) {
                // Additional security check for demo account
                if ($this->isDemoAccount($user)) {
                    $this->enforceDemoSecurity($user);
                }
                return $user;
            }
        }

        // 3. Try remember me cookie
        $rememberUser = $this->checkRememberCookie();
        if ($rememberUser) {
            // Additional security check for demo account
            if ($this->isDemoAccount($rememberUser)) {
                $this->enforceDemoSecurity($rememberUser);
            }
            return $rememberUser;
        }

        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // ── Magic Code ─────────────────────────────────

    /**
     * Generate a 6-digit code for email verification.
     */
    public function generateCode(): string {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Store a magic code for a user.
     */
    public function storeCode(PDO $pdo, int $userId, string $code): void {
        // Invalidate previous codes for this user
        $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);

        $stmt = $pdo->prepare(
            "INSERT INTO auth_tokens (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        );
        $stmt->execute([$userId, $code, self::CODE_EXPIRY]);
    }

    /**
     * Verify a magic code. Returns true and marks as used if valid.
     */
    public function verifyCode(PDO $pdo, int $userId, string $code): bool {
        $stmt = $pdo->prepare(
            "SELECT id FROM auth_tokens WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used_at IS NULL LIMIT 1"
        );
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();

        if (!$row) return false;

        $pdo->prepare("UPDATE auth_tokens SET used_at = NOW() WHERE id = ?")->execute([$row['id']]);
        return true;
    }

    // ── Remember Me Cookie ─────────────────────────

    /**
     * Create a remember me token and set the cookie.
     */
    public function createRememberToken(PDO $pdo, array $user, ?string $deviceName = null): void {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare(
            "INSERT INTO remember_tokens (user_id, token_hash, device_name, ip_address, last_used_at, expires_at)
             VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))"
        );
        $stmt->execute([
            $user['id'],
            $tokenHash,
            $deviceName ?? $this->detectDevice(),
            $_SERVER['REMOTE_ADDR'] ?? null,
            self::REMEMBER_EXPIRY,
        ]);

        // Cookie: userId:rawToken
        $cookieValue = $user['id'] . ':' . $rawToken;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::REMEMBER_COOKIE, $cookieValue, [
            'expires'  => time() + self::REMEMBER_EXPIRY,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Check if a valid remember me cookie exists. Returns JWT payload-like array or null.
     */
    public function checkRememberCookie(): ?array {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!$cookie) return null;

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2) return null;

        [$userId, $rawToken] = $parts;
        $tokenHash = hash('sha256', $rawToken);

        try {
            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT rt.id, u.id as user_id, u.email, u.role, u.tenant_id
                 FROM remember_tokens rt
                 JOIN users u ON u.id = rt.user_id
                 WHERE rt.user_id = ? AND rt.token_hash = ? AND rt.expires_at > NOW() AND u.is_active = TRUE
                 LIMIT 1"
            );
            $stmt->execute([(int)$userId, $tokenHash]);
            $row = $stmt->fetch();

            if (!$row) return null;

            // Update last_used_at
            $pdo->prepare("UPDATE remember_tokens SET last_used_at = NOW() WHERE id = ?")->execute([$row['id']]);

            return [
                'sub'       => (int)$row['user_id'],
                'tenant_id' => (int)$row['tenant_id'],
                'email'     => $row['email'],
                'role'      => $row['role'],
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Clear remember me cookie and DB token.
     */
    public function clearRememberToken(PDO $pdo): void {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if ($cookie) {
            $parts = explode(':', $cookie, 2);
            if (count($parts) === 2) {
                $tokenHash = hash('sha256', $parts[1]);
                $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$tokenHash]);
            }
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    // ── Biometric Authentication ──────────────────

    /**
     * Create a biometric key for a user/device.
     */
    public function createBiometricKey(PDO $pdo, int $userId, string $deviceId, string $secret, ?string $deviceName = null): void {
        $keyHash = password_hash($secret, PASSWORD_BCRYPT);
        
        // Remove old keys for this device/user combination
        $stmt = $pdo->prepare("DELETE FROM biometric_keys WHERE user_id = ? AND device_id = ?");
        $stmt->execute([$userId, $deviceId]);

        $stmt = $pdo->prepare(
            "INSERT INTO biometric_keys (user_id, device_id, key_hash, device_name, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))"
        );
        $stmt->execute([
            $userId,
            $deviceId,
            $keyHash,
            $deviceName ?? $this->detectDevice(),
        ]);
    }

    /**
     * Verify a biometric login request.
     * A device_id should normally identify a single active key, but we iterate
     * over all active keys for the device to avoid collisions.
     */
    public function verifyBiometric(PDO $pdo, string $deviceId, string $secret): ?array {
        $stmt = $pdo->prepare(
            "SELECT bk.id, bk.key_hash, u.id as user_id, u.email, u.login, u.role, u.tenant_id,
                    u.is_active, u.name, u.fonction, u.phone, u.avatar_path,
                    t.slug as tenant_slug, t.name as tenant_name
             FROM biometric_keys bk
             JOIN users u ON u.id = bk.user_id
             JOIN tenants t ON t.id = u.tenant_id
             WHERE bk.device_id = ? AND (bk.expires_at IS NULL OR bk.expires_at > NOW())
             ORDER BY bk.created_at DESC"
        );
        $stmt->execute([$deviceId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            if (!$row['is_active']) continue;
            if (!password_verify($secret, $row['key_hash'])) continue;

            // Update last_used_at
            $pdo->prepare("UPDATE biometric_keys SET last_used_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);

            return [
                'id'          => (int)$row['user_id'],
                'email'       => $row['email'],
                'login'       => $row['login'],
                'role'        => $row['role'],
                'tenant_id'   => (int)$row['tenant_id'],
                'name'        => $row['name'],
                'fonction'    => $row['fonction'],
                'phone'       => $row['phone'],
                'tenant_slug' => $row['tenant_slug'],
                'tenant_name' => $row['tenant_name'],
                'avatar_path' => $row['avatar_path'],
            ];
        }

        return null;
    }

    /**
     * Clear biometric keys for a user/device.
     */
    public function clearBiometricKey(PDO $pdo, int $userId, string $deviceId): void {
        $pdo->prepare("DELETE FROM biometric_keys WHERE user_id = ? AND device_id = ?")
            ->execute([$userId, $deviceId]);
    }

    // ── Email ──────────────────────────────────────

    /**
     * Send magic code by email.
     */
    public function sendCode(string $email, string $code, string $userName = ''): bool {
        $config = getAppConfig();
        $appName = 'WebIArtisan';
        $greeting = $userName ? "Bonjour $userName," : 'Bonjour,';

        $subject = "$appName — Votre code de connexion : $code";
        $body = <<<HTML
<!DOCTYPE html>
<html><body style="font-family: -apple-system, sans-serif; max-width: 480px; margin: 0 auto; padding: 20px;">
<h2 style="color: #1a1a2e;">$greeting</h2>
<p>Votre code de connexion :</p>
<div style="background: #f0f0f0; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;">
  <span style="font-size: 32px; letter-spacing: 8px; font-weight: bold; color: #1a1a2e;">$code</span>
</div>
<p style="color: #888; font-size: 13px;">Ce code expire dans 10 minutes. Si vous n'avez pas demandé ce code, ignorez cet email.</p>
<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
<p style="color: #aaa; font-size: 12px;">$appName</p>
</body></html>
HTML;

        $fromEmail = $config['mail_from'] ?? "noreply@webiartisan.prigent.tech";
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: WebIArtisan <' . $fromEmail . '>',
            'X-Mailer: PHP/' . phpversion(),
        ];

        $encodedSubject = mb_encode_mimeheader($subject, "UTF-8");
        return mail($email, $encodedSubject, $body, implode("\r\n", $headers), "-f$fromEmail");
    }

    // ── Helpers ────────────────────────────────────

    /**
     * Lookup user by email or login.
     */
    public function findUserByIdentifier(PDO $pdo, string $identifier): ?array {
        $stmt = $pdo->prepare(
            "SELECT u.*, t.slug as tenant_slug, t.name as tenant_name
             FROM users u JOIN tenants t ON t.id = u.tenant_id
             WHERE (u.email = ? OR u.login = ?) AND u.is_active = TRUE
             LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Detect device name from User-Agent.
     */
    private function detectDevice(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        if (strlen($ua) > 200) $ua = substr($ua, 0, 200);
        return $ua;
    }

    /**
     * Get avatar URL for a user.
     */
    public function getAvatarUrl(?string $avatarPath): ?string {
        if (!$avatarPath) return null;
        $config = getAppConfig();
        return ($config['api_url'] ?? '') . '/auth/avatar/' . basename($avatarPath);
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ── Demo Account Security ───────────────────────

    /**
     * Check if the user account is a demo account.
     * Fast path: only the known demo email can trigger the check.
     */
    private function isDemoAccount(array $user): bool {
        if (($user['email'] ?? '') !== 'demo@prigent.tech') {
            return false;
        }
        try {
            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT t.plan FROM tenants t 
                 JOIN users u ON u.tenant_id = t.id 
                 WHERE u.id = ? AND u.email = 'demo@prigent.tech'"
            );
            $stmt->execute([$user['sub']]);
            $plan = $stmt->fetchColumn();
            return $plan === 'demo';
        } catch (Exception $e) {
            error_log("Demo account check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enforce security restrictions for demo accounts.
     */
    private function enforceDemoSecurity(array $user): void {
        try {
            $pdo = getDatabase();
            $stmt = $pdo->prepare(
                "SELECT dm.*, t.plan FROM demo_metadata dm
                 JOIN users u ON u.id = dm.user_id
                 JOIN tenants t ON t.id = u.tenant_id
                 WHERE u.id = ? AND u.email = 'demo@prigent.tech'"
            );
            $stmt->execute([$user['sub']]);
            $metadata = $stmt->fetch();

            if (!$metadata) {
                // Create metadata if missing
                $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO demo_metadata (user_id, bypass_sso, max_login_attempts, auto_logout_minutes)
                     SELECT id, TRUE, 999, 480 FROM users WHERE email = 'demo@prigent.tech'"
                );
                $stmt->execute();
                return;
            }

            // Check IP restrictions if set
            if ($metadata['restricted_ips']) {
                $allowedIps = json_decode($metadata['restricted_ips'], true);
                $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
                if ($allowedIps && !in_array($currentIp, $allowedIps)) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'demo_ip_restricted',
                        'message' => 'Accès demo non autorisé depuis cette adresse IP'
                    ]);
                    exit;
                }
            }

            // Log demo access for monitoring
            error_log("Demo account access: user_id={$user['sub']}, ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
        } catch (Exception $e) {
            error_log("Demo security enforcement failed: " . $e->getMessage());
            // Don't block access on security check failure
        }
    }
}
