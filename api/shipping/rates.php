<?php

/**
 * api/shipping/rates.php
 * Calculates shipping costs based on the user's pincode.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/shipping.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$destPin = sanitizeInput($_GET['pincode'] ?? '');
$weight  = (int)($_GET['weight'] ?? 500); // Default to 500g

if (empty($destPin) || strlen($destPin) !== 6 || !is_numeric($destPin)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid 6-digit Indian pincode is required.']);
    exit;
}

$shipping = new DelhiveryShipping();

if (!$shipping->isEnabled()) {
    echo json_encode(['success' => false, 'error' => 'Shipping module is temporarily unavailable.']);
    exit;
}

$rates = $shipping->checkRates($destPin, $weight);

echo json_encode([
    'success' => true,
    'rates' => $rates[0] ?? $rates // Format depends heavily on the specific Delhivery account setup
]);
