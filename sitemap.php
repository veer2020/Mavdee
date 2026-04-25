<?php
/**
 * sitemap.php
 * Dynamically generates an XML sitemap for all products, categories and static pages.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/api/db.php';

header('Content-Type: application/xml; charset=utf-8');

$siteUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$urls    = [];

// Homepage
$urls[] = ['loc' => $siteUrl . '/', 'priority' => '1.0', 'changefreq' => 'daily'];

// Products
$products = db_select("SELECT id, slug, updated_at FROM products WHERE is_active = 1 ORDER BY updated_at DESC");
foreach ($products as $product) {
    $slug     = !empty($product['slug']) ? '&slug=' . rawurlencode($product['slug']) : '';
    $lastmod  = !empty($product['updated_at']) ? date('Y-m-d', strtotime($product['updated_at'])) : date('Y-m-d');
    $urls[]   = [
        'loc'        => $siteUrl . '/product.php?id=' . (int)$product['id'] . $slug,
        'lastmod'    => $lastmod,
        'priority'   => '0.8',
        'changefreq' => 'weekly',
    ];
}

// Categories
$categories = db_select("SELECT slug, created_at FROM categories WHERE is_active = 1");
foreach ($categories as $cat) {
    $urls[] = [
        'loc'        => $siteUrl . '/shop.php?cat=' . rawurlencode($cat['slug']),
        'lastmod'    => date('Y-m-d', strtotime($cat['created_at'])),
        'priority'   => '0.7',
        'changefreq' => 'weekly',
    ];
}

// Static pages
foreach (['about', 'contact', 'privacy', 'returns', 'shipping'] as $page) {
    $urls[] = ['loc' => $siteUrl . '/' . $page . '.php', 'priority' => '0.5', 'changefreq' => 'monthly'];
}

// Output XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc']) . "</loc>\n";
    if (!empty($url['lastmod']))    echo '    <lastmod>'    . $url['lastmod']    . "</lastmod>\n";
    if (!empty($url['changefreq'])) echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    if (!empty($url['priority']))   echo '    <priority>'   . $url['priority']   . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
