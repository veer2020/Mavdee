<?php

declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

$csrfToken  = csrf_token();
$returnMsg  = '';
$returnErr  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    csrf_check();
    if (!isLoggedIn()) {
        $returnErr = 'Please log in to submit a return request.';
    } else {
        $orderId  = (int)($_POST['order_id'] ?? 0);
        $reason   = sanitizeInput($_POST['reason'] ?? '');
        $desc     = sanitizeInput($_POST['description'] ?? '');
        $photoUrl = '';

        $ownCheck = db()->prepare("SELECT id FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
        $ownCheck->execute([$orderId, getUserId()]);

        if ($orderId <= 0 || !$ownCheck->fetch()) {
            $returnErr = 'Invalid order selected.';
        } elseif ($reason === '') {
            $returnErr = 'Please select a reason.';
        } else {
            if (!empty($_FILES['photo']['tmp_name'])) {
                $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                finfo_close($finfo);
                if (in_array($mime, $allowedMime, true) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
                    $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $filename = 'return_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
                    $dest     = __DIR__ . '/uploads/returns/' . $filename;
                    $uploadDir = dirname($dest);
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $returnErr = 'Could not create upload directory.';
                    } elseif (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        $photoUrl = '/uploads/returns/' . $filename;
                    }
                }
            }
            if (!$returnErr) {
                try {
                    db()->prepare("INSERT INTO return_requests (order_id, customer_id, reason, description, photo_url) VALUES (?,?,?,?,?)")
                        ->execute([$orderId, getUserId(), $reason, $desc ?: null, $photoUrl ?: null]);
                    $returnMsg = "Your return request has been submitted! We'll get back to you within 24–48 hours.";
                } catch (Throwable $e) {
                    $returnErr = 'Could not submit request. Please try again or contact support.';
                }
            }
        }
    }
}

