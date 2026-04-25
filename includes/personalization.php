<?php
/**
 * includes/personalization.php
 * Personalization — product view tracking and recommendation engine.
 */
declare(strict_types=1);

class Personalization
{
    private const SESSION_KEY = 'viewed_categories';

    /**
     * Track a product view (logged-in user → DB, guest → session).
     */
    public function trackView(int $userId, int $productId, string $category): void
    {
        if ($userId > 0) {
            try {
                // Create table if not present (graceful fallback)
                db()->exec("CREATE TABLE IF NOT EXISTS product_views (
                    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT UNSIGNED    NOT NULL,
                    product_id INT UNSIGNED    NOT NULL,
                    category   VARCHAR(100)    NOT NULL DEFAULT '',
                    viewed_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_pv_user_product (user_id, product_id),
                    INDEX idx_pv_user    (user_id),
                    INDEX idx_pv_viewed  (viewed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                db()->prepare(
                    "INSERT INTO product_views (user_id, product_id, category) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE viewed_at = NOW()"
                )->execute([$userId, $productId, $category]);
            } catch (Throwable) {
                // Non-fatal: DB may not support the query
            }
        }

        // Also track in session for guests and quick in-request use
        if (session_status() === PHP_SESSION_NONE) session_start();
        $cats   = $_SESSION[self::SESSION_KEY] ?? [];
        $cats[] = $category;
        // Keep last 10 categories
        $_SESSION[self::SESSION_KEY] = array_slice(array_unique($cats), -10);
    }

    /**
     * Return personalised recommendations for a logged-in user.
     * Falls back to trending for guests.
     *
     * @return array Product rows
     */
    public function getRecommendations(int $userId, int $limit = 4): array
    {
        if ($userId > 0) {
            $recs = $this->userRecommendations($userId, $limit);
            if (!empty($recs)) return $recs;
        }

        // Fallback: session-based category recommendations
        if (session_status() === PHP_SESSION_NONE) session_start();
        $cats = $_SESSION[self::SESSION_KEY] ?? [];
        if (!empty($cats)) {
            $recs = $this->categoryRecommendations($cats, $limit);
            if (!empty($recs)) return $recs;
        }

        return $this->getTrending($limit);
    }

    /**
     * Return trending products sorted by views DESC.
     */
    public function getTrending(int $limit = 8): array
    {
        try {
            $stmt = db()->prepare(
                "SELECT id, slug, name, price, original_price, image_url, category_id
                   FROM products
                  WHERE is_active = 1
                  ORDER BY views DESC, created_at DESC
                  LIMIT ?"
            );
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Recommendations from DB for logged-in users. */
    private function userRecommendations(int $userId, int $limit): array
    {
        try {
            // Get categories from recent views, then fetch unseen products from those categories
            $stmt = db()->prepare(
                "SELECT DISTINCT category FROM product_views
                  WHERE user_id = ?
                  ORDER BY viewed_at DESC
                  LIMIT 5"
            );
            $stmt->execute([$userId]);
            $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($cats)) return [];

            return $this->categoryRecommendations($cats, $limit, $userId);
        } catch (Throwable) {
            return [];
        }
    }

    /** Fetch products from given categories, excluding already-viewed by user. */
    private function categoryRecommendations(array $cats, int $limit, int $excludeUserId = 0): array
    {
        if (empty($cats)) return [];
        try {
            $placeholders = implode(',', array_fill(0, count($cats), '?'));
            $excludeSql   = '';
            $params       = $cats;

            if ($excludeUserId > 0) {
                $excludeSql = "AND p.id NOT IN (
                    SELECT product_id FROM product_views WHERE user_id = ?
                )";
                $params[] = $excludeUserId;
            }

            $params[] = $limit;

            $stmt = db()->prepare(
                "SELECT p.id, p.slug, p.name, p.price, p.original_price, p.image_url, p.category_id
                   FROM products p
                   JOIN categories c ON p.category_id = c.id
                  WHERE p.is_active = 1
                    AND c.name IN ({$placeholders})
                    {$excludeSql}
                  ORDER BY p.created_at DESC
                  LIMIT ?"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}
