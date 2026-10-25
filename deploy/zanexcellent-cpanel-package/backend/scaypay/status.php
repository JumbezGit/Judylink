<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/ScayPay.php';
require_once __DIR__ . '/../src/CompanyRegistrationPayment.php';
require_once __DIR__ . '/../admin/includes/auth.php';

admin_ensure_company_registration_tables();

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    Response::error('Method not allowed', 405);
}

$input = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? request_json() : $_GET;
$trackingId = trim((string) ($input['OrderTrackingId'] ?? $input['orderTrackingId'] ?? ''));
$merchantReference = trim((string) ($input['OrderMerchantReference'] ?? $input['orderMerchantReference'] ?? ''));
if ($trackingId === '' && $merchantReference === '') {
    Response::error('Payment reference is required');
}
if (strlen($trackingId) > 100 || strlen($merchantReference) > 50) {
    Response::error('Invalid payment reference');
}

try {
    $result = sync_company_registration_payment(Database::pdo(), new ScayPay(), $trackingId, $merchantReference);
    Response::json(['ok' => true] + $result);
} catch (RuntimeException $exception) {
    Response::error($exception->getMessage(), str_contains($exception->getMessage(), 'not found') ? 404 : 409);
}

