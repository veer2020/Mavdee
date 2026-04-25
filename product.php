<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/includes/image_helper.php';
$csrfToken = csrf_token();

// 1. Get the product ID and Slug from the URL parameter
$id = (int)($_GET['id'] ?? 0);
$reqSlug = trim($_GET['slug'] ?? '');

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if ($product && $product['slug'] !== $reqSlug) {
        header("Location: /product.php?id={$product['id']}&slug={$product['slug']}", true, 301);
        exit;
    }
} elseif ($reqSlug !== '') {
    $stmt = db()->prepare("SELECT * FROM products WHERE slug = ? AND is_active = 1");
    $stmt->execute([$reqSlug]);
    $product = $stmt->fetch();
    if ($product) {
        header("Location: /product.php?id={$product['id']}&slug={$product['slug']}", true, 301);
        exit;
    }
} else {
    header("Location: shop.php?error=product_not_found");
    exit;
}

if (!$product) {
    header("Location: shop.php?error=product_not_found");
    exit;
}

// Update view count
try {
    db()->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$product['id']]);
} catch (Throwable) {
}

// Review stats
try {
    $reviewStmt = db()->prepare(
        "SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews WHERE product_id = ? AND is_approved = 1"
    );
    $reviewStmt->execute([$product['id']]);
    $reviewStats = $reviewStmt->fetch();
} catch (Throwable) {
    $reviewStats = ['review_count' => 0, 'avg_rating' => 0];
}
$reviewCount = (int)($reviewStats['review_count'] ?? 0);
$avgRating   = round((float)($reviewStats['avg_rating'] ?? 0), 1);

// Flash sale
$flashSale = null;
try {
    $fsStmt = db()->prepare(
        "SELECT * FROM flash_sales WHERE product_id = ? AND is_active = 1
           AND starts_at <= NOW() AND ends_at >= NOW() LIMIT 1"
    );
    $fsStmt->execute([$product['id']]);
    $flashSale = $fsStmt->fetch() ?: null;
} catch (Throwable) {
}

$displayPrice = $flashSale ? (float)$flashSale['sale_price'] : (float)$product['price'];

// Images
$rawImages = array_filter([
    $product['image_url'] ?? '',
    $product['image_url_2'] ?? '',
    $product['image_url_3'] ?? '',
    $product['image_url_4'] ?? '',
]);
$images       = !empty($rawImages) ? $rawImages : [''];
$mainImage    = $images[0];
$hasRealImages = !empty($rawImages) && img_url($rawImages[0]) !== '/assets/img/placeholder.svg';

// Specs
$specs = [];
if (!empty($product['fabric'])) $specs[] = ['Fabric', htmlspecialchars($product['fabric'])];
$careVal = trim($product['care'] ?? '');
if (!empty($careVal) && strtolower($careVal) !== 'no' && strtolower($careVal) !== 'n/a')
    $specs[] = ['Care', htmlspecialchars($careVal)];
if (!empty($product['colors'])) $specs[] = ['Colors', htmlspecialchars($product['colors'])];
if (!empty($product['sizes'])) $specs[] = ['Sizes', htmlspecialchars($product['sizes'])];
$specs[] = ['Stitch', 'Ready to Wear'];
$specs[] = ['Product ID', '#' . $product['id']];

// Reviews
$recentReviews = [];
$allReviews    = [];
try {
    $rvStmt = db()->prepare(
        "SELECT * FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 3"
    );
    $rvStmt->execute([$product['id']]);
    $recentReviews = $rvStmt->fetchAll();
} catch (Throwable) {
}
if ($reviewCount > 3) {
    try {
        $rvAllStmt = db()->prepare(
            "SELECT * FROM product_reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC"
        );
        $rvAllStmt->execute([$product['id']]);
        $allReviews = $rvAllStmt->fetchAll();
    } catch (Throwable) {
    }
}

