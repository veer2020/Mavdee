<?php

/**
 * search.php — Full product search results page
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

$q       = trim($_GET['q'] ?? '');
$results = [];
$total   = 0;

if ($q !== '' && mb_strlen($q) >= 2) {
    // Modernized Search: Multi-word phrase matching
    $words = array_filter(explode(' ', preg_replace('/\s+/', ' ', $q)));

    $conditions = [];
    $params = [];

    foreach ($words as $word) {
        $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $word) . '%';
        $conditions[] = "(name LIKE ? OR description LIKE ? OR category_id IN (SELECT id FROM categories WHERE name LIKE ?))";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = implode(' AND ', $conditions);

    try {
        $stmt = db()->prepare(
            "SELECT id, slug, name, price, original_price, image_url, category_id
               FROM products
              WHERE is_active = 1
                AND ($whereSql)
              ORDER BY created_at DESC
              LIMIT 48"
        );
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total   = count($results);
    } catch (Throwable) {
        // Fallback if category table doesn't exist
        try {
            $fallbackConds = [];
            $fallbackParams = [];
            foreach ($words as $word) {
                $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $word) . '%';
                $fallbackConds[] = "(name LIKE ? OR description LIKE ?)";
                $fallbackParams[] = $term;
                $fallbackParams[] = $term;
            }
            $stmt = db()->prepare(
                "SELECT id, slug, name, price, original_price, image_url, category_id
                   FROM products
                  WHERE is_active = 1
                    AND (" . implode(' AND ', $fallbackConds) . ")
                  ORDER BY created_at DESC
                  LIMIT 48"
            );
            $stmt->execute($fallbackParams);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total   = count($results);
        } catch (Throwable $e) {
            $results = [];
        }
    }
}

// Fetch user's wishlist for initial button state
$userWishlist = [];
if ($total > 0 && function_exists('isLoggedIn') && isLoggedIn()) {
    try {
        $uid = getUserId();
        $wStmt = db()->prepare("SELECT product_id FROM wishlist WHERE customer_id = ?");
        $wStmt->execute([$uid]);
        $userWishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        try {
            $wStmt = db()->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
            $wStmt->execute([$uid]);
            $userWishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Search: <?= h($q) ?> — <?= h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <style>
        :root {
            --Mavdee-pink: #ff3f6c;
            --Mavdee-pink-light: #fff0f3;
            --Mavdee-dark: #1c1c1c;
            --Mavdee-grey: #f4f4f5;
            --Mavdee-border: #eaeaec;
            --Mavdee-muted: #94969f;
            --Mavdee-text: #3e4152;
            --font-sans: 'DM Sans', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--font-sans);
            font-size: 14px;
            background: var(--Mavdee-grey);
            color: var(--Mavdee-text);
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .search-hero {
            background: #fff;
            border-bottom: 1px solid var(--Mavdee-border);
            padding: 16px 16px 14px;
        }

        .search-hero h1 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--Mavdee-muted);
            margin: 0 0 12px;
        }

        .search-form {
            display: flex;
            max-width: 600px;
            border: 1.5px solid var(--Mavdee-border);
            border-radius: 4px;
            overflow: hidden;
            background: #fff;
            transition: border-color 0.2s;
        }

        .search-form:focus-within {
            border-color: var(--Mavdee-pink);
        }

        .search-form input {
            flex: 1;
            border: none;
            padding: 11px 14px;
            font-family: var(--font-sans);
            font-size: 15px;
            background: transparent;
            color: var(--Mavdee-dark);
            outline: none;
        }

        .search-form button {
            background: var(--Mavdee-pink);
            color: #fff;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .search-form button:hover {
            background: #e0325a;
        }

        .search-meta {
            padding: 10px 16px;
            font-size: 13px;
            color: var(--Mavdee-muted);
        }

        .search-meta strong {
            color: var(--Mavdee-dark);
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            padding: 6px;
        }

        @media (min-width: 600px) {
            .results-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 900px) {
            .results-grid {
                grid-template-columns: repeat(4, 1fr);
                padding: 12px;
                gap: 10px;
            }
        }

        /* Product card – Mavdee style */
        .product-card {
            background: #fff;
            cursor: pointer;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .product-card-img-wrap {
            position: relative;
            overflow: hidden;
            background: var(--Mavdee-grey);
            aspect-ratio: 3/4;
        }

        .product-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
            display: block;
        }

        .product-card:hover .product-card-img-wrap img {
            transform: scale(1.04);
        }

        .product-card-body {
            padding: 8px 10px 12px;
        }

        .product-card-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--Mavdee-dark);
            margin: 0 0 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-card-desc {
            font-size: 13px;
            color: var(--Mavdee-muted);
            margin: 0 0 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-card-price {
            font-size: 15px;
            color: var(--Mavdee-dark);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .product-card-price .original {
            text-decoration: line-through;
            color: var(--Mavdee-muted);
            font-size: 13px;
        }

        .product-card-price .discount {
            color: var(--Mavdee-pink);
            font-size: 13px;
            font-weight: 700;
        }

        .wishlist-btn-card {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.88);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--Mavdee-muted);
            transition: color 0.2s, background 0.2s;
        }

        .wishlist-btn-card:hover,
        .wishlist-btn-card.wishlisted {
            color: var(--Mavdee-pink);
            background: #fff;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            grid-column: 1 / -1;
            background: #fff;
            margin: 6px;
            border: 1px solid var(--Mavdee-border);
        }

        .empty-state svg {
            color: var(--Mavdee-muted);
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--Mavdee-dark);
            margin: 0 0 8px;
        }

        .empty-state p {
            color: var(--Mavdee-muted);
            font-size: 14px;
            margin: 0 0 24px;
        }

        .btn-browse {
            display: inline-block;
            padding: 13px 28px;
            background: var(--Mavdee-pink);
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: background 0.2s;
        }

        .btn-browse:hover {
            background: #e0325a;
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <section class="search-hero">
        <h1>Search Results</h1>
        <form class="search-form" method="GET" action="shop.php">
            <input type="search" name="q" placeholder="Search for dresses, kurtis, co-ords…"
                value="<?= h($q) ?>" autofocus aria-label="Search products">
            <button type="submit">Search</button>
        </form>
    </section>

    <?php if ($q !== ''): ?>
        <p class="search-meta">
            <?php if ($total > 0): ?>
                <strong><?= $total ?></strong> result<?= $total !== 1 ? 's' : '' ?> for &ldquo;<strong><?= h($q) ?></strong>&rdquo;
            <?php else: ?>
                No results for &ldquo;<strong><?= h($q) ?></strong>&rdquo;
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="results-grid">
        <?php if ($q !== '' && $total === 0): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <h2>No Results Found</h2>
                <p>Try different keywords, or browse our full collection.</p>
                <a href="shop.php" class="btn-browse">Browse All Products</a>
            </div>

        <?php elseif ($q === ''): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <h2>What Are You Looking For?</h2>
                <p>Search by product name, category, or description.</p>
                <a href="shop.php" class="btn-browse">Browse Collection</a>
            </div>

        <?php else: ?>
            <?php foreach ($results as $p): ?>
                <?php
                $discount = 0;
                if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']) {
                    $discount = round((1 - $p['price'] / $p['original_price']) * 100);
                }
                $href = 'product.php?id=' . (int)$p['id'] . '&slug=' . urlencode($p['slug'] ?? '');
                ?>
                <article class="product-card reveal-on-scroll">
                    <a href="<?= h($href) ?>">
                        <div class="product-card-img-wrap">
                            <img src="<?= h(img_url($p['image_url'] ?? '')) ?>"
                                alt="<?= h($p['name']) ?>"
                                loading="lazy"
                                onerror="this.src='/assets/img/placeholder.svg'">
                        </div>
                        <div class="product-card-body">
                            <p class="product-card-name"><?= h($p['name']) ?></p>
                            <p class="product-card-desc">View product</p>
                            <div class="product-card-price">
                                <span><?= h(CURRENCY) ?><?= number_format((float)$p['price'], 0) ?></span>
                                <?php if ($discount > 0): ?>
                                    <span class="original"><?= h(CURRENCY) ?><?= number_format((float)$p['original_price'], 0) ?></span>
                                    <span class="discount"><?= $discount ?>% off</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php $isWishlisted = in_array($p['id'], $userWishlist); ?>
                    <button class="wishlist-btn-card <?= $isWishlisted ? 'wishlisted' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" aria-label="Wishlist"
                        onclick="toggleWishlist(<?= (int)$p['id'] ?>, this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $isWishlisted ? '#ff3f6c' : 'none' ?>" stroke="<?= $isWishlisted ? '#ff3f6c' : 'currentColor' ?>" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                    </button>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
    <script src="/assets/js/cart.js" defer></script>
    <script src="/assets/js/app.js" defer></script>
    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        function addProductToCart(e, productId) {
            e.preventDefault();
            e.stopPropagation();
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
            fetch('/api/cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `product_id=${productId}&qty=1&csrf_token=${encodeURIComponent(csrf)}`
            }).then(() => {
                if (typeof window.showToast === 'function') window.showToast('Added to cart!', 'success');
            });
        }
    </script>
</body>

</html>