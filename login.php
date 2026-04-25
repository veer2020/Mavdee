<?php

// ... rest of your original index.php code ...

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

// If already logged in, redirect away
if (isLoggedIn()) {
    $next = intended_redirect('index.php');
    clear_intended_redirect();
    header("Location: " . $next);
    exit;
}

$error = '';
$oauthErrors = [
    'social_not_configured' => 'Google Sign-In is not configured right now. Please use your email and password.',
    'oauth_state_mismatch' => 'Your Google sign-in session expired. Please try again.',
    'oauth_no_code' => 'Google sign-in did not return an authorization code. Please try again.',
    'oauth_token_failed' => 'We could not verify your Google account right now. Please try again.',
    'oauth_no_token' => 'Google sign-in did not return an access token. Please try again.',
    'oauth_userinfo_failed' => 'We could not fetch your Google profile. Please try again.',
    'oauth_missing_profile' => 'Your Google account is missing the email details required to sign in.',
    'account_disabled' => 'Your account has been deactivated. Please contact support.',
    'oauth_db_error' => 'We could not finish Google sign-in because of a server error. Please try again.',
];

if (isset($_GET['error']) && is_string($_GET['error'])) {
    $error = $oauthErrors[$_GET['error']] ?? 'We could not complete sign in. Please try again.';
}

