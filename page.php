<?php

declare(strict_types=1);

function h(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function page_url(string $slug): string
{
  return 'page.php?slug=' . rawurlencode($slug);
}

$pages = [
  'about' => [
    'title' => 'About Mavdee',
    'eyebrow' => 'About',
    'intro' => 'Mavdee is built around occasionwear that feels special, wearable, and easy to return to season after season.',
    'sections' => [
      ['heading' => 'Design Point of View', 'body' => 'We focus on festive silhouettes, soft structure, and detail that photographs beautifully without feeling overworked in person.'],
      ['heading' => 'Quality First', 'body' => 'Each collection is planned around fit, finish, and fabric hand-feel so pieces look polished at events and still stay comfortable through long days.'],
      ['heading' => 'Small-Batch Intent', 'body' => 'We prefer tighter edits over endless inventory so the storefront stays curated and the shopping journey feels clear.'],
    ],
  ],
  'lookbook' => [
    'title' => 'Lookbook',
    'eyebrow' => 'Lookbook',
    'intro' => 'Style your wardrobe by mood: wedding guest, festive hosting, intimate celebrations, and elevated everyday dressing.',
    'sections' => [
      ['heading' => 'Occasion Dressing', 'body' => 'Pair embroidered kurtas with lighter jewelry for daytime events, and layer richer textures for evening functions.'],
      ['heading' => 'Color Stories', 'body' => 'Gold neutrals, rose tones, slate blues, and jewel accents are designed to mix across sets instead of living as one-time looks.'],
      ['heading' => 'Build the Full Look', 'body' => 'Start with one hero piece, then coordinate with a tonal dupatta, softer footwear, and minimal accessories for balance.'],
    ],
  ],
  'returns' => [
    'title' => 'Returns and Exchanges',
    'eyebrow' => 'Returns',
    'intro' => 'Eligible items can be returned within 7 days of delivery when they are unused, unwashed, and still include original tags.',
    'sections' => [
      ['heading' => 'Return Window', 'body' => 'Begin the return request promptly after delivery so pickup or courier coordination stays within the policy window.'],
      ['heading' => 'Condition Requirements', 'body' => 'Items should be unworn, stain-free, and returned with original packaging and tags wherever possible.'],
      ['heading' => 'Refund Timing', 'body' => 'Approved returns are typically processed after quality review, and refund timing depends on the original payment method.'],
    ],
  ],
  'privacy' => [
    'title' => 'Privacy Policy',
    'eyebrow' => 'Privacy',
    'intro' => 'We collect the information needed to process orders, provide account access, and improve the storefront experience.',
    'sections' => [
      ['heading' => 'What We Collect', 'body' => 'This can include contact details, shipping information, order history, and basic device or browser data used for performance and security.'],
      ['heading' => 'How It Is Used', 'body' => 'Information is used for checkout, fulfilment, customer support, fraud prevention, and store operations such as analytics or service updates.'],
      ['heading' => 'Your Control', 'body' => 'You can request account or marketing preference changes through the support channel published on the storefront or your order communication.'],
    ],
  ],
  'terms' => [
    'title' => 'Terms of Service',
    'eyebrow' => 'Terms',
    'intro' => 'By using the site, you agree to place accurate orders, provide valid payment and delivery details, and use the storefront lawfully.',
    'sections' => [
      ['heading' => 'Orders', 'body' => 'Orders may be reviewed for stock availability, pricing accuracy, payment verification, and shipping coverage before fulfilment.'],
      ['heading' => 'Store Content', 'body' => 'Product imagery, copy, layout, and branding remain protected content and should not be reused without permission.'],
      ['heading' => 'Policy Updates', 'body' => 'Terms may change over time, so important orders or commercial use should always rely on the latest published version.'],
    ],
  ],
  'size-guide' => [
    'title' => 'Size Guide',
    'eyebrow' => 'Fit Help',
    'intro' => 'Choose the size that best matches your body measurements, then size up if you prefer a more relaxed occasionwear fit.',
    'sections' => [
      ['heading' => 'How To Measure', 'body' => 'Measure bust, waist, and hip over light clothing using a soft tape. Compare the largest relevant point against the product size options.'],
      ['heading' => 'When In Between', 'body' => 'For structured fits, choose the larger size if you are between measurements. For flowy silhouettes, your regular size is usually the best start.'],
      ['heading' => 'Check Product Notes', 'body' => 'Fabric, lining, and silhouette can affect fit. Use the product description and care details as the final tie-breaker.'],
    ],
  ],
  'shipping' => [
    'title' => 'Shipping Information',
    'eyebrow' => 'Shipping',
    'intro' => 'Delivery timing depends on payment confirmation, service area, and any festive-season volume spikes.',
    'sections' => [
      ['heading' => 'Dispatch Timing', 'body' => 'Most in-stock orders are prepared quickly, while select pieces may need a little extra handling for finishing or quality checks.'],
      ['heading' => 'Delivery Windows', 'body' => 'Metro orders usually arrive sooner than remote locations. Tracking details are shared after dispatch when available.'],
      ['heading' => 'Shipping Charges', 'body' => 'Threshold-based free shipping and current courier charges are shown during checkout when those settings are enabled.'],
    ],
  ],
  'track-order' => [
    'title' => 'Track Your Order',
    'eyebrow' => 'Order Support',
    'intro' => 'Use your order number and delivery updates to follow progress from confirmation through dispatch and final delivery.',
    'sections' => [
      ['heading' => 'What You Need', 'body' => 'Keep your order number handy along with the email address or phone number used at checkout.'],
      ['heading' => 'Status Stages', 'body' => 'Pending, processing, dispatched, and delivered are the most common checkpoints you will see during fulfilment.'],
      ['heading' => 'Need Help', 'body' => 'If tracking stalls or a delivery attempt fails, reach out through the same support channel listed in your order confirmation.'],
    ],
  ],
  'contact' => [
    'title' => 'Contact Us',
    'eyebrow' => 'Contact',
    'intro' => 'For order help, product questions, or policy clarifications, use the support channel published in your storefront or order confirmation.',
    'sections' => [
      ['heading' => 'Order Queries', 'body' => 'Include your order number, delivery city, and the issue you want resolved so support can respond faster.'],
      ['heading' => 'Product Help', 'body' => 'Share the product name or link, along with fit or styling questions, if you need help choosing before checkout.'],
      ['heading' => 'Business Hours', 'body' => 'Response times can vary during launches and festive periods, but clear issue details usually speed things up.'],
    ],
  ],
  'artisans' => [
    'title' => 'Artisans and Craft',
    'eyebrow' => 'Craft',
    'intro' => 'Behind each collection is a focus on finishing, surface detail, and the kind of craft that makes a garment feel considered rather than mass-produced.',
    'sections' => [
      ['heading' => 'Detail Work', 'body' => 'Embroidery, trims, and finishing touches are chosen to elevate the final silhouette without overpowering it.'],
      ['heading' => 'Material Handling', 'body' => 'Fabric behavior matters. The final drape, lining, and weight all shape how a piece moves in real wear.'],
      ['heading' => 'Consistency', 'body' => 'Good craft also means quality control: cleaner seams, better balance, and thoughtful finishing where it counts.'],
    ],
  ],
  'sustainability' => [
    'title' => 'Sustainability',
    'eyebrow' => 'Sustainability',
    'intro' => 'A better storefront experience starts with tighter edits, clearer buying decisions, and fewer throwaway purchases.',
    'sections' => [
      ['heading' => 'Buy Better', 'body' => 'Clear product details, measured categories, and a calmer catalog help customers choose more intentionally.'],
      ['heading' => 'Smaller Assortments', 'body' => 'Focused seasonal drops reduce noise and make room for stronger curation.'],
      ['heading' => 'Longer Wear', 'body' => 'Pieces designed for repeat use naturally support a more sustainable wardrobe than one-event purchases.'],
    ],
  ],
  'press' => [
    'title' => 'Press',
    'eyebrow' => 'Press',
    'intro' => 'Press, editorial, and collaboration requests should include context, deadlines, audience details, and the collection or story angle you want to feature.',
    'sections' => [
      ['heading' => 'Editorial Requests', 'body' => 'Include your publication or platform, timeline, and the pieces or seasonal angle you are interested in.'],
      ['heading' => 'Brand Features', 'body' => 'Highlight whether you need imagery, brand background, or product notes so the request can be routed cleanly.'],
      ['heading' => 'Lead Time', 'body' => 'Campaign, event, and launch windows can affect turnaround, so earlier requests are always easier to support.'],
    ],
  ],
  'cookies' => [
    'title' => 'Cookie Notice',
    'eyebrow' => 'Cookies',
    'intro' => 'Cookies and similar browser storage help keep carts persistent, maintain sessions, and support analytics or storefront preferences.',
    'sections' => [
      ['heading' => 'Essential Storage', 'body' => 'Some storage is needed for sessions, carts, security, and the basic operation of account or checkout flows.'],
      ['heading' => 'Analytics and Preferences', 'body' => 'Optional tracking may be used to understand visits, product interest, and site performance over time.'],
      ['heading' => 'Browser Controls', 'body' => 'You can usually review or clear cookies from your browser settings, although that may affect saved carts or sign-in state.'],
    ],
  ],
];

$aliases = [
  'our-story' => 'about',
];

$slug = strtolower(trim((string)($_GET['slug'] ?? 'about')));
$slug = $aliases[$slug] ?? $slug;
$page = $pages[$slug] ?? null;

if ($page === null) {
  http_response_code(404);
  $page = [
    'title' => 'Page Not Found',
    'eyebrow' => '404',
    'intro' => 'The page you were looking for is not available.',
    'sections' => [
      ['heading' => 'What To Do Next', 'body' => 'Return to the home page, browse the collection, or use the footer links to continue exploring the storefront.'],
    ],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= h($page['title']) ?> | Mavdee</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    :root {
      --cream: #fcfafc;
      --parchment: #f3efea;
      --ink: #14100d;
      --ink-soft: #3f342d;
      --muted: #93857b;
      --gold: #dda74f;
      --gold-pale: #faf4e8;
      --border: rgba(20, 16, 13, 0.1);
      --surface: rgba(252, 250, 252, 0.92);
      --max: 1120px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Jost', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top right, rgba(221, 167, 79, 0.18), transparent 28%),
        linear-gradient(180deg, var(--cream), var(--parchment));
      min-height: 100vh;
    }

    a {
      color: inherit;
    }

    .shell {
      max-width: var(--max);
      margin: 0 auto;
      padding: 0 24px 48px;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      backdrop-filter: blur(18px);
      background: var(--surface);
      border-bottom: 1px solid var(--border);
    }

    .topbar-inner {
      max-width: calc(var(--max) + 48px);
      margin: 0 auto;
      padding: 18px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }

    .brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      text-decoration: none;
      letter-spacing: 0.04em;
    }

    .brand span,
    .crumbs a,
    .footer-links a {
      color: var(--gold);
    }

    .top-links,
    .footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: 16px;
      font-size: 14px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .hero {
      padding: 72px 0 28px;
    }

    .crumbs {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      color: var(--muted);
      font-size: 13px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 20px;
    }

    .eyebrow {
      color: var(--gold);
      letter-spacing: 0.18em;
      text-transform: uppercase;
      font-size: 13px;
      margin-bottom: 10px;
    }

    h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.2rem, 5vw, 4.2rem);
      line-height: 0.95;
      max-width: 780px;
      margin-bottom: 20px;
    }

    .intro {
      max-width: 720px;
      font-size: 1.05rem;
      line-height: 1.8;
      color: var(--ink-soft);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 18px;
      margin-top: 30px;
    }

    .panel {
      grid-column: span 4;
      padding: 26px;
      background: rgba(255, 255, 255, 0.66);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 18px 40px rgba(20, 16, 13, 0.06);
    }

    .panel h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem;
      margin-bottom: 12px;
    }

    .panel p,
    .callout p {
      color: var(--ink-soft);
      line-height: 1.75;
    }

    .callout {
      margin-top: 24px;
      padding: 24px 26px;
      border-left: 3px solid var(--gold);
      background: var(--gold-pale);
      border-radius: 0 16px 16px 0;
    }

    .cta-row {
      margin-top: 28px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 0 18px;
      border: 1px solid var(--ink);
      border-radius: 999px;
      text-decoration: none;
      font-size: 13px;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      font-weight: 600;
    }

    .btn-primary {
      background: var(--ink);
      color: var(--cream);
    }

    footer {
      margin-top: 56px;
      padding-top: 24px;
      border-top: 1px solid var(--border);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 16px;
      color: var(--muted);
      font-size: 15px;
    }

    @media (max-width: 860px) {

      .topbar-inner,
      .top-links {
        align-items: flex-start;
      }

      .topbar-inner {
        flex-direction: column;
      }

      .grid {
        grid-template-columns: 1fr;
      }

      .panel {
        grid-column: auto;
      }
    }
  </style>
