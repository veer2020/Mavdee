<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_auth.php';
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Validate CSRF token passed from FormData inside image-upload.js
$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrf_token(), $csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['file'];
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMime, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WEBP, GIF.']);
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$dest = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => true, 'url' => '/uploads/banners/' . $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save the uploaded file.']);
}
