<?php

/**
 * api/cart/remove_guest.php
 * Removes an item from the session-based guest cart
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
    echo json_encode(['error' => 'Use /api/cart/remove.php for logged-in users']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$size = trim($_POST['size'] ?? '');
$color = trim($_POST['color'] ?? '');

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

if (!empty($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    foreach ($_SESSION['guest_cart'] as $key => $item) {
        if (
            (int)$item['product_id'] === $productId &&
            $item['size'] === $size &&
            $item['color'] === $color
        ) {
            unset($_SESSION['guest_cart'][$key]);
            $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']); // Re-index
            break;
        }
    }
}

echo json_encode(['success' => true]);
