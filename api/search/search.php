<?php
/**
 * api/products/get_products.php — Product search API
 */

// Fix the include paths
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $query = trim($_GET['q'] ?? '');
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

    if ($query === '') {
        echo json_encode(['success' => true, 'products' => [], 'count' => 0, 'query' => '']);
        exit;
    }

    $pdo = db();
    $searchTerm = '%' . $query . '%';

    $stmt = $pdo->prepare("SELECT p.id, p.name, p.slug, p.description, p.price, p.sale_price,
            p.original_price, p.image_url, p.stock, p.sku, p.badge, p.badge_type,
            c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
          AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)
        ORDER BY
            CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END,
            p.sales_count DESC
        LIMIT ?");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    $rows = $stmt->fetchAll();

    $products = [];
    foreach ($rows as $row) {
        $products[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'slug'           => $row['slug'],
            'description'    => $row['description'],
            'price'          => (float)($row['sale_price'] ?: $row['price']),
            'original_price' => $row['original_price'] ? (float)$row['original_price'] : null,
            'sale_price'     => $row['sale_price'] ? (float)$row['sale_price'] : null,
            'image_url'      => $row['image_url'],
            'stock'          => (int)$row['stock'],
            'sku'            => $row['sku'],
            'badge'          => $row['badge'],
            'badge_type'     => $row['badge_type'],
            'category_name'  => $row['category_name'],
        ];
    }

    echo json_encode([
        'success'  => true,
        'products' => $products,
        'count'    => count($products),
        'query'    => $query,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}