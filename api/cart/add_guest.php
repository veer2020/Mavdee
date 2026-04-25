<?php

/**
 * api/cart/add_guest.php
 * Adds an item to the session-based guest cart.
 * Used when a non-logged-in user clicks "Add to Cart".
 *
 * POST JSON or form: { product_id, qty, size, color }
 * Response: { ok: bool, count: int }
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_check();

// Already logged in — no need for guest cart
if (isLoggedIn()) {
    echo json_encode(['ok' => false, 'error' => 'Use the regular cart endpoint for logged-in users.']);
    exit;
}

$raw  = (string)file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$productId = (int)(($body['product_id'] ?? null) ?: ($_POST['product_id'] ?? 0));
$qty       = max(1, (int)(($body['qty'] ?? null) ?: ($_POST['qty'] ?? 1)));
$size      = trim((string)(($body['size'] ?? null) ?: ($_POST['size'] ?? '')));
$color     = trim((string)(($body['color'] ?? null) ?: ($_POST['color'] ?? '')));

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'product_id required']);
    exit;
}

if (!isset($_SESSION['guest_cart'])) {
    $_SESSION['guest_cart'] = [];
}

// Find existing item in session cart
$found = false;
foreach ($_SESSION['guest_cart'] as &$item) {
    if ((int)$item['product_id'] === $productId && $item['size'] === $size && $item['color'] === $color) {
        $item['qty'] = min($item['qty'] + $qty, 20);
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $_SESSION['guest_cart'][] = [
        'product_id' => $productId,
        'qty'        => $qty,
        'size'       => $size,
        'color'      => $color,
    ];
}

$count = array_sum(array_column($_SESSION['guest_cart'], 'qty'));
echo json_encode(['ok' => true, 'count' => $count]);
