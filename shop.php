<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/includes/image_helper.php';

// 1. Build filtering logic dynamically based on URL parameters
$where = ["is_active = 1"];
$params = [];

// Advanced Search Query (Multi-word support)
$searchQuery = trim($_GET['q'] ?? '');
if ($searchQuery !== '') {
  $words = array_filter(explode(' ', preg_replace('/\s+/', ' ', $searchQuery)));
  foreach ($words as $word) {
    $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $word) . '%';
    $where[] = "(name LIKE ? OR description LIKE ? OR category_id IN (SELECT id FROM categories WHERE name LIKE ?))";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
  }
}

// Category Filter
$catParam = $_GET['cat'] ?? [];
$selectedCats = is_array($catParam) ? $catParam : ($catParam !== '' ? [$catParam] : []);
if (!empty($selectedCats)) {
  $catPlaceholders = implode(',', array_fill(0, count($selectedCats), '?'));
  $where[] = "category_id IN (SELECT id FROM categories WHERE name IN ($catPlaceholders) OR slug IN ($catPlaceholders))";
  foreach ($selectedCats as $c) {
    $params[] = $c;
    $params[] = $c;
  }
}

// Price Range Filter
$maxPrice = (int)($_GET['max'] ?? 10000);
if ($maxPrice < 10000 && $maxPrice > 0) {
  $where[] = "price <= ?";
  $params[] = $maxPrice;
}

// Size Filter
$selectedSizes = $_GET['size'] ?? [];
$selectedSizes = is_array($selectedSizes) ? $selectedSizes : ($selectedSizes !== '' ? [$selectedSizes] : []);
if (!empty($selectedSizes)) {
  $sizeConditions = [];
  foreach ($selectedSizes as $s) {
    $sizeConditions[] = "sizes LIKE ?";
    $params[] = '%' . $s . '%';
  }
  $where[] = "(" . implode(' OR ', $sizeConditions) . ")";
}

// Color Filter
$selectedColors = $_GET['color'] ?? [];
$selectedColors = is_array($selectedColors) ? $selectedColors : ($selectedColors !== '' ? [$selectedColors] : []);
if (!empty($selectedColors)) {
  $colorConditions = [];
  foreach ($selectedColors as $c) {
    $colorConditions[] = "colors LIKE ?";
    $params[] = '%' . $c . '%';
  }
  $where[] = "(" . implode(' OR ', $colorConditions) . ")";
}

