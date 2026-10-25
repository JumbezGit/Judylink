<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin_page('dashboard');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Admin token required']);
    exit;
}

if (!admin_is_super_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Super admin only']);
    exit;
}

$siteId = (int) ($_POST['site_id'] ?? 0);
$site = admin_set_selected_site($siteId);

if ($siteId > 0 && !$site) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Site not found']);
    exit;
}

echo json_encode([
    'ok' => true,
    'site' => $site,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
