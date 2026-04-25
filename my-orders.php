<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

// Validate Session
if (!isLoggedIn()) {
    header("Location: login.php?next=my-orders.php");
    exit;
}

$userId = getUserId();

// Fetch Customer Data
$stmt = db()->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$customer = $stmt->fetch();

if (!$customer) {
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $customer = $stmt->fetch();
    } catch (Throwable) {
        $customer = false;
    }
    if (!$customer) {
        header("Location: logout.php");
        exit;
    }
}

// Search query
$search = trim($_GET['q'] ?? '');

// Fetch Orders
try {
    if ($search !== '') {
        $stmt = db()->prepare(
            "SELECT o.* FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE o.customer_id = ?
               AND (o.order_number LIKE ? OR p.name LIKE ?)
             GROUP BY o.id
             ORDER BY o.created_at DESC"
        );
        $like = '%' . $search . '%';
        $stmt->execute([$userId, $like, $like]);
    } else {
        $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    }
    $orders = $stmt->fetchAll();
} catch (Throwable) {
    $orders = [];
}

// Fetch order items for each order
$orderItems = [];
foreach ($orders as $order) {
    try {
        $iStmt = db()->prepare(
            "SELECT oi.*, p.name AS product_name, p.image_url, p.slug
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?"
        );
        $iStmt->execute([$order['id']]);
        $orderItems[$order['id']] = $iStmt->fetchAll();
    } catch (Throwable) {
        $orderItems[$order['id']] = [];
    }
}

// Fetch recommended products (recently viewed or top-selling)
$recommended = [];
try {
    $rStmt = db()->prepare(
        "SELECT id, name, price, original_price, image_url, slug
         FROM products
         WHERE is_active = 1
         ORDER BY RAND()
         LIMIT 6"
    );
    $rStmt->execute();
    $recommended = $rStmt->fetchAll();
} catch (Throwable) {
    $recommended = [];
}

function statusIcon(string $status): string
{
    return match (strtolower($status)) {
        'cancelled' => '<div class="order-status-icon cancelled">✕</div>',
        'delivered' => '<div class="order-status-icon delivered">✓</div>',
        'shipped'   => '<div class="order-status-icon shipped">🚚</div>',
        default     => '<div class="order-status-icon pending">⏳</div>',
    };
}

