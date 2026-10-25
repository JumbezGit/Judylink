<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/scaypay-client.php';

require_method('POST');
$input = request_json();

$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phoneDigits = preg_replace('/\D/', '', (string) ($input['phone'] ?? ''));
$phone = $phoneDigits === '' ? '' : '+' . $phoneDigits;
$phoneDialCode = preg_replace('/\D/', '', (string) ($input['phoneDialCode'] ?? ''));
$countryCode = strtoupper(trim((string) ($input['countryCode'] ?? 'TZ')));
$state = strtoupper(trim((string) ($input['state'] ?? 'ZNZ')));
$pickup = trim((string) ($input['pickup'] ?? ''));
$dropoff = trim((string) ($input['dropoff'] ?? ''));
$travelDate = trim((string) ($input['date'] ?? ''));
$travelTime = trim((string) ($input['time'] ?? ''));
$passengers = filter_var($input['passengers'] ?? null, FILTER_VALIDATE_INT);
$specialRequests = trim((string) ($input['specialRequests'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    json_response(['message' => 'A valid passenger name is required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    json_response(['message' => 'A valid email address is required.'], 422);
}
if (strlen($phone) < 10 || strlen($phone) > 30 || strlen($phoneDigits) < 6) {
    json_response(['message' => 'A valid phone number is required.'], 422);
}
if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
    json_response(['message' => 'A valid phone country is required.'], 422);
}
if (!preg_match('/^[A-Z0-9 -]{2,20}$/', $state)) {
    json_response(['message' => 'A valid state or region is required.'], 422);
}
if (mb_strlen($pickup) < 2 || mb_strlen($pickup) > 190 || mb_strlen($dropoff) < 2 || mb_strlen($dropoff) > 190) {
    json_response(['message' => 'Valid pickup and drop-off locations are required.'], 422);
}
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDate);
if (!$date || $date->format('Y-m-d') !== $travelDate || $date < new DateTimeImmutable('today')) {
    json_response(['message' => 'Please select a valid future transfer date.'], 422);
}
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $travelTime)) {
    json_response(['message' => 'Please select a valid transfer time.'], 422);
}
if ($passengers === false || $passengers < 1 || $passengers > 100) {
    json_response(['message' => 'Passenger count must be between 1 and 100.'], 422);
}
if (mb_strlen($specialRequests) > 2000) {
    json_response(['message' => 'Special requests are too long.'], 422);
}

$buses = intdiv($passengers, 20);
$remaining = $passengers % 20;
$amount = $buses * 150;
$vehicleParts = [];
if ($buses > 0) {
    $vehicleParts[] = $buses . ' ' . ($buses === 1 ? 'Bus' : 'Buses');
}
if ($remaining > 0) {
    if ($remaining <= 6) {
        $amount += $remaining <= 4 ? 45 : ($remaining === 5 ? 55 : 65);
        $vehicleParts[] = 'Van';
    } else {
        $amount += 150;
        $vehicleParts[] = 'Bus';
    }
}
if ($passengers <= 20) {
    $amount = $passengers <= 4 ? 45 : ($passengers === 5 ? 55 : ($passengers === 6 ? 65 : 150));
    $vehicleParts = [$passengers <= 6 ? 'Van' : 'Bus'];
}
$vehicle = implode(' + ', $vehicleParts);

$nameParts = preg_split('/\s+/', $name, 2) ?: [$name];
$firstName = $nameParts[0];
$lastName = $nameParts[1] ?? '-';
$amountUsd = (float) $amount;
$currency = $config['scaypay']['currency'];
$amount = scaypay_payable_amount($config, $amountUsd);
$reference = 'ZIO-TRANSFER-' . strtoupper(bin2hex(random_bytes(5)));
$routeName = mb_substr('Transfer: ' . $pickup . ' to ' . $dropoff, 0, 190);
$details = "Date: {$travelDate} {$travelTime}\nPickup: {$pickup}\nDrop-off: {$dropoff}\nVehicle: {$vehicle}";
if ($specialRequests !== '') {
    $details .= "\nRequests: {$specialRequests}";
}

$pdo = db($config);
$pdo->beginTransaction();
$result = null;
try {
    $booking = $pdo->prepare('INSERT INTO bookings (booking_reference, tour_id, tour_name, tour_type, customer_name, customer_email, customer_phone, adults, children, special_requests, total_amount, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $booking->execute([$reference, 'airport-transfer', $routeName, 'private', $name, $email, $phone, $passengers, 0, $details, $amount, $currency]);
    $bookingId = (int) $pdo->lastInsertId();
    $payment = $pdo->prepare('INSERT INTO payments (booking_id, merchant_reference, amount, currency) VALUES (?, ?, ?, ?)');
    $payment->execute([$bookingId, $reference, $amount, $currency]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

try {
    $result = scaypay_create_card_payment($config, [
        'payment_type' => 'card',
        'amount' => $amount,
        'currency' => $currency,
        'phone' => $phone,
        'customer' => [
            'name' => $name,
            'email' => $email,
            'address' => $pickup,
            'city' => 'Zanzibar',
            'state' => $state,
            'postcode' => '71000',
            'country' => $countryCode,
        ],
        'redirect_url' => $config['app_url'] . '/booking/payment-embed-callback?OrderMerchantReference=' . rawurlencode($reference),
        'cancel_url' => $config['app_url'] . '/transfer',
        'reference' => $reference,
        'webhook_url' => $config['app_url'] . '/api/scaypay/webhook',
    ], $reference);
    $update = $pdo->prepare('UPDATE payments SET scaypay_payment_id = ?, raw_response = ? WHERE merchant_reference = ?');
    $update->execute([$result['id'], json_encode([
        'provider' => 'scaypay',
        'original_usd_amount' => $amountUsd,
        'response' => $result,
    ], JSON_UNESCAPED_SLASHES), $reference]);
    $callbackUrl = $config['app_url'] . '/booking/payment-embed-callback?OrderTrackingId=' . rawurlencode((string) $result['id']) . '&OrderMerchantReference=' . rawurlencode($reference);
    json_response([
        'merchant_reference' => $reference,
        'order_tracking_id' => $result['id'],
        'redirect_url' => $result['checkout_url'] ?? $callbackUrl,
        'amount' => $amount,
        'currency' => $currency,
        'vehicle' => $vehicle,
    ]);
} catch (Throwable $error) {
    $failed = $pdo->prepare("UPDATE payments SET status = 'failed', raw_response = ? WHERE merchant_reference = ?");
    $failed->execute([json_encode(['error' => $error->getMessage(), 'scaypay_response' => $result], JSON_UNESCAPED_SLASHES), $reference]);
    throw $error;
}
