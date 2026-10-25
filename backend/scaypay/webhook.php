<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/ScayPay.php';
require_once __DIR__ . '/../src/CompanyRegistrationPayment.php';
require_once __DIR__ . '/../admin/includes/auth.php';

admin_ensure_company_registration_tables();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Response::error('Method not allowed', 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$timestamp = trim((string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? $_SERVER['HTTP_X_SCAYPAY_TIMESTAMP'] ?? ''));
$signature = trim((string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_SCAYPAY_SIGNATURE'] ?? ''));
$secret = defined('SCAYPAY_WEBHOOK_SECRET') ? trim((string) SCAYPAY_WEBHOOK_SECRET) : '';
if ($secret === '') {
    Response::error('ScayPay webhook signing secret is not configured', 503);
}
if ($timestamp === '' || $signature === '' || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
    Response::error('Invalid or stale ScayPay webhook timestamp', 400);
}
$expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
if (!hash_equals($expected, $signature)) {
    Response::error('Invalid ScayPay webhook signature', 401);
}

$event = json_decode($rawBody, true);
if (!is_array($event) || !isset($event['data']) || !is_array($event['data'])) {
    Response::error('Invalid ScayPay webhook payload');
}
$data = $event['data'];
$trackingId = trim((string) ($data['id'] ?? ''));
$merchantReference = trim((string) ($data['reference'] ?? ''));
if ($trackingId === '' || $merchantReference === '') {
    Response::error('ScayPay payment id and reference are required');
}

try {
    sync_company_registration_payment(Database::pdo(), new ScayPay(), $trackingId, $merchantReference);
    Response::json(['ok' => true, 'received' => true]);
} catch (Throwable $exception) {
    Response::error($exception->getMessage(), 409);
}

