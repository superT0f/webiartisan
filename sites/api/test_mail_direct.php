<?php
// Standalone test file to bypass routing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load unified Logger - handle both local and production paths
$adminLoggerPath = __DIR__ . '/../admin/lib/Logger.php';
if (!file_exists($adminLoggerPath)) {
    // Production: admin is in a different vhost
    $adminLoggerPath = '/srv/data/web/vhosts/admin.prigent.tech/htdocs/lib/Logger.php';
}

echo "Checking Logger at: $adminLoggerPath<br>";
if (file_exists($adminLoggerPath)) {
    require_once $adminLoggerPath;
    $logger = Logger::getInstance();
    echo "Logger loaded successfully.<br>";
} else {
    echo "Logger NOT found, using fallback stub.<br>";
    $logger = new class {
        public function info($m, $c = []) { echo "STUB INFO: $m<br>"; }
        public function debug($m, $c = []) { echo "STUB DEBUG: $m<br>"; }
        public function error($m, $c = []) { echo "STUB ERROR: $m<br>"; }
        public function warning($m, $c = []) { echo "STUB WARNING: $m<br>"; }
    };
}

$to = $_GET['to'] ?? 'Supert0f@proton.me';
$subject = "Test Direct Apache - " . date('H:i:s');
$message = "Test direct mail() depuis Apache.\nTime: " . date('c');
$headers = "From: noreply@webiartisan.prigent.tech\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

echo "Sending mail to: $to<br>";
$sent = mail($to, $subject, $message, $headers, "-fnoreply@webiartisan.prigent.tech");

if ($sent) {
    echo "Mail SENT successfully.<br>";
    $logger->info("Test direct mail SENT", ['to' => $to]);
} else {
    echo "Mail FAILED.<br>";
    $logger->error("Test direct mail FAILED", ['to' => $to]);
}
