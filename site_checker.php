<?php
/**
 * Site Health Checker - Tests all critical pages
 * Run this file to see which pages are working and which are broken
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Site Health Checker - Mavdee</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:hover { background: #f9f9f9; }
        .summary { background: #e8f5e9; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
<h1>🔍 Mavdee - Site Health Checker</h1>";

// List of all critical pages to test
$pages = [
    // Core pages
    ['name' => 'Homepage', 'url' => '/index.php'],
    ['name' => 'Shop Page', 'url' => '/shop.php'],
    ['name' => 'Product Page (sample)', 'url' => '/product.php?id=1'],
    ['name' => 'Cart Page', 'url' => '/cart.php'],
    ['name' => 'Checkout Page', 'url' => '/checkout.php'],
    ['name' => 'Login Page', 'url' => '/login.php'],
    ['name' => 'Register Page', 'url' => '/register.php'],
    ['name' => 'Forgot Password', 'url' => '/forgot_password.php'],
    ['name' => 'Reset Password', 'url' => '/reset_password.php'],
    ['name' => 'My Orders', 'url' => '/my-orders.php'],
    ['name' => 'Order Details', 'url' => '/order-details.php?id=1'],
    ['name' => 'Dashboard', 'url' => '/dashboard.php'],
    ['name' => 'Wishlist', 'url' => '/wishlist.php'],
    ['name' => 'Thank You Page', 'url' => '/thankyou.php'],
    ['name' => 'Search Page', 'url' => '/search.php?q=test'],
    ['name' => 'Contact Page', 'url' => '/contact.php'],
    ['name' => 'About Page', 'url' => '/about.php'],
    ['name' => 'Privacy Policy', 'url' => '/privacy.php'],
    ['name' => 'Returns Policy', 'url' => '/returns.php'],
    ['name' => 'Shipping Info', 'url' => '/shipping.php'],
    ['name' => 'Health Check', 'url' => '/health.php'],
    ['name' => 'Offline Page', 'url' => '/offline.php'],
    
    // API endpoints
    ['name' => 'API - Get Products', 'url' => '/api/products/get_products.php'],
    ['name' => 'API - Get Cart', 'url' => '/api/cart/get.php'],
    ['name' => 'API - Search', 'url' => '/api/search.php?q=test'],
    
    // Admin pages (if accessible)
    ['name' => 'Admin Login', 'url' => '/admin/login.php'],
    ['name' => 'Admin Dashboard', 'url' => '/admin/index.php'],
    ['name' => 'Admin Products', 'url' => '/admin/products/index.php'],
    ['name' => 'Admin Orders', 'url' => '/admin/orders/index.php'],
    ['name' => 'Admin Customers', 'url' => '/admin/customers/index.php'],
    ['name' => 'Admin Categories', 'url' => '/admin/categories/index.php'],
    ['name' => 'Admin Coupons', 'url' => '/admin/coupons/index.php'],
    ['name' => 'Admin Reviews', 'url' => '/admin/reviews/index.php'],
    ['name' => 'Admin Returns', 'url' => '/admin/returns.php'],
    ['name' => 'Admin Q&A', 'url' => '/admin/qa.php'],
    ['name' => 'Admin Analytics', 'url' => '/admin/analytics.php'],
    ['name' => 'Admin Settings', 'url' => '/admin/settings/index.php'],
    ['name' => 'Admin Activity Log', 'url' => '/admin/activity_log/index.php'],
    
    // Include files (should not be accessed directly - expected to fail or redirect)
    ['name' => 'Header Include', 'url' => '/includes/header.php', 'expected_fail' => true],
    ['name' => 'Footer Include', 'url' => '/includes/footer.php', 'expected_fail' => true],
    ['name' => 'Config File', 'url' => '/config/config.php', 'expected_fail' => true],
];

echo "<div class='summary'>";
echo "<strong>📊 Testing " . count($pages) . " pages...</strong><br>";
echo "🟢 Green = Working | 🔴 Red = Broken | 🟡 Orange = Needs Attention<br>";
echo "⏱️ " . date('Y-m-d H:i:s');
echo "</div>";

echo "<table>";
echo "<tr><th>#</th><th>Page Name</th><th>URL</th><th>Status</th><th>HTTP Code</th><th>Notes</th></tr>";

$pass = 0;
$fail = 0;
$warning = 0;

foreach ($pages as $i => $page) {
    $url = 'https://ai360news.com' . $page['url'];
    $expected_fail = $page['expected_fail'] ?? false;
    
    // Use cURL to check the page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);  // HEAD request only (faster)
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $status = '';
    $status_class = '';
    $notes = '';
    
    if ($expected_fail) {
        // These files should NOT be accessible directly
        if ($http_code >= 400 || $http_code === 0) {
            $status = 'PASS (Blocked)';
            $status_class = 'pass';
            $pass++;
            $notes = 'Direct access correctly blocked';
        } else {
            $status = 'WARNING (Should be blocked)';
            $status_class = 'warning';
            $warning++;
            $notes = 'File is accessible directly - security risk!';
        }
    } else {
        // Normal pages should return 200
        if ($http_code === 200) {
            $status = 'PASS';
            $status_class = 'pass';
            $pass++;
            $notes = 'Page loads correctly';
        } elseif ($http_code === 301 || $http_code === 302) {
            $status = 'REDIRECT';
            $status_class = 'warning';
            $warning++;
            $notes = 'Page redirects';
        } elseif ($http_code === 401 || $http_code === 403) {
            $status = 'AUTH REQUIRED';
            $status_class = 'warning';
            $warning++;
            $notes = 'Login required (may be normal)';
        } elseif ($http_code >= 400 && $http_code < 500) {
            $status = 'FAIL (Client Error)';
            $status_class = 'fail';
            $fail++;
            $notes = "HTTP $http_code - Check this page";
        } elseif ($http_code >= 500) {
            $status = 'FAIL (Server Error)';
            $status_class = 'fail';
            $fail++;
            $notes = "HTTP $http_code - PHP error on this page";
        } elseif ($http_code === 0) {
            $status = 'FAIL (No Response)';
            $status_class = 'fail';
            $fail++;
            $notes = $error ?: 'Could not connect';
        } else {
            $status = "UNKNOWN ($http_code)";
            $status_class = 'warning';
            $warning++;
        }
    }
    
    $status_display = $status_class === 'pass' ? "✅ $status" : ($status_class === 'fail' ? "❌ $status" : "⚠️ $status");
    
    echo "<tr>
            <td>" . ($i+1) . "</td>
            <td>" . htmlspecialchars($page['name']) . "</td>
            <td><a href='" . htmlspecialchars($url) . "' target='_blank'>" . htmlspecialchars($page['url']) . "</a></td>
            <td class='$status_class'>$status_display</td>
            <td>" . ($http_code ?: 'N/A') . "</td>
            <td>" . htmlspecialchars($notes) . "</td>
          </tr>";
}

echo "</table>";

// Summary
echo "<div class='summary'>";
echo "<h2>📈 Summary</h2>";
echo "<p>✅ Passed: <strong>$pass</strong></p>";
echo "<p>❌ Failed: <strong>$fail</strong></p>";
echo "<p>⚠️ Warnings: <strong>$warning</strong></p>";
echo "<p>📊 Total Pages Checked: <strong>" . count($pages) . "</strong></p>";

if ($fail === 0 && $warning === 0) {
    echo "<p style='color:green; font-size:18px;'>🎉 ALL PAGES ARE WORKING CORRECTLY!</p>";
} elseif ($fail > 0) {
    echo "<p style='color:red;'>⚠️ $fail page(s) need immediate attention!</p>";
}

echo "</div>";
echo "</body></html>";