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

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid address ID.']);
    exit;
}

try {
    $stmt = db()->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, getUserId()]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not delete address.']);
}
