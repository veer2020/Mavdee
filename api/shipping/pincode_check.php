<?php

/**
 * api/shipping/pincode_check.php
 * POST JSON — Check Delhivery serviceability for a pincode.
 * Body: { "pincode": "400001" }
 * Returns: { "serviceable": bool, "cod": bool, "prepaid": bool } or { "error": "..." }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/delhivery.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$pincode = trim($input['pincode'] ?? '');

if (!preg_match('/^\d{6}$/', $pincode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid 6-digit Indian pincode.']);
    exit;
}

$dlvSettings = getPaymentSettings('delhivery');

// Fallback response used when Delhivery is unconfigured or unreachable — allows checkout to proceed
$fallback = json_encode(['serviceable' => true, 'cod' => true, 'prepaid' => true, 'source' => 'fallback']);

if (empty($dlvSettings['enabled']) || empty($dlvSettings['token'])) {
    log_error("Delhivery not configured. Pincode check fallback for $pincode");
    echo $fallback;
    exit;
}

$dlv = new Delhivery();
$result = $dlv->checkServiceability($pincode);

if (!$result['success'] || !isset($result['results'][$pincode])) {
    log_error("Delhivery serviceability check failed for $pincode. Fallback used. Error: " . ($result['error'] ?? ''));
    echo $fallback;
    exit;
}

$pinInfo = $result['results'][$pincode];
echo json_encode([
    'serviceable' => $pinInfo['serviceable'],
    'cod'         => $pinInfo['cod'],
    'prepaid'     => $pinInfo['prepaid'],
    'source'      => 'delhivery',
]);
