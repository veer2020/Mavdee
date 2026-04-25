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
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json.']);
    exit;
}

csrf_check();

// Rate limit: max 5 registrations per IP per hour
require_once dirname(__DIR__, 2) . '/security/rate_limiter.php';
$rl    = new RateLimiter();
$rlKey = 'api_register:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rl->check($rlKey, 5, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many registration attempts. Please try again later.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
        exit;
    }

    $name     = trim($input['name']     ?? '');
    $email    = trim($input['email']    ?? '');
    $password =      $input['password'] ?? '';

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name is required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid email is required.']);
        exit;
    }

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
        exit;
    }

    $rl->increment($rlKey, 3600);

    $pdo = db();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Email already registered.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $pdo->prepare("INSERT INTO customers (name, email, password, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$name, $email, $hashed]);
    $customerId = (int)$pdo->lastInsertId();

    // ── Auto-login ────────────────────────────────────────────────────────────
    // FIX #2: Previously only $_SESSION[CUSTOMER_SESSION_KEY] ('customer_id')
    // was set. isLoggedIn() checks $_SESSION['user_id'], so the user appeared
    // as logged-out immediately after registration. status.php also showed no
    // username/email. All four session keys are now set consistently with the
    // login endpoint.
    session_regenerate_id(true);
    $_SESSION[CUSTOMER_SESSION_KEY] = $customerId;
    $_SESSION['user_id']            = $customerId;
    $_SESSION['customer_name']      = $name;
    $_SESSION['customer_email']     = $email;

    echo json_encode([
        'success'    => true,
        'redirectTo' => '/dashboard.php',
        'user'       => [
            'id'    => $customerId,
            'name'  => $name,
            'email' => $email,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}