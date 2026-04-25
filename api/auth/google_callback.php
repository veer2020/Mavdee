<?php
/**
 * api/auth/google_callback.php
 * Google OAuth 2.0 callback handler.
 *
 * Required env vars (set in .env):
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, SITE_URL
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

$redirectUri = absolute_url('/api/auth/google_callback.php');

// ── Guard: config must be set ─────────────────────────────────────────────────
if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '' ||
    !defined('GOOGLE_CLIENT_SECRET') || GOOGLE_CLIENT_SECRET === '') {
    header('Location: /login.php?error=social_not_configured');
    exit;
}

// ── State check ───────────────────────────────────────────────────────────────
$state = $_GET['state'] ?? '';
if (!hash_equals((string)($_SESSION['oauth_state'] ?? ''), $state)) {
    header('Location: /login.php?error=oauth_state_mismatch');
    exit;
}
unset($_SESSION['oauth_state']);

// ── Code exchange ─────────────────────────────────────────────────────────────
$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: /login.php?error=oauth_no_code');
    exit;
}

// ── cURL helpers ──────────────────────────────────────────────────────────────
// FIX #9: The original code used file_get_contents() with an HTTP stream
// context. This silently returns false when allow_url_fopen is disabled (a
// common hardened-PHP setting), giving the user an opaque OAuth error.
// cURL is the correct, portable approach for server-to-server HTTP calls.

/**
 * POST to a URL with application/x-www-form-urlencoded body.
 * Returns the response body string, or false on failure.
 */
function oauth_http_post(string $url, array $fields): string|false
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('OAuth cURL POST error: ' . $error);
        return false;
    }
    return $result;
}

/**
 * GET a URL with a Bearer token.
 * Returns the response body string, or false on failure.
 */
function oauth_http_get(string $url, string $bearerToken): string|false
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $bearerToken"],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('OAuth cURL GET error: ' . $error);
        return false;
    }
    return $result;
}

// ── Token exchange ────────────────────────────────────────────────────────────
$tokenResponse = oauth_http_post('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

if (!$tokenResponse) {
    header('Location: /login.php?error=oauth_token_failed');
    exit;
}

$tokenData   = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';
if ($accessToken === '') {
    header('Location: /login.php?error=oauth_no_token');
    exit;
}

// ── Get user info ─────────────────────────────────────────────────────────────
$userInfoJson = oauth_http_get('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);

if (!$userInfoJson) {
    header('Location: /login.php?error=oauth_userinfo_failed');
    exit;
}

$userInfo = json_decode($userInfoJson, true);
$googleId = $userInfo['sub']   ?? '';
$email    = $userInfo['email'] ?? '';
$name     = $userInfo['name']  ?? '';

if ($googleId === '' || $email === '') {
    header('Location: /login.php?error=oauth_missing_profile');
    exit;
}

// ── Find or create customer ───────────────────────────────────────────────────
try {
    // Check if this Google account is already linked
    $stmt = db()->prepare(
        "SELECT sa.customer_id FROM social_accounts sa WHERE sa.provider = 'google' AND sa.provider_id = ? LIMIT 1"
    );
    $stmt->execute([$googleId]);
    $link = $stmt->fetch();

    if ($link) {
        $customerId = (int)$link['customer_id'];
    } else {
        // Try to find customer by email
        $cStmt = db()->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $cStmt->execute([$email]);
        $existing = $cStmt->fetch();

        if ($existing) {
            $customerId = (int)$existing['id'];
        } else {
            // Create new customer
            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $insStmt        = db()->prepare(
                "INSERT INTO customers (name, email, password, is_active, created_at) VALUES (?,?,?,1,NOW())"
            );
            $insStmt->execute([$name, $email, $randomPassword]);
            $customerId = (int)db()->lastInsertId();
        }

        // Link social account
        db()->prepare(
            "INSERT INTO social_accounts (customer_id, provider, provider_id) VALUES (?,?,?)"
        )->execute([$customerId, 'google', $googleId]);
    }

    // Load customer for session
    $custStmt = db()->prepare("SELECT id, name, email, is_active FROM customers WHERE id = ? LIMIT 1");
    $custStmt->execute([$customerId]);
    $customer = $custStmt->fetch();

    if (!$customer || !$customer['is_active']) {
        header('Location: /login.php?error=account_disabled');
        exit;
    }

    // Set session
    session_regenerate_id(true);
    $_SESSION[CUSTOMER_SESSION_KEY] = (int)$customer['id'];
    $_SESSION['user_id']            = (int)$customer['id'];
    $_SESSION['customer_name']      = $customer['name'];
    $_SESSION['customer_email']     = $customer['email'];

    require_once dirname(__DIR__, 2) . '/includes/cart_merge.php';
    merge_guest_cart();

    db()->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?")->execute([$customerId]);

    $next = safe_redirect((string)($_SESSION['oauth_redirect_after_login'] ?? '/dashboard.php'), '/dashboard.php');
    clear_intended_redirect();
    header('Location: ' . $next);
    exit;

} catch (Throwable $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    header('Location: /login.php?error=oauth_db_error');
    exit;
}