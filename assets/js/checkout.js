/**
 * assets/js/checkout.js
 * Checkout-specific JS: address autofill, quantity AJAX updates,
 * "use previous address" logic, and checkout form validation.
 * Loaded only on checkout.php.
 */
(function () {
    'use strict';

    // ── Address helpers ───────────────────────────────────────────────────────

    /**
     * Select a saved address card and populate the address form fields.
     * @param {HTMLElement} card
     * @param {number} id
     */
    window.selectSavedAddress = function (card, id) {
        document.querySelectorAll('.address-card').forEach(function (c) {
            c.classList.remove('selected');
        });
        card.classList.add('selected');

        var fields = ['name', 'phone', 'address', 'city', 'state', 'pincode'];
        fields.forEach(function (f) {
            var el = document.getElementById('field_' + f);
            if (el) el.value = card.dataset[f] || '';
        });

        var newSec = document.getElementById('newAddressSection');
        if (newSec) newSec.style.display = 'none';
    };

    /**
     * Show the "add new address" form and clear existing values.
     */
    window.showNewAddressForm = function () {
        document.querySelectorAll('.address-card').forEach(function (c) {
            c.classList.remove('selected');
        });
        var newSec = document.getElementById('newAddressSection');
        if (newSec) newSec.style.display = '';

        ['name', 'phone', 'address', 'city', 'state', 'pincode'].forEach(function (f) {
            var el = document.getElementById('field_' + f);
            if (el) el.value = '';
        });
    };

    /**
     * Auto-fill address form fields from the most recently saved address (first card).
     */
    window.usePreviousAddress = function () {
        var cards = document.querySelectorAll('.address-card');
        if (!cards.length) return;
        var firstCard = cards[0];
        window.selectSavedAddress(firstCard, parseInt(firstCard.dataset.id, 10));
        if (typeof showToast === 'function') {
            showToast('Previous address loaded.', 'info', 2000);
        }
    };

    // ── Quantity update helpers ───────────────────────────────────────────────

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function recalcTotals() {
        var CURRENCY = window.CURRENCY || '₹';
        var FREE_SHIPPING = window.FREE_SHIPPING || 999;
        var SHIP_COST = window.SHIP_COST || 99;

        var subtotal = 0;
        document.querySelectorAll('#summaryItems .cart-item').forEach(function (item) {
            var qtyEl = item.querySelector('.qty-input') || item.querySelector('.qty-ctrl span');
            var qty = qtyEl ? (parseInt(qtyEl.value !== undefined ? qtyEl.value : qtyEl.textContent) || 0) : 0;
            var price = parseFloat(item.dataset.price) || 0;
            var lt = qty * price;
            var lineTotal = item.querySelector('.line-total');
            if (lineTotal) lineTotal.textContent = lt.toFixed(2);
            subtotal += lt;
        });

        var shipping = subtotal >= FREE_SHIPPING ? 0 : (subtotal === 0 ? 0 : SHIP_COST);
        var subtotalEl = document.getElementById('summarySubtotal');
        var shippingEl = document.getElementById('summaryShipping');
        var totalEl = document.getElementById('summaryTotal');
        if (subtotalEl) subtotalEl.textContent = CURRENCY + subtotal.toFixed(2);
        if (shippingEl) shippingEl.textContent = shipping === 0 ? 'Free' : CURRENCY + shipping.toFixed(2);
        if (totalEl) totalEl.textContent = CURRENCY + (subtotal + shipping).toFixed(2);
    }

    /**
     * Handles click on +/- quantity buttons.
     * @param {HTMLElement} btn
     * @param {number} delta   +1 or -1
     */
    window.checkoutChangeQty = function (btn, delta) {
        var item = btn.closest('.cart-item');
        if (!item) return;

        var qtyInput = item.querySelector('.qty-input');
        var qty = qtyInput ? (parseInt(qtyInput.value) + delta) : 1;

        if (qty < 1) {
            // Remove item when going below 1
            var removeBtn = item.querySelector('.remove-item-btn');
            if (removeBtn) window.checkoutRemoveItem(removeBtn);
            return;
        }

        if (qtyInput) qtyInput.value = qty;
        recalcTotals();

        var productId = item.dataset.productId;
        var size = item.dataset.size || '';
        var color = item.dataset.color || '';
        var fd = new URLSearchParams({ product_id: productId, qty: qty, size: size, color: color });
        fd.set('csrf_token', getCsrfToken());

        fetch('/api/cart/update.php', { method: 'POST', body: fd }).then(function () {
            if (typeof loadCart === 'function') loadCart();
        }).catch(function (e) {
            console.error('Cart update error:', e);
        });
    };

    /**
     * Handles direct editing of the qty input field.
     * @param {HTMLInputElement} input
     */
    window.checkoutQtyInputChanged = function (input) {
        var item = input.closest('.cart-item');
        if (!item) return;

        var qty = parseInt(input.value);
        if (isNaN(qty) || qty < 1) {
            qty = 1;
            input.value = 1;
        }
        recalcTotals();

        var productId = item.dataset.productId;
        var size = item.dataset.size || '';
        var color = item.dataset.color || '';
        var fd = new URLSearchParams({ product_id: productId, qty: qty, size: size, color: color });
        fd.set('csrf_token', getCsrfToken());

        fetch('/api/cart/update.php', { method: 'POST', body: fd }).then(function () {
            if (typeof loadCart === 'function') loadCart();
        }).catch(function (e) {
            console.error('Cart update error:', e);
        });
    };

    /**
     * Remove a cart item from the checkout summary.
     * @param {HTMLElement} btn
     */
    window.checkoutRemoveItem = function (btn) {
        var item = btn.closest('.cart-item');
        if (!item) return;

        var productId = item.dataset.productId;
        var size = item.dataset.size || '';
        var color = item.dataset.color || '';
        var fd = new URLSearchParams({ product_id: productId, qty: 0, size: size, color: color });
        fd.set('csrf_token', getCsrfToken());

        item.style.opacity = '0.4';
        fetch('/api/cart/update.php', { method: 'POST', body: fd }).then(function () {
            item.remove();
            recalcTotals();
            if (typeof loadCart === 'function') loadCart();
            if (!document.querySelector('#summaryItems .cart-item')) {
                window.location.href = '/shop.php';
            }
        }).catch(function (e) {
            item.style.opacity = '';
            console.error('Cart remove error:', e);
        });
    };

    // ── Checkout form validation ──────────────────────────────────────────────

    /**
     * Validate the checkout shipping form before submission.
     * @returns {boolean}
     */
    window.validateCheckoutForm = function () {
        var required = ['field_name', 'field_phone', 'field_address', 'field_city', 'field_pincode'];
        for (var i = 0; i < required.length; i++) {
            var el = document.getElementById(required[i]);
            if (el && !el.value.trim()) {
                el.focus();
                if (typeof showToast === 'function') {
                    showToast('Please fill in all required shipping details.', 'error');
                }
                return false;
            }
        }
        var email = document.getElementById('field_email');
        if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            email.focus();
            if (typeof showToast === 'function') {
                showToast('Please enter a valid email address.', 'error');
            }
            return false;
        }
        return true;
    };

    // ── Auto-init ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-select the default saved address on load
        var defaultCard = document.querySelector('.address-card.selected');
        if (defaultCard) {
            window.selectSavedAddress(defaultCard, parseInt(defaultCard.dataset.id, 10));
        }
    });
})();
