<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Information — <?= h(SITE_NAME) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
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
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── Hero ── */
        .ship-hero {
            background: var(--ink);
            color: #fff;
            padding: 100px 24px 64px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .ship-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 80% 50%, rgba(255, 71, 87, 0.22) 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 20% 80%, rgba(0, 184, 148, 0.18) 0%, transparent 60%);
        }

        .ship-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 640px;
            margin: 0 auto;
        }

        .ship-hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 16px;
        }

        .ship-hero h1 {
            font-family: var(--f-d);
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.05;
            margin-bottom: 16px;
        }

        .ship-hero h1 span {
            color: var(--jade);
        }

        .ship-hero-sub {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.7;
            max-width: 480px;
            margin: 0 auto;
        }

        /* ── Shipping Option Cards ── */
        .ship-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            max-width: 900px;
            margin: -40px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        @media (min-width: 640px) {
            .ship-options {
                grid-template-columns: 1fr 1fr;
            }
        }

        .ship-option-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            transition: transform 0.25s, box-shadow 0.25s;
        }

        .ship-option-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
        }

        .ship-option-card.featured {
            border-color: var(--jade);
            border-width: 2px;
        }

        .soc-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .soc-icon-wrap.green {
            background: rgba(0, 184, 148, 0.12);
        }

        .soc-icon-wrap.amber {
            background: rgba(253, 203, 110, 0.2);
        }

        .soc-price {
            font-family: var(--f-d);
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.04em;
            line-height: 1;
        }

        .soc-price small {
            font-size: 1rem;
            font-weight: 500;
            color: var(--muted);
        }

        .soc-title {
            font-family: var(--f-d);
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
        }

        .soc-desc {
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.6;
        }

        .soc-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(0, 184, 148, 0.12);
            color: var(--jade);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
            letter-spacing: 0.04em;
            width: fit-content;
        }

        /* ── Content ── */
        .ship-content {
            max-width: 900px;
            margin: 60px auto;
            padding: 0 20px 80px;
        }

        .content-section {
            margin-bottom: 56px;
        }

        .cs-heading {
            font-family: var(--f-d);
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cs-heading::after {
            content: '';
            flex: 1;
            height: 2px;
            background: var(--border);
            margin-left: 8px;
        }

        .cs-sub {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 24px;
        }

        /* ── Timeline Table ── */
        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }

        .timeline-table thead tr {
            background: var(--ink);
        }

        .timeline-table th {
            padding: 14px 20px;
            text-align: left;
            font-family: var(--f-d);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.75);
        }

        .timeline-table td {
            padding: 14px 20px;
            font-size: 14px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .timeline-table tbody tr:last-child td {
            border-bottom: none;
        }

        .timeline-table tbody tr:hover {
            background: var(--surface);
        }

        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(0, 184, 148, 0.1);
            color: var(--jade);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
        }

        .time-badge.na {
            background: rgba(139, 134, 128, 0.1);
            color: var(--muted);
        }

        /* ── How it works steps ── */
        .steps-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        @media (min-width: 600px) {
            .steps-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (min-width: 900px) {
            .steps-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .step-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px 20px;
            position: relative;
            overflow: hidden;
        }

        .step-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--coral), var(--jade));
        }

        .step-num {
            font-family: var(--f-d);
            font-size: 3rem;
            font-weight: 800;
            color: var(--border);
            line-height: 1;
            margin-bottom: 12px;
            position: absolute;
            top: 10px;
            right: 16px;
        }

        .step-icon {
            font-size: 1.8rem;
            margin-bottom: 12px;
        }

        .step-title {
            font-family: var(--f-d);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .step-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.65;
        }

        /* ── Info alert ── */
        .info-box {
            background: #fff;
            border: 1px solid var(--border);
            border-left: 4px solid var(--jade);
            border-radius: 12px;
            padding: 20px 24px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .info-box-icon {
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .info-box-text {
            font-size: 14px;
            color: var(--text);
            line-height: 1.7;
        }

        .info-box-text a {
            color: var(--coral);
            font-weight: 600;
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <!-- ── Hero ── -->
    <section class="ship-hero">
        <div class="ship-hero-inner">
            <div class="ship-hero-eyebrow">🚚 Shipping Policy</div>
            <h1>Fast. Safe. <span>Everywhere</span>.</h1>
            <p class="ship-hero-sub">We deliver all across India — from metro cities to remote pin codes. Here's everything you need to know.</p>
        </div>
    </section>

    <!-- ── Shipping Options ── -->
    <div class="ship-options">
        <div class="ship-option-card featured">
            <div class="soc-icon-wrap green">🎁</div>
            <div class="soc-price">Free <small>shipping</small></div>
            <div class="soc-title">Prepaid Orders</div>
            <p class="soc-desc">Pay online and your order ships free — across India, no minimum order required.</p>
            <div class="soc-tag">✓ Always free · No minimum</div>
        </div>
        <div class="ship-option-card">
            <div class="soc-icon-wrap amber">💵</div>
            <div class="soc-price">₹60 <small>handling</small></div>
            <div class="soc-title">Cash on Delivery</div>
            <p class="soc-desc">COD is available across major pin codes. A ₹60 handling charge applies to cover the additional processing.</p>
            <div class="soc-tag" style="background:rgba(253,203,110,0.2);color:#b7860a;">⚡ Available at checkout</div>
        </div>
    </div>

    <!-- ── Main Content ── -->
    <div class="ship-content">

        <!-- Delivery Timelines -->
        <div class="content-section">
            <h2 class="cs-heading">Delivery Timelines</h2>
            <p class="cs-sub">Estimated delivery times by region — business days only, excluding Sundays & holidays.</p>

            <table class="timeline-table">
                <thead>
                    <tr>
                        <th>Region</th>
                        <th>Standard Delivery</th>
                        <th>Express Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Metro cities</strong> — Mumbai, Delhi, Bangalore, Chennai, Hyderabad</td>
                        <td><span class="time-badge">🕐 3–5 days</span></td>
                        <td><span class="time-badge">⚡ 1–2 days</span></td>
                    </tr>
                    <tr>
                        <td><strong>Tier 2 & Tier 3 cities</strong></td>
                        <td><span class="time-badge">🕐 5–7 days</span></td>
                        <td><span class="time-badge">⚡ 2–3 days</span></td>
                    </tr>
                    <tr>
                        <td><strong>Remote & rural areas</strong></td>
                        <td><span class="time-badge">🕐 7–10 days</span></td>
                        <td><span class="time-badge na">Not available</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- How it works -->
        <div class="content-section">
            <h2 class="cs-heading">How Your Order Gets to You</h2>
            <p class="cs-sub">From click to doorstep in 4 simple steps.</p>

            <div class="steps-grid">
                <div class="step-card">
                    <span class="step-num">01</span>
                    <div class="step-icon">🛒</div>
                    <div class="step-title">You Place the Order</div>
                    <p class="step-desc">Choose your items, select payment, and confirm. You'll get an immediate email acknowledgement.</p>
                </div>
                <div class="step-card">
                    <span class="step-num">02</span>
                    <div class="step-icon">📦</div>
                    <div class="step-title">We Pack & Dispatch</div>
                    <p class="step-desc">Orders placed before 2 PM IST are processed within 1–2 business days and handed to our courier.</p>
                </div>
                <div class="step-card">
                    <span class="step-num">03</span>
                    <div class="step-icon">🚚</div>
                    <div class="step-title">In Transit</div>
                    <p class="step-desc">You'll receive SMS & email updates with a tracking number. Track live from your account dashboard.</p>
                </div>
                <div class="step-card">
                    <span class="step-num">04</span>
                    <div class="step-icon">✅</div>
                    <div class="step-title">Delivered!</div>
                    <p class="step-desc">Your order arrives at your door. Inspect before accepting COD orders if you wish.</p>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="content-section">
            <h2 class="cs-heading">Important Notes</h2>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div class="info-box">
                    <span class="info-box-icon">⏰</span>
                    <div class="info-box-text">
                        Orders are processed within <strong>1–2 business days</strong>. Orders placed after 2 PM IST will be processed the next business day. We do not ship on Sundays and national holidays.
                    </div>
                </div>
                <div class="info-box" style="border-left-color:var(--coral);">
                    <span class="info-box-icon">⚠️</span>
                    <div class="info-box-text">
                        If your order arrives damaged or hasn't been received within the expected timeframe, please <a href="contact.php">contact us</a> immediately. We'll resolve it within 24 hours.
                    </div>
                </div>
                <div class="info-box">
                    <span class="info-box-icon">📱</span>
                    <div class="info-box-text">
                        You can track your shipment anytime via your <a href="dashboard.php">account dashboard</a> or directly on the courier's website using your tracking number.
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>

</html>