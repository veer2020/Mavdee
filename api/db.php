<?php
// Compatibility shim - use config/database.php as the single source of truth
if (!defined('DB_INITIALIZED')) {
    define('DB_INITIALIZED', true);
    require_once dirname(__DIR__) . '/config/database.php';
}