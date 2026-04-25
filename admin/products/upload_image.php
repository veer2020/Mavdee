<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../api/db.php';
require_once __DIR__ . '/../_auth.php';

requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

csrf_check();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload failed.']);
    exit;
}

$file = $_FILES['file'];
$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    echo json_encode(['success' => false, 'error' => 'File exceeds 5 MB limit.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png'];
if (!in_array($mimeType, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'Only JPG and PNG allowed.']);
    exit;
}

// Additional image validation
if (!getimagesize($file['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid image file.']);
    exit;
}

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$ext = $extMap[$mimeType];
$filename = uniqid('prod_', true) . '.' . $ext;

// Store outside web root
$uploadDir = dirname(__DIR__, 3) . '/private/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
    exit;
}

// Return URL to proxy script
echo json_encode(['success' => true, 'url' => '/serve_image.php?dir=products&file=' . urlencode($filename)]);
