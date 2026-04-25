<?php
/**
 * api/personalization/recommendations.php
 * GET — Returns personalised product recommendations.
 * Auth-optional: returns personalised results for logged-in users,
 * trending products for guests.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/api/db.php';
require_once dirname(__DIR__, 2) . '/includes/personalization.php';

header('Content-Type: application/json; charset=utf-8');

$limit  = min(max((int)($_GET['limit'] ?? 4), 1), 16);
$userId = isLoggedIn() ? getUserId() : 0;

$engine  = new Personalization();
$results = $engine->getRecommendations($userId, $limit);

// Format image URLs
foreach ($results as &$p) {
    $p['image_url']      = img_url($p['image_url'] ?? '');
    $p['price']          = (float)$p['price'];
    $p['original_price'] = (float)($p['original_price'] ?? 0);
}
unset($p);

echo json_encode(['recommendations' => $results]);
