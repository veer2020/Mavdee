<?php
/**
 * api/notifications/get.php
 * Returns the latest notifications for the logged-in user.
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['notifications' => []]);
    exit;
}

$userId = getUserId();

try {
    $stmt = db()->prepare(
        "SELECT id, type, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 20"
    );
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
} catch (Throwable) {
    $notifications = [];
}

echo json_encode(['notifications' => $notifications]);
