<?php
/**
 * includes/logger.php
 * ErrorLogger — Daily rotating JSON log files + global exception handler.
 */
declare(strict_types=1);

class ErrorLogger
{
    private static ?string $logFile = null;

    public static function init(): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        self::$logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::$logFile) self::init();

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message'   => $message,
            'context'   => $context,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'url'       => $_SERVER['REQUEST_URI'] ?? 'cli',
        ];

        error_log(json_encode($entry) . PHP_EOL, 3, self::$logFile);
    }

    public static function logException(\Throwable $e): void
    {
        self::log($e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

// Register global exception handler
set_exception_handler(function (\Throwable $e): void {
    ErrorLogger::logException($e);

    if ((getenv('APP_ENV') ?: 'production') === 'production') {
        http_response_code(500);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 'An error occurred. Please try again later.']);
    } else {
        throw $e;
    }
});
