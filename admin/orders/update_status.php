<?php
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

$orderId        = (int)($_POST['order_id'] ?? 0);
$newStatus      = trim($_POST['status'] ?? '');
$note           = trim($_POST['note'] ?? '');
$trackingNumber = trim($_POST['tracking_number'] ?? '');
$courier        = trim($_POST['courier'] ?? '');

$validStatuses = ['pending', 'processing', 'dispatched', 'shipped', 'delivered', 'cancelled'];

if ($orderId <= 0 || !in_array($newStatus, $validStatuses)) {
    setFlash('danger', 'Invalid request.');
    header('Location: index.php');
    exit;
}

try {
    // ── Build the status-specific timestamp fragment ───────────────────────
    // $timestampSQL is appended to the UPDATE; it uses SQL NOW() directly
    // so no extra bound parameters are needed ($extraParms removed to avoid confusion).
    $timestampSQL = '';
    switch ($newStatus) {
        case 'processing':
            $timestampSQL = ', processed_at = COALESCE(processed_at, NOW())';
            break;
        case 'dispatched':
        case 'shipped':
            $timestampSQL = ', dispatched_at = COALESCE(dispatched_at, NOW())';
            break;
        case 'delivered':
            $timestampSQL = ', delivered_at = COALESCE(delivered_at, NOW())';
            break;
        case 'cancelled':
            $timestampSQL = ', cancelled_at = COALESCE(cancelled_at, NOW())';
            break;
    }

    // ── Update order ──────────────────────────────────────────────────────
    db()->prepare(
        "UPDATE orders
            SET status          = ?,
                tracking_number = ?,
                courier         = ?
                {$timestampSQL}
          WHERE id = ?"
    )->execute([$newStatus, $trackingNumber ?: null, $courier ?: null, $orderId]);

    // ── Status history ─────────────────────────────────────────────────────
    db()->prepare(
        "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    )->execute([$orderId, $newStatus, $note ?: null, getAdminId()]);

    logAdminActivity('update_order_status', "Order ID $orderId → $newStatus");
    setFlash('success', 'Order status updated to "' . ucfirst($newStatus) . '".');

    // ── FIX: fetch the order row ONCE and reuse it everywhere below ────────
    $orderRow = db_row("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId]);

    // ── Customer status-update email ───────────────────────────────────────
    if ($orderRow) {
        $mailer = new EmailHandler();
        if ($mailer->customerNotifyEnabled() && !empty($orderRow['customer_email'])) {
            $mailer->sendOrderStatusUpdate($orderRow['customer_email'], $orderRow, $newStatus);
        }
    }

    // ── In-app notification for the customer ──────────────────────────────
    if ($orderRow && !empty($orderRow['customer_id'])) {
        $msgTemplates = [
            'processing' => 'Your order #{num} is being processed.',
            'dispatched' => 'Your order #{num} has been dispatched! 🚚',
            'shipped'    => 'Your order #{num} is on the way! 🚚',
            'delivered'  => 'Your order #{num} has been delivered. 🎉',
            'cancelled'  => 'Your order #{num} has been cancelled.',
        ];
        $msgText = str_replace(
            '{num}',
            $orderRow['order_number'],
            $msgTemplates[$newStatus] ?? 'Your order #{num} status has been updated to ' . $newStatus . '.'
        );
        createNotification(
            (int)$orderRow['customer_id'],
            $msgText,
            'order_update',
            '/order-details.php?id=' . $orderId
        );
    }

    // ── Auto-create Delhivery shipment when status → shipped/dispatched
    //    and no tracking number was supplied manually ────────────────────────
    if (in_array($newStatus, ['shipped', 'dispatched'], true) && empty($trackingNumber) && empty($orderRow['tracking_number']) && $orderRow) {
        $dlvSettings = getPaymentSettings('delhivery');
        if (!empty($dlvSettings['enabled']) && !empty($dlvSettings['token'])) {

            $shippingAddress = [];
            if (!empty($orderRow['shipping_address'])) {
                $decoded = json_decode($orderRow['shipping_address'], true);
                $shippingAddress = is_array($decoded) ? $decoded : ['address' => $orderRow['shipping_address']];
            }
            if (empty($shippingAddress['city']))    $shippingAddress['city']    = $orderRow['city']    ?? '';
            if (empty($shippingAddress['state']))   $shippingAddress['state']   = $orderRow['state']   ?? '';
            if (empty($shippingAddress['pincode'])) $shippingAddress['pincode'] = $orderRow['pincode'] ?? '';

            $orderItems = db_rows("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

            $orderData = [
                'order_number'     => $orderRow['order_number'],
                'customer_name'    => $orderRow['customer_name'],
                'customer_phone'   => $orderRow['customer_phone'] ?? '',
                'customer_email'   => $orderRow['customer_email'],
                'shipping_address' => $shippingAddress,
                'total'            => (float)$orderRow['total'],
                'payment_method'   => $orderRow['payment_method'] ?? 'online',
                'weight'           => 500,
                'shipping_mode'    => 'Surface',
                'items'            => array_map(fn($i) => [
                    'product_name' => $i['product_name'],
                    'qty'          => $i['qty'],
                    'price'        => $i['unit_price'] ?? $i['price'] ?? 0,
                ], $orderItems),
            ];

            $dlvResult = (new Delhivery())->createShipment($orderData);

            if ($dlvResult['success'] && !empty($dlvResult['waybill'])) {
                $waybill = $dlvResult['waybill'];
                db()->prepare(
                    "UPDATE orders SET tracking_number = ?, courier = 'Delhivery' WHERE id = ?"
                )->execute([$waybill, $orderId]);
                db()->prepare(
                    "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
                     VALUES (?, 'shipped', ?, ?, NOW())"
                )->execute([$orderId, 'Auto-shipped via Delhivery. Waybill: ' . $waybill, getAdminId()]);
                logAdminActivity('delhivery_auto_shipment', "Order ID $orderId waybill: $waybill");
                setFlash('success', 'Order shipped and Delhivery waybill created: ' . $waybill);
            }
        }
    }
} catch (Throwable $e) {
    setFlash('danger', 'Failed to update order: ' . $e->getMessage());
}

header("Location: view.php?id=$orderId");
exit;
