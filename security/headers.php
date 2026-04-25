<?php

/**
 * security/headers.php
 * Security HTTP headers — include at the top of every public-facing page.
 * (CSP is now set in config.php with nonce support.)
 */

declare(strict_types=1);

// Prevent MIME-type sniffing
header('X-Content-Type-Options: nosniff');

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Legacy XSS filter (belt-and-suspenders)
header('X-XSS-Protection: 1; mode=block');

// Control referrer information
header('Referrer-Policy: strict-origin-when-cross-origin');

// Disable browser features not needed by the store
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// HTTP Strict Transport Security — tell browsers to always use HTTPS
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
