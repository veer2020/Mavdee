<?php

/**
 * Security Headers Configuration
 * Include this file at TOP of index.php before ANY output
 * 
 * Usage:
 * Add this line at the very top of index.php:
 * require_once 'includes/security_headers.php';
 */

// ========================================
// HTTPS & FRAME SECURITY (Fix Console Error)
// ========================================

// Allow iframes from same domain only (FIXES THE ERROR)
header('X-Frame-Options: SAMEORIGIN');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection in older browsers
header('X-XSS-Protection: 1; mode=block');

// ========================================
// CONTENT SECURITY POLICY
// ========================================
// Allows iframes for Razorpay, bootstrap CDN, and same-origin
$csp = "
    default-src 'self';
    script-src 'self' 'unsafe-inline' 'unsafe-eval' 
        https://checkout.razorpay.com 
        https://cdn.jsdelivr.net 
        https://code.jquery.com
        https://maxcdn.bootstrapcdn.com;
    style-src 'self' 'unsafe-inline' 
        https://cdn.jsdelivr.net 
        https://maxcdn.bootstrapcdn.com 
        https://fonts.googleapis.com;
    img-src 'self' data: https:;
    font-src 'self' data: 
        https://fonts.gstatic.com
        https://maxcdn.bootstrapcdn.com;
    connect-src 'self' 
        https://api.razorpay.com 
        https://checkout.razorpay.com;
    frame-src 'self' https://checkout.razorpay.com;
    object-src 'none';
";
header('Content-Security-Policy: ' . str_replace(array("\r", "\n", "    "), '', $csp));

// ========================================
// ADDITIONAL SECURITY HEADERS
// ========================================

// Referrer Policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Force HTTPS (Strict Transport Security)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Hide powered-by information
header_remove('X-Powered-By');
header_remove('X-AspNet-Version');

// Prevent embedding in external sites
header('Cross-Origin-Embedder-Policy: require-corp');

// ========================================
// ERROR SUPPRESSION
// ========================================

// Don't expose PHP errors to browser
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Log errors to file instead
ini_set('log_errors', 1);
ini_set('error_log', realpath(__DIR__ . '/../logs/php_errors.log'));

// ========================================
// SESSION SECURITY
// ========================================

// Set secure session options
session_set_cookie_params([
    'lifetime' => 86400,           // 24 hours
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,              // HTTPS only
    'httponly' => true,            // No JavaScript access
    'samesite' => 'Lax'            // CSRF protection
]);

// ========================================
// END SECURITY HEADERS
// ========================================

/**
 * Testing:
 * 1. Open DevTools (F12)
 * 2. Go to Network tab
 * 3. Reload page
 * 4. Click on main document request
 * 5. Look for Response Headers section
 * 6. Verify these headers are present:
 *    - X-Frame-Options: SAMEORIGIN
 *    - Content-Security-Policy: ...
 *    - X-Content-Type-Options: nosniff
 *    - Strict-Transport-Security: ...
 */
