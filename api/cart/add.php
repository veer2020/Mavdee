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

$productId = (int)($_POST['product_id'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? $_POST['quantity'] ?? 1));
$size      = trim($_POST['size'] ?? '');
$color     = trim($_POST['color'] ?? '');

// Also accept JSON body (Content-Type: application/json)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json      = json_decode(file_get_contents('php://input'), true) ?: [];
    $productId = (int)($json['product_id'] ?? $productId);
    $qty       = max(1, (int)($json['quantity'] ?? $json['qty'] ?? $qty));
    $size      = trim($json['size'] ?? $size);
    $color     = trim($json['color'] ?? $color);
}

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product.']);
    exit;
}

try {
    $cols    = cart_schema_columns();
    $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
    $qtyCol  = in_array('qty', $cols) ? 'qty' : 'quantity';

    // Detect optional cart columns
    $hasSizeColor = in_array('size', $cols) && in_array('color', $cols);

    $pdo = db();

    // Verify product exists; check is_active only if that column exists
    $productCols = db_columns('products');
    $hasIsActive = in_array('is_active', $productCols);
    if ($hasIsActive) {
        $check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    } else {
        $check = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
    }
    $check->execute([$productId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found.']);
        exit;
    }

    $userId = getUserId();

    // INSERT or update quantity on duplicate
    if ($hasSizeColor) {
        $sql = "INSERT INTO cart ($userCol, product_id, $qtyCol, size, color)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE $qtyCol = $qtyCol + VALUES($qtyCol)";
        $pdo->prepare($sql)->execute([$userId, $productId, $qty, $size, $color]);
    } else {
        $sql = "INSERT INTO cart ($userCol, product_id, $qtyCol)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE $qtyCol = $qtyCol + VALUES($qtyCol)";
        $pdo->prepare($sql)->execute([$userId, $productId, $qty]);
    }

    // Return updated count
    $countStmt = $pdo->prepare("SELECT COALESCE(SUM($qtyCol), 0) FROM cart WHERE $userCol = ?");
    $countStmt->execute([$userId]);
    $count = (int)$countStmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not add to cart.']);
}
