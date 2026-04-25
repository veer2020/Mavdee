<?php

/**
 * POST /api/orders/place.php
 *
 * Accepts a JSON body with shipping details, reads the current user's cart,
 * inserts an order (with items) inside a transaction, clears the cart, and
 * returns a JSON response.
 *
 * Required JSON fields: name, email, phone, address, city, pincode
 * Optional JSON fields: state
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../includes/email.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Require an active session / login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

csrf_check();

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

$userId  = getUserId();
$name    = trim($input['name']    ?? '');
$email   = trim($input['email']   ?? '');
$phone   = trim($input['phone']   ?? '');
$address = trim($input['address'] ?? '');
$city    = trim($input['city']    ?? '');
$state   = trim($input['state']   ?? '');
$pincode = trim($input['pincode'] ?? '');
$paymentMethod = in_array($input['payment_method'] ?? '', ['COD', 'Razorpay'], true)
    ? $input['payment_method']
    : 'COD';
$paymentId = trim($input['payment_id'] ?? '');

// Validate required fields
if (!$name || !$phone || !$address || !$city || !$pincode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please fill in all required shipping details']);
    exit;
}

if (strlen($address) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a complete delivery address (minimum 10 characters)']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address']);
    exit;
}

// Determine correct column names based on schema
$cartCols = cart_schema_columns();
$userCol  = in_array('customer_id', $cartCols) ? 'customer_id' : 'user_id';
$qtyCol   = in_array('qty', $cartCols) ? 'qty' : 'quantity';

// Fetch cart items
try {
    $stmt = db()->prepare(
        "SELECT c.product_id, c.$qtyCol AS qty, c.size, c.color,
                p.name AS product_name, p.sku, p.price, p.sale_price, p.stock
         FROM cart c
         JOIN products p ON c.product_id = p.id
         WHERE c.$userCol = ?"
    );
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve cart']);
    exit;
}

if (empty($cartItems)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cart is empty']);
    exit;
}

// Calculate order totals
if (!defined('TAX_RATE'))                define('TAX_RATE', 0.18);
if (!defined('FREE_SHIPPING_THRESHOLD')) define('FREE_SHIPPING_THRESHOLD', 999);
if (!defined('SHIPPING_COST'))           define('SHIPPING_COST', 99);

$subtotal   = 0.0;
$orderItems = [];

foreach ($cartItems as $item) {
    $qty        = (int)$item['qty'];
    $unitPrice  = (float)($item['sale_price'] ?: $item['price']);
    $lineTotal  = $unitPrice * $qty;
    $subtotal  += $lineTotal;

    $orderItems[] = [
        'product_id'   => (int)$item['product_id'],
        'product_name' => $item['product_name'],
        'sku'          => $item['sku'] ?? '',
        'size'         => $item['size'] ?? '',
        'color'        => $item['color'] ?? '',
        'qty'          => $qty,
        'unit_price'   => $unitPrice,
        'total_price'  => $lineTotal,
    ];
}

$shippingAmount = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0.0 : (float)SHIPPING_COST;
$taxAmount      = round($subtotal * TAX_RATE, 2);
$total          = round($subtotal + $taxAmount + $shippingAmount, 2);

// Generate a unique order number
$orderNumber = 'ORD-' . strtoupper(dechex(time())) . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

try {
    $pdo = db();
    $pdo->beginTransaction();

    // Insert order record
    $paymentStatus = ($paymentMethod === 'Razorpay' && $paymentId !== '') ? 'paid' : 'pending';
    $stmt = $pdo->prepare(
        "INSERT INTO orders
            (order_number, customer_id, customer_name, customer_email, customer_phone,
             shipping_address, city, state, pincode,
             subtotal, tax_amount, shipping_amount, discount_amount, total,
             payment_method, payment_status, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', NOW())"
    );
    $stmt->execute([
        $orderNumber,
        $userId,
        $name,
        $email,
        $phone,
        $address,
        $city,
        $state,
        $pincode,
        $subtotal,
        $taxAmount,
        $shippingAmount,
        $total,
        $paymentMethod,
        $paymentStatus,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Insert order items and decrement stock
    $stmtItem  = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, product_name, sku, size, color, qty, unit_price, total_price)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtStock = $pdo->prepare(
        "UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ? AND stock >= ?"
    );

    foreach ($orderItems as $oi) {
        $stmtItem->execute([
            $orderId,
            $oi['product_id'],
            $oi['product_name'],
            $oi['sku'],
            $oi['size'],
            $oi['color'],
            $oi['qty'],
            $oi['unit_price'],
            $oi['total_price'],
        ]);
        $affected = $stmtStock->execute([$oi['qty'], $oi['qty'], $oi['product_id'], $oi['qty']]);
        if ($stmtStock->rowCount() === 0) {
            throw new RuntimeException("Insufficient stock for product ID {$oi['product_id']}");
        }
    }

    // Clear the user's cart
    $pdo->prepare("DELETE FROM cart WHERE $userCol = ?")->execute([$userId]);

    $pdo->commit();

    // Send order confirmation email (non-blocking)
    try {
        sendOrderPlacedEmail($orderId, $email);
    } catch (Throwable) {
        // Email failure must never block order success response
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Order placed successfully.',
        'order_id' => $orderNumber
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $userMessage = str_starts_with($e->getMessage(), 'Insufficient stock')
        ? 'One or more items in your cart are out of stock. Please update your cart and try again.'
        : 'Order failed. Please try again.';
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $userMessage]);
}
