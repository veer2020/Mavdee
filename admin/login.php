<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_auth.php';

// Redirect if already logged in
if (!empty($_SESSION[ADMIN_SESSION_KEY])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rate limit: max 5 admin login attempts per IP per 15 minutes
    require_once __DIR__ . '/../security/rate_limiter.php';
    $rl    = new RateLimiter();
    $rlKey = 'admin_login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!$rl->check($rlKey, 5, 900)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } else {
            try {
                $stmt = db()->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $rl->reset($rlKey); // Clear rate limit on successful login

                    // ★★★★★ SESSION FIXATION FIX ★★★★★
                    session_regenerate_id(true);
                    $_SESSION[ADMIN_SESSION_KEY] = $admin['id'];
                    $_SESSION['admin_name']      = $admin['name'];
                    $_SESSION['admin_role']      = $admin['role'];

                    // Update last login timestamp
                    db()->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")
                        ->execute([$admin['id']]);

                    // Log the login
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    db()->prepare("INSERT INTO activity_log (admin_id, action, detail, ip, created_at) VALUES (?, 'login', 'Admin logged in', ?, NOW())")
                        ->execute([$admin['id'], $ip]);

                    header('Location: index.php');
                    exit;
                } else {
                    $rl->increment($rlKey, 900);
                    $error = 'Invalid email or password.';
                }
            } catch (Throwable $e) {
                $error = 'A database error occurred. Please try again.';
            }
        }
    } // end rate-limit check
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login — <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1d23 0%, #252930 50%, #1a1d23 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .4);
        }

        .login-logo {
            width: 52px;
            height: 52px;
            background: #f8c146;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1d23;
            margin: 0 auto 12px;
        }

        .login-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a1d23;
            text-align: center;
            margin-bottom: 4px;
        }

        .login-sub {
            font-size: .85rem;
            color: #6b7280;
            text-align: center;
            margin-bottom: 28px;
        }

        .form-label {
            font-weight: 500;
            font-size: .875rem;
            color: #374151;
        }

        .form-control {
            border-radius: 8px;
            border-color: #d1d5db;
            padding: 10px 14px;
            font-size: .9rem;
        }

        .form-control:focus {
            border-color: #f8c146;
            box-shadow: 0 0 0 3px rgba(248, 193, 70, .18);
        }

        .btn-login {
            background: #f8c146;
            color: #1a1d23;
            border: none;
            font-weight: 700;
            font-size: .95rem;
            border-radius: 8px;
            padding: 11px;
            width: 100%;
            transition: background .2s;
        }

        .btn-login:hover {
            background: #e6af35;
            color: #1a1d23;
        }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .875rem;
            color: #991b1b;
            margin-bottom: 20px;
        }

        .storefront-link {
            text-align: center;
            margin-top: 24px;
            font-size: .8rem;
            color: #9ca3af;
        }

        .storefront-link a {
            color: #6b7280;
            text-decoration: none;
        }

        .storefront-link a:hover {
            color: #1a1d23;
        }

        .pw-toggle {
            cursor: pointer;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            background: none;
            border: none;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-logo"><?= strtoupper(substr(SITE_NAME, 0, 1)) ?></div>
        <div class="login-title"><?= h(SITE_NAME) ?></div>
        <div class="login-sub">Sign in to your admin panel</div>

        <?php if ($error !== ''): ?>
            <div class="error-box">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    placeholder="admin@example.com"
                    autocomplete="email"
                    required
                    autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        required
                        style="padding-right: 42px;">
                    <button type="button" class="pw-toggle" onclick="togglePw()" title="Show/hide password">
                        <i class="bi bi-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-shield-lock me-2"></i>Sign In
            </button>
        </form>

        <div class="storefront-link">
            <a href="../index.php"><i class="bi bi-arrow-left me-1"></i>Back to storefront</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $_SESSION['csp_nonce'] ?? '' ?>">
        function togglePw() {
            const inp = document.getElementById('password');
            const icon = document.getElementById('pwIcon');
            if (inp.type === 'password') {
                inp.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                inp.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
    </script>
</body>

</html>