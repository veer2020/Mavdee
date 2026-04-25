<?php

/**
 * api/orders/cancel.php
 * Allows a logged-in customer to cancel their own order
 * before it has been dispatched / shipped.
 *
 * POST  /api/orders/cancel.php
 * Body  (JSON): { "order_id": 123, "cancel_reason": "optional reason" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../includes/email.php';

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required.']));
}

$userId = getUserId();

// ── Input ─────────────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

// CSRF validation (token accepted from JSON body or X-CSRF-Token header)
$csrfToken = '';
if (is_array($data) && isset($data['csrf_token'])) {
    $csrfToken = (string)$data['csrf_token'];
}
if ($csrfToken === '') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
}
if (!hash_equals(csrf_token(), $csrfToken)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Invalid CSRF token.']));
}

$orderId      = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$cancelReason = isset($data['cancel_reason']) ? trim((string)$data['cancel_reason']) : '';

if ($orderId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid order ID.']));
}

// Limit cancel_reason length
if (mb_strlen($cancelReason) > 1000) {
    $cancelReason = mb_substr($cancelReason, 0, 1000);
}

// ── Load order ────────────────────────────────────────────────────────────────
$order = db_row(
    "SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1",
    [$orderId, $userId]
);

if (!$order) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'error' => 'Order not found.']));
}

// ── Business rule: only cancel before dispatch ─────────────────────────────────
$cancellableStatuses = ['pending', 'confirmed'];
if (!in_array($order['status'], $cancellableStatuses, true)) {
    http_response_code(422);
    exit(json_encode([
        'success' => false,
        'error'   => 'This order cannot be cancelled — it has already been shipped or delivered.',
    ]));
}

// ── Update order ──────────────────────────────────────────────────────────────
try {
    db_transaction(function () use ($orderId, $userId, $cancelReason, $order): void {
        db_execute(
            "UPDATE orders
                SET status        = 'cancelled',
                    cancelled_at  = NOW(),
                    cancel_reason = ?
              WHERE id = ? AND customer_id = ?",
            [$cancelReason ?: null, $orderId, $userId]
        );

        // Record in order_status_history (created_by = NULL → customer action)
        db_execute(
            "INSERT INTO order_status_history (order_id, status, note, created_by, created_at)
             VALUES (?, 'cancelled', ?, NULL, NOW())",
            [$orderId, $cancelReason ? 'Customer reason: ' . $cancelReason : 'Cancelled by customer']
        );
    });
} catch (Throwable $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Could not cancel the order. Please try again.']));
}

// ── Email notifications ────────────────────────────────────────────────────────
try {
    // Merge the updated fields into the order array so email templates reflect cancellation details
    $cancelledOrder = array_merge($order, [
        'status'        => 'cancelled',
        'cancel_reason' => htmlspecialchars($cancelReason, ENT_QUOTES, 'UTF-8'),
        'cancelled_at'  => date('Y-m-d H:i:s'),
    ]);
    $mailer = new EmailHandler();
    $mailer->sendOrderCancellationToCustomer($cancelledOrder);
    $mailer->sendOrderCancellationToAdmin($cancelledOrder);
} catch (Throwable) {
    // Email failures must never block the cancellation response
}

// ── Response ─────────────────────────────────────────────────────────────────
echo json_encode(['success' => true, 'message' => 'Your order has been cancelled successfully.']);
