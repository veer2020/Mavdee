<?php
/**
 * api/qa/get.php
 * Returns approved Q&A for a product.
 *
 * GET: product_id=int
 * Response: { qa: [ { id, question, answer, answered_at, created_at } ] }
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

header('Content-Type: application/json');
header('Cache-Control: max-age=60');

$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'product_id required']);
    exit;
}

try {
    $stmt = db()->prepare(
        "SELECT id, question, answer, answered_at, created_at
         FROM product_qa
         WHERE product_id = ? AND is_public = 1
         ORDER BY created_at DESC
         LIMIT 30"
    );
    $stmt->execute([$productId]);
    $qa = $stmt->fetchAll();
    echo json_encode(['qa' => $qa]);
} catch (Throwable $e) {
    echo json_encode(['qa' => []]);
}
