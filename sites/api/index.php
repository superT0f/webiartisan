<?php
/**
 * WebIArtisan API — Front Controller (Livry POC)
 * All requests are routed through this file via .htaccess rewrite.
 *
 * URL pattern: /api/{module}/{action}[/{param}]
 * Example:     /api/cities/livry
 *              /api/artisans/register
 */

$appEnv = $_ENV['APP_ENV'] ?? 'production';
$isDev = $appEnv === 'development' || $appEnv === 'dev';

error_reporting(E_ALL);
ini_set('display_errors', $isDev ? 1 : 0);
ini_set('log_errors', 1);

// Ensure consistent timezone for streaks and cooldowns
date_default_timezone_set('Europe/Paris');

// Load unified Logger - handle both local and production paths
$adminLoggerPath = __DIR__ . '/../admin/lib/Logger.php';
if (!file_exists($adminLoggerPath)) {
    // Production: admin is in a different vhost
    $adminLoggerPath = '/srv/data/web/vhosts/admin.prigent.tech/htdocs/lib/Logger.php';
}
if (file_exists($adminLoggerPath)) {
    require_once $adminLoggerPath;
    $logger = Logger::getInstance();
} else {
    // Fallback: create a simple logger stub
    $logger = new class {
        public function info($m, $c = []) { }
        public function debug($m, $c = []) { }
        public function error($m, $c = []) { error_log($m); }
        public function warning($m, $c = []) { }
    };
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load core
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/middleware/Cors.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/Tenant.php';
require_once __DIR__ . '/middleware/PlanQuota.php';
require_once __DIR__ . '/middleware/RateLimit.php';

// Load environment variables globally (docker-compose env vars take precedence)
$env = loadEnv(__DIR__ . '/.env');
foreach ($env as $key => $value) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        continue;
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        continue;
    }
    if (getenv($key) !== false && getenv($key) !== '') {
        continue;
    }
    $_ENV[$key] = $value;
}

$appEnv = $_ENV['APP_ENV'] ?? 'production';
$jwtSecret = $_ENV['JWT_SECRET'] ?? '';
if ($appEnv === 'production' && (strlen($jwtSecret) < 32)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'error' => 'Configuration error: JWT_SECRET too weak']);
    exit;
}

// Database connection used by rate limiting and route files
$pdo = getDatabase();

// Global exception handler — return JSON for API requests
set_exception_handler(function (Throwable $e) use ($logger): void {
    $logger->error('Unhandled exception', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error'   => 'Erreur serveur',
    ]);
});

// Handle CORS
handleCors();

if (isset($logger)) {
    $logger->debug('API Request', [
        'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'r_get' => $_GET['r'] ?? 'N/A',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A'
    ]);
}

// Parse the request path - support both URL rewriting and query string
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = '/api';

// Check for query string routing (Gandi compatible: ?r=module/action/param)
if (isset($_GET['r']) && !empty($_GET['r'])) {
    $path = $_GET['r'];
} else {
    // Strip query string from URL
    $path = parse_url($requestUri, PHP_URL_PATH);
    if ($path === null) $path = '/';
}

if (isset($logger)) {
    $logger->debug('Raw Path Info', [
        'requestUri' => $requestUri,
        'basePath' => $basePath,
        'path_before_ltrim' => $path
    ]);
}

// Robust removal of base path /api
$path = '/' . ltrim($path, '/');
if (strpos($path, $basePath . '/') === 0) {
    $path = substr($path, strlen($basePath));
} elseif ($path === $basePath) {
    $path = '/';
}

$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

if (isset($logger)) {
    $logger->debug('API Routing', [
        'path' => $path,
        'segments' => $segments
    ]);
}

$module = $segments[0] ?? '';
$action = $segments[1] ?? '';
$param  = $segments[2] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

// Route map

