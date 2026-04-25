<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

csrf_check();

// ── Rate limiting ─────────────────────────────────────────────────────────────
// FIX #7: No rate limiting existed on this endpoint. An attacker could hammer
// it with token guesses. Limit to 10 attempts per IP per hour.
require_once __DIR__ . '/../../security/rate_limiter.php';
$rl    = new RateLimiter();
$rlKey = 'reset_pw:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!$rl->check($rlKey, 10, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts. Please try again later.']);
    exit;
}
$rl->increment($rlKey, 3600);

$token    = trim($_POST['token']            ?? '');
$password =      $_POST['password']         ?? '';
$confirm  =      $_POST['confirm_password'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid reset link.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters.']);
    exit;
}

if ($password !== $confirm) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwords do not match.']);
    exit;
}

try {
    $stmt = db()->prepare(
        "SELECT id, customer_id FROM password_reset_tokens
         WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['error' => 'This reset link has expired or already been used.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    db()->prepare("UPDATE customers SET password = ? WHERE id = ?")
        ->execute([$hashed, $row['customer_id']]);

    db()->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")
        ->execute([$row['id']]);

    // Clear the rate-limit counter on a successful reset
    $rl->reset($rlKey);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again.']);
}