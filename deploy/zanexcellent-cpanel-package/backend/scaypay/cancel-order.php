<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/scaypay-client.php';

require_method('POST');

$input = request_json();
$trackingId = trim((string) ($input['OrderTrackingId'] ?? $input['orderTrackingId'] ?? ''));
$merchantReference = trim((string) ($input['OrderMerchantReference'] ?? $input['orderMerchantReference'] ?? ''));

if (($trackingId === '' && $merchantReference === '') || strlen($trackingId) > 100 || strlen($merchantReference) > 50) {
    json_response(['message' => 'OrderTrackingId or OrderMerchantReference is required.'], 400);
}

$pdo = db($config);
$query = $pdo->prepare(
    "SELECT p.id, p.booking_id, p.status, p.scaypay_payment_id, p.merchant_reference
     FROM payments p
     WHERE p.scaypay_payment_id = ? OR p.merchant_reference = ?
     LIMIT 1"
);
$query->execute([$trackingId, $merchantReference]);
$payment = $query->fetch();

if (!$payment) {
    json_response(['message' => 'Payment reference was not found.'], 404);
}

$paymentId = (string) ($payment['scaypay_payment_id'] ?? '');
if ($paymentId !== '') {
    try {
        $status = scaypay_status_for_frontend(scaypay_status($config, $paymentId));
        sync_payment($pdo, $paymentId, $status);
        if (normalize_payment_status($status) === 'completed') {
            json_response(['status' => 'completed', 'message' => 'Payment was already completed.']);
        }
    } catch (Throwable) {
        // If ScayPay cannot be reached, cancel only the local pending record.
    }
}

$pdo->beginTransaction();
try {
    $cancelPayment = $pdo->prepare("UPDATE payments SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    $cancelPayment->execute([(int) $payment['id']]);

    $cancelBooking = $pdo->prepare(
        "UPDATE bookings b
         JOIN payments p ON p.booking_id = b.id
         SET b.status = 'cancelled'
         WHERE b.id = ? AND b.status = 'pending' AND p.status = 'cancelled'"
    );
    $cancelBooking->execute([(int) $payment['booking_id']]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

json_response(['status' => 'cancelled', 'message' => 'Pending booking was cancelled.']);
