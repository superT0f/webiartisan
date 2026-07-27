<?php
/**
 * WebiArtisan API — Route publique : état maintenance (lecture seule).
 * GET /maintenance → { enabled, message } — pour afficher une bannière.
 */

require_once __DIR__ . '/../lib/AppLogger.php';

$rows = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM local_settings WHERE setting_key IN ('maintenance_enabled','maintenance_message')")
        ->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { /* table pas encore créée */ }

echo json_encode([
    'success' => true,
    'data'    => [
        'enabled' => ($rows['maintenance_enabled'] ?? '0') === '1',
        'message' => $rows['maintenance_message'] ?? '',
    ],
]);
