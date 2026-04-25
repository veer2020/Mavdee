<?php
declare(strict_types=1);

/**
 * api/shipping-cost.php
 *
 * POST-only endpoint to calculate Delhivery shipping cost for a destination.
 *
 * Body (JSON): {
 *   dest_pin:      string   required — 6-digit destination pincode
 *   weight_grams?: number   default 500
 *   payment_mode?: string   'Pre-paid' | 'COD'  default 'Pre-paid'
 *   mode?:         string   'Surface' | 'Express'  default 'Surface'
 *   order_total?:  number   used for free-shipping threshold check
 * }
 *
 * Returns: { success, cost, cod_charges, total, currency, free_shipping }
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once __DIR__ . '/shipping/Delhivery.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$destPin     = trim($input['dest_pin']     ?? '');
$weightGrams = (float)($input['weight_grams'] ?? 500);
$paymentMode = trim($input['payment_mode'] ?? 'Pre-paid');
$mode        = trim($input['mode']         ?? 'Surface');
$orderTotal  = (float)($input['order_total'] ?? 0);

if (!preg_match('/^\d{6}$/', $destPin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid 6-digit destination pincode.']);
    exit;
}

// ── Free-shipping threshold ────────────────────────────────────────────────────
$freeShippingAbove = (float)getSetting('free_shipping_above', 999);
$freeShipping      = ($freeShippingAbove > 0 && $orderTotal >= $freeShippingAbove);

if ($freeShipping) {
    echo json_encode([
        'success'      => true,
        'cost'         => 0,
        'cod_charges'  => 0,
        'total'        => 0,
        'currency'     => 'INR',
        'free_shipping' => true,
    ]);
    exit;
}

// ── Attempt live rate from Delhivery ─────────────────────────────────────────
$originPin = getSetting('delhivery_warehouse_pin', '');
if (empty($originPin)) {
    // Fall back to warehouse table
    try {
        $wh        = db_row("SELECT pincode FROM warehouses WHERE is_default = 1 LIMIT 1");
        $originPin = $wh['pincode'] ?? '';
    } catch (Throwable) {
        $originPin = '';
    }
}

$dlv    = new Delhivery();
$result = $dlv->calculateShippingCost($originPin, $destPin, $weightGrams, $paymentMode, $mode);

if ($result['success']) {
    echo json_encode([
        'success'       => true,
        'cost'          => $result['cost'],
        'cod_charges'   => $result['cod_charges'],
        'total'         => $result['total'],
        'currency'      => $result['currency'],
        'free_shipping' => false,
    ]);
    exit;
}

// ── Graceful fallback to configured defaults ──────────────────────────────────
$defaultCost   = (float)getSetting('default_shipping_cost', 49);
$codExtraFee   = strtolower($paymentMode) === 'cod'
    ? (float)getSetting('cod_extra_fee', 30)
    : 0.0;

echo json_encode([
    'success'       => true,
    'cost'          => $defaultCost,
    'cod_charges'   => $codExtraFee,
    'total'         => $defaultCost + $codExtraFee,
    'currency'      => 'INR',
    'free_shipping' => false,
    'source'        => 'fallback',
]);
