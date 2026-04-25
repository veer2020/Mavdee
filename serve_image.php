<?php
$file = basename($_GET['file'] ?? '');
$dir  = $_GET['dir'] ?? '';
$allowedDirs = ['products', 'categories', 'reviews', 'returns'];

if (!in_array($dir, $allowedDirs) || !$file) {
    http_response_code(403);
    die('Forbidden');
}

$path = __DIR__ . '/../private/uploads/' . $dir . '/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    die('Not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
