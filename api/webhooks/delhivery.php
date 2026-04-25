<?php
/**
 * api/webhooks/delhivery.php
 *
 * Receives Delhivery shipment-status push notifications and updates
 * the corresponding order in the database.
 *
 * Delhivery sends a JSON body; no shared-secret is provided by their
 * standard API so we verify the waybill exists in our DB and belongs
 * to a Delhivery shipment before acting on it.
 *
 * Configure this URL in Delhivery's client portal as the webhook endpoint.
 */
declare(strict_types=1);

// Webhooks must not start a normal session — read-only config only
define('WEBHOOK_REQUEST', true);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed.']));
}

// ── Read payload ──────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Empty body.']));
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON.']));
}

// ── Extract fields ────────────────────────────────────────────────────────────
$waybill    = trim($data['waybill']    ?? $data['AWB']        ?? '');
$statusCode = trim($data['status']     ?? $data['Status']     ?? '');
$statusText = trim($data['statusText'] ?? $data['StatusText'] ?? $statusCode);
$location   = trim($data['location']   ?? $data['Location']   ?? '');

if (empty($waybill) || empty($statusCode)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing required fields.']));
}

// ── Map Delhivery status codes → our order statuses ───────────────────────────
$statusMap = [
    'DL'  => 'delivered',   // Delivered
    'OD'  => 'shipped',     // Out for delivery
    'IT'  => 'shipped',     // In transit
    'PP'  => 'dispatched',  // Pickup pending
    'PU'  => 'dispatched',  // Picked up
    'PKD' => 'processing',  // Packed
    'RTO' => 'cancelled',   // Return to origin initiated
    'RTD' => 'cancelled',   // Returned to origin
];

$newStatus = $statusMap[$statusCode] ?? null;

// ── Fetch order ───────────────────────────────────────────────────────────────
try {
    $order = db_row(
        "SELECT id, status, customer_id, order_number, customer_email
         FROM orders
         WHERE tracking_number = ? AND LOWER(courier) = 'delhivery'
         LIMIT 1",
        [$waybill]
    );
} catch (Throwable $e) {
    error_log('[Delhivery webhook] DB error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Internal error.']));
}

// ── Log raw webhook event ─────────────────────────────────────────────────────
try {
    db_execute(
        "INSERT INTO delhivery_webhook_events
            (waybill, order_id, status_code, status_text, location, raw_payload, received_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [
            $waybill,
            $order['id'] ?? null,
            $statusCode,
            $statusText,
            $location,
            $raw,
        ]
    );
} catch (Throwable $e) {
    // Non-fatal — table may not exist yet on first deploy
    error_log('[Delhivery webhook] Event log error: ' . $e->getMessage());
}

if (!$order) {
    // Acknowledge so Delhivery doesn't keep retrying
    http_response_code(200);
    exit(json_encode(['status' => 'ignored', 'reason' => 'Order not found.']));
}

if ($newStatus === null) {
    http_response_code(200);
    exit(json_encode(['status' => 'ignored', 'reason' => 'Unrecognised status code.']));
}

// Don't downgrade a delivered order
$skipDowngrade = ['delivered', 'cancelled'];
if (in_array($order['status'], $skipDowngrade, true) && $order['status'] !== $newStatus) {
    http_response_code(200);
    exit(json_encode(['status' => 'ignored', 'reason' => 'Order already in terminal state.']));
}

// ── Update order ──────────────────────────────────────────────────────────────
try {
    $extraCols = '';

    switch ($newStatus) {
        case 'processing':
            $extraCols = ', processed_at = COALESCE(processed_at, NOW())';
            break;
        case 'dispatched':
        case 'shipped':
            $extraCols = ', dispatched_at = COALESCE(dispatched_at, NOW())';
            break;
        case 'delivered':
            $extraCols = ', delivered_at = COALESCE(delivered_at, NOW())';
            break;
    }

    db_execute(
        "UPDATE orders SET status = ?{$extraCols} WHERE id = ?",
        [$newStatus, $order['id']]
    );

    db_execute(
        "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
         VALUES (?, ?, ?, NULL, NOW())",
        [$order['id'], $newStatus, 'Delhivery update: ' . $statusText . ' (' . $statusCode . ')']
    );

    // In-app notification for the customer
    if (!empty($order['customer_id'])) {
        $msgs = [
            'dispatched' => 'Your order #{num} has been dispatched! 🚚',
            'shipped'    => 'Your order #{num} is on the way! 🚚',
            'delivered'  => 'Your order #{num} has been delivered. 🎉',
            'cancelled'  => 'Your order #{num} shipment was returned/cancelled.',
        ];
        if (isset($msgs[$newStatus])) {
            $msg = str_replace('{num}', $order['order_number'], $msgs[$newStatus]);
            createNotification(
                (int)$order['customer_id'],
                $msg,
                'order_update',
                '/order-details.php?id=' . $order['id']
            );
        }
    }
} catch (Throwable $e) {
    error_log('[Delhivery webhook] Update error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'DB update failed.']));
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'order_id' => $order['id'], 'new_status' => $newStatus]);
