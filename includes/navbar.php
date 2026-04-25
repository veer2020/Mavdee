<?php

/**
 * includes/navbar.php
 * Shared site navigation — included inside <body> on every frontend page.
 * Depends on: config/config.php (SITE_NAME, isLoggedIn(), getCustomerId())
 *
 * Optional variables set by the including page before the require:
 *   $headerHideSearch        (bool) — hide the search bar
 *   $headerHideCartWishlist  (bool) — hide cart / wishlist icons
 */

if (defined('NAVBAR_INCLUDED')) return;
define('NAVBAR_INCLUDED', true);

$_navHideSearch       = !empty($headerHideSearch);
$_navHideCartWishlist = !empty($headerHideCartWishlist);
$_navLoggedIn         = function_exists('isLoggedIn') && isLoggedIn();
$_navCustomerName     = $_navLoggedIn ? htmlspecialchars($_SESSION['customer_name'] ?? 'Account') : '';
$_navSiteName         = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Mavdee';

// Current page for active nav state
$_navCurrentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Language & currency
$_navLang     = $_SESSION['lang']     ?? 'en';
$_navCurrency = $_SESSION['currency'] ?? 'INR';

// Unread notification count
$_navUnread = 0;
if ($_navLoggedIn) {
  try {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([getUserId()]);
    $_navUnread = (int)$stmt->fetchColumn();
  } catch (Throwable) { /* table may not exist yet */
  }
}
?>
<!-- ══════════════════════════════════════════════════════════════════
     SITE HEADER / NAVBAR
     ══════════════════════════════════════════════════════════════════ -->
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
  // Ensure csrf-token meta is available even if not in <head>
  if (!document.querySelector('meta[name="csrf-token"]')) {
    var m = document.createElement('meta');
    m.name = 'csrf-token';
    m.content = '<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES) ?>';
    document.head.appendChild(m);
  }
