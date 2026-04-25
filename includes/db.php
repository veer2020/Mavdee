<?php
/**
 * ============================================================
 *  includes/db.php  –  Compatibility shim for Ecom
 * ============================================================
 *
 *  This file delegates to the canonical database layer at
 *  config/database.php. It exists for backwards compatibility
 *  so that existing code using `require_once 'includes/db.php'`
 *  continues to work without changes.
 *
 *  Prefer requiring config/database.php directly in new code.
 * ============================================================
 */

declare(strict_types=1);

// Load the canonical database layer (defines db(), db_select(), etc.)
require_once dirname(__DIR__) . '/config/database.php';

// ─── Include all other core files ────────────────────────────────────────────
// config.php   – site constants, session start, CSRF, helper functions
require_once dirname(__DIR__) . '/config/config.php';

// email.php    – email sending helpers (PHPMailer wrapper)
require_once __DIR__ . '/email.php';
