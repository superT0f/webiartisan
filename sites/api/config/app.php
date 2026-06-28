<?php
/**
 * Application configuration.
 */

function getAppConfig(): array {
    static $config = null;
    if ($config !== null) return $config;

    $env = loadEnv(__DIR__ . '/../.env');

    $siteBaseUrl = $env['SITE_BASE_URL'] ?? 'https://web.prigent.tech';
    if (strpos($siteBaseUrl, 'app.prigent.tech') !== false) {
        $siteBaseUrl = 'https://web.prigent.tech';
    }

    $config = [
        'env'              => $env['APP_ENV']   ?? 'development',
        'debug'            => ($env['APP_DEBUG'] ?? 'true') === 'true',
        'url'              => $env['APP_URL']   ?? 'http://localhost:8080',
        'api_url'          => $env['API_URL']   ?? 'http://localhost:8080/api',
        'site_output_dir'  => $env['SITE_OUTPUT_DIR'] ?? '../htdocs',
        'site_base_url'    => $siteBaseUrl,
        'jwt_secret'       => $env['JWT_SECRET'] ?? 'change_me_in_production',
        'mail_from'          => $env['MAIL_FROM'] ?? 'noreply@webiartisan.prigent.tech',
    ];

    return $config;
}
