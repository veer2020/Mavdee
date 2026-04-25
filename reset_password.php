<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
$valid = false;

if ($token) {
    try {
        $stmt = db()->prepare(
            "SELECT id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1"
        );
        $stmt->execute([$token]);
        $valid = (bool)$stmt->fetch();
    } catch (Throwable) {
        $valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require __DIR__ . '/includes/head-favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= h(SITE_NAME) ?></title>
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
            <?php if (!$token || !$valid): ?>
                <h1 class="auth-title">Invalid Link</h1>
                <p class="auth-subtitle">This password reset link is invalid or has expired.</p>
                <div class="auth-links">
                    <a href="forgot_password.php">Request a new reset link</a>
                    &nbsp;·&nbsp;
                    <a href="login.php">Sign In</a>
                </div>
            <?php else: ?>
                <h1 class="auth-title">New Password</h1>
                <p class="auth-subtitle">Choose a strong password for your account.</p>
                <div id="alertBox" style="display:none;" class="alert"></div>
                <form id="resetForm">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" id="passwordInput" class="form-control" required placeholder="Min. 8 characters" minlength="8">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirmInput" class="form-control" required placeholder="Repeat new password">
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">Update Password</button>
                </form>
                <div class="auth-links">
                    <a href="login.php">← Back to Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <?php if ($valid): ?>
        <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
            document.getElementById('resetForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitBtn');
                const alertBox = document.getElementById('alertBox');
                btn.disabled = true;
                btn.textContent = 'Updating…';
                alertBox.style.display = 'none';
                try {
                    const fd = new FormData(this);
                    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    const res = await fetch('/api/auth/reset_password.php', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    alertBox.style.display = '';
                    if (data.success) {
                        alertBox.className = 'alert alert-success';
                        alertBox.textContent = 'Password updated! Redirecting to sign in…';
                        document.getElementById('resetForm').style.display = 'none';
                        setTimeout(() => {
                            window.location = '/login.php';
                        }, 2000);
                    } else {
                        alertBox.className = 'alert alert-error';
                        alertBox.textContent = data.error || 'Something went wrong. Please try again.';
                        btn.disabled = false;
                        btn.textContent = 'Update Password';
                    }
                } catch (err) {
                    alertBox.style.display = '';
                    alertBox.className = 'alert alert-error';
                    alertBox.textContent = 'Network error. Please try again.';
                    btn.disabled = false;
                    btn.textContent = 'Update Password';
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>