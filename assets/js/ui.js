/**
 * assets/js/ui.js
 * Generic UI utilities: toast notifications, modal helpers, loader spinners.
 * Extracted from app.js for better modularity.
 */
(function () {
    'use strict';

    // ── Toast container ───────────────────────────────────────────────────────
    function getToastContainer() {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Show a toast notification.
     * @param {string} message
     * @param {'success'|'error'|'info'} type
     * @param {number} duration  ms (default 3500)
     */
    window.showToast = function (message, type, duration) {
        type = type || 'success';
        duration = duration || 3500;

        const icons = {
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
            error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
        };

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<span class="toast-icon">' + (icons[type] || '') + '</span>'
            + '<span class="toast-msg">' + escHtml(message) + '</span>';

        const container = getToastContainer();
        container.appendChild(toast);

        requestAnimationFrame(function () { toast.classList.add('toast-show'); });

        setTimeout(function () {
            toast.classList.remove('toast-show');
            setTimeout(function () { toast.remove(); }, 350);
        }, duration);

        toast.addEventListener('click', function () {
            toast.classList.remove('toast-show');
            setTimeout(function () { toast.remove(); }, 350);
        });
    };

    // ── Modal helpers ─────────────────────────────────────────────────────────

    /**
     * Open a modal element by ID.
     * @param {string} modalId
     */
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    };

    /**
     * Close a modal element by ID.
     * @param {string} modalId
     */
    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    // ── Loader spinner ────────────────────────────────────────────────────────

    /**
     * Show a full-page loading spinner.
     */
    window.showLoader = function () {
        let loader = document.getElementById('globalLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'globalLoader';
            loader.style.cssText = 'position:fixed;inset:0;background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;z-index:99998;';
            loader.innerHTML = '<div style="width:40px;height:40px;border:3px solid #e8e0d5;border-top-color:#8b1a1a;border-radius:50%;animation:spin 0.7s linear infinite;"></div>';
            if (!document.getElementById('uiSpinnerStyle')) {
                const style = document.createElement('style');
                style.id = 'uiSpinnerStyle';
                style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
                document.head.appendChild(style);
            }
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    };

    /**
     * Hide the full-page loading spinner.
     */
    window.hideLoader = function () {
        const loader = document.getElementById('globalLoader');
        if (loader) loader.style.display = 'none';
    };
})();
