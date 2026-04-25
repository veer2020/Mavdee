<?php

/**
 * api/cart/get_guest.php
 * Returns guest shopping cart from session
 * Used when user is not logged in
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

// Only for guests
if (isLoggedIn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Use /api/cart/get.php for logged-in users']);
    exit;
}

$items = [];
$count = 0;
$total = 0;

// Get guest cart from session
if (!empty($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    try {
        $pdo = db();

        foreach ($_SESSION['guest_cart'] as $cartItem) {
            $productId = (int)($cartItem['product_id'] ?? 0);
            $qty = (int)($cartItem['qty'] ?? 1);

            if ($productId <= 0 || $qty <= 0) continue;

            // Get product details
            $stmt = $pdo->prepare(
                "SELECT * FROM products WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) continue;

            $price = (float)($product['price'] ?? 0);
            $itemTotal = $price * $qty;
            $total += $itemTotal;
            $count += $qty;

            $items[] = [
                'product_id' => $productId,
                'name' => $product['name'],
                'slug' => $product['slug'],
                'price' => $price,
                'qty' => $qty,
                'image_url' => $product['image_url'] ?? '/assets/img/placeholder.svg',
                'stock' => (int)$product['stock'],
                'size' => $cartItem['size'] ?? '',
                'color' => $cartItem['color'] ?? '',
                'subtotal' => $itemTotal,
            ];
        }
    } catch (Throwable $e) {
        error_log('Guest cart retrieval error: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'count' => $count,
    'total' => $total,
    'currency' => CURRENCY,
]);
