<?php

/**
 * api/auth/status.php
 * Returns current authentication status
 * Used by JavaScript to determine if user is logged in
 */
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$status = [
    'logged_in' => false,
    'user_id' => 0,
    'is_admin' => false,
    'username' => null,
    'email' => null,
];

// Check if customer is logged in
if (isLoggedIn()) {
    $status['logged_in'] = true;
    $status['user_id'] = getUserId();
    $status['username'] = $_SESSION['customer_name'] ?? $_SESSION['user_name'] ?? null;
    $status['email'] = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? null;
}

// Check if admin is logged in
if (!empty($_SESSION['admin_id'])) {
    $status['is_admin'] = true;
    $status['user_id'] = $_SESSION['admin_id'];
    $status['username'] = $_SESSION['admin_name'] ?? null;
}

echo json_encode($status);