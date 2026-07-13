<?php
/**
 * WebIArtisan API — Migration 037 : one-shot hash of legacy plaintext artisan tokens.
 *
 * Hashes any remaining plaintext auth_token values into auth_token_hash,
 * then clears the plaintext auth_token column.
 */

require_once __DIR__ . '/../config/database.php';

// Make sure .env credentials are available when running outside the FPM request.
// Do not override already-exported shell variables.
$env = loadEnv(__DIR__ . '/../.env');
foreach ($env as $key => $value) {
    $hasEnvValue = isset($_ENV[$key]) && $_ENV[$key] !== '';
    $hasServerValue = isset($_SERVER[$key]) && $_SERVER[$key] !== '';
    $hasGetenvValue = getenv($key) !== false && getenv($key) !== '';
    if (!$hasEnvValue && !$hasServerValue && !$hasGetenvValue) {
        $_ENV[$key] = $value;
    }
}

try {
    $pdo = getDatabase();

    $select = $pdo->prepare("
        SELECT id, auth_token
        FROM local_artisans
        WHERE auth_token IS NOT NULL
          AND auth_token_hash IS NULL
          AND auth_token_exp > NOW()
    ");
    $select->execute();
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rows);
    echo "Found {$total} artisan token(s) to migrate.\n";

    $migrated = 0;
    if ($total > 0) {
        $update = $pdo->prepare("
            UPDATE local_artisans
            SET auth_token_hash = ?, auth_token = NULL
            WHERE id = ?
        ");

        foreach ($rows as $row) {
            $hash = password_hash($row['auth_token'], PASSWORD_DEFAULT);
            $update->execute([$hash, $row['id']]);
            $migrated++;
            echo "Migrated {$migrated}/{$total} (id={$row['id']})\n";
        }

        echo "Successfully migrated {$migrated} artisan token(s).\n";
    }

    // Clear any expired plaintext tokens that were not migrated.
    $clear = $pdo->prepare("
        UPDATE local_artisans
        SET auth_token = NULL
        WHERE auth_token_exp <= NOW()
          AND auth_token IS NOT NULL
    ");
    $clear->execute();
    $cleared = $clear->rowCount();
    echo "Cleared {$cleared} expired plaintext token(s).\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
