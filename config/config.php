<?php

/**
 * config/config.php — Core Configuration & Helper Functions
 * Initializes session, constants, CSRF protection, and utility functions
 */

declare(strict_types=1);

// ── Prevent multiple includes ────────────────────────────────────────────────
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// ── Load environment variables ───────────────────────────────────────────────
if (file_exists(__DIR__ . '/../.env')) {
    $__env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($__env as $key => $value) {
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

// ── Request helpers (hosting / proxy aware) ─────────────────────────────────
if (!function_exists('request_forwarded_value')) {
    function request_forwarded_value(string $header): string
    {
        $value = trim((string)($_SERVER[$header] ?? ''));
        if ($value === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $value));
        return strtolower((string)($parts[0] ?? ''));
    }
}

if (!function_exists('request_is_secure')) {
    function request_is_secure(): bool
    {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        if (request_forwarded_value('HTTP_X_FORWARDED_PROTO') === 'https') {
            return true;
        }

        $forwardedSsl = request_forwarded_value('HTTP_X_FORWARDED_SSL');
        if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
            return true;
        }

        if (request_forwarded_value('HTTP_FRONT_END_HTTPS') === 'on') {
            return true;
        }

        return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}

if (!function_exists('request_scheme')) {
    function request_scheme(): string
    {
        return request_is_secure() ? 'https' : 'http';
    }
}

if (!function_exists('request_host')) {
    function request_host(): string
    {
        $forwardedHost = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
        $rawHost = $forwardedHost !== ''
            ? explode(',', $forwardedHost)[0]
            : (($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));

        $host = strtolower(trim((string)$rawHost));
        return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    }
}

if (!function_exists('cookie_domain')) {
    function cookie_domain(): string
    {
        $host = request_host();
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) || !str_contains($host, '.')) {
            return '';
        }

        return $host;
    }
}

// ── Session security (MUST be set before session_start) ─────────────────────
// FIX #3: Was hardcoded to '.mavdee.com' — breaks localhost and all staging
// environments. Now uses the dynamic cookie_domain() helper defined above.
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => cookie_domain(),
    'secure'   => request_is_secure(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_name('MAVDEESESSID');

// ── Start session safely ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── CSP Nonce (for script-src) ──────────────────────────────────────────────
if (empty($_SESSION['csp_nonce'])) {
    $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
}
$nonce = $_SESSION['csp_nonce'];

// ── Send CSP header (removes unsafe-inline/unsafe-eval) ─────────────────────
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce' https://checkout.razorpay.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://api.razorpay.com; connect-src 'self' https://api.razorpay.com; object-src 'none'; base-uri 'self';");

// ── Other security headers ──────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── Site Constants ───────────────────────────────────────────────────────────
define('SITE_NAME',    getenv('SITE_NAME')    ?: 'Mavdee');
define('SITE_TAGLINE', getenv('SITE_TAGLINE') ?: 'Premium Occasionwear');
define('SITE_URL',     rtrim((string)(getenv('SITE_URL') ?: (request_scheme() . '://' . request_host())), '/'));
define('CURRENCY',     getenv('CURRENCY')     ?: '₹');
define('APP_DEBUG',    (bool)(getenv('APP_DEBUG') ?: false));
define('CRON_SECRET',  getenv('CRON_SECRET')  ?: '');
define('CUSTOMER_SESSION_KEY', 'customer_id');

// ── Social Media Constants ───────────────────────────────────────────────────
define('SOCIAL_INSTAGRAM', getenv('SOCIAL_INSTAGRAM') ?: '#');
define('SOCIAL_FACEBOOK',  getenv('SOCIAL_FACEBOOK')  ?: '#');
define('SOCIAL_PINTEREST', getenv('SOCIAL_PINTEREST') ?: '#');
define('SOCIAL_WHATSAPP',  getenv('SOCIAL_WHATSAPP')  ?: '#');

// ── Google OAuth Constants ───────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID')     ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');


// ── Global Error / Exception Handler (production) ───────────────────────────
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
    set_exception_handler(function (Throwable $e) {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Internal server error.']);
        } else {
            require __DIR__ . '/../500.php';
        }
        exit;
    });
}

