<?php
// Shared site footer — require this file at the end of each page's <body>.
// Depends on: config/config.php (SITE_NAME, isLoggedIn())
?>
<style>
  /* ── Mavdee-style Footer ─────────────────────────────────────── */
  footer {
    background: #fff;
    padding: 32px 16px calc(16px + env(safe-area-inset-bottom));
    border-top: 6px solid var(--Mavdee-grey, #f4f4f5);
    font-family: var(--font-sans, 'DM Sans', sans-serif);
  }

  @media (min-width: 768px) {
    footer {
      padding: 40px 24px 32px;
    }
  }

  @media (min-width: 1024px) {
    footer {
      padding: 48px 40px 32px;
    }
  }

  .footer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    max-width: 1400px;
    margin: 0 auto 24px;
  }

  @media (min-width: 768px) {
    .footer-grid {
      grid-template-columns: 2fr 1fr 1fr;
      gap: 32px;
    }
  }

  @media (min-width: 1024px) {
    .footer-grid {
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 48px;
    }
  }

  .footer-brand-name {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--Mavdee-pink, #ff3f6c);
    letter-spacing: -0.03em;
    display: block;
    margin-bottom: 10px;
    text-decoration: none;
  }

  .footer-brand-desc {
    color: var(--Mavdee-muted, #94969f);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 16px;
  }

  .footer-social {
    display: flex;
    gap: 10px;
  }

  .footer-social a {
    width: 38px;
    height: 38px;
    border: 1px solid var(--Mavdee-border, #eaeaec);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--Mavdee-muted, #94969f);
    transition: all 0.2s;
  }

  .footer-social a:hover {
    border-color: var(--Mavdee-pink, #ff3f6c);
    color: var(--Mavdee-pink, #ff3f6c);
  }

  .footer-col-title {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: var(--Mavdee-dark, #1c1c1c);
    margin-bottom: 14px;
  }

  .footer-col-links {
    display: flex;
    flex-direction: column;
    gap: 9px;
  }

  .footer-col-links a {
    font-size: 14px;
    color: var(--Mavdee-muted, #94969f);
    transition: color 0.2s;
    text-decoration: none;
  }

  .footer-col-links a:hover {
    color: var(--Mavdee-pink, #ff3f6c);
  }

  .footer-col-links span {
    font-size: 14px;
    color: var(--Mavdee-muted, #94969f);
  }

  .footer-bottom {
    border-top: 1px solid var(--Mavdee-border, #eaeaec);
    padding-top: 20px;
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--Mavdee-muted, #94969f);
    flex-wrap: wrap;
    gap: 10px;
  }

  .footer-bottom a {
    color: var(--Mavdee-muted, #94969f);
    margin-left: 14px;
    text-decoration: none;
  }

  .footer-bottom a:hover {
    color: var(--Mavdee-pink, #ff3f6c);
  }

  #pwa-install-banner {
    display: none;
    position: fixed;
    bottom: 70px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10000;
    background: #fff;
    border: 1px solid var(--Mavdee-border, #eaeaec);
    border-radius: 16px;
    padding: 14px 16px;
    width: calc(100% - 32px);
    max-width: 420px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.14);
  }
</style>

<!-- Footer -->
<footer>
  <div class="footer-grid">
    <div>
      <a href="index.php" class="footer-brand-name"><?= htmlspecialchars(SITE_NAME) ?></a>
      <p class="footer-brand-desc">Premium Indian fashion crafted with love. Festive silhouettes, everyday comfort, and styles for every occasion.</p>
      <div class="footer-social">
        <a href="<?= h(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="2" y="2" width="20" height="20" rx="5" />
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
          </svg>
        </a>
        <a href="<?= h(SOCIAL_FACEBOOK) ?>" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Facebook">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
          </svg>
        </a>
        <a href="<?= h(SOCIAL_PINTEREST) ?>" target="_blank" rel="noopener noreferrer" title="Pinterest" aria-label="Pinterest">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="10" />
            <path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32" />
          </svg>
        </a>
      </div>
    </div>
    <div>
      <div class="footer-col-title">Quick Links</div>
      <div class="footer-col-links">
        <a href="shop.php">Shop All</a>
        <a href="shop.php?cat=kurtis">Kurtis</a>
        <a href="shop.php?cat=dresses">Dresses</a>
        <a href="shop.php?cat=party">Party Wear</a>
        <a href="about.php">About Us</a>
        <a href="wishlist.php">My Wishlist</a>
        <a href="dashboard.php">My Account</a>
      </div>
    </div>
    <div>
      <div class="footer-col-title">Support</div>
      <div class="footer-col-links">
        <a href="returns.php">Returns &amp; Exchanges</a>
        <a href="shipping.php">Shipping Info</a>
        <a href="privacy.php">Privacy Policy</a>
        <a href="contact.php">Contact Us</a>
      </div>
    </div>
    <div>
      <div class="footer-col-title">Contact</div>
      <div class="footer-col-links">
        support@mavdee.com
        <span>&#128222; +91 98765 43210</span>
        <span>&#128336; Mon&#8211;Sat, 10am&#8211;6pm</span>
      </div>
    </div>
  </div>
  <div class="footer-payment" style="text-align:center;padding:16px 0 8px;border-top:1px solid var(--Mavdee-border,#eaeaec);margin-top:8px;max-width:1400px;margin-left:auto;margin-right:auto;">
    <p style="font-size:12px;color:var(--Mavdee-muted,#94969f);margin:0 0 10px;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Secure Payments</p>
    <div style="display:flex;justify-content:center;align-items:center;gap:10px;flex-wrap:wrap;">
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">VISA</span>
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">MASTERCARD</span>
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">UPI</span>
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">RAZORPAY</span>
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">COD</span>
      <span style="background:#f4f4f5;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;color:#3e4152;">NET BANKING</span>
    </div>
  </div>
  <div class="footer-newsletter" style="background:var(--Mavdee-grey,#f4f4f5);border-radius:12px;padding:20px 24px;max-width:500px;margin:16px auto 0;text-align:center;">
    <p style="font-size:13px;font-weight:700;color:var(--Mavdee-dark,#1c1c1c);margin:0 0 4px;text-transform:uppercase;letter-spacing:.06em;">Stay in the Loop</p>
    <p style="font-size:12px;color:var(--Mavdee-muted,#94969f);margin:0 0 12px;">New arrivals, offers &amp; style tips — straight to your inbox.</p>
    <form action="/api/newsletter/subscribe.php" method="POST" style="display:flex;gap:8px;" onsubmit="handleNewsletterSignup(event, this)">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
      <input type="email" name="email" placeholder="Enter your email" required
        style="flex:1;height:40px;border:1.5px solid var(--Mavdee-border,#eaeaec);border-radius:8px;padding:0 12px;font-size:13px;font-family:var(--font-sans,'DM Sans',sans-serif);outline:none;"
        onfocus="this.style.borderColor='var(--Mavdee-pink,#ff3f6c)'" onblur="this.style.borderColor='var(--Mavdee-border,#eaeaec)'">
      <button type="submit" style="height:40px;padding:0 18px;background:var(--Mavdee-pink,#ff3f6c);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;">Subscribe</button>
    </form>
  </div>
  <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
    function handleNewsletterSignup(e, form) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var input = form.querySelector('input[type="email"]');
      if (!input.value) return;
      btn.disabled = true;
      btn.textContent = '...';
      var fd = new FormData(form);
      fetch('/api/newsletter/subscribe.php', {
          method: 'POST',
          body: fd
        })
        .then(function(r) {
          return r.json();
        })
        .then(function(d) {
          if (d.success || d.ok) {
            btn.textContent = '✓ Done!';
            btn.style.background = '#03a685';
            input.value = '';
          } else {
            btn.textContent = 'Subscribe';
            btn.disabled = false;
            alert(d.error || 'Could not subscribe. Try again.');
          }
        })
        .catch(function() {
          btn.textContent = 'Subscribe';
          btn.disabled = false;
        });
    }
  </script>
  <div class="footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.</span>
    <span>
      <a href="privacy.php">Privacy</a>
      <a href="returns.php">Terms</a>
    </span>
  </div>
</footer>

<script src="/assets/js/ui.js" defer></script>
<script src="/assets/js/cart.js" defer></script>
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/search.js" defer></script>

<!-- ── Live Chat ──────────────────────────────────────────────────────── -->
<?php
// Only show live chat on product, checkout, contact pages
$_chatPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_chatShow = in_array($_chatPage, ['product.php', 'checkout.php', 'contact.php', 'index.php'], true);
if ($_chatShow):
?>
  <style>
    #fc_widget,
    #tidio-chat,
    .tawk-widget-wrapper {
      z-index: 999 !important;
      bottom: calc(72px + env(safe-area-inset-bottom)) !important;
    }
  </style>
<?php endif; ?>

<!-- PWA Install Prompt -->
<div id="pwa-install-banner">
  <div style="display:flex;align-items:center;gap:12px;">
    <img src="/assets/icons/icon-192.png" alt="" width="40" height="40" style="border-radius:8px;">
    <div style="flex:1;min-width:0;">
      Add Mavdee
      <p style="margin:2px 0 0;font-size:12px;color:#94969f;">Install for faster access &amp; offline browsing</p>
    </div>
    <button id="pwa-install-btn" style="background:var(--Mavdee-pink,#ff3f6c);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Install</button>
    <button id="pwa-install-close" style="background:none;border:none;font-size:20px;color:#94969f;cursor:pointer;padding:0 4px;">&times;</button>
  </div>
</div>
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
  (function() {
    var deferredPrompt;
    var banner = document.getElementById('pwa-install-banner');
    if (!banner) return;
    window.addEventListener('beforeinstallprompt', function(e) {
      e.preventDefault();
      deferredPrompt = e;
      if (!localStorage.getItem('pwa_dismissed')) banner.style.display = 'block';
    });
    document.getElementById('pwa-install-btn').addEventListener('click', function() {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function() {
        banner.style.display = 'none';
      });
      deferredPrompt = null;
    });
    document.getElementById('pwa-install-close').addEventListener('click', function() {
      banner.style.display = 'none';
      localStorage.setItem('pwa_dismissed', '1');
    });
  })();
</script>