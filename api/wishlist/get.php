<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'items' => [],
        'count' => 0
    ]);
    exit;
}

try {
    $userId = getUserId();
    $pdo = db();

    // Handle schema inconsistency between `customer_id` and `user_id`
    $wishlistCols = db_columns('wishlist');
    $userCol = in_array('customer_id', $wishlistCols) ? 'customer_id' : 'user_id';

    $stmt = $pdo->prepare("SELECT w.id AS wishlist_id, w.created_at AS added_at,
                p.id, p.name, p.slug, p.price, p.original_price,
                p.image_url, p.stock
            FROM wishlist w
            JOIN products p ON p.id = w.product_id
            WHERE w.$userCol = ?
            ORDER BY w.created_at DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'wishlist_id'    => (int)$row['wishlist_id'],
            'added_at'       => $row['added_at'],
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'slug'           => $row['slug'],
            'price'          => (float)($row['price']),
            'original_price' => $row['original_price'] ? (float)$row['original_price'] : null,
            'sale_price'     => null,
            'image_url'      => $row['image_url'],
            'stock'          => (int)$row['stock'],
            'badge'          => null,
            'badge_type'     => null,
        ];
    }

    echo json_encode(['success' => true, 'items' => $items, 'count' => count($items)]);
} catch (Exception $e) {
    error_log("Wishlist Get Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