// Personalization
require_once __DIR__ . '/includes/personalization.php';
$engine  = new Personalization();
$catStmt = db()->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
$catStmt->execute([$product['category_id'] ?? 0]);
$catRow  = $catStmt->fetch();
$catName = $catRow['name'] ?? '';
$engine->trackView(isLoggedIn() ? getUserId() : 0, $product['id'], $catName);
$recommendations = $engine->getRecommendations(isLoggedIn() ? getUserId() : 0, 4);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title><?= h($product['name']) ?> | <?= h(SITE_NAME) ?></title>

    <!-- SEO Meta -->
    <meta name="description" content="<?= h(mb_substr(strip_tags($product['description'] ?? ''), 0, 160)) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= h($product['name']) ?>">
    <meta property="og:description" content="<?= h(mb_substr(strip_tags($product['description'] ?? ''), 0, 160)) ?>">
    <meta property="og:image" content="<?= h(SITE_URL . '/' . ltrim(img_url($product['image_url'] ?? ''), '/')) ?>">
    <meta property="og:url" content="<?= h(SITE_URL . '/product.php?id=' . $product['id'] . '&slug=' . $product['slug']) ?>">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="product:price:amount" content="<?= number_format($displayPrice, 2, '.', '') ?>">
    <meta property="product:price:currency" content="<?= h(CURRENCY ?? 'INR') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($product['name']) ?>">
    <meta name="twitter:description" content="<?= h(mb_substr(strip_tags($product['description'] ?? ''), 0, 160)) ?>">
    <meta name="twitter:image" content="<?= h(SITE_URL . '/' . ltrim(img_url($product['image_url'] ?? ''), '/')) ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Global CSS -->
    <link rel="stylesheet" href="/assets/css/global.css">

    <!-- Swiper -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css" as="style">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css">

    <style>
        /* ═══════════════════════════════════════════════════════════
  MAVDEE — Premium Product Page (Mavdee Level)
   ═══════════════════════════════════════════════════════════ */
        :root {
            /* Brand palette */
            --rose: #ff3f6c;
            --rose-light: #fff0f4;
            --rose-dark: #cc2f55;
            --teal: #03a685;
            --teal-light: #e6f7f4;
            --amber: #ff905a;
            --ink: #1c1c1c;
            --ink-2: #3e4152;
            --muted: #7e818c;
            --border: #eaeaec;
            --surface: #f4f4f5;
            --white: #ffffff;
            --gold: #f5a623;

            /* Typography */
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'Manrope', system-ui, sans-serif;

            /* Spacing */
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 24px;

            /* Shadows */
            --shadow-sm: 0 1px 4px rgba(0, 0, 0, .06);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, .08);
            --shadow-lg: 0 8px 40px rgba(0, 0, 0, .12);
            --shadow-btn: 0 4px 14px rgba(255, 63, 108, .35);

            /* Legacy compat */
            --mavdee-pink: var(--rose);
            --mavdee-pink-light: var(--rose-light);
            --mavdee-green: var(--teal);
            --mavdee-dark: var(--ink);
            --mavdee-grey: var(--surface);
            --mavdee-border: var(--border);
            --mavdee-muted: var(--muted);
            --mavdee-text: var(--ink-2);
            --mavdee-rating: var(--amber);
            --font-sans: var(--font-body);
            --font-serif: var(--font-display);
            --light-grey: var(--surface);
            --parchment: var(--surface);
            --maroon: var(--rose);
            --off-white: var(--surface);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: auto;
            min-height: 100%;
        }

        body {
            font-family: var(--font-body);
            font-size: 14px;
            background: var(--white);
            color: var(--ink-2);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            padding-bottom: calc(var(--bottom-nav-height, 60px) + env(safe-area-inset-bottom));
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── Breadcrumb ─────────────────────────────────────────────── */
        .pdp-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            background: var(--white);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(8px);
        }

        .pdp-breadcrumb a {
            color: var(--muted);
            transition: color .2s;
        }

        .pdp-breadcrumb a:hover {
            color: var(--rose);
        }

        .pdp-breadcrumb .sep {
            color: var(--border);
        }

        .pdp-breadcrumb .current {
            color: var(--ink);
            font-weight: 600;
        }

        /* ── Main Layout ────────────────────────────────────────────── */
        .pdp-layout {
            display: grid;
            grid-template-columns: 1fr;
            max-width: 100%;
        }

        @media (min-width: 1024px) {
            .pdp-layout {
                grid-template-columns: 55% 45%;
                max-width: 1280px;
                margin: 0 auto;
                align-items: start;
            }
        }

        /* ══════════════════════════════════════════════════
   GALLERY — LEFT COLUMN
   ══════════════════════════════════════════════════ */
        .pdp-gallery {
            position: relative;
            background: var(--surface);
        }

        @media (min-width: 1024px) {
            .pdp-gallery {
                position: sticky;
                top: 0;
                display: flex;
                gap: 10px;
                padding: 16px 0 16px 16px;
                background: var(--white);
                height: 100vh;
                overflow: hidden;
            }
        }

        /* Desktop: vertical thumbnail strip */
        .pdp-thumbs {
            display: none;
        }

        @media (min-width: 1024px) {
            .pdp-thumbs {
                display: flex;
                flex-direction: column;
                gap: 8px;
                width: 72px;
                flex-shrink: 0;
                overflow-y: auto;
                scrollbar-width: none;
            }

            .pdp-thumbs::-webkit-scrollbar {
                display: none;
            }
        }

        .pdp-thumb {
            width: 72px;
            height: 88px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color .2s, transform .2s;
            flex-shrink: 0;
        }

        .pdp-thumb:hover {
            transform: translateY(-2px);
        }

        .pdp-thumb.active {
            border-color: var(--rose);
        }

        .pdp-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Main image area */
        .pdp-main-image-wrap {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        @media (min-width: 1024px) {
            .pdp-main-image-wrap {
                flex: 1;
                border-radius: var(--radius-lg);
                height: calc(100vh - 32px);
                overflow-y: auto;
            }
        }

        /* Swiper on mobile */
        .pdp-swiper {
            width: 100%;
            aspect-ratio: 3/4;
        }

        @media (min-width: 1024px) {
            .pdp-swiper {
                aspect-ratio: unset;
                height: calc(100vh - 32px);
            }
        }

        .pdp-swiper .swiper-slide {
            overflow: hidden;
            background: var(--surface);
        }

        @media (min-width: 1024px) {
            .pdp-swiper .swiper-slide {
                border-radius: var(--radius-lg);
            }
        }

        .pdp-swiper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            opacity: 1 !important;
            visibility: visible !important;
            transition: transform .6s cubic-bezier(.25, .46, .45, .94);
        }

        .pdp-swiper .swiper-slide:hover img {
            transform: scale(1.04);
        }

        /* Gallery overlays */
        .pdp-gallery-overlay {
            position: absolute;
            top: 14px;
            left: 14px;
            right: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 10;
            pointer-events: none;
        }

        .pdp-gallery-overlay>* {
            pointer-events: auto;
        }

        .pdp-badge-live {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(6px);
            border-radius: 99px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            color: var(--rose);
            letter-spacing: .02em;
            box-shadow: var(--shadow-sm);
        }

        .pdp-badge-live .dot {
            width: 6px;
            height: 6px;
            background: var(--rose);
            border-radius: 50%;
            animation: pdp-pulse 1.4s ease infinite;
        }

        .pdp-gallery-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .pdp-icon-btn {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(6px);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            transition: transform .2s, box-shadow .2s;
            color: var(--ink);
        }

        .pdp-icon-btn:hover {
            transform: scale(1.08);
            box-shadow: var(--shadow-md);
        }

        .pdp-icon-btn.wishlisted svg {
            fill: var(--rose);
            stroke: var(--rose);
        }

        /* Rating pill on gallery */
        .pdp-gallery-rating {
            position: absolute;
            bottom: 60px;
            left: 14px;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(0, 0, 0, .72);
            color: var(--white);
            border-radius: 99px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
        }

        .pdp-gallery-rating .star {
            color: var(--gold);
        }

        /* Placeholder state */
        .pdp-img-placeholder {
            width: 100%;
            aspect-ratio: 3/4;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: var(--surface);
            color: var(--muted);
            font-size: 13px;
        }

        /* Swiper pagination dots */
        .pdp-swiper .swiper-pagination-bullet {
            background: var(--rose);
            opacity: .4;
            width: 6px;
            height: 6px;
        }

        .pdp-swiper .swiper-pagination-bullet-active {
            opacity: 1;
            width: 18px;
            border-radius: 3px;
        }

        /* ══════════════════════════════════════════════════
   PRODUCT INFO — RIGHT COLUMN
   ══════════════════════════════════════════════════ */
        .pdp-info {
            padding: 16px 16px 24px;
            background: var(--white);
        }

        @media (min-width: 1024px) {
            .pdp-info {
                padding: 24px 32px 40px;
                overflow-y: auto;
                height: 100vh;
                scrollbar-width: thin;
                scrollbar-color: var(--border) transparent;
            }
        }

        /* Brand + title */
        .pdp-brand {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .pdp-title {
            font-family: var(--font-display);
            font-size: clamp(1.05rem, 2.5vw, 1.4rem);
            font-weight: 600;
            color: var(--ink);
            line-height: 1.35;
            margin-bottom: 10px;
        }

        /* Rating row */
        .pdp-rating-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .pdp-star-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--teal);
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .pdp-review-count {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
            border-left: 1px solid var(--border);
            padding-left: 8px;
        }

        /* Divider */
        .pdp-divider {
            height: 1px;
            background: var(--border);
            margin: 14px 0;
        }

        /* Price */
        .pdp-price-block {
            margin-bottom: 6px;
        }

        .pdp-price-row {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pdp-price-current {
            font-size: clamp(1.3rem, 3vw, 1.6rem);
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -.02em;
        }

        .pdp-price-original {
            font-size: .9rem;
            color: var(--muted);
            text-decoration: line-through;
            font-weight: 400;
        }

        .pdp-discount-tag {
            display: inline-block;
            background: var(--rose-light);
            color: var(--rose);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: .02em;
        }

        .pdp-tax-note {
            font-size: 11px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* Flash sale banner */
        .pdp-flash-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #fff0f4, #fff8f0);
            border: 1px solid #ffd6df;
            border-radius: var(--radius-md);
            padding: 10px 14px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .pdp-flash-label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 800;
            color: var(--rose);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .pdp-flash-timer {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            background: var(--white);
            border-radius: 4px;
            padding: 2px 8px;
            border: 1px solid var(--border);
        }

        /* Stock bar */
        .pdp-stock-bar {
            margin: 12px 0;
        }

        .pdp-stock-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .pdp-stock-bar-labels .urgent {
            color: #e53935;
            font-weight: 700;
        }

        .pdp-stock-bar-labels .speed {
            color: var(--muted);
        }

        .pdp-stock-bar-track {
            height: 3px;
            background: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .pdp-stock-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--rose), var(--amber));
            border-radius: 2px;
            transition: width .4s ease;
        }

        /* Scarcity alerts */
        .pdp-scarcity {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
        }

        .pdp-scarcity.low {
            background: #fff3e0;
            color: #e65100;
        }

        .pdp-scarcity.out {
            background: #fce4ec;
            color: #c62828;
        }

        /* Viewers badge */
        .pdp-viewers {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--rose);
            background: var(--rose-light);
            padding: 5px 10px;
            border-radius: 99px;
            margin-bottom: 14px;
        }

        /* ── Variant Sections ───────────────────────────────────── */
        .pdp-variant-section {
            margin-bottom: 18px;
        }

        .pdp-variant-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .pdp-variant-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pdp-variant-links {
            display: flex;
            gap: 12px;
        }

        .pdp-variant-link {
            font-size: 12px;
            color: var(--rose);
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 3px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .pdp-variant-link:hover {
            color: var(--rose-dark);
        }

        /* Size buttons */
        .pdp-size-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pdp-size-btn {
            position: relative;
            cursor: pointer;
        }

        .pdp-size-btn input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .pdp-size-btn span {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            height: 48px;
            padding: 0 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-2);
            background: var(--white);
            transition: all .18s;
            user-select: none;
        }

        .pdp-size-btn:hover span {
            border-color: var(--ink);
            color: var(--ink);
        }

        .pdp-size-btn input:checked+span {
            border-color: var(--rose);
            background: var(--rose-light);
            color: var(--rose);
            font-weight: 700;
        }

        /* Color buttons */
        .pdp-color-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pdp-color-btn {
            position: relative;
            cursor: pointer;
        }

        .pdp-color-btn input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .pdp-color-btn span {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border: 1.5px solid var(--border);
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-2);
            background: var(--white);
            transition: all .18s;
            white-space: nowrap;
        }

        .pdp-color-btn input:checked+span {
            border-color: var(--rose);
            background: var(--rose-light);
            color: var(--rose);
        }

        /* ── CTAs ───────────────────────────────────────────────── */
        .pdp-cta-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 380px) {
            .pdp-cta-group {
                grid-template-columns: 1fr;
            }
        }

        .pdp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: .03em;
            border: none;
            cursor: pointer;
            transition: all .22s cubic-bezier(.34, 1.56, .64, 1);
            text-transform: uppercase;
        }

        .pdp-btn:active {
            transform: scale(.97);
        }

        .pdp-btn-cart {
            background: var(--white);
            color: var(--rose);
            border: 2px solid var(--rose);
        }

        .pdp-btn-cart:hover {
            background: var(--rose-light);
            transform: translateY(-2px);
        }

        .pdp-btn-buy {
            background: var(--rose);
            color: var(--white);
            box-shadow: var(--shadow-btn);
        }

        .pdp-btn-buy:hover {
            background: var(--rose-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 63, 108, .45);
        }

        .pdp-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ── Delivery Section ───────────────────────────────────── */
        .pdp-delivery {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .pdp-delivery-header {
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--ink);
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .pdp-pincode-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        .pdp-pincode-row svg {
            flex-shrink: 0;
            color: var(--rose);
        }

        .pdp-pincode-input {
            flex: 1;
            border: none;
            outline: none;
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            background: transparent;
        }

        .pdp-pincode-input::placeholder {
            color: var(--muted);
        }

        .pdp-pincode-btn {
            background: none;
            border: none;
            color: var(--rose);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background .15s;
        }

        .pdp-pincode-btn:hover {
            background: var(--rose-light);
        }

        /* Pincode result messages */
        .pincode-result {
            padding: 0 16px 12px;
            font-size: 13px;
            line-height: 1.4;
        }

        .pincode-result--error {
            color: #c62828;
        }

        .pincode-result--success {
            color: var(--teal);
        }

        .pincode-result--loading {
            color: var(--muted);
        }

        .pincode-result .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 4px;
        }

        .pincode-result .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .pincode-result .badge-warning {
            background: #fff3e0;
            color: #e65100;
        }

        /* Delivery date row */
        .pdp-delivery-eta {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .pdp-delivery-eta .check {
            color: var(--teal);
            font-size: 16px;
        }

        .pdp-delivery-type {
            color: var(--muted);
            font-size: 12px;
        }

        .pdp-delivery-date {
            font-weight: 700;
            color: var(--ink);
        }

        /* Service badges */
        .pdp-services {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .pdp-service-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            line-height: 1.4;
        }

        .pdp-service-item:nth-child(even) {
            border-right: none;
        }

        .pdp-service-item:nth-last-child(-n+2) {
            border-bottom: none;
        }

        .pdp-service-item strong {
            display: block;
            color: var(--ink);
            font-weight: 700;
        }

        .pdp-service-item small {
            color: var(--muted);
            font-size: 11px;
        }

        .pdp-service-icon {
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ── Accordion / Details ────────────────────────────────── */
        .pdp-accordion {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 10px;
        }

        .pdp-accordion summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--ink);
            cursor: pointer;
            list-style: none;
            transition: background .15s;
            background: var(--white);
        }

        .pdp-accordion summary:hover {
            background: var(--surface);
        }

        .pdp-accordion summary::-webkit-details-marker {
            display: none;
        }

        .pdp-accordion-arrow {
            width: 20px;
            height: 20px;
            transition: transform .3s ease;
            color: var(--muted);
            flex-shrink: 0;
        }

        .pdp-accordion[open] .pdp-accordion-arrow {
            transform: rotate(180deg);
        }

        .pdp-accordion-body {
            padding: 14px 16px;
            font-size: 13.5px;
            color: var(--ink-2);
            line-height: 1.7;
            border-top: 1px solid var(--border);
            background: var(--white);
        }

        /* ── Specs Grid ─────────────────────────────────────────── */
        .pdp-specs-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .pdp-specs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .pdp-spec-item {
            padding: 12px 14px;
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .pdp-spec-item:nth-child(even) {
            border-right: none;
        }

        .pdp-spec-item.hidden {
            display: none;
        }

        .pdp-spec-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
        }

        .pdp-spec-value {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink);
        }

        .pdp-specs-toggle {
            width: 100%;
            padding: 10px;
            background: var(--surface);
            border: none;
            font-size: 12px;
            font-weight: 700;
            color: var(--rose);
            cursor: pointer;
            transition: background .15s;
            font-family: var(--font-body);
        }

        .pdp-specs-toggle:hover {
            background: var(--rose-light);
        }

        /* ══════════════════════════════════════════════════
   BELOW THE FOLD — FULL WIDTH SECTIONS
   ══════════════════════════════════════════════════ */
        .pdp-full-width {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* ── Ratings & Reviews ──────────────────────────────────── */
        .pdp-reviews {
            background: var(--white);
            border-top: 6px solid var(--surface);
            padding: 24px 16px;
        }

        @media (min-width: 768px) {
            .pdp-reviews {
                padding: 28px 32px;
            }
        }

        .pdp-section-title {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--ink);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pdp-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .pdp-rating-overview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: var(--surface);
            border-radius: var(--radius-lg);
        }

        .pdp-rating-big {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 64px;
        }

        .pdp-rating-number {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
        }

        .pdp-rating-stars {
            display: flex;
            gap: 2px;
        }

        .pdp-rating-star {
            color: var(--gold);
            font-size: 14px;
        }

        .pdp-rating-star.empty {
            color: var(--border);
        }

        .pdp-rating-count {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .pdp-reviews-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: none;
            scroll-snap-type: x mandatory;
        }

        .pdp-reviews-scroll::-webkit-scrollbar {
            display: none;
        }

        .pdp-review-card {
            flex-shrink: 0;
            width: 260px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px;
            scroll-snap-align: start;
            transition: box-shadow .2s, transform .2s;
        }

        .pdp-review-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        @media (min-width: 768px) {
            .pdp-review-card {
                width: 300px;
            }
        }

        .pdp-review-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .pdp-review-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--teal);
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .pdp-review-date {
            font-size: 11px;
            color: var(--muted);
        }

        .pdp-review-size {
            display: inline-block;
            background: var(--surface);
            color: var(--muted);
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            margin-bottom: 8px;
        }

        .pdp-review-text {
            font-size: 13px;
            color: var(--ink-2);
            line-height: 1.6;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .pdp-review-author {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pdp-review-verified {
            color: var(--teal);
        }

        .pdp-view-all-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
            padding: 12px 24px;
            border: 1.5px solid var(--rose);
            border-radius: var(--radius-md);
            color: var(--rose);
            font-size: 13px;
            font-weight: 700;
            background: none;
            cursor: pointer;
            width: 100%;
            transition: all .18s;
            font-family: var(--font-body);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .pdp-view-all-btn:hover {
            background: var(--rose-light);
        }

        /* ── You May Like ───────────────────────────────────────── */
        .pdp-ymal {
            background: var(--white);
            border-top: 6px solid var(--surface);
            padding: 24px 16px;
        }

        @media (min-width: 768px) {
            .pdp-ymal {
                padding: 28px 32px;
            }
        }

        .pdp-ymal-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 18px;
            border-bottom: 2px solid var(--border);
            overflow-x: auto;
            scrollbar-width: none;
        }

        .pdp-ymal-tabs::-webkit-scrollbar {
            display: none;
        }

        .pdp-ymal-tab {
            background: none;
            border: none;
            padding: 10px 18px;
            font-family: var(--font-body);
            font-size: 12.5px;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
            position: relative;
            transition: color .18s;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .pdp-ymal-tab.active {
            color: var(--rose);
        }

        .pdp-ymal-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--rose);
            border-radius: 2px 2px 0 0;
        }

        .pdp-ymal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        @media (min-width: 640px) {
            .pdp-ymal-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .pdp-ymal-card {
            position: relative;
            cursor: pointer;
        }

        .pdp-ymal-card-img {
            aspect-ratio: 3/4;
            overflow: hidden;
            background: var(--surface);
            border-radius: var(--radius-md);
            position: relative;
        }

        .pdp-ymal-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s ease;
        }

        .pdp-ymal-card:hover .pdp-ymal-card-img img {
            transform: scale(1.05);
        }

        .pdp-ymal-card-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0, 0, 0, .7);
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .pdp-ymal-wishlist {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(4px);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: var(--muted);
            transition: all .18s;
        }

        .pdp-ymal-wishlist:hover {
            color: var(--rose);
            transform: scale(1.1);
        }

        .pdp-ymal-card-info {
            padding: 8px 2px 0;
        }

        .pdp-ymal-card-brand {
            font-size: 11px;
            font-weight: 700;
            color: var(--ink);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .pdp-ymal-card-name {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .pdp-ymal-card-price {
            font-size: 13px;
            font-weight: 800;
            color: var(--ink);
        }

        .pdp-ymal-card-savings {
            font-size: 11px;
            font-weight: 700;
            color: var(--rose);
        }

        /* ── FBT Section ────────────────────────────────────────── */
        .pdp-fbt {
            background: var(--white);
            border-top: 6px solid var(--surface);
            padding: 24px 16px;
            display: none;
        }

        @media (min-width: 768px) {
            .pdp-fbt {
                padding: 28px 32px;
            }
        }

        .pdp-fbt-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        /* ── Q&A Section ────────────────────────────────────────── */
        .pdp-qa {
            background: var(--white);
            border-top: 6px solid var(--surface);
            padding: 24px 16px;
        }

        @media (min-width: 768px) {
            .pdp-qa {
                padding: 28px 32px;
                max-width: 900px;
                margin: 0 auto;
            }
        }

        .pdp-qa-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }

        .pdp-qa-textarea {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            font-size: 13.5px;
            font-family: var(--font-body);
            resize: vertical;
            min-height: 80px;
            outline: none;
            transition: border-color .18s;
            color: var(--ink);
        }

        .pdp-qa-textarea:focus {
            border-color: var(--rose);
        }

        .pdp-qa-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pdp-char-count {
            font-size: 11px;
            color: var(--muted);
        }

        .pdp-qa-submit {
            background: var(--rose);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-body);
            text-transform: uppercase;
            letter-spacing: .04em;
            transition: background .18s, transform .18s;
        }

        .pdp-qa-submit:hover {
            background: var(--rose-dark);
            transform: translateY(-1px);
        }

        .pdp-qa-submit:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }

        .pdp-qa-login-note {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .pdp-qa-login-note a {
            color: var(--rose);
            font-weight: 700;
        }

        .pdp-qa-item {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 10px;
            transition: box-shadow .18s;
        }

        .pdp-qa-item:hover {
            box-shadow: var(--shadow-sm);
        }

        .pdp-qa-q {
            font-weight: 700;
            font-size: 13px;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .pdp-qa-q::before {
            content: 'Q  ';
            color: var(--rose);
            font-weight: 800;
        }

        .pdp-qa-a {
            font-size: 13px;
            color: var(--ink-2);
            background: var(--teal-light);
            border-left: 3px solid var(--teal);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            padding: 8px 12px;
        }

        .pdp-qa-a::before {
            content: 'A  ';
            color: var(--teal);
            font-weight: 800;
        }

        .pdp-qa-pending {
            font-size: 12px;
            color: var(--muted);
            font-style: italic;
        }

        .pdp-qa-date {
            font-size: 11px;
            color: var(--border);
            margin-top: 6px;
        }

        .pdp-qa-empty {
            color: var(--muted);
            font-size: 13px;
        }

        /* ── Mobile sticky CTA ──────────────────────────────────── */
        .pdp-mobile-cta {
            display: none;
            position: fixed;
            bottom: calc(var(--bottom-nav-height, 60px) + env(safe-area-inset-bottom));
            left: 0;
            right: 0;
            z-index: 200;
            padding: 10px 14px;
            background: var(--white);
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, .08);
            gap: 10px;
        }

        @media (max-width: 767px) {
            .pdp-mobile-cta {
                display: flex;
            }
        }

        /* ── Modals ─────────────────────────────────────────────── */
        .pdp-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 5000;
        }

        .pdp-modal-overlay.open {
            display: block;
        }

        .pdp-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            backdrop-filter: blur(2px);
        }

        .pdp-modal-sheet {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 560px;
            background: var(--white);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: pdp-sheet-up .3s cubic-bezier(.34, 1.56, .64, 1);
        }

        .pdp-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .pdp-modal-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdp-modal-close {
            width: 32px;
            height: 32px;
            background: var(--surface);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s;
        }

        .pdp-modal-close:hover {
            background: var(--border);
        }

        .pdp-modal-body {
            overflow-y: auto;
            padding: 20px;
            flex: 1;
            scrollbar-width: thin;
        }

        /* Size modal inputs */
        .pdp-size-input-group {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }

        .pdp-size-input-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .pdp-size-input {
            width: 100%;
            padding: 11px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            outline: none;
            transition: border-color .18s;
            color: var(--ink);
        }

        .pdp-size-input:focus {
            border-color: var(--rose);
        }

        .pdp-size-find-btn {
            width: 100%;
            padding: 14px;
            background: var(--rose);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            cursor: pointer;
            font-family: var(--font-body);
            transition: background .18s;
        }

        .pdp-size-find-btn:hover {
            background: var(--rose-dark);
        }

        .pdp-size-result {
            display: none;
            margin-top: 16px;
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
        }

        .pdp-size-result-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .pdp-size-result-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rose);
            line-height: 1;
            margin-bottom: 6px;
        }

        .pdp-size-result-note {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Animations */
        @keyframes pdp-pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .5;
                transform: scale(1.3);
            }
        }

        @keyframes pdp-sheet-up {
            from {
                transform: translateX(-50%) translateY(40px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        @keyframes pdp-fade-in {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pdp-info>* {
            animation: pdp-fade-in .35s ease both;
        }

        /* Scroll reveal */
        .pdp-reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity .5s ease, transform .5s ease;
        }

        .pdp-reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* More info footer */
        .pdp-more-info {
            padding: 14px 16px;
            font-size: 11.5px;
            color: var(--muted);
            border-top: 1px solid var(--border);
        }

        .pdp-more-info a {
            color: var(--rose);
            font-weight: 600;
        }

        /* ── Responsive polish ──────────────────────────────────── */
        @media (max-width: 767px) {
            .pdp-info {
                padding-bottom: 80px;
            }

            .pdp-delivery-header,
            .pdp-specs-card,
            .pdp-accordion,
            .pdp-variant-section:last-of-type {
                border-radius: var(--radius-md);
            }
        }

        /* Swiper on desktop — no swiper, show all as stacked */
        @media (min-width: 1024px) {
            .pdp-swiper .swiper-wrapper {
                display: flex;
                flex-direction: column;
                gap: 8px;
                transform: none !important;
            }

            .pdp-swiper .swiper-slide {
                width: 100% !important;
                height: auto;
                aspect-ratio: 3/4;
                flex-shrink: 0;
            }

            .pdp-swiper .swiper-pagination {
                display: none;
            }

            .pdp-main-image-wrap {
                overflow-y: auto;
                scrollbar-width: none;
            }

            .pdp-main-image-wrap::-webkit-scrollbar {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main-content">

        <!-- Breadcrumb -->
        <nav class="pdp-breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span class="sep">›</span>
            <a href="/shop.php">Shop</a>
            <?php if (!empty($catName)): ?>
                <span class="sep">›</span>
                <a href="/shop.php?category=<?= urlencode($catName) ?>"><?= h($catName) ?></a>
            <?php endif; ?>
            <span class="sep">›</span>
            <span class="current"><?= h(mb_substr($product['name'], 0, 40)) ?></span>
        </nav>

        <!-- ═══ MAIN PRODUCT LAYOUT ════════════════════════════════════ -->
        <div class="pdp-layout">

            <!-- ── LEFT: Image Gallery ──────────────────────────────── -->
            <div class="pdp-gallery">

                <!-- Desktop thumbnails -->
                <?php if ($hasRealImages && count($images) > 1): ?>
                    <div class="pdp-thumbs">
                        <?php foreach ($images as $idx => $img): ?>
                            <div class="pdp-thumb <?= $idx === 0 ? 'active' : '' ?>" onclick="pdpChangeImage(<?= $idx ?>, this)">
                                <img src="<?= h(img_url($img)) ?>" alt="View <?= $idx + 1 ?>" loading="lazy"
                                    onerror="this.src='/assets/img/placeholder.svg'">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Main image -->
                <div class="pdp-main-image-wrap">
                    <?php if (!$hasRealImages): ?>
                        <div class="pdp-img-placeholder">
                            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="m21 15-5-5L5 21" />
                            </svg>
                            <span>Product image coming soon</span>
                        </div>
                    <?php else: ?>
                        <div class="pdp-swiper swiper mySwiper" id="pdpGallery">
                            <div class="swiper-wrapper">
                                <?php foreach ($images as $idx => $img):
                                    $imgSrc = img_url($img);
                                    if (!$imgSrc) $imgSrc = '/assets/img/placeholder.svg';
                                    $webp = get_webp_url($imgSrc);
                                ?>
                                    <div class="swiper-slide">
                                        <picture>
                                            <?php if ($webp !== $imgSrc): ?>
                                                <source srcset="<?= h($webp) ?>" type="image/webp">
                                            <?php endif; ?>
                                            <img id="pdp-slide-<?= $idx ?>"
                                                class="main-img-slide<?= $idx === 0 ? ' active-slide' : '' ?>"
                                                src="<?= h($imgSrc) ?>"
                                                alt="<?= h($product['name']) ?> - Image <?= $idx + 1 ?>"
                                                loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>"
                                                onerror="this.src='/assets/img/placeholder.svg'">
                                        </picture>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>

                        <!-- Gallery overlays -->
                        <div class="pdp-gallery-overlay">
                            <div class="pdp-badge-live">
                                <span class="dot"></span>
                                <?= rand(40, 150) ?> viewing
                            </div>
                            <div class="pdp-gallery-actions">
                                <button class="pdp-icon-btn" onclick="pdpShare()" aria-label="Share product">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="18" cy="5" r="3" />
                                        <circle cx="6" cy="12" r="3" />
                                        <circle cx="18" cy="19" r="3" />
                                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                                    </svg>
                                </button>
                                <button class="pdp-icon-btn" id="wishlistBtn" onclick="toggleWishlist(<?= (int)$product['id'] ?>, this)" aria-label="Add to wishlist">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <?php if ($avgRating > 0): ?>
                            <div class="pdp-gallery-rating">
                                <span class="star">★</span>
                                <?= $avgRating ?>
                                <?php if ($reviewCount > 0): ?>
                                    <span style="opacity:.7">| <?= $reviewCount >= 1000 ? round($reviewCount / 1000, 1) . 'k' : $reviewCount ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── RIGHT: Product Info ──────────────────────────────── -->
            <form class="pdp-info" id="addToCartForm" onsubmit="event.preventDefault(); addToCart(this);">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <!-- Brand -->
                <div class="pdp-brand"><?= h(SITE_NAME) ?></div>

                <!-- Title + actions -->
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;">
                    <h1 class="pdp-title"><?= h($product['name']) ?></h1>
                    <div style="display:flex;gap:8px;flex-shrink:0;margin-top:4px;">
                        <button type="button" class="pdp-icon-btn" onclick="pdpShare()" aria-label="Share" style="width:34px;height:34px;background:var(--surface);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="18" cy="5" r="3" />
                                <circle cx="6" cy="12" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Ratings -->
                <div class="pdp-rating-row">
                    <?php if ($reviewCount > 0): ?>
                        <span class="pdp-star-badge">★ <?= $avgRating ?></span>
                        <span class="pdp-review-count"><?= $reviewCount ?> Rating<?= $reviewCount !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                        <span class="pdp-review-count" style="border:none;padding:0;">Be the first to review</span>
                    <?php endif; ?>
                </div>

                <div class="pdp-divider"></div>

                <!-- Price -->
                <div class="pdp-price-block">
                    <?php if ($flashSale): ?>
                        <div class="pdp-flash-banner">
                            <span class="pdp-flash-label">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                                </svg>
                                Flash Sale
                            </span>
                            <span>Ends in</span>
                            <span class="pdp-flash-timer" id="flashTimer">--:--:--</span>
                            <div id="flashCountdown" data-ends="<?= h($flashSale['ends_at']) ?>" style="display:none;"></div>
                        </div>
                    <?php endif; ?>

                    <div class="pdp-price-row">
                        <span class="pdp-price-current"><?= CURRENCY . number_format($displayPrice, 0) ?></span>
                        <?php if ($flashSale): ?>
                            <span class="pdp-price-original"><?= CURRENCY . number_format($product['price'], 0) ?></span>
                            <span class="pdp-discount-tag"><?= round((($product['price'] - $displayPrice) / $product['price']) * 100) ?>% OFF</span>
                        <?php elseif (!empty($product['original_price']) && $product['original_price'] > $product['price']): ?>
                            <span class="pdp-price-original"><?= CURRENCY . number_format($product['original_price'], 0) ?></span>
                            <span class="pdp-discount-tag"><?= round((($product['original_price'] - $product['price']) / $product['original_price']) * 100) ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    <p class="pdp-tax-note">inclusive of all taxes</p>
                </div>

                <!-- Stock urgency bar -->
                <?php if ($product['stock'] > 0 && $product['stock'] <= 20): ?>
                    <div class="pdp-stock-bar">
                        <div class="pdp-stock-bar-labels">
                            <span class="urgent">Only <?= $product['stock'] ?> left!</span>
                            <span class="speed">Selling fast</span>
                        </div>
                        <div class="pdp-stock-bar-track">
                            <div class="pdp-stock-bar-fill" style="width:<?= min(95, 100 - ($product['stock'] * 3)) ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Scarcity / Out of stock -->
                <?php if ($product['stock'] > 0 && $product['stock'] <= ($product['low_stock_alert'] ?? 5)): ?>
                    <div class="pdp-scarcity low">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M12 2v10M12 16v2M12 21v1" />
                        </svg>
                        Hurry! Only <?= $product['stock'] ?> left in stock
                    </div>
                <?php elseif ($product['stock'] <= 0): ?>
                    <div class="pdp-scarcity out">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="15" y1="9" x2="9" y2="15" />
                            <line x1="9" y1="9" x2="15" y2="15" />
                        </svg>
                        Out of Stock
                    </div>
                <?php endif; ?>

                <div class="pdp-divider"></div>

                <!-- Size Selector -->
                <?php if (!empty($product['sizes'])): ?>
                    <div class="pdp-variant-section">
                        <div class="pdp-variant-header">
                            <span class="pdp-variant-label">Select Size</span>
                            <div class="pdp-variant-links">
                                <button type="button" class="pdp-variant-link" onclick="pdpOpenSizeChart()">Size Chart</button>
                                <button type="button" class="pdp-variant-link" id="findMySizeLink" onclick="pdpOpenSizeModal()">Find My Size</button>
                            </div>
                        </div>
                        <div class="pdp-size-grid">
                            <?php foreach (explode(',', $product['sizes']) as $size):
                                $size = trim($size);
                                if (!$size) continue; ?>
                                <label class="pdp-size-btn">
                                    <input type="radio" name="size" value="<?= h($size) ?>">
                                    <span><?= h($size) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Color Selector -->
                <?php if (!empty($product['colors'])): ?>
                    <div class="pdp-variant-section">
                        <div class="pdp-variant-header">
                            <span class="pdp-variant-label">Available Colors</span>
                        </div>
                        <div class="pdp-color-grid">
                            <?php foreach (explode(',', $product['colors']) as $color):
                                $color = trim($color);
                                if (!$color) continue; ?>
                                <label class="pdp-color-btn">
                                    <input type="radio" name="color" value="<?= h($color) ?>">
                                    <span><?= h($color) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- CTA Buttons -->
                <?php if ($product['stock'] > 0): ?>
                    <div class="pdp-cta-group">
                        <button type="submit" class="pdp-btn pdp-btn-cart">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2 3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                                <line x1="3" y1="6" x2="21" y2="6" />
                                <path d="M16 10a4 4 0 01-8 0" />
                            </svg>
                            Add to Bag
                        </button>
                        <button type="button" class="pdp-btn pdp-btn-buy" onclick="buyNow(this.form)">
                            Buy Now
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="pdp-cta-group">
                        <button type="button" class="pdp-btn pdp-btn-cart" disabled style="grid-column:1/-1;">Sold Out</button>
                    </div>
                <?php endif; ?>

                <!-- Delivery & Services -->
                <div class="pdp-delivery">
                    <div class="pdp-delivery-header">Delivery &amp; Services</div>

                    <div class="pdp-pincode-row">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                            <circle cx="12" cy="10" r="3" />
                        </svg>
                        <input type="text" id="pincodeInput" name="pincode" class="pdp-pincode-input" placeholder="Enter pincode for delivery info" maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                        <button type="button" class="pdp-pincode-btn check-pin-btn">Check</button>
                    </div>
                    <div id="pincodeResult" class="pincode-result"></div>

                    <div class="pdp-delivery-eta">
                        <span class="check">✔</span>
                        <div>
                            <div class="pdp-delivery-type">Standard Delivery</div>
                            <div class="pdp-delivery-date">Estimated by <?= date('D, d M', strtotime('+3 days')) ?></div>
                        </div>
                    </div>

                    <div class="pdp-services">
                        <div class="pdp-service-item">
                            <span class="pdp-service-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--rose)" stroke-width="1.8" stroke-linecap="round">
                                    <rect x="2" y="7" width="20" height="14" rx="2" />
                                    <path d="M16 3H8L2 7h20z" />
                                </svg>
                            </span>
                            <div><strong>Cash on Delivery</strong><small>₹10 additional fee</small></div>
                        </div>
                        <div class="pdp-service-item">
                            <span class="pdp-service-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0" />
                                    <path d="M12 8v4l3 3" />
                                </svg>
                            </span>
                            <div><strong>7 Days Exchange</strong><small>Hassle free</small></div>
                        </div>
                        <div class="pdp-service-item">
                            <span class="pdp-service-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--rose)" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M9 14l-4-4 4-4" />
                                    <path d="M5 10h11a4 4 0 010 8h-1" />
                                </svg>
                            </span>
                            <div><strong>Easy Returns</strong><small>7 days return policy</small></div>
                        </div>
                        <div class="pdp-service-item">
                            <span class="pdp-service-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="1.8" stroke-linecap="round">
                                    <rect x="1" y="3" width="15" height="13" rx="1" />
                                    <path d="M16 8h4l3 3v5h-7V8z" />
                                    <circle cx="5.5" cy="18.5" r="2.5" />
                                    <circle cx="18.5" cy="18.5" r="2.5" />
                                </svg>
                            </span>
                            <div><strong>Free Shipping</strong><small>On all prepaid orders</small></div>
                        </div>
                    </div>
                </div>

                <!-- Description accordion -->
                <details class="pdp-accordion" open>
                    <summary>
                        Description
                        <svg class="pdp-accordion-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="pdp-accordion-body">
                        <?php
                        $desc = trim($product['description'] ?? '');
                        if (!empty($desc) && strtoupper($desc) !== 'XYZ' && strlen($desc) > 3):
                            echo nl2br(h($desc));
                        else: ?>
                            <span style="color:var(--muted);font-style:italic;">Product description coming soon.</span>
                        <?php endif; ?>
                    </div>
                </details>

                <?php if (!empty($product['fabric']) || !empty($product['care'])): ?>
                    <details class="pdp-accordion">
                        <summary>
                            Fabric &amp; Care
                            <svg class="pdp-accordion-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9l6 6 6-6" />
                            </svg>
                        </summary>
                        <div class="pdp-accordion-body">
                            <?php if (!empty($product['fabric'])): ?><p><strong>Material:</strong> <?= h($product['fabric']) ?></p><?php endif; ?>
                            <?php if (!empty($product['care'])): ?><p style="margin-top:8px;"><strong>Care Instructions:</strong><br><?= nl2br(h($product['care'])) ?></p><?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <!-- Specs grid -->
                <div class="pdp-specs-card">
                    <div class="pdp-specs-grid" id="pdpSpecsGrid">
                        <?php foreach (array_slice($specs, 0, 4) as $spec): ?>
                            <div class="pdp-spec-item">
                                <span class="pdp-spec-label"><?= $spec[0] ?></span>
                                <span class="pdp-spec-value"><?= $spec[1] ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($specs, 4) as $spec): ?>
                            <div class="pdp-spec-item hidden">
                                <span class="pdp-spec-label"><?= $spec[0] ?></span>
                                <span class="pdp-spec-value"><?= $spec[1] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($specs) > 4): ?>
                        <button type="button" class="pdp-specs-toggle" onclick="pdpToggleSpecs(this)">See more details ›</button>
                    <?php endif; ?>
                </div>

            </form><!-- .pdp-info -->
        </div><!-- .pdp-layout -->

        <!-- ═══ RATINGS & REVIEWS ══════════════════════════════════════ -->
        <section class="pdp-reviews pdp-reveal" id="ratingsSection">
            <div class="pdp-full-width">
                <h2 class="pdp-section-title">Ratings &amp; Reviews</h2>

                <?php if ($avgRating > 0 || $reviewCount > 0): ?>
                    <div class="pdp-rating-overview">
                        <div class="pdp-rating-big">
                            <div class="pdp-rating-number"><?= $avgRating > 0 ? $avgRating : '—' ?></div>
                            <div class="pdp-rating-stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <span class="pdp-rating-star <?= $s > round($avgRating) ? 'empty' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <div class="pdp-rating-count"><?= $reviewCount ?> ratings</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($recentReviews)): ?>
                    <div class="pdp-reviews-scroll">
                        <?php foreach ($recentReviews as $rv): ?>
                            <div class="pdp-review-card">
                                <div class="pdp-review-top">
                                    <span class="pdp-review-pill">★ <?= number_format((float)$rv['rating'], 1) ?></span>
                                    <span class="pdp-review-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                                </div>
                                <?php if (!empty($rv['size'])): ?>
                                    <span class="pdp-review-size">Size: <?= h($rv['size']) ?></span>
                                <?php endif; ?>
                                <p class="pdp-review-text"><?= h($rv['review_text'] ?? $rv['comment'] ?? '') ?></p>
                                <div class="pdp-review-author">
                                    <?= h($rv['reviewer_name'] ?? $rv['name'] ?? 'Verified Buyer') ?>
                                    <span class="pdp-review-verified">✓ Verified</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--muted);margin:8px 0 0;">No reviews yet. Be the first to share your experience!</p>
                <?php endif; ?>

                <?php if ($reviewCount > 3): ?>
                    <button type="button" class="pdp-view-all-btn" onclick="pdpOpenReviewsModal()">
                        View All <?= $reviewCount ?> Reviews
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <!-- ═══ YOU MAY ALSO LIKE ══════════════════════════════════════ -->
        <?php if (!empty($recommendations)): ?>
            <section class="pdp-ymal pdp-reveal">
                <div class="pdp-full-width">
                    <h2 class="pdp-section-title">You May Also Like</h2>

                    <div class="pdp-ymal-tabs">
                        <button type="button" class="pdp-ymal-tab active" onclick="pdpSetTab(this)">All</button>
                        <button type="button" class="pdp-ymal-tab" onclick="pdpSetTab(this)">Similar</button>
                        <button type="button" class="pdp-ymal-tab" onclick="pdpSetTab(this)">Your Next Favourites</button>
                    </div>

                    <div class="pdp-ymal-grid">
                        <?php foreach ($recommendations as $rec):
                            $recDiscount = 0;
                            $recSavings = 0;
                            if (!empty($rec['original_price']) && (float)$rec['original_price'] > (float)$rec['price']) {
                                $recDiscount = round((1 - $rec['price'] / $rec['original_price']) * 100);
                                $recSavings  = (int)((float)$rec['original_price'] - (float)$rec['price']);
                            }
                            $recHref = 'product.php?id=' . (int)$rec['id'] . '&slug=' . urlencode($rec['slug'] ?? '');
                            $recImg  = img_url($rec['image_url'] ?? '');
                            $recWebp = get_webp_url($recImg);
                        ?>
                            <div class="pdp-ymal-card">
                                <a href="<?= h($recHref) ?>" style="display:block;text-decoration:none;color:inherit;">
                                    <div class="pdp-ymal-card-img">
                                        <picture>
                                            <?php if ($recWebp !== $recImg): ?>
                                                <source srcset="<?= h($recWebp) ?>" type="image/webp">
                                            <?php endif; ?>
                                            <img src="<?= h($recImg) ?>" alt="<?= h($rec['name']) ?>" loading="lazy"
                                                onerror="this.src='/assets/img/placeholder.svg'">
                                        </picture>
                                        <?php if ($recDiscount > 0): ?>
                                            <span class="pdp-ymal-card-badge"><?= $recDiscount ?>% OFF</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pdp-ymal-card-info">
                                        <p class="pdp-ymal-card-brand"><?= h(SITE_NAME) ?></p>
                                        <p class="pdp-ymal-card-name"><?= h($rec['name']) ?></p>
                                        <p class="pdp-ymal-card-price">
                                            <?= h(CURRENCY) ?><?= number_format((float)$rec['price'], 0) ?>
                                            <?php if ($recDiscount > 0): ?>
                                                <span style="text-decoration:line-through;color:var(--muted);font-size:.72rem;font-weight:400;margin-left:4px;"><?= h(CURRENCY) ?><?= number_format((float)$rec['original_price'], 0) ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($recSavings > 0): ?>
                                            <p class="pdp-ymal-card-savings"><?= h(CURRENCY) ?><?= number_format($recSavings, 0) ?> OFF</p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <button type="button" class="pdp-ymal-wishlist" onclick="toggleWishlist(<?= (int)$rec['id'] ?>, this)" aria-label="Wishlist">♡</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pdp-more-info">
                        Product Code: <?= (int)$product['id'] ?> &nbsp;·&nbsp;
                        <a href="<?= h('product.php?id=' . $product['id'] . '&slug=' . $product['slug']) ?>">View Product</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ═══ FREQUENTLY BOUGHT TOGETHER ════════════════════════════ -->
        <section class="pdp-fbt pdp-reveal" id="fbtSection">
            <div class="pdp-full-width">
                <h2 class="pdp-section-title">Frequently Bought Together</h2>
                <div class="pdp-fbt-grid" id="fbtGrid"></div>
            </div>
        </section>

        <!-- ═══ QUESTIONS & ANSWERS ════════════════════════════════════ -->
        <section class="pdp-qa pdp-reveal">
            <h2 class="pdp-section-title">Questions &amp; Answers</h2>

            <?php if (isLoggedIn()): ?>
                <form class="pdp-qa-form" id="qaForm" onsubmit="pdpSubmitQuestion(event)">
                    <textarea class="pdp-qa-textarea" id="qaQuestion"
                        placeholder="Ask a question about this product… (min 10 characters)"
                        maxlength="500" aria-label="Your question"
                        oninput="document.getElementById('pdpCharCount').textContent=this.value.length+'/500'"></textarea>
                    <div class="pdp-qa-footer">
                        <span class="pdp-char-count" id="pdpCharCount">0/500</span>
                        <button type="submit" class="pdp-qa-submit">Ask Question</button>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                </form>
            <?php else: ?>
                <p class="pdp-qa-login-note"><a href="/login.php">Log in</a> to ask a question about this product.</p>
            <?php endif; ?>

            <div id="qaList">
                <p class="pdp-qa-empty">Loading questions…</p>
            </div>
        </section>

    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <!-- ═══ MODALS ══════════════════════════════════════════════════════ -->

    <!-- Find My Size Modal -->
    <div id="pdpSizeModal" class="pdp-modal-overlay" role="dialog" aria-modal="true" aria-label="Find My Size">
        <div class="pdp-modal-backdrop" onclick="pdpCloseSizeModal()"></div>
        <div class="pdp-modal-sheet">
            <div class="pdp-modal-header">
                <div class="pdp-modal-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M21 7.5V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14c0 1.1.9 2 2 2h14a2 2 0 002-2V10" />
                        <path d="M2 10h20" />
                    </svg>
                    Find My Size
                </div>
                <button class="pdp-modal-close" onclick="pdpCloseSizeModal()" aria-label="Close">×</button>
            </div>
            <div class="pdp-modal-body">
                <p style="font-size:12.5px;color:var(--muted);margin-bottom:18px;line-height:1.6;">Enter your measurements in centimetres to get a size recommendation.</p>
                <div class="pdp-size-input-group">
                    <div>
                        <label class="pdp-size-input-label" for="smBust">Bust (cm)</label>
                        <input type="number" id="smBust" class="pdp-size-input" min="60" max="140" step="0.5" placeholder="e.g. 88">
                    </div>
                    <div>
                        <label class="pdp-size-input-label" for="smWaist">Waist (cm)</label>
                        <input type="number" id="smWaist" class="pdp-size-input" min="50" max="130" step="0.5" placeholder="e.g. 70">
                    </div>
                    <div>
                        <label class="pdp-size-input-label" for="smHip">Hip (cm)</label>
                        <input type="number" id="smHip" class="pdp-size-input" min="70" max="150" step="0.5" placeholder="e.g. 94">
                    </div>
                </div>
                <button type="button" class="pdp-size-find-btn" onclick="pdpFindMySize()">Find My Size</button>
                <div class="pdp-size-result" id="pdpSizeResult">
                    <div class="pdp-size-result-label">Recommended Size</div>
                    <div class="pdp-size-result-value" id="pdpSizeResultLabel"></div>
                    <div class="pdp-size-result-note" id="pdpSizeResultNote"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- All Reviews Modal -->
    <?php if ($reviewCount > 3): ?>
        <div id="pdpReviewsModal" class="pdp-modal-overlay" role="dialog" aria-modal="true" aria-label="All Ratings & Reviews">
            <div class="pdp-modal-backdrop" onclick="pdpCloseReviewsModal()"></div>
            <div class="pdp-modal-sheet">
                <div class="pdp-modal-header">
                    <div class="pdp-modal-title">
                        Ratings &amp; Reviews
                        <?php if ($avgRating > 0): ?>
                            <span style="background:var(--teal);color:#fff;font-size:.78rem;padding:2px 8px;border-radius:4px;">★ <?= $avgRating ?></span>
                        <?php endif; ?>
                        <span style="color:var(--muted);font-weight:400;font-size:.8rem;">(<?= $reviewCount ?>)</span>
                    </div>
                    <button class="pdp-modal-close" onclick="pdpCloseReviewsModal()" aria-label="Close">×</button>
                </div>
                <div class="pdp-modal-body">
                    <?php foreach ($allReviews as $rv): ?>
                        <div style="border-bottom:1px solid var(--border);padding:14px 0;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                <span class="pdp-review-pill">★ <?= number_format((float)$rv['rating'], 1) ?></span>
                                <span style="font-size:11px;color:var(--muted);"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                                <?php if (!empty($rv['size'])): ?>
                                    <span class="pdp-review-size">Size: <?= h($rv['size']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p style="font-size:13px;color:var(--ink-2);margin:0 0 8px;line-height:1.6;"><?= h($rv['review_text'] ?? $rv['comment'] ?? '') ?></p>
                            <div class="pdp-review-author">
                                <?= h($rv['reviewer_name'] ?? $rv['name'] ?? 'Verified Buyer') ?>
                                <span class="pdp-review-verified">✓ Verified</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>
    <script src="/assets/js/pincode.js"></script>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        /* ═══════════════════════════════════════════════════════
   MAVDEE PDP — JavaScript
   ═══════════════════════════════════════════════════════ */

        // ── Swiper (mobile only) ─────────────────────────────────
        var pdpSwiper = null;

        function pdpInitSwiper() {
            if (pdpSwiper || typeof Swiper === 'undefined') return;
            pdpSwiper = new Swiper(".mySwiper", {
                loop: false,
                grabCursor: true,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                    dynamicBullets: true
                },
                on: {
                    slideChange: function() {
                        var idx = this.activeIndex;
                        document.querySelectorAll('.pdp-thumb').forEach(function(t, i) {
                            t.classList.toggle('active', i === idx);
                        });
                    }
                }
            });
            requestAnimationFrame(function() {
                if (pdpSwiper) pdpSwiper.update();
            });
        }

        function pdpDestroySwiper() {
            if (pdpSwiper) {
                pdpSwiper.destroy(true, true);
                pdpSwiper = null;
            }
        }

        var pdpMq = window.matchMedia('(max-width: 1023px)');

        function pdpHandleMq(e) {
            if (e.matches) pdpInitSwiper();
            else pdpDestroySwiper();
        }

        pdpMq.addEventListener('change', pdpHandleMq);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                pdpHandleMq(pdpMq);
            });
        } else {
            pdpHandleMq(pdpMq);
        }

        // ── Desktop image switch ────────────────────────────────
        function pdpChangeImage(index, el) {
            document.querySelectorAll('.main-img-slide').forEach(function(img) {
                img.classList.remove('active-slide');
            });
            var slide = document.getElementById('pdp-slide-' + index);
            if (slide) {
                slide.classList.add('active-slide');
                if (window.innerWidth >= 1024) {
                    var swiperSlide = slide.closest('.swiper-slide');
                    if (swiperSlide) swiperSlide.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
            document.querySelectorAll('.pdp-thumb').forEach(function(t) {
                t.classList.remove('active');
            });
            if (el) el.classList.add('active');
            if (pdpSwiper) pdpSwiper.slideTo(index);
        }

        // ── Desktop scroll sync ─────────────────────────────────
        (function() {
            if (!('IntersectionObserver' in window)) return;
            var wrap = document.querySelector('.pdp-main-image-wrap');
            if (!wrap) return;
            var obs = new IntersectionObserver(function(entries) {
                entries.forEach(function(e) {
                    if (e.isIntersecting && window.innerWidth >= 1024) {
                        var img = e.target.querySelector('.main-img-slide');
                        if (img && img.id) {
                            var idx = parseInt(img.id.replace('pdp-slide-', ''), 10);
                            if (!isNaN(idx)) {
                                document.querySelectorAll('.pdp-thumb').forEach(function(t, i) {
                                    t.classList.toggle('active', i === idx);
                                });
                            }
                        }
                    }
                });
            }, {
                root: wrap,
                threshold: 0.5
            });
            document.querySelectorAll('.swiper-slide').forEach(function(s) {
                obs.observe(s);
            });
        })();

        // ── Share ────────────────────────────────────────────────
        function pdpShare() {
            var url = window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    url: url
                }).catch(function() {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() {
                    showToast && showToast('Link copied!', 'success');
                });
            } else {
                prompt('Copy this link:', url);
            }
        }

        // Legacy alias
        function shareProduct() {
            pdpShare();
        }

        // ── Specs toggle ─────────────────────────────────────────
        function pdpToggleSpecs(btn) {
            var hidden = document.querySelectorAll('.pdp-spec-item.hidden');
            var expanded = btn.dataset.expanded === '1';
            hidden.forEach(function(el) {
                el.style.display = expanded ? '' : 'flex';
            });
            btn.dataset.expanded = expanded ? '' : '1';
            btn.textContent = expanded ? 'See more details ›' : 'See less ‹';
        }

        // Legacy alias
        function toggleSpecs(btn) {
            pdpToggleSpecs(btn);
        }

        // ── YMAL tabs ────────────────────────────────────────────
        function pdpSetTab(btn) {
            document.querySelectorAll('.pdp-ymal-tab').forEach(function(t) {
                t.classList.remove('active');
            });
            btn.classList.add('active');
        }

        function setYmalTab(btn) {
            pdpSetTab(btn);
        }

        // ── Size modal ───────────────────────────────────────────
        function pdpOpenSizeModal() {
            var m = document.getElementById('pdpSizeModal');
            if (m) {
                m.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        function pdpCloseSizeModal() {
            var m = document.getElementById('pdpSizeModal');
            if (m) {
                m.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        function pdpOpenSizeChart() {
            pdpOpenSizeModal();
        }

        // Legacy aliases
        function openSizeModal() {
            pdpOpenSizeModal();
        }

        function closeSizeModal() {
            pdpCloseSizeModal();
        }

        function pdpFindMySize() {
            var bust = parseFloat(document.getElementById('smBust').value) || 0;
            var waist = parseFloat(document.getElementById('smWaist').value) || 0;
            var hip = parseFloat(document.getElementById('smHip').value) || 0;

            if (!bust && !waist && !hip) {
                alert('Please enter at least one measurement.');
                return;
            }

            var category = '<?= addslashes($catName ?: 'general') ?>';

            fetch('/api/size/recommend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        bust,
                        waist,
                        hip,
                        category
                    })
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    document.getElementById('pdpSizeResultLabel').textContent = data.recommended_size || '';
                    document.getElementById('pdpSizeResultNote').textContent = data.fit_notes || '';
                    document.getElementById('pdpSizeResult').style.display = '';

                    document.querySelectorAll('.pdp-size-btn input[type=radio]').forEach(function(radio) {
                        if (radio.value.trim().toUpperCase() === (data.recommended_size || '').toUpperCase()) {
                            radio.checked = true;
                            var span = radio.nextElementSibling;
                            if (span) {
                                span.style.background = 'var(--rose-light)';
                                span.style.color = 'var(--rose)';
                                span.style.borderColor = 'var(--rose)';
                            }
                        }
                    });
                })
                .catch(function() {
                    alert('Could not fetch size recommendation. Please try again.');
                });
        }

        function findMySize() {
            pdpFindMySize();
        }

        // ── Reviews modal ────────────────────────────────────────
        function pdpOpenReviewsModal() {
            var m = document.getElementById('pdpReviewsModal');
            if (m) {
                m.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        function pdpCloseReviewsModal() {
            var m = document.getElementById('pdpReviewsModal');
            if (m) {
                m.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        // Legacy aliases (keep for compatibility)
        function openAllReviewsModal() {
            pdpOpenReviewsModal();
        }

        function closeAllReviewsModal() {
            pdpCloseReviewsModal();
        }

        // ── Keyboard close for modals ─────────────────────────────
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                pdpCloseSizeModal();
                pdpCloseReviewsModal();
            }
        });

        // ── Flash Sale Timer ─────────────────────────────────────
        (function() {
            var timerEl = document.getElementById('flashTimer');
            var dataEl = document.getElementById('flashCountdown');
            if (!timerEl || !dataEl) return;
            var endsAt = new Date(dataEl.dataset.ends.replaceAll(' ', 'T'));

            function tick() {
                var diff = endsAt - Date.now();
                if (diff <= 0) {
                    timerEl.textContent = 'Sale Ended';
                    return;
                }
                var h = Math.floor(diff / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                var s = Math.floor((diff % 60000) / 1000);
                timerEl.textContent = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                setTimeout(tick, 1000);
            }
            tick();
        })();

        // ── Scroll hide/show bottom nav ──────────────────────────
        (function() {
            var lastY = window.scrollY;
            var nav = document.getElementById('bottomNav');
            if (!nav) return;
            window.addEventListener('scroll', function() {
                if (window.innerWidth > 900) return;
                if (window.scrollY > lastY && window.scrollY > 150) {
                    nav.style.transform = 'translateY(100%)';
                } else {
                    nav.style.transform = 'translateY(0)';
                }
                lastY = window.scrollY;
            }, {
                passive: true
            });
        })();

        // ── Scroll reveal ────────────────────────────────────────
        (function() {
            var els = document.querySelectorAll('.pdp-reveal');
            if (!('IntersectionObserver' in window)) {
                els.forEach(function(el) {
                    el.classList.add('visible');
                });
                return;
            }
            var obs = new IntersectionObserver(function(entries) {
                entries.forEach(function(e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('visible');
                        obs.unobserve(e.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            els.forEach(function(el) {
                obs.observe(el);
            });
        })();

        // ── Pincode check button ─────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            var checkBtn = document.querySelector('.check-pin-btn');
            var pinInput = document.getElementById('pincodeInput');
            var pinRes = document.getElementById('pincodeResult');
            if (checkBtn && pinInput && window.checkPincode) {
                checkBtn.addEventListener('click', function() {
                    window.checkPincode(pinInput.value, pinRes, pinInput);
                });
            }
        });

        // ── Q&A ──────────────────────────────────────────────────
        var QA_PRODUCT_ID = <?= (int)$product['id'] ?>;

        document.addEventListener('DOMContentLoaded', function() {
            pdpLoadQA();
            pdpLoadFBT();
        });

        function pdpLoadQA() {
            fetch('/api/qa/get.php?product_id=' + QA_PRODUCT_ID)
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var list = document.getElementById('qaList');
                    if (!list) return;
                    if (!data.qa || !data.qa.length) {
                        list.innerHTML = '<p class="pdp-qa-empty">No questions yet. Be the first to ask!</p>';
                        return;
                    }
                    list.innerHTML = data.qa.map(function(q) {
                        var d = q.created_at ? new Date(q.created_at.replaceAll(' ', 'T')).toLocaleDateString('en-IN', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        }) : '';
                        return '<div class="pdp-qa-item">' +
                            '<p class="pdp-qa-q">' + escHtml(q.question) + '</p>' +
                            (q.answer ?
                                '<p class="pdp-qa-a">' + escHtml(q.answer) + '</p>' :
                                '<p class="pdp-qa-pending">Awaiting answer from the brand</p>') +
                            '<div class="pdp-qa-date">' + d + '</div>' +
                            '</div>';
                    }).join('');
                })
                .catch(function() {
                    var list = document.getElementById('qaList');
                    if (list) list.innerHTML = '<p class="pdp-qa-empty">Could not load questions.</p>';
                });
        }

        function pdpSubmitQuestion(e) {
            e.preventDefault();
            var q = document.getElementById('qaQuestion').value.trim();
            if (q.length < 10) {
                alert('Please write at least 10 characters.');
                return;
            }
            var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            var btn = document.querySelector('.pdp-qa-submit');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Submitting…';
            }
            fetch('/api/qa/submit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        product_id: QA_PRODUCT_ID,
                        question: q,
                        csrf_token: csrf
                    })
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.ok) {
                        document.getElementById('qaQuestion').value = '';
                        document.getElementById('pdpCharCount').textContent = '0/500';
                        pdpLoadQA();
                        alert('Your question has been submitted!');
                    } else {
                        alert(data.error || 'Could not submit question.');
                    }
                })
                .catch(function() {
                    alert('Network error. Please try again.');
                })
                .finally(function() {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Ask Question';
                    }
                });
        }

        // Legacy
        function submitQuestion(e) {
            pdpSubmitQuestion(e);
        }

        function loadQA() {
            pdpLoadQA();
        }

        // ── FBT ──────────────────────────────────────────────────
        function pdpLoadFBT() {
            fetch('/api/recommendations.php?product_id=' + QA_PRODUCT_ID + '&type=fbt&limit=4')
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var sec = document.getElementById('fbtSection');
                    var grid = document.getElementById('fbtGrid');
                    if (!sec || !grid || !data.products || !data.products.length) return;
                    grid.innerHTML = data.products.map(function(p) {
                        var href = '/product.php?id=' + p.id + (p.slug ? '&slug=' + encodeURIComponent(p.slug) : '');
                        return '<a href="' + href + '" style="text-decoration:none;color:inherit;display:block;">' +
                            '<div style="aspect-ratio:3/4;overflow:hidden;background:var(--surface);border-radius:var(--radius-md);">' +
                            (p.image_url ? '<img src="' + escHtml(p.image_url) + '" alt="' + escHtml(p.name) + '" loading="lazy" style="width:100%;height:100%;object-fit:cover;">' : '') +
                            '</div>' +
                            '<p style="font-size:.78rem;font-weight:700;margin:8px 0 2px;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(p.name) + '</p>' +
                            '<p style="font-size:.78rem;color:var(--rose);font-weight:800;">₹' + Number(p.price).toLocaleString('en-IN') + '</p>' +
                            '</a>';
                    }).join('');
                    sec.style.display = '';
                })
                .catch(function() {});
        }

        function loadFBT() {
            pdpLoadFBT();
        }

        // ── HTML escape helper ────────────────────────────────────
        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ── changeImage legacy alias ──────────────────────────────
        function changeImage(index, el) {
            pdpChangeImage(index, el);
        }
    </script>

</body>

</html>