<?php

declare(strict_types=1);

/**
 * api/pincode-check.php
 *
 * Public AJAX endpoint for frontend pincode serviceability checks.
 * Supports both GET (?pin=395001) and POST (JSON body {"pin":"395001"}).
 * Results are cached in the pincode_cache table (TTL 24 h).
 *
 * Returns: { success, serviceable, cod, prepaid, city, state, pin }
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once __DIR__ . '/shipping/delhivery.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Read pin from GET or POST (JSON or form) ──────────────────────────────────
$pin = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pin = trim($_GET['pin'] ?? '');
} else {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw ?: '', true);
    if (is_array($input)) {
        $pin = trim($input['pin'] ?? $input['pincode'] ?? '');
    } else {
        $pin = trim($_POST['pin'] ?? $_POST['pincode'] ?? '');
    }
}

if (!preg_match('/^\d{6}$/', $pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid 6-digit pincode.']);
    exit;
}

// ── Check cache ───────────────────────────────────────────────────────────────
$cached = null;
try {
    $cached = db_row(
        "SELECT serviceable, cod, prepaid, city, state
         FROM pincode_cache
         WHERE pin = ? AND cached_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         LIMIT 1",
        [$pin]
    );
} catch (Throwable) {
    // Cache table may not exist yet — proceed without it
}

if ($cached !== null) {
    echo json_encode([
        'success'     => true,
        'serviceable' => (bool)$cached['serviceable'],
        'cod'         => (bool)$cached['cod'],
        'prepaid'     => (bool)$cached['prepaid'],
        'city'        => $cached['city']  ?? '',
        'state'       => $cached['state'] ?? '',
        'pin'         => $pin,
        'source'      => 'cache',
    ]);
    exit;
}

// ── Call Delhivery ────────────────────────────────────────────────────────────
$dlv    = new Delhivery();
$result = $dlv->checkServiceability($pin);

// Graceful fail-open: if the API is down, allow checkout to proceed
if (!$result['success']) {
    error_log('[pincode-check] Delhivery API unavailable for pin ' . $pin . ': ' . ($result['error'] ?? 'unknown'));
    echo json_encode([
        'success'     => true,
        'serviceable' => true,
        'cod'         => true,
        'prepaid'     => true,
        'city'        => '',
        'state'       => '',
        'pin'         => $pin,
        'source'      => 'fallback',
    ]);
    exit;
}

$info = $result['results'][$pin] ?? [
    'serviceable' => false,
    'cod'         => false,
    'prepaid'     => false,
    'city'        => '',
    'state'       => '',
];

// ── Write to cache ────────────────────────────────────────────────────────────
try {
    db_execute(
        "INSERT INTO pincode_cache (pin, serviceable, cod, prepaid, city, state, cached_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           serviceable = VALUES(serviceable),
           cod         = VALUES(cod),
           prepaid     = VALUES(prepaid),
           city        = VALUES(city),
           state       = VALUES(state),
           cached_at   = NOW()",
        [
            $pin,
            (int)$info['serviceable'],
            (int)$info['cod'],
            (int)$info['prepaid'],
            $info['city'],
            $info['state'],
        ]
    );
} catch (Throwable) {
    // Non-fatal — cache write failure must not break checkout
}

echo json_encode([
    'success'     => true,
    'serviceable' => $info['serviceable'],
    'cod'         => $info['cod'],
    'prepaid'     => $info['prepaid'],
    'city'        => $info['city'],
    'state'       => $info['state'],
    'pin'         => $pin,
    'source'      => 'api',
]);
