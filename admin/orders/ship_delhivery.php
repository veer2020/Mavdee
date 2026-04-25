<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../api/shipping/delhivery.php';
require_once __DIR__ . '/../../includes/email.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_check();

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    setFlash('danger', 'Invalid order ID.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
} catch (Throwable) {
    $order = null;
}

if (!$order) {
    setFlash('danger', 'Order not found.');
    header('Location: index.php');
    exit;
}

if (!empty($order['tracking_number'])) {
    setFlash('danger', 'This order already has a tracking number.');
    header("Location: view.php?id=$orderId");
    exit;
}

// Decode shipping address — use separate DB columns as fallback
$shipping = [];
if (!empty($order['shipping_address'])) {
    $decoded = json_decode($order['shipping_address'], true);
    $shipping = is_array($decoded) ? $decoded : ['address' => $order['shipping_address']];
}
// Ensure city/state/pincode are present even when address was stored as plain text
if (empty($shipping['city']))    $shipping['city']    = $order['city']    ?? '';
if (empty($shipping['state']))   $shipping['state']   = $order['state']   ?? '';
if (empty($shipping['pincode'])) $shipping['pincode'] = $order['pincode'] ?? '';

// Fetch order items
try {
    $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
} catch (Throwable) {
    $items = [];
}

// Build order data for Delhivery
$orderData = [
    'order_number'    => $order['order_number'],
    'customer_name'   => $order['customer_name'],
    'customer_phone'  => $order['customer_phone'] ?? '',
    'customer_email'  => $order['customer_email'],
    'shipping_address' => $shipping,
    'total'           => (float)$order['total'],
    'payment_method'  => $order['payment_method'] ?? 'online',
    'weight'          => (int)($_POST['weight'] ?? 500),
    'shipping_mode'   => $_POST['shipping_mode'] ?? 'Surface',
    'items'           => array_map(fn($i) => [
        'product_name' => $i['product_name'],
        'qty'          => $i['qty'],
        'price'        => $i['unit_price'] ?? $i['price'] ?? 0,
    ], $items),
];

$result = (new Delhivery())->createShipment($orderData);

if (!$result['success']) {
    setFlash('danger', 'Delhivery shipment failed: ' . ($result['error'] ?? 'Unknown error.'));
    header("Location: view.php?id=$orderId");
    exit;
}

$waybill = $result['waybill'];

try {
    db()->prepare(
        "UPDATE orders SET tracking_number=?, courier='Delhivery', status='shipped' WHERE id=?"
    )->execute([$waybill, $orderId]);

    db()->prepare(
        "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
         VALUES (?, 'shipped', ?, ?, NOW())"
    )->execute([$orderId, 'Shipped via Delhivery. Waybill: ' . $waybill, getAdminId()]);

    // Update order with tracking info for the email notification
    $order['tracking_number'] = $waybill;
    $order['courier']         = 'Delhivery';

    // Send order shipped notification to the customer
    $mailer = new EmailHandler();
    $mailer->sendOrderStatusUpdate($order['customer_email'], $order, 'shipped');

    logAdminActivity('delhivery_shipment', "Order ID $orderId shipped. Waybill: $waybill");
    setFlash('success', 'Shipment created! Delhivery waybill: ' . $waybill);
} catch (Throwable $e) {
    setFlash('warning', 'Shipment created (waybill: ' . $waybill . ') but DB update failed: ' . $e->getMessage());
}

header("Location: view.php?id=$orderId");
exit;
