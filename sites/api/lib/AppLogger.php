<?php
/**
 * WebIArtisan API — Logger applicatif basé sur Monolog.
 *
 * Écrit les logs dans storage/logs/api-YYYY-MM-DD.log
 * Le dossier storage est à la racine du projet API (__DIR__ . '/../storage').
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class AppLogger
{
    private static ?Logger $logger = null;

    public static function getInstance(): Logger
    {
        if (self::$logger === null) {
            $logDir = self::resolveLogDir();
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $date = date('Y-m-d');
            $logFile = $logDir . '/api-' . $date . '.log';

            $logger = new Logger('webiartisan-api');

            $handler = new StreamHandler($logFile, Logger::DEBUG);
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            // Un problème d'écriture de log ne doit jamais faire échouer une requête
            $logger->pushHandler(new \Monolog\Handler\WhatFailureGroupHandler([$handler]));
            self::$logger = $logger;
        }

        return self::$logger;
    }

    /**
     * Résout le répertoire de logs.
     * En production Gandi, le dossier storage est au même niveau que htdocs.
     * En local Docker, il est dans sites/api/storage.
     */
    private static function resolveLogDir(): string
    {
        // Production Gandi: htdocs/lib/AppLogger.php -> ../.. -> storage/logs
        $prodDir = __DIR__ . '/../../storage/logs';
        if (is_dir(dirname($prodDir))) {
            return $prodDir;
        }

        // Fallback local: sites/api/storage/logs
        $localDir = __DIR__ . '/../storage/logs';
        return $localDir;
    }
}

/**
 * Helper rapide pour logger une ligne avec contexte.
 */
function app_log(string $level, string $message, array $context = []): void
{
    $logger = AppLogger::getInstance();
    switch (strtolower($level)) {
        case 'debug':
            $logger->debug($message, $context);
            break;
        case 'info':
            $logger->info($message, $context);
            break;
        case 'notice':
            $logger->notice($message, $context);
            break;
        case 'warning':
            $logger->warning($message, $context);
            break;
        case 'error':
            $logger->error($message, $context);
            break;
        case 'critical':
            $logger->critical($message, $context);
            break;
        case 'alert':
            $logger->alert($message, $context);
            break;
        case 'emergency':
            $logger->emergency($message, $context);
            break;
        default:
            $logger->info($message, $context);
    }
}
