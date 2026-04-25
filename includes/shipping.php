<?php

/**
 * includes/shipping.php — Delhivery API Wrapper
 * Handles rate checking, shipment creation, and tracking for Mavdee.
 */

declare(strict_types=1);

class DelhiveryShipping
{
    private string $token;
    private string $clientName;
    private string $warehousePin;
    private bool $isEnabled;
    private string $baseUrl;

    public function __construct()
    {
        $settings = getPaymentSettings('delhivery');

        $this->isEnabled = $settings['enabled'] ?? false;
        $this->token = $settings['token'] ?? '';
        $this->warehousePin = $settings['warehouse_pin'] ?? '';
        // Allow environment variable to override facility name for exact matching
        $this->clientName = getenv('DELHIVERY_FACILITY_NAME') ?: ($settings['client_name'] ?? 'Mavdee');

        // Fetch environment to determine the API base URL
        $env = getenv('DELHIVERY_ENV') ?: 'production';
        $this->baseUrl = ($env === 'sandbox')
            ? 'https://staging-express.delhivery.com'
            : 'https://track.delhivery.com';
    }

    /**
     * Check if Delhivery integration is active and properly configured.
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled && !empty($this->token);
    }

    /**
     * Internal helper to execute cURL requests to Delhivery API endpoints.
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        if (!$this->isEnabled()) {
            log_error("Delhivery API called but integration is disabled or token is missing.");
            return null;
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init();

        $headers = [
            'Authorization: Token ' . $this->token,
            'Accept: application/json'
        ];

        if (strtoupper($method) === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT') {
            if (strtoupper($method) === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            // Delhivery's package creation endpoint often requires form-url-encoded data
            if (isset($data['format']) && isset($data['data'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            log_error("Delhivery API Request Error ($url): $error");
            return null;
        }

        $decoded = json_decode($response, true);

        // Handle raw file responses (e.g. PDF Packing Slips)
        if (json_last_error() !== JSON_ERROR_NONE && !empty($response)) {
            return ['is_raw' => true, 'content' => base64_encode($response)];
        }

        if ($httpCode >= 400) {
            log_error("Delhivery API HTTP $httpCode ($url): " . json_encode($decoded));
        }

        return $decoded;
    }

    /**
     * Calculate shipping rates between origin warehouse and customer pincode.
     */
    public function checkRates(string $destPin, int $weightGrams = 500, string $mode = 'S'): ?array
    {
        if (empty($this->warehousePin)) {
            log_error("Delhivery CheckRates: Origin PIN code is not set in DB settings.");
            return null;
        }

        // 'md' = Mode (S for Surface, E for Express)
        // 'ss' = Status (Delivered)
        return $this->request('GET', 'api/kinko/v1/invoice/charges/.json', [
            'md' => $mode,
            'ss' => 'Delivered',
            'd_pin' => $destPin,
            'o_pin' => $this->warehousePin,
            'cgm' => $weightGrams
        ]);
    }

    /**
     * Fetch a new Waybill (AWB) number to assign to an order.
     */
    public function fetchWaybill(): ?string
    {
        $response = $this->request('GET', 'waybill/api/bulk/json/', ['count' => 1]);
        if (isset($response['waybill'])) {
            $waybillData = explode(',', $response['waybill']);
            return trim($waybillData[0]);
        }
        return null;
    }

    /**
     * Fetch Bulk Waybills
     */
    public function fetchBulkWaybills(int $count = 5): ?array
    {
        $response = $this->request('GET', 'waybill/api/bulk/json/', ['count' => $count]);
        if (isset($response['waybill'])) {
            $waybills = explode(',', $response['waybill']);
            return array_map('trim', $waybills);
        }
        return null;
    }

    /**
     * Create a shipment by sending package details to Delhivery.
     */
    public function createShipment(array $shipmentData): ?array
    {
        $payload = [
            'format' => 'json',
            'data' => json_encode([
                'shipments' => [$shipmentData],
                'pickup_location' => [
                    'name' => $this->clientName,
                    'pin' => $this->warehousePin,
                ]
            ])
        ];

        return $this->request('POST', 'api/cmu/create.json', $payload);
    }

    /**
     * Fetch tracking details for a specific waybill number.
     */
    public function trackShipment(string $waybill): ?array
    {
        return $this->request('GET', 'api/v1/packages/json/', ['waybill' => $waybill]);
    }

