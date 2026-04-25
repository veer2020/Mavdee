<?php

/**
 * api/reviews/upload_photo.php
 * Upload up to 3 review photos. Returns the stored URLs.
 * Accepts multipart/form-data with field 'photos[]'.
 */

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/../private/uploads/reviews/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
$maxSize     = 5 * 1024 * 1024; // 5 MB
$uploaded    = [];
$errors      = [];

$files = $_FILES['photos'] ?? [];
if (empty($files['tmp_name'])) {
    echo json_encode(['urls' => []]);
    exit;
}

// Normalize single vs multi file upload
if (!is_array($files['tmp_name'])) {
    $files = array_map(fn($v) => [$v], $files);
}

$count = min(3, count($files['tmp_name']));
for ($i = 0; $i < $count; $i++) {
    $tmpName = $files['tmp_name'][$i];
    $size    = (int)$files['size'][$i];

    if (empty($tmpName) || !is_uploaded_file($tmpName)) {
        continue;
    }
    if ($size > $maxSize) {
        $errors[] = "File " . ($i + 1) . " exceeds 5 MB limit.";
        continue;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpName);

    if (!in_array($mime, $allowedMime, true)) {
        $errors[] = "File " . ($i + 1) . " is not a valid image (JPG, PNG, or WebP).";
        continue;
    }

    // Additional image validation
    if (!getimagesize($tmpName)) {
        $errors[] = "File " . ($i + 1) . " is not a valid image.";
        continue;
    }

    $ext      = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = 'review_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (move_uploaded_file($tmpName, $dest)) {
        $uploaded[] = '/serve_image.php?dir=reviews&file=' . urlencode($filename);
    } else {
        $errors[] = "Could not save file " . ($i + 1) . ".";
    }
}

echo json_encode(['urls' => $uploaded, 'errors' => $errors]);
