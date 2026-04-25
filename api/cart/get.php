<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
        $guestItems = [];
        $total = 0;
        $count = 0;
        $savings = 0;

        foreach ($_SESSION['guest_cart'] as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['qty'];
            $size = $item['size'] ?? '';
            $color = $item['color'] ?? '';

            // Quick product lookup for totals
            $price = 0;
            try {
                $stmt = db()->prepare('SELECT price, original_price FROM products WHERE id = ? AND is_active = 1');
                $stmt->execute([$pid]);
                $prod = $stmt->fetch();
                $price = $prod ? (float)$prod['price'] : 0;
                $origPrice = (float)($prod['original_price'] ?? $price);
                $savings += max(0, ($origPrice - $price) * $qty);
            } catch (Throwable) {
            }

            $total += $price * $qty;
            $count += $qty;

            $guestItems[] = [
                'product_id' => $pid,
                'qty' => $qty,
                'size' => $size,
                'color' => $color,
                'price' => $price
            ];
        }

        echo json_encode([
            'items' => $guestItems,
            'total' => round($total, 2),
            'count' => $count,
            'savings' => round($savings, 2),
            'guest' => true
        ]);
        exit;
    }
    echo json_encode(['items' => [], 'total' => 0, 'count' => 0, 'savings' => 0]);
    exit;
}

try {
    $cols    = cart_schema_columns();
    $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
    $qtyCol  = in_array('qty', $cols) ? 'qty' : 'quantity';

    // Handle carts that may not yet have size/color columns
    $hasSizeColor = in_array('size', $cols) && in_array('color', $cols);
    $sizeSelect   = $hasSizeColor ? "c.size, c.color," : "'' AS size, '' AS color,";

    // Handle products that may not yet have original_price column
    $productCols        = db_columns('products');
    $hasOriginalPrice   = in_array('original_price', $productCols);
    $origPriceSelect    = $hasOriginalPrice ? 'p.original_price,' : 'NULL AS original_price,';

    $stmt = db()->prepare(
        "SELECT c.product_id, c.$qtyCol AS qty, $sizeSelect
                p.name, p.price, $origPriceSelect p.image_url, p.slug
         FROM cart c
         JOIN products p ON p.id = c.product_id
         WHERE c.$userCol = ?"
    );
    $stmt->execute([getUserId()]);
    $rows = $stmt->fetchAll();

    $total   = 0.0;
    $savings = 0.0;
    $count   = 0;
    $items   = [];

    foreach ($rows as $row) {
        $qty            = (int)$row['qty'];
        $price          = (float)$row['price'];
        $originalPrice  = (float)($row['original_price'] ?? $price);
        $lineTotal      = $price * $qty;
        $lineSavings    = max(0, ($originalPrice - $price) * $qty);

        $total   += $lineTotal;
        $savings += $lineSavings;
        $count   += $qty;

        $items[] = [
            'product_id'     => (int)$row['product_id'],
            'name'           => $row['name'],
            'price'          => $price,
            'original_price' => $originalPrice,
            'image_url'      => $row['image_url'] ?? '',
            'slug'           => $row['slug'] ?? '',
            'qty'            => $qty,
            'size'           => $row['size'] ?? '',
            'color'          => $row['color'] ?? '',
        ];
    }

    echo json_encode([
        'items'   => $items,
        'total'   => round($total, 2),
        'count'   => $count,
        'savings' => round($savings, 2),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load cart.']);
}
