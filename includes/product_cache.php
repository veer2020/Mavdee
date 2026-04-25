<?php
/**
 * includes/product_cache.php
 * Cached helpers for common product/category queries.
 * Depends on: includes/cache.php, config/config.php, api/db.php
 */
declare(strict_types=1);

require_once __DIR__ . '/cache.php';

/**
 * Return featured/active products, cached for 30 minutes.
 */
function get_featured_products(int $limit = 8): array
{
    $key   = "products:featured:{$limit}";
    $cache = Cache::instance();
    $hit   = $cache->get($key);
    if (is_array($hit)) return $hit;

    try {
        $stmt = db()->prepare(
            "SELECT id, slug, name, price, original_price, image_url, category_id
               FROM products
              WHERE is_active = 1
              ORDER BY created_at DESC
              LIMIT ?"
        );
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cache->set($key, $rows, 1800); // 30 min
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

/**
 * Return a single product by ID, cached for 1 hour.
 */
function get_product_by_id(int $id): ?array
{
    $key   = "product:{$id}";
    $cache = Cache::instance();
    $hit   = $cache->get($key);
    if (is_array($hit)) return $hit;

    try {
        $stmt = db()->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            $cache->set($key, $row, 3600); // 1 hour
        }
        return $row;
    } catch (Throwable) {
        return null;
    }
}

/**
 * Return all categories, cached for 2 hours.
 */
function get_categories(): array
{
    $key   = 'categories:all';
    $cache = Cache::instance();
    $hit   = $cache->get($key);
    if (is_array($hit)) return $hit;

    try {
        $stmt = db()->query("SELECT id, name, slug, image_url FROM categories ORDER BY name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cache->set($key, $rows, 7200); // 2 hours
        return $rows;
    } catch (Throwable) {
        return [];
    }
}

/**
 * Invalidate caches when a product is created or updated.
 */
function invalidate_product_cache(int $id): void
{
    $cache = Cache::instance();
    $cache->delete("product:{$id}");
    // Bust featured lists for common limits
    foreach ([4, 8, 12, 16] as $limit) {
        $cache->delete("products:featured:{$limit}");
    }
}

/**
 * Invalidate category cache (e.g., after admin edits).
 */
function invalidate_category_cache(): void
{
    Cache::instance()->delete('categories:all');
}
