<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

try {
    $userId = getUserId();
    $singleOrderId = (int)($_GET['order_id'] ?? 0);

    if ($singleOrderId > 0) {
        $stmt = db()->prepare(
            "SELECT o.id, o.order_number, o.status, o.total, o.created_at,
                    oi.product_id, oi.qty, oi.unit_price AS price, oi.size, oi.color,
                    p.name AS product_name, p.image_url, p.slug
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products p ON p.id = oi.product_id
             WHERE o.customer_id = ? AND o.id = ?
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$userId, $singleOrderId]);
    } else {
        $stmt = db()->prepare(
            "SELECT o.id, o.order_number, o.status, o.total, o.created_at,
                    oi.product_id, oi.qty, oi.unit_price AS price, oi.size, oi.color,
                    p.name AS product_name, p.image_url, p.slug
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             JOIN products p ON p.id = oi.product_id
             WHERE o.customer_id = ?
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$userId]);
    }
    $rows = $stmt->fetchAll();

    // Group items under each order
    $orders = [];
    foreach ($rows as $row) {
        $orderId = $row['id'];
        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                'id'           => $orderId,
                'order_number' => $row['order_number'],
                'status'       => $row['status'],
                'total_amount' => (float)$row['total'],
                'created_at'   => $row['created_at'],
                'items'        => [],
            ];
        }
        $orders[$orderId]['items'][] = [
            'product_id'   => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'image_url'    => $row['image_url'] ?? '',
            'slug'         => $row['slug'] ?? '',
            'qty'          => (int)$row['qty'],
            'price'        => (float)$row['price'],
            'size'         => $row['size'] ?? '',
            'color'        => $row['color'] ?? '',
        ];
    }

    echo json_encode(['orders' => array_values($orders)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not load orders.']);
}
