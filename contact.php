<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

// Load contact settings from the database (configurable via Admin → Settings)
$contactPhone   = getSetting('contact_phone', '');
$contactWhatsapp = getSetting('contact_whatsapp', $contactPhone); // falls back to phone if not set separately

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // In a real deployment, send an email here via includes/email.php
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | <?= h(SITE_NAME) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        :root {
            --cream: #fcfafc;
            --parchment: #f3efea;
            --blush: #f4e1e6;
            --gold: #dda74f;
            --ink: #14100d;
            --muted: #8a7b6f;
            --border: #e8e0d5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Jost', sans-serif;
            background: var(--cream);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        header {
            padding: 24px 40px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            background: rgba(252, 250, 252, 0.9);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-sizing: border-box;
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .nav-links {
            display: flex;
            gap: 32px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.1em;
        }

        .nav-links a:hover {
            color: var(--gold);
        }

        .header-icons {
            justify-self: end;
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .header-icons a,
        .header-icons button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink);
            display: flex;
            align-items: center;
        }

        .cart-icon-wrap {
            position: relative;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: var(--gold);
            color: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 99px;
            font-weight: 600;
        }

        .page-hero {
            text-align: center;
            padding: 140px 20px 60px;
            background: linear-gradient(180deg, var(--blush), var(--cream));
        }

        .page-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 500;
            margin: 0 0 16px;
        }

        .page-hero p {
            color: var(--muted);
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.8;
        }

        .contact-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            max-width: 1000px;
            margin: 0 auto;
            padding: 80px 40px;
        }

        .contact-info h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            margin: 0 0 20px;
        }

        .contact-info p {
            color: var(--muted);
            line-height: 1.8;
            margin: 0 0 24px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }

        .info-icon {
            font-size: 1.3rem;
            margin-top: 2px;
        }

        .info-text strong {
            display: block;
            margin-bottom: 4px;
        }

        .info-text span {
            color: var(--muted);
            font-size: 15px;
        }

        .contact-form {
            background: var(--parchment);
            border-radius: 20px;
            padding: 40px;
        }

        .contact-form h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            margin: 0 0 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            color: var(--muted);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            font-family: 'Jost', sans-serif;
            font-size: 15px;
            background: #fff;
            color: var(--ink);
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--gold);
        }

        .form-group textarea {
            height: 140px;
            resize: vertical;
        }

        .btn-submit {
            background: var(--ink);
            color: #fff;
            border: none;
            border-radius: 99px;
            padding: 14px 36px;
            font-family: 'Jost', sans-serif;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: background 0.2s;
            width: 100%;
        }

        .btn-submit:hover {
            background: var(--gold);
        }

        .success-msg {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .error-msg {
            background: #fdecea;
            color: #c62828;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 20px;
            font-size: 15px;
        }

        /* Cart Drawer */
        .cart-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 16, 13, 0.5);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 2000;
        }

        .cart-drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: 420px;
            max-width: 100%;
            height: 100%;
            background: var(--cream);
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2001;
            display: flex;
            flex-direction: column;
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.1);
        }

        body.cart-open {
            overflow: hidden;
        }

        body.cart-open .cart-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        body.cart-open .cart-drawer {
            transform: translateX(0);
        }

        .cart-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            margin: 0;
        }

        .cart-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--muted);
        }

        .cart-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
        }

        .cart-footer {
            padding: 24px;
            border-top: 1px solid var(--border);
            background: #fff;
        }

        .cart-item {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .cart-item-img {
            width: 80px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-title {
            font-family: 'Playfair Display', serif;
            font-size: 15px;
            margin: 0 0 6px;
        }

        .cart-item-meta {
            color: var(--muted);
            font-size: 14px;
            margin: 0 0 8px;
        }

        .cart-qty-ctrl {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-qty-ctrl button {
            background: none;
            border: 1px solid var(--border);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .cart-savings {
            text-align: center;
            color: var(--gold);
            font-size: 15px;
            margin-bottom: 16px;
        }

        .btn-checkout {
            width: 100%;
            background: var(--ink);
            color: #fff;
            border: none;
            border-radius: 99px;
            padding: 16px;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
        }

        footer {
            background: #fff;
            padding: 60px 40px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 40px;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .footer-col a {
            color: var(--muted);
            font-size: 15px;
            transition: color 0.2s;
        }

        .footer-col a:hover {
            color: var(--gold);
        }

        @media (max-width: 1023px) {
            header {
                grid-template-columns: 1fr;
                padding: 16px 20px;
            }

            .logo {
                text-align: center;
            }

            .header-icons,
            .nav-links {
                display: none;
            }

            .contact-wrap {
                grid-template-columns: 1fr;
                padding: 40px 20px;
                gap: 32px;
            }

            .page-hero h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <div class="page-hero">
        <h1>Get in Touch</h1>
        <p>We'd love to hear from you. Whether it's a question about sizing, an order update, or just a hello — we're here.</p>
    </div>

    <div class="contact-wrap">
        <div class="contact-info">
            <h2>We're here to help</h2>
            <p>Reach out via the form, email, or WhatsApp. Our team responds within 24 hours on business days.</p>
            <div class="info-row">
                <span class="info-icon">📧</span>
                <div class="info-text">
                    <strong>Email</strong>
                    <span>hello@mavdeefashion.com</span>
                </div>
            </div>
            <div class="info-row">
                <span class="info-icon">💬</span>
                <div class="info-text">
                    <strong>WhatsApp</strong>
                    <?php if ($contactWhatsapp !== ''): ?>
                        <span><a href="https://wa.me/<?= h(preg_replace('/\D/', '', $contactWhatsapp)) ?>" style="color:var(--gold);">Chat with us on WhatsApp</a></span>
                    <?php else: ?>
                        <span style="color:var(--gold);">WhatsApp support coming soon</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <span class="info-icon">🕐</span>
                <div class="info-text">
                    <strong>Business Hours</strong>
                    <span>Mon–Sat, 10 AM – 7 PM IST</span>
                </div>
            </div>
        </div>
        <div class="contact-form">
            <h2>Send us a message</h2>
            <?php if ($sent): ?>
                <div class="success-msg">✓ Thank you! We'll get back to you within 24 hours.</div>
            <?php elseif ($error !== ''): ?>
                <div class="error-msg"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if (!$sent): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" placeholder="Neha Gupta" value="<?= h($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="priya@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="How can we help you?" required><?= h($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>

</html>