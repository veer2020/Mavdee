<?php
/**
 * api/search.php — JSON search endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';

header('Content-Type: application/json');

// Simple image URL helper (avoid function dependency issues)
function _img_url($path) {
    if (empty($path)) return '/assets/img/placeholder.svg';
    if (strpos($path, 'http') === 0) return $path;
    return '/' . ltrim($path, '/');
}

$q = trim($_GET['q'] ?? '');

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'products' => [], 'count' => 0, 'query' => $q]);
    exit;
}

$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

try {
    // Use only columns that definitely exist
    $stmt = db()->prepare(
        "SELECT id, slug, name, price, image_url
         FROM products
         WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?)
         ORDER BY created_at DESC
         LIMIT 12"
    );
    $stmt->execute([$like, $like]);
    $products = $stmt->fetchAll();

    $formatted = [];
    foreach ($products as $p) {
        $formatted[] = [
            'id' => (int)$p['id'],
            'slug' => $p['slug'],
            'name' => $p['name'],
            'price' => (float)$p['price'],
            'image_url' => _img_url($p['image_url'] ?? '')
        ];
    }

    echo json_encode([
        'success' => true,
        'products' => $formatted,
        'count' => count($formatted),
        'query' => $q
    ]);
    
} catch (Throwable $e) {
    error_log('Search API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage(),
        'products' => [],
        'count' => 0,
        'query' => $q
    ]);
}
