<?php
/**
 * api/profile/update.php
 * Updates the logged-in customer's name and phone number.
 *
 * POST  /api/profile/update.php
 * Body  (form-encoded): name, phone, csrf_token
 */

declare(strict_types=1);

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

$userId = getUserId();
$name   = sanitizeInput($_POST['name'] ?? '');
$phone  = sanitizeInput($_POST['phone'] ?? '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required.']);
    exit;
}

if (mb_strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Name must not exceed 100 characters.']);
    exit;
}

if ($phone !== '' && (
    !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone) ||
    preg_match_all('/[0-9]/', $phone, $digits) < 6 ||
    count($digits[0]) < 6
)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid phone number (6–20 digits).']);
    exit;
}

try {
    db_update(
        'customers',
        ['name' => $name, 'phone' => $phone !== '' ? $phone : null],
        ['id'   => $userId]
    );
    echo json_encode(['success' => true, 'name' => $name, 'phone' => $phone]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update profile. Please try again.']);
}
