<?php
/**
 * api/payment/create_order.php
 * POST — Creates a Razorpay order server-side.
 * Requires: auth or active guest checkout, CSRF token in header or POST body.
 * Returns: { "order_id": "...", "amount": ..., "currency": "INR", "key": "..." }
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';
require_once dirname(__DIR__, 2) . '/includes/payment.php';

header('Content-Type: application/json; charset=utf-8');

// Auth guard — allow logged-in users and active guest checkout sessions
$isGuestCheckout = !isLoggedIn() && !empty($_SESSION['guest_checkout']);
if (!isLoggedIn() && !$isGuestCheckout) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated.']);
    exit;
}

// CSRF (accept token from header or POST body)
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$rzp = getPaymentSettings('razorpay');
if (empty($rzp['enabled']) || empty($rzp['key_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Razorpay is not enabled.']);
    exit;
}

// Calculate order total from active cart (DB cart for logged-in, session for guests)
$userId  = getUserId();
$cols    = cart_schema_columns();
$userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
$qtyCol  = in_array('qty', $cols) ? 'qty' : 'quantity';

if ($isGuestCheckout) {
    // Guest cart stored in $_SESSION['guest_cart']
    $guestCart = $_SESSION['guest_cart'] ?? [];
    if (empty($guestCart)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty.']);
        exit;
    }
    // Filter to items with valid integer product_id only
    $productIds = array_values(array_filter(
        array_map(fn($i) => isset($i['product_id']) ? (int)$i['product_id'] : 0, $guestCart),
        fn($id) => $id > 0
    ));
    if (empty($productIds)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty.']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    try {
        $pStmt = db()->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
        $pStmt->execute($productIds);
        $prices = array_column($pStmt->fetchAll(PDO::FETCH_ASSOC), 'price', 'id');
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not retrieve cart.']);
        exit;
    }
    $subtotal = 0.0;
    foreach ($guestCart as $cartItem) {
        $pid = isset($cartItem['product_id']) ? (int)$cartItem['product_id'] : 0;
        if ($pid <= 0) continue;
        $qty = (int)($cartItem['qty'] ?? $cartItem['quantity'] ?? 1);
        $subtotal += (float)($prices[$pid] ?? 0) * $qty;
    }
} else {
    try {
        $stmt = db()->prepare(
            "SELECT c.$qtyCol as qty, p.price
               FROM cart c
               JOIN products p ON c.product_id = p.id
              WHERE c.$userCol = ?"
        );
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not retrieve cart.']);
        exit;
    }

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty.']);
        exit;
    }
    $subtotal = array_sum(array_map(fn($i) => (float)$i['price'] * (int)$i['qty'], $items));
}

$freeShippingAbove = (float)(getSetting('free_shipping_above', 999) ?: 999);
$stdShippingCost   = (float)(getSetting('shipping_cost', 99)         ?: 99);
$shipping = $subtotal >= $freeShippingAbove ? 0.0 : $stdShippingCost;
$total    = $subtotal + $shipping;

$receiptId = $isGuestCheckout ? ('guest_' . time()) : ($userId . '_' . time());
$receipt   = 'rcpt_' . $receiptId;
$order     = PaymentVerifier::createRazorpayOrder($total, 'INR', $receipt, $rzp);

if ($order === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to create payment order. Please try again.']);
    exit;
}

echo json_encode([
    'order_id' => $order['id'],
    'amount'   => $order['amount'],
    'currency' => $order['currency'],
    'key'      => $rzp['key_id'],
]);
