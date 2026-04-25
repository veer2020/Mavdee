<?php
// $pageTitle must be set by the including page
$pageTitle = $pageTitle ?? 'Admin';

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Detect active page for sidebar highlighting
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentDir    = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

function isActive(string $dir, string $file = ''): string
{
    global $currentDir, $currentScript;

    // For root admin files (dashboard, analytics, returns, qa, logout)
    if ($dir === 'admin') {
        if ($file !== '' && $currentScript === $file) return 'active';
        if ($file === '' && $currentScript === 'index.php') return 'active';
        return '';
    }

    // For subdirectory modules
    if ($file !== '') {
        return ($currentDir === $dir && $currentScript === $file) ? 'active' : '';
    }

    // Check if we're in the subdirectory (any file in that dir)
    return ($currentDir === $dir) ? 'active' : '';
}

// Determine base path to admin root from the current file
$adminBase = $currentDir === 'admin' ? '' : '../';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= h(SITE_NAME) ?> Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-bg: #13161c;
            --sidebar-text: #9ba3af;
            --sidebar-active: #ff3f6c;
            --sidebar-hover-bg: #1e2229;
            --sidebar-active-bg: rgba(255, 63, 108, 0.12);
            --sidebar-border: #242830;
            --sidebar-width: 248px;
            --topbar-height: 60px;
            --accent: #ff3f6c;
            --accent-alt: #f8c146;
            --page-bg: #f4f6f9;
            --content-max-width: 1200px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--page-bg);
            color: #1e2229;
        }

        /* ── Sidebar ─────────────────────────────── */
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 1030;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 20px;
            border-bottom: 1px solid var(--sidebar-border);
            text-decoration: none;
        }

        .sidebar-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent) 0%, #ff6b8f 100%);
            border-radius: 9px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.15rem;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(255, 63, 108, .35);
        }

        .sidebar-brand .brand-text {
            color: #fff;
            font-weight: 700;
            font-size: .88rem;
            line-height: 1.2;
        }

        .sidebar-brand .brand-sub {
            color: var(--sidebar-text);
            font-size: .68rem;
            font-weight: 400;
        }

        .sidebar-section-label {
            color: #4a5260;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            padding: 18px 20px 6px;
        }

        .sidebar-nav {
            list-style: none;
            padding: 4px 10px;
            margin: 0;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: .855rem;
            transition: background .15s, color .15s;
        }

        .sidebar-nav li a i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .sidebar-nav li a:hover {
            background: var(--sidebar-hover-bg);
            color: #e9ecef;
        }

        .sidebar-nav li a.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active);
            font-weight: 600;
        }

        .sidebar-nav li a.active i {
            color: var(--sidebar-active);
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 14px 20px;
            border-top: 1px solid var(--sidebar-border);
            font-size: .75rem;
            color: #4a5260;
        }

        /* ── Top navbar ──────────────────────────── */
        #topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #e8eaed;
            display: flex;
            align-items: center;
            padding: 0 28px;
            z-index: 1020;
            gap: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .04);
        }

        #sidebarToggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.3rem;
            color: #495057;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        #sidebarToggle:hover {
            background: #f4f6f9;
        }

        .topbar-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: #13161c;
        }

        .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-view-site {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .8rem;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
            border: 1.5px solid rgba(255, 63, 108, .3);
            padding: 5px 12px;
            border-radius: 6px;
            transition: background .15s, border-color .15s;
        }

        .topbar-view-site:hover {
            background: rgba(255, 63, 108, .06);
            border-color: var(--accent);
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, #ff6b8f 100%);
            color: #fff;
            font-weight: 700;
            font-size: .8rem;
            display: grid;
            place-items: center;
            box-shadow: 0 2px 6px rgba(255, 63, 108, .3);
        }

        .admin-name {
            font-size: .875rem;
            font-weight: 600;
            color: #374151;
        }

        /* ── Main content ────────────────────────── */
        #main-content {
            margin-left: var(--sidebar-width);
            padding-top: var(--topbar-height);
            min-height: 100vh;
        }

        .page-content {
            padding: 28px;
            max-width: var(--content-max-width);
            margin: 0 auto;
        }

        /* ── Cards ───────────────────────────────── */
        .admin-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06), 0 2px 8px rgba(0, 0, 0, .04);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(0, 0, 0, .04);
        }

        /* ── Stat cards ──────────────────────────── */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06), 0 2px 8px rgba(0, 0, 0, .04);
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid rgba(0, 0, 0, .04);
            transition: transform .15s, box-shadow .15s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, .1);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 1.7rem;
            font-weight: 700;
            color: #13161c;
            line-height: 1;
        }

        .stat-label {
            font-size: .78rem;
            color: #6b7280;
            margin-top: 5px;
        }

        .stat-trend {
            font-size: .72rem;
            font-weight: 600;
            margin-top: 2px;
        }

        .stat-trend.up {
            color: #16a34a;
        }

        .stat-trend.down {
            color: #dc2626;
        }

        /* ── Tables ──────────────────────────────── */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            background: #f8fafc;
            padding: 11px 14px;
            font-size: .73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            border-bottom: 2px solid #e8eaed;
            white-space: nowrap;
        }

        .admin-table td {
            padding: 13px 14px;
            border-bottom: 1px solid #f1f3f5;
            font-size: .875rem;
            vertical-align: middle;
            color: #374151;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background: #fafbfc;
        }

        /* ── Badges ──────────────────────────────── */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: capitalize;
            letter-spacing: .02em;
        }

        .badge-pending {
            background: #fef9c3;
            color: #92400e;
        }

        .badge-processing {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-shipped {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-delivered {
            background: #dcfce7;
            color: #14532d;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-paid {
            background: #dcfce7;
            color: #14532d;
        }

        .badge-unpaid {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-active {
            background: #dcfce7;
            color: #14532d;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-approved {
            background: #dcfce7;
            color: #14532d;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-pending-review {
            background: #fef9c3;
            color: #92400e;
        }

        .badge-percent {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge-fixed {
            background: #fce7f3;
            color: #9d174d;
        }

        /* ── Page header ─────────────────────────── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #13161c;
            margin: 0;
        }

        /* ── Sidebar overlay (mobile) ────────────── */
        #sidebarOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 1025;
            backdrop-filter: blur(2px);
        }

        /* ── Responsive ──────────────────────────── */
        @media (max-width: 991px) {
            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.show {
                transform: translateX(0);
            }

            #sidebarOverlay.show {
                display: block;
            }

            #topbar {
                left: 0;
            }

            #main-content {
                margin-left: 0;
            }

            #sidebarToggle {
                display: block;
            }

            .page-content {
                padding: 16px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 1.4rem;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }

            .topbar-view-site {
                display: none;
            }
        }

        /* ── Accent button ───────────────────────── */
        .btn-accent {
            background: var(--accent);
            color: #fff;
            border: none;
            font-weight: 600;
            transition: background .15s, transform .1s;
        }

        .btn-accent:hover {
            background: #e0325a;
            color: #fff;
            transform: translateY(-1px);
        }

        /* ── Form controls ───────────────────────── */
        .form-label {
            font-weight: 600;
            font-size: .855rem;
            color: #374151;
        }

        .form-control,
        .form-select {
            font-size: .875rem;
            border-color: #e8eaed;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255, 63, 108, .12);
        }

        .form-text {
            font-size: .75rem;
        }

        /* ── Image preview ───────────────────────── */
        .img-preview {
            max-width: 80px;
            max-height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e8eaed;
        }

        /* ── Alert flash ─────────────────────────── */
        .flash-alert {
            border-radius: 10px;
            font-size: .875rem;
            padding: 12px 18px;
            border: none;
        }

        /* ── Timeline ────────────────────────────── */
        .timeline {
            position: relative;
            padding-left: 28px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #e8eaed;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 18px;
        }

        .timeline-dot {
            position: absolute;
            left: -24px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px rgba(255, 63, 108, .3);
        }

        .timeline-time {
            font-size: .72rem;
            color: #9ca3af;
        }

        .timeline-text {
            font-size: .875rem;
            color: #374151;
        }

        .timeline-note {
            font-size: .8rem;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── Stars ───────────────────────────────── */
        .stars {
            color: #f59e0b;
            letter-spacing: 1px;
        }

        /* ── Scrollbar (sidebar) ─────────────────── */
        #sidebar {
            scrollbar-width: thin;
            scrollbar-color: #242830 transparent;
        }

        #sidebar::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background: #242830;
            border-radius: 2px;
        }

        /* ── Image drop zone ─────────────────────── */
        .img-drop-zone {
            border: 2px dashed #e8eaed;
            border-radius: 10px;
            padding: 32px 16px;
            text-align: center;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: border-color .2s, background .2s;
            min-height: 140px;
            background: #fafbfc;
        }

        .img-drop-zone:hover,
        .img-drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(255, 63, 108, .04);
        }

        .img-drop-zone p {
            margin-bottom: 0;
        }

        .img-upload-preview {
            position: relative;
            width: 100%;
        }

        .img-upload-preview .preview-img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            display: block;
        }

        .img-upload-preview .remove-img {
            position: absolute;
            top: 8px;
            right: 8px;
        }

        /* ── Utility classes ─────────────────────── */
        .card-flush {
            padding: 0;
            overflow: hidden;
        }

        .card-header-bar {
            border-bottom: 1px solid #f3f4f6;
        }

        .fw-500 {
            font-weight: 500;
        }

        .fw-600 {
            font-weight: 600;
        }

        .fw-700 {
            font-weight: 700;
        }

        .stock-item {
            padding: 10px 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .875rem;
        }

        /* Loading states */
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Better table responsiveness */
        @media (max-width: 768px) {
            .table-responsive {
                margin-bottom: 0;
                border: 0;
            }

            .admin-table th,
            .admin-table td {
                white-space: nowrap;
            }

            .admin-card {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 1.2rem;
            }
        }

        /* Improved card hover effects */
        .admin-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        /* Fix for modal z-index */
        .modal {
            z-index: 1060;
        }

        .modal-backdrop {
            z-index: 1050;
        }

        /* Better focus states */
        .btn:focus,
        .form-control:focus,
        .form-select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 63, 108, 0.25);
        }

        /* Skeleton loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
    </style>
</head>

<body>

    <!-- Sidebar overlay (mobile) -->
    <div id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- ═══ Sidebar ═══════════════════════════════════════════════════ -->
    <nav id="sidebar">
        <a href="<?= $adminBase ?>index.php" class="sidebar-brand">
            <div class="brand-icon"><?= strtoupper(substr(SITE_NAME, 0, 1)) ?></div>
            <div>
                <div class="brand-text"><?= h(SITE_NAME) ?></div>
                <div class="brand-sub">Admin Panel</div>
            </div>
        </a>

        <span class="sidebar-section-label">Main</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>index.php" class="<?= isActive('admin', 'index.php') ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
        </ul>

        <span class="sidebar-section-label">Catalogue</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>products/index.php" class="<?= isActive('products') ?>">
                    <i class="bi bi-bag-heart"></i> Products
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>categories/index.php" class="<?= isActive('categories') ?>">
                    <i class="bi bi-grid-3x3-gap"></i> Categories
                </a>
            </li>
        </ul>

        <span class="sidebar-section-label">Sales</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>orders/index.php" class="<?= isActive('orders') ?>">
                    <i class="bi bi-cart-check"></i> Orders
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>customers/index.php" class="<?= isActive('customers') ?>">
                    <i class="bi bi-people"></i> Customers
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>customers/addresses.php" class="<?= isActive('customers', 'addresses.php') ?>">
                    <i class="bi bi-geo-alt"></i> Addresses
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>coupons/index.php" class="<?= isActive('coupons') ?>">
                    <i class="bi bi-ticket-perforated"></i> Coupons
                </a>
            </li>
        </ul>

        <span class="sidebar-section-label">Content</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>reviews/index.php" class="<?= isActive('reviews') ?>">
                    <i class="bi bi-star-half"></i> Reviews
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>returns.php" class="<?= isActive('admin', 'returns.php') ?>">
                    <i class="bi bi-arrow-return-left"></i> Returns
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>qa.php" class="<?= isActive('admin', 'qa.php') ?>">
                    <i class="bi bi-question-circle"></i> Q&amp;A
                </a>
            </li>
        </ul>

        <span class="sidebar-section-label">Insights</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>analytics.php" class="<?= isActive('admin', 'analytics.php') ?>">
                    <i class="bi bi-graph-up-arrow"></i> Analytics
                </a>
            </li>
        </ul>

        <span class="sidebar-section-label">System</span>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= $adminBase ?>settings/index.php" class="<?= isActive('settings') ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>activity_log/index.php" class="<?= isActive('activity_log') ?>">
                    <i class="bi bi-clock-history"></i> Activity Log
                </a>
            </li>
            <li>
                <a href="<?= $adminBase ?>logout.php">
                    <i class="bi bi-box-arrow-left"></i> Logout
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <?= h(SITE_NAME) ?> Admin &copy; <?= date('Y') ?>
        </div>
    </nav>

    <!-- ═══ Top navbar ════════════════════════════════════════════════ -->
    <header id="topbar">
        <button id="sidebarToggle" onclick="toggleSidebar()" title="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <span class="topbar-title"><?= h($pageTitle) ?></span>
        <div class="topbar-right">
            <a href="<?= defined('SITE_URL') ? SITE_URL : '../' ?>index.php" target="_blank" class="topbar-view-site">
                <i class="bi bi-box-arrow-up-right"></i> View Site
            </a>
            <div class="admin-avatar"><?= strtoupper(substr(getAdminName(), 0, 1)) ?></div>
            <span class="admin-name d-none d-sm-inline"><?= h(getAdminName()) ?></span>
            <a href="<?= $adminBase ?>logout.php" class="btn btn-sm btn-outline-secondary" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>

    <!-- ═══ Main content wrapper ══════════════════════════════════════ -->
    <main id="main-content">
        <div class="page-content">

            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible flash-alert" role="alert">
                    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                    <?= h($flash['msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>