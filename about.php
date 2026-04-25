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
    <title>About Us | <?= h(SITE_NAME) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
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
        .page-hero p { color: var(--muted); max-width: 600px; margin: 0 auto; line-height: 1.8; font-size: 1.05rem; }

        .content-section { max-width: 900px; margin: 0 auto; padding: 80px 40px; }
        .section-title { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 500; margin: 0 0 20px; }
        .section-text { color: var(--muted); line-height: 1.9; font-size: 1rem; margin: 0 0 40px; }

        .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; margin-top: 40px; }
        .value-card { background: var(--parchment); border-radius: 16px; padding: 32px; text-align: center; }
        .value-icon { font-size: 2.5rem; margin-bottom: 16px; }
        .value-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin: 0 0 10px; }
        .value-desc { color: var(--muted); font-size: 15px; line-height: 1.7; margin: 0; }

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
        .cart-item { display: flex; gap: 16px; margin-bottom: 24px; }
        .cart-item-img { width: 80px; height: 100px; object-fit: cover; border-radius: 8px; }
        .cart-item-info { flex: 1; }
        .cart-item-title { font-family: 'Playfair Display', serif; font-size: 15px; margin: 0 0 6px; }
        .cart-item-meta { color: var(--muted); font-size: 14px; margin: 0 0 8px; }
        .cart-qty-ctrl { display: flex; align-items: center; gap: 12px; }
        .cart-qty-ctrl button { background: none; border: 1px solid var(--border); width: 28px; height: 28px; border-radius: 50%; cursor: pointer; }
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
            .values-grid { grid-template-columns: 1fr; }
            .content-section { padding: 40px 20px; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/includes/header.php'; ?>

    <div class="page-hero">
        <h1>Our Story</h1>
        <p>Born from a love of colour, craft, and celebration — <?= h(SITE_NAME) ?> dresses women for the moments that matter most.</p>
    </div>

    <div class="content-section">
        <h2 class="section-title">Where Fashion Meets Heritage</h2>
        <p class="section-text">
            <?= h(SITE_NAME) ?> was founded with one simple belief: that every woman deserves to feel extraordinary. We started as a small studio sourcing hand-embroidered fabrics directly from artisans across India — and grew into a destination for premium occasionwear that blends traditional craftsmanship with modern silhouettes.
        </p>
        <p class="section-text">
            Our collections are curated season by season, each piece selected for its fabric quality, finishing, and the quiet confidence it gives the woman who wears it. From festive Kurta sets to fluid evening dresses, every garment is a story of patience and skill.
        </p>

        <h2 class="section-title">Our Values</h2>
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon">🪡</div>
                <h3 class="value-title">Artisan Craftsmanship</h3>
                <p class="value-desc">We partner directly with skilled artisans to preserve traditional embroidery and weaving techniques that have been passed down for generations.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">🌿</div>
                <h3 class="value-title">Conscious Choices</h3>
                <p class="value-desc">We source natural fibres and use responsible packaging wherever possible, because beauty should never come at the planet's expense.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">✨</div>
                <h3 class="value-title">Exceptional Quality</h3>
                <p class="value-desc">Every piece undergoes rigorous quality checks. We stand behind each garment — if it's not perfect, it doesn't leave our studio.</p>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
<?php require __DIR__ . '/includes/bottom-nav.php'; ?>
</body>
</html>