</script>
<a href="#main-content" class="skip-link">Skip to main content</a>
<header class="site-header" id="siteHeader" role="banner">

  <!-- ── Top Row: logo · search · icons ───────────────────────────── -->
  <div class="header-top-row">

    <!-- Hamburger (mobile) -->
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false" aria-controls="mobNavDrawer">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <line x1="3" y1="6" x2="21" y2="6" />
        <line x1="3" y1="12" x2="21" y2="12" />
        <line x1="3" y1="18" x2="21" y2="18" />
      </svg>
    </button>

    <!-- Logo -->
    <a href="/index.php" class="site-logo" aria-label="<?= $_navSiteName ?> – Home">
      <?= $_navSiteName ?>
    </a>

    <!-- Desktop Category Nav -->
    <nav class="header-nav-desktop" aria-label="Main categories">
      <a href="/shop.php" <?= $_navCurrentPage === 'shop.php' && ($_GET['cat'] ?? '') === ''             ? 'class="nav-active"' : '' ?>>Shop All</a>
      <a href="/shop.php?cat=kurtis" <?= $_navCurrentPage === 'shop.php' && ($_GET['cat'] ?? '') === 'kurtis'       ? 'class="nav-active"' : '' ?>>Kurtis</a>
      <a href="/shop.php?cat=dresses" <?= $_navCurrentPage === 'shop.php' && ($_GET['cat'] ?? '') === 'dresses'      ? 'class="nav-active"' : '' ?>>Dresses</a>
      <a href="/shop.php?cat=coord-sets" <?= $_navCurrentPage === 'shop.php' && ($_GET['cat'] ?? '') === 'coord-sets'  ? 'class="nav-active"' : '' ?>>Co-ord Sets</a>
      <a href="/shop.php?cat=party" <?= $_navCurrentPage === 'shop.php' && ($_GET['cat'] ?? '') === 'party'        ? 'class="nav-active"' : '' ?>>Party Wear</a>
    </nav>

    <?php if (!$_navHideSearch): ?>
      <!-- Search Bar -->
      <div class="header-search-wrap" role="search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input type="search"
          class="header-search-input search-trigger"
          id="headerSearchInput"
          placeholder="Search for products, brands and more"
          aria-label="Search products"
          autocomplete="off"
          autocorrect="off"
          spellcheck="false"
          data-search-trigger="1">
      </div>
    <?php endif; ?>

    <!-- Right Icons -->
    <?php if (!$_navHideCartWishlist): ?>
      <div class="header-icons">

        <?php if (!$_navHideSearch): ?>
          <!-- Search icon (mobile only — the search bar is hidden on small screens) -->
          <a href="#" class="header-icon-btn search-mobile-btn search-trigger" aria-label="Search" data-search-trigger="1">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <span>Search</span>
          </a>
        <?php endif; ?>

        <!-- Wishlist -->
        <a href="/wishlist.php" class="header-icon-btn" aria-label="Wishlist" style="position:relative;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
          </svg>
          <span class="cart-badge" id="wishlistBadge" style="display:none">0</span>
          <span>Wishlist</span>
        </a>

        <!-- Cart -->
        <button type="button" class="cart-icon-wrap" id="cartToggleBtn" aria-label="Shopping cart" aria-haspopup="dialog" aria-expanded="false">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
          </svg>
          <span class="cart-badge" id="cartBadge" style="display:none">0</span>
          <span>Cart</span>
        </button>

        <!-- Profile / Account -->
        <?php if ($_navLoggedIn): ?>

          <!-- Notification Bell -->
          <div class="header-notif-wrap" id="notifDropdownWrap">
            <button type="button" class="header-icon-btn" id="notifDropdownBtn"
              aria-haspopup="true" aria-expanded="false" aria-label="Notifications"
              onclick="toggleNotifDropdown()">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
              </svg>
              <?php if ($_navUnread > 0): ?>
                <span class="cart-badge notif-badge" id="notifBadge"><?= min($_navUnread, 9) ?></span>
              <?php else: ?>
                <span class="cart-badge notif-badge" id="notifBadge" style="display:none">0</span>
              <?php endif; ?>
              <span>Alerts</span>
            </button>
            <div class="notif-dropdown" id="notifDropdown" role="dialog" aria-label="Notifications" aria-live="polite">
              <div class="notif-header">
                <strong>Notifications</strong>
                <button class="notif-mark-read" onclick="markAllNotifsRead()" type="button">Mark all read</button>
              </div>
              <div class="notif-list" id="notifList">
                <div class="notif-empty">Loading…</div>
              </div>
            </div>
          </div>

          <div class="header-account-wrap" id="accountDropdownWrap">
            <button type="button" class="header-icon-btn" id="accountDropdownBtn" aria-haspopup="true" aria-expanded="false" aria-label="My account">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
              <span><?= $_navCustomerName ?></span>
            </button>
            <div class="account-dropdown" id="accountDropdown" role="menu">
              <a href="/dashboard.php" role="menuitem">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                My Profile
              </a>
              <a href="/my-orders.php" role="menuitem">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                  <line x1="3" y1="6" x2="21" y2="6" />
                  <path d="M16 10a4 4 0 0 1-8 0" />
                </svg>
                My Orders
              </a>
              <a href="/wishlist.php" role="menuitem">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
                My Wishlist
              </a>
              <div class="account-dropdown-divider"></div>
              <a href="/logout.php" role="menuitem">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <polyline points="16 17 21 12 16 7" />
                  <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <a href="/login.php" class="header-icon-btn" aria-label="Login or Register">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
            <span>Login</span>
          </a>
        <?php endif; ?>

        <!-- Language Switcher -->
        <div class="header-lang-wrap">
          <button class="header-icon-btn lang-btn" aria-label="Language" onclick="toggleLangMenu(this)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <circle cx="12" cy="12" r="10" />
              <line x1="2" y1="12" x2="22" y2="12" />
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
            </svg>
            <span><?= strtoupper(h($_navLang)) ?></span>
          </button>
          <div class="lang-dropdown" role="menu" aria-label="Select language">
            <a href="/api/set_preference.php?lang=en" class="<?= $_navLang === 'en' ? 'active' : '' ?>" role="menuitem">🇬🇧 English</a>
            <a href="/api/set_preference.php?lang=hi" class="<?= $_navLang === 'hi' ? 'active' : '' ?>" role="menuitem">🇮🇳 हिन्दी</a>
          </div>
        </div>

        <!-- Currency Switcher -->
        <div class="header-currency-wrap">
          <button class="header-icon-btn currency-btn" aria-label="Currency" onclick="toggleCurrencyMenu(this)">
            <span><?= h($_navCurrency) ?></span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </button>
          <div class="currency-dropdown" role="menu" aria-label="Select currency">
            <a href="/api/set_preference.php?currency=INR" class="<?= $_navCurrency === 'INR' ? 'active' : '' ?>" role="menuitem">₹ INR</a>
            <a href="/api/set_preference.php?currency=USD" class="<?= $_navCurrency === 'USD' ? 'active' : '' ?>" role="menuitem">$ USD</a>
            <a href="/api/set_preference.php?currency=EUR" class="<?= $_navCurrency === 'EUR' ? 'active' : '' ?>" role="menuitem">€ EUR</a>
          </div>
        </div>

      </div><!-- /.header-icons -->
    <?php endif; ?>

  </div><!-- /.header-top-row -->

  <!-- ── Delivery Location Row (mobile) ───────────────────────────── -->
  <div class="header-delivery-row" aria-label="Delivery location">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
      <circle cx="12" cy="10" r="3" />
    </svg>
    <span>Deliver to</span>
    <span class="delivery-location">
      India
      <svg class="delivery-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <polyline points="6 9 12 15 18 9" />
      </svg>
    </span>
  </div>

