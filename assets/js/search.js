/**
 * assets/js/search.js
 * Live search dropdown with 300 ms debounce.
 * Hooks into a search icon button in the header.
 */
(function () {
    'use strict';

    // ── Build search UI ──────────────────────────────────────────────────────
    const CURRENCY = (window.SITE_CURRENCY || '₹');

    // Create the floating search bar & dropdown if not already in the DOM
    let searchBar = document.getElementById('liveSearchBar');
    if (!searchBar) {
        searchBar = document.createElement('div');
        searchBar.id = 'liveSearchBar';
        searchBar.setAttribute('role', 'search');
        searchBar.innerHTML = `
            <div class="ls-backdrop" id="lsBackdrop"></div>
            <div class="ls-panel" id="lsPanel">
                <div class="ls-input-wrap">
                    <svg class="ls-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="search" id="lsInput" class="ls-input" placeholder="Search products…" autocomplete="off" aria-label="Live search">
                    <button class="ls-close" id="lsClose" aria-label="Close search">&times;</button>
                </div>
                <div class="ls-results" id="lsResults" aria-live="polite"></div>
                <div class="ls-footer" id="lsFooter" style="display:none;">
                    <a href="#" class="ls-view-all" id="lsViewAll">View all results</a>
                </div>
            </div>`;
        document.body.appendChild(searchBar);
    }

    // Inject styles
    if (!document.getElementById('lsStyles')) {
        const style = document.createElement('style');
        style.id = 'lsStyles';
        style.textContent = `
            #liveSearchBar { position: fixed; inset: 0; z-index: 9990; display: none; }
            #liveSearchBar.open { display: block; }
            .ls-backdrop {
                position: absolute; inset: 0;
                background: rgba(26,26,26,0.45);
                backdrop-filter: blur(2px);
                animation: lsFadeIn 0.2s ease;
            }
            .ls-panel {
                position: absolute;
                top: 0; left: 50%;
                transform: translateX(-50%);
                width: min(640px, 96vw);
                background: #fff;
                box-shadow: 0 12px 40px rgba(0,0,0,0.14);
                border-radius: 0 0 16px 16px;
                overflow: hidden;
                animation: lsSlideDown 0.22s cubic-bezier(0.4,0,0.2,1);
            }
            @keyframes lsFadeIn   { from { opacity:0 } to { opacity:1 } }
            @keyframes lsSlideDown { from { transform: translateX(-50%) translateY(-12px); opacity:0 } to { transform: translateX(-50%) translateY(0); opacity:1 } }
            .ls-input-wrap {
                display: flex; align-items: center;
                padding: 14px 16px; border-bottom: 1px solid #e8e4df;
                gap: 10px;
            }
            .ls-icon { color: #888; flex-shrink: 0; }
            .ls-input {
                flex: 1; border: none; outline: none;
                font-size: 1rem; font-family: inherit; color: #1a1a1a;
                background: transparent;
                -webkit-appearance: none;
            }
            .ls-input::-webkit-search-cancel-button { display: none; }
            .ls-close {
                background: none; border: none; font-size: 1.4rem;
                cursor: pointer; color: #888; line-height: 1;
                transition: color 0.2s;
            }
            .ls-close:hover { color: #1a1a1a; }
            .ls-results { max-height: 420px; overflow-y: auto; }
            .ls-item {
                display: flex; align-items: center; gap: 14px;
                padding: 12px 16px; text-decoration: none; color: #1a1a1a;
                border-bottom: 1px solid #f5f5f5;
                transition: background 0.15s;
            }
            .ls-item:last-child { border-bottom: none; }
            .ls-item:hover { background: #faf9f7; }
            .ls-item-img {
                width: 52px; height: 64px;
                object-fit: cover; flex-shrink: 0;
                background: #f4f4f2;
            }
            .ls-item-name { flex: 1; font-size: 0.92rem; font-weight: 500; }
            .ls-item-price { font-size: 0.88rem; color: #8b1a2e; font-weight: 600; white-space: nowrap; }
            .ls-empty { padding: 28px 16px; text-align: center; color: #888; font-size: 0.92rem; }
            .ls-loading { padding: 20px 16px; text-align: center; }
            .ls-spinner {
                display: inline-block; width: 20px; height: 20px;
                border: 2px solid #e8e4df; border-top-color: #c9a96e;
                border-radius: 50%; animation: lsSpin 0.7s linear infinite;
            }
            @keyframes lsSpin { to { transform: rotate(360deg); } }
            .ls-footer { border-top: 1px solid #e8e4df; padding: 12px 16px; text-align: center; }
            .ls-view-all {
                font-size: 0.85rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: 0.08em; color: #8b1a2e; text-decoration: none;
            }
            .ls-view-all:hover { text-decoration: underline; }
        `;
        document.head.appendChild(style);
    }

    const lsPanel = document.getElementById('lsPanel');
    const lsInput = document.getElementById('lsInput');
    const lsResults = document.getElementById('lsResults');
    const lsFooter = document.getElementById('lsFooter');
    const lsViewAll = document.getElementById('lsViewAll');
    const lsBackdrop = document.getElementById('lsBackdrop');
    const lsClose = document.getElementById('lsClose');

    let debounceTimer = null;
    let currentQuery = '';

    function openSearch() {
        searchBar.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => lsInput && lsInput.focus(), 80);
    }

    function closeSearch() {
        searchBar.classList.remove('open');
        document.body.style.overflow = '';
    }

    function renderLoading() {
        lsResults.innerHTML = '<div class="ls-loading"><span class="ls-spinner"></span></div>';
        lsFooter.style.display = 'none';
    }

    function renderResults(q, items) {
        if (!items.length) {
            lsResults.innerHTML = `<p class="ls-empty">No results for &ldquo;${escHtml(q)}&rdquo;</p>`;
            lsFooter.style.display = 'none';
            return;
        }
        lsResults.innerHTML = items.map(p => {
            const href = `product.php?id=${p.id}&slug=${encodeURIComponent(p.slug || '')}`;
            const img = p.image_url ? `<img class="ls-item-img" src="${escHtml(p.image_url)}" alt="${escHtml(p.name)}" loading="lazy" onerror="this.src='/assets/img/placeholder.svg'">` : '';
            const price = CURRENCY + Number(p.price).toLocaleString('en-IN', { minimumFractionDigits: 0 });
            return `<a class="ls-item" href="${escHtml(href)}">${img}<span class="ls-item-name">${escHtml(p.name)}</span><span class="ls-item-price">${escHtml(price)}</span></a>`;
        }).join('');

        lsViewAll.href = `shop.php?q=${encodeURIComponent(q)}`;
        lsFooter.style.display = '';
    }

    function doSearch(q) {
        if (q.length < 2) {
            lsResults.innerHTML = '';
            lsFooter.style.display = 'none';
            return;
        }
        renderLoading();
        fetch(`/api/search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (lsInput.value.trim() === q) {
                    renderResults(q, data.results || []);
                }
            })
            .catch(() => {
                lsResults.innerHTML = '<p class="ls-empty">Search unavailable.</p>';
            });
    }

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Input with debounce
    lsInput.addEventListener('input', function () {
        currentQuery = this.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => doSearch(currentQuery), 300);
    });

    // Enter → navigate to search page
    lsInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const q = this.value.trim();
            if (q) window.location.href = `shop.php?q=${encodeURIComponent(q)}`;
        }
        if (e.key === 'Escape') closeSearch();
    });

    // Close triggers
    lsBackdrop.addEventListener('click', closeSearch);
    lsClose.addEventListener('click', closeSearch);

    // Hook all search-trigger elements (data-search-trigger or #searchTrigger)
    function hookTriggers() {
        document.querySelectorAll('[data-search-trigger], #searchTrigger, .search-trigger').forEach(el => {
            if (!el.dataset.lsHooked) {
                el.dataset.lsHooked = '1';
                el.addEventListener('click', function (e) { e.preventDefault(); openSearch(); });
            }
        });
    }

    hookTriggers();

    // Also expose globally so header can call it after late DOM insertion
    window.openLiveSearch = openSearch;
})();
