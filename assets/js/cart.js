/* cart.js – Mavdee slide-in cart drawer */

// ── Toast notification ────────────────────────────────────────────────────────

function showToast(msg, type) {
    type = type || 'info';
    var toast = document.createElement('div');
    toast.setAttribute('role', 'alert');
    toast.style.cssText = [
        'position:fixed',
        'bottom:calc(90px + env(safe-area-inset-bottom))',
        'left:50%',
        'transform:translateX(-50%)',
        'background:' + (type === 'error' ? '#c0392b' : type === 'success' ? '#27ae60' : '#333'),
        'color:#fff',
        'padding:12px 24px',
        'border-radius:8px',
        'font-size:0.88rem',
        'font-weight:500',
        'z-index:99999',
        'box-shadow:0 4px 16px rgba(0,0,0,0.2)',
        'pointer-events:none',
        'opacity:0',
        'transition:opacity 0.3s',
        'max-width:calc(100vw - 32px)',
        'text-align:center',
    ].join(';');
    toast.textContent = msg;
    document.body.appendChild(toast);
    requestAnimationFrame(function () {
        toast.style.opacity = '1';
        setTimeout(function () {
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 300);
        }, 3000);
    });
}

// ── Cart open / close ─────────────────────────────────────────────────────────

function openCart() {
    document.body.classList.add('cart-open');
    loadCart();
}

function closeCart() {
    document.body.classList.remove('cart-open');
}

// ── Load & render cart ────────────────────────────────────────────────────────

async function loadCart() {
    try {
        // Determine if user is logged in (check auth status)
        let endpoint = '/api/cart/get.php'; // Default to logged-in
        try {
            const statusRes = await fetch('/api/auth/status.php');
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
                endpoint = '/api/cart/get_guest.php'; // Use guest endpoint if not logged in
            }
        } catch (e) {
            console.warn('Could not determine auth status, assuming logged-in');
        }

        const res = await fetch(endpoint);
        const data = await res.json();

        const body = document.getElementById('cartBody');
        const badge = document.getElementById('cartBadge');
        const mobileBadge = document.getElementById('mobileCartBadge');
        const footer = document.getElementById('cartFooter');
        const totalEl = document.getElementById('cartTotal');
        const savingsEl = document.getElementById('cartSavings');

        if (!body) return;

        if (!data.items || data.items.length === 0) {
            body.innerHTML = '<div style="padding:40px 20px;text-align:center;color:var(--muted);">' +
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 16px;opacity:0.4">' +
                '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>' +
                '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>' +
                '</svg><p style="margin:0;font-size:0.95rem;">Your cart is empty</p>' +
                '<a href="shop.php" style="display:inline-block;margin-top:16px;padding:10px 24px;background:var(--ink);color:#fff;border-radius:99px;font-size:0.85rem;text-decoration:none;">Shop Now</a></div>';
            if (footer) footer.style.display = 'none';
        } else {
            const currency = window._currency || '₹';

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.items.map(function (item) {
                return '<div class="cart-item" data-product-id="' + item.product_id + '" data-size="' + escHtml(item.size || '') + '" data-color="' + escHtml(item.color || '') + '">' +
                    '<img src="' + escHtml(item.image_url || '') + '" class="cart-item-img" alt="' + escHtml(item.name) + '" onerror="this.style.display=\'none\'">' +
                    '<div class="cart-item-info">' +
                    '<h3 class="cart-item-title">' + escHtml(item.name) + '</h3>' +
                    (item.size ? '<p class="cart-item-meta">Size: ' + escHtml(item.size) + '</p>' : '') +
                    (item.color ? '<p class="cart-item-meta">Color: ' + escHtml(item.color) + '</p>' : '') +
                    '<p style="font-weight:600;margin:0 0 10px;" class="cart-item-price">' + currency + formatPrice(item.price * item.qty) + '</p>' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                    '<div class="cart-qty-ctrl">' +
                    '<button type="button" data-action="dec" data-pid="' + item.product_id + '" data-size="' + escHtml(item.size || '') + '" data-color="' + escHtml(item.color || '') + '" data-price="' + item.price + '" aria-label="Decrease quantity">−</button>' +
                    '<span class="cart-qty-val">' + item.qty + '</span>' +
                    '<button type="button" data-action="inc" data-pid="' + item.product_id + '" data-size="' + escHtml(item.size || '') + '" data-color="' + escHtml(item.color || '') + '" data-price="' + item.price + '" aria-label="Increase quantity">+</button>' +
                    '</div>' +
                    '<button type="button" style="background:none;border:none;color:var(--muted);font-size:0.8rem;text-decoration:underline;cursor:pointer;" ' +
                    'data-action="remove" data-pid="' + item.product_id + '" data-size="' + escHtml(item.size || '') + '" data-color="' + escHtml(item.color || '') + '">Remove</button>' +
                    '</div></div></div>';
            }).join('');

            // Preserve existing image nodes to prevent screen blink / image flickering
            const existingImages = {};
            body.querySelectorAll('.cart-item').forEach(function (el) {
                const key = el.dataset.productId + '|' + (el.dataset.size || '') + '|' + (el.dataset.color || '');
                const img = el.querySelector('.cart-item-img');
                if (img) existingImages[key] = img;
            });

            tempDiv.querySelectorAll('.cart-item').forEach(function (el) {
                const key = el.dataset.productId + '|' + (el.dataset.size || '') + '|' + (el.dataset.color || '');
                if (existingImages[key]) {
                    const newImg = el.querySelector('.cart-item-img');
                    if (newImg) newImg.replaceWith(existingImages[key]);
                }
            });

            body.innerHTML = '';
            while (tempDiv.firstChild) {
                body.appendChild(tempDiv.firstChild);
            }

            if (footer) footer.style.display = '';
            if (totalEl) totalEl.textContent = currency + formatPrice(data.total);
            if (savingsEl) {
                if (data.savings > 0) {
                    savingsEl.textContent = '🎉 You saved ' + currency + formatPrice(data.savings);
                    savingsEl.style.display = '';
                } else {
                    savingsEl.style.display = 'none';
                }
            }
        }

        const count = data.count || 0;
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        }
        if (mobileBadge) {
            mobileBadge.textContent = count;
            mobileBadge.style.display = count > 0 ? '' : 'none';
        }
    } catch (e) {
        console.error('Cart load error:', e);
    }
}