</header><!-- /.site-header -->


<!-- ══════════════════════════════════════════════════════════════════
     MOBILE NAV DRAWER
     ══════════════════════════════════════════════════════════════════ -->
<div class="mob-nav-overlay" id="mobNavOverlay" aria-hidden="true"></div>
<nav class="mob-nav-drawer" id="mobNavDrawer" role="dialog" aria-label="Mobile navigation" aria-modal="true" aria-hidden="true">
  <button class="mob-nav-close" id="mobNavClose" aria-label="Close menu">&times;</button>

  <?php if ($_navLoggedIn): ?>
    <div class="mob-nav-account">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
      </svg>
      <span>Hi, <?= $_navCustomerName ?></span>
    </div>
  <?php else: ?>
    <div class="mob-nav-auth-links">
      <a href="/login.php" class="mob-nav-login-btn">Login</a>
      <span class="mob-nav-sep">/</span>
      <a href="/register.php" class="mob-nav-login-btn">Register</a>
    </div>
  <?php endif; ?>

  <div class="mob-nav-links">
    <a href="/shop.php?cat=womens">
      <span>Women</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
    <a href="/shop.php?cat=mens">
      <span>Men</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
    <a href="/shop.php?cat=kids">
      <span>Kids</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
    <a href="/shop.php?cat=accessories">
      <span>Accessories</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
    <a href="/shop.php?sale=1" style="color:var(--Mavdee-pink,#ff3f6c)">
      <span>Sale 🔥</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
    <a href="/shop.php">
      <span>All Products</span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="9 18 15 12 9 6" />
      </svg>
    </a>
  </div>

  <div class="mob-nav-bottom">
    <?php if ($_navLoggedIn): ?>
      <a href="/dashboard.php">My Account</a>
      <a href="/my-orders.php">My Orders</a>
      <a href="/logout.php">Logout</a>
    <?php else: ?>
      <a href="/login.php">Login / Register</a>
    <?php endif; ?>
    <a href="/about.php">About Us</a>
    <a href="/contact.php">Contact</a>
  </div>
</nav>


<!-- ══════════════════════════════════════════════════════════════════
     CART MINI-DRAWER
     ══════════════════════════════════════════════════════════════════ -->
<div class="cart-overlay" id="cartOverlay" aria-hidden="true"></div>
<aside class="cart-drawer" id="cartDrawer" role="dialog" aria-label="Shopping cart" aria-modal="true" aria-hidden="true">
  <div class="cart-header">
    <h2 class="cart-title">My Bag</h2>
    <button type="button" class="cart-close" id="cartCloseBtn" aria-label="Close cart">&times;</button>
  </div>
  <div class="cart-body" id="cartBody">
    <!-- Populated by cart.js -->
    <div style="padding:40px 20px;text-align:center;color:#94969f;">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 12px;opacity:0.4">
        <circle cx="9" cy="21" r="1" />
        <circle cx="20" cy="21" r="1" />
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
      </svg>
      <p style="margin:0;font-size:14px;">Loading…</p>
    </div>
  </div>
  <div class="cart-footer" id="cartFooter" style="display:none">
    <div class="cart-total-row">
      <span>Subtotal</span>
      <span id="cartTotal">₹0</span>
    </div>
    <div class="cart-savings" id="cartSavings" style="display:none"></div>
    <a href="/cart.php" class="btn-checkout" style="display:block;text-align:center;text-decoration:none;margin-bottom:8px;">
      VIEW CART
    </a>
    <a href="/checkout.php" class="btn-checkout" style="background:var(--Mavdee-green,#03a685);display:block;text-align:center;text-decoration:none;">
      CHECKOUT
    </a>
  </div>
