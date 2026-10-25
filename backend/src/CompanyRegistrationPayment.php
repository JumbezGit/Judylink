<?php

declare(strict_types=1);

function sync_company_registration_payment(PDO $pdo, ScayPay $gateway, string $trackingId, string $merchantReference = ''): array
{
    $lookup = $pdo->prepare(
        'SELECT payments.*, registrations.registration_ref, registrations.plan_name
         FROM company_registration_payments AS payments
         INNER JOIN company_registrations AS registrations ON registrations.id = payments.company_registration_id
         WHERE payments.gateway_tracking_id = :tracking_id
            OR (:merchant_reference <> "" AND payments.merchant_reference = :merchant_reference_match)
         LIMIT 1'
    );
    $lookup->execute([
        'tracking_id' => $trackingId,
        'merchant_reference' => $merchantReference,
        'merchant_reference_match' => $merchantReference,
    ]);
    $payment = $lookup->fetch();
    if (!$payment) {
        throw new RuntimeException('Payment reference was not found.');
    }

    $savedTrackingId = trim((string) ($payment['gateway_tracking_id'] ?? ''));
    if ($savedTrackingId === '') {
        throw new RuntimeException('Payment has not been submitted to ScayPay.');
    }
    if ($trackingId !== '' && !hash_equals($savedTrackingId, $trackingId)) {
        throw new RuntimeException('Payment tracking ID does not match this registration.');
    }

    if ((string) $payment['status'] === 'paid') {
        return [
            'registrationRef' => (string) $payment['registration_ref'],
            'planName' => (string) $payment['plan_name'],
            'paymentReference' => (string) $payment['merchant_reference'],
            'paymentStatus' => 'paid',
            'gatewayStatus' => (string) ($payment['gateway_status'] ?? 'COMPLETED'),
            'amount' => round((float) $payment['amount'], 2),
            'currency' => (string) $payment['currency'],
            'checkoutUrl' => (string) ($payment['checkout_url'] ?? ''),
        ];
    }

    $gatewayStatus = $gateway->transactionStatus($savedTrackingId);
    $gatewayReference = trim((string) ($gatewayStatus['reference'] ?? $gatewayStatus['merchant_reference'] ?? ''));
    $gatewayAmountData = isset($gatewayStatus['amount']) && is_array($gatewayStatus['amount']) ? $gatewayStatus['amount'] : [];
    $gatewayCurrency = strtoupper(trim((string) ($gatewayAmountData['currency'] ?? $gatewayStatus['currency'] ?? '')));
    $gatewayAmountValue = $gatewayAmountData['value'] ?? $gatewayStatus['amount'] ?? -1;
    $gatewayAmount = round((float) $gatewayAmountValue, 2);
    $expectedAmount = round((float) $payment['amount'], 2);
    if ($gatewayReference !== '' && !hash_equals((string) $payment['merchant_reference'], $gatewayReference)) {
        throw new RuntimeException('ScayPay returned a different merchant reference.');
    }
    if ($gatewayCurrency !== '' && $gatewayCurrency !== strtoupper((string) $payment['currency'])) {
        throw new RuntimeException('ScayPay returned a different payment currency.');
    }
    if ($gatewayAmount >= 0 && abs($gatewayAmount - $expectedAmount) > 0.009) {
        throw new RuntimeException('ScayPay returned a different payment amount.');
    }

    $localStatus = ScayPay::localStatus($gatewayStatus);
    $gatewayLabel = strtoupper(trim((string) ($gatewayStatus['status'] ?? 'PENDING')));
    $pdo->beginTransaction();
    try {
        $savePayment = $pdo->prepare(
            'UPDATE company_registration_payments
             SET status = ?, gateway_status = ?, gateway_response = ?
             WHERE id = ?'
        );
        $savePayment->execute([
            $localStatus,
            $gatewayLabel,
            json_encode($gatewayStatus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int) $payment['id'],
        ]);
        $saveRegistration = $pdo->prepare(
            'UPDATE company_registrations
             SET payment_status = ?, payment_method = ?
             WHERE id = ?'
        );
        $saveRegistration->execute([
            $localStatus,
            trim((string) ($gatewayStatus['payment_type'] ?? $gatewayStatus['network'] ?? '')) ?: 'scaypay',
            (int) $payment['company_registration_id'],
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'registrationRef' => (string) $payment['registration_ref'],
        'planName' => (string) $payment['plan_name'],
        'paymentReference' => (string) $payment['merchant_reference'],
        'paymentStatus' => $localStatus,
        'gatewayStatus' => $gatewayLabel,
        'amount' => $expectedAmount,
        'currency' => (string) $payment['currency'],
        'checkoutUrl' => (string) ($payment['checkout_url'] ?? ''),
    ];
}
