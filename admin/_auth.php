<?php
// Ensure session is active — config/config.php normally starts it, but guard
// defensively so this file is safe when included in isolation.
if (session_status() === PHP_SESSION_NONE) {
    $httpsOn = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '0';
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    $forwardedSsl = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''))[0] ?? ''));
    $secure = $httpsOn || $forwardedProto === 'https' || $forwardedSsl === 'on' || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('ADMIN_SESSION_KEY', 'admin_id');

function adminIsLoggedIn(): bool
{
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function requireAdminLogin(): void
{
    if (!adminIsLoggedIn()) {
        // Determine if we're in a subdirectory of admin/ (e.g. admin/orders/)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $inSubDir  = str_ends_with($scriptDir, '/admin') ? false : str_contains($scriptDir, '/admin/');
        $loginPath = $inSubDir ? '../login.php' : 'login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

function getAdminId(): int
{
    return (int)($_SESSION[ADMIN_SESSION_KEY] ?? 0);
}

function getAdminName(): string
{
    return $_SESSION['admin_name'] ?? 'Admin';
}

function getAdminRole(): string
{
    return $_SESSION['admin_role'] ?? 'admin';
}

function logAdminActivity(string $action, string $detail = ''): void
{
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = db()->prepare(
            "INSERT INTO activity_log (admin_id, action, detail, ip, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([getAdminId(), $action, $detail, $ip]);
    } catch (Throwable $e) {
        // Silently fail — logging must never break the request
    }
}

function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}