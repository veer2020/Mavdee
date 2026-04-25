<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

try {
    $id = trim($_GET['id'] ?? '');

    if ($id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Product ID or slug is required.']);
        exit;
    }

    $pdo = db();

    // Fetch product by ID or slug
    if (ctype_digit($id)) {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.id AS cat_id
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.is_active = 1 LIMIT 1");
        $stmt->execute([(int)$id]);
    } else {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.id AS cat_id
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.is_active = 1 LIMIT 1");
        $stmt->execute([$id]);
    }

    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found.']);
        exit;
    }

    // Increment views (column may not exist on older schemas)
    try {
        $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$product['id']]);
    } catch (Throwable) {
        // Ignore if views column is missing
    }

    // Fetch additional images (table may not exist on all deployments)
    $images = [];
    try {
        $stmt = $pdo->prepare("SELECT id, image, alt_text, display_order, is_primary FROM product_images WHERE product_id = ? ORDER BY display_order ASC");
        $stmt->execute([$product['id']]);
        $images = $stmt->fetchAll();
    } catch (Throwable) {
        $images = [];
    }

    // Parse sizes and colors
    $sizes = [];
    if (!empty($product['sizes'])) {
        $sizes = array_map('trim', explode(',', $product['sizes']));
    }
    $colors = [];
    if (!empty($product['colors'])) {
        $colors = array_map('trim', explode(',', $product['colors']));
    }

    // Fetch approved reviews
    $stmt = $pdo->prepare("SELECT id, name, rating, title, body, created_at FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC");
    $stmt->execute([$product['id']]);
    $reviews = $stmt->fetchAll();

    $rating = 0;
    $reviewCount = count($reviews);
    if ($reviewCount > 0) {
        $rating = round(array_sum(array_column($reviews, 'rating')) / $reviewCount, 1);
    }

    // Fetch related products (same category, exclude current, limit 4)
    $related = [];
    if (!empty($product['cat_id'])) {
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.original_price, p.image_url, p.badge, p.badge_type
            FROM products p
            WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
            ORDER BY p.sales_count DESC LIMIT 4");
        $stmt->execute([$product['cat_id'], $product['id']]);
        $related = $stmt->fetchAll();
    }

    $result = [
        'id'              => (int)$product['id'],
        'name'            => $product['name'],
        'slug'            => $product['slug'],
        'description'     => $product['description'],
        'price'           => (float)($product['sale_price'] ?: $product['price']),
        'price_num'       => (float)($product['sale_price'] ?: $product['price']),
        'original_price'  => $product['original_price'] ? (float)$product['original_price'] : null,
        'sale_price'      => $product['sale_price'] ? (float)$product['sale_price'] : null,
        'image_url'       => $product['image_url'],
        'image_url_2'     => $product['image_url_2'] ?? null,
        'image_url_3'     => $product['image_url_3'] ?? null,
        'image_url_4'     => $product['image_url_4'] ?? null,
        'sizes'           => $sizes,
        'colors'          => $colors,
        'badge'           => $product['badge'],
        'badge_type'      => $product['badge_type'],
        'stock'           => (int)$product['stock'],
        'sku'             => $product['sku'],
        'rating'          => $rating,
        'review_count'    => $reviewCount,
        'reviews'         => $reviews,
        'images'          => $images,
        'related_products' => $related,
        'category_name'   => $product['category_name'],
        'is_featured'     => (bool)$product['is_featured'],
        'is_new_arrival'  => (bool)$product['is_new_arrival'],
        'is_bestseller'   => (bool)$product['is_bestseller'],
        'views'           => (int)$product['views'] + 1,
        'meta_title'      => $product['meta_title'],
        'meta_description' => $product['meta_description'],
    ];

    echo json_encode(['success' => true, 'product' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
