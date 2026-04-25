<?php
if (defined('BOTTOM_NAV_INCLUDED')) return;
define('BOTTOM_NAV_INCLUDED', true);
?>

<nav class="mobile-bottom-nav" id="bottomNav" aria-label="Mobile Navigation">
  <!-- Home -->
  <a href="index.php" class="nav-item" data-nav="home">
    <span class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        <polyline points="9 22 9 12 15 12 15 22" />
      </svg>
    </span>
    <span class="nav-label">Home</span>
  </a>

  <!-- Shop -->
  <a href="shop.php" class="nav-item" data-nav="shop">
    <span class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="3" y="8" width="18" height="13" rx="2" />
        <path d="M8 8C8 5.79 9.79 4 12 4C14.21 4 16 5.79 16 8" />
      </svg>
    </span>
    <span class="nav-label">Shop</span>
  </a>

  <!-- User Profile -->
  <a href="dashboard.php" class="nav-item" data-nav="profile">
    <span class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
      </svg>
    </span>
    <span class="nav-label">Profile</span>
  </a>

  <!-- Orders -->
  <a href="my-orders.php" class="nav-item" data-nav="orders">
    <span class="icon-wrap">
      <!-- Receipt / list icon for Orders -->
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
        <line x1="8" y1="13" x2="16" y2="13" />
        <line x1="8" y1="17" x2="16" y2="17" />
        <line x1="8" y1="9" x2="11" y2="9" />
      </svg>
    </span>
    <span class="nav-label">Orders</span>
  </a>

  <!-- Cart -->
  <button type="button" class="nav-item" data-nav="cart" id="mobileCartButton">
    <span class="icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <circle cx="9" cy="21" r="1" />
        <circle cx="20" cy="21" r="1" />
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
      </svg>
      <span class="nav-badge" id="mobileCartBadge" style="display:none">0</span>
    </span>
    <span class="nav-label">Cart</span>
  </button>
</nav>

<?php
// Expose login state to JS
?>
<script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
  (function() {
    // Active state detection
    var currentPath = window.location.pathname;
    var navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    navItems.forEach(function(item) {
      var href = item.getAttribute('href');
      if (href) {
        var itemPath = href.split('?')[0];
        var normalizedCurrent = currentPath.replace(/\/index\.php$/, '/').replace(/\/$/, '') || '/';
        var normalizedItem    = '/' + itemPath.replace(/^\//, '').replace(/index\.php$/, '').replace(/\/$/, '');
        if (normalizedItem === '/') normalizedItem = '/';
        if (normalizedCurrent === normalizedItem ||
            (normalizedCurrent === '' && normalizedItem === '/') ||
            (normalizedCurrent !== '/' && normalizedCurrent !== '' && normalizedCurrent.endsWith('/' + itemPath.split('?')[0]))) {
          item.classList.add('active');
        }
      }
    });

    // Cart button handler
    var cartBtn = document.getElementById('mobileCartButton');
    if (cartBtn) {
      cartBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.openCart === 'function') {
          window.openCart();
        } else if (typeof openCart === 'function') {
          openCart();
        }
      });
    }

    // Scroll hide/show with RAF + ticking
    var lastScrollY = window.scrollY;
    var bottomNav = document.getElementById('bottomNav');
    var ticking = false;

    if (bottomNav) {
      window.addEventListener('scroll', function() {
        if (!ticking) {
          requestAnimationFrame(function() {
            if (window.innerWidth > 1023) {
              ticking = false;
              return;
            }
            var currentScrollY = window.scrollY;
            if (currentScrollY > lastScrollY && currentScrollY > 100) {
              bottomNav.style.transform = 'translateY(100%)';
            } else if (currentScrollY < lastScrollY || currentScrollY <= 50) {
              bottomNav.style.transform = 'translateY(0)';
            }
            lastScrollY = currentScrollY;
            ticking = false;
          });
          ticking = true;
        }
      }, {
        passive: true
      });

      window.addEventListener('resize', function() {
        if (window.innerWidth > 1023) {
          bottomNav.style.transform = '';
        }
      });
    }
  })();

  // Global badge update functions
  window.updateCartBadge = function(count) {
    var desktopBadge = document.getElementById('cartBadge');
    var mobileBadge = document.getElementById('mobileCartBadge');
    if (desktopBadge) {
      desktopBadge.textContent = count;
      desktopBadge.style.display = count > 0 ? 'flex' : 'none';
    }
    if (mobileBadge) {
      mobileBadge.textContent = count;
      mobileBadge.style.display = count > 0 ? 'flex' : 'none';
    }
  };

  window.updateWishlistBadge = function(count) {
    var desktopBadge = document.getElementById('wishlistBadge');
    if (desktopBadge) {
      desktopBadge.textContent = count;
      desktopBadge.style.display = count > 0 ? 'flex' : 'none';
    }
  };
</script>