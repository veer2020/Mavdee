<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_check();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required.']);
    exit;
}

$userId  = getUserId();
$id      = (int)($_POST['id'] ?? 0);
$label   = trim($_POST['label'] ?? 'Home');
$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city    = trim($_POST['city'] ?? '');
$state   = trim($_POST['state'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');
$isDefault = !empty($_POST['is_default']) ? 1 : 0;

if (!$name || !$address || !$city || !$pincode) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name, address, city and pincode are required.']);
    exit;
}

try {
    $pdo = db();

    if ($isDefault) {
        $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")
            ->execute([$userId]);
    }

    if ($id > 0) {
        // Update existing – ensure it belongs to the current user
        $stmt = $pdo->prepare(
            "UPDATE customer_addresses SET label=?, name=?, phone=?, address=?, city=?, state=?, pincode=?, is_default=?
             WHERE id=? AND customer_id=?"
        );
        $stmt->execute([$label, $name, $phone, $address, $city, $state, $pincode, $isDefault, $id, $userId]);
        echo json_encode(['success' => true, 'message' => 'Address updated.', 'id' => $id]);
    } else {
        // Insert new address
        $stmt = $pdo->prepare(
            "INSERT INTO customer_addresses (customer_id, label, name, phone, address, city, state, pincode, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $label, $name, $phone, $address, $city, $state, $pincode, $isDefault]);
        echo json_encode(['success' => true, 'message' => 'Address saved.', 'id' => (int)$pdo->lastInsertId()]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save address.']);
}
