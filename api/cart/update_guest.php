<?php

/**
 * api/cart/update_guest.php
 * Updates quantity in session-based guest cart
 */
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

csrf_check();
if (isLoggedIn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Use /api/cart/update.php for logged-in users']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 0);
$change = (int)($_POST['change'] ?? 0);
$size = trim($_POST['size'] ?? '');
$color = trim($_POST['color'] ?? '');

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true) ?: [];
    $productId = (int)($json['product_id'] ?? $productId);
    $qty       = (int)($json['qty'] ?? $json['quantity'] ?? $qty);
    $change    = isset($json['change']) ? (int)$json['change'] : $change;
    $size      = trim($json['size'] ?? $size);
    $color     = trim($json['color'] ?? $color);
}

// If change is provided (delta), use it instead of absolute qty
if ($change !== 0 && $qty === 0) {
    $qty = $change; // Treat as delta
    $isDelta = true;
} else {
    $isDelta = false;
}

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

if (!empty($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    foreach ($_SESSION['guest_cart'] as $key => &$item) {
        if (
            (int)$item['product_id'] === $productId &&
            ($item['size'] ?? '') === $size &&
            ($item['color'] ?? '') === $color
        ) {

            if ($isDelta) {
                // Update by delta
                $item['qty'] = max(0, (int)$item['qty'] + $qty);
            } else {
                // Set absolute quantity
                $item['qty'] = max(0, $qty);
            }

            // Remove if quantity is 0
            if ($item['qty'] <= 0) {
                unset($_SESSION['guest_cart'][$key]);
            }
            break;
        }
    }
    $_SESSION['guest_cart'] = array_filter($_SESSION['guest_cart']);
    $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']); // Re-index
}

echo json_encode(['success' => true]);