// ── Add to cart ───────────────────────────────────────────────────────────────

async function addToCart(formOrData) {
    let formData;
    if (formOrData instanceof HTMLFormElement) {
        formData = new FormData(formOrData);
    } else {
        formData = new FormData();
        for (const key in formOrData) {
            formData.append(key, formOrData[key]);
        }
    }
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken && !formData.has('csrf_token')) formData.set('csrf_token', csrfToken.getAttribute('content'));

    try {
        // Determine endpoint based on login status
        let endpoint = '/api/cart/add.php'; // Default to logged-in
        try {
            const statusRes = await fetch('/api/auth/status.php');
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
                endpoint = '/api/cart/add_guest.php'; // Use guest endpoint if not logged in
            }
        } catch (e) {
            console.warn('Could not determine auth status, assuming logged-in');
        }

        const res = await fetch(endpoint, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success || data.ok) {
            await loadCart();
            openCart();
            showToast('Added to cart!', 'success');
            return true;
        } else if (data.error === 'Login required.' || data.require_login) {
            showToast('Login required to add items to cart.', 'error');
            setTimeout(() => {
                const next = encodeURIComponent(window.location.pathname + window.location.search);
                window.location = '/login.php?next=' + next;
            }, 1500);
            return false;
        } else {
            showToast(data.error || 'Could not add item to cart.', 'error');
            return false;
        }
    } catch (e) {
        console.error('Add to cart error:', e);
        showToast('Could not add item to cart. Please try again.', 'error');
        return false;
    }
}

// ── Qty adjustment with optimistic UI & debounce ──────────────────────────────

var _qtyTimers = {};

function adjustQty(btn, productId, delta, size, color, unitPrice) {
    var ctrl = btn.closest('.cart-qty-ctrl');
    if (!ctrl) return;

    var qtyEl = ctrl.querySelector('.cart-qty-val');
    var cartItem = ctrl.closest('.cart-item');
    var priceEl = cartItem ? cartItem.querySelector('.cart-item-price') : null;
    var currency = window._currency || '₹';

    // Optimistic UI: update count immediately
    var currentQty = parseInt(qtyEl.textContent, 10) || 1;
    var newQty = currentQty + delta;
    if (newQty < 1) newQty = 0;

    qtyEl.textContent = newQty > 0 ? newQty : '…';
    if (priceEl && newQty > 0) {
        priceEl.textContent = currency + formatPrice(unitPrice * newQty);
    }

    // Disable ctrl buttons while pending
    var buttons = ctrl.querySelectorAll('button');
    buttons.forEach(function (b) { b.disabled = true; });

    // Debounce: cancel prior pending call for the same item key
    var key = productId + '|' + size + '|' + color;
    clearTimeout(_qtyTimers[key]);
    _qtyTimers[key] = setTimeout(function () {
        _sendQtyUpdate(productId, delta, size, color, ctrl, buttons);
    }, 300);
}

