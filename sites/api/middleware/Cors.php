<?php
/**
 * CORS Middleware
 * Handles Cross-Origin Resource Sharing headers.
 */

function handleCors(): void {
    $allowedOrigins = [
        'http://localhost:5173',        // Vite dev server
        'http://localhost:1313',        // Hugo dev server
        'https://app.prigent.tech',
        'https://web.prigent.tech',
        'https://artisans-combs.prigent.tech',
        'https://artisans-vert-saint-denis.prigent.tech',
        'https://artisans-livry.prigent.tech',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Artisan-Token');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
