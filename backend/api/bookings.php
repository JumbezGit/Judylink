<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';

$pdo = Database::pdo();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $siteId = request_site_id();
    $data = request_json();
    $required = ['tourSlug', 'tourName', 'date', 'time', 'firstName', 'lastName', 'email', 'phone', 'nationality'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("Missing required field: {$field}");
        }
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Valid email is required');
    }
    if (empty($data['agreedTerms']) || empty($data['agreedCancellation'])) {
        Response::error('Terms and cancellation policy must be accepted');
    }

    $tourStmt = $pdo->prepare('SELECT id FROM tours WHERE site_id = ? AND slug = ? LIMIT 1');
    $tourStmt->execute([$siteId, (string) $data['tourSlug']]);
    $tourId = $tourStmt->fetchColumn() ?: null;

    $bookingRef = 'HYT-' . date('Y') . '-' . random_int(10000, 99999);
    $stmt = $pdo->prepare(
        'INSERT INTO bookings (
            site_id, booking_ref, tour_id, tour_slug, tour_name, travel_date, start_time, adults, children,
            pickup, pickup_location, first_name, last_name, email, phone, nationality, country,
            contact_method, special_requests, promo_code, payment_method, subtotal, discount,
            service_fee, total, currency, agreed_terms, agreed_cancellation, payment_status,
            ip_address, user_agent
        ) VALUES (
            :site_id, :booking_ref, :tour_id, :tour_slug, :tour_name, :travel_date, :start_time, :adults, :children,
            :pickup, :pickup_location, :first_name, :last_name, :email, :phone, :nationality, :country,
            :contact_method, :special_requests, :promo_code, :payment_method, :subtotal, :discount,
            :service_fee, :total, :currency, :agreed_terms, :agreed_cancellation, :payment_status,
            :ip_address, :user_agent
        )'
    );

    $stmt->execute([
        'site_id' => $siteId,
        'booking_ref' => $bookingRef,
        'tour_id' => $tourId,
        'tour_slug' => $data['tourSlug'],
        'tour_name' => $data['tourName'],
        'travel_date' => $data['date'],
        'start_time' => $data['time'],
        'adults' => (int) ($data['adults'] ?? 1),
        'children' => (int) ($data['children'] ?? 0),
        'pickup' => as_bool($data['pickup'] ?? true),
        'pickup_location' => $data['pickupLocation'] ?? null,
        'first_name' => trim((string) $data['firstName']),
        'last_name' => trim((string) $data['lastName']),
        'email' => trim((string) $data['email']),
        'phone' => trim((string) $data['phone']),
        'nationality' => trim((string) $data['nationality']),
        'country' => $data['country'] ?? null,
        'contact_method' => $data['contactMethod'] ?? 'email',
        'special_requests' => $data['specialRequests'] ?? null,
        'promo_code' => $data['promoCode'] ?? null,
        'payment_method' => $data['paymentMethod'] ?? 'later',
        'subtotal' => (float) ($data['subtotal'] ?? 0),
        'discount' => (float) ($data['discount'] ?? 0),
        'service_fee' => (float) ($data['serviceFee'] ?? 0),
        'total' => (float) ($data['total'] ?? 0),
        'currency' => $data['currency'] ?? 'USD',
        'agreed_terms' => as_bool($data['agreedTerms']),
        'agreed_cancellation' => as_bool($data['agreedCancellation']),
        'payment_status' => ($data['paymentMethod'] ?? 'later') === 'later' ? 'pending' : 'paid',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    Response::json(['ok' => true, 'bookingRef' => $bookingRef, 'id' => (int) $pdo->lastInsertId()], 201);
}

if ($method === 'GET') {
    require_admin();
    $siteId = request_site_id();
    if (!empty($_GET['summary']) || strtolower((string) ($_SERVER['HTTP_X_ADMIN_CONTEXT'] ?? '')) === 'dashboard') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE site_id = ?');
        $countStmt->execute([$siteId]);
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE site_id = ? AND (status = 'new' OR payment_status = 'pending')");
        $pendingStmt->execute([$siteId]);
        $latestStmt = $pdo->prepare(
            'SELECT booking_ref, first_name, last_name, tour_name, status
             FROM bookings
             WHERE site_id = ?
             ORDER BY created_at DESC
             LIMIT 5'
        );
        $latestStmt->execute([$siteId]);
        Response::json([
            'ok' => true,
            'count' => (int) $countStmt->fetchColumn(),
            'pending' => (int) $pendingStmt->fetchColumn(),
            'latest' => $latestStmt->fetchAll(),
        ]);
    }

    $stmt = $pdo->prepare(
        'SELECT bookings.*, tours.image_path AS tour_image_path
         FROM bookings
         LEFT JOIN tours ON tours.site_id = bookings.site_id AND (tours.id = bookings.tour_id OR tours.slug = bookings.tour_slug)
         WHERE bookings.site_id = ?
         ORDER BY bookings.created_at DESC
         LIMIT 300'
    );
    $stmt->execute([$siteId]);
    Response::json(['ok' => true, 'bookings' => $stmt->fetchAll()]);
}

if ($method === 'PATCH') {
    require_admin();
    $siteId = request_site_id();
    $id = (int) ($_GET['id'] ?? 0);
    $data = request_json();
    if ($id < 1) {
        Response::error('Booking id is required');
    }
    $stmt = $pdo->prepare('UPDATE bookings SET status = COALESCE(:status, status), payment_status = COALESCE(:payment_status, payment_status) WHERE id = :id AND site_id = :site_id');
    $stmt->execute([
        'id' => $id,
        'site_id' => $siteId,
        'status' => $data['status'] ?? null,
        'payment_status' => $data['paymentStatus'] ?? null,
    ]);
    Response::json(['ok' => true]);
}

Response::error('Method not allowed', 405);
