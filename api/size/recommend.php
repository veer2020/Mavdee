<?php
/**
 * api/size/recommend.php
 * POST JSON — Returns recommended size and full size chart.
 * Body: { "bust": 88, "waist": 70, "hip": 94, "category": "Dresses" }
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/size_recommender.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$bust     = (float)($input['bust']     ?? 0);
$waist    = (float)($input['waist']    ?? 0);
$hip      = (float)($input['hip']      ?? 0);
$category = trim($input['category']    ?? 'general');

if ($bust <= 0 && $waist <= 0 && $hip <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide at least one measurement (bust, waist, or hip) in cm.']);
    exit;
}

$recommender = new SizeRecommender();
$measurements = ['bust' => $bust, 'waist' => $waist, 'hip' => $hip];
$size         = $recommender->recommend($measurements, $category);
$sizeChart    = $recommender->getSizeChart($category);
$fitNote      = $recommender->getFitNote($measurements, $size, $category);

echo json_encode([
    'recommended_size' => $size,
    'size_chart'       => $sizeChart,
    'fit_notes'        => $fitNote,
]);
