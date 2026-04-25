<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
        exit;
    }

    $csrfToken = $input['csrf_token'] ?? '';
    if (empty($csrfToken) || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh and try again.']);
        exit;
    }

    $code     = strtoupper(trim($input['code'] ?? ''));
    $subtotal = (float)($input['subtotal'] ?? 0);

    if ($code === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Coupon code is required.']);
        exit;
    }

    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired coupon.']);
        exit;
    }

    // Check expiry
    if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'This coupon has expired.']);
        exit;
    }

    // Check usage limit
    if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'error' => 'This coupon has reached its usage limit.']);
        exit;
    }

    // Check minimum order
    if ($coupon['min_order'] > 0 && $subtotal < (float)$coupon['min_order']) {
        echo json_encode(['success' => false, 'error' => 'Minimum order of ₹' . number_format($coupon['min_order'], 0) . ' required.']);
        exit;
    }

    // Calculate discount
    $discount = 0;
    if ($coupon['type'] === 'percent') {
        $discount = round($subtotal * ((float)$coupon['value'] / 100), 2);
        if ($coupon['max_discount'] > 0 && $discount > (float)$coupon['max_discount']) {
            $discount = (float)$coupon['max_discount'];
        }
        $message = $coupon['value'] . '% discount applied!';
    } else {
        $discount = (float)$coupon['value'];
        if ($discount > $subtotal) {
            $discount = $subtotal;
        }
        $message = '₹' . number_format($discount, 0) . ' discount applied!';
    }

    echo json_encode([
        'success'  => true,
        'discount' => $discount,
        'type'     => $coupon['type'],
        'value'    => (float)$coupon['value'],
        'message'  => $message,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
