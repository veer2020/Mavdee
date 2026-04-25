<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required.', 'require_login' => true]);
    exit;
}

csrf_check();

try {
    $productId = (int)($_POST['product_id'] ?? 0);

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        if (isset($input['product_id'])) {
            $productId = (int)$input['product_id'];
        }
    }

    if ($productId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid product_id is required.']);
        exit;
    }

    $userId = getUserId();
    $pdo = db();

    try {
        // Check if already in wishlist using customer_id
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE customer_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();
        $userCol = 'customer_id';
    } catch (Throwable $e) {
        // Fallback to user_id
        try {
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$userId, $productId]);
            $existing = $stmt->fetch();
            $userCol = 'user_id';
        } catch (Throwable $e2) {
            // Table likely doesn't exist, create it
            $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NULL,
                user_id INT NULL,
                product_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $userCol = 'customer_id';
            $existing = false;
        }
    }

    if ($existing) {
        $pdo->prepare("DELETE FROM wishlist WHERE id = ?")->execute([$existing['id']]);
        $action = 'removed';
    } else {
        $pdo->prepare("INSERT INTO wishlist ($userCol, product_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$userId, $productId]);
        $action = 'added';
    }

    // Get updated count using the resolved column
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM wishlist WHERE $userCol = ?");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'action' => $action, 'count' => $count]);
} catch (Exception $e) {
    error_log("Wishlist Toggle Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
