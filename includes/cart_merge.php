<?php

/**
 * includes/cart_merge.php – Merge guest cart → DB cart on login.
 * Call after setting $_SESSION[CUSTOMER_SESSION_KEY].
 */
if (!function_exists('merge_guest_cart')) {
    function merge_guest_cart()
    {
        if (!isLoggedIn() || empty($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
            return; // Nothing to merge
        }

        try {
            $userId = getUserId();
            if (!$userId) return;

            $cols = cart_schema_columns();
            $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
            $qtyCol = in_array('qty', $cols) ? 'qty' : 'quantity';
            $hasSizeColor = in_array('size', $cols) && in_array('color', $cols);

            $pdo = db();

            foreach ($_SESSION['guest_cart'] as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = max(1, (int)($item['qty'] ?? 1));
                $size = trim($item['size'] ?? '');
                $color = trim($item['color'] ?? '');

                if ($productId <= 0) continue;

                // Verify product exists
                $check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
                $check->execute([$productId]);
                if (!$check->fetch()) continue;

                if ($hasSizeColor) {
                    $sql = "INSERT INTO cart ($userCol, product_id, $qtyCol, size, color) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE $qtyCol = $qtyCol + VALUES($qtyCol)";
                    $pdo->prepare($sql)->execute([$userId, $productId, $qty, $size, $color]);
                } else {
                    $sql = "INSERT INTO cart ($userCol, product_id, $qtyCol) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE $qtyCol = $qtyCol + VALUES($qtyCol)";
                    $pdo->prepare($sql)->execute([$userId, $productId, $qty]);
                }
            }

            // Clear guest cart after successful merge
            unset($_SESSION['guest_cart']);
            error_log("Cart merged for user $userId: " . count($_SESSION['guest_cart'] ?? []) . " items");
        } catch (Throwable $e) {
            error_log("Cart merge failed: " . $e->getMessage());
        }
    }
}