// ── CSRF Token Management ───────────────────────────────────────────────────
/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from request
 */
function csrf_check(): bool
{
    $token = '';

    // 1. Check POST
    if (!empty($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }

    // 2. Check JSON body
    if (!$token) {
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!empty($input['csrf_token'])) {
            $token = $input['csrf_token'];
        }
    }

    // 3. Check headers (robust)
    if (!$token) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (!empty($headers['X-CSRF-TOKEN'])) {
            $token = $headers['X-CSRF-TOKEN'];
        } elseif (!empty($headers['x-csrf-token'])) {
            $token = $headers['x-csrf-token'];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
    }

    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid CSRF token',
        ]);
        exit;
    }

    return true;
}

/**
 * Generate CSRF field HTML for forms
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

// ── Authentication Helpers ──────────────────────────────────────────────────
/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current customer ID (alias for getUserId)
 * FIX #1: Was declared as returning int but called getUserId() which returns
 * ?int — caused a Fatal TypeError when the user is not logged in.
 * Now explicitly casts to int (returns 0 when not logged in).
 */
function getCustomerId(): int
{
    return (int)getUserId();
}

/**
 * Require authentication or redirect to login
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        $intended = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $_SESSION['redirect_after_login'] = $intended;
        header('Location: /login.php?next=' . rawurlencode($intended));
        exit;
    }
}

// ── Input Validation & Sanitization ─────────────────────────────────────────
/**
 * Sanitize user input
 */