</head>

<body>
  <div class="topbar">
    <div class="topbar-inner">
      <a class="brand" href="index.php">Mavdee <span>Fashion</span></a>
      <nav class="top-links" aria-label="Primary">
        <a href="shop.php">Collections</a>
        <a href="<?= h(page_url('lookbook')) ?>">Lookbook</a>
        <a href="<?= h(page_url('about')) ?>">About</a>
        <a href="<?= h(page_url('contact')) ?>">Contact</a>
        <a href="cart.php">Cart</a>
      </nav>
    </div>
  </div>

  <main class="shell">
    <section class="hero">
      <div class="crumbs">
        <a href="index.php">Home</a>
        <span>/</span>
        <a href="shop.php">Collections</a>
        <span>/</span>
        <span><?= h($page['eyebrow']) ?></span>
      </div>
      <p class="eyebrow"><?= h($page['eyebrow']) ?></p>
      <h1><?= h($page['title']) ?></h1>
      <p class="intro"><?= h($page['intro']) ?></p>
    </section>

    <section class="grid" aria-label="Page sections">
      <?php foreach ($page['sections'] as $section): ?>
        <article class="panel">
          <h2><?= h($section['heading']) ?></h2>
          <p><?= h($section['body']) ?></p>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="callout">
      <p>Need something specific? Use the collection pages for product browsing, then return here for policy and support details when you need them.</p>
      <div class="cta-row">
        <a class="btn btn-primary" href="shop.php">Browse Collection</a>
        <a class="btn" href="login.php">My Account</a>
      </div>
    </div>

    <footer>
      <p>&copy; 2026 Mavdee. All rights reserved.</p>
      <div class="footer-links">
        <a href="<?= h(page_url('privacy')) ?>">Privacy</a>
        <a href="<?= h(page_url('terms')) ?>">Terms</a>
        <a href="<?= h(page_url('returns')) ?>">Returns</a>
        <a href="<?= h(page_url('shipping')) ?>">Shipping</a>
      </div>
    </footer>
  </main>
</body>

</html>