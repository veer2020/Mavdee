<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

if (!isLoggedIn()) {
    header('Location: login.php?next=wishlist.php');
    exit;
}

$items = [];
try {
    $pdo = db();
    try {
        $stmt = $pdo->prepare(
            "SELECT w.id AS wishlist_id, w.product_id,
                    p.name, p.price, p.original_price, p.image_url, p.slug
             FROM wishlist w
             JOIN products p ON p.id = w.product_id
             WHERE w.customer_id = ?
             ORDER BY w.created_at DESC"
        );
        $stmt->execute([getUserId()]);
        $items = $stmt->fetchAll();
    } catch (Throwable $e) {
        $stmt = $pdo->prepare(
            "SELECT w.id AS wishlist_id, w.product_id,
                    p.name, p.price, p.original_price, p.image_url, p.slug
             FROM wishlist w
             JOIN products p ON p.id = w.product_id
             WHERE w.user_id = ?
             ORDER BY w.created_at DESC"
        );
        $stmt->execute([getUserId()]);
        $items = $stmt->fetchAll();
    }
} catch (Throwable) {
    $items = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | <?= h(SITE_NAME) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        :root {
            --Mavdee-pink: #ff3f6c;
            --Mavdee-pink-light: #fff0f3;
            --Mavdee-dark: #1c1c1c;
            --Mavdee-grey: #f4f4f5;
            --Mavdee-border: #eaeaec;
            --Mavdee-muted: #94969f;
            --Mavdee-text: #3e4152;
            --Mavdee-green: #03a685;
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

        .page-header {
            background: #fff;
            border-bottom: 1px solid var(--Mavdee-border);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-header h1 {
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--Mavdee-dark);
            margin: 0;
        }

        .page-header .item-count {
            font-size: 13px;
            color: var(--Mavdee-muted);
        }

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            padding: 6px;
            max-width: 1200px;
            margin: 0 auto;
        }

        @media (min-width: 600px) {
            .wishlist-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 900px) {
            .wishlist-grid {
                grid-template-columns: repeat(4, 1fr);
                padding: 16px;
                gap: 12px;
            }
        }

        .prod-card {
            background: #fff;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .prod-img-wrap {
            position: relative;
            aspect-ratio: 3/4;
            overflow: hidden;
            background: var(--Mavdee-grey);
        }

        .prod-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
            display: block;
        }

        .prod-card:hover .prod-img-wrap img {
            transform: scale(1.04);
        }

        .badge-discount {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--Mavdee-pink);
            color: #fff;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 2px;
            z-index: 2;
        }

        .btn-remove-overlay {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--Mavdee-muted);
            z-index: 2;
            transition: color 0.2s;
        }

        .btn-remove-overlay:hover {
            color: var(--Mavdee-pink);
        }

        .prod-body {
            padding: 10px 10px 12px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .prod-brand {
            font-size: 12px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .prod-name {
            font-size: 13px;
            color: var(--Mavdee-muted);
            margin: 0 0 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .prod-price {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 10px;
        }

        .price-now {
            font-size: 15px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        .price-was {
            font-size: 13px;
            color: var(--Mavdee-muted);
            text-decoration: line-through;
        }

        .price-off {
            font-size: 13px;
            color: var(--Mavdee-pink);
            font-weight: 600;
        }

        .btn-move-to-bag {
            width: 100%;
            padding: 10px;
            background: var(--Mavdee-pink);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: auto;
        }

        .btn-move-to-bag:hover {
            background: #e0325a;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            margin: 16px;
            border: 1px solid var(--Mavdee-border);
        }

        .empty-state svg {
            display: block;
            margin: 0 auto 16px;
            color: var(--Mavdee-muted);
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
            font-size: 14px;
            color: var(--Mavdee-muted);
            margin: 0 0 24px;
        }

        .btn-explore {
            display: inline-block;
            background: var(--Mavdee-pink);
            color: #fff;
            padding: 13px 28px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: background 0.2s;
        }

        .btn-explore:hover {
            background: #e0325a;
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <div class="page-header">
        <h1>My Wishlist</h1>
        <span class="item-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
            </svg>
            <h2>Your Wishlist is Empty</h2>
            <p>Save items you love and find them here anytime.</p>
            <a href="shop.php" class="btn-explore">Explore Collection</a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($items as $item):
                $discount = ($item['original_price'] > 0 && $item['original_price'] > $item['price'])
                    ? round((1 - $item['price'] / $item['original_price']) * 100) : 0;
            ?>
                <div class="prod-card" id="wishcard-<?= (int)$item['product_id'] ?>">
                    <a href="product.php?slug=<?= h($item['slug']) ?>" class="prod-img-wrap">
                        <?php if ($discount > 0): ?>
                            <span class="badge-discount">-<?= $discount ?>%</span>
                        <?php endif; ?>
                        <img src="<?= h(img_url($item['image_url'] ?? '')) ?>" alt="<?= h($item['name']) ?>" loading="lazy" onerror="this.src='/assets/img/placeholder.svg'">
                    </a>
                    <button class="btn-remove-overlay" onclick="removeFromWishlist(<?= (int)$item['product_id'] ?>, this)" title="Remove from wishlist" aria-label="Remove from wishlist">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                    <div class="prod-body">
                        <a href="product.php?slug=<?= h($item['slug']) ?>">
                            <p class="prod-brand"><?= h($item['name']) ?></p>
                            <p class="prod-name">View product</p>
                        </a>
                        <div class="prod-price">
                            <span class="price-now"><?= h(CURRENCY) ?><?= number_format($item['price'], 0) ?></span>
                            <?php if ($discount > 0): ?>
                                <span class="price-was"><?= h(CURRENCY) ?><?= number_format($item['original_price'], 0) ?></span>
                                <span class="price-off"><?= $discount ?>% off</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn-move-to-bag" onclick="addWishlistItemToCart(<?= (int)$item['product_id'] ?>, this)">Move to Bag</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        async function removeFromWishlist(productId, btn) {
            const card = document.getElementById('wishcard-' + productId);
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const bodyParams = new URLSearchParams({
                    product_id: productId,
                    csrf_token: csrf
                });

                const res = await fetch('/api/wishlist/toggle.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-Token': csrf
                    },
                    body: bodyParams
                });
                let data = null;
                try {
                    data = await res.json();
                } catch (parseError) {
                    data = null;
                }

                if (res.status === 401 || (data && (data.error === 'Login required.' || data.require_login))) {
                    window.location = '/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }

                if (!res.ok) {
                    throw new Error((data && data.error) ? data.error : 'Server responded with status ' + res.status);
                }

                if (data.success) {
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transition = 'opacity 0.3s';
                        setTimeout(() => card.remove(), 300);
                    }
                    const wb = document.getElementById('wishlistBadge');
                    if (wb && data.count !== undefined) {
                        wb.textContent = data.count;
                        wb.style.display = data.count > 0 ? '' : 'none';
                    }
                }
            } catch (e) {
                console.error('Wishlist remove error:', e);
            }
        }

        async function addWishlistItemToCart(productId, btn) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const body = new FormData();
            body.append('product_id', productId);
            body.append('qty', 1);
            body.append('csrf_token', csrf);
            try {
                const res = await fetch('/api/cart/add.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': csrf
                    },
                    body: body
                });
                const data = await res.json();
                if (data.success) {
                    if (typeof loadCart === 'function') await loadCart();
                    if (typeof openCart === 'function') openCart();
                } else {
                    alert(data.error || 'Could not add to cart.');
                }
            } catch (e) {
                console.error('Add to cart error:', e);
            }
        }
    </script>
</body>

</html>