if ($module === 'auth') {
    $flutterActions = ['login', 'send-code', 'verify-code', 'villes'];
    if (in_array($action, $flutterActions, true)) {
        applyRateLimit($pdo, 'login');
        require_once __DIR__ . '/routes/flutter-auth.php';
        exit;
    }

    $sensitiveActions = ['lookup', 'request-code', 'verify-code', 'register', 'biometric-login', 'sso-verify'];
    applyRateLimit($pdo, in_array($action, $sensitiveActions, true) ? 'login' : 'public');
    $auth = new Auth();
    require_once __DIR__ . '/routes/auth.php';
    exit;
}

// Routes publiques artisans locaux (pas d'auth requise pour lecture)
if ($module === 'cities') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/cities.php';
    exit;
}

if ($module === 'artisans') {
    // Rate limit plus strict sur le register
    $rlEndpoint = ($action === 'register') ? 'login' : 'public';
    applyRateLimit($pdo, $rlEndpoint);
    require_once __DIR__ . '/routes/artisans.php';
    exit;
}

if ($module === 'prospects') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/prospects.php';
    exit;
}

if ($module === 'recipes') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/recipes.php';
    exit;
}

if ($module === 'testimonials') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/testimonials.php';
    exit;
}

if ($module === 'service-catalog') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/services.php';
    exit;
}

if ($module === 'users') {
    require_once __DIR__ . '/routes/users.php';
    exit;
}

if ($module === 'avatars') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/avatars.php';
    exit;
}

// Backward compatibility: old /auth/avatar/:file URLs redirect to static uploads
if ($module === 'auth' && $action === 'avatar' && $method === 'GET' && $param !== null) {
    header('Location: /uploads/avatars/' . basename($param), true, 301);
    exit;
}

if ($module === 'spin') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/spin.php';
    exit;
}

if ($module === 'cron') {
    require_once __DIR__ . '/routes/cron.php';
    exit;
}

if ($module === 'games') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/games.php';
    exit;
}

if ($module === 'actions') {
    applyRateLimit($pdo, 'login');
    require_once __DIR__ . '/routes/actions.php';
    exit;
}

if ($module === 'gamification') {
    applyRateLimit($pdo, 'login');
    require_once __DIR__ . '/routes/gamification.php';
    exit;
}

if ($module === 'subscription') {
    applyRateLimit($pdo, 'login');
    require_once __DIR__ . '/routes/subscriptions.php';
    exit;
}

if ($module === 'admin') {
    applyRateLimit($pdo, 'login');
    require_once __DIR__ . '/routes/admin.php';
    exit;
}

if ($module === 'webhooks' && $action === 'stripe') {
    require_once __DIR__ . '/routes/webhooks.php';
    exit;
}

if ($module === 'weather') {
    applyRateLimit($pdo, 'public');
    require_once __DIR__ . '/routes/weather.php';
    exit;
}

// Route to the correct module
$routeFile = __DIR__ . "/routes/{$module}.php";

if ($module && file_exists($routeFile)) {
    $logger->info('API route loaded', [
        'module' => $module,
        'action' => $action,
        'method' => $method,
        'user_id' => null,
        'tenant_id' => null
    ]);
    require_once $routeFile;
} elseif ($module === '' || $module === 'health') {
    $dbHealthy = false;
    try {
        $pdo->query('SELECT 1');
        $dbHealthy = true;
    } catch (Exception $e) {
        $logger->error('Health check: database unreachable', ['error' => $e->getMessage()]);
    }

    $statusCode = $dbHealthy ? 200 : 503;
    http_response_code($statusCode);
    $logger->debug('Health check', ['healthy' => $dbHealthy]);
    echo json_encode([
        'success' => $dbHealthy,
        'service' => 'WebIArtisan API',
        'version' => '1.1.0',
        'database'=> $dbHealthy,
        'time'    => date('c'),
    ]);
} else {
    $logger->warning('Unknown API module', ['module' => $module, 'action' => $action]);
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => "Unknown module: $module",
    ]);
}
