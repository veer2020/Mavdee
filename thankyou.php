<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

$orderNumber = '';
if (isset($_GET['order'])) {
    $raw = $_GET['order'];
    if (preg_match('/^ORD-[A-Z0-9]+$/i', $raw)) {
        $orderNumber = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <title>Order Confirmed — <?= htmlspecialchars(SITE_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .ty-page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background blobs */
        .ty-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.4;
        }

        .ty-blob-1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0, 184, 148, 0.35), transparent);
            top: -100px;
            right: -80px;
            animation: blobFloat 8s ease-in-out infinite;
        }

        .ty-blob-2 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 71, 87, 0.2), transparent);
            bottom: -80px;
            left: -60px;
            animation: blobFloat 10s 2s ease-in-out infinite reverse;
        }

        @keyframes blobFloat {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(20px, -20px) scale(1.05);
            }

            66% {
                transform: translate(-15px, 15px) scale(0.95);
            }
        }

        /* Card */
        .ty-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            z-index: 1;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            animation: cardReveal 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes cardReveal {
            from {
                opacity: 0;
                transform: scale(0.88) translateY(24px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Success circle */
        .ty-circle-wrap {
            position: relative;
            width: 88px;
            height: 88px;
            margin: 0 auto 24px;
        }

        .ty-circle {
            width: 88px;
            height: 88px;
            background: var(--jade);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: circlePop 0.5s 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes circlePop {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .ty-circle::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px solid var(--jade);
            opacity: 0;
            animation: ringPulse 0.6s 0.7s ease-out forwards;
        }

        @keyframes ringPulse {
            0% {
                transform: scale(1);
                opacity: 0.6;
            }

            100% {
                transform: scale(1.4);
                opacity: 0;
            }
        }

        .ty-check {
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: drawCheck 0.4s 0.6s ease forwards;
        }

        @keyframes drawCheck {
            to {
                stroke-dashoffset: 0;
            }
        }

        /* Text */
        .ty-heading {
            font-family: var(--f-d);
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-bottom: 8px;
            animation: fadeUp 0.5s 0.4s ease both;
        }

        .ty-sub {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 6px;
            animation: fadeUp 0.5s 0.5s ease both;
        }

        /* Order badge */
        .ty-badge-wrap {
            animation: fadeUp 0.5s 0.6s ease both;
        }

        .ty-order-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            background: var(--surface);
            border: 1.5px dashed var(--border);
            padding: 10px 20px;
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: 0.04em;
        }

        /* Delivery estimate */
        .ty-delivery {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 184, 148, 0.1);
            color: var(--jade);
            font-size: 13px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            margin-bottom: 32px;
            animation: fadeUp 0.5s 0.65s ease both;
        }

        /* Info chips */
        .ty-chips {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 28px;
            animation: fadeUp 0.5s 0.7s ease both;
        }

        .ty-chip {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
        }

        /* Actions */
        .ty-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            animation: fadeUp 0.5s 0.75s ease both;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 52px;
            background: var(--coral);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--coral-d);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.35);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 50px;
            background: transparent;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
            text-decoration: none;
        }

        .btn-ghost:hover {
            border-color: var(--coral);
            color: var(--coral);
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Confetti canvas */
        #confettiCanvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 999;
        }

        @media (max-width: 480px) {
            .ty-card {
                padding: 36px 20px;
                border-radius: 16px;
            }

            .ty-heading {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <canvas id="confettiCanvas"></canvas>

    <div class="ty-page">
        <div class="ty-blob ty-blob-1"></div>
        <div class="ty-blob ty-blob-2"></div>

        <div class="ty-card">
            <!-- Animated checkmark -->
            <div class="ty-circle-wrap">
                <div class="ty-circle">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline class="ty-check" points="20 6 9 17 4 12" />
                    </svg>
                </div>
            </div>

            <h1 class="ty-heading">Order Confirmed! 🎉</h1>
            <p class="ty-sub">Your order has been placed and is being processed.</p>
            <p class="ty-sub">A confirmation email is on its way to your inbox.</p>

            <div class="ty-badge-wrap">
                <?php if ($orderNumber !== ''): ?>
                    <div class="ty-order-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <rect x="2" y="7" width="20" height="14" rx="2" />
                            <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                        </svg>
                        Order #<?= $orderNumber ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ty-delivery">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="1" y="3" width="15" height="13" />
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                    <circle cx="5.5" cy="18.5" r="2.5" />
                    <circle cx="18.5" cy="18.5" r="2.5" />
                </svg>
                Estimated delivery in 4–7 business days
            </div>

            <div class="ty-chips">
                <div class="ty-chip">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                    </svg>
                    Live tracking available
                </div>
                <div class="ty-chip">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                    SMS updates enabled
                </div>
                <div class="ty-chip">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="9 11 12 14 22 4" />
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                    </svg>
                    7-day easy returns
                </div>
            </div>

            <div class="ty-actions">
                <a href="/shop.php" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                    </svg>
                    Continue Shopping
                </a>
                <a href="/dashboard.php" class="btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                    </svg>
                    View My Orders
                </a>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script>
        // Lightweight confetti
        (function() {
            const canvas = document.getElementById('confettiCanvas');
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const colors = ['#FF4757', '#00b894', '#fdcb6e', '#0984e3', '#a29bfe', '#fd79a8'];
            const pieces = Array.from({
                length: 80
            }, () => ({
                x: Math.random() * canvas.width,
                y: Math.random() * -canvas.height,
                r: Math.random() * 6 + 4,
                d: Math.random() * 60 + 20,
                color: colors[Math.floor(Math.random() * colors.length)],
                tilt: Math.random() * 10 - 5,
                tiltSpeed: Math.random() * 0.1 + 0.05,
                speed: Math.random() * 2 + 1,
                angle: 0,
            }));

            let frame = 0;
            let stopped = false;

            function draw() {
                if (stopped) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                pieces.forEach(p => {
                    ctx.beginPath();
                    ctx.lineWidth = p.r / 2;
                    ctx.strokeStyle = p.color;
                    ctx.moveTo(p.x + p.tilt + p.r / 4, p.y);
                    ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 4);
                    ctx.stroke();
                    p.y += p.speed;
                    p.angle += p.tiltSpeed;
                    p.tilt = Math.sin(p.angle) * 12;
                    if (p.y > canvas.height) {
                        p.y = -20;
                        p.x = Math.random() * canvas.width;
                    }
                });
                frame++;
                if (frame < 180) requestAnimationFrame(draw);
                else {
                    stopped = true;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            }
            setTimeout(draw, 300);
        })();
    </script>
</body>

</html>