<?php
/**
 * api/crm/trigger_abandoned_cart.php
 * CLI-safe script to send abandoned cart emails.
 * Cron usage:  php /path/to/api/crm/trigger_abandoned_cart.php
 * HTTP usage:  GET /api/crm/trigger_abandoned_cart.php?token=<CRON_SECRET>
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

// Security: CLI only, or valid cron secret token
if (php_sapi_name() !== 'cli' && ($_GET['token'] ?? '') !== CRON_SECRET) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden.']);
    exit;
}

// Delegate to abandoned_cart.php script
require_once dirname(__DIR__, 2) . '/abandoned_cart.php';
