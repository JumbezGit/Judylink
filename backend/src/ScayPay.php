<?php

declare(strict_types=1);

final class ScayPay
{
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(defined('SCAYPAY_API_URL') ? (string) SCAYPAY_API_URL : 'https://api.scaypay.com/v1', '/');
        $this->secretKey = defined('SCAYPAY_SECRET_KEY') ? trim((string) SCAYPAY_SECRET_KEY) : '';
    }

    public function createCheckout(array $order): array
    {
        $this->requireCredentials();
        $payload = [
            'payment_type' => 'card',
            'amount' => (int) round((float) $order['amount']),
            'currency' => 'TZS',
            'phone' => (string) $order['phone'],
            'customer' => [
                'name' => trim((string) $order['first_name'] . ' ' . (string) $order['last_name']),
                'email' => (string) $order['email'],
                'address' => (string) $order['company_name'],
                'city' => 'Dar es Salaam',
                'state' => 'DSM',
                'postcode' => '00000',
                'country' => 'TZ',
            ],
            'redirect_url' => (string) $order['callback_url'],
            'cancel_url' => (string) $order['cancellation_url'],
            'reference' => (string) $order['reference'],
            'description' => mb_substr((string) $order['description'], 0, 100),
        ];
        $webhookUrl = defined('SCAYPAY_WEBHOOK_URL') ? trim((string) SCAYPAY_WEBHOOK_URL) : '';
        if ($webhookUrl !== '') {
            $payload['webhook_url'] = $webhookUrl;
        }

        $response = $this->request('POST', '/payments/collect', $payload, (string) $order['reference']);
        $payment = $this->unwrap($response);
        $trackingId = trim((string) ($payment['id'] ?? ''));
        $checkoutUrl = trim((string) ($payment['checkout_url'] ?? ''));
        $status = strtolower(trim((string) ($payment['status'] ?? 'pending')));
        if ($trackingId === '') {
            throw new RuntimeException($this->errorMessage($response, 'ScayPay did not return a payment ID.'));
        }
        if ($checkoutUrl === '' && in_array($status, ['completed', 'paid', 'successful', 'success'], true)) {
            // Sandbox payments complete instantly and therefore do not open hosted checkout.
            $checkoutUrl = (string) $order['callback_url'];
        }
        if ($checkoutUrl === '') {
            throw new RuntimeException($this->errorMessage($response, 'ScayPay did not return a hosted checkout link.'));
        }

        return [
            'order_tracking_id' => $trackingId,
            'merchant_reference' => (string) $order['reference'],
            'redirect_url' => $checkoutUrl,
            'status' => $status,
            'response' => $response,
        ];
    }

    public function transactionStatus(string $trackingId): array
    {
        $this->requireCredentials();
        if ($trackingId === '') {
            throw new InvalidArgumentException('ScayPay payment ID is required.');
        }

        return $this->unwrap($this->request('GET', '/payments/' . rawurlencode($trackingId)));
    }

    public static function localStatus(array $gatewayStatus): string
    {
        $status = strtolower(trim((string) ($gatewayStatus['status'] ?? 'pending')));
        return match ($status) {
            'completed', 'paid', 'successful', 'success' => 'paid',
            'failed', 'declined', 'expired', 'cancelled', 'canceled' => 'failed',
            'reversed', 'refunded' => 'refunded',
            default => 'pending',
        };
    }

    private function requireCredentials(): void
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('ScayPay sandbox secret key is not configured in backend/config.php.');
        }
        if (!str_starts_with($this->secretKey, 'sk_test_') && (defined('SCAYPAY_ENVIRONMENT') ? SCAYPAY_ENVIRONMENT : 'sandbox') === 'sandbox') {
            throw new RuntimeException('A ScayPay sk_test_ key is required while sandbox mode is enabled.');
        }
    }

    private function request(string $method, string $path, ?array $payload = null, string $idempotencyKey = ''): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for ScayPay payments.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->secretKey,
        ];
        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . substr($idempotencyKey, 0, 30);
        }

        $encodedPayload = null;
        if ($payload !== null) {
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encodedPayload === false) {
                throw new RuntimeException('Could not prepare the ScayPay payment request.');
            }
        }

        $retryableCurlErrors = [
            CURLE_COULDNT_CONNECT,
            CURLE_PARTIAL_FILE,
            CURLE_OPERATION_TIMEDOUT,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING,
            CURLE_SEND_ERROR,
            CURLE_RECV_ERROR,
        ];
        $retryableStatusCodes = [408, 425, 429, 500, 502, 503, 504];
        $maxAttempts = ($method === 'GET' || $idempotencyKey !== '') ? 3 : 1;
        $lastCurlError = '';
        $lastCurlErrno = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $curl = curl_init($this->baseUrl . $path);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_NOSIGNAL => true,
            ];
            if ($encodedPayload !== null) {
                $options[CURLOPT_POSTFIELDS] = $encodedPayload;
            }
            curl_setopt_array($curl, $options);
            $body = curl_exec($curl);
            $lastCurlErrno = curl_errno($curl);
            $lastCurlError = curl_error($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($body === false) {
                if ($attempt < $maxAttempts && in_array($lastCurlErrno, $retryableCurlErrors, true)) {
                    usleep(250000 * $attempt);
                    continue;
                }
                error_log(sprintf('ScayPay transport failure after %d attempt(s): cURL %d: %s', $attempt, $lastCurlErrno, $lastCurlError));
                throw new RuntimeException('ScayPay is temporarily unreachable. Please try the payment again in a moment.');
            }

            $decoded = json_decode((string) $body, true);
            if (!is_array($decoded)) {
                if ($attempt < $maxAttempts && in_array($statusCode, $retryableStatusCodes, true)) {
                    usleep(250000 * $attempt);
                    continue;
                }
                throw new RuntimeException('ScayPay returned an invalid response.');
            }

            if ($attempt < $maxAttempts && in_array($statusCode, $retryableStatusCodes, true)) {
                usleep(250000 * $attempt);
                continue;
            }

            $successfulEnvelope = strtolower((string) ($decoded['status'] ?? '')) === 'success';
            if ($statusCode < 200 || $statusCode >= 300 || (!$successfulEnvelope && isset($decoded['error_code']))) {
                throw new RuntimeException($this->errorMessage($decoded, 'ScayPay rejected the request.'));
            }
            return $decoded;
        }

        error_log(sprintf('ScayPay transport failure: cURL %d: %s', $lastCurlErrno, $lastCurlError));
        throw new RuntimeException('ScayPay is temporarily unreachable. Please try the payment again in a moment.');
    }

    private function unwrap(array $response): array
    {
        return isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
    }

    private function errorMessage(array $response, string $fallback): string
    {
        foreach (['message', 'error', 'error_description'] as $field) {
            if (isset($response[$field]) && is_string($response[$field]) && trim($response[$field]) !== '') {
                return trim($response[$field]);
            }
        }
        return $fallback;
    }
}