</aside>


<!-- ══════════════════════════════════════════════════════════════════
     INLINE STYLES: account dropdown + hamburger + search dropdown
     ══════════════════════════════════════════════════════════════════ -->
<style>
  /* ── Header Layout + Logo Positioning ───────────────────────────── */
  .header-top-row {
    position: relative;
  }

  /* ── Mobile: logo centred within the available pane
        The hamburger sits on the left (~36px + 4px padding = ~44px).
        We use margin-left: auto / margin-right: auto but push it
        away from the hamburger so it's optically centred in the
        remaining space between hamburger and icons.               ── */
  .site-logo {
    position: absolute !important;
    /* Centre relative to the FULL row width … */
    left: 40%;
    top: 50%;
    transform: translate(-50%, -50%) !important;
    flex-grow: 0 !important;
    margin: 0 !important;
    z-index: 2;
    font-size: 1.8rem;
    transition: font-size 0.3s ease, left 0.3s ease, transform 0.3s ease;
    font-weight: 800;
    /* … then shift right by half the hamburger width so it sits
       centred in the pane between hamburger and icons            */
    margin-left: 22px !important;
    /* ≈ half of ~44px hamburger area */
  }

  /* ── Desktop: logo left-aligned, flush with the icons pane ─── */
  /* ── Desktop: logo LEFT-aligned ─── */
  @media (min-width: 1024px) {
    .site-logo {
      position: static !important;
      transform: none !important;

      margin-left: 0 !important;
      /* keep it on left */
      margin-right: auto !important;
      /* push other items right */

      order: 0;
      /* ensure it's first */
      flex-shrink: 0;
      font-size: 1.9rem;
    }
  }

  /* ── Hamburger button ──────────────────────────────────────── */
  .hamburger-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--Mavdee-dark, #1c1c1c);
    padding: 4px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
  }

  @media (min-width: 1024px) {
    .hamburger-btn {
      display: none;
    }
  }

  /* ── Search mobile button ─────────────────────────────────── */
  .search-mobile-btn {
    display: flex;
  }

  @media (min-width: 1024px) {
    .search-mobile-btn {
      display: none !important;
    }
  }

  /* ── Hide full search bar on mobile (search icon link used instead) ── */
  @media (max-width: 1023px) {
    .header-search-wrap {
      display: none;
    }
  }

  /* ── Desktop category nav — active ───────────────────────── */
  .header-nav-desktop a.nav-active {
    color: var(--Mavdee-pink, #ff3f6c);
    border-bottom-color: var(--Mavdee-pink, #ff3f6c);
  }

  /* ── Account dropdown ─────────────────────────────────────── */
  .header-account-wrap {
    position: relative;
  }

  .account-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: #fff;
    border: 1px solid var(--Mavdee-border, #eaeaec);
    border-radius: 8px;
    min-width: 180px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px);
    transition: opacity 0.2s, transform 0.2s;
    z-index: 1100;
    padding: 6px 0;
  }

  .account-dropdown.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
  }

  .account-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    font-size: 14px;
    color: var(--Mavdee-text, #3e4152);
    text-decoration: none;
    transition: background 0.15s;
  }

  .account-dropdown a:hover {
    background: var(--Mavdee-grey, #f4f4f5);
    color: var(--Mavdee-dark, #1c1c1c);
  }

  .account-dropdown-divider {
    height: 1px;
    background: var(--Mavdee-border, #eaeaec);
    margin: 4px 0;
  }

  /* ── Mobile nav drawer extras ─────────────────────────────── */
  .mob-nav-account {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 0 20px;
    font-size: 15px;
    font-weight: 600;
    color: var(--Mavdee-dark, #1c1c1c);
    border-bottom: 1px solid var(--Mavdee-border, #eaeaec);
    margin-bottom: 8px;
  }

  .mob-nav-auth-links {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 0 20px;
    border-bottom: 1px solid var(--Mavdee-border, #eaeaec);
    margin-bottom: 8px;
  }

  .mob-nav-login-btn {
    font-size: 15px;
    font-weight: 700;
    color: var(--Mavdee-pink, #ff3f6c);
    text-decoration: none;
  }

  .mob-nav-sep {
    color: var(--Mavdee-muted, #94969f);
  }

  .mob-nav-links {
    flex: 1;
    overflow-y: auto;
  }

  .mob-nav-links a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 16px;
    font-weight: 500;
    text-decoration: none;
    color: var(--Mavdee-dark, #1c1c1c);
    min-height: 48px;
  }

  .mob-nav-links a:last-child {
    border-bottom: none;
  }

  .mob-nav-bottom {
    border-top: 1px solid var(--Mavdee-border, #eaeaec);
    padding-top: 16px;
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .mob-nav-bottom a {
    padding: 11px 0;
    font-size: 14px;
    color: var(--Mavdee-muted, #94969f);
    text-decoration: none;
    border-bottom: 1px solid #f8f8f8;
    min-height: 44px;
    display: flex;
    align-items: center;
  }

  .mob-nav-bottom a:hover {
    color: var(--Mavdee-pink, #ff3f6c);
  }

  /* ── Mobile nav drawer open state ─────────────────────────── */
  body.mob-nav-open {
    overflow: hidden;
  }

  body.mob-nav-open .mob-nav-overlay {
    display: block;
  }

  body.mob-nav-open .mob-nav-drawer {
    transform: translateX(0);
  }

  /* ── Notification bell ─────────────────────────────────────── */
  .header-notif-wrap {
    position: relative;
  }

  .notif-badge {
    background: var(--Mavdee-pink, #ff3f6c) !important;
  }

  .notif-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: #fff;
    border: 1px solid var(--Mavdee-border, #eaeaec);
    border-radius: 10px;
    min-width: 300px;
    max-width: 340px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.13);
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px);
    transition: opacity 0.2s, transform 0.2s;
    z-index: 1100;
  }

  .notif-dropdown.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
  }

  .notif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--Mavdee-border, #eaeaec);
    font-size: 0.9rem;
  }

  .notif-mark-read {
    background: none;
    border: none;
    color: var(--Mavdee-pink, #ff3f6c);
    font-size: 0.78rem;
    cursor: pointer;
  }

  .notif-list {
    max-height: 320px;
    overflow-y: auto;
  }

  .notif-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 10px 16px;
    border-bottom: 1px solid #f5f5f5;
    font-size: 0.82rem;
    color: var(--Mavdee-text, #3e4152);
    text-decoration: none;
  }

  .notif-item:last-child {
    border-bottom: none;
  }

  .notif-item.unread {
    background: #fff8f9;
  }

  .notif-item:hover {
    background: var(--Mavdee-grey, #f4f4f5);
  }

  .notif-dot {
    width: 8px;
    height: 8px;
    background: var(--Mavdee-pink, #ff3f6c);
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
  }

  .notif-empty {
    padding: 20px 16px;
    text-align: center;
    color: var(--Mavdee-muted, #94969f);
    font-size: 0.85rem;
  }

  /* ── Language switcher ─────────────────────────────────────── */
  .header-lang-wrap,
  .header-currency-wrap {
    position: relative;
    display: none;
  }

  @media (min-width: 1024px) {

    .header-lang-wrap,
    .header-currency-wrap {
      display: block;
    }
  }

  .lang-btn,
  .currency-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    font-weight: 600;
  }

  .lang-dropdown,
  .currency-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: #fff;
    border: 1px solid var(--Mavdee-border, #eaeaec);
    border-radius: 8px;
    min-width: 130px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
    opacity: 0;
    pointer-events: none;
    transform: translateY(-4px);
    transition: opacity 0.18s, transform 0.18s;
    z-index: 1100;
  }

  .lang-dropdown.open,
  .currency-dropdown.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
  }

  .lang-dropdown a,
  .currency-dropdown a {
    display: block;
    padding: 9px 14px;
    font-size: 0.83rem;
    color: var(--Mavdee-text, #3e4152);
    text-decoration: none;
  }

  .lang-dropdown a:hover,
  .currency-dropdown a:hover,
  .lang-dropdown a.active,
  .currency-dropdown a.active {
    color: var(--Mavdee-pink, #ff3f6c);
    background: #fff5f7;
  }
</style>


<!-- ══════════════════════════════════════════════════════════════════
     NAVBAR JS — hamburger, account dropdown, cart toggle, search
     ══════════════════════════════════════════════════════════════════ -->
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
  (function() {
    'use strict';

    // ── Hamburger / mobile nav drawer ────────────────────────────────
    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var mobNavDrawer = document.getElementById('mobNavDrawer');
    var mobNavOverlay = document.getElementById('mobNavOverlay');
    var mobNavClose = document.getElementById('mobNavClose');

    function openMobNav() {
      document.body.classList.add('mob-nav-open');
      if (mobNavDrawer) {
        mobNavDrawer.classList.add('mob-nav-open');
        mobNavDrawer.setAttribute('aria-hidden', 'false');
      }
      if (mobNavOverlay) {
        mobNavOverlay.setAttribute('aria-hidden', 'false');
      }
      if (hamburgerBtn) {
        hamburgerBtn.setAttribute('aria-expanded', 'true');
      }
    }

    function closeMobNav() {
      document.body.classList.remove('mob-nav-open');
      if (hamburgerBtn && hamburgerBtn.offsetParent !== null) {
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        hamburgerBtn.focus();
      } else if (document.activeElement) {
        document.activeElement.blur();
      }
      if (mobNavDrawer) {
        mobNavDrawer.classList.remove('mob-nav-open');
        mobNavDrawer.setAttribute('aria-hidden', 'true');
      }
      if (mobNavOverlay) {
        mobNavOverlay.setAttribute('aria-hidden', 'true');
      }
    }
    if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobNav);
    if (mobNavClose) mobNavClose.addEventListener('click', closeMobNav);
    if (mobNavOverlay) mobNavOverlay.addEventListener('click', closeMobNav);

    // ── Cart drawer toggle ────────────────────────────────────────────
    var cartToggleBtn = document.getElementById('cartToggleBtn');
    var cartCloseBtn = document.getElementById('cartCloseBtn');
    var cartOverlay = document.getElementById('cartOverlay');
    var cartDrawer = document.getElementById('cartDrawer');

    function openCartDrawer() {
      document.body.classList.add('cart-open');
      if (cartDrawer) cartDrawer.setAttribute('aria-hidden', 'false');
      if (cartOverlay) cartOverlay.setAttribute('aria-hidden', 'false');
      if (cartToggleBtn) cartToggleBtn.setAttribute('aria-expanded', 'true');
      if (typeof loadCart === 'function') loadCart();
    }

    function closeCartDrawer() {
      document.body.classList.remove('cart-open');
      if (cartToggleBtn && cartToggleBtn.offsetParent !== null) {
        cartToggleBtn.setAttribute('aria-expanded', 'false');
        cartToggleBtn.focus();
      } else if (document.activeElement) {
        document.activeElement.blur();
      }
      if (cartDrawer) cartDrawer.setAttribute('aria-hidden', 'true');
      if (cartOverlay) cartOverlay.setAttribute('aria-hidden', 'true');
    }

    if (cartToggleBtn) cartToggleBtn.addEventListener('click', openCartDrawer);
    if (cartCloseBtn) cartCloseBtn.addEventListener('click', closeCartDrawer);
    if (cartOverlay) cartOverlay.addEventListener('click', closeCartDrawer);

    // Expose openCart / closeCart globally (used by cart.js and bottom-nav.php)
    window.openCart = openCartDrawer;
    window.closeCart = closeCartDrawer;

    // ── Account dropdown ──────────────────────────────────────────────
    var accountWrap = document.getElementById('accountDropdownWrap');
    var accountBtn = document.getElementById('accountDropdownBtn');
    var accountDrop = document.getElementById('accountDropdown');

    if (accountBtn && accountDrop) {
      accountBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = accountDrop.classList.toggle('open');
        accountBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
      document.addEventListener('click', function(e) {
        if (accountWrap && !accountWrap.contains(e.target)) {
          accountDrop.classList.remove('open');
          accountBtn.setAttribute('aria-expanded', 'false');
        }
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          accountDrop.classList.remove('open');
          accountBtn.setAttribute('aria-expanded', 'false');
        }
      });
    }

    // ── Utility: HTML escape ──────────────────────────────────────────
    function escHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // ── Auto-load cart count on page load ─────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
      fetch('/api/cart/get.php')
        .then(function(r) {
          return r.json();
        })
        .then(function(data) {
          var count = data.count || 0;
          if (typeof window.updateCartBadge === 'function') {
            window.updateCartBadge(count);
          } else {
            var badge = document.getElementById('cartBadge');
            if (badge) {
              badge.textContent = count;
              badge.style.display = count > 0 ? 'flex' : 'none';
            }
          }
        })
        .catch(function(err) {
          console.warn('Cart count load failed:', err);
        });
      fetch('/api/wishlist/get.php', {
          credentials: 'same-origin'
        })
        .then(function(r) {
          return r.json();
        })
        .then(function(data) {
          var count = data.count || 0;
          var wb = document.getElementById('wishlistBadge');
          if (wb) {
            wb.textContent = count;
            wb.style.display = count > 0 ? '' : 'none';
          }
        })
        .catch(function(err) {
          console.warn('Wishlist count load failed:', err);
        });
    });
  })();

  // ── Notification dropdown ─────────────────────────────────────────────────
  function toggleNotifDropdown() {
    var drop = document.getElementById('notifDropdown');
    var btn = document.getElementById('notifDropdownBtn');
    if (!drop) return;
    var isOpen = drop.classList.toggle('open');
    if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) loadNotifications();
  }

  function loadNotifications() {
    var list = document.getElementById('notifList');
    if (!list) return;
    fetch('/api/notifications/get.php')
      .then(function(r) {
        return r.json();
      })
      .then(function(data) {
        if (!data.notifications || !data.notifications.length) {
          list.innerHTML = '<div class="notif-empty">No new notifications</div>';
          return;
        }
        list.innerHTML = data.notifications.map(function(n) {
          return '<a href="' + escHtml(n.link || '#') + '" class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '">' +
            (n.is_read == 0 ? '<span class="notif-dot"></span>' : '<span style="width:8px;flex-shrink:0"></span>') +
            '<span>' + escHtml(n.message) + '</span></a>';
        }).join('');
      })
      .catch(function() {
        list.innerHTML = '<div class="notif-empty">Could not load notifications</div>';
      });
  }

  function markAllNotifsRead() {
    var formData = new FormData();
    formData.append('csrf_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || '');
    fetch('/api/notifications/mark_read.php', {
      method: 'POST',
      body: formData
    });

    var badge = document.getElementById('notifBadge');
    if (badge) badge.style.display = 'none';
    var items = document.querySelectorAll('.notif-item.unread');
    items.forEach(function(i) {
      i.classList.remove('unread');
      var d = i.querySelector('.notif-dot');
      if (d) d.style.display = 'none';
    });
  }

  document.addEventListener('click', function(e) {
    var wrap = document.getElementById('notifDropdownWrap');
    var drop = document.getElementById('notifDropdown');
    if (wrap && drop && !wrap.contains(e.target)) {
      drop.classList.remove('open');
    }
  });

  // ── Language / Currency dropdowns ─────────────────────────────────────────
  function toggleLangMenu(btn) {
    var drop = btn.closest('.header-lang-wrap').querySelector('.lang-dropdown');
    drop.classList.toggle('open');
    closeOtherDropdowns(drop);
  }

  function toggleCurrencyMenu(btn) {
    var drop = btn.closest('.header-currency-wrap').querySelector('.currency-dropdown');
    drop.classList.toggle('open');
    closeOtherDropdowns(drop);
  }

  function closeOtherDropdowns(except) {
    document.querySelectorAll('.lang-dropdown, .currency-dropdown, .account-dropdown').forEach(function(d) {
      if (d !== except) d.classList.remove('open');
    });
  }
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.header-lang-wrap, .header-currency-wrap')) {
      document.querySelectorAll('.lang-dropdown, .currency-dropdown').forEach(function(d) {
        d.classList.remove('open');
      });
    }
  });

  function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
</script>