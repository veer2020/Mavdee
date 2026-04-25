<?php

/**
 * includes/payment.php
 * PaymentVerifier: server-side Razorpay signature verification and order creation.
 */

declare(strict_types=1);

class PaymentVerifier
{
    /**
     * Verify Razorpay webhook/checkout signature.
     * Signature = HMAC-SHA256(orderId + "|" + paymentId, secret)
     */
    public static function verifyRazorpaySignature(
        string $orderId,
        string $paymentId,
        string $signature,
        string $secret
    ): bool {
        if ($orderId === '' || $paymentId === '' || $signature === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Create a Razorpay order server-side via their REST API.
     * Returns the order data array or null on failure.
     *
     * @param float  $amount   Amount in INR (will be converted to paise)
     * @param string $currency Default "INR"
     * @param string $receipt  Order reference / receipt number
     * @param array  $rzpConfig ['key_id' => ..., 'key_secret' => ...]
     */
    public static function createRazorpayOrder(
        float $amount,
        string $currency = 'INR',
        string $receipt = '',
        array $rzpConfig = []
    ): ?array {
        $keyId     = $rzpConfig['key_id']     ?? '';
        $keySecret = $rzpConfig['key_secret'] ?? '';

        if ($keyId === '' || $keySecret === '') {
            return null;
        }

        $payload = json_encode([
            'amount'   => (int)round($amount * 100), // paise
            'currency' => $currency,
            'receipt'  => $receipt,
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Problem 1 Fix: Check for curl errors
        if ($curlErrno !== 0) {
            error_log("Razorpay API curl error: [$curlErrno] $curlError");
            return null;
        }

        // Problem 2 Fix: Accept both 200 (OK) and 201 (Created)
        if ($response === false || !in_array($httpCode, [200, 201])) {
            error_log("Razorpay API HTTP error: Code $httpCode, Response: $response");
            return null;
        }

        // Problem 3 Fix: Validate JSON decode and check for errors
        $data = json_decode((string)$response, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("Razorpay API JSON decode error: " . json_last_error_msg());
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
