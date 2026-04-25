/**
 * assets/js/pincode.js
 *
 * Frontend pincode serviceability checker.
 * Drop this file in assets/js/ and include it on any page that has a pincode input.
 *
 * Auto-discovers:
 *   Inputs:  #pincodeInput, #checkoutPincode, [name="pincode"]
 *   Results: #pincodeResult, #checkoutPincodeResult
 *
 * Public API:
 *   window.checkPincode(pin, resultEl, inputEl)  — async, can be called manually
 */
(function () {
  'use strict';

  var ENDPOINT = '/api/pincode-check.php';
  var SESSION_KEY = 'delhivery_pincode';
  var debounceTimer = null;

  // ── HTML escape ─────────────────────────────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Show result message ──────────────────────────────────────────────────────
  function showPincodeResult(el, type, html) {
    if (!el) return;
    el.className = 'pincode-result pincode-result--' + type;
    el.innerHTML = html;
  }

  // ── Enable / disable COD option ──────────────────────────────────────────────
  function updateCodAvailability(available) {
    var codInput = document.getElementById('paymentCod')
      || document.querySelector('[value="cod"]');

    if (!codInput) return;

    var noteId = 'codUnavailableNote';
    var existingNote = document.getElementById(noteId);

    if (available) {
      codInput.disabled = false;
      if (existingNote) existingNote.parentNode.removeChild(existingNote);
    } else {
      codInput.disabled = true;

      if (!existingNote) {
        var note = document.createElement('p');
        note.id = noteId;
        note.style.cssText = 'color:#c0392b;font-size:.85em;margin:.25em 0 0';
        note.textContent = 'Cash on Delivery is not available for this pincode.';
        codInput.parentNode.appendChild(note);
      }

      // Switch away from COD if it was selected
      if (codInput.checked) {
        var onlineInput = document.querySelector('[value="online"]')
          || document.querySelector('[value="prepaid"]');
        if (onlineInput) {
          onlineInput.checked = true;
          onlineInput.dispatchEvent(new Event('change'));
        }
      }
    }
  }

  // ── Core check function ──────────────────────────────────────────────────────
  window.checkPincode = function (pin, resultEl, inputEl) {
    if (!pin || !/^\d{6}$/.test(pin)) {
      showPincodeResult(resultEl, 'error', 'Please enter a valid 6-digit pincode.');
      return Promise.resolve(null);
    }

    showPincodeResult(resultEl, 'loading', 'Checking&hellip;');
    if (inputEl) inputEl.setAttribute('aria-busy', 'true');

    return fetch(ENDPOINT + '?pin=' + encodeURIComponent(pin), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (resp) { return resp.json(); })
      .then(function (data) {
        if (inputEl) inputEl.removeAttribute('aria-busy');

        if (!data.success) {
          showPincodeResult(resultEl, 'error', escHtml(data.error || 'Could not check pincode.'));
          return null;
        }

        var info = data;
        var html = '';

        if (info.serviceable) {
          var badges = [];
          if (info.city || info.state) {
            badges.push('<strong>' + escHtml((info.city || '') + (info.city && info.state ? ', ' : '') + (info.state || '')) + '</strong>');
          }
          if (info.cod) {
            badges.push('<span class="badge badge-success">COD Available</span>');
          } else {
            badges.push('<span class="badge badge-warning">COD Unavailable</span>');
          }
          html = '&#10003; Serviceable &mdash; ' + badges.join(' ');
          showPincodeResult(resultEl, 'success', html);
        } else {
          showPincodeResult(resultEl, 'error',
            '&#10007; Sorry, delivery is not available for pincode ' + escHtml(pin) + '.');
        }

        // Save to sessionStorage only when serviceable
        if (info.serviceable) {
          try {
            sessionStorage.setItem(SESSION_KEY, JSON.stringify({
              pin: pin,
              cod: info.cod,
              prepaid: info.prepaid,
              city: info.city,
              state: info.state,
            }));
          } catch (_) {}
        }

        // Fire custom event so other scripts can react
        var event = new CustomEvent('pincodeChecked', { detail: info });
        document.dispatchEvent(event);

        // Update COD availability
        updateCodAvailability(info.cod);

        return info;
      })
      .catch(function (err) {
        if (inputEl) inputEl.removeAttribute('aria-busy');
        showPincodeResult(resultEl, 'error', 'Network error. Please try again.');
        return null;
      });
  };

  // ── Debounced auto-check ─────────────────────────────────────────────────────
  function attachToInput(inputEl, resultEl) {
    if (!inputEl) return;

    inputEl.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      var val = inputEl.value.replace(/\D/g, '').slice(0, 6);
      inputEl.value = val;
      if (val.length === 6) {
        debounceTimer = setTimeout(function () {
          window.checkPincode(val, resultEl, inputEl);
        }, 400);
      } else if (resultEl) {
        resultEl.className = 'pincode-result';
        resultEl.innerHTML = '';
      }
    });

    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(debounceTimer);
        var val = inputEl.value.replace(/\D/g, '').slice(0, 6);
        if (val.length === 6) {
          window.checkPincode(val, resultEl, inputEl);
        }
      }
    });
  }

  // ── Init ─────────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    // Discover inputs
    var inputEl = document.getElementById('pincodeInput')
      || document.getElementById('checkoutPincode')
      || document.querySelector('[name="pincode"]');

    // Discover result containers
    var resultEl = document.getElementById('pincodeResult')
      || document.getElementById('checkoutPincodeResult');

    attachToInput(inputEl, resultEl);

    // Restore saved pincode from sessionStorage
    try {
      var saved = sessionStorage.getItem(SESSION_KEY);
      if (saved) {
        var info = JSON.parse(saved);
        if (info && info.pin && inputEl) {
          inputEl.value = info.pin;
          if (resultEl) {
            // Re-show the cached result without another API call
            if (info.serviceable === true) {
              var loc = (info.city || '') + (info.city && info.state ? ', ' : '') + (info.state || '');
              var html = '&#10003; Serviceable' + (loc ? ' &mdash; <strong>' + escHtml(loc) + '</strong>' : '');
              if (info.cod) html += ' <span class="badge badge-success">COD Available</span>';
              showPincodeResult(resultEl, 'success', html);
            }
          }
          updateCodAvailability(info.cod);
        }
      }
    } catch (_) {}
  });
}());
