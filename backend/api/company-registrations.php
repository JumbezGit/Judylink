<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../src/ScayPay.php';
require_once __DIR__ . '/../admin/includes/auth.php';

admin_ensure_company_registration_tables();

$pdo = Database::pdo();
$method = $_SERVER['REQUEST_METHOD'];

function clean_company_string(mixed $value): string
{
    return trim((string) $value);
}

function company_plans(): array
{
    return [
        'tours-travel' => ['name' => 'Tours & Travel', 'type' => 'Tour & Travel', 'reference' => 'TOURS', 'monthly' => 99000.00, 'setup' => 249000.00],
        'hotels-lodges' => ['name' => 'Hotels & Lodges', 'type' => 'Hotel / Lodge', 'reference' => 'HOTELS', 'monthly' => 129000.00, 'setup' => 299000.00],
        'stationery-printing' => ['name' => 'Stationery & Printing', 'type' => 'Stationery & Printing', 'reference' => 'STATIONERY', 'monthly' => 69000.00, 'setup' => 179000.00],
        'car-rental' => ['name' => 'Car Rental', 'type' => 'Car Rental', 'reference' => 'CARRENTAL', 'monthly' => 99000.00, 'setup' => 249000.00],
        'general-business' => ['name' => 'General Business', 'type' => 'General Business', 'reference' => 'GENERAL', 'monthly' => 79000.00, 'setup' => 199000.00],
    ];
}

function payment_return_url(string $registrationRef, string $paymentReference, bool $cancelled = false): string
{
    $base = defined('SCAYPAY_CALLBACK_URL') ? (string) SCAYPAY_CALLBACK_URL : APP_URL . '/plans';
    $separator = str_contains($base, '?') ? '&' : '?';
    return $base . $separator . http_build_query([
        'payment' => $cancelled ? 'cancelled' : 'return',
        'reference' => $registrationRef,
        'OrderMerchantReference' => $paymentReference,
    ]);
}