    /**
     * Check if a pincode is serviceable by Delhivery.
     * Supports standard B2C and Heavy product types.
     *
     * FIX: Previously returned the raw Delhivery response which has a
     * 'delivery_codes' key. All callers (pincode-check.php, pincode_check.php,
     * delhivery-panel.php) expected $result['results'][$pin]. Now normalised.
     *
     * @param string $pincode     The 6-digit destination pincode
     * @param string $productType Pass 'Heavy' for heavy shipment serviceability
     * @return array ['success'=>bool, 'results'=>[$pin => [...]], 'error'=>string]
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
            return ['success' => false, 'error' => 'No response from Delhivery API.'];
        }

        // Normalise Delhivery's delivery_codes array into results[$pin]
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

        if (!isset($results[$pincode])) {
            $results[$pincode] = [
                'serviceable' => false,
                'cod'         => false,
                'prepaid'     => false,
                'city'        => '',
                'state'       => '',
            ];
        }

        return ['success' => true, 'results' => $results];
    }

    /**
     * Get Expected TAT (Turn Around Time)
     */
    public function getExpectedTat(string $originPin, string $destPin, string $expectedPickupDate, string $mot = 'S', string $pdt = 'B2C'): ?array
    {
        return $this->request('GET', 'api/dc/expected_tat', [
            'origin_pin' => $originPin,
            'destination_pin' => $destPin,
            'mot' => $mot,
            'pdt' => $pdt,
            'expected_pickup_date' => $expectedPickupDate
        ]);
    }

    /**
     * Update Shipment Details (e.g. COD amount, dimensions)
     */
    public function updateShipment(string $waybill, array $updates): ?array
    {
        $payload = array_merge(['waybill' => $waybill], $updates);
        return $this->request('POST', 'api/p/edit', $payload);
    }

    /**
     * Cancel an existing shipment
     */
    public function cancelShipment(string $waybill): ?array
    {
        return $this->request('POST', 'api/p/edit', [
            'waybill' => $waybill,
            'cancellation' => 'true'
        ]);
    }

    /**
     * Generate / Download Shipping Label (Packing Slip)
     */
    public function getShippingLabel(string $waybill, string $pdfSize = ''): ?array
    {
        $params = ['wbns' => $waybill, 'pdf' => 'true'];
        if (!empty($pdfSize)) {
            $params['pdf_size'] = $pdfSize;
        }
        return $this->request('GET', 'api/p/packing_slip', $params);
    }

    /**
     * Create a Pickup Request.
     *
     * FIX: Signature changed from (string, string, int) to (array $options) so
     * callers can pass named parameters without breaking when new fields are added.
     * Backwards-compatible: existing direct string calls are handled via overloading.
     */
    public function createPickupRequest(string|array $pickupDateOrOptions, string $pickupTime = '10:00:00', int $packageCount = 1): ?array
    {
        if (is_array($pickupDateOrOptions)) {
            $opts         = $pickupDateOrOptions;
            $pickupDate   = $opts['pickup_date']            ?? date('Y-m-d');
            $pickupTime   = $opts['pickup_time']            ?? '10:00:00';
            $packageCount = (int)($opts['expected_package_count'] ?? 1);
        } else {
            $pickupDate = $pickupDateOrOptions;
        }

        return $this->request('POST', 'fm/request/new/', [
            'pickup_date'            => $pickupDate,
            'pickup_time'            => $pickupTime,
            'pickup_location'        => $this->clientName,
            'expected_package_count' => $packageCount,
        ]);
    }

    /**
     * Create a New Warehouse / Pickup Location
     */
    public function createWarehouse(array $warehouseData): ?array
    {
        return $this->request('POST', 'api/backend/clientwarehouse/create/', $warehouseData);
    }

    /**
     * Edit an Existing Warehouse / Pickup Location
     */
    public function editWarehouse(array $warehouseData): ?array
    {
        return $this->request('POST', 'api/backend/clientwarehouse/edit/', $warehouseData);
    }

    /**
     * Update E-waybill for a shipment
     */
    public function updateEwaybill(string $waybill, string $invoiceNumber, string $ewbNumber): ?array
    {
        return $this->request('PUT', "api/rest/ewaybill/{$waybill}/", [
            'data' => [
                [
                    'dcn' => $invoiceNumber,
                    'ewbn' => $ewbNumber
                ]
            ]
        ]);
    }

    /**
     * Update NDR (Non-Delivery Report) Status
     */
    public function updateNdr(string $waybill, string $action = 'RE-ATTEMPT'): ?array
    {
        return $this->request('POST', 'api/p/update', [
            'data' => [
                [
                    'waybill' => $waybill,
                    'act' => $action
                ]
            ]
        ]);
    }

    /**
     * Fetch Document (e.g. POD, QC Images)
     */
    public function fetchDocument(string $waybill, string $docType): ?array
    {
        return $this->request('GET', 'api/rest/fetch/pkg/document/', [
            'doc_type' => $docType,
            'waybill' => $waybill
        ]);
    }
}
