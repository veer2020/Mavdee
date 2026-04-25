<?php

declare(strict_types=1);

/**
 * api/shipping/label-proxy.php
 *
 * Admin-only endpoint that proxies the Delhivery label PDF so that the API
 * token is never exposed to the browser.
 *
 * Usage: GET /api/shipping/label-proxy.php?waybills=AWB123&csrf_token=TOKEN
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';
require_once dirname(__DIR__, 2) . '/admin/_auth.php';
require_once __DIR__ . '/delhivery.php';

requireAdminLogin();
csrf_check();

$waybills = trim($_GET['waybills'] ?? '');
if ($waybills === '') {
    http_response_code(400);
    echo 'Missing waybills parameter.';
    exit;
}

// Validate: allow only alphanumeric, comma, and hyphen to prevent injection
if (!preg_match('/^[A-Z0-9,\-]+$/i', $waybills)) {
    http_response_code(400);
    echo 'Invalid waybills format.';
    exit;
}

$dlv    = new Delhivery();
$result = $dlv->downloadDocument($waybills, 'label');

if (!$result['success']) {
    http_response_code(502);
    echo 'Label download failed: ' . htmlspecialchars($result['error'] ?? 'Unknown error.', ENT_QUOTES, 'UTF-8');
    exit;
}

$filename = 'label-' . preg_replace('/[^A-Z0-9,\-]/i', '_', $waybills) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');
header('Content-Length: ' . strlen((string)$result['content']));

echo $result['content'];
