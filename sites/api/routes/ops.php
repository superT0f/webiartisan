<?php
/**
 * WebiArtisan API — Route : Ops (exploitation, debug, incidents)
 *
 * Auth : token ops dédié (lib/OpsAuth.php) — jamais le JWT utilisateur.
 *
 * GET /ops/health                      — versions, migrations, DB, files
 * GET /ops/logs?date=&lines=           — tail des logs applicatifs
 * GET /ops/maintenance                 — état du flag maintenance
 * POST /ops/maintenance {enabled,message} — bascule le flag
 * GET /ops/db/tables                   — tables + lignes + taille (inventaire)
 * GET /ops/db/export?table=&anonymize= — export CSV d'une table local_*
 */

require_once __DIR__ . '/../lib/OpsAuth.php';
require_once __DIR__ . '/../lib/AppLogger.php';

ops_require_auth();

switch ($method) {
    case 'GET':
        if ($action === 'health') {
            ops_health($pdo);
        } elseif ($action === 'logs') {
            ops_logs();
        } elseif ($action === 'maintenance') {
            ops_maintenance_get($pdo);
        } elseif ($action === 'db' && $param === 'tables') {
            ops_db_tables($pdo);
        } elseif ($action === 'db' && $param === 'export') {
            ops_db_export($pdo);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if ($action === 'maintenance') {
            ops_maintenance_set($pdo, $body);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Endpoint inconnu']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

function ops_health(PDO $pdo): void
{
    $migrations = [];
    try {
        $rows = $pdo->query("SELECT name, applied_at FROM local_migrations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $migrations[$r['name']] = $r['applied_at'];
        }
    } catch (Throwable $e) {
        $migrations = ['error' => 'local_migrations illisible'];
    }

    $emailPending = null;
    try {
        $emailPending = (int)$pdo->query("SELECT COUNT(*) FROM email_queue WHERE status IN ('pending','retrying')")->fetchColumn();
    } catch (Throwable $e) { /* table absente */ }

    $version = null;
    foreach ([__DIR__ . '/../../../.version', __DIR__ . '/../../.version'] as $vf) {
        if (is_file($vf)) { $version = trim((string)file_get_contents($vf)); break; }
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'time'               => date('c'),
            'php_version'        => PHP_VERSION,
            'api_version'        => $version,
            'db'                 => true,
            'migrations'         => $migrations,
            'email_queue_pending' => $emailPending,
            'maintenance'        => ops_maintenance_state($pdo),
        ],
    ]);
}

function ops_logs(): void
{
    $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Date invalide (YYYY-MM-DD)']);
        return;
    }
    $lines = min(500, max(1, (int)($_GET['lines'] ?? 200)));

    // Même résolution que AppLogger : prod = ../../storage, local = ../storage
    $candidates = [__DIR__ . '/../../storage/logs', __DIR__ . '/../storage/logs'];
    $file = null;
    foreach ($candidates as $dir) {
        $f = "$dir/api-$date.log";
        if (is_file($f)) { $file = $f; break; }
    }
    if (!$file) {
        echo json_encode(['success' => true, 'data' => ['date' => $date, 'lines' => []]]);
        return;
    }

    $all = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    echo json_encode([
        'success' => true,
        'data'    => ['date' => $date, 'total' => count($all), 'lines' => array_slice($all, -$lines)],
    ]);
}

function ops_maintenance_state(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM local_settings WHERE setting_key IN ('maintenance_enabled','maintenance_message')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        $rows = [];
    }
    return [
        'enabled' => ($rows['maintenance_enabled'] ?? '0') === '1',
        'message' => $rows['maintenance_message'] ?? '',
    ];
}

function ops_maintenance_get(PDO $pdo): void
{
    echo json_encode(['success' => true, 'data' => ops_maintenance_state($pdo)]);
}

function ops_maintenance_set(PDO $pdo, array $body): void
{
    $enabled = !empty($body['enabled']) ? '1' : '0';
    $message = trim((string)($body['message'] ?? ''));
    $stmt = $pdo->prepare("INSERT INTO local_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['maintenance_enabled', $enabled]);
    $stmt->execute(['maintenance_message', $message]);
    app_log('warning', '[OPS] maintenance ' . ($enabled === '1' ? 'ACTIVÉE' : 'désactivée'), ['message' => $message]);
    echo json_encode(['success' => true, 'data' => ops_maintenance_state($pdo)]);
}

function ops_db_tables(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT TABLE_NAME AS table_name, TABLE_ROWS AS table_rows,
               ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS size_kb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function ops_db_export(PDO $pdo): void
{
    $table = trim((string)($_GET['table'] ?? ''));
    // Whitelist stricte : tables local_* existantes uniquement
    if (!preg_match('/^local_[a-z0-9_]+$/', $table)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Table invalide (local_* requis)']);
        return;
    }
    $exists = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $exists->execute([$table]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table introuvable']);
        return;
    }

    $anonymize = ($_GET['anonymize'] ?? '1') !== '0';
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $rows = $pdo->query("SELECT * FROM `$table` LIMIT 50000")->fetchAll(PDO::FETCH_NUM);

    // Anonymisation par défaut : colonnes email/téléphone hashées (RGPD)
    $sensitiveIdx = [];
    foreach ($cols as $i => $col) {
        if (preg_match('/email|phone|telephone|token|secret|password/i', $col)) {
            $sensitiveIdx[] = $i;
        }
    }
    if ($anonymize) {
        foreach ($rows as &$row) {
            foreach ($sensitiveIdx as $i) {
                if ($row[$i] !== null && $row[$i] !== '') {
                    $row[$i] = substr(hash('sha256', (string)$row[$i]), 0, 12);
                }
            }
        }
        unset($row);
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $table . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $cols);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    app_log('warning', '[OPS] export table', ['table' => $table, 'rows' => count($rows), 'anonymized' => $anonymize]);
    exit;
}
