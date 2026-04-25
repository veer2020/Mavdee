<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json.']);
    exit;
}

csrf_check();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
        exit;
    }

    $email    = trim($input['email']    ?? '');
    $password =      $input['password'] ?? '';

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email format.']);
        exit;
    }

    // ── Rate limiting ────────────────────────────────────────────────────────
    // FIX #4: The attempt counter is now incremented BEFORE the credential
    // check so every request (valid or not) counts against the limit.
    // On a successful login the counter is reset, so legitimate users are
    // never permanently locked out.
    require_once __DIR__ . '/../../security/rate_limiter.php';
    $rl    = new RateLimiter();
    $rlKey = 'api_login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (!$rl->check($rlKey, 10, 900)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please try again later.']);
        exit;
    }
    // Increment before any DB work so bots can't flood without penalty
    $rl->increment($rlKey, 900);

    $pdo = db();

    // ── Check customers table first ──────────────────────────────────────────
    $userSource = 'customers';
    $stmt = $pdo->prepare("SELECT id, name, email, password, is_active FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Fallback to legacy users table (ignored if table does not exist)
    if (!$user) {
        try {
            $userSource = 'users';
            $stmt = $pdo->prepare("SELECT id, name, email, password, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (Throwable) {
            $userSource = 'customers';
            $user       = false;
        }
    }

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
        exit;
    }

    if (empty($user['password'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'This account uses Google Sign-In. Please continue with Google or reset your password first.',
        ]);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
        exit;
    }

    if (empty($user['is_active'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Account is deactivated.']);
        exit;
    }

    // ── Session ──────────────────────────────────────────────────────────────
    session_regenerate_id(true);
    $rl->reset($rlKey);   // clear the rate-limit counter on successful login

    $_SESSION[CUSTOMER_SESSION_KEY] = $user['id'];
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['customer_name']      = $user['name'];
    $_SESSION['customer_email']     = $user['email'];

    require_once __DIR__ . '/../../includes/cart_merge.php';
    merge_guest_cart();

    // ── Optional: rehash password if algorithm/cost has changed ─────────────
    // FIX #5: Whitelist the table name before interpolating into SQL to
    // eliminate any SQL injection risk from the $userSource variable.
    $allowedTables = ['customers', 'users'];
    $safeTable     = in_array($userSource, $allowedTables, true) ? $userSource : 'customers';

    try {
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $pdo->prepare("UPDATE {$safeTable} SET password = ? WHERE id = ?")
                ->execute([
                    password_hash($password, PASSWORD_DEFAULT),
                    $user['id'],
                ]);
        }
    } catch (Throwable) {
        // Non-critical — ignore rehash failures silently.
    }

    try {
        $pdo->prepare("UPDATE {$safeTable} SET last_login_at = NOW() WHERE id = ?")
            ->execute([$user['id']]);
    } catch (Throwable) {
        // Ignore schemas without last_login_at.
    }

    echo json_encode([
        'success'    => true,
        'redirectTo' => '/dashboard.php',
        'user'       => [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}