function statusLabel(string $status, string $date): string
{
    $d = date('D, d M', strtotime($date));
    return match (strtolower($status)) {
        'cancelled' => "Cancelled on $d as per your request",
        'delivered' => "Delivered on $d",
        'shipped'   => "Shipped on $d",
        default     => "Order placed on $d",
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title>My Orders - <?= h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        :root {
            --Mavdee-pink: #ff3f6c;
            --Mavdee-green: #03a685;
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
            background: var(--Mavdee-grey);
            color: var(--Mavdee-text);
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(64px + env(safe-area-inset-bottom));
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── Page wrapper ── */
        .orders-page {
            max-width: 600px;
            margin: 0 auto;
            background: var(--Mavdee-grey);
        }

        /* ── Back header ── */
        .orders-header {
            background: #fff;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--Mavdee-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .orders-back {
            display: flex;
            align-items: center;
            color: var(--Mavdee-dark);
            padding: 4px;
        }

        .orders-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--Mavdee-dark);
            flex: 1;
            margin: 0;
        }

        .orders-wallet {
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--Mavdee-border);
            border-radius: 99px;
            padding: 5px 10px 5px 12px;
            font-size: 13px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        /* ── Insider banner ── */
        .insider-banner {
            background: linear-gradient(135deg, #e8e0ff 0%, #d5c8ff 100%);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .insider-text strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #3d1a8e;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .insider-text span {
            font-size: 13px;
            color: #3d1a8e;
            opacity: 0.8;
        }

        .btn-enroll {
            background: var(--Mavdee-pink);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-sans);
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ── Search + Filter row ── */
        .search-row {
            background: #fff;
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            border-bottom: 1px solid var(--Mavdee-border);
        }

        .search-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--Mavdee-border);
            border-radius: 4px;
            padding: 10px 12px;
        }

        .search-wrap input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 14px;
            font-family: var(--font-sans);
            color: var(--Mavdee-text);
            background: transparent;
        }

        .search-wrap input::placeholder {
            color: var(--Mavdee-muted);
        }

        .btn-filter {
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--Mavdee-border);
            border-radius: 4px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            background: #fff;
            cursor: pointer;
            font-family: var(--font-sans);
            white-space: nowrap;
        }

        /* ── Live updates banner ── */
        .live-updates {
            background: #fff5f7;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 10px 0 0;
        }

        .live-updates-img {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffb347 0%, #ff3f6c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        .live-updates-text {
            flex: 1;
        }

        .live-updates-text strong {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        .live-updates-text span {
            font-size: 13px;
            color: var(--Mavdee-muted);
        }

        .live-updates-text span em {
            color: var(--Mavdee-pink);
            font-style: normal;
            font-weight: 600;
        }

        .toggle-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        /* Toggle switch */
        .toggle {
            position: relative;
            width: 44px;
            height: 24px;
            display: inline-block;
        }

        .toggle input {
            display: none;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #ccc;
            border-radius: 99px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }

        .toggle input:checked+.toggle-slider {
            background: var(--Mavdee-green);
        }

        .toggle input:checked+.toggle-slider::before {
            transform: translateX(20px);
        }

        .toggle-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--Mavdee-pink);
        }

        /* ── Recommendations ── */
        .reco-section {
            background: #eef2fb;
            padding: 14px 0 14px 16px;
            margin-top: 10px;
        }

        .reco-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            margin: 0 0 12px;
            padding-right: 16px;
        }

        .reco-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scrollbar-width: none;
            padding-right: 16px;
        }

        .reco-scroll::-webkit-scrollbar {
            display: none;
        }

        .reco-card {
            background: #fff;
            border-radius: 8px;
            min-width: 160px;
            max-width: 160px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid var(--Mavdee-border);
        }

        .reco-card-img {
            width: 100%;
            height: 110px;
            object-fit: cover;
            display: block;
            background: var(--Mavdee-grey);
        }

        .reco-card-img-placeholder {
            width: 100%;
            height: 110px;
            background: linear-gradient(135deg, #f4f4f5, #eaeaec);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--Mavdee-muted);
            font-size: 1.8rem;
        }

        .reco-card-body {
            padding: 8px 10px;
        }

        .reco-card-brand {
            font-size: 12px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .reco-card-name {
            font-size: 12px;
            color: var(--Mavdee-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .reco-card-price-row {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        .reco-price {
            font-size: 13px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        .reco-original {
            font-size: 12px;
            color: var(--Mavdee-muted);
            text-decoration: line-through;
        }

        .reco-off {
            font-size: 12px;
            font-weight: 700;
            color: #ff9000;
        }

        /* ── Order groups ── */
        .orders-list {
            padding: 10px 0;
        }

        .order-group {
            background: #fff;
            margin-bottom: 10px;
        }

        .order-status-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px 10px;
        }

        /* Status icons */
        .order-status-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .order-status-icon.cancelled {
            background: #f4f4f5;
            color: var(--Mavdee-muted);
            border: 1px solid var(--Mavdee-border);
        }

        .order-status-icon.delivered {
            background: rgba(3, 166, 133, 0.1);
            color: var(--Mavdee-green);
        }

        .order-status-icon.shipped {
            background: rgba(91, 143, 190, 0.1);
            color: #5b8fbe;
            font-size: 15px;
        }

        .order-status-icon.pending {
            background: rgba(196, 148, 58, 0.1);
            color: #c4943a;
            font-size: 15px;
        }

        .order-status-text {
            flex: 1;
        }

        .order-status-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        .order-status-subtitle {
            font-size: 13px;
            color: var(--Mavdee-muted);
            margin-top: 2px;
        }

        /* Order item rows */
        .order-item-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px 14px;
            border-top: 1px solid var(--Mavdee-border);
            cursor: pointer;
            transition: background 0.15s;
        }

        .order-item-row:hover {
            background: #f9f9f9;
        }

        .order-item-img {
            width: 70px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            background: var(--Mavdee-grey);
            flex-shrink: 0;
        }

        .order-item-img-placeholder {
            width: 70px;
            height: 80px;
            background: linear-gradient(135deg, #f4f4f5, #eaeaec);
            border-radius: 4px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--Mavdee-muted);
        }

        .order-item-info {
            flex: 1;
            min-width: 0;
        }

        .order-item-brand {
            font-size: 14px;
            font-weight: 700;
            color: var(--Mavdee-dark);
        }

        .order-item-name {
            font-size: 13px;
            color: var(--Mavdee-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }

        .order-item-size {
            font-size: 13px;
            color: var(--Mavdee-muted);
            margin-top: 4px;
        }

        .order-item-arrow {
            color: var(--Mavdee-muted);
            flex-shrink: 0;
        }

        /* Profile row below item */
        .order-profile-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px 14px;
            border-top: 1px solid var(--Mavdee-border);
        }

        .order-profile-label {
            font-size: 13px;
            color: var(--Mavdee-muted);
        }

        .btn-add-profile {
            border: 1px solid var(--Mavdee-border);
            border-radius: 99px;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            background: #fff;
            cursor: pointer;
            font-family: var(--font-sans);
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--Mavdee-muted);
        }

        .empty-state svg {
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty-state p {
            margin: 0 0 16px;
            font-size: 15px;
        }

        .btn-shop {
            display: inline-block;
            background: var(--Mavdee-pink);
            color: #fff;
            padding: 12px 28px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-radius: 4px;
        }

        /* ── End of orders ── */
        .end-of-orders {
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: var(--Mavdee-muted);
            background: #fff;
            margin-top: 10px;
        }

        /* ── Desktop ── */
        @media (min-width: 768px) {
            .orders-page {
                margin: 24px auto;
                max-width: 1100px;
                background: transparent;
                padding: 0 16px;
            }

            .orders-header {
                display: none;
            }

            .orders-desktop-title {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                padding: 0 0 16px;
                border-bottom: 1px solid var(--Mavdee-border);
                margin-bottom: 20px;
            }

            .orders-desktop-title h1 {
                font-size: 1.3rem;
                font-weight: 700;
                color: var(--Mavdee-dark);
                margin: 0;
            }

            .orders-body-layout {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 20px;
                align-items: start;
            }

            .reco-section {
                background: #fff;
                border-radius: 8px;
                border: 1px solid var(--Mavdee-border);
                padding: 16px;
            }

            .reco-scroll {
                overflow-x: visible;
                flex-wrap: wrap;
                gap: 10px;
            }

            .reco-card {
                flex-shrink: 0;
                min-width: calc(50% - 5px);
                scroll-snap-align: unset;
            }
        }

        @media (max-width: 767px) {
            .orders-desktop-title {
                display: none;
            }

            .orders-side-col {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <div class="orders-page">

        <!-- Desktop-only title bar -->
        <div class="orders-desktop-title">
            <h1>My Orders</h1>
            <a href="shop.php" style="font-size:13px;color:var(--Mavdee-pink);font-weight:700;text-decoration:none;">Continue Shopping →</a>
        </div>

        <!-- ── Header ── -->
        <div class="orders-header">
            <a href="dashboard.php" class="orders-back" aria-label="Back to Profile">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </a>
            <h1 class="orders-title">My Orders</h1>
            <div class="orders-wallet">
                <?= CURRENCY ?>0
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#03a685" stroke-width="1.8">
                    <rect x="1" y="4" width="22" height="16" rx="2" />
                    <line x1="1" y1="10" x2="23" y2="10" />
                </svg>
            </div>
        </div>

        <!-- ── Insider banner ── -->
        <div class="insider-banner">
            <div class="insider-text">
                <strong>Mavdee Insider</strong>
                <span>Earn 10 supercoins for every <?= CURRENCY ?>100 purchase</span>
            </div>
            <button class="btn-enroll" onclick="openInsiderModal()">Enroll Now</button>
        </div>

        <!-- ── Search + Filter ── -->
        <div class="search-row">
            <form method="get" action="my-orders.php" class="search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--Mavdee-muted);flex-shrink:0;">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" name="q" placeholder="Search in orders" value="<?= h($search) ?>" autocomplete="off">
            </form>
            <button class="btn-filter" type="button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="6" x2="20" y2="6" />
                    <line x1="8" y1="12" x2="16" y2="12" />
                    <line x1="11" y1="18" x2="13" y2="18" />
                </svg>
                FILTER
            </button>
        </div>

        <!-- ── Live Updates banner ── -->
        <div class="live-updates">
            <div class="live-updates-img">📦</div>
            <div class="live-updates-text">
                <strong>Get Live Updates</strong>
                <span>About Your <em>Orders!</em></span>
            </div>
            <div class="toggle-wrap">
                <label class="toggle" aria-label="Allow live updates">
                    <input type="checkbox" id="liveUpdatesToggle">
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">Allow</span>
            </div>
        </div>

        <?php if (!empty($recommended)): ?>
            <!-- ── Two-column layout: orders list (main) + recommendations (sidebar) ── -->
            <div class="orders-body-layout">

                <!-- Main column: orders list -->
                <div class="orders-main-col">
                    <!-- ── Orders list ── -->
                    <div class="orders-list">
                        <?php if (empty($orders)): ?>
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                    <line x1="3" y1="6" x2="21" y2="6" />
                                    <path d="M16 10a4 4 0 0 1-8 0" />
                                </svg>
                                <p><?= $search ? 'No orders found for your search.' : "You haven't placed any orders yet." ?></p>
                                <a href="shop.php" class="btn-shop">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order):
                                $items = $orderItems[$order['id']] ?? [];
                                $status = strtolower($order['status']);
                                $statusTitle = ucfirst($status);
                                $statusDate = $order['updated_at'] ?? $order['created_at'];
                            ?>
                                <div class="order-group">
                                    <div class="order-status-row">
                                        <?= statusIcon($status) ?>
                                        <div class="order-status-text">
                                            <div class="order-status-title"><?= h($statusTitle) ?></div>
                                            <div class="order-status-subtitle"><?= h(statusLabel($status, $statusDate)) ?></div>
                                        </div>
                                    </div>

                                    <?php foreach ($items as $item): ?>
                                        <a href="order-details.php?id=<?= (int)$order['id'] ?>" class="order-item-row">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['product_name'] ?? '') ?>" class="order-item-img" loading="lazy">
                                            <?php else: ?>
                                                <div class="order-item-img-placeholder">👗</div>
                                            <?php endif; ?>
                                            <div class="order-item-info">
                                                <div class="order-item-brand"><?= h(explode(' ', $item['product_name'] ?? '')[0] ?? '') ?></div>
                                                <div class="order-item-name"><?= h($item['product_name'] ?? 'Product') ?></div>
                                                <?php if (!empty($item['size'])): ?>
                                                    <div class="order-item-size">Size: <?= h($item['size']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <svg class="order-item-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 18 15 12 9 6" />
                                            </svg>
                                        </a>
                                    <?php endforeach; ?>

                                    <?php if (empty($items)): ?>
                                        <a href="order-details.php?id=<?= (int)$order['id'] ?>" class="order-item-row">
                                            <div class="order-item-img-placeholder">📦</div>
                                            <div class="order-item-info">
                                                <div class="order-item-brand">Order <?= h($order['order_number']) ?></div>
                                                <div class="order-item-name"><?= CURRENCY ?><?= number_format($order['total'], 0) ?></div>
                                            </div>
                                            <svg class="order-item-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 18 15 12 9 6" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <div class="order-profile-row">
                                        <span class="order-profile-label">Bought this for</span>
                                        <button class="btn-add-profile" type="button">Add Profile</button>
                                    </div>
                                    <?php if (in_array(strtolower($order['status']), ['pending', 'confirmed'])): ?>
                                        <div style="padding:0 16px 14px;text-align:right;">
                                            <button type="button"
                                                onclick="confirmCancel(<?= (int)$order['id'] ?>)"
                                                style="background:none;border:1px solid #eaeaec;border-radius:4px;padding:6px 14px;font-size:12px;font-weight:700;color:#94969f;cursor:pointer;font-family:inherit;text-transform:uppercase;letter-spacing:.05em;"
                                                onmouseover="this.style.borderColor='#ff3f6c';this.style.color='#ff3f6c';"
                                                onmouseout="this.style.borderColor='#eaeaec';this.style.color='#94969f';">
                                                Cancel Order
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="end-of-orders">You have reached the end of your orders</div>
                        <?php endif; ?>
                    </div>
                </div><!-- /orders-main-col -->

                <!-- Side column: recommendations (desktop sidebar) -->
                <div class="orders-side-col">
                    <div class="reco-section">
                        <p class="reco-title">Frequently bought by shoppers like you</p>
                        <div class="reco-scroll">
                            <?php foreach ($recommended as $reco):
                                $hasDiscount = $reco['original_price'] && $reco['original_price'] > $reco['price'];
                                $discPct = $hasDiscount ? round(100 * (1 - $reco['price'] / $reco['original_price'])) : 0;
                            ?>
                                <a href="product.php?slug=<?= h($reco['slug']) ?>" class="reco-card">
                                    <?php if ($reco['image_url']): ?>
                                        <img src="<?= h($reco['image_url']) ?>" alt="<?= h($reco['name']) ?>" class="reco-card-img" loading="lazy">
                                    <?php else: ?>
                                        <div class="reco-card-img-placeholder">👗</div>
                                    <?php endif; ?>
                                    <div class="reco-card-body">
                                        <div class="reco-card-brand"><?= h(explode(' ', $reco['name'])[0]) ?></div>
                                        <div class="reco-card-name"><?= h($reco['name']) ?></div>
                                        <div class="reco-card-price-row">
                                            <span class="reco-price"><?= CURRENCY ?><?= number_format($reco['price'], 0) ?></span>
                                            <?php if ($hasDiscount): ?>
                                                <span class="reco-original"><?= CURRENCY ?><?= number_format($reco['original_price'], 0) ?></span>
                                                <span class="reco-off"><?= $discPct ?>% OFF</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div><!-- /orders-side-col -->

            </div><!-- /orders-body-layout -->

        <?php else: ?>
            <!-- No recommendations: orders list full-width -->
            <!-- ── Orders list ── -->
            <div class="orders-list">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                        <p><?= $search ? 'No orders found for your search.' : "You haven't placed any orders yet." ?></p>
                        <a href="shop.php" class="btn-shop">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order):
                        $items = $orderItems[$order['id']] ?? [];
                        $status = strtolower($order['status']);
                        $statusTitle = ucfirst($status);
                        $statusDate = $order['updated_at'] ?? $order['created_at'];
                    ?>
                        <div class="order-group">
                            <div class="order-status-row">
                                <?= statusIcon($status) ?>
                                <div class="order-status-text">
                                    <div class="order-status-title"><?= h($statusTitle) ?></div>
                                    <div class="order-status-subtitle"><?= h(statusLabel($status, $statusDate)) ?></div>
                                </div>
                            </div>

                            <?php foreach ($items as $item): ?>
                                <a href="order-details.php?id=<?= (int)$order['id'] ?>" class="order-item-row">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['product_name'] ?? '') ?>" class="order-item-img" loading="lazy">
                                    <?php else: ?>
                                        <div class="order-item-img-placeholder">👗</div>
                                    <?php endif; ?>
                                    <div class="order-item-info">
                                        <div class="order-item-brand"><?= h(explode(' ', $item['product_name'] ?? '')[0] ?? '') ?></div>
                                        <div class="order-item-name"><?= h($item['product_name'] ?? 'Product') ?></div>
                                        <?php if (!empty($item['size'])): ?>
                                            <div class="order-item-size">Size: <?= h($item['size']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <svg class="order-item-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6" />
                                    </svg>
                                </a>
                            <?php endforeach; ?>

                            <?php if (empty($items)): ?>
                                <a href="order-details.php?id=<?= (int)$order['id'] ?>" class="order-item-row">
                                    <div class="order-item-img-placeholder">📦</div>
                                    <div class="order-item-info">
                                        <div class="order-item-brand">Order <?= h($order['order_number']) ?></div>
                                        <div class="order-item-name"><?= CURRENCY ?><?= number_format($order['total'], 0) ?></div>
                                    </div>
                                    <svg class="order-item-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <div class="order-profile-row">
                                <span class="order-profile-label">Bought this for</span>
                                <button class="btn-add-profile" type="button">Add Profile</button>
                            </div>
                            <?php if (in_array(strtolower($order['status']), ['pending', 'confirmed'])): ?>
                                <div style="padding:0 16px 14px;text-align:right;">
                                    <button type="button"
                                        onclick="confirmCancel(<?= (int)$order['id'] ?>)"
                                        style="background:none;border:1px solid #eaeaec;border-radius:4px;padding:6px 14px;font-size:12px;font-weight:700;color:#94969f;cursor:pointer;font-family:inherit;text-transform:uppercase;letter-spacing:.05em;"
                                        onmouseover="this.style.borderColor='#ff3f6c';this.style.color='#ff3f6c';"
                                        onmouseout="this.style.borderColor='#eaeaec';this.style.color='#94969f';">
                                        Cancel Order
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="end-of-orders">You have reached the end of your orders</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- /orders-page -->

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        document.getElementById('liveUpdatesToggle').addEventListener('change', function() {
            var toggle = this;
            if (this.checked) {
                if ('Notification' in window) {
                    Notification.requestPermission().then(function(perm) {
                        if (perm !== 'granted') {
                            toggle.checked = false;
                            return;
                        }
                        startNotificationPolling();
                        localStorage.setItem('liveUpdates', '1');
                    });
                } else {
                    startNotificationPolling();
                    localStorage.setItem('liveUpdates', '1');
                }
            } else {
                stopNotificationPolling();
                localStorage.removeItem('liveUpdates');
            }
        });

        var _notifPollInterval = null;
        var _lastNotifId = 0;

        function startNotificationPolling() {
            if (_notifPollInterval) return;
            _notifPollInterval = setInterval(pollNotifications, 30000);
            pollNotifications();
        }

        function stopNotificationPolling() {
            if (_notifPollInterval) {
                clearInterval(_notifPollInterval);
                _notifPollInterval = null;
            }
        }

        function pollNotifications() {
            fetch('/api/notifications/get.php', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    var notifications = data.notifications || [];
                    notifications.forEach(function(n) {
                        if (n.id > _lastNotifId && !n.is_read) {
                            if (Notification.permission === 'granted') {
                                new Notification('Mavdee', {
                                    body: n.message || 'You have a new update.',
                                    icon: '/assets/img/logo.png'
                                });
                            }
                        }
                    });
                    if (notifications.length > 0) {
                        _lastNotifId = Math.max.apply(null, notifications.map(function(n) {
                            return n.id;
                        }));
                    }
                })
                .catch(function() {});
        }

        function confirmCancel(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) return;
            var reason = prompt('Reason for cancellation (optional):') || '';
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch('/api/orders/cancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        cancel_reason: reason,
                        csrf_token: csrf ? csrf.getAttribute('content') : ''
                    })
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.status === 'success') {
                        alert('Order cancelled successfully.');
                        location.reload();
                    } else {
                        alert(data.message || 'Could not cancel the order. Please try again.');
                    }
                })
                .catch(function() {
                    alert('An error occurred. Please try again.');
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('liveUpdates') === '1') {
                document.getElementById('liveUpdatesToggle').checked = true;
                startNotificationPolling();
            }
        });
    </script>

    <!-- ── Mavdee Insider Modal ──────────────────────────────────────────────────── -->
    <div id="insiderModal" role="dialog" aria-modal="true" aria-label="Mavdee Insider Programme" style="display:none;position:fixed;inset:0;z-index:5000;">
        <div id="insiderModalBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,.5);"></div>
        <div style="position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:center;min-height:100%;padding:0;">
            <div style="background:#fff;border-radius:12px 12px 0 0;padding:28px 24px 36px;max-width:480px;width:100%;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                    <h2 style="font-family:var(--font-sans,sans-serif);font-size:1rem;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.04em;color:#1c1c1c;">Mavdee Insider</h2>
                    <button onclick="closeInsiderModal()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#94969f;line-height:1;padding:0 4px;">&times;</button>
                </div>
                <p style="font-size:.9rem;color:#535766;margin:0 0 16px;line-height:1.6;">
                    Join the <strong>Mavdee Insider</strong> loyalty programme and earn <strong>10 supercoins</strong> for every <?= CURRENCY ?>100 you spend. Redeem your coins for exciting rewards and exclusive offers!
                </p>
                <ul style="font-size:.85rem;color:#535766;margin:0 0 24px;padding-left:18px;line-height:1.8;">
                    <li>Earn supercoins on every purchase</li>
                    <li>Early access to sales and new arrivals</li>
                    <li>Exclusive member-only discounts</li>
                </ul>
                <p style="font-size:.82rem;color:#ff3f6c;font-weight:600;margin:0 0 20px;text-align:center;">🚀 Insider enrolment is coming soon — stay tuned!</p>
                <button onclick="closeInsiderModal()" style="width:100%;padding:13px;background:#ff3f6c;color:#fff;border:none;border-radius:4px;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;cursor:pointer;font-family:inherit;">Got It</button>
            </div>
        </div>
    </div>
    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        (function() {
            var backdrop = document.getElementById('insiderModalBackdrop');
            if (backdrop) backdrop.addEventListener('click', closeInsiderModal);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    var m = document.getElementById('insiderModal');
                    if (m && m.style.display !== 'none') closeInsiderModal();
                }
            });
        })();

        function openInsiderModal() {
            var m = document.getElementById('insiderModal');
            if (m) {
                m.style.display = '';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeInsiderModal() {
            var m = document.getElementById('insiderModal');
            if (m) {
                m.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
    </script>
</body>

</html>