<?php
// ... rest of your original index.php code ...
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/includes/image_helper.php';

// Fetch featured / trending products
$trendingProducts = db()->query("SELECT * FROM products WHERE is_active = 1 AND is_featured = 1 ORDER BY created_at DESC LIMIT 8")->fetchAll();
// Fetch 2 featured products for Shop the Look section
$lookProducts = array_slice($trendingProducts, 0, 2);

// Load admin-controlled content settings
$promoText    = getSetting('promo_strip_text', 'FLAT ₹300 OFF on orders above ₹1499 · USE CODE');
$promoCode    = getSetting('promo_strip_code', 'SAVE300');
$cashbackText = getSetting('cashback_text', 'Flat 7.5% Cashback with HDFC &amp; SBI Credit Cards on orders above ₹999');

// Build carousel slides from admin-controlled banners (fall back to defaults)
$adminBanners = [];
for ($bi = 1; $bi <= 3; $bi++) {
  $img = getSetting("home_banner_{$bi}_img", '');
  if ($img !== '') {
    $adminBanners[] = [
      'img'      => $img,
      'title'    => getSetting("home_banner_{$bi}_title", ''),
      'subtitle' => getSetting("home_banner_{$bi}_subtitle", ''),
      'link'     => getSetting("home_banner_{$bi}_link", 'shop.php'),
    ];
  }
}
$defaultBanners = [
  [
    'img' => 'https://images.unsplash.com/photo-1537832816519-689ad163238b?q=80&w=800&auto=format&fit=crop',
    'title' => 'New Summer Collection 2026',
    'subtitle' => 'Starting ₹499',
    'link' => 'shop.php'
  ],
  [
    'img' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?q=80&w=800&auto=format&fit=crop',
    'title' => 'Flat 40% OFF on Party Wear',
    'subtitle' => 'Starting ₹799',
    'link' => 'shop.php?cat=party'
  ],
  [
    'img' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?q=80&w=800&auto=format&fit=crop',
    'title' => 'Complete Your Look',
    'subtitle' => 'Starting ₹299',
    'link' => 'shop.php?cat=accessories'
  ],
];
$carouselBanners = !empty($adminBanners) ? $adminBanners : $defaultBanners;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(SITE_NAME) ?> | <?= htmlspecialchars(SITE_TAGLINE) ?></title>

  <!-- Open Graph -->
  <meta property="og:title" content="<?= h(SITE_NAME) ?> | <?= h(SITE_TAGLINE) ?>">
  <meta property="og:description" content="Shop the latest ethnic wear, dresses, kurtis, and party wear at <?= h(SITE_NAME) ?>.">
  <meta property="og:image" content="<?= h(SITE_URL) ?>/assets/img/og-image.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:url" content="<?= h(SITE_URL) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= h(SITE_NAME) ?> | <?= h(SITE_TAGLINE) ?>">
  <meta name="twitter:description" content="Shop the latest ethnic wear, dresses, kurtis, and party wear at <?= h(SITE_NAME) ?>.">
  <meta name="twitter:image" content="<?= h(SITE_URL) ?>/assets/img/og-image.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: var(--font-sans);
      font-size: 14px;
      background: #f4f4f5;
      color: var(--mavdee-text);
      -webkit-font-smoothing: antialiased;
      padding-bottom: calc(var(--bottom-nav-height, 60px) + env(safe-area-inset-bottom));
    }

    @media (min-width: 768px) {
      body {
        padding-bottom: 0;
        background: #fff;
      }
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ── Top Offer Bar ────────────────────────────────────────────── */
    .top-offer-bar {
      background: #3d3d3d;
      color: #fff;
      font-size: 11px;
      padding: 7px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      position: relative;
    }

    .top-offer-bar .offer-code {
      display: inline-flex;
      align-items: center;
      border: 1px dashed var(--mavdee-pink);
      border-radius: 4px;
      padding: 1px 7px;
      font-size: 10px;
      font-weight: 700;
      color: var(--mavdee-pink);
      background: transparent;
    }

    .top-offer-bar .offer-close {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #fff;
      cursor: pointer;
      font-size: 14px;
      line-height: 1;
      padding: 2px 4px;
    }

    /* ── Category Navigation Strip ────────────────────────────────── */
    .cat-icons-row {
      display: flex;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -ms-overflow-style: none;
      padding: 14px 10px;
      gap: 10px;
      background: #fff;
      border-bottom: 1px solid var(--mavdee-border);
    }

    .cat-icons-row::-webkit-scrollbar {
      display: none;
    }

    .cat-icon-item {
      flex-shrink: 0;
      scroll-snap-align: start;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 7px;
      min-width: 72px;
      text-align: center;
      cursor: pointer;
      text-decoration: none;
      color: var(--mavdee-text);
      padding: 2px 4px 4px;
      transition: opacity 0.2s;
    }

    .cat-icon-item:hover {
      opacity: 0.82;
    }

    .cat-icon-circle {
      width: 72px;
      height: 88px;
      border-radius: 14px;
      background: #FFF8E7;
      overflow: hidden;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.07);
    }

    .cat-icon-circle img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: top center;
      display: block;
    }

    .cat-icon-item:hover .cat-icon-circle {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .cat-icon-label {
      font-size: 11px;
      font-weight: 600;
      color: var(--mavdee-text);
      line-height: 1.2;
      white-space: nowrap;
    }

    /* ── Promo Strip ──────────────────────────────────────────────── */
    .promo-strip {
      background: #fff3cd;
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 600;
      color: var(--mavdee-dark);
      border-bottom: 1px solid #fde9a6;
    }

    .promo-strip-code {
      display: inline-flex;
      align-items: center;
      background: #fff;
      border: 1px dashed var(--mavdee-pink);
      border-radius: 4px;
      padding: 2px 8px;
      font-size: 13px;
      font-weight: 700;
      color: var(--mavdee-pink);
      margin-left: 4px;
    }

    .promo-strip-arrow {
      margin-left: auto;
      color: var(--mavdee-pink);
      font-weight: 700;
    }

    /* ── Hero Carousel ────────────────────────────────────────────── */
    .hero-carousel {
      position: relative;
      overflow: hidden;
      background: #f8f0f5;
    }

    .carousel-track {
      display: flex;
      transition: transform 0.45s ease;
    }

    .carousel-slide {
      flex-shrink: 0;
      width: 100%;
      position: relative;
    }

    .carousel-slide img {
      width: 100%;
      aspect-ratio: 4/3;
      object-fit: cover;
      display: block;
    }

    .carousel-info {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.6) 0%, transparent 100%);
      padding: 40px 20px 16px;
      color: #fff;
    }

    .carousel-name {
      font-size: 1rem;
      font-weight: 700;
      margin: 2px 0 4px;
    }

    .carousel-price {
      font-size: 13px;
      opacity: 0.85;
      margin-bottom: 10px;
    }

    .carousel-cta {
      display: none;
      background: #fff;
      color: var(--mavdee-pink);
      font-size: 13px;
      font-weight: 700;
      padding: 8px 20px;
      border-radius: 4px;
      text-decoration: none;
    }

    .carousel-dots {
      position: absolute;
      bottom: 14px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 5px;
    }

    .carousel-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      transition: all 0.3s;
      cursor: pointer;
    }

    .carousel-dot.active {
      background: #fff;
      width: 18px;
      border-radius: 3px;
    }

    /* Arrow buttons (desktop only, hidden mobile) */
    .carousel-arrow {
      display: none;
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      border: none;
      cursor: pointer;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: var(--mavdee-dark);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
      z-index: 10;
      transition: background 0.2s;
    }

    .carousel-arrow:hover {
      background: #fff;
    }

    .carousel-arrow-prev {
      left: 16px;
    }

    .carousel-arrow-next {
      right: 16px;
    }

    /* ── Cashback Strip ───────────────────────────────────────────── */
    .cashback-strip {
      background: var(--mavdee-dark);
      color: #fff;
      font-size: 13px;
      font-weight: 500;
      padding: 9px 16px;
      text-align: center;
      letter-spacing: 0.02em;
    }

    /* ── Section Header ───────────────────────────────────────────── */
    .m-section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 12px 10px;
    }

    .m-section-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--mavdee-dark);
      margin: 0;
    }

    .m-section-link {
      font-size: 13px;
      font-weight: 700;
      color: var(--mavdee-pink);
      text-decoration: none;
    }

    /* ── Three-Column Promo Cards ─────────────────────────────────── */
    .promo-cards-wrap {
      background: #fff;
      padding: 20px 12px;
      border-bottom: 6px solid var(--mavdee-grey);
    }

    .promo-cards-grid {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .promo-card {
      border-radius: 10px;
      padding: 20px;
      color: #fff;
      height: 140px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
      overflow: hidden;
      text-decoration: none;
      transition: transform 0.25s, box-shadow 0.25s;
    }

    .promo-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    }

    .promo-card-emoji {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 4rem;
      opacity: 0.18;
      pointer-events: none;
    }

    .promo-card-eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.15em;
      opacity: 0.85;
      margin-bottom: 4px;
    }

    .promo-card-title {
      font-size: 1.2rem;
      font-weight: 800;
      margin: 0 0 3px;
    }

    .promo-card-sub {
      font-size: 12px;
      opacity: 0.85;
      margin-bottom: 10px;
    }

    .promo-card-cta {
      display: inline-block;
      background: rgba(255, 255, 255, 0.22);
      border: 1px solid rgba(255, 255, 255, 0.5);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 5px 14px;
      border-radius: 4px;
      width: fit-content;
    }

    /* ── Product Grid ─────────────────────────────────────────────── */
    .prod-grid-section {
      background: #fff;
      border-bottom: 6px solid var(--mavdee-grey);
      padding-bottom: 16px;
    }

    .prod-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
      padding: 0 8px;
    }

    .prod-card {
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
      text-decoration: none;
      color: inherit;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    }

    .prod-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.13);
    }

    .prod-card-img-wrap {
      position: relative;
    }

    .prod-card-img {
      width: 100%;
      aspect-ratio: 3/4;
      object-fit: cover;
      object-position: top center;
      display: block;
      background: var(--mavdee-grey);
    }

    .prod-card-badge {
      position: absolute;
      top: 8px;
      left: 8px;
      background: var(--mavdee-pink);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 3px;
    }

    .prod-card-badge.new-badge {
      background: #03a685;
    }

    .prod-card-wishlist {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.92);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: var(--mavdee-muted);
      transition: color 0.2s;
    }

    .prod-card-wishlist:hover {
      color: var(--mavdee-pink);
    }

    .prod-card-info {
      padding: 8px 8px 10px;
    }

    .prod-card-brand {
      font-size: 13px;
      font-weight: 700;
      color: var(--mavdee-dark);
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .prod-card-name {
      font-size: 12px;
      color: var(--mavdee-muted);
      margin-bottom: 5px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .prod-card-price-row {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-wrap: wrap;
      margin-bottom: 4px;
    }

    .prod-card-price {
      font-size: 15px;
      font-weight: 700;
      color: var(--mavdee-dark);
    }

    .prod-card-mrp {
      font-size: 13px;
      color: var(--mavdee-muted);
      text-decoration: line-through;
    }

    .prod-card-disc {
      font-size: 12px;
      font-weight: 700;
      color: var(--mavdee-rating);
    }

    .prod-card-rating {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      background: #14958f;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 3px;
    }

    /* ── Shop The Look ────────────────────────────────────────────── */
    .look-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      padding: 0 8px;
    }

    .look-card {
      border-radius: 8px;
      overflow: hidden;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
      transition: transform 0.2s, box-shadow 0.2s;
      background: #fff;
    }

    .look-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.13);
    }

    .look-card img {
      width: 100%;
      aspect-ratio: 2/3;
      object-fit: cover;
      object-position: top center;
      display: block;
    }

    .look-card-info {
      padding: 8px;
    }

    .look-card-name {
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 3px;
    }

    .look-card-price {
      font-size: 12px;
      color: var(--mavdee-pink);
      font-weight: 700;
    }

    /* ── Insider Banner ───────────────────────────────────────────── */
    .insider-banner {
      background: linear-gradient(90deg, #7b2ff7 0%, #ff3f6c 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 16px;
      margin: 0;
    }

    .insider-eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.15em;
      opacity: 0.85;
      margin-bottom: 4px;
    }

    .insider-title {
      font-size: 1.1rem;
      font-weight: 800;
      margin: 0 0 3px;
    }

    .insider-sub {
      font-size: 12px;
      opacity: 0.85;
    }

    .insider-btn {
      background: #fff;
      color: var(--mavdee-pink);
      font-size: 13px;
      font-weight: 700;
      padding: 10px 20px;
      border-radius: 4px;
      text-decoration: none;
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* ── Desktop Overrides ────────────────────────────────────────── */
    @media (min-width: 768px) {

      /* Category icons */
      .cat-icons-row {
        justify-content: center;
        overflow-x: hidden;
        padding: 16px 24px;
        gap: 14px;
        flex-wrap: wrap;
      }

      .cat-icon-item {
        min-width: 82px;
      }

      .cat-icon-circle {
        width: 84px;
        height: 104px;
        border-radius: 16px;
      }

      .cat-icon-label {
        font-size: 12px;
      }

      /* Promo strip */
      .promo-strip {
        max-width: 1400px;
        margin: 0 auto;
        padding: 12px 24px;
        font-size: 14px;
      }

      .promo-strip {
        max-width: none;
      }

      /* full-width background, inner auto */

      /* Carousel */
      .carousel-slide img {
        aspect-ratio: 16/7;
        max-height: 520px;
      }

      .carousel-cta {
        display: inline-block;
      }

      .carousel-arrow {
        display: flex;
      }

      .carousel-dots {
        bottom: 18px;
      }

      /* Section headers */
      .m-section-header {
        padding: 20px 24px 12px;
      }

      .m-section-title {
        font-size: 1.15rem;
      }

      /* Promo cards */
      .promo-cards-wrap {
        padding: 24px;
        max-width: 1400px;
        margin: 0 auto;
        border-bottom: none;
      }

      .promo-cards-grid {
        flex-direction: row;
        gap: 16px;
      }

      .promo-card {
        flex: 1;
        height: 180px;
      }

      /* Product grid */
      .prod-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        padding: 0 24px;
      }

      .prod-card-brand {
        font-size: 13px;
      }

      .prod-card-name {
        font-size: 12px;
      }

      /* Look grid */
      .look-grid {
        padding: 0 24px;
        gap: 16px;
      }

      /* Insider banner */
      .insider-banner {
        padding: 0 24px;
        height: 120px;
        max-width: 1400px;
        margin: 0 auto;
      }

      .insider-title {
        font-size: 1.4rem;
      }
    }

    @media (min-width: 1024px) {

      /* Desktop carousel aspect ratio */
      .carousel-slide img {
        aspect-ratio: 21/8;
        max-height: 520px;
      }
    }

    @media (min-width: 1100px) {

      /* 5-col product grid */
      .prod-grid {
        grid-template-columns: repeat(5, 1fr);
      }

      /* centre constrained sections */
      .promo-cards-section,
      .promo-cards-wrap {
        max-width: 1400px;
        margin: 0 auto;
      }

      .prod-grid-section,
      .insider-wrap {
        max-width: 1400px;
        margin: 0 auto;
      }
    }

    @media (min-width: 1280px) {
      .carousel-arrow {
        display: flex;
      }
    }

    /* ── Mobile-only fallback: hide desktop offer bar ─────────────── */
    @media (max-width: 767px) {
      .top-offer-bar {
        font-size: 10px;
        padding: 6px 12px;
      }

      .promo-cards-grid {
        flex-direction: column;
      }

      .insider-banner {
        flex-direction: column;
        gap: 12px;
        text-align: center;
        padding: 20px 16px;
      }
    }

    /* ── Order success ────────────────────────────────────────────── */
    .order-success-bar {
      background: #e8f5e9;
      padding: 20px 16px;
      text-align: center;
      border-bottom: 1px solid #c8e6c9;
    }

    .order-success-icon {
      width: 48px;
      height: 48px;
      background: var(--mavdee-green);
      color: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin: 0 auto 10px;
    }
  </style>
