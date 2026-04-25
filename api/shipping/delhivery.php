<?php

declare(strict_types=1);

/**
 * api/shipping/delhivery.php
 *
 * Delhivery API client used by all admin order-management files.
 * All public methods return a normalised array:
 *   ['success' => bool, ...payload keys...]
 *   ['success' => false, 'error' => string]   on failure
 *
 * FIX: Previously this file did not exist; admin files required it but only
 * includes/shipping.php (class DelhiveryShipping) was present, causing a
 * fatal "Class 'Delhivery' not found" on every admin shipping action.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

class Delhivery
{
    private string $token;
    private string $clientName;
    private string $warehousePin;
    private bool   $enabled;
    private string $baseUrl;

    public function __construct()
    {
        $settings = getPaymentSettings('delhivery');

        $this->enabled      = !empty($settings['enabled']);
        $this->token        = $settings['token']         ?? '';
        $this->warehousePin = $settings['warehouse_pin'] ?? '';
        $this->clientName   = getenv('DELHIVERY_FACILITY_NAME')
            ?: ($settings['client_name'] ?? 'Mavdee');

        $env = getenv('DELHIVERY_ENV') ?: 'production';
        $this->baseUrl = ($env === 'sandbox')
            ? 'https://staging-express.delhivery.com'
            : 'https://track.delhivery.com';
    }

    // ── Internal HTTP helper ──────────────────────────────────────────────────

    /**
     * Execute a cURL request and return the raw decoded response.
     * Returns null on cURL error or if integration is disabled.
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        if (!$this->enabled || empty($this->token)) {
            log_error('Delhivery API called but integration is disabled or token missing.');
            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $ch  = curl_init();

        $headers = [
            'Authorization: Token ' . $this->token,
            'Accept: application/json',
        ];

        if (strtoupper($method) === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $upperMethod = strtoupper($method);
        if ($upperMethod === 'POST' || $upperMethod === 'PUT') {
            if ($upperMethod === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            // Shipment-creation endpoint uses form-urlencoded with format+data keys
            if (isset($data['format'], $data['data'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            log_error("Delhivery cURL error ($url): $curlErr");
            return null;
        }

        // PDF / binary response (e.g. packing slip)
        $decoded = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE && !empty($response)) {
            return ['__binary' => true, 'content' => $response];
        }

        if ($httpCode >= 400) {
            log_error("Delhivery HTTP $httpCode ($url): " . json_encode($decoded));
        }

        return $decoded;
    }

    // ── Normalised response helpers ───────────────────────────────────────────

    private function ok(array $extra = []): array
    {
        return array_merge(['success' => true], $extra);
    }

    private function fail(string|array $message): array
    {
        if (is_array($message)) {
            $message = implode(', ', array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $message));
        }
        return ['success' => false, 'error' => $message];
    }

    // ── Public API methods ────────────────────────────────────────────────────

    /**
     * Create a forward shipment.
     *
     * Accepts a high-level order array and maps it to Delhivery's payload.
     * Returns ['success'=>true, 'waybill'=>'...'] on success.
     *
     * FIX: Previously createShipment() returned the raw API response with no
     * 'success'/'waybill' keys; every caller that checked $result['success']
     * always hit the failure branch.
     */
    public function createShipment(array $order, string $shippingMode = 'Surface', int $weightGrams = 500): array
    {
        $addr    = $order['shipping_address'] ?? [];
        $items   = $order['items']            ?? [];
        $isCod   = strtolower($order['payment_method'] ?? '') === 'cod';

        // Build product description from items
        $productsDesc = implode(', ', array_map(
            fn($i) => ($i['product_name'] ?? 'Item') . ' x' . ($i['qty'] ?? 1),
            $items
        ));

        $shipment = [
            'name'           => $order['customer_name']  ?? '',
            'add'            => $addr['address']         ?? $addr['add'] ?? '',
            'pin'            => (string)($addr['pincode'] ?? $addr['pin'] ?? ''),
            'city'           => $addr['city']            ?? '',
            'state'          => $addr['state']           ?? '',
            'country'        => $addr['country']         ?? 'India',
            'phone'          => $order['customer_phone'] ?? '',
            'order'          => $order['order_number']   ?? '',
            'payment_mode'   => $isCod ? 'COD' : 'Prepaid',
            'cod_amount'     => $isCod ? (string)($order['total'] ?? 0) : '',
            'total_amount'   => (string)($order['total'] ?? 0),
            'products_desc'  => $productsDesc ?: 'Fashion garments',
            'quantity'       => (string)array_sum(array_column($items, 'qty')),
            'weight'         => isset($order['weight']) ? (string)$order['weight'] : (string)$weightGrams,
            'shipping_mode'  => $order['shipping_mode'] ?? $shippingMode,
            'waybill'        => '',        // blank = Delhivery auto-assigns
            'return_pin'     => $this->warehousePin,
            'return_city'    => '',
            'return_phone'   => '',
            'return_add'     => '',
            'return_state'   => '',
            'return_country' => 'India',
        ];

        $payload = [
            'format' => 'json',
            'data'   => json_encode([
                'shipments'       => [$shipment],
                'pickup_location' => ['name' => $this->clientName],
            ]),
        ];

        $raw = $this->request('POST', 'api/cmu/create.json', $payload);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        // Delhivery returns {"packages":[{"status":"Success","waybill":"..."}],...}
        $pkg = $raw['packages'][0] ?? null;
        if (!$pkg) {
            $msg = $raw['rmk'] ?? $raw['error'] ?? 'Unexpected API response.';
            return $this->fail($msg);
        }

        if (strtolower($pkg['status'] ?? '') !== 'success') {
            return $this->fail($pkg['remarks'] ?? $pkg['status'] ?? 'Shipment creation failed.');
        }

        return $this->ok(['waybill' => $pkg['waybill']]);
    }

    /**
     * Create a Reverse/Return (RVP) shipment.
     *
     * FIX: delhivery-panel.php called $dlv->createRvpShipment() which did not
     * exist anywhere, causing a fatal error.
     */
    public function createRvpShipment(array $order, int $weightGrams = 500): array
    {
        // For RVP the pickup address = customer, delivery address = warehouse
        $addr  = $order['shipping_address'] ?? [];
        $items = $order['items']            ?? [];

        $productsDesc = implode(', ', array_map(
            fn($i) => ($i['product_name'] ?? 'Item') . ' x' . ($i['qty'] ?? 1),
            $items
        ));

        $shipment = [
            'name'          => $order['customer_name']  ?? '',
            'add'           => $addr['address']         ?? $addr['add'] ?? '',
            'pin'           => (string)($addr['pincode'] ?? $addr['pin'] ?? ''),
            'city'          => $addr['city']            ?? '',
            'state'         => $addr['state']           ?? '',
            'country'       => 'India',
            'phone'         => $order['customer_phone'] ?? '',
            'order'         => $order['order_number']   ?? '',
            'payment_mode'  => 'Pickup',    // RVP = Pickup mode
            'total_amount'  => (string)($order['total'] ?? 0),
            'products_desc' => $productsDesc ?: 'Return shipment',
            'quantity'      => (string)array_sum(array_column($items, 'qty')),
            'weight'        => isset($order['weight']) ? (string)$order['weight'] : (string)$weightGrams,
            'shipping_mode' => 'Surface',
            'waybill'       => '',
            'return_pin'    => $this->warehousePin,
            'return_city'   => '',
            'return_phone'  => '',
            'return_add'    => '',
            'return_state'  => '',
            'return_country' => 'India',
        ];

        $payload = [
            'format' => 'json',
            'data'   => json_encode([
                'shipments'       => [$shipment],
                'pickup_location' => ['name' => $this->clientName],
            ]),
        ];

        $raw = $this->request('POST', 'api/cmu/create.json', $payload);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        $pkg = $raw['packages'][0] ?? null;
        if (!$pkg || strtolower($pkg['status'] ?? '') !== 'success') {
            return $this->fail($pkg['remarks'] ?? $pkg['status'] ?? 'RVP creation failed.');
        }

        return $this->ok(['waybill' => $pkg['waybill']]);
    }

    /**
     * Cancel a shipment by waybill.
     *
     * FIX: Previously returned raw API array; callers checked $result['success']
     * which was never set, so the success branch was unreachable.
     */
    public function cancelShipment(string $waybill): array
    {
        $raw = $this->request('POST', 'api/p/edit', [
            'waybill'      => $waybill,
            'cancellation' => 'true',
        ]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        // Delhivery returns {"status":true} or {"status":false,"error":"..."}
        if (!empty($raw['status']) || strtolower((string)($raw['status'] ?? '')) === 'true') {
            return $this->ok();
        }

        return $this->fail($raw['error'] ?? $raw['message'] ?? 'Cancellation failed.');
    }

    /**
     * Download a shipping label PDF as raw bytes.
     *
     * FIX: Both label_delhivery.php and label-proxy.php called
     * $dlv->downloadDocument($waybill, 'label') — this method did not exist.
     * It is now implemented on top of the packing_slip endpoint.
     */
    public function downloadDocument(string $waybills, string $type = 'label'): array
    {
        if ($type === 'label') {
            $raw = $this->request('GET', 'api/p/packing_slip', [
                'wbns' => $waybills,
                'pdf'  => 'true',
            ]);
        } else {
            // Generic document (POD, QC images, etc.)
            $raw = $this->request('GET', 'api/rest/fetch/pkg/document/', [
                'doc_type' => $type,
                'waybill'  => $waybills,
            ]);
        }

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        // Binary (PDF) response
        if (!empty($raw['__binary'])) {
            return $this->ok(['content' => $raw['content']]);
        }

        // base64-encoded content from legacy raw handler in DelhiveryShipping
        if (!empty($raw['content'])) {
            $content = $raw['content'];
            // Decode if it was base64-encoded by an earlier layer
            $decoded = base64_decode($content, true);
            return $this->ok(['content' => $decoded !== false ? $decoded : $content]);
        }

        return $this->fail('Label not available.');
    }

    /**
     * Track a shipment by waybill.
     * Returns ['success'=>true, 'status'=>'...', 'events'=>[...], ...]
     */
    public function trackShipment(string $waybill): array
    {
        $raw = $this->request('GET', 'api/v1/packages/json/', ['waybill' => $waybill]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        $pkg = $raw['ShipmentData'][0]['Shipment'] ?? null;
        if (!$pkg) {
            return $this->fail('Tracking data not found.');
        }

        $events = [];
        foreach ($pkg['Scans'] ?? [] as $scan) {
            $s        = $scan['ScanDetail'] ?? [];
            $events[] = [
                'timestamp' => $s['ScanDateTime']    ?? '',
                'status'    => $s['Scan']            ?? '',
                'detail'    => $s['Instructions']    ?? '',
                'location'  => $s['ScannedLocation'] ?? '',
            ];
        }

        return $this->ok([
            'status'            => $pkg['Status']['Status']            ?? '',
            'location'          => $pkg['Status']['StatusLocation']    ?? '',
            'expected_delivery' => $pkg['ExpectedDeliveryDate']        ?? '',
            'tracking_url'      => 'https://www.delhivery.com/track/package/' . urlencode($waybill),
            'events'            => $events,
        ]);
    }

    /**
     * Check pincode serviceability.
     * Returns ['success'=>true, 'results'=>[$pin => ['serviceable'=>bool, 'cod'=>bool, 'prepaid'=>bool, 'city'=>'', 'state'=>'']]]
     *
     * FIX: Both pincode-check.php and pincode_check.php read $result['results'][$pin]
     * but the raw Delhivery response uses 'delivery_codes'; this method now
     * normalises the response into the expected shape.
     */
    public function checkServiceability(string $pincode, string $productType = ''): array
    {
        if (strtolower($productType) === 'heavy') {
            $raw = $this->request('GET', 'api/dc/fetch/serviceability/pincode', [
                'pincode'      => $pincode,
                'product_type' => 'Heavy',
            ]);
        } else {
            $raw = $this->request('GET', 'c/api/pin-codes/json/', [
                'filter_codes' => $pincode,
            ]);
        }

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        // Delhivery standard response: {"delivery_codes":[{"postal_code":{"pin":"...","cod":"Y","pre_paid":"Y","pickup":"Y","repl":"Y","city":"...","state_code":"..."}}}
        $results = [];
        foreach ($raw['delivery_codes'] ?? [] as $entry) {
            $pc  = $entry['postal_code'] ?? [];
            $pin = (string)($pc['pin'] ?? '');
            if ($pin === '') continue;

            $results[$pin] = [
                'serviceable' => true,
                'cod'         => strtoupper($pc['cod']      ?? 'N') === 'Y',
                'prepaid'     => strtoupper($pc['pre_paid'] ?? 'N') === 'Y',
                'city'        => $pc['city']       ?? '',
                'state'       => $pc['state_code'] ?? '',
            ];
        }

        // If the requested pin isn't in results, it's not serviceable
        if (!isset($results[$pincode])) {
            $results[$pincode] = [
                'serviceable' => false,
                'cod'         => false,
                'prepaid'     => false,
                'city'        => '',
                'state'       => '',
            ];
        }

        return $this->ok(['results' => $results]);
    }

    /**
     * Create a pickup request.
     *
     * FIX: delhivery-panel.php called createPickupRequest(['pickup_date'=>..., 'expected_package_count'=>...])
     * passing a single array, but the old signature was (string $date, string $time, int $count).
     * This method now accepts an options array so the panel call works as-is.
     */
    public function createPickupRequest(array $options): array
    {
        $pickupDate  = $options['pickup_date']            ?? date('Y-m-d');
        $pickupTime  = $options['pickup_time']            ?? '10:00:00';
        $packageCount = (int)($options['expected_package_count'] ?? 1);

        $raw = $this->request('POST', 'fm/request/new/', [
            'pickup_date'            => $pickupDate,
            'pickup_time'            => $pickupTime,
            'pickup_location'        => $this->clientName,
            'expected_package_count' => $packageCount,
        ]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        if (!empty($raw['success']) || !empty($raw['pickup_id'])) {
            return $this->ok(['pickup_id' => $raw['pickup_id'] ?? $raw['id'] ?? null]);
        }

        return $this->fail($raw['error'] ?? $raw['message'] ?? 'Pickup request failed.');
    }

    /**
     * NDR action (re-attempt or RTO).
     *
     * FIX: delhivery-panel.php called $dlv->ndrAction($awb, $action, $opts) which
     * did not exist. Now implemented (maps to updateNdr internally).
     */
    public function ndrAction(string $waybill, string $action, array $options = []): array
    {
        // Normalise action string to what Delhivery expects
        $actionMap = [
            're-attempt' => 'RE-ATTEMPT',
            'reattempt'  => 'RE-ATTEMPT',
            'rto'        => 'RTO',
        ];
        $dlvAction = $actionMap[strtolower($action)] ?? strtoupper($action);

        $raw = $this->request('POST', 'api/p/update', [
            'data' => [[
                'waybill' => $waybill,
                'act'     => $dlvAction,
                'remarks' => $options['remarks'] ?? '',
            ]],
        ]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        $first = $raw[0] ?? $raw;
        if (!empty($first['success']) || (isset($first['status']) && $first['status'] !== false)) {
            return $this->ok();
        }

        return $this->fail($first['error'] ?? $first['message'] ?? 'NDR action failed.');
    }

    /**
     * Update e-waybill.
     *
     * FIX: delhivery-panel.php called updateEwaybill($awb, $ewaybillNo, $expiry)
     * but the old signature was (waybill, invoiceNumber, ewbNumber) — the
     * third parameter was the e-waybill number, not expiry.
     * New signature matches the panel's intent: ($waybill, $ewbNumber, $expiry='').
     */
    public function updateEwaybill(string $waybill, string $ewbNumber, string $expiry = ''): array
    {
        $payload = [['dcn' => $waybill, 'ewbn' => $ewbNumber]];
        if (!empty($expiry)) {
            $payload[0]['ewb_expiry'] = $expiry;
        }

        $raw = $this->request('PUT', "api/rest/ewaybill/{$waybill}/", ['data' => $payload]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        if (!empty($raw['success']) || (isset($raw['status']) && $raw['status'] !== false)) {
            return $this->ok();
        }

        return $this->fail($raw['error'] ?? $raw['message'] ?? 'E-waybill update failed.');
    }

    /**
     * Get shipping rates.
     */
    public function checkRates(string $destPin, int $weightGrams = 500, string $mode = 'S'): array
    {
        if (empty($this->warehousePin)) {
            return $this->fail('Warehouse pincode not configured.');
        }

        $raw = $this->request('GET', 'api/kinko/v1/invoice/charges/.json', [
            'md'    => $mode,
            'ss'    => 'Delivered',
            'd_pin' => $destPin,
            'o_pin' => $this->warehousePin,
            'cgm'   => $weightGrams,
        ]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        return $this->ok(['rates' => $raw]);
    }

    /**
     * Fetch expected TAT.
     */
    public function getExpectedTat(string $originPin, string $destPin, string $expectedPickupDate, string $mot = 'S', string $pdt = 'B2C'): array
    {
        $raw = $this->request('GET', 'api/dc/expected_tat', [
            'origin_pin'           => $originPin,
            'destination_pin'      => $destPin,
            'mot'                  => $mot,
            'pdt'                  => $pdt,
            'expected_pickup_date' => $expectedPickupDate,
        ]);

        if ($raw === null) {
            return $this->fail('No response from Delhivery API.');
        }

        return $this->ok(['tat' => $raw]);
    }

    /**
     * Fetch bulk waybills.
     */
    public function fetchBulkWaybills(int $count = 5): array
    {
        $raw = $this->request('GET', 'waybill/api/bulk/json/', ['count' => $count]);

        if ($raw === null || empty($raw['waybill'])) {
            return $this->fail('Could not fetch waybills.');
        }

        return $this->ok(['waybills' => array_map('trim', explode(',', $raw['waybill']))]);
    }
}