$authNext = trim((string)($_GET['next'] ?? ($_SESSION['redirect_after_login'] ?? '')));
$authNext = $authNext !== '' ? safe_redirect($authNext, 'index.php') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rate limit: max 10 login attempts per IP per 15 minutes
    require_once __DIR__ . '/security/rate_limiter.php';
    $rl        = new RateLimiter();
    $rlKey     = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!$rl->check($rlKey, 10, 900)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email !== '' && $password !== '') {
            // Lookup the customer
            $customerSource = 'customers';
            $stmt = db()->prepare("SELECT id, name, email, password, is_active FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            // Fallback to legacy users table
            if (!$customer) {
                try {
                    $customerSource = 'users';
                    $stmt = db()->prepare("SELECT id, name, email, password, is_active FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $customer = $stmt->fetch();
                } catch (Throwable) {
                    $customerSource = 'customers';
                    $customer = false;
                }
            }

            if ($customer) {
                if (!empty($customer['password']) && password_verify($password, $customer['password'])) {
                    if ($customer['is_active']) {
                        $rl->reset($rlKey); // Clear rate limit on successful login
                        session_regenerate_id(true);
                        $_SESSION[CUSTOMER_SESSION_KEY] = $customer['id'];
                        $_SESSION['user_id'] = $customer['id'];
                        $_SESSION['customer_name'] = $customer['name'];
                        $_SESSION['customer_email'] = $customer['email'];

                        require_once __DIR__ . '/includes/cart_merge.php';
                        merge_guest_cart();

                        try {
                            if (password_needs_rehash($customer['password'], PASSWORD_DEFAULT)) {
                                db()->prepare("UPDATE {$customerSource} SET password = ? WHERE id = ?")->execute([
                                    password_hash($password, PASSWORD_DEFAULT),
                                    $customer['id'],
                                ]);
                            }
                        } catch (Throwable) {
                            // Rehash failures should never block login.
                        }

                        try {
                            db()->prepare("UPDATE {$customerSource} SET last_login_at = NOW() WHERE id = ?")->execute([$customer['id']]);
                        } catch (Throwable) {
                            // Ignore schemas that do not expose last_login_at.
                        }

                        $next = intended_redirect('index.php');
                        clear_intended_redirect();
                        header("Location: " . $next);
                        exit;
                    } else {
                        $error = "Your account has been deactivated. Please contact support.";
                    }
                } elseif (empty($customer['password'])) {
                    $rl->increment($rlKey, 900); // Count failed attempts
                    $error = "This account uses Google Sign-In. Please 'Continue with Google' below, or use 'Forgot Password' to set a local password.";
                } else {
                    $rl->increment($rlKey, 900); // Count failed attempts
                    $error = "Invalid email address or password.";
                }
            } else {
                $rl->increment($rlKey, 900); // Count failed attempts
                $error = "Invalid email address or password.";
            }
        } else {
            $error = "Please enter both email and password.";
        }
    } // end rate-limit else
} // end POST block
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="view-transition" content="same-origin">
    <title>Sign In - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <style>
        body.auth-body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(255, 63, 108, 0.12), transparent 28%),
                radial-gradient(circle at bottom right, rgba(3, 166, 133, 0.10), transparent 24%),
                linear-gradient(180deg, #fffaf8 0%, #f6f2ec 100%);
        }

        @media (min-width: 1024px) {
            body.auth-body {
                display: flex;
                flex-direction: column;
            }
        }

        .auth-page-wrap {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            min-height: calc(100vh - 120px);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: clamp(20px, 4vw, 42px);
            align-items: stretch;
            padding: clamp(24px, 4vw, 40px) 16px 40px;
        }

        @media (min-width: 1024px) {
            .auth-page-wrap {
                flex: 1;
                height: auto;
                padding: 12px 16px 12px;
                align-items: center;
            }
        }

        .auth-left-panel {
            display: none;
            position: relative;
            overflow: hidden;
            padding: clamp(32px, 5vw, 48px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.14), transparent 32%),
                linear-gradient(145deg, #13171e 0%, #1d2430 48%, #2c2231 100%);
            color: #fff;
            box-shadow: 0 28px 60px rgba(17, 24, 39, 0.22);
        }

        .auth-left-panel::before,
        .auth-left-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .auth-left-panel::before {
            top: -120px;
            right: -90px;
            width: 280px;
            height: 280px;
            background: rgba(255, 63, 108, 0.18);
        }

        .auth-left-panel::after {
            bottom: -110px;
            left: -60px;
            width: 220px;
            height: 220px;
            background: rgba(3, 166, 133, 0.16);
        }

        .panel-eyebrow,
        .panel-logo,
        .panel-tagline,
        .panel-sub,
        .panel-badges,
        .panel-stats {
            position: relative;
            z-index: 1;
        }

        .panel-eyebrow {
            margin-bottom: 18px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .panel-logo {
            margin-bottom: 16px;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #ffd8e1;
        }

        .panel-tagline {
            margin: 0;
            font-family: var(--f-display);
            font-size: clamp(2.4rem, 4vw, 4.1rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .panel-sub {
            margin: 18px 0 30px;
            max-width: 430px;
            font-size: 15px;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.78);
        }

        .panel-badges {
            display: grid;
            gap: 14px;
        }

        .panel-badge {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 14px;
            align-items: center;
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .panel-badge-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.10);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .panel-badge strong {
            display: block;
            margin-bottom: 3px;
            font-size: 14px;
        }

        .panel-badge span:last-child {
            font-size: 13px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.72);
        }

        .panel-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 28px;
        }

        .panel-stat {
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .panel-stat strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1.1rem;
        }

        .panel-stat span {
            font-size: 12px;
            line-height: 1.45;
            color: rgba(255, 255, 255, 0.74);
        }

        .auth-right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            width: 100%;
            max-width: 520px;
            padding: clamp(20px, 3vw, 32px);
            border-radius: 28px;
            border: 1px solid var(--mavdee-border);
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(18px);
            box-shadow: 0 24px 60px rgba(17, 24, 39, 0.10);
        }

        .auth-eyebrow {
            margin-bottom: 6px;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--mavdee-muted);
        }

        .auth-logo {
            margin-bottom: 6px;
            text-align: center;
        }

        .auth-logo-text {
            font-size: clamp(1.5rem, 3vw, 1.9rem);
            font-weight: 800;
            letter-spacing: -0.05em;
            color: var(--mavdee-pink);
        }

        .auth-title {
            margin: 0;
            text-align: center;
            font-family: var(--f-display);
            font-size: clamp(1.6rem, 2.5vw, 2.1rem);
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--mavdee-dark);
        }

        .auth-subtitle {
            margin: 6px auto 0;
            max-width: 440px;
            text-align: center;
            font-size: 13px;
            line-height: 1.5;
            color: var(--mavdee-muted);
        }

        .auth-trustbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin: 12px 0 14px;
        }

        .auth-trust-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--mavdee-border);
            background: var(--surface-soft);
            font-size: 12px;
            font-weight: 600;
            color: var(--mavdee-dark);
        }

        .auth-trust-pill::before {
            content: '';
            width: 7px;
            height: 7px;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--mavdee-green);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.10em;
            text-transform: uppercase;
            color: var(--mavdee-muted);
        }

        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            border: 1.5px solid var(--mavdee-border);
            border-radius: 16px;
            font-family: var(--font-sans);
            font-size: 15px;
            color: var(--mavdee-dark);
            background: var(--surface-soft);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-control:hover {
            border-color: var(--border-d);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--mavdee-pink);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(255, 63, 108, 0.12);
        }

        .password-field {
            position: relative;
        }

        .password-field .form-control {
            padding-right: 82px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            border: none;
            background: none;
            border-radius: 999px;
            color: var(--mavdee-pink);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            padding: 6px 8px;
        }

        .auth-helper-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 10px;
            font-size: 13px;
            color: var(--mavdee-muted);
        }

        .auth-helper-row span {
            line-height: 1.5;
        }

        .forgot-link {
            color: var(--mavdee-pink);
            font-weight: 700;
            white-space: nowrap;
        }

        .forgot-link:hover,
        .password-toggle:hover,
        .auth-links a:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            height: 50px;
            margin-top: 6px;
            border: none;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--mavdee-pink) 0%, #d72856 100%);
            color: #fff;
            font-family: var(--font-sans);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 18px 30px rgba(255, 63, 108, 0.24);
            transition: transform 0.18s, box-shadow 0.18s, filter 0.18s;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            filter: saturate(1.05);
        }

        .alert-error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(192, 20, 60, 0.14);
            border-left: 4px solid var(--mavdee-pink);
            background: #fff1f3;
            color: #b42346;
            font-size: 14px;
            line-height: 1.6;
            box-shadow: 0 10px 24px rgba(192, 20, 60, 0.06);
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 12px 0 10px;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--mavdee-border);
        }

        .auth-divider span {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--mavdee-muted);
        }

        .btn-social {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--mavdee-border);
            border-radius: 16px;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            background: #fff;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s, border-color 0.15s, transform 0.15s;
        }

        .btn-google:hover {
            transform: translateY(-1px);
            border-color: var(--border-d);
            box-shadow: 0 12px 24px rgba(17, 24, 39, 0.08);
        }

        .auth-note {
            margin-top: 8px;
            text-align: center;
            font-size: 12px;
            line-height: 1.5;
            color: var(--mavdee-muted);
        }

        .auth-links {
            margin-top: 10px;
            text-align: center;
            font-size: 14px;
            color: var(--mavdee-muted);
        }

        .auth-links a {
            color: var(--mavdee-pink);
            font-weight: 700;
        }

        @media (min-width: 1024px) {
            .auth-left-panel {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 0;
                height: 100%;
            }
        }

        @media (max-width: 1023px) {
            .auth-page-wrap {
                grid-template-columns: 1fr;
                min-height: auto;
            }
        }

        @media (max-width: 640px) {
            .auth-card {
                padding: 24px 18px;
                border-radius: 22px;
            }

            .auth-helper-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .auth-page-wrap {
                padding: 14px 12px 30px;
            }

            .auth-card {
                padding: 22px 16px;
                border-radius: 20px;
            }

            .panel-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="auth-body">

    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="auth-page-wrap">
        <section class="auth-left-panel" aria-label="Benefits of signing in">
            <div>
                <div class="panel-eyebrow">Customer access</div>
                <div class="panel-logo"><?= htmlspecialchars(SITE_NAME) ?></div>
                <p class="panel-tagline">Pick up your shopping journey right where you left it.</p>
                <p class="panel-sub">Sign in to recover saved products, check live order status, and move through checkout faster on both desktop and mobile.</p>
            </div>

            <div class="panel-badges">
                <div class="panel-badge">
                    <span class="panel-badge-icon">01</span>
                    <div>
                        <strong>Saved wishlist and cart</strong>
                        <span>Your favorites stay synced across devices after sign-in.</span>
                    </div>
                </div>
                <div class="panel-badge">
                    <span class="panel-badge-icon">02</span>
                    <div>
                        <strong>Secure checkout flow</strong>
                        <span>Reach payment and address steps faster with your account restored.</span>
                    </div>
                </div>
                <div class="panel-badge">
                    <span class="panel-badge-icon">03</span>
                    <div>
                        <strong>Clear post-order tracking</strong>
                        <span>Follow deliveries, returns, and order updates in one place.</span>
                    </div>
                </div>
            </div>

            <div class="panel-stats">
                <div class="panel-stat">
                    <strong>24x7</strong>
                    <span>Access to your account and order history</span>
                </div>
                <div class="panel-stat">
                    <strong>1 tap</strong>
                    <span>Saved items ready after login</span>
                </div>
                <div class="panel-stat">
                    <strong>100%</strong>
                    <span>Responsive flow built for phone and desktop</span>
                </div>
            </div>
        </section>

        <section class="auth-right-panel">
            <div class="auth-card">
                <div class="auth-eyebrow">Sign in securely</div>
                <div class="auth-logo">
                    <span class="auth-logo-text"><?= htmlspecialchars(SITE_NAME) ?></span>
                </div>
                <h1 class="auth-title">Welcome back</h1>
                <p class="auth-subtitle">Use your customer account to manage orders, wishlist items, addresses, and checkout details in one place.</p>

                <div class="auth-trustbar" aria-hidden="true">
                    <span class="auth-trust-pill">Faster checkout</span>
                    <span class="auth-trust-pill">Saved wishlist</span>
                    <span class="auth-trust-pill">Order tracking</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert-error" role="alert" aria-live="polite"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="" data-auth="login">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="email" class="form-control" required placeholder="Enter your email" autocomplete="email" inputmode="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <div class="password-field">
                            <input type="password" id="loginPassword" name="password" class="form-control" required placeholder="Enter your password" autocomplete="current-password">
                            <button type="button" class="password-toggle" data-password-toggle="#loginPassword" aria-label="Show password">Show</button>
                        </div>
                        <div class="auth-helper-row">
                            <span>Use the same account you use for orders and wishlist.</span>
                            <a href="/forgot_password.php" class="forgot-link">Forgot password? / Set a password</a>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">Sign In</button>
                </form>

                <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
                    <div>
                        <div class="auth-divider"><span>Or continue with</span></div>
                        <?php
                        $googleRedirect = absolute_url('/api/auth/google_callback.php');
                        $googleState    = bin2hex(random_bytes(12));
                        $_SESSION['oauth_state'] = $googleState;
                        $_SESSION['oauth_redirect_after_login'] = intended_redirect('/dashboard.php');
                        $googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                            'client_id'     => GOOGLE_CLIENT_ID,
                            'redirect_uri'  => $googleRedirect,
                            'response_type' => 'code',
                            'scope'         => 'openid email profile',
                            'state'         => $googleState,
                            'prompt'        => 'select_account',
                        ]);
                        ?>
                        <a href="<?= h($googleUrl) ?>" class="btn-social btn-google">
                            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                            </svg>
                            Continue with Google
                        </a>
                    </div>
                <?php endif; ?>

                <p class="auth-note">Your cart and account details stay protected, and your saved items remain available after sign-in.</p>

                <div class="auth-links">
                    New to <?= htmlspecialchars(SITE_NAME) ?>?
                    <a href="register.php<?= $authNext !== '' ? '?next=' . urlencode($authNext) : '' ?>">Create Account</a>
                </div>
            </div>
        </section>
    </main>
    <script src="/assets/js/ui.js" defer></script>
    <script src="/assets/js/auth.js" defer></script>
</body>

</html>