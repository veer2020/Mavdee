<?php

declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

if (!isLoggedIn()) {
    header('Location: login.php?next=' . urlencode('order-details.php?id=' . (int)($_GET['id'] ?? 0)));
    exit;
}

$userId  = getUserId();
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$order = db_row("SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1", [$orderId, $userId]);
if (!$order) {
    header('Location: dashboard.php');
    exit;
}

$items = db_select("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

$timelineSteps = [
    'pending'    => ['label' => 'Order Placed',  'icon' => '📦', 'color' => '#0984e3'],
    'processing' => ['label' => 'Processing',    'icon' => '⚙️',  'color' => '#fdcb6e'],
    'dispatched' => ['label' => 'Dispatched',    'icon' => '🚚', 'color' => '#a29bfe'],
    'delivered'  => ['label' => 'Delivered',     'icon' => '✅', 'color' => '#00b894'],
];

$displayStatus = $order['status'] === 'shipped' ? 'dispatched' : $order['status'];
$isCancelled   = ($order['status'] === 'cancelled');
$stepOrder     = ['pending', 'processing', 'dispatched', 'delivered'];
$currentStep   = array_search($displayStatus, $stepOrder, true);
$stepTimestamps = [
    'pending'    => $order['created_at']    ?? null,
    'processing' => $order['processed_at']  ?? null,
    'dispatched' => $order['dispatched_at'] ?? null,
    'delivered'  => $order['delivered_at']  ?? null,
];
$cancellable = in_array($order['status'], ['pending', 'confirmed'], true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Order #<?= h($order['order_number']) ?> — <?= h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        :root {
            --coral: #FF4757;
            --coral-d: #e03346;
            --coral-xl: #fff0f1;
            --ink: #0F0F0F;
            --text: #2D2926;
            --muted: #8B8680;
            --surface: #F8F7F5;
            --border: #E8E5E1;
            --white: #fff;
            --jade: #00b894;
            --amber: #fdcb6e;
            --sky: #0984e3;
            --f-d: 'Syne', sans-serif;
            --f-b: 'DM Sans', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--f-b);
            color: var(--text);
            background: var(--surface);
            -webkit-font-smoothing: antialiased;
            padding-bottom: 80px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .od-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 16px 12px calc(var(--bottom-nav-height, 60px) + 40px);
        }

        @media (min-width: 768px) {
            .od-wrap {
                padding: 32px 24px 80px;
            }
        }

        /* ── Page Header ── */
        .od-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .od-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: color 0.2s;
        }

        .od-back:hover {
            color: var(--coral);
        }

        .od-heading {
            font-family: var(--f-d);
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.03em;
        }

        .od-meta {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .od-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ── Card ── */
        .od-card {
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }

        .od-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .od-card-title {
            font-family: var(--f-d);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
        }

        .od-card-body {
            padding: 20px;
        }

        /* ── Cancelled Banner ── */
        .cancelled-banner {
            background: var(--coral-xl);
            border: 1px solid rgba(255, 71, 87, 0.2);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .cb-icon {
            font-size: 1.4rem;
        }

        .cb-title {
            font-family: var(--f-d);
            font-weight: 700;
            color: var(--coral-d);
            margin-bottom: 3px;
        }

        .cb-body {
            font-size: 13px;
            color: var(--muted);
        }

        /* ── Timeline ── */
        .timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 8px 0 4px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 26px;
            left: calc(50% / 4);
            right: calc(50% / 4);
            height: 3px;
            background: var(--border);
            border-radius: 99px;
        }

        .timeline-progress {
            position: absolute;
            top: 26px;
            left: calc(50% / 4);
            height: 3px;
            background: linear-gradient(90deg, var(--sky), var(--jade));
            border-radius: 99px;
            transition: width 0.8s ease;
        }

        .tl-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
            text-align: center;
            gap: 8px;
        }

        .tl-dot {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--surface);
            border: 2.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.4s;
        }

        .tl-step.done .tl-dot {
            background: var(--jade);
            border-color: var(--jade);
            filter: brightness(1.05);
        }

        .tl-step.current .tl-dot {
            background: var(--sky);
            border-color: var(--sky);
            box-shadow: 0 0 0 5px rgba(9, 132, 227, 0.15);
            animation: currentPulse 2s ease-in-out infinite;
        }

        @keyframes currentPulse {

            0%,
            100% {
                box-shadow: 0 0 0 5px rgba(9, 132, 227, 0.15);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(9, 132, 227, 0.08);
            }
        }

        .tl-step.cancelled .tl-dot {
            background: var(--coral-xl);
            border-color: var(--coral);
        }

        .tl-label {
            font-family: var(--f-d);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .tl-step.done .tl-label,
        .tl-step.current .tl-label {
            color: var(--ink);
        }

        .tl-date {
            font-size: 11px;
            color: var(--muted);
        }

        /* ── Order Items ── */
        .oi-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .oi-row:last-child {
            border-bottom: none;
        }

        .oi-img {
            width: 58px;
            height: 72px;
            background: var(--surface);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .oi-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .oi-info {
            flex: 1;
        }

        .oi-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 3px;
        }

        .oi-meta {
            font-size: 12.5px;
            color: var(--muted);
        }

        .oi-price {
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            white-space: nowrap;
        }

        /* ── Totals ── */
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 13.5px;
            color: var(--muted);
        }

        .totals-row.grand {
            font-family: var(--f-d);
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
            border-top: 2px solid var(--border);
            margin-top: 8px;
            padding-top: 12px;
        }

        .free {
            color: var(--jade);
            font-weight: 700;
        }

        .discount {
            color: var(--jade);
        }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item span {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 4px;
        }

        .info-item p {
            font-size: 14px;
            color: var(--ink);
            font-weight: 500;
            line-height: 1.5;
        }

        /* ── Tracking ── */
        .tracking-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            flex-wrap: wrap;
        }

        .tracking-id {
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            flex: 1;
        }

        .btn-copy {
            background: var(--coral);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--f-b);
            transition: background 0.2s;
        }

        .btn-copy:hover {
            background: var(--coral-d);
        }

        /* ── Cancel ── */
        .cancel-section {
            text-align: center;
            padding: 20px;
        }

        .btn-cancel {
            background: none;
            border: 1.5px solid var(--border);
            color: var(--muted);
            padding: 11px 24px;
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-cancel:hover {
            border-color: var(--coral);
            color: var(--coral);
        }

        /* ── Modal ── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 15, 15, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal {
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.2);
            animation: modalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalIn {
            from {
                transform: scale(0.88);
                opacity: 0;
            }
        }

        .modal h3 {
            font-family: var(--f-d);
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .modal p {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 16px;
        }

        .modal textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: var(--f-b);
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            color: var(--ink);
            outline: none;
        }

        .modal textarea:focus {
            border-color: var(--coral);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .btn-modal-keep {
            flex: 1;
            padding: 13px;
            background: #fff;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .btn-modal-keep:hover {
            border-color: var(--coral);
        }

        .btn-modal-confirm {
            flex: 1;
            padding: 13px;
            background: var(--coral);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-confirm:hover {
            background: var(--coral-d);
        }

        .btn-modal-confirm:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ── Mobile Overrides ── */
        @media (max-width: 767px) {
            body {
                padding-bottom: calc(var(--bottom-nav-height, 60px) + 20px);
            }

            .od-wrap {
                padding: 12px 10px calc(var(--bottom-nav-height, 60px) + 48px);
            }

            .od-card {
                border-radius: 12px;
                margin-bottom: 12px;
                border-width: 1px;
            }

            .od-card-body {
                padding: 14px;
            }

            .od-card-header {
                padding: 12px 14px;
            }

            .od-heading {
                font-size: 1.25rem;
            }

            .od-page-header {
                margin-bottom: 16px;
                gap: 8px;
            }

            /* Timeline: tighter on small screens */
            .tl-dot {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .tl-label {
                font-size: 9px;
                letter-spacing: 0.04em;
            }

            .tl-date {
                font-size: 10px;
            }

            .timeline::before {
                top: 18px;
            }

            .timeline-progress {
                top: 18px;
            }

            /* Order items: tighter layout */
            .oi-row {
                gap: 10px;
                padding: 10px 0;
            }

            .oi-img {
                width: 48px;
                height: 60px;
                border-radius: 6px;
            }

            .oi-name {
                font-size: 13px;
            }

            .oi-meta {
                font-size: 12px;
            }

            .oi-price {
                font-size: 13px;
            }

            /* Info grid: single column on mobile */
            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            /* Cancel section */
            .cancel-section {
                padding: 14px 0 4px;
            }

            .btn-cancel {
                width: 100%;
            }

            /* Modal padding */
            .modal {
                padding: 24px 18px;
                border-radius: 16px;
            }

            /* Tracking wrap */
            .tracking-wrap {
                padding: 10px 12px;
            }

            /* Totals */
            .totals-row {
                font-size: 13px;
            }

            .totals-row.grand {
                font-size: 15px;
            }

            /* Status pill */
            .od-status-pill {
                font-size: 11px;
                padding: 4px 10px;
            }
        }

        @media (max-width: 380px) {
            .tl-label {
                font-size: 8px;
            }

            .tl-dot {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }

            .timeline::before,
            .timeline-progress {
                top: 15px;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <div class="od-wrap">

        <!-- Page Header -->
        <div class="od-page-header">
            <div>
                <a href="dashboard.php" class="od-back">← My Account</a>
                <h1 class="od-heading">Order #<?= h($order['order_number']) ?></h1>
                <p class="od-meta">
                    Placed <?= date('d M Y \a\t H:i', strtotime($order['created_at'])) ?>
                    · <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
                </p>
            </div>
            <?php
            $statusStyles = [
                'pending'    => 'background:#e3f2fd;color:#0d47a1',
                'processing' => 'background:#fff8e1;color:#f57f17',
                'dispatched' => 'background:#f3e5f5;color:#6a1b9a',
                'shipped'    => 'background:#f3e5f5;color:#6a1b9a',
                'delivered'  => 'background:#f0faf7;color:#05684e',
                'cancelled'  => 'background:var(--coral-xl);color:var(--coral-d)',
            ];
            $ss = $statusStyles[$order['status']] ?? 'background:var(--surface);color:var(--muted)';
            ?>
            <span class="od-status-pill" style="<?= $ss ?>">
                <?= ucfirst($displayStatus) ?>
            </span>
        </div>

        <?php if ($isCancelled): ?>
            <div class="cancelled-banner">
                <span class="cb-icon">❌</span>
                <div>
                    <div class="cb-title">Order Cancelled</div>
                    <div class="cb-body">
                        <?php if (!empty($order['cancelled_at'])): ?>Cancelled on <?= date('d M Y \a\t H:i', strtotime($order['cancelled_at'])) ?>.<?php endif; ?>
                        <?php if (!empty($order['cancel_reason'])): ?> Reason: <?= h($order['cancel_reason']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Timeline -->
        <div class="od-card">
            <div class="od-card-header">
                <span class="od-card-title">Order Status</span>
            </div>
            <div class="od-card-body">
                <?php
                $progressWidth = '0%';
                if (!$isCancelled && is_int($currentStep) && $currentStep >= 0) {
                    $progressWidth = ($currentStep / (count($stepOrder) - 1) * 100) . '%';
                }
                ?>
                <div class="timeline" style="position:relative;">
                    <div class="timeline-progress" style="width:<?= $progressWidth ?>"></div>
                    <?php foreach ($timelineSteps as $key => $step):
                        $stepIndex = array_search($key, $stepOrder, true);
                        if ($isCancelled) {
                            $cls = 'cancelled';
                        } elseif (is_int($currentStep)) {
                            if ($stepIndex < $currentStep) $cls = 'done';
                            elseif ($stepIndex === $currentStep) $cls = 'current';
                            else $cls = '';
                        } else {
                            $cls = '';
                        }
                    ?>
                        <div class="tl-step <?= $cls ?>">
                            <div class="tl-dot"><?= $step['icon'] ?></div>
                            <div class="tl-label"><?= h($step['label']) ?></div>
                            <?php if (!empty($stepTimestamps[$key])): ?>
                                <div class="tl-date"><?= date('d M', strtotime($stepTimestamps[$key])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($isCancelled): ?>
                        <div class="tl-step cancelled">
                            <div class="tl-dot">❌</div>
                            <div class="tl-label">Cancelled</div>
                            <?php if (!empty($order['cancelled_at'])): ?>
                                <div class="tl-date"><?= date('d M', strtotime($order['cancelled_at'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tracking -->
        <?php if (!empty($order['tracking_number'])): ?>
            <div class="od-card">
                <div class="od-card-header"><span class="od-card-title">Tracking</span></div>
                <div class="od-card-body">
                    <div class="tracking-wrap">
                        <span style="font-size:1.2rem;">🚚</span>
                        <div class="tracking-id"><?= h($order['tracking_number']) ?></div>
                        <?php if (!empty($order['courier'])): ?>
                            <span style="font-size:13px;color:var(--muted);">via <?= h($order['courier']) ?></span>
                        <?php endif; ?>
                        <button class="btn-copy" onclick="copyTracking(this,'<?= h($order['tracking_number']) ?>')">Copy</button>
                    </div>
                    <?php if (strtolower($order['courier'] ?? '') === 'delhivery'): ?>
                        <a href="https://www.delhivery.com/track/package/<?= rawurlencode($order['tracking_number']) ?>"
                            target="_blank" rel="noopener noreferrer"
                            style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--coral);font-weight:600;margin-top:12px;">
                            🔗 Track on Delhivery →
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Items -->
        <div class="od-card">
            <div class="od-card-header"><span class="od-card-title">Items (<?= count($items) ?>)</span></div>
            <div class="od-card-body" style="padding-top:8px;padding-bottom:8px;">
                <?php foreach ($items as $item): ?>
                    <div class="oi-row">
                        <div class="oi-img">🛍</div>
                        <div class="oi-info">
                            <div class="oi-name"><?= h($item['product_name']) ?></div>
                            <div class="oi-meta">
                                Qty: <?= (int)$item['qty'] ?>
                                <?php if (!empty($item['size'])): ?> · Size: <?= h($item['size']) ?><?php endif; ?>
                                    <?php if (!empty($item['color'])): ?> · Color: <?= h($item['color']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="oi-price"><?= h(CURRENCY) ?><?= number_format((float)$item['unit_price'] * (int)$item['qty'], 2) ?></div>
                    </div>
                <?php endforeach; ?>

                <!-- Totals -->
                <div style="margin-top:16px;padding-top:8px;border-top:1px solid var(--border);">
                    <div class="totals-row"><span>Subtotal</span><span><?= h(CURRENCY) ?><?= number_format((float)$order['subtotal'], 2) ?></span></div>
                    <?php $shipAmt = (float)($order['shipping_amount'] ?? $order['shipping_cost'] ?? 0); ?>
                    <div class="totals-row">
                        <span>Shipping</span>
                        <?php if ($shipAmt > 0): ?>
                            <span><?= h(CURRENCY) ?><?= number_format($shipAmt, 2) ?></span>
                        <?php else: ?>
                            <span class="free">FREE</span>
                        <?php endif; ?>
                    </div>
                    <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                        <div class="totals-row"><span>Discount</span><span class="discount">−<?= h(CURRENCY) ?><?= number_format((float)$order['discount_amount'], 2) ?></span></div>
                    <?php endif; ?>
                    <div class="totals-row grand"><span>Total</span><span><?= h(CURRENCY) ?><?= number_format((float)$order['total'], 2) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Order Details -->
        <div class="od-card">
            <div class="od-card-header"><span class="od-card-title">Order Details</span></div>
            <div class="od-card-body">
                <div class="info-grid">
                    <div class="info-item"><span>Order Number</span>
                        <p><?= h($order['order_number']) ?></p>
                    </div>
                    <div class="info-item"><span>Payment Method</span>
                        <p><?= h(ucwords($order['payment_method'] ?? '—')) ?></p>
                    </div>
                    <div class="info-item"><span>Payment Status</span>
                        <p><?= h(ucfirst($order['payment_status'] ?? '—')) ?></p>
                    </div>
                    <div class="info-item"><span>Order Status</span>
                        <p><?= h(ucfirst($displayStatus)) ?></p>
                    </div>
                    <?php if (!empty($order['created_at'])): ?>
                        <div class="info-item"><span>Ordered On</span>
                            <p><?= date('d M Y', strtotime($order['created_at'])) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($order['delivered_at'])): ?>
                        <div class="info-item"><span>Delivered On</span>
                            <p><?= date('d M Y', strtotime($order['delivered_at'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $shipping = [];
                if (!empty($order['shipping_address'])) {
                    $decoded  = json_decode($order['shipping_address'], true);
                    $shipping = is_array($decoded) ? $decoded : ['address' => $order['shipping_address']];
                }
                if (!empty($shipping)):
                ?>
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                        <div class="info-item">
                            <span>Delivery Address</span>
                            <p>
                                <?php foreach ($shipping as $val): if (!$val) continue; ?>
                                    <?= h(is_array($val) ? implode(', ', $val) : (string)$val) ?><br>
                                <?php endforeach; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cancel -->
        <?php if ($cancellable): ?>
            <div class="cancel-section">
                <button type="button" class="btn-cancel" onclick="openCancelModal(<?= (int)$order['id'] ?>)">Cancel This Order</button>
                <p style="font-size:12px;color:var(--muted);margin-top:6px;">Orders can be cancelled before they are dispatched.</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Cancel Modal -->
    <div class="modal-backdrop" id="cancelBackdrop">
        <div class="modal" role="dialog" aria-modal="true">
            <h3>Cancel Order?</h3>
            <p>Are you sure? This action cannot be undone. Any payment will be refunded within 5–7 business days.</p>
            <textarea id="cancelReason" placeholder="Reason for cancellation (optional)" maxlength="1000"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn-modal-keep" onclick="closeCancelModal()">Keep Order</button>
                <button type="button" class="btn-modal-confirm" id="cancelConfirmBtn" onclick="submitCancel()">Yes, Cancel</button>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        var _cancelOrderId = 0;

        function openCancelModal(id) {
            _cancelOrderId = id;
            document.getElementById('cancelReason').value = '';
            document.getElementById('cancelBackdrop').classList.add('open');
        }

        function closeCancelModal() {
            document.getElementById('cancelBackdrop').classList.remove('open');
        }
        document.getElementById('cancelBackdrop').addEventListener('click', function(e) {
            if (e.target === this) closeCancelModal();
        });

        function copyTracking(btn, tn) {
            navigator.clipboard.writeText(tn).then(function() {
                var orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                setTimeout(function() {
                    btn.textContent = orig;
                }, 2000);
            });
        }

        async function submitCancel() {
            var btn = document.getElementById('cancelConfirmBtn');
            btn.disabled = true;
            btn.textContent = 'Cancelling…';
            var reason = document.getElementById('cancelReason').value.trim();
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                var res = await fetch('/api/orders/cancel.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: _cancelOrderId,
                        cancel_reason: reason,
                        csrf_token: csrf
                    })
                });
                var data = await res.json();
                closeCancelModal();
                if (data.status === 'success') location.reload();
                else alert(data.message || 'Could not cancel the order.');
            } catch (e) {
                closeCancelModal();
                alert('An error occurred.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Yes, Cancel';
            }
        }
    </script>
<!-- Code injected by live-server -->
<script type="text/javascript">
	// <![CDATA[  <-- For SVG support
	if ('WebSocket' in window) {
		(function() {
			function refreshCSS() {
				var sheets = [].slice.call(document.getElementsByTagName("link"));
				var head = document.getElementsByTagName("head")[0];
				for (var i = 0; i < sheets.length; ++i) {
					var elem = sheets[i];
					head.removeChild(elem);
					var rel = elem.rel;
					if (elem.href && typeof rel != "string" || rel.length == 0 || rel.toLowerCase() == "stylesheet") {
						var url = elem.href.replace(/(&|\?)_cacheOverride=\d+/, '');
						elem.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_cacheOverride=' + (new Date().valueOf());
					}
					head.appendChild(elem);
				}
			}
			var protocol = window.location.protocol === 'http:' ? 'ws://' : 'wss://';
			var address = protocol + window.location.host + window.location.pathname + '/ws';
			var socket = new WebSocket(address);
			socket.onmessage = function(msg) {
				if (msg.data == 'reload') window.location.reload();
				else if (msg.data == 'refreshcss') refreshCSS();
			};
			console.log('Live reload enabled.');
		})();
	}
	// ]]>
</script>
</body>

</html>