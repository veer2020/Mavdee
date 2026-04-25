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
        $qty = (int)($_POST['qty'] ?? 0);
        $change = isset($_POST['change']) ? (int)$_POST['change'] : null;
        $size = trim($_POST['size'] ?? '');
        $color = trim($_POST['color'] ?? '');

        // JSON body support
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode(file_get_contents('php://input'), true) ?: [];
            $productId = (int)($json['product_id'] ?? $productId);
            $size = trim($json['size'] ?? $size);
            $color = trim($json['color'] ?? $color);
            if (isset($json['change'])) $change = (int)$json['change'];
            else $qty = (int)($json['qty'] ?? $json['quantity'] ?? $qty);
        }

        if ($productId > 0) {
            foreach ($_SESSION['guest_cart'] as $key => &$item) {
                if ((int)$item['product_id'] === $productId && ($item['size'] ?? '') === $size && ($item['color'] ?? '') === $color) {
                    if ($change !== null) {
                        $item['qty'] = $item['qty'] + $change;
                    } else {
                        $item['qty'] = $qty;
                    }
                    if ($item['qty'] <= 0) unset($_SESSION['guest_cart'][$key]);
                    break;
                }
            }
            // Re-index array so JSON encodes properly as an array, not an object
            $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
            echo json_encode(['success' => true]);
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
$qty       = (int)($_POST['qty'] ?? 0);
$size      = trim($_POST['size'] ?? '');
$color     = trim($_POST['color'] ?? '');
$change    = isset($_POST['change']) ? (int)$_POST['change'] : null;

// Also accept JSON body (Content-Type: application/json)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $json      = json_decode(file_get_contents('php://input'), true) ?: [];
    $productId = (int)($json['product_id'] ?? $productId);
    $size      = trim($json['size'] ?? $size);
    $color     = trim($json['color'] ?? $color);
    if (isset($json['change'])) {
        // Delta-based: quantity = quantity + change
        $change = (int)$json['change'];
    } else {
        $qty = (int)($json['qty'] ?? $json['quantity'] ?? $qty);
    }
}

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product.']);
    exit;
}

try {
    $cols    = cart_schema_columns();
    $userCol = in_array('customer_id', $cols) ? 'customer_id' : 'user_id';
    // Whitelist the column name to prevent any SQL injection via schema introspection
    $qtyCol  = in_array('qty', $cols) ? 'qty' : 'quantity';
    $qtyCol  = in_array($qtyCol, ['qty', 'quantity'], true) ? $qtyCol : 'qty';

    $hasSizeColor = in_array('size', $cols) && in_array('color', $cols);
    $sizeColorCond = $hasSizeColor ? " AND size = ? AND color = ?" : "";

    // Get the database connection and User ID BEFORE building the query parameters
    $pdo    = db();
    $userId = getUserId();

    $params = [$userId, $productId];
    if ($hasSizeColor) {
        $params[] = $size;
        $params[] = $color;
    }

    if ($change !== null) {
        // Delta-based update: wrap in a transaction so the UPDATE + conditional DELETE are atomic
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE cart SET $qtyCol = $qtyCol + ? WHERE $userCol = ? AND product_id = ?" . $sizeColorCond);
            $upd->execute(array_merge([$change], $params));
            // Remove row if quantity drops to 0 or below
            $del = $pdo->prepare("DELETE FROM cart WHERE $userCol = ? AND product_id = ?" . $sizeColorCond . " AND $qtyCol <= 0");
            $del->execute($params);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($qty <= 0) {
        $del = $pdo->prepare("DELETE FROM cart WHERE $userCol = ? AND product_id = ?" . $sizeColorCond);
        $del->execute($params);
    } else {
        $upd = $pdo->prepare("UPDATE cart SET $qtyCol = ? WHERE $userCol = ? AND product_id = ?" . $sizeColorCond);
        $upd->execute(array_merge([$qty], $params));
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update cart.']);
}