</head>

<body>

  <!-- Top Offer Bar -->
  <div class="top-offer-bar" id="topOfferBar">
    <span><?= htmlspecialchars($promoText) ?></span>
    <?php if ($promoCode !== ''): ?>
      <span class="offer-code"><?= htmlspecialchars($promoCode) ?></span>
    <?php endif; ?>
    <button class="offer-close" onclick="document.getElementById('topOfferBar').style.display='none'" aria-label="Close">&times;</button>
  </div>

  <?php
  $headerHideSearch       = false;
  $headerHideCartWishlist = false;
  require __DIR__ . '/includes/header.php';
  ?>

  <main id="main-content">

    <?php if (isset($_GET['order_success'])): ?>
      <div class="order-success-bar">
        <div class="order-success-icon">&#10003;</div>
        <h2 style="margin:0 0 6px;font-size:1.1rem;color:#1b5e20;">Order Placed!</h2>
        <p style="margin:0;color:#2e7d32;font-size:14px;">Order <strong><?= htmlspecialchars($_GET['order_success']) ?></strong> confirmed. Email on its way.</p>
        <a href="shop.php" style="display:inline-block;margin-top:12px;background:var(--mavdee-pink);color:#fff;padding:9px 20px;font-size:13px;font-weight:700;border-radius:4px;">Continue Shopping</a>
      </div>
    <?php endif; ?>

    <!-- Category Navigation Strip -->
    <div class="cat-icons-row" role="navigation" aria-label="Category shortcuts">
      <a href="shop.php?cat=kurta-sets" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=160&h=200&fit=crop&auto=format&q=75" alt="Kurta Sets" loading="lazy">
        </span>
        <span class="cat-icon-label">Kurta Sets</span>
      </a>
      <a href="shop.php?cat=dresses" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=160&h=200&fit=crop&auto=format&q=75" alt="Dresses" loading="lazy">
        </span>
        <span class="cat-icon-label">Dresses</span>
      </a>
      <a href="shop.php?cat=sarees" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1537832816519-689ad163238b?w=160&h=200&fit=crop&auto=format&q=75" alt="Sarees" loading="lazy">
        </span>
        <span class="cat-icon-label">Sarees</span>
      </a>
      <a href="shop.php?cat=jeans" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=160&h=200&fit=crop&auto=format&q=75" alt="Jeans" loading="lazy">
        </span>
        <span class="cat-icon-label">Jeans</span>
      </a>
      <a href="shop.php?cat=kurtis" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=160&h=200&fit=crop&auto=format&q=75" alt="Kurtis" loading="lazy">
        </span>
        <span class="cat-icon-label">Kurtis</span>
      </a>
      <a href="shop.php?cat=tops" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=160&h=200&fit=crop&auto=format&q=75" alt="Tops" loading="lazy">
        </span>
        <span class="cat-icon-label">Tops</span>
      </a>
      <a href="shop.php?cat=lehenga" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=160&h=200&fit=crop&auto=format&q=75" alt="Lehenga" loading="lazy">
        </span>
        <span class="cat-icon-label">Lehenga</span>
      </a>
      <a href="shop.php?cat=palazzo" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1539109136881-3be0616acf4b?w=160&h=200&fit=crop&auto=format&q=75" alt="Palazzo" loading="lazy">
        </span>
        <span class="cat-icon-label">Palazzo</span>
      </a>
      <a href="shop.php?cat=dupatta" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1567401893414-76b7b1e5a7a5?w=160&h=200&fit=crop&auto=format&q=75" alt="Dupatta" loading="lazy">
        </span>
        <span class="cat-icon-label">Dupatta</span>
      </a>
      <a href="shop.php?cat=co-ords" class="cat-icon-item">
        <span class="cat-icon-circle">
          <img src="https://images.unsplash.com/photo-1483985988355-763728e1935b?w=160&h=200&fit=crop&auto=format&q=75" alt="Co-ords" loading="lazy">
        </span>
        <span class="cat-icon-label">Co-ords</span>
      </a>
    </div>

    <!-- Promo Strip -->
    <div class="promo-strip">
      🎉 <strong><?= htmlspecialchars($promoText) ?></strong>
      <?php if ($promoCode !== ''): ?>
        <span class="promo-strip-code"><?= htmlspecialchars($promoCode) ?></span>
      <?php endif; ?>
      <span class="promo-strip-arrow">›</span>
    </div>

    <!-- Hero Carousel -->
    <div class="hero-carousel" id="heroCarousel">
      <button class="carousel-arrow carousel-arrow-prev" id="carouselPrev" aria-label="Previous slide">&#8249;</button>
      <div class="carousel-track" id="carouselTrack">
        <?php foreach ($carouselBanners as $slide): ?>
          <div class="carousel-slide">
            <?php $slideLink = htmlspecialchars($slide['link'] ?? 'shop.php', ENT_QUOTES, 'UTF-8'); ?>
            <a href="<?= $slideLink ?>">
              <img src="<?= htmlspecialchars($slide['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($slide['title']) ?>" loading="eager">
            </a>
            <div class="carousel-info">
              <div class="carousel-name"><?= htmlspecialchars($slide['title']) ?></div>
              <div class="carousel-price"><?= htmlspecialchars($slide['subtitle']) ?></div>
              <a href="<?= $slideLink ?>" class="carousel-cta">Shop Now</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-arrow carousel-arrow-next" id="carouselNext" aria-label="Next slide">&#8250;</button>
      <div class="carousel-dots" id="carouselDots">
        <?php foreach ($carouselBanners as $i => $slide): ?>
          <span class="carousel-dot<?= $i === 0 ? ' active' : '' ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Cashback Strip -->
    <div class="cashback-strip">
      <?= htmlspecialchars($cashbackText) ?>
    </div>

    <!-- Three-Column Promo Cards -->
    <div class="promo-cards-section">
      <div class="promo-cards-wrap">
        <div class="promo-cards-grid">
          <a href="shop.php" class="promo-card" style="background: linear-gradient(135deg,#ff3f6c,#ff8080);">
            <span class="promo-card-emoji">🛍️</span>
            <div class="promo-card-eyebrow">MEGA SALE</div>
            <div class="promo-card-title">EORS 2026</div>
            <div class="promo-card-sub">5000+ Brands · Up to 80% OFF</div>
            <span class="promo-card-cta">Shop Sale</span>
          </a>
          <a href="shop.php" class="promo-card" style="background: linear-gradient(135deg,#7b2ff7,#4fa3e0);">
            <span class="promo-card-emoji">✨</span>
            <div class="promo-card-eyebrow">TOP PICKS</div>
            <div class="promo-card-title">New Arrivals</div>
            <div class="promo-card-sub">2000+ New Styles Daily</div>
            <span class="promo-card-cta">Explore</span>
          </a>
          <a href="shop.php" class="promo-card" style="background: linear-gradient(135deg,#1a1a2e,#0f3460);">
            <span class="promo-card-emoji">💎</span>
            <div class="promo-card-eyebrow">PREMIUM</div>
            <div class="promo-card-title">Luxe Picks</div>
            <div class="promo-card-sub">International Luxury Brands</div>
            <span class="promo-card-cta">View Luxe</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Trending Products Grid -->
    <?php if (!empty($trendingProducts)): ?>
      <div class="prod-grid-section">
        <div class="m-section-header">
          <h2 class="m-section-title">Trending Now</h2>
          <a href="shop.php" class="m-section-link">See All →</a>
        </div>
        <div class="prod-grid">
          <?php foreach ($trendingProducts as $p):
            $hasDiscount = !empty($p['compare_price']) && (float)$p['compare_price'] > (float)$p['price'];
            $discPct = $hasDiscount ? round((1 - $p['price'] / $p['compare_price']) * 100) : 0;
            $slug = htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8');
          ?>
            <a href="product.php?id=<?= (int)$p['id'] ?>&slug=<?= $slug ?>" class="prod-card">
              <div class="prod-card-img-wrap">
                <?php $__imgUrl = img_url($p['image_url']);
                $__webp = get_webp_url($__imgUrl); ?>
                <picture>
                  <?php if ($__webp !== $__imgUrl): ?>
                    <source srcset="<?= h($__webp) ?>" type="image/webp"><?php endif; ?>
                  <img class="prod-card-img" src="<?= h($__imgUrl) ?>" alt="<?= h($p['name']) ?>" loading="lazy" onerror="this.src='/assets/img/placeholder.svg'">
                </picture>
                <?php if ($hasDiscount): ?>
                  <span class="prod-card-badge"><?= $discPct ?>% OFF</span>
                <?php else: ?>
                  <span class="prod-card-badge new-badge">NEW</span>
                <?php endif; ?>
                <button class="prod-card-wishlist" onclick="event.preventDefault();toggleWishlist(<?= (int)$p['id'] ?>, this)" aria-label="Add to wishlist">
                  <i class="fa-regular fa-heart"></i>
                </button>
              </div>
              <div class="prod-card-info">
                <div class="prod-card-brand"><?= h($p['brand'] ?? SITE_NAME) ?></div>
                <div class="prod-card-name"><?= h($p['name']) ?></div>
                <div class="prod-card-price-row">
                  <span class="prod-card-price"><?= CURRENCY ?><?= number_format($p['price'], 0) ?></span>
                  <?php if ($hasDiscount): ?>
                    <span class="prod-card-mrp"><?= CURRENCY ?><?= number_format($p['compare_price'], 0) ?></span>
                    <span class="prod-card-disc">(<?= $discPct ?>% OFF)</span>
                  <?php endif; ?>
                </div>
                <span class="prod-card-rating">4.2 ★</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- New Arrivals Grid -->
    <?php if (!empty($trendingProducts)): ?>
      <div class="prod-grid-section">
        <div class="m-section-header">
          <h2 class="m-section-title">New Arrivals</h2>
          <a href="shop.php" class="m-section-link">See All →</a>
        </div>
        <div class="prod-grid">
          <?php foreach (array_reverse($trendingProducts) as $p):
            $hasDiscount = !empty($p['compare_price']) && (float)$p['compare_price'] > (float)$p['price'];
            $discPct = $hasDiscount ? round((1 - $p['price'] / $p['compare_price']) * 100) : 0;
            $slug = htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8');
          ?>
            <a href="product.php?id=<?= (int)$p['id'] ?>&slug=<?= $slug ?>" class="prod-card">
              <div class="prod-card-img-wrap">
                <?php $__imgUrl = img_url($p['image_url']);
                $__webp = get_webp_url($__imgUrl); ?>
                <picture>
                  <?php if ($__webp !== $__imgUrl): ?>
                    <source srcset="<?= h($__webp) ?>" type="image/webp"><?php endif; ?>
                  <img class="prod-card-img" src="<?= h($__imgUrl) ?>" alt="<?= h($p['name']) ?>" loading="lazy" onerror="this.src='/assets/img/placeholder.svg'">
                </picture>
                <?php if ($hasDiscount): ?>
                  <span class="prod-card-badge"><?= $discPct ?>% OFF</span>
                <?php else: ?>
                  <span class="prod-card-badge new-badge">NEW</span>
                <?php endif; ?>
                <button class="prod-card-wishlist" onclick="event.preventDefault();toggleWishlist(<?= (int)$p['id'] ?>, this)" aria-label="Add to wishlist">
                  <i class="fa-regular fa-heart"></i>
                </button>
              </div>
              <div class="prod-card-info">
                <div class="prod-card-brand"><?= h($p['brand'] ?? SITE_NAME) ?></div>
                <div class="prod-card-name"><?= h($p['name']) ?></div>
                <div class="prod-card-price-row">
                  <span class="prod-card-price"><?= CURRENCY ?><?= number_format($p['price'], 0) ?></span>
                  <?php if ($hasDiscount): ?>
                    <span class="prod-card-mrp"><?= CURRENCY ?><?= number_format($p['compare_price'], 0) ?></span>
                    <span class="prod-card-disc">(<?= $discPct ?>% OFF)</span>
                  <?php endif; ?>
                </div>
                <span class="prod-card-rating">4.2 ★</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Shop The Look -->
    <?php if (!empty($lookProducts)): ?>
      <div class="prod-grid-section">
        <div class="m-section-header">
          <h2 class="m-section-title">Shop The Look</h2>
          <a href="shop.php" class="m-section-link">See All →</a>
        </div>
        <div class="look-grid">
          <?php foreach ($lookProducts as $lp):
            $slug = htmlspecialchars($lp['slug'] ?? '', ENT_QUOTES, 'UTF-8');
          ?>
            <a href="product.php?id=<?= (int)$lp['id'] ?>&slug=<?= $slug ?>" class="look-card">
              <?php $__lpImg = img_url($lp['image_url']);
              $__lpWebp = get_webp_url($__lpImg); ?>
              <picture>
                <?php if ($__lpWebp !== $__lpImg): ?>
                  <source srcset="<?= h($__lpWebp) ?>" type="image/webp"><?php endif; ?>
                <img src="<?= h($__lpImg) ?>" alt="<?= h($lp['name']) ?>" loading="lazy" onerror="this.src='/assets/img/placeholder.svg'">
              </picture>
              <div class="look-card-info">
                <div class="look-card-name"><?= h($lp['name']) ?></div>
                <div class="look-card-price"><?= CURRENCY ?><?= number_format($lp['price'], 0) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Mavdee Insider Banner -->
    <div class="insider-wrap" style="background:#f4f4f5;">
      <div class="insider-banner">
        <div>
          <div class="insider-eyebrow">MAVDEE INSIDER</div>
          <div class="insider-title">Earn. Redeem. Repeat.</div>
          <div class="insider-sub">Join India's biggest fashion loyalty programme</div>
        </div>
        <a href="register.php" class="insider-btn">JOIN NOW →</a>
      </div>
    </div>

  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>
  <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

  <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    // Auto-sliding carousel with prev/next arrow support
    (function() {
      var track = document.getElementById('carouselTrack');
      var dots = document.querySelectorAll('#carouselDots .carousel-dot');
      var prevBtn = document.getElementById('carouselPrev');
      var nextBtn = document.getElementById('carouselNext');
      if (!track) return;
      var total = track.children.length;
      var current = 0;

      function goTo(idx) {
        current = (idx + total) % total;
        track.style.transform = 'translateX(-' + (current * 100) + '%)';
        dots.forEach(function(d, i) {
          d.classList.toggle('active', i === current);
        });
      }

      var timer = setInterval(function() {
        goTo(current + 1);
      }, 3500);

      if (prevBtn) prevBtn.addEventListener('click', function() {
        clearInterval(timer);
        goTo(current - 1);
        timer = setInterval(function() {
          goTo(current + 1);
        }, 3500);
      });
      if (nextBtn) nextBtn.addEventListener('click', function() {
        clearInterval(timer);
        goTo(current + 1);
        timer = setInterval(function() {
          goTo(current + 1);
        }, 3500);
      });

      // Touch swipe
      var startX = 0;
      track.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        clearInterval(timer);
      }, {
        passive: true
      });
      track.addEventListener('touchend', function(e) {
        var diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) goTo(current + (diff > 0 ? 1 : -1));
        timer = setInterval(function() {
          goTo(current + 1);
        }, 3500);
      });

      dots.forEach(function(dot, i) {
        dot.addEventListener('click', function() {
          clearInterval(timer);
          goTo(i);
          timer = setInterval(function() {
            goTo(current + 1);
          }, 3500);
        });
      });
    })();

    function addToWishlist(id) {
      var safeId = parseInt(id, 10);
      if (!safeId || safeId < 1) return;
      if (typeof toggleWishlist === 'function') {
        toggleWishlist(safeId, window.event ? window.event.currentTarget : null, window.event);
      } else {
        window.location.href = 'wishlist.php?add=' + safeId;
      }
    }
  </script>
</body>

</html>