$whereStr = implode(" AND ", $where);
$sortParam = $_GET['sort'] ?? 'new';
$orderBy = match ($sortParam) {
  'price_asc'  => 'price ASC',
  'price_desc' => 'price DESC',
  'name'       => 'name ASC',
  default      => 'created_at DESC',
};
$stmt = db()->prepare("SELECT * FROM products WHERE $whereStr ORDER BY $orderBy");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch user's wishlist for initial button state
$userWishlist = [];
if (isLoggedIn()) {
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

// Build safe sort base URL for desktop sort dropdown
$sortBaseParams = [];
if ($searchQuery !== '') {
  $sortBaseParams[] = 'q=' . rawurlencode($searchQuery);
}
foreach ($selectedCats as $c) {
  $sortBaseParams[] = 'cat[]=' . rawurlencode($c);
}
if ($maxPrice < 10000) {
  $sortBaseParams[] = 'max=' . $maxPrice;
}
foreach ($selectedSizes as $s) {
  $sortBaseParams[] = 'size[]=' . rawurlencode($s);
}
foreach ($selectedColors as $cl) {
  $sortBaseParams[] = 'color[]=' . rawurlencode($cl);
}
$sortBaseQuery = implode('&', $sortBaseParams);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Shop Collection - <?= htmlspecialchars(SITE_NAME) ?></title>

  <!-- Open Graph -->
  <meta property="og:title" content="Shop Collection - <?= h(SITE_NAME) ?>">
  <meta property="og:description" content="Browse our curated collection of ethnic wear, party dresses, kurtis and more at <?= h(SITE_NAME) ?>.">
  <meta property="og:image" content="<?= h(SITE_URL) ?>/assets/img/og-image.png">
  <meta property="og:url" content="<?= h(SITE_URL) ?>/shop.php">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Shop Collection - <?= h(SITE_NAME) ?>">
  <meta name="twitter:image" content="<?= h(SITE_URL) ?>/assets/img/og-image.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    /* ── Mavdee Shop Page Styles ─────────────────────────────────── */
    :root {
      /* ── Core design tokens ── */
      --mavdee-pink:       #ff3f6c;
      --mavdee-pink-light: #fff0f3;
      --mavdee-green:      #03a685;
      --mavdee-dark:       #1c1c1c;
      --mavdee-grey:       #f4f4f5;
      --mavdee-border:     #e5e7eb;
      --mavdee-muted:      #6b7280;
      --mavdee-text:       #111827;
      --mavdee-express:    #f57f17;
      --font-sans:         'DM Sans', sans-serif;

      /* ── Aliases: map wrong-case refs to correct tokens ──
         CSS variables are case-sensitive; every var(--Mavdee-*)
         throughout this file was resolving to undefined.          */
      --Mavdee-pink:       var(--mavdee-pink);
      --Mavdee-pink-light: var(--mavdee-pink-light);
      --Mavdee-green:      var(--mavdee-green);
      --Mavdee-dark:       var(--mavdee-dark);
      --Mavdee-grey:       var(--mavdee-grey);
      --Mavdee-border:     var(--mavdee-border);
      --Mavdee-muted:      var(--mavdee-muted);
      --Mavdee-text:       var(--mavdee-text);
      --Mavdee-express:    var(--mavdee-express);
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
      background: #fff;
      color: var(--mavdee-text);
      -webkit-font-smoothing: antialiased;
      overflow-x: clip;
      padding-bottom: calc(var(--bottom-nav-height, 60px) + env(safe-area-inset-bottom));
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ── Offer Banner Chips ────────────────────────────────────── */
    .offer-banners {
      display: flex;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -ms-overflow-style: none;
      padding: 10px 8px;
      gap: 8px;
      background: #fff;
      border-bottom: 1px solid var(--Mavdee-border);
    }

    .offer-banners::-webkit-scrollbar {
      display: none;
    }

    .offer-chip {
      flex-shrink: 0;
      scroll-snap-align: start;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      cursor: pointer;
      min-width: 72px;
      text-align: center;
      text-decoration: none;
      line-height: 1.2;
    }

    .offer-chip .chip-icon {
      font-size: 1.2rem;
    }

    .offer-chip-foryou {
      background: #fff3e0;
      color: #e65100;
    }

    .offer-chip-minoff {
      background: #e8f5e9;
      color: #1b5e20;
    }

    .offer-chip-new {
      background: #e3f2fd;
      color: #0d47a1;
    }

    .offer-chip-deal {
      background: #fce4ec;
      color: #880e4f;
    }

    .offer-chip-express {
      background: #fff8e1;
      color: #f57f17;
    }

    /* ── Filter Chips ──────────────────────────────────────────── */
    .filter-chips-wrap {
      display: flex;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scrollbar-width: none;
      -ms-overflow-style: none;
      padding: 8px 8px;
      gap: 6px;
      background: #fff;
      border-bottom: 2px solid var(--Mavdee-border);
    }

    .filter-chips-wrap::-webkit-scrollbar {
      display: none;
    }

    .filter-chip {
      flex-shrink: 0;
      scroll-snap-align: start;
      display: inline-flex;
      align-items: center;
      gap: 3px;
      padding: 6px 12px;
      border: 1px solid var(--Mavdee-border);
      border-radius: 99px;
      font-size: 12px;
      font-weight: 600;
      color: var(--Mavdee-text);
      background: #fff;
      cursor: pointer;
      white-space: nowrap;
      text-decoration: none;
      transition: border-color 0.2s, background 0.2s;
    }

    .filter-chip.active {
      border-color: var(--Mavdee-pink);
      color: var(--Mavdee-pink);
      background: var(--Mavdee-pink-light, #fff0f3);
    }

    .filter-chip-chevron {
      font-size: 11px;
      margin-left: 2px;
    }

    /* Compact filter form hidden by default (submit on change) */
    .filter-form-wrap {
      display: none;
    }

    /* ── Product Grid ──────────────────────────────────────────── */
    .prod-grid-wrap {
      padding: 8px 6px;
      min-height: 60vh;
    }

    .prod-grid-count {
      font-size: 12px;
      color: var(--Mavdee-muted);
      padding: 6px 6px 8px;
      font-weight: 500;
    }

    .prod-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 6px;
    }

    .prod-card {
      background: #fff;
      border: 1px solid var(--mavdee-border);
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
      position: relative;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06);
      transition: box-shadow 0.2s;
    }

    .prod-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.10);
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
      display: block;
    }

    /* Wishlist btn on image */
    .wishlist-btn {
      position: absolute;
      top: 8px;
      right: 8px;
      background: rgba(255, 255, 255, 0.92);
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 2;
      color: var(--Mavdee-muted);
      transition: color 0.2s;
    }

    .wishlist-btn:hover,
    .wishlist-btn.wishlisted {
      color: var(--Mavdee-pink);
    }

    /* Rating overlay */
    .prod-rating-overlay {
      position: absolute;
      bottom: 36px;
      left: 0;
      right: 0;
      padding: 3px 8px;
      background: rgba(0, 0, 0, 0.45);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: #fff;
      font-weight: 600;
      z-index: 3;
    }

    .prod-rating-star {
      color: #ffd700;
      margin-right: 2px;
    }

    /* Add to cart btn */
    .prod-add-btn {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--Mavdee-pink);
      color: #fff;
      border: none;
      padding: 7px 0;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      letter-spacing: 0.04em;
      z-index: 2;
    }

    /* Product info below image */
    .prod-info {
      padding: 7px 6px 8px;
      flex: 1;
    }

    .prod-brand {
      font-size: 13px;
      font-weight: 700;
      color: var(--Mavdee-dark);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .prod-title {
      font-size: 12px;
      color: var(--Mavdee-muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin: 1px 0 5px;
    }

    .prod-price-row {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-wrap: wrap;
      margin-bottom: 3px;
    }

    .prod-price {
      font-size: 15px;
      font-weight: 700;
      color: var(--Mavdee-dark);
    }

    .prod-original {
      font-size: 13px;
      color: var(--Mavdee-muted);
      text-decoration: line-through;
    }

    .prod-off {
      font-size: 12px;
      color: var(--Mavdee-green);
      font-weight: 700;
    }

    .prod-express {
      display: inline-flex;
      align-items: center;
      gap: 3px;
      font-size: 11px;
      font-weight: 700;
      color: var(--Mavdee-express);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .prod-delivery {
      font-size: 11px;
      color: var(--Mavdee-muted);
      font-weight: 500;
    }

    /* ── Empty state ───────────────────────────────────────────── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      grid-column: 1/-1;
    }

    .empty-state h2 {
      font-size: 1.2rem;
      color: var(--Mavdee-dark);
      margin-bottom: 8px;
    }

    .empty-state p {
      color: var(--Mavdee-muted);
      font-size: 14px;
    }

    .btn-clear {
      display: inline-block;
      margin-top: 16px;
      background: var(--Mavdee-pink);
      color: #fff;
      padding: 10px 24px;
      border-radius: 4px;
      font-weight: 700;
      font-size: 13px;
    }

    /* ── Desktop overrides ─────────────────────────────────────── */
    @media (min-width: 768px) {
      body {
        padding-bottom: 0;
      }

      .prod-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
      }

      .prod-grid-wrap {
        padding: 16px 20px;
      }

      /* Offer banners: no scroll on desktop */
      .offer-banners {
        overflow-x: visible;
        flex-wrap: wrap;
        padding: 12px 24px;
        gap: 10px;
      }

      /* Filter chips: no scroll on desktop */
      .filter-chips-wrap {
        overflow-x: visible;
        flex-wrap: wrap;
        padding: 10px 24px;
        gap: 8px;
      }
    }

    @media (min-width: 1024px) {
      .prod-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    /* Hide desktop sidebar on mobile */
    .filter-sidebar {
      display: none;
    }

    /* ── Desktop 2-column shop layout (sidebar + products) ──────── */
    @media (min-width: 900px) {
      .shop-layout {
        display: grid;
        grid-template-columns: 240px 1fr;
        align-items: start;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 24px;
        gap: 0 24px;
      }

      /* Hide mobile filter chips & offer banners from inside layout */
      .shop-layout .filter-chips-wrap,
      .shop-layout .offer-banners {
        display: none;
      }

      /* Desktop filter sidebar */
      .filter-sidebar {
        display: block;
        position: sticky;
        top: calc(var(--desktop-header-total, 114px) + 10px);
        background: #fff;
        border: 1px solid var(--Mavdee-border);
        border-radius: 8px;
        padding: 0;
        overflow: hidden;
        align-self: start;
      }

      .filter-sidebar-title {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: var(--Mavdee-dark);
        padding: 14px 16px;
        border-bottom: 1px solid var(--Mavdee-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
      }

      .filter-sidebar-title a {
        font-size: 12px;
        color: var(--Mavdee-pink);
        text-decoration: none;
        font-weight: 600;
      }

      .filter-section {
        border-bottom: 1px solid var(--Mavdee-border);
        padding: 14px 16px;
      }

      .filter-section:last-child {
        border-bottom: none;
      }

      .filter-section-title {
        font-size: 12px;
        font-weight: 700;
        color: var(--Mavdee-dark);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin: 0 0 10px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
      }

      .filter-section-title::after {
        content: '−';
        font-size: 1rem;
        color: var(--Mavdee-muted);
      }

      .filter-option {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--Mavdee-text);
        margin-bottom: 8px;
        cursor: pointer;
      }

      .filter-option input[type="checkbox"] {
        accent-color: var(--Mavdee-pink);
        width: 14px;
        height: 14px;
        cursor: pointer;
      }

      .filter-option:last-child {
        margin-bottom: 0;
      }

      .filter-price-inputs {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 6px;
      }

      .filter-price-inputs input {
        width: 100%;
        border: 1px solid var(--Mavdee-border);
        border-radius: 4px;
        padding: 5px 8px;
        font-size: 13px;
        font-family: var(--font-sans);
        color: var(--Mavdee-text);
        outline: none;
      }

      .filter-price-inputs input:focus {
        border-color: var(--Mavdee-pink);
      }

      .filter-apply-btn {
        width: 100%;
        padding: 8px;
        background: var(--Mavdee-pink);
        color: #fff;
        border: none;
        border-radius: 4px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        margin-top: 10px;
        font-family: var(--font-sans);
      }

      .filter-apply-btn:hover {
        background: #e0325a;
      }

      /* Desktop main area */
      .shop-main {
        min-width: 0;
      }

      .shop-main .prod-grid-wrap {
        padding: 0;
      }

      .shop-main .prod-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
      }
    }

    @media (min-width: 1200px) {
      .shop-layout {
        grid-template-columns: 260px 1fr;
      }

      .shop-main .prod-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    /* Desktop offer bar replaces chips on top */
    .shop-desktop-top {
      display: none;
    }

    @media (min-width: 900px) {
      .shop-desktop-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0 14px;
        border-bottom: 1px solid var(--Mavdee-border);
        margin-bottom: 16px;
      }

      .shop-desktop-top .offers-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
      }

      .shop-desktop-top .sort-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--Mavdee-muted);
        flex-shrink: 0;
      }

      .shop-desktop-top .sort-row select {
        border: 1px solid var(--Mavdee-border);
        border-radius: 4px;
        padding: 5px 10px;
        font-size: 13px;
        color: var(--Mavdee-text);
        font-family: var(--font-sans);
        outline: none;
        cursor: pointer;
      }
    }

    /* ── Mobile-specific overrides ───────────────────────────────── */
    @media (max-width: 767px) {
      /* Product grid: tighter, clearly separated cards */
      .prod-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
      }

      .prod-grid-wrap {
        padding: 8px;
      }

      /* Card image: enforce consistent aspect ratio */
      .prod-img-wrap {
        aspect-ratio: 3/4;
      }

      /* Product info: comfortable padding */
      .prod-info {
        padding: 8px 8px 10px;
      }

      .prod-brand {
        font-size: 12px;
      }

      .prod-title {
        font-size: 11px;
      }

      .prod-price {
        font-size: 14px;
      }

      .prod-original {
        font-size: 11px;
      }

      .prod-off {
        font-size: 11px;
      }

      /* Add button: slightly taller touch target */
      .prod-add-btn {
        padding: 9px 0;
        font-size: 11px;
      }

      /* Rating overlay: tighter */
      .prod-rating-overlay {
        font-size: 11px;
        padding: 2px 6px;
        bottom: 34px;
      }

      /* Offer banners: horizontal scroll, no wrap */
      .offer-banners {
        padding: 8px 6px;
        gap: 6px;
        border-bottom: 1px solid var(--mavdee-border);
      }

      .offer-chip {
        padding: 6px 12px;
        font-size: 10px;
        min-width: 64px;
      }

      /* Filter chips: horizontal scroll row */
      .filter-chips-wrap {
        padding: 6px 6px;
        gap: 5px;
        border-bottom: 1.5px solid var(--mavdee-border);
      }

      .filter-chip {
        padding: 5px 10px;
        font-size: 11px;
      }

      /* Product count label */
      .prod-grid-count {
        padding: 4px 4px 6px;
        font-size: 11px;
      }

      /* Empty state */
      .empty-state {
        padding: 48px 16px;
      }

      /* Ensure body clears the fixed bottom nav + safe area */
      body {
        padding-bottom: calc(var(--bottom-nav-height, 60px) + env(safe-area-inset-bottom) + 8px);
      }
    }

    /* Very small screens (< 360px): single narrower grid */
    @media (max-width: 359px) {
      .prod-grid {
        gap: 6px;
      }

      .prod-brand {
        font-size: 11px;
      }

      .prod-price {
        font-size: 13px;
      }

      .offer-chip {
        min-width: 56px;
        font-size: 9px;
      }
    }
  </style>