$customerOrders = [];
if (isLoggedIn()) {
    try {
        $stmt = db()->prepare("SELECT id, order_number, created_at, total, status FROM orders WHERE customer_id = ? AND status = 'delivered' ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([getUserId()]);
        $customerOrders = $stmt->fetchAll();
    } catch (Throwable) {
    }
}

$myReturns = [];
if (isLoggedIn()) {
    try {
        $stmt = db()->prepare("SELECT rr.*, o.order_number FROM return_requests rr LEFT JOIN orders o ON o.id = rr.order_id WHERE rr.customer_id = ? ORDER BY rr.created_at DESC LIMIT 10");
        $stmt->execute([getUserId()]);
        $myReturns = $stmt->fetchAll();
    } catch (Throwable) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns & Exchanges — <?= h(SITE_NAME) ?></title>
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
        .ret-hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #0f0f0f 100%);
            color: #fff;
            padding: 100px 24px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .ret-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 70% 60% at 50% 100%, rgba(255, 71, 87, 0.18) 0%, transparent 60%);
        }

        .ret-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin: 0 auto;
        }

        .ret-hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 71, 87, 0.12);
            border: 1px solid rgba(255, 71, 87, 0.25);
            color: #ff8090;
            border-radius: 99px;
            padding: 5px 14px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .ret-hero h1 {
            font-family: var(--f-d);
            font-size: clamp(2rem, 5vw, 3.4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.05;
            margin-bottom: 14px;
        }

        .ret-hero-sub {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.7;
        }

        /* ── Policy Highlight Cards ── */
        .policy-highlights {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            max-width: 900px;
            margin: -36px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        @media (min-width: 640px) {
            .policy-highlights {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .ph-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px 20px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .ph-icon {
            font-size: 2rem;
        }

        .ph-title {
            font-family: var(--f-d);
            font-size: 1rem;
            font-weight: 700;
            color: var(--ink);
        }

        .ph-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.55;
        }

        /* ── Main Layout ── */
        .ret-content {
            max-width: 960px;
            margin: 56px auto;
            padding: 0 20px 80px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
        }

        @media (min-width: 900px) {
            .ret-content {
                grid-template-columns: 1.1fr 0.9fr;
            }
        }

        /* ── Section headings ── */
        .sec-title {
            font-family: var(--f-d);
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }

        /* ── Return Steps ── */
        .ret-steps {
            display: flex;
            flex-direction: column;
        }

        .ret-step {
            display: flex;
            gap: 16px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .ret-step:last-child {
            border-bottom: none;
        }

        .ret-step-num {
            width: 36px;
            height: 36px;
            min-width: 36px;
            background: var(--coral);
            color: #fff;
            border-radius: 50%;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ret-step-body {}

        .ret-step-title {
            font-family: var(--f-d);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 5px;
        }

        .ret-step-desc {
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.65;
        }

        /* ── Conditions ── */
        .conditions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .cond-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
        }

        .cond-card.eligible {
            border-top: 3px solid var(--jade);
        }

        .cond-card.ineligible {
            border-top: 3px solid var(--coral);
        }

        .cond-card-title {
            font-family: var(--f-d);
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .cond-card.eligible .cond-card-title {
            color: var(--jade);
        }

        .cond-card.ineligible .cond-card-title {
            color: var(--coral);
        }

        .cond-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cond-list li {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            gap: 7px;
            align-items: flex-start;
            line-height: 1.5;
        }

        .cond-list li::before {
            content: '';
            flex-shrink: 0;
        }

        .cond-card.eligible .cond-list li::before {
            content: '✓';
            color: var(--jade);
            font-weight: 700;
        }

        .cond-card.ineligible .cond-list li::before {
            content: '✗';
            color: var(--coral);
            font-weight: 700;
        }

        /* ── Form Panel ── */
        .ret-form-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .f-group {
            margin-bottom: 16px;
        }

        .f-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .f-input,
        .f-select,
        .f-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: var(--f-b);
            color: var(--ink);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .f-input:focus,
        .f-select:focus,
        .f-textarea:focus {
            border-color: var(--coral);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.10);
        }

        .f-textarea {
            resize: vertical;
            min-height: 90px;
        }

        .btn-submit {
            width: 100%;
            height: 50px;
            background: var(--coral);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            margin-top: 4px;
        }

        .btn-submit:hover {
            background: var(--coral-d);
            transform: translateY(-1px);
        }

        .login-cta {
            text-align: center;
            padding: 24px;
            background: var(--surface);
            border-radius: 12px;
            font-size: 14px;
            color: var(--muted);
        }

        .login-cta a {
            color: var(--coral);
            font-weight: 700;
        }

        /* ── My Returns ── */
        .my-returns {
            margin-top: 40px;
        }

        .ret-item {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ret-item-left {
            flex: 1;
        }

        .ret-item-order {
            font-weight: 700;
            color: var(--ink);
            font-size: 14px;
            margin-bottom: 3px;
        }

        .ret-item-reason {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 3px;
        }

        .ret-item-date {
            font-size: 12px;
            color: var(--muted);
        }

        .ret-item-note {
            font-size: 13px;
            background: var(--surface);
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 8px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
        }

        .sp-requested {
            background: #fff8e1;
            color: #b7860a;
        }

        .sp-approved {
            background: #f0faf7;
            color: #05684e;
        }

        .sp-completed {
            background: #f0faf7;
            color: #05684e;
        }

        .sp-rejected {
            background: var(--coral-xl);
            color: var(--coral-d);
        }

        .sp-default {
            background: var(--surface);
            color: var(--muted);
        }

        /* Alert */
        .alert-s {
            background: #f0faf7;
            color: #05684e;
            border: 1px solid #b7e4d5;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-e {
            background: var(--coral-xl);
            color: var(--coral-d);
            border: 1px solid #ffbfc6;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <!-- ── Hero ── -->
    <section class="ret-hero">
        <div class="ret-hero-inner">
            <div class="ret-hero-pill">↩ Returns & Exchanges</div>
            <h1>We'll Make It Right</h1>
            <p class="ret-hero-sub">We want you to love every purchase. If something doesn't fit, we'll sort it — fast.</p>
        </div>
    </section>

    <!-- ── Policy Highlights ── -->
    <div class="policy-highlights">
        <div class="ph-card">
            <div class="ph-icon">📅</div>
            <div class="ph-title">7-Day Window</div>
            <p class="ph-desc">Return any eligible item within 7 days of delivery. Simple, no hassle.</p>
        </div>
        <div class="ph-card">
            <div class="ph-icon">🔄</div>
            <div class="ph-title">Free Exchanges</div>
            <p class="ph-desc">Size or colour not right? We'll swap it free for you, subject to stock.</p>
        </div>
        <div class="ph-card">
            <div class="ph-icon">💰</div>
            <div class="ph-title">Fast Refunds</div>
            <p class="ph-desc">Refunds processed within 5–7 business days to your original payment method.</p>
        </div>
    </div>

    <!-- ── Main Content ── -->
    <div class="ret-content">

        <!-- Left: Policy & Steps -->
        <div>
            <div style="margin-bottom:40px;">
                <h2 class="sec-title">How to Return</h2>
                <div class="ret-steps">
                    <div class="ret-step">
                        <div class="ret-step-num">1</div>
                        <div class="ret-step-body">
                            <div class="ret-step-title">Submit a Request</div>
                            <p class="ret-step-desc">Use the form on this page or email us at hello@<?= strtolower(SITE_NAME) ?>.com with your order number and reason within 7 days of delivery.</p>
                        </div>
                    </div>
                    <div class="ret-step">
                        <div class="ret-step-num">2</div>
                        <div class="ret-step-body">
                            <div class="ret-step-title">We Send a Return Label</div>
                            <p class="ret-step-desc">For eligible returns, receive a prepaid return label within 24 hours. COD orders may have the return cost deducted from the refund.</p>
                        </div>
                    </div>
                    <div class="ret-step">
                        <div class="ret-step-num">3</div>
                        <div class="ret-step-body">
                            <div class="ret-step-title">Pack & Drop Off</div>
                            <p class="ret-step-desc">Pack the item securely in its original packaging with all tags. Drop it at the nearest courier point or schedule a home pickup.</p>
                        </div>
                    </div>
                    <div class="ret-step">
                        <div class="ret-step-num">4</div>
                        <div class="ret-step-body">
                            <div class="ret-step-title">Refund or Exchange</div>
                            <p class="ret-step-desc">After we receive & inspect (1–2 days), your refund hits your account in 5–7 business days — or your exchange ships within 3–5 days.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="sec-title">What Can Be Returned?</h2>
                <div class="conditions-grid">
                    <div class="cond-card eligible">
                        <div class="cond-card-title">✓ Eligible</div>
                        <ul class="cond-list">
                            <li>Original condition, tags attached</li>
                            <li>Reported within 7 days of delivery</li>
                            <li>Damaged or incorrect items</li>
                            <li>Manufacturing defects</li>
                        </ul>
                    </div>
                    <div class="cond-card ineligible">
                        <div class="cond-card-title">✗ Not Eligible</div>
                        <ul class="cond-list">
                            <li>Worn, washed, or altered items</li>
                            <li>Sale / clearance items</li>
                            <li>Missing tags or packaging</li>
                            <li>Innerwear & accessories (hygiene)</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- My Returns -->
            <?php if (!empty($myReturns)): ?>
                <div class="my-returns">
                    <h2 class="sec-title">My Return Requests</h2>
                    <?php
                    $spClasses = ['requested' => 'sp-requested', 'approved' => 'sp-approved', 'completed' => 'sp-completed', 'rejected' => 'sp-rejected'];
                    foreach ($myReturns as $r):
                        $sp = $spClasses[$r['status']] ?? 'sp-default';
                    ?>
                        <div class="ret-item">
                            <div class="ret-item-left">
                                <div class="ret-item-order">Order #<?= h($r['order_number'] ?? $r['order_id']) ?></div>
                                <div class="ret-item-reason"><?= h($r['reason']) ?></div>
                                <div class="ret-item-date">Submitted <?= h(date('d M Y', strtotime($r['created_at']))) ?></div>
                                <?php if (!empty($r['admin_note'])): ?>
                                    <div class="ret-item-note"><strong>Update:</strong> <?= h($r['admin_note']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="status-pill <?= $sp ?>"><?= ucwords(str_replace('_', ' ', h($r['status']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Form Panel -->
        <div>
            <div class="ret-form-panel" id="return-request-section">
                <h3 class="sec-title" style="font-size:1.1rem;">Submit Return Request</h3>

                <?php if ($returnMsg): ?>
                    <div class="alert-s">✅ <?= h($returnMsg) ?></div>
                <?php endif; ?>
                <?php if ($returnErr): ?>
                    <div class="alert-e">⚠️ <?= h($returnErr) ?></div>
                <?php endif; ?>

                <?php if (!isLoggedIn()): ?>
                    <div class="login-cta">
                        <div style="font-size:2rem;margin-bottom:8px;">🔒</div>
                        <p>Please <a href="/login.php?next=<?= urlencode('/returns.php') ?>">log in</a> to submit a return request online.</p>
                    </div>
                <?php elseif (empty($customerOrders)): ?>
                    <div class="login-cta">
                        <div style="font-size:2rem;margin-bottom:8px;">📦</div>
                        <p style="color:var(--muted);">No delivered orders found. Return requests can only be made for orders delivered within the last 7 days.</p>
                    </div>
                <?php else: ?>
                    <form method="post" action="#return-request-section" enctype="multipart/form-data">
                        <input type="hidden" name="submit_return" value="1">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                        <div class="f-group">
                            <label class="f-label">Select Order *</label>
                            <select name="order_id" class="f-select" required>
                                <option value="">— Choose an order —</option>
                                <?php foreach ($customerOrders as $ord): ?>
                                    <option value="<?= (int)$ord['id'] ?>"
                                        <?= !empty($_POST['order_id']) && (int)$_POST['order_id'] === (int)$ord['id'] ? 'selected' : '' ?>>
                                        #<?= h($ord['order_number']) ?> · <?= h(date('d M Y', strtotime($ord['created_at']))) ?> · <?= CURRENCY . number_format($ord['total']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f-group">
                            <label class="f-label">Reason for Return *</label>
                            <select name="reason" class="f-select" required>
                                <option value="">— Select a reason —</option>
                                <option value="Size issue">Size issue</option>
                                <option value="Damaged / defective">Damaged / defective</option>
                                <option value="Wrong item received">Wrong item received</option>
                                <option value="Quality not as expected">Quality not as expected</option>
                                <option value="Changed my mind">Changed my mind</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="f-group">
                            <label class="f-label">Additional Details <span style="font-weight:400;text-transform:none;font-size:11px;">(optional)</span></label>
                            <textarea name="description" class="f-textarea" maxlength="500" placeholder="Describe the issue in more detail…"></textarea>
                        </div>

                        <div class="f-group">
                            <label class="f-label">Upload Photo <span style="font-weight:400;text-transform:none;font-size:11px;">(optional, max 5 MB)</span></label>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="f-input" style="height:auto;padding:10px;">
                        </div>

                        <button type="submit" class="btn-submit">Submit Return Request</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>

</html>