async function _sendQtyUpdate(productId, delta, size, color, ctrl, buttons) {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    try {
        let endpoint = '/api/cart/update.php';
        try {
            const statusRes = await fetch('/api/auth/status.php');
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
                endpoint = '/api/cart/update_guest.php';
            }
        } catch (e) { }

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('change', delta);
        formData.append('size', size || '');
        formData.append('color', color || '');
        formData.append('csrf_token', csrfToken);

        const res = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.error || 'Could not update quantity.', 'error');
        }
        // Reload to sync server-authoritative state
        await loadCart();
    } catch (e) {
        console.error('Update qty error:', e);
        showToast('Could not update quantity. Please try again.', 'error');
        await loadCart();
    } finally {
        if (buttons) {
            buttons.forEach(function (b) { b.disabled = false; });
        }
    }
}

// ── Legacy updateQty (kept for backward-compat with any inline callers) ───────

async function updateQty(productId, qty, size, color) {
    const body = new FormData();
    body.append('product_id', productId);
    body.append('qty', qty);
    body.append('size', size || '');
    body.append('color', color || '');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) body.append('csrf_token', csrfMeta.getAttribute('content'));

    try {
        // Determine endpoint based on login status
        let endpoint = '/api/cart/update.php'; // Default to logged-in
        try {
            const statusRes = await fetch('/api/auth/status.php');
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
                endpoint = '/api/cart/update_guest.php'; // Use guest endpoint if not logged in
            }
        } catch (e) {
            console.warn('Could not determine auth status, assuming logged-in');
        }

        await fetch(endpoint, { method: 'POST', body: body });
        await loadCart();
    } catch (e) {
        console.error('Update qty error:', e);
    }
}

// ── Remove from cart ──────────────────────────────────────────────────────────

async function removeFromCart(productId, size, color) {
    const body = new FormData();
    body.append('product_id', productId);
    body.append('qty', 0);
    body.append('size', size || '');
    body.append('color', color || '');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) body.append('csrf_token', csrfMeta.getAttribute('content'));

    try {
        // Determine endpoint based on login status
        let endpoint = '/api/cart/update.php'; // Default to logged-in
        try {
            const statusRes = await fetch('/api/auth/status.php');
            const statusData = await statusRes.json();
            if (!statusData.logged_in) {
                endpoint = '/api/cart/update_guest.php'; // Use guest endpoint if not logged in
            }
        } catch (e) {
            console.warn('Could not determine auth status, assuming logged-in');
        }

        await fetch(endpoint, { method: 'POST', body: body });
        await loadCart();
    } catch (e) {
        console.error('Remove from cart error:', e);
    }
}

// ── Buy now ───────────────────────────────────────────────────────────────────

async function buyNow(formOrData) {
    const ok = await addToCart(formOrData);
    if (ok) window.location = '/checkout.php';
}

