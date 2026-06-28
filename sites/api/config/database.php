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

function getDatabase(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = $_ENV['DB_HOST'] ?? 'mysql';
    $db      = $_ENV['DB_NAME'] ?? 'webiartisan';
    $user    = $_ENV['DB_USER'] ?? 'webiartisan';
    $pass    = $_ENV['DB_PASS'] ?? 'webiartisan_dev';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}
