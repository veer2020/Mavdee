<?php
/**
 * api/set_preference.php
 * Sets language and/or currency preference in session and redirects back.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

$allowed_langs     = ['en', 'hi'];
$allowed_currencies = ['INR', 'USD', 'EUR'];

if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
}

if (isset($_GET['currency']) && in_array($_GET['currency'], $allowed_currencies, true)) {
    $_SESSION['currency'] = $_GET['currency'];
}

$redirect = $_SERVER['HTTP_REFERER'] ?? '/';
// Safety: only redirect to same origin
$parsed = parse_url($redirect);
if (!empty($parsed['host']) && $parsed['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
    $redirect = '/';
}

header('Location: ' . $redirect);
exit;
