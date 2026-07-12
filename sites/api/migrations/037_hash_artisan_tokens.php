<?php
/**
 * WebIArtisan API — Migration 037 : one-shot hash of legacy plaintext artisan tokens.
 *
 * Hashes any remaining plaintext auth_token values into auth_token_hash,
 * then clears the plaintext auth_token column.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDatabase();

    $select = $pdo->prepare("\n        SELECT id, auth_token\n        FROM local_artisans\n        WHERE auth_token IS NOT NULL\n          AND auth_token_hash IS NULL\n          AND auth_token_exp > NOW()\n    ");
    $select->execute();
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rows);
    echo "Found {$total} artisan token(s) to migrate.\n";

    if ($total === 0) {
        echo "Nothing to do.\n";
        exit(0);
    }

    $update = $pdo->prepare("\n        UPDATE local_artisans\n        SET auth_token_hash = ?, auth_token = NULL\n        WHERE id = ?\n    ");

    $migrated = 0;
    foreach ($rows as $row) {
        $hash = password_hash($row['auth_token'], PASSWORD_DEFAULT);
        $update->execute([$hash, $row['id']]);
        $migrated++;
        echo "Migrated {$migrated}/{$total} (id={$row['id']})\n";
    }

    echo "Successfully migrated {$migrated} artisan token(s).\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
