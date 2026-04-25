<?php
/**
 * api/recommendations.php
 * Returns product recommendations.
 *
 * GET params:
 *   product_id  int     – "Frequently Bought Together" (co-purchase based)
 *   type        string  – 'fbt' (frequently bought together) | 'similar' | 'trending'
 *   limit       int     – max results (default 4, max 12)
 *
 * Response: { products: [...] }
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';

header('Content-Type: application/json');
header('Cache-Control: max-age=300');

$productId = (int)($_GET['product_id'] ?? 0);
$type      = $_GET['type'] ?? 'similar';
$limit     = min(12, max(1, (int)($_GET['limit'] ?? 4)));

/**
 * Safely return a list of products as compact cards.
 */
function formatProducts(array $rows): array
{
    return array_map(function (array $p): array {
        return [
            'id'        => (int)$p['id'],
            'name'      => $p['name'],
            'slug'      => $p['slug'] ?? '',
            'price'     => (float)$p['price'],
            'image_url' => $p['image_url'] ?? '',
            'avg_rating'=> round((float)($p['avg_rating'] ?? 0), 1),
        ];
    }, $rows);
}

try {
    $products = [];

    if ($type === 'fbt' && $productId > 0) {
        // Co-purchase: products ordered together with this product
        $sql = "
            SELECT p.id, p.name, p.slug, p.price, p.image_url,
                   COALESCE(AVG(r.rating), 0) AS avg_rating,
                   COUNT(oi2.id) AS co_count
            FROM order_items oi1
            JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id != oi1.product_id
            JOIN products p ON p.id = oi2.product_id AND p.is_active = 1
            LEFT JOIN product_reviews r ON r.product_id = p.id AND r.is_approved = 1
            WHERE oi1.product_id = ?
            GROUP BY p.id
            ORDER BY co_count DESC
            LIMIT ?
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([$productId, $limit]);
        $products = $stmt->fetchAll();
    }

    if (empty($products) && $productId > 0) {
        // Fallback: same category products
        $catStmt = db()->prepare("SELECT category_id FROM products WHERE id = ? LIMIT 1");
        $catStmt->execute([$productId]);
        $catRow = $catStmt->fetch();
        $catId  = (int)($catRow['category_id'] ?? 0);

        if ($catId > 0) {
            $sql = "
                SELECT p.id, p.name, p.slug, p.price, p.image_url,
                       COALESCE(AVG(r.rating), 0) AS avg_rating
                FROM products p
                LEFT JOIN product_reviews r ON r.product_id = p.id AND r.is_approved = 1
                WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT ?
            ";
            $stmt = db()->prepare($sql);
            $stmt->execute([$catId, $productId, $limit]);
            $products = $stmt->fetchAll();
        }
    }

    if (empty($products)) {
        // Final fallback: trending / most-viewed products
        $sql = "
            SELECT p.id, p.name, p.slug, p.price, p.image_url,
                   COALESCE(AVG(r.rating), 0) AS avg_rating
            FROM products p
            LEFT JOIN product_reviews r ON r.product_id = p.id AND r.is_approved = 1
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY p.views DESC, p.created_at DESC
            LIMIT ?
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([$limit]);
        $products = $stmt->fetchAll();
    }

    echo json_encode(['products' => formatProducts($products)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'products' => []]);
}
