<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/scaypay-client.php';

require_method('POST');
$input = request_json();
$prices = require dirname(__DIR__) . '/prices.php';

$tourId = trim((string) ($input['tourId'] ?? ''));
$tourName = trim((string) ($input['tourName'] ?? ''));
$tourType = (string) ($input['tourType'] ?? 'private');
$firstName = trim((string) ($input['firstName'] ?? ''));
$lastName = trim((string) ($input['lastName'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);
$email = trim((string) ($input['email'] ?? ''));
$countryCode = strtoupper(trim((string) ($input['countryCode'] ?? 'TZ')));
$state = strtoupper(trim((string) ($input['state'] ?? 'ZNZ')));
$phoneDigits = preg_replace('/\D/', '', (string) ($input['phone'] ?? ''));
$phone = $phoneDigits === '' ? '' : '+' . $phoneDigits;
$phoneDialCode = preg_replace('/\D/', '', (string) ($input['phoneDialCode'] ?? ''));
$address = trim((string) ($input['address'] ?? ''));
$city = trim((string) ($input['city'] ?? ''));
$postalCode = trim((string) ($input['postalCode'] ?? ''));
$bookingDate = trim((string) ($input['bookingDate'] ?? ''));
$adults = filter_var($input['adults'] ?? null, FILTER_VALIDATE_INT);
$children = filter_var($input['children'] ?? 0, FILTER_VALIDATE_INT);
$specialRequests = trim((string) ($input['specialRequests'] ?? ''));
$paymentMethod = strtolower(trim((string) ($input['paymentMethod'] ?? 'card')));
if (!in_array($paymentMethod, ['card', 'mobile'], true)) {
    $paymentMethod = 'card';
}

if (!isset($prices[$tourId][$tourType])) {
    json_response(['message' => 'This tour or tour type cannot currently be paid online.'], 422);
}
if ($tourName === '' || mb_strlen($tourName) > 190 || mb_strlen($firstName) < 2 || mb_strlen($firstName) > 75 || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 75) {
    json_response(['message' => 'A valid tour and customer name are required.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    json_response(['message' => 'A valid email address is required.'], 422);
}
if (strlen($phone) < 10 || strlen($phone) > 30) {
    json_response(['message' => 'A valid phone number is required.'], 422);
}
if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
    json_response(['message' => 'Please select a valid billing country.'], 422);
}
if (!preg_match('/^[A-Z0-9 -]{2,20}$/', $state)) {
    json_response(['message' => 'Please enter a valid billing state or region.'], 422);
}
if (mb_strlen($address) < 3 || mb_strlen($address) > 190 || mb_strlen($city) < 2 || mb_strlen($city) > 100 || mb_strlen($postalCode) < 2 || mb_strlen($postalCode) > 20) {
    json_response(['message' => 'A valid street address, city, and postal code are required.'], 422);
}
$parsedBookingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
if (!$parsedBookingDate || $parsedBookingDate->format('Y-m-d') !== $bookingDate || $parsedBookingDate <= new DateTimeImmutable('today')) {
    json_response(['message' => 'Please select a booking date after today.'], 422);
}
if ($adults === false || $adults < 1 || $adults > 30 || $children === false || $children < 0 || $children > 30) {
    json_response(['message' => 'Invalid passenger count.'], 422);
}
if (mb_strlen($specialRequests) > 2000) {
    json_response(['message' => 'Special requests are too long.'], 422);
}

[$singleAdultPrice, $adultPrice, $childPrice] = $prices[$tourId][$tourType];
$adultUnitPrice = $adults === 1 ? $singleAdultPrice : $adultPrice;
$amountUsd = round(($adults * $adultUnitPrice) + ($children * $childPrice), 2);
$currency = $config['scaypay']['currency'];
$amount = scaypay_payable_amount($config, $amountUsd);
if ($amount <= 0) {
    json_response(['message' => 'The calculated booking amount is invalid.'], 422);
}

$reference = 'ZIO-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $tourId), 0, 12)) . '-' . strtoupper(bin2hex(random_bytes(5)));
$pdo = db($config);

$pdo->beginTransaction();
$result = null;
try {
    $booking = $pdo->prepare('INSERT INTO bookings (booking_reference, tour_id, tour_name, tour_type, booking_date, customer_name, customer_email, customer_phone, adults, children, special_requests, total_amount, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $booking->execute([$reference, $tourId, $tourName, $tourType, $bookingDate, $fullName, $email, $phone, $adults, $children, $specialRequests ?: null, $amount, $currency]);
    $bookingId = (int) $pdo->lastInsertId();
    $payment = $pdo->prepare('INSERT INTO payments (booking_id, merchant_reference, amount, currency) VALUES (?, ?, ?, ?)');
    $payment->execute([$bookingId, $reference, $amount, $currency]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

try {
    if ($paymentMethod === 'mobile') {
        $result = scaypay_create_mobile_payment($config, [
            'payment_type' => 'mobile',
            'amount' => $amount,
            'currency' => $currency,
            'phone' => $phone,
            'customer' => [
                'name' => $fullName,
                'email' => $email,
            ],
            'reference' => $reference,
            'webhook_url' => $config['app_url'] . '/api/scaypay/webhook',
        ], $reference);
    } else {
        $result = scaypay_create_card_payment($config, [
            'payment_type' => 'card',
            'amount' => $amount,
            'currency' => $currency,
            'phone' => $phone,
            'customer' => [
                'name' => $fullName,
                'email' => $email,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'postcode' => $postalCode,
                'country' => $countryCode,
            ],
            'redirect_url' => $config['app_url'] . '/booking/payment-embed-callback?OrderMerchantReference=' . rawurlencode($reference),
            'cancel_url' => $config['app_url'] . '/booking',
            'reference' => $reference,
            'webhook_url' => $config['app_url'] . '/api/scaypay/webhook',
        ], $reference);
    }
    $update = $pdo->prepare('UPDATE payments SET scaypay_payment_id = ?, payment_method = ?, raw_response = ? WHERE merchant_reference = ?');
    $update->execute([$result['id'], $paymentMethod, json_encode([
        'provider' => 'scaypay',
        'payment_method' => $paymentMethod,
        'original_usd_amount' => $amountUsd,
        'response' => $result,
    ], JSON_UNESCAPED_SLASHES), $reference]);
    $callbackUrl = $config['app_url'] . '/booking/payment-embed-callback?OrderTrackingId=' . rawurlencode((string) $result['id']) . '&OrderMerchantReference=' . rawurlencode($reference);
    json_response([
        'merchant_reference' => $reference,
        'order_tracking_id' => $result['id'],
        'redirect_url' => $result['checkout_url'] ?? $callbackUrl,
        'payment_method' => $paymentMethod,
        'amount' => $amount,
        'currency' => $currency,
    ]);
} catch (Throwable $error) {
    $failed = $pdo->prepare("UPDATE payments SET status = 'failed', raw_response = ? WHERE merchant_reference = ?");
    $failed->execute([json_encode([
        'error' => $error->getMessage(),
        'scaypay_response' => $result,
    ], JSON_UNESCAPED_SLASHES), $reference]);
    throw $error;
}
