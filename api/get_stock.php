<?php
/**
 * api/get_stock.php
 * Returns stock level for a product variant (color + size combination).
 * Used by product.php to update stock availability in real time.
 *
 * GET params:
 *   product_id  int      required
 *   color       string   optional
 *   size        string   optional
 *
 * Response: { stock: int, available: bool, price: float|null }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$productId = (int)($_GET['product_id'] ?? 0);
$color     = trim($_GET['color'] ?? '');
$size      = trim($_GET['size']  ?? '');

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

try {
    // First check product_variants table for an exact match
    $conditions = ['product_id = ?', 'is_active = 1'];
    $params     = [$productId];

    if ($color !== '') { $conditions[] = 'color = ?'; $params[] = $color; }
    if ($size  !== '') { $conditions[] = 'size = ?';  $params[] = $size;  }

    $sql = "SELECT stock, price FROM product_variants WHERE " . implode(' AND ', $conditions) . " LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $variant = $stmt->fetch();

    if ($variant) {
        echo json_encode([
            'stock'     => (int)$variant['stock'],
            'available' => (int)$variant['stock'] > 0,
            'price'     => $variant['price'] !== null ? (float)$variant['price'] : null,
        ]);
        exit;
    }

    // Fall back to parent product stock
    $pStmt = db()->prepare("SELECT stock, price FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();

    if ($product) {
        echo json_encode([
            'stock'     => (int)$product['stock'],
            'available' => (int)$product['stock'] > 0,
            'price'     => null,
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
