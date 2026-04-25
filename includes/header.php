<?php
// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) == 'header.php') {
    http_response_code(403);
    die('Direct access forbidden');
}
/**
 * includes/header.php
 * Shared site navigation — delegates to navbar.php.
 * Included inside <body> on every frontend page.
 */
require_once __DIR__ . '/navbar.php';
