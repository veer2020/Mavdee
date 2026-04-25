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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
        exit;
    }

    $productId = (int)($input['product_id'] ?? 0);
    $rating    = (int)($input['rating'] ?? 0);
    $title     = trim($input['title'] ?? '');
    $body      = trim($input['body'] ?? '');

    if ($productId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid product_id is required.']);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5.']);
        exit;
    }

    if ($body === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Review body is required.']);
        exit;
    }

    $pdo = db();
    $userId = getUserId();

    // Get customer details
    $stmt = $pdo->prepare("SELECT name, email FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Customer not found.']);
        exit;
    }

    // Verify product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO product_reviews
        (product_id, customer_id, name, email, rating, title, body, photo_urls, is_approved, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");

    $photoUrls = null;
    if (!empty($input['photo_urls']) && is_array($input['photo_urls'])) {
        $uploadsBase = realpath(__DIR__ . '/../../uploads/reviews');
        $safeUrls = array_filter($input['photo_urls'], function($u) use ($uploadsBase) {
            if (!is_string($u)) return false;
            // Must start with the allowed web prefix
            if (!str_starts_with($u, '/uploads/reviews/')) return false;
            // Reject any directory traversal in the path
            if (strpos($u, '..') !== false) return false;
            // Only allow safe filename characters
            $filename = basename($u);
            if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp)$/i', $filename)) return false;
            // Verify the file actually exists in the expected directory (if realpath is available)
            if ($uploadsBase !== false) {
                $filePath = realpath($uploadsBase . DIRECTORY_SEPARATOR . $filename);
                if ($filePath === false || !str_starts_with($filePath, $uploadsBase)) return false;
            }
            return true;
        });
        $safeUrls  = array_slice(array_values($safeUrls), 0, 3);
        $photoUrls = !empty($safeUrls) ? json_encode($safeUrls) : null;
    }

    $stmt->execute([$productId, $userId, $customer['name'], $customer['email'], $rating, $title, $body, $photoUrls]);

    echo json_encode(['success' => true, 'message' => 'Review submitted for approval.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
