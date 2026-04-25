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
    if (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        $color = trim($_POST['color'] ?? '');

        // JSON body support
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true) ?: [];
            $productId = (int)($json['product_id'] ?? $productId);
            $size = trim($json['size'] ?? $size);
            $color = trim($json['color'] ?? $color);
        }

        if ($productId > 0) {
            foreach ($_SESSION['guest_cart'] as $key => $item) {
                if ((int)$item['product_id'] === $productId && $item['size'] === $size && $item['color'] === $color) {
                    unset($_SESSION['guest_cart'][$key]);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
            echo json_encode(['success' => true]); // Not found, treat as success
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product.']);
        }
        exit;
    }
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$size      = trim($_POST['size'] ?? '');
$color     = trim($_POST['color'] ?? '');

// Also accept JSON body (Content-Type: application/json)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json      = json_decode(file_get_contents('php://input'), true) ?: [];
    $productId = (int)($json['product_id'] ?? $productId);
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

    $del = db()->prepare("DELETE FROM cart WHERE $userCol = ? AND product_id = ? AND size = ? AND color = ?");
    $del->execute([getUserId(), $productId, $size, $color]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not remove item.']);
}
