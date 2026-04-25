<?php
/**
 * api/qa/answer.php
 * Admin endpoint: post an answer to a product question.
 *
 * POST: { qa_id, answer, csrf_token }
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';
require_once dirname(__DIR__, 2) . '/admin/_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireAdminLogin();
csrf_check();

$qaId   = (int)($_POST['qa_id'] ?? 0);
$answer = trim(strip_tags($_POST['answer'] ?? ''));

if ($qaId <= 0 || $answer === '') {
    http_response_code(400);
    echo json_encode(['error' => 'qa_id and answer required']);
    exit;
}

try {
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    db()->prepare(
        "UPDATE product_qa SET answer = ?, answered_by = ?, answered_at = NOW() WHERE id = ?"
    )->execute([$answer, $adminId ?: null, $qaId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
