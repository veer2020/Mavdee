<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_auth.php';

if (adminIsLoggedIn()) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        db()->prepare("INSERT INTO activity_log (admin_id, action, detail, ip, created_at) VALUES (?, 'logout', 'Admin logged out', ?, NOW())")
           ->execute([getAdminId(), $ip]);
    } catch (Throwable) {}
}

session_destroy();
header('Location: login.php');
exit;