</head>

<body>

  <?php require __DIR__ . '/includes/header.php'; ?>

  <main id="main-content">
    <div class="main-content">

      <!-- Offer Banners (mobile only — on desktop shown inside .shop-layout) -->
      <div class="offer-banners" aria-label="Special offers">
        <a href="shop.php" class="offer-chip offer-chip-foryou">
          <span class="chip-icon">⭐</span>
          <span>FOR YOU</span>
        </a>
        <a href="shop.php" class="offer-chip offer-chip-minoff">
          <span class="chip-icon">🏷️</span>
          <span>MIN 60% OFF</span>
        </a>
        <a href="shop.php" class="offer-chip offer-chip-new">
          <span class="chip-icon">🆕</span>
          <span>WHAT'S NEW</span>
        </a>
        <a href="shop.php" class="offer-chip offer-chip-deal">
          <span class="chip-icon">🔥</span>
          <span>DEAL OF DAY</span>
        </a>
        <a href="shop.php" class="offer-chip offer-chip-express">
          <span class="chip-icon">⚡</span>
          <span>EXPRESS</span>
        </a>
      </div>

      <!-- Filter Chips (mobile only) -->
      <form id="filterForm" method="GET" action="shop.php" class="filter-form-wrap">
        <?php if ($searchQuery !== ''): ?>
          <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
        <?php endif; ?>
        <input type="hidden" name="max" value="<?= htmlspecialchars($maxPrice) ?>">
        <?php foreach ($selectedSizes as $sz): ?><input type="hidden" name="size[]" value="<?= htmlspecialchars($sz) ?>"><?php endforeach; ?>
        <?php foreach ($selectedColors as $cl): ?><input type="hidden" name="color[]" value="<?= htmlspecialchars($cl) ?>"><?php endforeach; ?>
      </form>

      <div class="filter-chips-wrap" role="navigation" aria-label="Product filters">
        <a href="#" class="filter-chip" onclick="openFilterModal('gender');return false;">
          Gender <span class="filter-chip-chevron">▾</span>
        </a>
        <?php foreach (['Kurtis', 'Dresses', 'Co-ord Sets', 'Party Wear'] as $catName): ?>
          <a href="shop.php?cat[]=<?= urlencode($catName) ?>" class="filter-chip <?= in_array($catName, $selectedCats) ? 'active' : '' ?>">
            <?= htmlspecialchars($catName) ?>
            <?php if (in_array($catName, $selectedCats)): ?> <span>✕</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if ($maxPrice < 10000): ?>
          <a href="shop.php" class="filter-chip active">
            ₹<?= number_format($maxPrice) ?> &amp; Below <span>✕</span>
          </a>
        <?php else: ?>
          <a href="#" class="filter-chip" onclick="openFilterModal('price');return false;">
            Price <span class="filter-chip-chevron">▾</span>
          </a>
        <?php endif; ?>
        <?php foreach ($selectedSizes as $sz): ?>
          <a href="shop.php?<?= http_build_query(array_merge($_GET, ['size' => array_diff($selectedSizes, [$sz])])) ?>" class="filter-chip active">
            Size: <?= htmlspecialchars($sz) ?> <span>✕</span>
          </a>
        <?php endforeach; ?>
        <?php foreach ($selectedColors as $cl): ?>
          <a href="shop.php?<?= http_build_query(array_merge($_GET, ['color' => array_diff($selectedColors, [$cl])])) ?>" class="filter-chip active">
            <?= htmlspecialchars($cl) ?> <span>✕</span>
          </a>
        <?php endforeach; ?>
        <?php if (!empty($selectedCats) || !empty($selectedSizes) || !empty($selectedColors) || $maxPrice < 10000): ?>
          <a href="shop.php" class="filter-chip" style="color:var(--Mavdee-pink);border-color:var(--Mavdee-pink);">Clear All</a>
        <?php endif; ?>
      </div>

      <!-- Desktop two-column layout (sidebar + products) -->
      <div class="shop-layout">

        <!-- Desktop Filter Sidebar -->
        <aside class="filter-sidebar" aria-label="Filter products">
          <div class="filter-sidebar-title">
            <span>FILTERS</span>
            <?php if (!empty($selectedCats) || !empty($selectedSizes) || !empty($selectedColors) || $maxPrice < 10000): ?>
              <a href="shop.php">Clear All</a>
            <?php endif; ?>
          </div>
          <form method="GET" action="shop.php" id="desktopFilterForm">
            <?php if ($searchQuery !== ''): ?>
              <input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>">
            <?php endif; ?>
            <!-- Categories -->
            <div class="filter-section">
              <div class="filter-section-title">CATEGORIES</div>
              <?php foreach (['Kurtis', 'Dresses', 'Co-ord Sets', 'Party Wear', 'Tops', 'Sarees'] as $catName): ?>
                <label class="filter-option">
                  <input type="checkbox" name="cat[]" value="<?= htmlspecialchars($catName) ?>"
                    <?= in_array($catName, $selectedCats) ? 'checked' : '' ?>
                    onchange="document.getElementById('desktopFilterForm').submit()">
                  <?= htmlspecialchars($catName) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <!-- Price -->
            <div class="filter-section">
              <div class="filter-section-title">PRICE RANGE</div>
              <?php foreach ([999 => 'Under ₹999', 1499 => 'Under ₹1,499', 2999 => 'Under ₹2,999', 4999 => 'Under ₹4,999'] as $val => $label): ?>
                <label class="filter-option">
                  <input type="radio" name="max" value="<?= $val ?>"
                    <?= $maxPrice == $val ? 'checked' : '' ?>
                    onchange="document.getElementById('desktopFilterForm').submit()">
                  <?= $label ?>
                </label>
              <?php endforeach; ?>
              <label class="filter-option">
                <input type="radio" name="max" value="10000"
                  <?= $maxPrice >= 10000 ? 'checked' : '' ?>
                  onchange="document.getElementById('desktopFilterForm').submit()">
                All prices
              </label>
            </div>
            <!-- Sizes -->
            <div class="filter-section">
              <div class="filter-section-title">SIZE</div>
              <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size'] as $sz): ?>
                <label class="filter-option">
                  <input type="checkbox" name="size[]" value="<?= htmlspecialchars($sz) ?>"
                    <?= in_array($sz, $selectedSizes) ? 'checked' : '' ?>
                    onchange="document.getElementById('desktopFilterForm').submit()">
                  <?= htmlspecialchars($sz) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <!-- Colors -->
            <div class="filter-section">
              <div class="filter-section-title">COLOUR</div>
              <?php foreach (['Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Pink', 'Orange'] as $cl): ?>
                <label class="filter-option">
                  <input type="checkbox" name="color[]" value="<?= htmlspecialchars($cl) ?>"
                    <?= in_array($cl, $selectedColors) ? 'checked' : '' ?>
                    onchange="document.getElementById('desktopFilterForm').submit()">
                  <?= htmlspecialchars($cl) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </form>
        </aside>

        <!-- Product Grid -->
        <div class="shop-main">
          <!-- Desktop top bar: offer chips + sort -->
          <div class="shop-desktop-top">
            <div class="offers-row">
              <a href="shop.php" class="offer-chip offer-chip-foryou" style="padding:5px 12px;font-size:11px;">⭐ FOR YOU</a>
              <a href="shop.php" class="offer-chip offer-chip-minoff" style="padding:5px 12px;font-size:11px;">🏷️ MIN 60% OFF</a>
              <a href="shop.php" class="offer-chip offer-chip-new" style="padding:5px 12px;font-size:11px;">🆕 NEW ARRIVALS</a>
              <a href="shop.php" class="offer-chip offer-chip-deal" style="padding:5px 12px;font-size:11px;">🔥 DEAL OF DAY</a>
              <a href="shop.php" class="offer-chip offer-chip-express" style="padding:5px 12px;font-size:11px;">⚡ EXPRESS</a>
            </div>
            <div class="sort-row">
              <span>Sort by:</span>
              <select onchange="if(this.value) window.location='shop.php?sort='+this.value+'<?= $sortBaseQuery !== '' ? h('&' . $sortBaseQuery) : '' ?>'">
                <option value="new" <?= $sortParam === 'new'        ? 'selected' : '' ?>>What's New</option>
                <option value="price_asc" <?= $sortParam === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sortParam === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="name" <?= $sortParam === 'name'       ? 'selected' : '' ?>>Name A–Z</option>
              </select>
            </div>
          </div>

          <div class="prod-grid-wrap">
            <div class="prod-grid-count">
              <?= count($products) ?> Products
            </div>
            <div class="prod-grid">
              <?php if (empty($products)): ?>
                <div class="empty-state">
                  <h2>No products found</h2>
                  <p>Try adjusting your filters to find what you're looking for.</p>
                  <a href="shop.php" class="btn-clear">Clear Filters</a>
                </div>
              <?php else: ?>
                <?php foreach ($products as $p):
                  $imgSrc = img_url($p['image_url'] ?? '');
                  if ($imgSrc === '') $imgSrc = '/assets/img/placeholder.svg';
                  $hasDiscount = !empty($p['original_price']) && $p['original_price'] > $p['price'];
                  $discountPct = $hasDiscount ? round((($p['original_price'] - $p['price']) / $p['original_price']) * 100) : 0;
                  $rating = number_format(3.5 + ($p['id'] % 15) / 10, 1);
                  $reviews = 100 + ($p['id'] * 37 % 5000);
                ?>
                  <div class="prod-card">
                    <!-- Image wrapper -->
                    <div class="prod-img-wrap">
                      <?php $__webp = get_webp_url($imgSrc); ?>
                      <picture>
                        <?php if ($__webp !== $imgSrc): ?>
                          <source srcset="<?= h($__webp) ?>" type="image/webp"><?php endif; ?>
                        <img src="<?= h($imgSrc) ?>" alt="<?= h($p['name']) ?>" loading="lazy"
                          onclick="window.location='product.php?id=<?= $p['id'] ?>&slug=<?= htmlspecialchars($p['slug'] ?? '') ?>'"
                          style="cursor:pointer;" onerror="this.src='/assets/img/placeholder.svg'">
                      </picture>
                      <!-- Wishlist btn -->
                      <?php $isWishlisted = in_array($p['id'], $userWishlist); ?>
                      <button class="wishlist-btn <?= $isWishlisted ? 'wishlisted' : '' ?>" type="button" id="wbtn-<?= $p['id'] ?>"
                        onclick="toggleWishlist(<?= $p['id'] ?>, this)" title="Wishlist" aria-label="Add to wishlist">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $isWishlisted ? '#ff3f6c' : 'none' ?>" stroke="<?= $isWishlisted ? '#ff3f6c' : 'currentColor' ?>" stroke-width="2">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                      </button>
                      <!-- Rating overlay -->
                      <div class="prod-rating-overlay">
                        <span><span class="prod-rating-star">★</span><?= $rating ?> | <?= number_format($reviews) ?></span>
                      </div>
                      <!-- Add button -->
                      <button class="prod-add-btn" type="button"
                        onclick="addToCartFromGrid(<?= $p['id'] ?>, this)"
                        aria-label="Add <?= h($p['name']) ?> to cart">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <circle cx="9" cy="21" r="1" />
                          <circle cx="20" cy="21" r="1" />
                          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                        </svg>
                        Add
                      </button>
                    </div>
                    <!-- Product info -->
                    <div class="prod-info" onclick="window.location='product.php?id=<?= $p['id'] ?>&slug=<?= htmlspecialchars($p['slug'] ?? '') ?>'" style="cursor:pointer;">
                      <div class="prod-brand"><?= htmlspecialchars($p['name']) ?></div>
                      <div class="prod-title"><?= htmlspecialchars($p['description'] ?? $p['name']) ?></div>
                      <div class="prod-price-row">
                        <span class="prod-price"><?= CURRENCY ?><?= number_format($p['price'], 0) ?></span>
                        <?php if ($hasDiscount): ?>
                          <span class="prod-original"><?= CURRENCY ?><?= number_format($p['original_price'], 0) ?></span>
                          <span class="prod-off"><?= $discountPct ?>% off</span>
                        <?php endif; ?>
                      </div>
                      <div>
                        <span class="prod-express">⚡ EXPRESS</span>
                        <span class="prod-delivery"> · 2 Day Delivery</span>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div><!-- /.prod-grid-wrap -->
        </div><!-- /.shop-main -->
      </div><!-- /.shop-layout -->

    </div><!-- /.main-content -->
  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>
  <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

  <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    var ADD_BTN_HTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Add';

    async function addToCartFromGrid(productId, btn) {
      const ADD_BTN_HTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Add';

      btn.disabled = true;
      btn.innerHTML = '...';

      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      try {
        // Check auth status first (like cart.js)
        let endpoint = '/api/cart/add.php';
        try {
          const statusRes = await fetch('/api/auth/status.php');
          if (statusRes.ok) {
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
              endpoint = '/api/cart/add_guest.php';
            }
          }
        } catch (authErr) {
          console.warn('Auth status check failed, using logged-in endpoint');
        }

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('qty', 1);
        formData.append('csrf_token', csrf);

        const res = await fetch(endpoint, {
          method: 'POST',
          body: formData
        });

        const data = await res.json();

        if ((data.success || data.ok) && data.success !== false) {
          btn.innerHTML = '✓ Added';
          btn.style.background = '#03a685';
          if (typeof window.updateCartBadge === 'function') {
            window.updateCartBadge(data.count || 1);
          }
          // Trigger cart open if available
          if (typeof openCart === 'function') openCart();
          setTimeout(() => {
            btn.innerHTML = ADD_BTN_HTML;
            btn.style.background = '';
            btn.disabled = false;
          }, 1500);
          return true;
        } else if (res.status === 401 || data.error?.includes('Login')) {
          window.location = '/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
          return false;
        } else {
          throw new Error(data.error || 'Add failed');
        }
      } catch (e) {
        console.error('Add to cart error:', e);
        btn.innerHTML = ADD_BTN_HTML;
        btn.disabled = false;
        if (typeof window.showToast === 'function') {
          window.showToast('Could not add item. Please try again.', 'error');
        }
        return false;
      }
    }

    function openFilterModal(type) {
      if (type === 'price') {
        var p = prompt('Max price (₹):', '<?= $maxPrice ?>');
        if (p && !isNaN(p)) window.location = 'shop.php?max=' + parseInt(p);
      }
    }
  </script>
</body>

</html>