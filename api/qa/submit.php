<?php
/**
 * api/qa/submit.php
 * Submit a product question. Requires login.
 *
 * POST JSON: { product_id, question, csrf_token }
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to ask a question.']);
    exit;
}

$raw  = (string)file_get_contents('php://input');
$body = json_decode($raw, true);

// Also accept form POST
$productId = (int)(($body['product_id'] ?? null) ?: ($_POST['product_id'] ?? 0));
$question  = trim((string)(($body['question'] ?? null) ?: ($_POST['question'] ?? '')));
$csrfIn    = (string)(($body['csrf_token'] ?? null) ?: ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

if (!hash_equals(csrf_token(), $csrfIn)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

if ($question === '' || mb_strlen($question) < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Question must be at least 10 characters.']);
    exit;
}

if (mb_strlen($question) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Question must not exceed 500 characters.']);
    exit;
}

try {
    db()->prepare(
        "INSERT INTO product_qa (product_id, customer_id, question) VALUES (?, ?, ?)"
    )->execute([$productId, getUserId(), strip_tags($question)]);

    echo json_encode(['ok' => true, 'message' => 'Your question has been submitted!']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error. Please try again.']);
}
