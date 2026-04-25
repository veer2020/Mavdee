<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../api/shipping/delhivery.php';
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
    $order = db_row("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);
} catch (Throwable) {
    $order = null;
}

if (!$order) {
    setFlash('danger', 'Order not found.');
    header("Location: view.php?id=$orderId");
    exit;
}

$waybill = $order['tracking_number'] ?? '';
if (empty($waybill) || strtolower($order['courier'] ?? '') !== 'delhivery') {
    setFlash('danger', 'No Delhivery waybill found for this order.');
    header("Location: view.php?id=$orderId");
    exit;
}

$dlv = new Delhivery();
$result = $dlv->cancelShipment($waybill);

if (!$result['success']) {
    setFlash('danger', 'Delhivery cancellation failed: ' . ($result['error'] ?? 'Unknown error.'));
    header("Location: view.php?id=$orderId");
    exit;
}

try {
    // FIX: also set status = 'cancelled' so the order doesn't stay as
    // 'shipped' with no waybill after the shipment is cancelled.
    db()->prepare(
        "UPDATE orders
            SET tracking_number = NULL,
                courier         = NULL,
                status          = 'cancelled',
                cancelled_at    = NOW()
          WHERE id = ?"
    )->execute([$orderId]);

    db()->prepare(
        "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
         VALUES (?, 'cancelled', ?, ?, NOW())"
    )->execute([
        $orderId,
        'Delhivery shipment cancelled by admin. Waybill was: ' . $waybill,
        getAdminId(),
    ]);

    logAdminActivity('delhivery_cancel', "Order ID $orderId — waybill $waybill cancelled on Delhivery.");
    setFlash('success', 'Delhivery shipment cancelled successfully (waybill: ' . $waybill . ').');
} catch (Throwable $e) {
    setFlash('warning', 'Shipment cancelled on Delhivery but DB update failed: ' . $e->getMessage());
}

header("Location: view.php?id=$orderId");
exit;
