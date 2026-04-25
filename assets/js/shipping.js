/**
 * assets/js/shipping.js
 * Pincode → dynamic shipping rates in checkout.
 * Debounced 500 ms. Renders options below the pincode field.
 */
(function () {
    'use strict';

    const CURRENCY = window.SITE_CURRENCY || '₹';

    function init() {
        const pincodeInput = document.getElementById('field_pincode') || document.getElementById('pincode');
        if (!pincodeInput) return;

        // Create container for shipping options
        let shippingOptions = document.getElementById('shippingOptionsWrap');
        if (!shippingOptions) {
            shippingOptions = document.createElement('div');
            shippingOptions.id = 'shippingOptionsWrap';
            shippingOptions.style.cssText = 'margin-top:12px;';
            pincodeInput.parentNode.insertAdjacentElement('afterend', shippingOptions);
        }

        // Inject styles
        if (!document.getElementById('shippingRateStyles')) {
            const style = document.createElement('style');
            style.id = 'shippingRateStyles';
            style.textContent = `
                .shipping-option {
                    display:flex; align-items:center; gap:12px;
                    padding:12px 14px; margin-bottom:8px;
                    border:1.5px solid var(--border,#e8e4df);
                    border-radius:10px; cursor:pointer;
                    transition:border-color .2s, background .2s;
                    background:#fff;
                }
                .shipping-option:hover { border-color:var(--gold,#c9a96e); }
                .shipping-option input[type=radio] { accent-color:var(--maroon,#8b1a2e); }
                .shipping-option-label { flex:1; }
                .shipping-option-name { font-weight:600; font-size:.92rem; margin:0 0 2px; }
                .shipping-option-meta { font-size:.82rem; color:var(--muted,#888); }
                .shipping-option-price { font-weight:700; font-size:.95rem; white-space:nowrap; }
                .shipping-option-price.free { color:var(--maroon,#8b1a2e); }
                .shipping-loading { color:var(--muted,#888); font-size:.88rem; padding:8px 0; }
                .shipping-error   { color:#c0392b; font-size:.88rem; padding:8px 0; }
            `;
            document.head.appendChild(style);
        }

        let debounceTimer  = null;
        let selectedRate   = null;

        function fetchRates(pincode) {
            shippingOptions.innerHTML = '<p class="shipping-loading">Calculating shipping…</p>';

            // Get current cart total from DOM
            const totalEl = document.getElementById('summaryTotal') || document.getElementById('summarySubtotal');
            const totalText = totalEl ? totalEl.textContent.replace(/[^\d.]/g, '') : '0';
            const cartTotal = parseFloat(totalText) || 0;

            fetch('/api/shipping/rates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pincode: pincode, cart_total: cartTotal })
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    shippingOptions.innerHTML = `<p class="shipping-error">${escHtml(data.error)}</p>`;
                    return;
                }
                renderOptions(data.rates || []);
            })
            .catch(() => {
                shippingOptions.innerHTML = '<p class="shipping-error">Could not load shipping rates. Default rate applied.</p>';
            });
        }

        function renderOptions(rates) {
            if (!rates.length) {
                shippingOptions.innerHTML = '<p class="shipping-error">No shipping options available for this pincode.</p>';
                return;
            }
            shippingOptions.innerHTML = rates.map((rate, i) => {
                const priceStr = rate.price === 0
                    ? '<span class="shipping-option-price free">Free</span>'
                    : `<span class="shipping-option-price">${escHtml(CURRENCY + rate.price)}</span>`;
                return `
                <label class="shipping-option">
                    <input type="radio" name="shipping_option" value="${rate.price}" data-rate-name="${escHtml(rate.name)}" ${i === 0 ? 'checked' : ''}>
                    <div class="shipping-option-label">
                        <p class="shipping-option-name">${escHtml(rate.name)}</p>
                        <p class="shipping-option-meta">${escHtml(rate.days)}${rate.estimate ? ' · Est. ' + escHtml(rate.estimate) : ''}${rate.cod ? ' · COD available' : ''}</p>
                    </div>
                    ${priceStr}
                </label>`;
            }).join('');

            // Update shipping line in order summary on selection change
            shippingOptions.querySelectorAll('input[type=radio]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    updateShippingTotal(Number(this.value));
                });
            });

            // Trigger for initially checked option
            const first = shippingOptions.querySelector('input[type=radio]:checked');
            if (first) updateShippingTotal(Number(first.value));
        }

        function updateShippingTotal(shippingPrice) {
            selectedRate = shippingPrice;
            const shippingEl = document.getElementById('summaryShipping');
            if (shippingEl) {
                shippingEl.textContent = shippingPrice === 0 ? 'Free' : (CURRENCY + Number(shippingPrice).toFixed(2));
            }
            // Recompute grand total
            const subtotalEl = document.getElementById('summarySubtotal');
            const totalEl    = document.getElementById('summaryTotal');
            if (subtotalEl && totalEl) {
                const sub   = parseFloat(subtotalEl.textContent.replace(/[^\d.]/g, '')) || 0;
                const grand = sub + shippingPrice;
                totalEl.textContent = CURRENCY + grand.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Also set a hidden input so the form can submit the selected shipping
            let hidden = document.getElementById('selectedShippingCost');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.id   = 'selectedShippingCost';
                hidden.name = 'shipping_cost';
                const form  = document.getElementById('checkoutForm');
                if (form) form.appendChild(hidden);
            }
            hidden.value = shippingPrice;
        }

        pincodeInput.addEventListener('input', function () {
            const val = this.value.trim();
            clearTimeout(debounceTimer);
            if (!/^\d{6}$/.test(val)) {
                shippingOptions.innerHTML = '';
                return;
            }
            debounceTimer = setTimeout(() => fetchRates(val), 500);
        });

        // Fetch on page load if pincode already filled
        const existing = pincodeInput.value.trim();
        if (/^\d{6}$/.test(existing)) {
            fetchRates(existing);
        }
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
