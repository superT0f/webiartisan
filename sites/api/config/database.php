<?php
/**
 * Database configuration — unified for all API modules.
 * Loads credentials from .env file.
 */

function loadEnv(string $path): array {
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

function envOrDefault(string $key, string $default): string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

function getDatabase(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = envOrDefault('DB_HOST', 'mysql');
    $port    = envOrDefault('DB_PORT', '3306');
    $db      = envOrDefault('DB_NAME', 'webiartisan');
    $user    = envOrDefault('DB_USER', 'webiartisan');
    $pass    = envOrDefault('DB_PASS', 'webiartisan_dev');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    // Use the current PHP offset so MySQL time functions agree with PHP even when
    // the MySQL timezone tables are not loaded (common on shared hosts).
    date_default_timezone_set('Europe/Paris');
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}
