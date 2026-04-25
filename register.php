<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

if (isLoggedIn()) {
    $next = intended_redirect('index.php');
    clear_intended_redirect();
    header("Location: " . $next);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_once __DIR__ . '/security/rate_limiter.php';
    $rl    = new RateLimiter();
    $rlKey = 'register:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!$rl->check($rlKey, 5, 3600)) {
        $error = 'Too many registration attempts. Please try again later.';
    }
    if (!$error) {
        $rl->increment($rlKey, 3600);
        $name             = trim($_POST['name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$name || !$email || !$password) {
            $error = "Name, email, and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($password !== $confirm_password) {
            $error = "Your passwords do not match.";
        } elseif (strlen($password) < 8) {
            $error = "Your password must be at least 8 characters long.";
        } else {
            $stmt = db()->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email address already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = db()->prepare("INSERT INTO customers (name, email, phone, password, is_active) VALUES (?, ?, ?, ?, 1)");
                if ($stmt->execute([$name, $email, $phone, $hashed])) {
                    $customerId = db()->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION[CUSTOMER_SESSION_KEY] = $customerId;
                    $_SESSION['user_id']            = $customerId;
                    $_SESSION['customer_name']      = $name;
                    $_SESSION['customer_email']     = $email;
                    try {
                        require_once __DIR__ . '/includes/crm.php';
                        (new CRMMailer())->sendWelcomeEmail($email, $name);
                    } catch (Throwable $e) {
                    }
                    $next = intended_redirect('index.php');
                    clear_intended_redirect();
                    header("Location: " . $next);
                    exit;
                } else {
                    $error = "Registration failed. Please try again later.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Create Account — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --coral: #FF4757;
            --coral-d: #e03346;
            --coral-xl: #fff0f1;
            --ink: #0F0F0F;
            --text: #2D2926;
            --muted: #8B8680;
            --surface: #F8F7F5;
            --border: #E8E5E1;
            --white: #fff;
            --jade: #00b894;
            --amber: #fdcb6e;
            --f-d: 'Syne', sans-serif;
            --f-b: 'DM Sans', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--f-b);
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ── Layout ── */
        .auth-layout {
            display: grid;
            grid-template-columns: 1fr;
            min-height: 100vh;
        }

        @media (min-width: 960px) {
            .auth-layout {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* ── Left Panel ── */
        .auth-panel {
            display: none;
            background: var(--ink);
            color: #fff;
            padding: 60px 56px;
            position: relative;
            overflow: hidden;
            flex-direction: column;
            justify-content: space-between;
        }

        @media (min-width: 960px) {
            .auth-panel {
                display: flex;
            }
        }

        .auth-panel::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(255, 71, 87, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .auth-panel::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -60px;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(0, 184, 148, 0.18) 0%, transparent 70%);
            pointer-events: none;
        }

        .panel-logo {
            font-family: var(--f-d);
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--coral);
            letter-spacing: -0.02em;
        }

        .panel-body {
            position: relative;
            z-index: 1;
        }

        .panel-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--coral);
            margin-bottom: 12px;
        }

        .panel-headline {
            font-family: var(--f-d);
            font-size: 3.2rem;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
            margin-bottom: 20px;
        }

        .panel-headline em {
            color: var(--coral);
            font-style: normal;
        }

        .panel-sub {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.7;
            max-width: 340px;
            margin-bottom: 40px;
        }

        .panel-perks {
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: relative;
            z-index: 1;
        }

        .panel-perk {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 14px 18px;
            backdrop-filter: blur(8px);
        }

        .panel-perk-icon {
            width: 38px;
            height: 38px;
            background: rgba(255, 71, 87, 0.18);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .panel-perk-title {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .panel-perk-sub {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.55);
            margin-top: 2px;
        }

        .panel-social-proof {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .proof-avatars {
            display: flex;
        }

        .proof-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid var(--ink);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-left: -8px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .proof-avatar:first-child {
            margin-left: 0;
        }

        .proof-text {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        .proof-text strong {
            color: #fff;
        }

        /* ── Right / Form Panel ── */
        .auth-form-wrap {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 32px;
            background: var(--white);
        }

        @media (min-width: 960px) {
            .auth-form-wrap {
                padding: 60px 56px;
            }
        }

        .form-logo {
            font-family: var(--f-d);
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--coral);
            margin-bottom: 32px;
            display: block;
        }

        @media (min-width: 960px) {
            .form-logo {
                display: none;
            }
        }

        .form-title {
            font-family: var(--f-d);
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-bottom: 4px;
        }

        .form-sub {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 28px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-grid .full {
            grid-column: 1/-1;
        }

        .f-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .f-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .f-input {
            height: 50px;
            padding: 0 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            font-family: var(--f-b);
            color: var(--ink);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .f-input:focus {
            border-color: var(--coral);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.10);
        }

        .f-input::placeholder {
            color: var(--muted);
            opacity: 0.7;
        }

        .f-input.error {
            border-color: var(--coral);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .f-input {
            padding-right: 48px;
        }

        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
            padding: 0;
        }

        .pw-strength {
            height: 3px;
            border-radius: 99px;
            background: var(--border);
            margin-top: 6px;
            overflow: hidden;
        }

        .pw-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 99px;
            transition: width 0.3s, background 0.3s;
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: var(--coral);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: var(--f-d);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--coral-d);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.35);
        }

        .btn-submit:disabled {
            background: #ccc;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .trust-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 14px 0;
            border-top: 1px solid var(--border);
            margin-top: 24px;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: var(--muted);
        }

        .trust-item svg {
            color: var(--jade);
        }

        .signin-link {
            text-align: center;
            font-size: 14px;
            color: var(--muted);
            margin-top: 16px;
        }

        .signin-link a {
            color: var(--coral);
            font-weight: 700;
        }

        .alert-error {
            background: var(--coral-xl);
            color: #c0143c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid var(--coral);
            margin-bottom: 20px;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <div class="auth-layout">
        <!-- ── Left Branding Panel ── -->
        <div class="auth-panel">
            <div class="panel-logo"><?= htmlspecialchars(SITE_NAME) ?></div>

            <div class="panel-body">
                <div class="panel-eyebrow">New here?</div>
                <h1 class="panel-headline">
                    Style Starts<br>with <em>You</em>
                </h1>
                <p class="panel-sub">
                    Join millions of shoppers discovering India's best fashion every day. Exclusive deals, curated looks, zero compromise.
                </p>

                <div class="panel-perks">
                    <div class="panel-perk">
                        <div class="panel-perk-icon">💎</div>
                        <div>
                            <div class="panel-perk-title">Exclusive Member Offers</div>
                            <div class="panel-perk-sub">Unlock deals only available to registered members</div>
                        </div>
                    </div>
                    <div class="panel-perk">
                        <div class="panel-perk-icon">⚡</div>
                        <div>
                            <div class="panel-perk-title">Express Checkout</div>
                            <div class="panel-perk-sub">Save your address once, checkout in seconds</div>
                        </div>
                    </div>
                    <div class="panel-perk">
                        <div class="panel-perk-icon">📦</div>
                        <div>
                            <div class="panel-perk-title">Real-time Order Tracking</div>
                            <div class="panel-perk-sub">From warehouse to your doorstep — always informed</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-social-proof">
                <div class="proof-avatars">
                    <div class="proof-avatar">P</div>
                    <div class="proof-avatar" style="background:linear-gradient(135deg,#f093fb,#f5576c)">A</div>
                    <div class="proof-avatar" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">S</div>
                    <div class="proof-avatar" style="background:linear-gradient(135deg,#43e97b,#38f9d7)">+</div>
                </div>
                <div class="proof-text"><strong>2.4M+</strong> happy shoppers & counting</div>
            </div>
        </div>

        <!-- ── Right Form Panel ── -->
        <div class="auth-form-wrap">
            <span class="form-logo"><?= htmlspecialchars(SITE_NAME) ?></span>
            <h2 class="form-title">Create Account</h2>
            <p class="form-sub">Join <?= htmlspecialchars(SITE_NAME) ?> — it takes under a minute</p>

            <?php if ($error): ?>
                <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form id="regForm" data-auth="register">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form-grid">
                    <div class="f-group full">
                        <label class="f-label" for="f_name">Full Name *</label>
                        <input class="f-input" type="text" id="f_name" name="name" required
                            placeholder="Neha Gupta"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="f-group">
                        <label class="f-label" for="f_email">Email *</label>
                        <input class="f-input" type="email" id="f_email" name="email" required
                            placeholder="you@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="f-group">
                        <label class="f-label" for="f_phone">Phone <span style="font-weight:400;font-size:10px;text-transform:none">(optional)</span></label>
                        <input class="f-input" type="tel" id="f_phone" name="phone"
                            placeholder="+91 98765 43210"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="f-group">
                        <label class="f-label" for="f_pw">Password *</label>
                        <div class="password-wrap">
                            <input class="f-input" type="password" id="f_pw" name="password" required
                                placeholder="Min. 8 characters"
                                oninput="updateStrength(this.value)">
                            <button type="button" class="toggle-pw" onclick="togglePw('f_pw',this)">👁</button>
                        </div>
                        <div class="pw-strength">
                            <div class="pw-strength-bar" id="pwBar"></div>
                        </div>
                    </div>
                    <div class="f-group">
                        <label class="f-label" for="f_cpw">Confirm Password *</label>
                        <div class="password-wrap">
                            <input class="f-input" type="password" id="f_cpw" name="confirm_password" required
                                placeholder="Re-enter password">
                            <button type="button" class="toggle-pw" onclick="togglePw('f_cpw',this)">👁</button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                        <circle cx="8.5" cy="7" r="4" />
                        <line x1="20" y1="8" x2="20" y2="14" />
                        <line x1="23" y1="11" x2="17" y2="11" />
                    </svg>
                    Create My Account
                </button>
            </form>

            <div class="trust-row">
                <div class="trust-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <rect x="3" y="11" width="18" height="11" rx="2" />
                        <path d="M7 11V7a5 5 0 0110 0v4" />
                    </svg>
                    SSL Secure
                </div>
                <div class="trust-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Privacy Safe
                </div>
                <div class="trust-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    No Spam
                </div>
            </div>

            <div class="signin-link">
                Already have an account?
                <a href="/login.php<?= isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : '' ?>">Sign In →</a>
            </div>
        </div>
    </div>

    <script>
        function togglePw(id, btn) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁';
            }
        }

        function updateStrength(val) {
            const bar = document.getElementById('pwBar');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const colors = ['#ff4757', '#fdcb6e', '#fdcb6e', '#00b894', '#00b894'];
            const widths = ['20%', '40%', '60%', '80%', '100%'];
            bar.style.width = widths[score] || '0%';
            bar.style.background = colors[score] || '#eee';
        }
        document.getElementById('regForm').addEventListener('submit', function(e) {
            if (typeof window.validateRegisterForm === 'function' && !window.validateRegisterForm(this)) {
                e.preventDefault();
                return;
            }
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Creating…';
        });
    </script>
    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
    <script src="/assets/js/auth.js" defer></script>
</body>

</html>