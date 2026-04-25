/**
 * assets/js/app.js
 * Lightweight vanilla JS enhancement layer — no frameworks.
 * Provides: page transitions, scroll-reveal, lazy image loading,
 * header scroll behaviour, and a global toast notification system.
 */
(function () {
    'use strict';

    // ── 1. Header scroll behaviour ───────────────────────────────────────────
    const siteHeader = document.getElementById('siteHeader');
    if (siteHeader) {
        const onScroll = () => siteHeader.classList.toggle('scrolled', window.scrollY > 10);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ── 2. Page transition animations ───────────────────────────────────────
    document.addEventListener('click', function (e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor) return;

        const href = anchor.getAttribute('href');
        if (!href) return;

        // Skip: external links, hash-only, JS links, new-tab, download
        if (
            href.startsWith('http') ||
            href.startsWith('//') ||
            href.startsWith('#') ||
            href.startsWith('javascript') ||
            href.startsWith('mailto') ||
            href.startsWith('tel') ||
            anchor.target === '_blank' ||
            anchor.hasAttribute('download') ||
            e.ctrlKey || e.metaKey || e.shiftKey || e.altKey
        ) return;

        e.preventDefault();
        document.documentElement.classList.add('page-transition-out');
        setTimeout(() => { window.location.href = href; }, 220);
    });

    // Fade-in on load
    document.documentElement.classList.add('page-transition-in');

    // ── 3. Scroll-reveal animations ──────────────────────────────────────────
    function initReveal() {
        const items = document.querySelectorAll('.reveal-on-scroll');
        if (!items.length) return;

        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        items.forEach(el => io.observe(el));
    }

    if ('IntersectionObserver' in window) {
        initReveal();
    } else {
        // Fallback: make all visible immediately
        document.querySelectorAll('.reveal-on-scroll').forEach(el => el.classList.add('is-visible'));
    }

    // ── 4. Lazy image blur-up effect ─────────────────────────────────────────
    function initLazyImages() {
        const imgs = document.querySelectorAll('img[loading="lazy"].img-blur-up');
        if (!imgs.length) return;
        imgs.forEach(function (img) {
            if (img.complete) {
                img.classList.add('img-loaded');
            } else {
                img.addEventListener('load', () => img.classList.add('img-loaded'));
            }
        });
    }
    initLazyImages();

    // ── 5. Toast notification system ─────────────────────────────────────────
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastContainer);
    }

    /**
     * Show a toast notification.
     * @param {string} message
     * @param {'success'|'error'|'info'} type
     * @param {number} duration ms (default 3500)
     */
    window.showToast = function (message, type, duration) {
        type = type || 'success';
        duration = duration || 3500;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');

        const icons = {
            success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
            error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
        };

        toast.innerHTML = `<span class="toast-icon">${icons[type] || ''}</span><span class="toast-msg">${escHtml(message)}</span>`;
        toastContainer.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => toast.classList.add('toast-show'));

        // Auto-dismiss
        setTimeout(function () {
            toast.classList.remove('toast-show');
            setTimeout(() => toast.remove(), 350);
        }, duration);

        // Dismiss on click
        toast.addEventListener('click', function () {
            toast.classList.remove('toast-show');
            setTimeout(() => toast.remove(), 350);
        });
    };

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── 6. Mobile nav hamburger (header fallback) ─────────────────────────────
    const hamburger = document.querySelector('.hamburger-btn');
    if (hamburger && !hamburger.dataset.appHooked) {
        hamburger.dataset.appHooked = '1';
        hamburger.addEventListener('click', function () {
            if (typeof openMobNav === 'function') openMobNav();
        });
    }

    // ── 7. Highlight active bottom-nav tab ────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const links = document.querySelectorAll('.mobile-bottom-nav .nav-item');
        const current = window.location.pathname;
        links.forEach(function (link) {
            const href = link.getAttribute('href');
            if (href && (current === href || current.endsWith('/' + href))) {
                link.classList.add('active');
            }
        });
    });

    // ── 8. Global error catchers ─────────────────────────────────────────────
    window.addEventListener('error', function (e) {
        console.error('Global error:', e.error);
        if (typeof showToast === 'function') {
            showToast('Something went wrong. Please refresh the page.', 'error');
        }
    });

    window.addEventListener('unhandledrejection', function (e) {
        console.error('Unhandled promise rejection:', e.reason);
        if (typeof showToast === 'function') {
            showToast('Network error. Please check your connection.', 'error');
        }
    });
})();
