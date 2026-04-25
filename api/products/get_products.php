<?php
/**
 * api/products/get_products.php — Product search API
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.']);
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

    // Use only columns that exist in your products table
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.description, p.price, p.image_url, p.stock,
               c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
          AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
    $rows = $stmt->fetchAll();

    $products = [];
    foreach ($rows as $row) {
        $products[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'slug'           => $row['slug'],
            'description'    => $row['description'],
            'price'          => (float)$row['price'],
            'image_url'      => $row['image_url'],
            'stock'          => (int)$row['stock'],
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
    error_log('Products API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'products' => []
    ]);
}