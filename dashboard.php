<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

// 1. Validate Session
if (!isLoggedIn()) {
    header("Location: login.php?next=dashboard.php");
    exit;
}

$userId = getUserId();

// 2. Fetch Customer Data
$stmt = db()->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$customer = $stmt->fetch();

if (!$customer) {
    // Fallback if they were registered in the legacy users table
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $customer = $stmt->fetch();
    } catch (Throwable) {
        $customer = false;
    }
    if (!$customer) {
        header("Location: logout.php");
        exit;
    }
}

// 3. Fetch Recent Orders
$stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// 4. Fetch Saved Addresses
$savedAddresses = [];
try {
    $addrStmt = db()->prepare(
        "SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC"
    );
    $addrStmt->execute([$userId]);
    $savedAddresses = $addrStmt->fetchAll();
} catch (Throwable) {
    $savedAddresses = [];
}

$firstName = htmlspecialchars(explode(' ', $customer['name'])[0]);
$avatarLetter = strtoupper(substr($customer['name'], 0, 1));
$orderCount = count($orders);
$wishlistCount = 0;
try {
    $wStmt = db()->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $wStmt->execute([$userId]);
    $wishlistCount = (int)$wStmt->fetchColumn();
} catch (Throwable) {
}

// Referral code — auto-generate if missing
$referralCode = '';
try {
    $rcStmt = db()->prepare("SELECT code FROM referral_codes WHERE customer_id = ? LIMIT 1");
    $rcStmt->execute([$userId]);
    $rcRow = $rcStmt->fetch();
    if ($rcRow) {
        $referralCode = $rcRow['code'];
    } else {
        // Generate a unique code
        $namePrefix = strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/', '', $customer['name']), 0, 3));
        if (strlen($namePrefix) < 3) {
            $namePrefix = str_pad($namePrefix, 3, 'X');
        }
        do {
            $code = $namePrefix . strtoupper(bin2hex(random_bytes(3)));
            $exists = db()->prepare("SELECT id FROM referral_codes WHERE code = ? LIMIT 1");
            $exists->execute([$code]);
        } while ($exists->fetch());
        db()->prepare("INSERT INTO referral_codes (customer_id, code) VALUES (?,?)")->execute([$userId, $code]);
        $referralCode = $code;
    }
} catch (Throwable) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        :root {
            --mavdee-pink: #ff3f6c;
            --mavdee-green: #03a685;
            --mavdee-dark: #1c1c1c;
            --mavdee-grey: #f4f4f5;
            --mavdee-border: #eaeaec;
            --mavdee-muted: #94969f;
            --mavdee-text: #3e4152;
            --font-sans: 'DM Sans', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--font-sans);
            background-color: var(--mavdee-grey);
            color: var(--mavdee-text);
            -webkit-font-smoothing: antialiased;
            padding-bottom: calc(64px + env(safe-area-inset-bottom));
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── Profile page wrapper ── */
        .profile-page {
            max-width: 600px;
            margin: 0 auto;
            background: var(--mavdee-grey);
        }

        /* ── Top profile header ── */
        .profile-top {
            background: #fff;
            padding: 20px 16px 0;
            border-bottom: 6px solid var(--mavdee-grey);
        }

        .profile-shopping-for {
            font-size: 1rem;
            font-weight: 700;
            color: var(--mavdee-dark);
            margin: 0 0 16px;
        }

        .profile-avatars {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .profile-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .profile-avatar-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #c8ece4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--mavdee-green);
            border: 2.5px solid var(--mavdee-pink);
            position: relative;
            flex-shrink: 0;
        }

        .profile-admin-badge {
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--mavdee-pink);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 99px;
            white-space: nowrap;
        }

        .profile-avatar-name {
            font-size: 13px;
            color: var(--mavdee-dark);
            font-weight: 500;
            text-align: center;
        }

        /* ── Profile pills ── */
        .profile-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 16px;
            scrollbar-width: none;
        }

        .profile-pills::-webkit-scrollbar {
            display: none;
        }

        .profile-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid var(--mavdee-border);
            border-radius: 99px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: var(--mavdee-dark);
            white-space: nowrap;
            cursor: pointer;
        }

        .profile-pill svg {
            flex-shrink: 0;
        }

        /* ── Myncash Magic banner ── */
        .myncash-banner {
            background: linear-gradient(135deg, #f5a623 0%, #f7c26b 100%);
            border-radius: 12px;
            margin: 12px 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
        }

        .myncash-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .myncash-text strong {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #7a4800;
        }

        .myncash-text span {
            font-size: 13px;
            color: #7a4800;
            opacity: 0.85;
        }

        /* ── Task streak section ── */
        .task-streak {
            background: #fdf6e3;
            border-radius: 12px;
            margin: 0 16px 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .task-streak-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }

        .task-streak-text {
            flex: 1;
        }

        .task-streak-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--mavdee-dark);
        }

        .task-streak-text span {
            font-size: 13px;
            color: var(--mavdee-muted);
        }

        .task-streak-badge {
            background: #f5a623;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 99px;
            white-space: nowrap;
        }

        /* ── 2×2 quick links grid ── */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 16px;
            margin: 0;
            background-color: #f8f9fa;
        }

        .quick-grid-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px 12px;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            gap: 8px;
            min-height: 100px;
        }

        .quick-grid-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .quick-grid-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-bottom: 4px;
        }

        .quick-grid-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .quick-grid-arrow {
            display: none;
        }

        /* ── Brand Colors for Individual Icons ── */
        .quick-grid-item:nth-child(1) .quick-grid-icon {
            background-color: var(--pink-xl, #FFF0F3);
            color: var(--pink, #FF3F6C);
        }

        .quick-grid-item:nth-child(2) .quick-grid-icon {
            background-color: var(--amber-xl, #FFF8E1);
            color: var(--amber, #F9A825);
        }

        .quick-grid-item:nth-child(3) .quick-grid-icon {
            background-color: var(--sky-xl, #E3F2FD);
            color: var(--sky, #1976D2);
        }

        .quick-grid-item:nth-child(4) .quick-grid-icon {
            background-color: var(--green-xl, #E6F7F3);
            color: var(--green, #03A685);
        }

        /* ── Menu sections ── */
        .menu-section {
            background: #fff;
            margin-bottom: 6px;
        }

        .menu-row {
            display: flex;
            align-items: center;
            padding: 16px;
            gap: 14px;
            border-bottom: 1px solid var(--Mavdee-border);
            cursor: pointer;
            transition: background 0.15s;
        }

        .menu-row:last-child {
            border-bottom: none;
        }

        .menu-row:hover {
            background: #f9f9f9;
        }

        .menu-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mavdee-muted);
            flex-shrink: 0;
        }

        .menu-text {
            flex: 1;
        }

        .menu-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--Mavdee-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .menu-subtitle {
            font-size: 13px;
            color: var(--mavdee-muted);
            margin-top: 2px;
        }

        .menu-arrow {
            color: var(--Mavdee-muted);
            flex-shrink: 0;
        }

        .menu-chevron {
            color: var(--Mavdee-muted);
            flex-shrink: 0;
            transition: transform 0.2s;
        }

        .menu-row.open .menu-chevron {
            transform: rotate(180deg);
        }

        /* NEW badge */
        .badge-new {
            background: var(--mavdee-pink);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ── Expandable sub-content ── */
        .menu-expand {
            display: none;
            border-top: 1px solid var(--mavdee-border);
            padding: 16px;
        }

        .menu-expand.open {
            display: block;
        }

        /* Account tiles (Account Details + Addresses) */
        .account-tiles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .account-tile {
            border: 1px solid var(--mavdee-border);
            border-radius: 8px;
            padding: 18px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .account-tile:hover {
            background: #f9f9f9;
        }

        .account-tile-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .account-tile-icon.pink-bg {
            background: rgba(255, 63, 108, 0.08);
        }

        .account-tile-icon.green-bg {
            background: rgba(3, 166, 133, 0.08);
        }

        .account-tile-label {
            font-size: 13px;
            color: var(--mavdee-muted);
            font-weight: 600;
            text-align: center;
        }

        /* Profile info (inside expand) */
        .profile-info-rows {
            margin-top: 8px;
        }

        .profile-info-row {
            display: flex;
            flex-direction: column;
            padding: 10px 0;
            border-bottom: 1px solid var(--mavdee-border);
            gap: 2px;
        }

        .profile-info-row:last-child {
            border-bottom: none;
        }

        .profile-info-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--mavdee-muted);
        }

        .profile-info-value {
            font-size: 14px;
            color: var(--mavdee-dark);
            font-weight: 500;
        }

        /* ── Profile footer links ── */
        .profile-footer {
            background: var(--mavdee-grey);
            padding: 24px 16px 8px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .profile-footer-link {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--mavdee-muted);
            padding: 10px 0;
            cursor: pointer;
        }

        .profile-footer-link:hover {
            color: var(--mavdee-dark);
        }

        /* ── Sign out button ── */
        .btn-signout {
            display: block;
            width: calc(100% - 32px);
            margin: 16px;
            padding: 14px;
            background: #fff;
            color: var(--mavdee-pink);
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 13px;
            border: 1px solid var(--mavdee-border);
            border-radius: 4px;
            cursor: pointer;
            font-family: var(--font-sans);
            transition: background 0.2s;
        }

        .btn-signout:hover {
            background: #fff0f3;
        }

        /* ── Profile stats bar ── */
        .profile-stats {
            display: flex;
            gap: 0;
            border-top: 1px solid var(--mavdee-border);
            margin-top: 4px;
        }

        .profile-stat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 8px;
            gap: 2px;
            border-right: 1px solid var(--mavdee-border);
        }

        .profile-stat:last-child {
            border-right: none;
        }

        .profile-stat-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--mavdee-dark);
        }

        .profile-stat-label {
            font-size: 11px;
            color: var(--mavdee-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
        }

        /* ── Edit profile button ── */
        .btn-edit-profile {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 14px;
            padding: 9px 18px;
            border: 1px solid var(--mavdee-pink);
            border-radius: 4px;
            color: var(--mavdee-pink);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            background: #fff;
            font-family: var(--font-sans);
            transition: background 0.15s;
        }

        .btn-edit-profile:hover {
            background: #fff0f3;
        }

        /* ── Referral section ── */
        .referral-section {
            background: linear-gradient(135deg, #f0fff8 0%, #e6f7f3 100%);
            margin: 0 16px 12px;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 1px solid rgba(3, 166, 133, 0.15);
        }

        .referral-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .referral-text {
            flex: 1;
            min-width: 0;
        }

        .referral-text strong {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--mavdee-dark);
            margin-bottom: 4px;
        }

        .referral-code-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .referral-code {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--mavdee-green);
            font-family: monospace;
        }

        .btn-copy-ref {
            background: var(--mavdee-green);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-sans);
            transition: background 0.2s;
        }

        .btn-copy-ref:hover {
            background: #028a6e;
        }

        /* ── Modal overlay ── */
        .dash-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(28, 28, 28, 0.55);
            z-index: 10001;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
        }

        .dash-modal-backdrop.open {
            display: flex;
        }

        @media (min-width: 600px) {
            .dash-modal-backdrop {
                align-items: center;
                padding: 20px;
            }
        }

        .dash-modal {
            background: #fff;
            width: 100%;
            max-width: 480px;
            border-radius: 16px 16px 0 0;
            padding: 24px 20px;
            max-height: 90vh;
            overflow-y: auto;
        }

        @media (min-width: 600px) {
            .dash-modal {
                border-radius: 12px;
            }
        }

        .dash-modal-handle {
            width: 40px;
            height: 4px;
            background: var(--mavdee-border);
            border-radius: 99px;
            margin: 0 auto 20px;
        }

        @media (min-width: 600px) {
            .dash-modal-handle {
                display: none;
            }
        }

        .dash-modal h3 {
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--mavdee-dark);
            margin: 0 0 20px;
        }

        .dash-form-group {
            margin-bottom: 16px;
        }

        .dash-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--mavdee-muted);
            margin-bottom: 6px;
        }

        .dash-form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--mavdee-border);
            border-radius: 4px;
            font-size: 14px;
            font-family: var(--font-sans);
            color: var(--mavdee-dark);
            background: #fff;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .dash-form-group input:focus {
            outline: none;
            border-color: var(--mavdee-pink);
        }

        .dash-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-modal-secondary {
            flex: 1;
            padding: 12px;
            background: #fff;
            color: var(--mavdee-text);
            border: 1px solid var(--mavdee-border);
            border-radius: 4px;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
        }

        .btn-modal-primary {
            flex: 2;
            padding: 12px;
            background: var(--mavdee-pink);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-primary:hover {
            background: #e0325a;
        }

        .btn-modal-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .dash-modal-error {
            font-size: 13px;
            color: var(--mavdee-pink);
            margin-top: 8px;
            display: none;
        }

        /* ── Address list in modal ── */
        .addr-list {
            margin-bottom: 12px;
        }

        .addr-card {
            border: 1px solid var(--mavdee-border);
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 10px;
            position: relative;
        }

        .addr-card.is-default {
            border-color: var(--mavdee-green);
            background: #f0fff8;
        }

        .addr-default-tag {
            font-size: 11px;
            font-weight: 700;
            color: var(--mavdee-green);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: block;
        }

        .addr-card-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--mavdee-dark);
        }

        .addr-card-line {
            font-size: 13px;
            color: var(--mavdee-muted);
            margin-top: 3px;
        }

        .addr-card-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-addr-action {
            border: none;
            background: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-sans);
            padding: 0;
            letter-spacing: 0.03em;
        }

        .btn-addr-action.edit {
            color: var(--mavdee-pink);
        }

        .btn-addr-action.remove {
            color: var(--mavdee-muted);
        }

        .btn-addr-action.remove:hover {
            color: #e03535;
        }

        .btn-add-addr {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 11px;
            border: 1px dashed var(--mavdee-border);
            border-radius: 6px;
            background: #fff;
            font-size: 13px;
            font-weight: 700;
            color: var(--mavdee-muted);
            cursor: pointer;
            font-family: var(--font-sans);
            margin-bottom: 4px;
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-add-addr:hover {
            border-color: var(--mavdee-pink);
            color: var(--mavdee-pink);
        }

        .addr-form-section {
            border-top: 1px solid var(--mavdee-border);
            margin-top: 16px;
            padding-top: 16px;
        }

        .addr-form-section h4 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--mavdee-dark);
            margin: 0 0 14px;
        }

        .addr-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .addr-form-group {
            margin-bottom: 12px;
        }

        .addr-form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--mavdee-muted);
            margin-bottom: 5px;
        }

        .addr-form-group input,
        .addr-form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--mavdee-border);
            border-radius: 4px;
            font-size: 13px;
            font-family: var(--font-sans);
            color: var(--Mavdee-dark);
            background: #fff;
            box-sizing: border-box;
        }

        .addr-form-group input:focus,
        .addr-form-group select:focus {
            outline: none;
            border-color: var(--Mavdee-pink);
        }

        .addr-default-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--Mavdee-text);
            cursor: pointer;
            margin-bottom: 14px;
        }

        /* ── Desktop: keep reasonable max-width and center ── */
        @media (min-width: 768px) {
            .profile-page {
                min-height: 70vh;
                margin: 16px auto;
                border: 1px solid var(--Mavdee-border);
            }

            .quick-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <div class="main-content">

        <div class="profile-page">

            <!-- ── Top: Shopping for + Avatar ── -->
            <div class="profile-top">
                <p class="profile-shopping-for">Shopping for <?= $firstName ?></p>
                <div class="profile-avatars">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-circle">
                            <span id="avatar-letter"><?= h($avatarLetter) ?></span>
                        </div>
                        <span class="profile-avatar-name"><?= $firstName ?></span>
                    </div>
                </div>
                <!-- Profile pills -->
                <div class="profile-pills">
                    <a href="dashboard.php#account-details" class="profile-pill">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        Basics
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </a>
                    <a href="dashboard.php#manage-account" class="profile-pill">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="9" y1="21" x2="9" y2="9" />
                        </svg>
                        Size Details
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </a>
                </div>
                <!-- Stats bar -->
                <div class="profile-stats">
                    <div class="profile-stat">
                        <span class="profile-stat-value"><?= $orderCount ?></span>
                        <span class="profile-stat-label">Orders</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value"><?= $wishlistCount ?></span>
                        <span class="profile-stat-label">Wishlist</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value"><?= CURRENCY ?><?= number_format((float)($customer['total_spent'] ?? 0), 0) ?></span>
                        <span class="profile-stat-label">Total Spent</span>
                    </div>
                </div>
            </div>

            <!-- ── Daily Myncash Magic banner ── -->
            <div class="myncash-banner" onclick="location.href='shop.php'">
                <div class="myncash-icon">📅</div>
                <div class="myncash-text">
                    <strong>Daily Myncash Magic!</strong>
                    <span>Win up to <?= CURRENCY ?>200 now</span>
                </div>
            </div>

            <!-- ── Task streak ── -->
            <div class="task-streak">
                <div class="task-streak-icon">🔥</div>
                <div class="task-streak-text">
                    <strong>Today's task completed</strong>
                    <span>Come back tomorrow for more rewards</span>
                </div>
                <span class="task-streak-badge">1/3 days</span>
            </div>

            <!-- ── 2×2 Quick links grid ── -->
            <div class="quick-grid">
                <a href="my-orders.php" class="quick-grid-item">
                    <span class="quick-grid-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                    </span>
                    <span class="quick-grid-label">Orders</span>
                    <svg class="quick-grid-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>
                <a href="#" class="quick-grid-item">
                    <span class="quick-grid-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="6" />
                            <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
                        </svg>
                    </span>
                    <span class="quick-grid-label">Insider</span>
                    <svg class="quick-grid-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>
                <a href="contact.php" class="quick-grid-item">
                    <span class="quick-grid-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 18v-6a9 9 0 0 1 18 0v6" />
                            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z" />
                        </svg>
                    </span>
                    <span class="quick-grid-label">Help Center</span>
                    <svg class="quick-grid-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>
                <a href="wishlist.php" class="quick-grid-item">
                    <span class="quick-grid-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                    </span>
                    <span class="quick-grid-label">Coupons</span>
                    <svg class="quick-grid-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>
            </div>

            <!-- ── Collapsible menu sections ── -->
            <div class="menu-section">

                <!-- Manage Account -->
                <div class="menu-row" id="manage-account" onclick="toggleSection('manage')">
                    <span class="menu-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                    </span>
                    <div class="menu-text">
                        <div class="menu-title">Manage Account</div>
                        <div class="menu-subtitle">Manage your account and saved addresses</div>
                    </div>
                    <svg class="menu-chevron" id="chevron-manage" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="menu-expand" id="section-manage">
                    <div class="account-tiles">
                        <div class="account-tile" id="account-details" onclick="toggleSection('account-info')">
                            <div class="account-tile-icon pink-bg">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff3f6c" stroke-width="1.8">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </div>
                            <span class="account-tile-label">Account Details</span>
                        </div>
                        <div class="account-tile" onclick="openAddrModal()">
                            <div class="account-tile-icon green-bg">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#03a685" stroke-width="1.8">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                            </div>
                            <span class="account-tile-label">Addresses</span>
                        </div>
                    </div>
                    <div class="menu-expand" id="section-account-info" style="margin-top:12px;padding:0;">
                        <div class="profile-info-rows">
                            <div class="profile-info-row">
                                <span class="profile-info-label">Full Name</span>
                                <span class="profile-info-value" id="display-name"><?= h($customer['name']) ?></span>
                            </div>
                            <div class="profile-info-row">
                                <span class="profile-info-label">Email</span>
                                <span class="profile-info-value"><?= h($customer['email']) ?></span>
                            </div>
                            <div class="profile-info-row">
                                <span class="profile-info-label">Mobile</span>
                                <span class="profile-info-value" id="display-phone"><?= h($customer['phone'] ?: '—') ?></span>
                            </div>
                            <?php if (!empty($savedAddresses)): ?>
                                <div class="profile-info-row">
                                    <span class="profile-info-label">Default Address</span>
                                    <span class="profile-info-value">
                                        <?= h($savedAddresses[0]['address']) ?>, <?= h($savedAddresses[0]['city']) ?>
                                        <?php if ($savedAddresses[0]['state']): ?>, <?= h($savedAddresses[0]['state']) ?><?php endif; ?>
                                        – <?= h($savedAddresses[0]['pincode']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn-edit-profile" onclick="openEditProfileModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                            Edit Profile
                        </button>
                    </div>
                </div>

                <!-- Wishlist -->
                <a href="wishlist.php" class="menu-row">
                    <span class="menu-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                    </span>
                    <div class="menu-text">
                        <div class="menu-title">Wishlist</div>
                        <div class="menu-subtitle">Your most loved styles</div>
                    </div>
                    <svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>

                <!-- Mavdee Suggests -->
                <a href="shop.php" class="menu-row">
                    <span class="menu-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                    </span>
                    <div class="menu-text">
                        <div class="menu-title">Mavdee Suggests</div>
                        <div class="menu-subtitle">100% personalized feed just for you</div>
                    </div>
                    <svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>

                <!-- Settings -->
                <a href="#" class="menu-row">
                    <span class="menu-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="3" />
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                    </span>
                    <div class="menu-text">
                        <div class="menu-title">Settings</div>
                        <div class="menu-subtitle">Manage Notifications</div>
                    </div>
                    <svg class="menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </a>

            </div>

            <?php if ($referralCode): ?>
                <!-- ── Referral Code ── -->
                <div class="referral-section">
                    <div class="referral-icon">🎁</div>
                    <div class="referral-text">
                        <strong>Invite Friends, Earn Rewards</strong>
                        <div class="referral-code-row">
                            <span class="referral-code" id="refCode"><?= h($referralCode) ?></span>
                            <button type="button" class="btn-copy-ref" id="copyRefBtn" onclick="copyReferralCode()">Copy</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Sign Out ── -->
            <a href="logout.php" class="btn-signout">Sign Out</a>

            <!-- ── Footer links ── -->
            <div class="profile-footer">
                <a href="contact.php" class="profile-footer-link">FAQs</a>
                <a href="about.php" class="profile-footer-link">ABOUT US</a>
                <a href="returns.php" class="profile-footer-link">TERMS OF USE</a>
                <a href="privacy.php" class="profile-footer-link">PRIVACY POLICY</a>
            </div>

        </div>

    </div><!-- /.main-content -->

    <!-- ── Edit Profile Modal ── -->
    <div class="dash-modal-backdrop" id="editProfileBackdrop" role="dialog" aria-modal="true" aria-labelledby="editProfileTitle">
        <div class="dash-modal">
            <div class="dash-modal-handle"></div>
            <h3 id="editProfileTitle">Edit Profile</h3>
            <form id="editProfileForm" onsubmit="submitProfileUpdate(event)">
                <input type="hidden" name="csrf_token" id="editProfileCsrf" value="<?= csrf_token() ?>">
                <div class="dash-form-group">
                    <label for="editName">Full Name</label>
                    <input type="text" id="editName" name="name" maxlength="100" required
                        value="<?= h($customer['name']) ?>" autocomplete="name">
                </div>
                <div class="dash-form-group">
                    <label for="editPhone">Mobile Number</label>
                    <input type="tel" id="editPhone" name="phone" maxlength="20"
                        value="<?= h($customer['phone'] ?? '') ?>" autocomplete="tel"
                        placeholder="e.g. +91 98765 43210">
                </div>
                <div class="dash-modal-error" id="editProfileError"></div>
                <div class="dash-modal-actions">
                    <button type="button" class="btn-modal-secondary" onclick="closeEditProfileModal()">Cancel</button>
                    <button type="submit" class="btn-modal-primary" id="editProfileSaveBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Address Management Modal ── -->
    <div class="dash-modal-backdrop" id="addrModalBackdrop" role="dialog" aria-modal="true" aria-labelledby="addrModalTitle">
        <div class="dash-modal" style="max-height:85vh;">
            <div class="dash-modal-handle"></div>
            <h3 id="addrModalTitle">My Addresses</h3>
            <div id="addrList" class="addr-list">
                <p style="color:var(--Mavdee-muted);font-size:13px;">Loading…</p>
            </div>
            <button type="button" class="btn-add-addr" id="btnShowAddrForm" onclick="showAddrForm()">
                + Add New Address
            </button>
            <div class="addr-form-section" id="addrFormSection" style="display:none;">
                <h4 id="addrFormTitle">New Address</h4>
                <form id="addrForm" onsubmit="submitAddr(event)">
                    <input type="hidden" id="addrId" name="id" value="0">
                    <input type="hidden" name="csrf_token" id="addrCsrf" value="<?= csrf_token() ?>">
                    <div class="addr-form-row">
                        <div class="addr-form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" id="addrName" maxlength="100" required>
                        </div>
                        <div class="addr-form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" id="addrPhone" maxlength="20">
                        </div>
                    </div>
                    <div class="addr-form-group">
                        <label>Address *</label>
                        <input type="text" name="address" id="addrAddress" maxlength="500" required>
                    </div>
                    <div class="addr-form-row">
                        <div class="addr-form-group">
                            <label>City *</label>
                            <input type="text" name="city" id="addrCity" maxlength="100" required>
                        </div>
                        <div class="addr-form-group">
                            <label>Pincode *</label>
                            <input type="text" name="pincode" id="addrPincode" maxlength="20" required>
                        </div>
                    </div>
                    <div class="addr-form-row">
                        <div class="addr-form-group">
                            <label>State</label>
                            <input type="text" name="state" id="addrState" maxlength="100">
                        </div>
                        <div class="addr-form-group">
                            <label>Label</label>
                            <select name="label" id="addrLabel">
                                <option value="Home">Home</option>
                                <option value="Work">Work</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <label class="addr-default-check">
                        <input type="checkbox" name="is_default" id="addrIsDefault" value="1">
                        Set as default address
                    </label>
                    <div class="dash-modal-error" id="addrFormError"></div>
                    <div class="dash-modal-actions">
                        <button type="button" class="btn-modal-secondary" onclick="hideAddrForm()">Back</button>
                        <button type="submit" class="btn-modal-primary" id="addrSaveBtn">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        // ── Section toggle ────────────────────────────────────────────────────────────
        function toggleSection(id) {
            const section = document.getElementById('section-' + id);
            const chevron = document.getElementById('chevron-' + id);
            if (!section) return;
            const isOpen = section.classList.contains('open');
            section.classList.toggle('open', !isOpen);
            if (chevron) {
                chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
            }
        }

        // Auto-open section if hash matches
        (function() {
            const hash = window.location.hash;
            if (hash === '#manage-account' || hash === '#account-details') {
                toggleSection('manage');
                if (hash === '#account-details') {
                    setTimeout(() => toggleSection('account-info'), 100);
                }
            }
        })();

        // ── Referral code copy ────────────────────────────────────────────────────────
        function copyReferralCode() {
            var code = document.getElementById('refCode')?.textContent?.trim();
            var btn = document.getElementById('copyRefBtn');
            if (!code) return;
            navigator.clipboard?.writeText(code).then(function() {
                if (btn) {
                    btn.textContent = 'Copied!';
                    setTimeout(function() {
                        btn.textContent = 'Copy';
                    }, 2000);
                }
            }).catch(function() {
                var ta = document.createElement('textarea');
                ta.value = code;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                if (btn) {
                    btn.textContent = 'Copied!';
                    setTimeout(function() {
                        btn.textContent = 'Copy';
                    }, 2000);
                }
            });
        }

        // ── Edit Profile Modal ────────────────────────────────────────────────────────
        function openEditProfileModal() {
            document.getElementById('editProfileError').style.display = 'none';
            document.getElementById('editProfileBackdrop').classList.add('open');
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileBackdrop').classList.remove('open');
        }

        document.getElementById('editProfileBackdrop').addEventListener('click', function(e) {
            if (e.target === this) closeEditProfileModal();
        });

        async function submitProfileUpdate(e) {
            e.preventDefault();
            const btn = document.getElementById('editProfileSaveBtn');
            const errEl = document.getElementById('editProfileError');
            const form = document.getElementById('editProfileForm');
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const res = await fetch('/api/profile/update.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await res.json();
                if (data.success) {
                    // Update displayed values
                    const nameEl = document.getElementById('display-name');
                    const phoneEl = document.getElementById('display-phone');
                    if (nameEl) nameEl.textContent = data.name;
                    if (phoneEl) phoneEl.textContent = data.phone || '—';
                    // Update avatar letter
                    const avatarLetterEl = document.getElementById('avatar-letter');
                    if (avatarLetterEl && data.name) {
                        avatarLetterEl.textContent = data.name.charAt(0).toUpperCase();
                    }
                    closeEditProfileModal();
                    if (typeof showToast === 'function') {
                        showToast('Profile updated successfully!', 'success');
                    }
                } else {
                    errEl.textContent = data.error || 'Could not update profile.';
                    errEl.style.display = 'block';
                }
            } catch (err) {
                errEl.textContent = 'An error occurred. Please try again.';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
            }
        }

        // ── Address Management Modal ──────────────────────────────────────────────────
        var _addrCsrf = document.getElementById('addrCsrf')?.value || '';
        var _addrStore = {}; // id → address object, populated by renderAddresses

        function toInt(v) {
            return parseInt(v, 10) || 0;
        }

        function esc(s) {
            var d = document.createElement('div');
            d.textContent = String(s || '');
            return d.innerHTML;
        }

        function openAddrModal() {
            document.getElementById('addrFormSection').style.display = 'none';
            document.getElementById('btnShowAddrForm').style.display = '';
            document.getElementById('addrModalBackdrop').classList.add('open');
            loadAddresses();
        }

        function closeAddrModal() {
            document.getElementById('addrModalBackdrop').classList.remove('open');
        }

        document.getElementById('addrModalBackdrop').addEventListener('click', function(e) {
            if (e.target === this) closeAddrModal();
        });

        async function loadAddresses() {
            const listEl = document.getElementById('addrList');
            listEl.innerHTML = '<p style="color:var(--Mavdee-muted);font-size:13px;">Loading…</p>';
            try {
                const res = await fetch('/api/addresses/list.php');
                const data = await res.json();
                renderAddresses(data.addresses || []);
            } catch (e) {
                listEl.innerHTML = '<p style="color:var(--Mavdee-muted);font-size:13px;">Could not load addresses.</p>';
            }
        }

        function renderAddresses(addresses) {
            const listEl = document.getElementById('addrList');
            _addrStore = {};
            if (!addresses.length) {
                listEl.innerHTML = '<p style="color:var(--Mavdee-muted);font-size:13px;text-align:center;padding:8px 0;">No saved addresses yet.</p>';
                return;
            }
            addresses.forEach(function(a) {
                _addrStore[toInt(a.id)] = a;
            });
            listEl.innerHTML = addresses.map(function(a) {
                var id = toInt(a.id);
                var defaultTag = a.is_default == 1 ? '<span class="addr-default-tag">Default</span>' : '';
                var addrLine = [a.address, a.city, a.state, a.pincode].filter(Boolean).join(', ');
                return '<div class="addr-card' + (a.is_default == 1 ? ' is-default' : '') + '">' +
                    defaultTag +
                    '<div class="addr-card-name">' + esc(a.name) + (a.phone ? ' · ' + esc(a.phone) : '') + '</div>' +
                    '<div class="addr-card-line">' + esc(a.label || 'Home') + ': ' + esc(addrLine) + '</div>' +
                    '<div class="addr-card-actions">' +
                    '<button class="btn-addr-action edit" data-addr-id="' + id + '" onclick="editAddrById(this)">Edit</button>' +
                    '<button class="btn-addr-action remove" data-addr-id="' + id + '" onclick="deleteAddr(toInt(this.dataset.addrId))">Remove</button>' +
                    '</div></div>';
            }).join('');
        }

        function showAddrForm() {
            document.getElementById('addrFormTitle').textContent = 'New Address';
            document.getElementById('addrForm').reset();
            document.getElementById('addrId').value = '0';
            document.getElementById('addrFormError').style.display = 'none';
            document.getElementById('addrFormSection').style.display = 'block';
            document.getElementById('btnShowAddrForm').style.display = 'none';
        }

        function hideAddrForm() {
            document.getElementById('addrFormSection').style.display = 'none';
            document.getElementById('btnShowAddrForm').style.display = '';
        }

        function editAddrById(btn) {
            var id = toInt(btn.dataset.addrId);
            var a = _addrStore[id];
            if (!a) return;
            editAddr(a);
        }

        function editAddr(a) {
            document.getElementById('addrFormTitle').textContent = 'Edit Address';
            document.getElementById('addrId').value = toInt(a.id);
            document.getElementById('addrName').value = a.name || '';
            document.getElementById('addrPhone').value = a.phone || '';
            document.getElementById('addrAddress').value = a.address || '';
            document.getElementById('addrCity').value = a.city || '';
            document.getElementById('addrState').value = a.state || '';
            document.getElementById('addrPincode').value = a.pincode || '';
            document.getElementById('addrIsDefault').checked = a.is_default == 1;
            var labelSel = document.getElementById('addrLabel');
            for (var i = 0; i < labelSel.options.length; i++) {
                if (labelSel.options[i].value === a.label) {
                    labelSel.selectedIndex = i;
                    break;
                }
            }
            document.getElementById('addrFormError').style.display = 'none';
            document.getElementById('addrFormSection').style.display = 'block';
            document.getElementById('btnShowAddrForm').style.display = 'none';
        }

        async function deleteAddr(id) {
            if (!confirm('Remove this address?')) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('csrf_token', _addrCsrf);
                const res = await fetch('/api/addresses/delete.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    loadAddresses();
                } else {
                    alert(data.error || 'Could not remove address.');
                }
            } catch (e) {
                alert('An error occurred.');
            }
        }

        async function submitAddr(e) {
            e.preventDefault();
            const btn = document.getElementById('addrSaveBtn');
            const errEl = document.getElementById('addrFormError');
            const form = document.getElementById('addrForm');
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Saving…';

            try {
                const res = await fetch('/api/addresses/save.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await res.json();
                if (data.success) {
                    hideAddrForm();
                    loadAddresses();
                } else {
                    errEl.textContent = data.error || 'Could not save address.';
                    errEl.style.display = 'block';
                }
            } catch (err) {
                errEl.textContent = 'An error occurred. Please try again.';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Address';
            }
        }
    </script>
</body>

</html>