if ($method === 'POST') {
    $data = request_json();
    $required = ['companyName', 'ownerName', 'email', 'phone', 'username', 'password', 'planId'];
    foreach ($required as $field) {
        if (clean_company_string($data[$field] ?? '') === '') {
            Response::error("Missing required field: {$field}");
        }
    }

    $email = clean_company_string($data['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Valid email is required');
    }

    $username = strtolower(clean_company_string($data['username']));
    $password = (string) $data['password'];
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{3,49}$/', $username)) {
        Response::error('Username must be 4-50 characters using letters, numbers, dots, hyphens, or underscores');
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        Response::error('Password must be between 8 and 72 characters');
    }

    $usernameCheck = $pdo->prepare(
        'SELECT 1 FROM admin_users WHERE username = ?
         UNION ALL
         SELECT 1 FROM company_registrations WHERE requested_username = ?
         LIMIT 1'
    );
    $usernameCheck->execute([$username, $username]);
    if ($usernameCheck->fetchColumn()) {
        Response::error('That username is already taken');
    }

    $planId = clean_company_string($data['planId']);
    $plans = company_plans();
    if (!isset($plans[$planId])) {
        Response::error('Please choose a valid business system');
    }
    $plan = $plans[$planId];
    $monthlyPrice = (float) $plan['monthly'];
    $setupFee = (float) $plan['setup'];
    $firstPayment = $monthlyPrice + $setupFee;

    $companyName = clean_company_string($data['companyName']);
    $registrationRef = sprintf(
        'JUDY-%s-PAY-%06d',
        (string) $plan['reference'],
        random_int(100000, 999999)
    );
    $paymentReference = $registrationRef;
    $stmt = $pdo->prepare(
        'INSERT INTO company_registrations (
            registration_ref, company_name, owner_name, email, phone, requested_username, requested_password_hash, website_type,
            plan_id, plan_name, monthly_price, setup_fee, first_payment, currency,
            payment_method, payment_status, status, ip_address, user_agent
        ) VALUES (
            :registration_ref, :company_name, :owner_name, :email, :phone, :requested_username, :requested_password_hash, :website_type,
            :plan_id, :plan_name, :monthly_price, :setup_fee, :first_payment, :currency,
            :payment_method, :payment_status, :status, :ip_address, :user_agent
        )'
    );
    $pdo->beginTransaction();
    try {
        $stmt->execute([
            'registration_ref' => $registrationRef,
            'company_name' => $companyName,
            'owner_name' => clean_company_string($data['ownerName']),
            'email' => $email,
            'phone' => clean_company_string($data['phone']),
            'requested_username' => $username,
            'requested_password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'website_type' => (string) $plan['type'],
            'plan_id' => $planId,
            'plan_name' => (string) $plan['name'],
            'monthly_price' => $monthlyPrice,
            'setup_fee' => $setupFee,
            'first_payment' => $firstPayment,
            'currency' => 'TZS',
            'payment_method' => 'scaypay',
            'payment_status' => 'pending',
            'status' => 'pending',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        $registrationId = (int) $pdo->lastInsertId();

        $payment = $pdo->prepare(
            'INSERT INTO company_registration_payments
             (company_registration_id, merchant_reference, amount, currency, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $payment->execute([$registrationId, $paymentReference, $firstPayment, 'TZS', 'pending']);

        $nameParts = preg_split('/\s+/', clean_company_string($data['ownerName']), 2) ?: ['Customer'];
        $checkout = (new ScayPay())->createCheckout([
            'reference' => $paymentReference,
            'currency' => 'TZS',
            'amount' => $firstPayment,
            'description' => 'Judylink - ' . (string) $plan['name'],
            'callback_url' => payment_return_url($registrationRef, $paymentReference),
            'cancellation_url' => payment_return_url($registrationRef, $paymentReference, true),
            'email' => $email,
            'phone' => clean_company_string($data['phone']),
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? '-',
            'company_name' => $companyName,
        ]);
        $checkoutUrl = trim((string) $checkout['redirect_url']);
        $trackingId = trim((string) $checkout['order_tracking_id']);
        $initialPaymentStatus = ScayPay::localStatus(['status' => $checkout['status'] ?? 'pending']);
        $saveCheckout = $pdo->prepare(
            'UPDATE company_registration_payments
             SET gateway_tracking_id = ?, checkout_url = ?, status = ?, gateway_status = ?, gateway_response = ?
             WHERE merchant_reference = ?'
        );
        $saveCheckout->execute([
            $trackingId,
            $checkoutUrl,
            $initialPaymentStatus,
            strtoupper((string) ($checkout['status'] ?? 'pending')),
            json_encode($checkout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $paymentReference,
        ]);
        if ($initialPaymentStatus === 'paid') {
            $markRegistrationPaid = $pdo->prepare("UPDATE company_registrations SET payment_status = 'paid', payment_method = 'card' WHERE id = ?");
            $markRegistrationPaid->execute([$registrationId]);
        }
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            Response::error('That username is already taken');
        }
        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Response::error($exception->getMessage(), 502);
    }

    Response::json([
        'ok' => true,
        'id' => $registrationId,
        'registrationRef' => $registrationRef,
        'status' => 'pending',
        'checkoutUrl' => $checkoutUrl,
        'paymentReference' => $paymentReference,
    ], 201);
}

if ($method === 'GET') {
    require_admin();

    if (!empty($_GET['summary']) || strtolower((string) ($_SERVER['HTTP_X_ADMIN_CONTEXT'] ?? '')) === 'dashboard') {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM company_registrations');
        $pendingStmt = $pdo->query("SELECT COUNT(*) FROM company_registrations WHERE status = 'pending'");
        $latestStmt = $pdo->query(
            'SELECT registration_ref, company_name, plan_name, status, payment_status
             FROM company_registrations
             ORDER BY created_at DESC
             LIMIT 5'
        );
        Response::json([
            'ok' => true,
            'count' => (int) $countStmt->fetchColumn(),
            'pending' => (int) $pendingStmt->fetchColumn(),
            'latest' => $latestStmt->fetchAll(),
        ]);
    }

    $stmt = $pdo->query(
        'SELECT company_registrations.*, admin_users.username AS approved_by_username
         FROM company_registrations
         LEFT JOIN admin_users ON admin_users.id = company_registrations.approved_by
         ORDER BY FIELD(company_registrations.status, "pending", "approved", "rejected"), company_registrations.created_at DESC
         LIMIT 500'
    );
    $registrations = $stmt->fetchAll();
    foreach ($registrations as &$registration) {
        unset($registration['requested_password_hash']);
    }
    unset($registration);
    Response::json(['ok' => true, 'registrations' => $registrations]);
}

if ($method === 'PATCH') {
    require_admin();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Company registration id is required');
    }

    $data = request_json();
    $status = clean_company_string($data['status'] ?? '');
    $paymentStatus = clean_company_string($data['paymentStatus'] ?? '');
    $adminNote = array_key_exists('adminNote', $data) ? clean_company_string($data['adminNote']) : null;

    if ($status !== '' && !in_array($status, ['pending', 'approved', 'rejected'], true)) {
        Response::error('Invalid company status');
    }
    if ($status === 'approved') {
        Response::error('Approve companies from the Companies page so the registered login is activated');
    }
    if ($paymentStatus !== '' && !in_array($paymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
        Response::error('Invalid payment status');
    }

    $currentUser = admin_current_user();
    $stmt = $pdo->prepare(
        'UPDATE company_registrations
         SET status = COALESCE(:status, status),
             payment_status = COALESCE(:payment_status, payment_status),
             admin_note = COALESCE(:admin_note, admin_note),
             approved_by = CASE WHEN :status_for_approval = "approved" THEN :approved_by ELSE approved_by END,
             approved_at = CASE WHEN :status_for_approved_at = "approved" THEN NOW() ELSE approved_at END,
             rejected_at = CASE WHEN :status_for_rejected_at = "rejected" THEN NOW() ELSE rejected_at END,
             requested_password_hash = CASE WHEN :status_for_password_cleanup = "rejected" THEN NULL ELSE requested_password_hash END
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'status' => $status !== '' ? $status : null,
        'payment_status' => $paymentStatus !== '' ? $paymentStatus : null,
        'admin_note' => $adminNote,
        'status_for_approval' => $status,
        'status_for_approved_at' => $status,
        'status_for_rejected_at' => $status,
        'status_for_password_cleanup' => $status,
        'approved_by' => $currentUser['id'] ?? null,
    ]);

    Response::json(['ok' => true, 'status' => $status ?: null, 'paymentStatus' => $paymentStatus ?: null]);
}

Response::error('Method not allowed', 405);
