<?php
/**
 * Development router for PHP built-in server.
 *
 * Emulates the nginx rewrite used by the Docker stack:
 *   - Existing files under public/ are served as static assets.
 *   - Everything else is forwarded to index.php.
 *
 * Usage:
 *   php -S 127.0.0.1:8081 -t sites/api sites/api/router-dev.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicFile = __DIR__ . '/public' . $uri;

if ($uri !== '/index.php' && file_exists($publicFile) && is_file($publicFile)) {
    return false;
}

require __DIR__ . '/index.php';
