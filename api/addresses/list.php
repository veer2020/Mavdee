<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['addresses' => []]);
    exit;
}

try {
    $stmt = db()->prepare(
        "SELECT id, label, name, phone, address, city, state, pincode, is_default
         FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC"
    );
    $stmt->execute([getUserId()]);
    echo json_encode(['addresses' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    echo json_encode(['addresses' => []]);
}
