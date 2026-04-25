<?php

/**
 * admin/orders/label_delhivery.php
 * Streams the Delhivery shipping-label PDF for an order.
 * The Authorization token is passed server-side so it never appears in the browser URL.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../api/shipping/delhivery.php';
requireAdminLogin();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    setFlash('danger', 'Invalid order ID.');
    header('Location: index.php');
    exit;
}

$token = $_GET['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    setFlash('danger', 'Invalid security token.');
    header("Location: view.php?id=$orderId");
    exit;
}

try {
    $order = db_row("SELECT tracking_number, courier, order_number FROM orders WHERE id = ? LIMIT 1", [$orderId]);
} catch (Throwable) {
    $order = null;
}

if (!$order || empty($order['tracking_number'])) {
    setFlash('danger', 'No Delhivery waybill found for this order.');
    header("Location: view.php?id=$orderId");
    exit;
}

$dlv = new Delhivery();
$res = $dlv->downloadDocument($order['tracking_number'], 'label');

if (!$res['success']) {
    setFlash('danger', 'Could not retrieve label from Delhivery. ' . ($res['error'] ?? ''));
    header("Location: view.php?id=$orderId");
    exit;
}

$safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $order['order_number'] ?? 'label');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="label_' . $safeNumber . '.pdf"');
header('Content-Length: ' . strlen($res['content']));
echo $res['content'];