function sanitizeInput($input): string
{
    if (is_array($input)) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML (htmlspecialchars wrapper)
 */
function h($text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic: digits and + only)
 */
function validatePhone(string $phone): bool
{
    return preg_match('/^\+?\d{10,15}$/', preg_replace('/\D/', '', $phone)) > 0;
}

// ── Payment Integration ─────────────────────────────────────────────────────
/**
 * Return true when a settings row value represents "enabled".
 * Treats missing (null), empty string, and '0' as disabled.
 */
function settings_is_enabled(?string $value): bool
{
    return $value !== null && $value !== '' && $value !== '0';
}

/**
 * Get payment settings from environment/database
 */
function getPaymentSettings(string $gateway): array
{
    switch (strtolower($gateway)) {
        case 'razorpay':
            $envKeyId  = getenv('RZP_KEY_ID')     ?: '';
            $envSecret = getenv('RZP_KEY_SECRET') ?: '';
            if ($envKeyId !== '') {
                return [
                    'enabled'    => (bool)(getenv('RZP_ENABLED') ?: false),
                    'key_id'     => $envKeyId,
                    'key_secret' => $envSecret,
                ];
            }
            try {
                $stmt = db()->prepare(
                    "SELECT `key`, `value` FROM settings
                     WHERE `key` IN ('razorpay_enabled','razorpay_key_id','razorpay_key_secret')"
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                return [
                    'enabled'    => settings_is_enabled($rows['razorpay_enabled'] ?? null),
                    'key_id'     => $rows['razorpay_key_id']     ?? '',
                    'key_secret' => $rows['razorpay_key_secret'] ?? '',
                ];
            } catch (Throwable) {
                return [];
            }
        case 'cod':
            try {
                $stmt = db()->prepare(
                    "SELECT `key`, `value` FROM settings WHERE `key` IN ('cod_enabled','cod_fee')"
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $enabled = !isset($rows['cod_enabled']) || settings_is_enabled($rows['cod_enabled']);
                return [
                    'enabled' => $enabled,
                    'fee'     => (float)($rows['cod_fee'] ?? 0),
                ];
            } catch (Throwable) {
                return ['enabled' => true, 'fee' => 0];
            }
        case 'delhivery':
            try {
                $stmt = db()->prepare(
                    "SELECT `key`, `value` FROM settings
                     WHERE `key` IN ('delhivery_enabled','delhivery_token','delhivery_warehouse_pin','delhivery_client_name')"
                );
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                return [
                    'enabled'       => settings_is_enabled($rows['delhivery_enabled'] ?? null),
                    'token'         => $rows['delhivery_token']         ?? '',
                    'warehouse_pin' => $rows['delhivery_warehouse_pin'] ?? '',
                    'client_name'   => $rows['delhivery_client_name']   ?? 'Mavdee',
                ];
            } catch (Throwable) {
                return [];
            }
        default:
            return [];
    }
}

// ── Cart Schema Detection ───────────────────────────────────────────────────
/**
 * Get available columns in cart table
 */
function cart_schema_columns(): array
{
    static $columns = null;
    if ($columns === null) {
        try {
            $res     = db()->query('SHOW COLUMNS FROM cart');
            $columns = array_column($res->fetchAll(), 'Field');
        } catch (Throwable) {
            $columns = ['user_id', 'qty', 'product_id', 'size', 'color'];
        }
    }
    return $columns;
}

/**
 * Get available columns in any table (generic schema detection)
 */
function db_columns(string $tableName): array
{
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $res     = db()->query('SHOW COLUMNS FROM ' . preg_replace('/[^a-zA-Z0-9_]/', '', $tableName));
        $columns = array_column($res->fetchAll(), 'Field');
        $cache[$tableName] = $columns;
        return $columns;
    } catch (Throwable $e) {
        error_log("Error getting columns for table $tableName: " . $e->getMessage());
        return [];
    }
}

/**
 * Get site setting from database or return default
 */
function getSetting(string $key, $default = null)
{
    static $settings = [];

    if (empty($settings)) {
        try {
            $stmt = db()->prepare("SELECT `key`, `value` FROM settings");
            $stmt->execute();
            $rows     = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $settings = is_array($rows) ? $rows : [];
        } catch (Throwable) {
            $settings = [];
        }
    }

    if (isset($settings[$key])) {
        $value = $settings[$key];

        // Handle JSON values
        if (is_string($value) && in_array($value[0] ?? '', ['{', '['])) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }
        return $value;
    }

    return $default;
}

/**
 * Update site setting in database
 */
function setSetting(string $key, $value): bool
{
    try {
        $valueStr = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
        $stmt     = db()->prepare("
            INSERT INTO settings (`key`, `value`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        return $stmt->execute([$key, $valueStr]);
    } catch (Throwable $e) {
        error_log("Error setting $key: " . $e->getMessage());
        return false;
    }
}

// ── Image URL Helper ────────────────────────────────────────────────────────
/**
 * Convert image path to public URL
 */
function img_url($imagePath): string
{
    if (empty($imagePath)) {
        return '/assets/img/placeholder.svg';
    }

    if (strpos($imagePath, 'http') === 0) {
        return $imagePath;
    }

    if (strpos($imagePath, 'serve_image.php') !== false || strpos($imagePath, '?') !== false) {
        return (strpos($imagePath, '/') === 0 ? '' : '/') . $imagePath;
    }

    $imagePath = ltrim($imagePath, '/');

    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    if ($docRoot !== '' && !file_exists($docRoot . '/' . $imagePath)) {
        return '/assets/img/placeholder.svg';
    }

    return '/' . $imagePath;
}

/**
 * Get single row from database
 */
function db_row(string $query, array $params = []): ?array
{
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all rows from database
 */
function db_rows(string $query, array $params = []): array
{
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        return [];
    }
}

// ── Logging ─────────────────────────────────────────────────────────────────
/**
 * Log message to file
 */
function log_message(string $level, string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile  = $logDir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry  = "[$timestamp] [$level] $message\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function log_error(string $message): void
{
    log_message('ERROR', $message);
    if (APP_DEBUG) {
        error_log($message);
    }
}

function log_info(string $message): void
{
    log_message('INFO', $message);
}

/**
 * Build an absolute URL using the configured site URL or current host.
 */
function absolute_url(string $path = ''): string
{
    $base = rtrim(SITE_URL, '/');
    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * Normalize an internal path and strip traversal attempts.
 */
function normalize_internal_path(string $url, string $fallback = '/index.php'): string
{
    $fallback = '/' . ltrim($fallback, '/');
    $url      = trim($url);

    if ($url === '') {
        return $fallback;
    }

    $parsed = parse_url($url);
    if ($parsed === false) {
        return $fallback;
    }

    if (isset($parsed['scheme']) || isset($parsed['host']) || str_starts_with($url, '//')) {
        return $fallback;
    }

    $path     = str_replace('\\', '/', (string)($parsed['path'] ?? ''));
    $segments = [];

    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    $cleanPath = '/' . implode('/', $segments);
    if ($cleanPath === '/') {
        $cleanPath = $fallback;
    }

    if (!empty($parsed['query'])) {
        $cleanPath .= '?' . $parsed['query'];
    }

    if (!empty($parsed['fragment'])) {
        $cleanPath .= '#' . $parsed['fragment'];
    }

    return $cleanPath;
}

// ── Safe Redirect Helper ─────────────────────────────────────────────────────
/**
 * Safely redirect to URL or fallback. Prevents open redirect attacks.
 */
function safe_redirect(string $url, string $fallback = 'index.php'): string
{
    $normalizedFallback = normalize_internal_path($fallback, '/index.php');
    $url = trim($url);

    if ($url === '') {
        return $normalizedFallback;
    }

    $parsed = parse_url($url);
    if ($parsed === false) {
        return $normalizedFallback;
    }

    if (!isset($parsed['scheme']) && !isset($parsed['host']) && !str_starts_with($url, '//')) {
        return normalize_internal_path($url, $normalizedFallback);
    }

    $siteParsed     = parse_url(SITE_URL) ?: [];
    $allowedHosts   = array_values(array_unique(array_filter([
        strtolower((string)($siteParsed['host'] ?? '')),
        request_host(),
    ])));
    $allowedSchemes = array_values(array_unique(array_filter([
        strtolower((string)($siteParsed['scheme'] ?? '')),
        request_scheme(),
    ])));

    $targetHost   = strtolower((string)($parsed['host']   ?? ''));
    $targetScheme = strtolower((string)($parsed['scheme'] ?? ''));

    if (
        $targetHost !== '' &&
        in_array($targetHost, $allowedHosts, true) &&
        ($targetScheme === '' || in_array($targetScheme, $allowedSchemes, true))
    ) {
        return $url;
    }

    return $normalizedFallback;
}

/**
 * Get the intended post-auth redirect target.
 */
function intended_redirect(string $fallback = 'index.php'): string
{
    $candidate = trim((string)($_GET['next'] ?? ''));

    if ($candidate === '' && !empty($_SESSION['redirect_after_login'])) {
        $candidate = (string)$_SESSION['redirect_after_login'];
    }

    return safe_redirect($candidate, $fallback);
}

/**
 * Clear any saved post-auth redirect state.
 */
function clear_intended_redirect(): void
{
    unset($_SESSION['redirect_after_login'], $_SESSION['oauth_redirect_after_login']);
}

// ── Load language strings ────────────────────────────────────────────────────
$_activeLang = $_SESSION['lang'] ?? 'en';
$_langFile   = __DIR__ . '/../includes/lang/' . $_activeLang . '.php';
if (!file_exists($_langFile)) {
    $_langFile = __DIR__ . '/../includes/lang/en.php';
}
$LANG = require $_langFile;

// ── Database Load ───────────────────────────────────────────────────────────
require_once __DIR__ . '/database.php';

// ── Timezone Setting ───────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');