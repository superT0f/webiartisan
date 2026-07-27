<?php
/**
 * WebiArtisan — Mini-runner de migrations additives.
 *
 * Les migrations sont idempotentes et enregistrées dans local_migrations :
 * au déploiement, le premier appel API applique ce qui manque (plus besoin
 * de passer par phpMyAdmin pour les changements additifs).
 *
 * Règles : uniquement du DDL additif (CREATE TABLE IF NOT EXISTS, ALTER
 * d'ENUM élargi). Jamais de DROP ni de modification destructive.
 */

function migrations_apply(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS local_migrations (
            name VARCHAR(64) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = $pdo->query("SELECT name FROM local_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $migrations = [
        '046_inventory' => [
            "ALTER TABLE local_world_objects
                MODIFY object_type enum(
                    'dechet','canette','papier','tresor','cadeau_artisan','big_brother',
                    'boss_spawner','energy_store'
                ) COLLATE utf8mb4_unicode_ci NOT NULL",
            "CREATE TABLE IF NOT EXISTS local_user_inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                object_type VARCHAR(32) NOT NULL,
                source_object_id INT NULL,
                status ENUM('active','used') NOT NULL DEFAULT 'active',
                acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME NULL,
                INDEX idx_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],
    ];

    foreach ($migrations as $name => $statements) {
        if (in_array($name, $done, true)) {
            continue;
        }
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
        $pdo->prepare("INSERT INTO local_migrations (name) VALUES (?)")->execute([$name]);
        if (function_exists('app_log')) {
            app_log('info', "[MIGRATIONS] $name appliquée");
        }
    }
}