// ── PDP Gallery Global Functions (prevents inline 'not defined' errors) ────
window.pdpChangeImage = function (index, el) {
    try {
        var galleryWrap = el ? el.closest('.pdp-gallery, .pdp-gallary') : document;
        if (!galleryWrap) galleryWrap = document;

        galleryWrap.querySelectorAll('.main-img-slide').forEach(function (img) {
            img.classList.remove('active-slide');
        });
        var slide = document.getElementById('pdp-slide-' + index);
        if (slide) {
            slide.classList.add('active-slide');
            if (window.innerWidth >= 1024) {
                var swiperSlide = slide.closest('.swiper-slide');
                if (swiperSlide) swiperSlide.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        galleryWrap.querySelectorAll('.pdp-thumb').forEach(function (t) {
            t.classList.remove('active');
        });
        if (el) el.classList.add('active');

        if (typeof pdpSwiper !== 'undefined' && pdpSwiper) {
            pdpSwiper.slideTo(index);
        }
    } catch (e) {
        console.error('Image swap error:', e);
    }
};
window.changeImage = window.pdpChangeImage;

// ── Wishlist logic ────────────────────────────────────────────────────────────

async function toggleWishlist(productId, btn, e) {
    if (e && e.preventDefault) {
        e.preventDefault();
        e.stopPropagation();
    } else if (window.event && window.event.preventDefault) {
        window.event.preventDefault();
        window.event.stopPropagation();
    }
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('csrf_token', csrfToken);

        const res = await fetch('/api/wishlist/toggle.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        let data = null;
        try {
            data = await res.json();
        } catch (parseError) {
            data = null;
        }

        if (res.status === 401 || (data && (data.error === 'Login required.' || data.require_login))) {
            window.location = '/login.php?next=' + encodeURIComponent(window.location.pathname + window.location.search);
            return;
        }

        if (!res.ok) {
            throw new Error((data && data.error) ? data.error : 'Server responded with status ' + res.status);
        }

        if (data && data.success) {
            if (data.action === 'added') {
                if (btn) {
                    btn.classList.add('active', 'wishlisted');
                    const path = btn.querySelector('svg path');
                    if (path) { path.setAttribute('fill', '#ff3f6c'); path.setAttribute('stroke', '#ff3f6c'); }
                    const icon = btn.querySelector('i.fa-heart');
                    if (icon) { icon.classList.remove('fa-regular'); icon.classList.add('fa-solid'); icon.style.color = '#ff3f6c'; }
                    if (btn.classList.contains('pdp-ymal-wishlist') || btn.textContent.trim() === '♡') {
                        btn.textContent = '♥';
                        btn.style.color = '#ff3f6c';
                    }
                }
                showToast('Added to wishlist!', 'success');
            } else {
                if (btn) {
                    btn.classList.remove('active', 'wishlisted');
                    const path = btn.querySelector('svg path');
                    if (path) { path.setAttribute('fill', 'none'); path.setAttribute('stroke', 'currentColor'); }
                    const icon = btn.querySelector('i.fa-heart');
                    if (icon) { icon.classList.remove('fa-solid'); icon.classList.add('fa-regular'); icon.style.color = ''; }
                    if (btn.classList.contains('pdp-ymal-wishlist') || btn.textContent.trim() === '♥') {
                        btn.textContent = '♡';
                        btn.style.color = '';
                    }
                }
                showToast('Removed from wishlist', 'info');
            }
            loadWishlistCount();
        } else {
            showToast((data && data.error) || 'Could not update wishlist.', 'error');
        }
    } catch (e) {
        console.error('Wishlist error:', e);
        showToast('Could not update wishlist. Ensure API exists.', 'error');
    }
}

async function loadWishlistCount() {
    try {
        // Check login first
        const statusRes = await fetch('/api/auth/status.php', {
            credentials: 'same-origin'
        });
        const statusData = await statusRes.json();

        if (!statusData.logged_in) {
            // If not logged in → hide badge and STOP
            const badge = document.getElementById('wishlistBadge');
            if (badge) badge.style.display = 'none';
            return;
        }

        // Now call wishlist API
        const res = await fetch('/api/wishlist/get.php', {
            credentials: 'same-origin'
        });
        if (!res.ok) return;

        const data = await res.json();

        const badge = document.getElementById('wishlistBadge');
        if (badge) {
            const count = data.count || 0;
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        }
    } catch (e) {
        console.warn('Wishlist load skipped');
    }
}

// ── helpers ──────────────────────────────────────────────────────────────────

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escJs(str) {
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function formatPrice(n) {
    return Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// ── init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    loadCart();
    loadWishlistCount();

    // Checkout button loading state
    var checkoutBtn = document.querySelector('.btn-checkout');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            if (checkoutBtn.dataset.loading) return;
            checkoutBtn.dataset.loading = '1';
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Please wait…';
            // Navigate after a brief delay; reset if navigation doesn't proceed
            var navTimer = setTimeout(function () {
                window.location = 'checkout.php';
            }, 100);
            // Reset state if page is still here (e.g. navigation cancelled)
            window.addEventListener('focus', function resetBtn() {
                window.removeEventListener('focus', resetBtn);
                clearTimeout(navTimer);
                checkoutBtn.disabled = false;
                delete checkoutBtn.dataset.loading;
                checkoutBtn.textContent = 'Secure Checkout';
            }, { once: true });
        });
    }

    // Delegate cart body events (attached once; survives loadCart re-renders via cartBodyEl ref)
    var cartBodyEl = document.getElementById('cartBody');
    if (cartBodyEl) {
        cartBodyEl.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            if (!btn) return;
            var action = btn.dataset.action;
            var pid = btn.dataset.pid;
            var size = btn.dataset.size || '';
            var color = btn.dataset.color || '';
            if (action === 'inc') {
                adjustQty(btn, pid, 1, size, color, Number(btn.dataset.price));
            } else if (action === 'dec') {
                adjustQty(btn, pid, -1, size, color, Number(btn.dataset.price));
            } else if (action === 'remove') {
                removeFromCart(pid, size, color);
            }
        });
    }

    // ── Delegated Gallery Zoom ──────────────────────────────────────────────────
    document.body.addEventListener('mousemove', function (e) {
        var wrap = e.target.closest('.pdp-main, .pdp-main-image-wrap');
        if (!wrap) return;
        var activeImg = wrap.querySelector('.main-img-slide.active-slide') || wrap.querySelector('img');
        if (!activeImg) return;
        var rect = wrap.getBoundingClientRect();
        var x = ((e.clientX - rect.left) / rect.width) * 100;
        var y = ((e.clientY - rect.top) / rect.height) * 100;
        activeImg.style.transformOrigin = x + '% ' + y + '%';
    });

    document.body.addEventListener('mouseout', function (e) {
        var wrap = e.target.closest('.pdp-main, .pdp-main-image-wrap');
        if (!wrap) return;
        if (!wrap.contains(e.relatedTarget)) {
            var activeImg = wrap.querySelector('.main-img-slide.active-slide') || wrap.querySelector('img');
            if (activeImg) activeImg.style.transformOrigin = 'center center';
        }
    });

    // ── Fullscreen Modal Setup ──────────────────────────────────────────────────
    var modal = document.getElementById('pdp-fullscreen-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'pdp-fullscreen-modal';
        modal.innerHTML = '<button id="pdp-modal-close" aria-label="Close">&times;</button>' +
            '<div id="pdp-modal-content"><img id="pdp-modal-img" src="" alt="Fullscreen Image"></div>';
        document.body.appendChild(modal);
    }

    var modalImg = document.getElementById('pdp-modal-img');

    function closePdpModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('pdp-modal-close').addEventListener('click', closePdpModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.id === 'pdp-modal-content') closePdpModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closePdpModal();
    });


    // Delegate wishlist button clicks globally for elements missing the inline onclick handler
    document.body.addEventListener('click', function (e) {
        var wishBtn = e.target.closest('.wishlist-btn, .wishlist-btn-card, .p-card__wish, .prod-card-wishlist');
        if (wishBtn && wishBtn.dataset.productId) {
            if (!wishBtn.hasAttribute('onclick')) {
                e.preventDefault();
                e.stopPropagation();
                toggleWishlist(wishBtn.dataset.productId, wishBtn, e);
            }
        }

        // Delegate click for Delivery Location button
        var deliveryBtn = e.target.closest('.delivery-location, .header-delivery-row');
        if (deliveryBtn) {
            e.preventDefault();
            var currentPincode = localStorage.getItem('user_pincode') || '';
            var pincode = prompt('Enter your Delivery Pincode:', currentPincode);

            if (pincode !== null && pincode.trim().length >= 4) {
                localStorage.setItem('user_pincode', pincode.trim());
                deliveryBtn.innerHTML = 'Delivering to <b>' + pincode.trim() + '</b> <span class="delivery-chevron">▾</span>';
                if (typeof showToast === 'function') {
                    showToast('Delivery location updated to ' + pincode.trim(), 'success');
                }
            }
        }

        // Delegate click for opening Fullscreen Modal
        var mainWrapClick = e.target.closest('.pdp-main, .pdp-main-image-wrap');
        if (mainWrapClick) {
            var clickedImg = e.target.closest('img.main-img-slide');
            var activeImg = clickedImg || mainWrapClick.querySelector('.main-img-slide.active-slide') || mainWrapClick.querySelector('img');
            if (activeImg && modalImg) {
                modalImg.src = activeImg.src;
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        // PDP Gallery Image Swap (Fallback if inline onclick is missing)
        var thumb = e.target.closest('.pdp-thumb');
        if (thumb && !thumb.hasAttribute('onclick')) {
            var galleryWrap = thumb.closest('.pdp-gallery, .pdp-gallary') || document;
            var mainWrap = galleryWrap.querySelector('.pdp-main, .pdp-main-image-wrap');
            if (mainWrap) {
                galleryWrap.querySelectorAll('.pdp-thumb').forEach(function (t) {
                    t.classList.remove('active');
                });
                thumb.classList.add('active');

                var slides = mainWrap.querySelectorAll('.main-img-slide');
                if (slides.length > 0) {
                    var thumbArray = Array.from(galleryWrap.querySelectorAll('.pdp-thumb'));
                    var idx = thumbArray.indexOf(thumb);
                    if (idx > -1 && slides[idx]) {
                        slides.forEach(function (s) { s.classList.remove('active-slide'); });
                        slides[idx].classList.add('active-slide');
                    }
                } else {
                    var mainImg = mainWrap.querySelector('img');
                    if (mainImg) {
                        var childImg = thumb.querySelector('img');
                        mainImg.src = thumb.dataset.large || thumb.dataset.src || (childImg ? childImg.src : thumb.src);
                    }
                }
            }
        }
    });
});
