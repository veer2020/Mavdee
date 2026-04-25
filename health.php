<?php
/**
 * health.php
 * Returns a JSON health-check report for monitoring tools.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

$status = ['status' => 'healthy', 'checks' => [], 'timestamp' => date('c')];

// Database check
try {
    db()->query('SELECT 1');
    $status['checks']['database'] = 'ok';
} catch (\Throwable $e) {
    $status['status'] = 'unhealthy';
    $status['checks']['database'] = 'connection_failed';
    // Log the real error server-side without exposing it in the response
    error_log('Health check DB error: ' . $e->getMessage());
}

// Storage (uploads) check
$uploadDir = __DIR__ . '/uploads';
$status['checks']['storage'] = is_writable($uploadDir) ? 'ok' : 'not_writable';

// Logs directory writable check
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);
$status['checks']['logs'] = is_writable($logsDir) ? 'ok' : 'not_writable';

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
