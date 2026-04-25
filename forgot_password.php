<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Check if feature is enabled
$forgotEnabled = (bool)getSetting('forgot_password_enabled', '1');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global.css">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--font-sans);
            font-size: 14px;
            background: var(--mavdee-grey);
            color: var(--mavdee-text);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .auth-page-wrap {
            min-height: calc(100vh - 120px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .auth-card {
            background: #fff;
            width: 100%;
            max-width: 400px;
            padding: 32px 28px;
            border: 1px solid var(--mavdee-border);
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 8px;
        }

        .auth-logo-text {
            font-size: 2rem;
            font-weight: 800;
            color: var(--mavdee-pink);
            letter-spacing: -0.04em;
        }

        .auth-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--mavdee-dark);
            margin: 0 0 4px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .auth-subtitle {
            text-align: center;
            color: var(--mavdee-muted);
            margin-bottom: 24px;
            font-size: 13px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--mavdee-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--mavdee-border);
            border-radius: 4px;
            font-family: var(--font-sans);
            font-size: 15px;
            color: var(--mavdee-dark);
            transition: border-color 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--mavdee-pink);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--mavdee-pink);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #e0325a;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .auth-links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--mavdee-muted);
        }

        .auth-links a {
            color: var(--mavdee-pink);
            font-weight: 600;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-weight: 500;
            font-size: 14px;
        }

        .alert-error {
            background: #fff0f3;
            color: #c0143c;
            border-left: 3px solid var(--mavdee-pink);
        }

        .alert-success {
            background: #f0faf8;
            color: #027a62;
            border-left: 3px solid var(--mavdee-green);
        }

        .disabled-notice {
            text-align: center;
            padding: 20px;
            color: var(--mavdee-muted);
            font-size: 15px;
        }

        @media(max-width:480px) {
            .auth-card {
                padding: 24px 16px;
                border: none;
            }

            .auth-page-wrap {
                align-items: flex-start;
                padding-top: 0;
            }
        }
    </style>
</head>

<body>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="auth-page-wrap">
        <div class="auth-card">
            <div class="auth-logo">
                <span class="auth-logo-text"><?= h(SITE_NAME) ?></span>
            </div>
            <h1 class="auth-title">Forgot Password</h1>
            <?php if (!$forgotEnabled): ?>
                <div class="disabled-notice">
                    <p>Password reset is currently disabled. Please contact support.</p>
                    <div class="auth-links"><a href="login.php">← Back to Sign In</a></div>
                </div>
            <?php else: ?>
                <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>
                <div id="alertBox" style="display:none;" class="alert"></div>
                <form id="forgotForm">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="emailInput" class="form-control" required placeholder="you@example.com" autocomplete="email">
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">Send Reset Link</button>
                </form>
                <div class="auth-links">
                    Remember your password? <a href="login.php">Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        const form = document.getElementById('forgotForm');
        if (form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitBtn');
                const alertBox = document.getElementById('alertBox');
                const email = document.getElementById('emailInput').value;
                btn.disabled = true;
                btn.textContent = 'Sending…';
                alertBox.style.display = 'none';
                try {
                    const fd = new FormData();
                    fd.set('email', email);
                    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    const res = await fetch('/api/auth/forgot_password.php', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    alertBox.style.display = '';
                    if (data.success) {
                        alertBox.className = 'alert alert-success';
                        alertBox.textContent = 'If an account exists with that email, a reset link has been sent. Please check your inbox.';
                        form.reset();
                    } else {
                        alertBox.className = 'alert alert-error';
                        alertBox.textContent = data.error || 'Something went wrong. Please try again.';
                    }
                } catch (err) {
                    alertBox.style.display = '';
                    alertBox.className = 'alert alert-error';
                    alertBox.textContent = 'Network error. Please try again.';
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Send Reset Link';
                }
            });
        }
    </script>
</body>

</html>