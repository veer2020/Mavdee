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
    <title>Privacy Policy | <?= h(SITE_NAME) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --cream: #fcfafc; --parchment: #f3efea; --blush: #f4e1e6; --gold: #dda74f; --ink: #14100d; --muted: #8a7b6f; --border: #e8e0d5; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Jost', sans-serif; background: var(--cream); color: var(--ink); -webkit-font-smoothing: antialiased; }
        a { text-decoration: none; color: inherit; }
        header { padding: 24px 40px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; background: rgba(252,250,252,0.9); backdrop-filter: blur(10px); position: fixed; width: 100%; top: 0; z-index: 1000; box-sizing: border-box; }
        .logo { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 600; letter-spacing: 0.05em; }
        .nav-links { display: flex; gap: 32px; font-weight: 500; text-transform: uppercase; font-size: 14px; letter-spacing: 0.1em; }
        .nav-links a:hover { color: var(--gold); }
        .header-icons { justify-self: end; display: flex; gap: 24px; align-items: center; }
        .header-icons a, .header-icons button { background: none; border: none; cursor: pointer; color: var(--ink); display: flex; align-items: center; }
        .cart-icon-wrap { position: relative; }
        .cart-badge { position: absolute; top: -8px; right: -10px; background: var(--gold); color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 99px; font-weight: 600; }

        .page-hero { text-align: center; padding: 140px 20px 60px; background: linear-gradient(180deg, var(--blush), var(--cream)); }
        .page-hero h1 { font-family: 'Playfair Display', serif; font-size: 3rem; font-weight: 500; margin: 0 0 16px; }
        .page-hero p { color: var(--muted); max-width: 560px; margin: 0 auto; line-height: 1.8; }

        .content-section { max-width: 860px; margin: 0 auto; padding: 60px 40px 80px; }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 500; margin: 40px 0 16px; color: var(--ink); }
        .section-title:first-child { margin-top: 0; }
        p, li { line-height: 1.9; color: var(--muted); margin: 0 0 12px; }
        ul { padding-left: 20px; margin: 0 0 20px; }
        .updated { color: var(--muted); font-size: 14px; margin-bottom: 40px; }

        /* Cart Drawer */
        .cart-overlay { position: fixed; inset: 0; background: rgba(20,16,13,0.5); backdrop-filter: blur(4px); opacity: 0; pointer-events: none; transition: opacity 0.3s; z-index: 2000; }
        .cart-drawer { position: fixed; top: 0; right: 0; width: 420px; max-width: 100%; height: 100%; background: var(--cream); transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4,0,0.2,1); z-index: 2001; display: flex; flex-direction: column; box-shadow: -10px 0 40px rgba(0,0,0,0.1); }
        body.cart-open { overflow: hidden; }
        body.cart-open .cart-overlay { opacity: 1; pointer-events: auto; }
        body.cart-open .cart-drawer { transform: translateX(0); }
        .cart-header { padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .cart-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; margin: 0; }
        .cart-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted); }
        .cart-body { padding: 24px; flex: 1; overflow-y: auto; }
        .cart-footer { padding: 24px; border-top: 1px solid var(--border); background: #fff; }
        .cart-total-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 1.1rem; font-weight: 600; }
        .cart-savings { text-align: center; color: var(--gold); font-size: 15px; margin-bottom: 16px; }
        .btn-checkout { width: 100%; background: var(--ink); color: #fff; border: none; border-radius: 99px; padding: 16px; font-size: 15px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; }

        footer { background: #fff; padding: 60px 40px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 40px; }
        .footer-col { display: flex; flex-direction: column; gap: 12px; }
        .footer-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin-bottom: 8px; }
        .footer-col a { color: var(--muted); font-size: 15px; transition: color 0.2s; }
        .footer-col a:hover { color: var(--gold); }

        @media (max-width: 1023px) {
            header { grid-template-columns: 1fr; padding: 16px 20px; }
            .logo { text-align: center; }
            .header-icons, .nav-links { display: none; }
            .page-hero h1 { font-size: 2.2rem; }
            .content-section { padding: 40px 20px 60px; }
        }
    </style>
    <link rel="stylesheet" href="/assets/css/global.css">
</head>
<body>
<?php require __DIR__ . '/includes/header.php'; ?>

    <div class="page-hero">
        <h1>Privacy Policy</h1>
        <p>Your privacy matters to us. This policy explains how we collect, use, and protect your information.</p>
    </div>

    <div class="content-section">
        <p class="updated">Last updated: March 2026</p>

        <h2 class="section-title">1. Information We Collect</h2>
        <p>When you use <?= h(SITE_NAME) ?>, we may collect the following information:</p>
        <ul>
            <li><strong>Account information:</strong> Name, email address, and password when you register.</li>
            <li><strong>Order information:</strong> Shipping address, phone number, and payment details when you place an order.</li>
            <li><strong>Usage data:</strong> Pages visited, products viewed, and actions taken on our website, collected via server logs and cookies.</li>
            <li><strong>Communications:</strong> Messages sent to our support team.</li>
        </ul>

        <h2 class="section-title">2. How We Use Your Information</h2>
        <p>We use your information to:</p>
        <ul>
            <li>Process and fulfil your orders and send you order updates.</li>
            <li>Create and manage your account.</li>
            <li>Send transactional emails (order confirmation, shipping updates).</li>
            <li>Respond to your enquiries and provide customer support.</li>
            <li>Improve our website, products, and services.</li>
            <li>Send you promotional emails and offers if you have opted in (you may unsubscribe at any time).</li>
        </ul>

        <h2 class="section-title">3. Cookies</h2>
        <p>We use cookies to keep you logged in, remember your cart, and understand how you use our site. You can control cookies through your browser settings. Disabling cookies may affect some functionality of the site.</p>

        <h2 class="section-title">4. Data Sharing</h2>
        <p>We do not sell your personal data. We may share your information with:</p>
        <ul>
            <li><strong>Shipping partners</strong> to deliver your orders.</li>
            <li><strong>Payment processors</strong> (e.g., Razorpay) to securely handle transactions. We never store your full card details.</li>
            <li><strong>Legal authorities</strong> if required by law.</li>
        </ul>

        <h2 class="section-title">5. Data Security</h2>
        <p>We implement appropriate technical and organisational measures to protect your personal information. However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>

        <h2 class="section-title">6. Your Rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Access the personal data we hold about you.</li>
            <li>Request correction of inaccurate data.</li>
            <li>Request deletion of your account and associated data.</li>
            <li>Opt out of marketing communications at any time.</li>
        </ul>
        <p>To exercise any of these rights, please <a href="contact.php" style="color:var(--gold);">contact us</a>.</p>

        <h2 class="section-title">7. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by posting the new policy on this page with an updated date. Your continued use of the site after changes constitutes acceptance of the revised policy.</p>

        <h2 class="section-title">8. Contact Us</h2>
        <p>If you have any questions about this Privacy Policy, please <a href="contact.php" style="color:var(--gold);">contact us</a> at hello@mavdeefashion.com.</p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>
</html>
