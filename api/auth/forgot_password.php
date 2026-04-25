<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../../includes/email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

csrf_check();

// Rate limit: max 3 reset requests per email per hour
require_once __DIR__ . '/../../security/rate_limiter.php';
$rl    = new RateLimiter();
$rlKey = 'forgot:' . ($_POST['email'] ?? '') . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rl->check($rlKey, 3, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please try again in 1 hour.']);
    exit;
}
$rl->increment($rlKey, 3600);

// Check if feature is enabled
if (!getSetting('forgot_password_enabled', '1')) {
    http_response_code(403);
    echo json_encode(['error' => 'Password reset is currently disabled.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

try {
    $stmt = db()->prepare("SELECT id, name FROM customers WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    // Always return success to prevent user enumeration
    if ($customer) {
        // Invalidate old tokens for this user and clean up globally expired tokens
        db()->prepare("DELETE FROM password_reset_tokens WHERE customer_id = ? OR expires_at <= NOW()")
            ->execute([$customer['id']]);

        $token          = bin2hex(random_bytes(32));
        $expirySeconds  = 3600; // 1 hour
        $expiresAt      = date('Y-m-d H:i:s', time() + $expirySeconds);

        db()->prepare(
            "INSERT INTO password_reset_tokens (customer_id, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$customer['id'], $token, $expiresAt]);

        $resetLink = rtrim(SITE_URL, '/') . '/reset_password.php?token=' . urlencode($token);

        // Send the email
        $emailHandler = new EmailHandler();
        $emailHandler->sendPasswordReset($email, $customer['name'], $resetLink);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
}