<?php
/**
 * api/payment/verify.php
 * POST — Verifies Razorpay payment signature server-side.
 * Accepts: razorpay_order_id, razorpay_payment_id, razorpay_signature
 * Returns: { "success": true/false }
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';
require_once dirname(__DIR__, 2) . '/includes/payment.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// CSRF check
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$rzpOrderId  = trim($_POST['razorpay_order_id']  ?? '');
$rzpPayId    = trim($_POST['razorpay_payment_id'] ?? '');
$rzpSig      = trim($_POST['razorpay_signature']  ?? '');

if ($rzpOrderId === '' || $rzpPayId === '' || $rzpSig === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing payment parameters.']);
    exit;
}

$rzp    = getPaymentSettings('razorpay');
$secret = $rzp['key_secret'] ?? '';

if ($secret === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Payment configuration error.']);
    exit;
}

$valid = PaymentVerifier::verifyRazorpaySignature($rzpOrderId, $rzpPayId, $rzpSig, $secret);

echo json_encode(['success' => $valid]);
