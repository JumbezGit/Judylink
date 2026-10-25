<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Bootstrap.php';
require_once __DIR__ . '/../admin/includes/auth.php';

admin_ensure_company_registration_tables();

function site_api_key(string $value, string $label): string
{
    $value = strtolower(trim($value));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $value)) {
        Response::error("Invalid {$label}. Use letters, numbers, hyphens, or underscores.");
    }
    return $value;
}

function site_api_payload(mixed $value): string
{
    if (!is_array($value)) {
        Response::error('The data field must be a JSON object or array.');
    }
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || strlen($encoded) > 1024 * 1024) {
        Response::error('Data must be valid JSON and no larger than 1 MB.');
    }
    return $encoded;
}

function site_api_request_key(): string
{
    $key = trim((string) ($_SERVER['HTTP_X_SITE_API_KEY'] ?? ''));
    if ($key !== '') {
        return $key;
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'X-Site-API-Key') === 0) {
                return trim((string) $value);
            }
        }
    }

    return '';
}

function site_api_record(array $row): array
{
    return [
        'key' => (string) $row['record_key'],
        'data' => json_decode((string) $row['payload'], true),
        'createdAt' => (string) $row['created_at'],
        'updatedAt' => (string) $row['updated_at'],
    ];
}

function site_business_record(array $row): array
{
    return [
        'key' => (string) $row['record_ref'],
        'data' => [
            'title' => (string) $row['title'],
            'status' => (string) $row['status'],
            'amount' => $row['amount'] === null ? null : (float) $row['amount'],
            'currency' => (string) $row['currency'],
            'details' => (string) ($row['details'] ?? ''),
            'fields' => json_decode((string) ($row['payload'] ?? 'null'), true),
        ],
        'createdAt' => (string) $row['created_at'],
        'updatedAt' => (string) $row['updated_at'],
    ];
}

function site_api_log(PDO $pdo, int $siteId, string $action, ?string $collection = null, ?string $recordKey = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO site_api_activity (site_id, action_key, collection_key, record_key, request_method, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $siteId,
        $action,
        $collection,
        $recordKey,
        substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10),
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);
}

$pdo = Database::pdo();
$siteSlug = site_api_key((string) ($_GET['site'] ?? ''), 'site');
$providedKey = site_api_request_key();

$siteStmt = $pdo->prepare('SELECT id, site_ref, site_name, site_slug, api_key, plan_id, plan_name, business_type, status, setup_status FROM company_sites WHERE site_slug = ? LIMIT 1');
$siteStmt->execute([$siteSlug]);
$site = $siteStmt->fetch();

if (!is_array($site)) {
    Response::error('Invalid site or API key.', 401);
}
if ($providedKey === '') {
    Response::error('Site API key required. Send it in the X-Site-API-Key header.', 401);
}
if (!hash_equals((string) ($site['api_key'] ?? ''), $providedKey)) {
    Response::error('Invalid site or API key.', 401);
}
if ((string) $site['status'] !== 'active') {
    site_api_log($pdo, (int) $site['id'], 'blocked_inactive_site');
    Response::error('This site API is not active.', 403);
}
$businessProfile = admin_business_profile((string) $site['business_type']);

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$collectionInput = (string) ($_GET['collection'] ?? '');

if ($collectionInput === '') {
    if ($method !== 'GET') {
        Response::error('Collection is required.');
    }
    $collections = $pdo->prepare(
        'SELECT collection_key, COUNT(*) AS record_count, MAX(updated_at) AS updated_at
         FROM site_api_records WHERE site_id = ? GROUP BY collection_key ORDER BY collection_key'
    );
    $collections->execute([(int) $site['id']]);
    $features = $pdo->prepare(
        'SELECT feature_key, label, icon, handler, route, sort_order
         FROM site_features WHERE site_id = ? AND is_enabled = 1 ORDER BY sort_order, feature_key'
    );
    $features->execute([(int) $site['id']]);
    $navigation = array_map(static fn (array $row): array => [
        'key' => (string) $row['feature_key'],
        'label' => (string) $row['label'],
        'icon' => (string) $row['icon'],
        'type' => (string) $row['handler'],
        'route' => $row['route'],
    ], $features->fetchAll());
    site_api_log($pdo, (int) $site['id'], 'list_collections');
    Response::json([
        'ok' => true,
        'site' => [
            'ref' => $site['site_ref'],
            'name' => $site['site_name'],
            'slug' => $site['site_slug'],
            'businessType' => $site['business_type'],
            'businessTypeName' => $businessProfile['label'],
        ],
        'navigation' => $navigation,
        'collections' => array_map(static fn (array $row): array => [
            'name' => (string) $row['collection_key'],
            'recordCount' => (int) $row['record_count'],
            'updatedAt' => $row['updated_at'],
        ], $collections->fetchAll()),
    ]);
}

$collection = site_api_key($collectionInput, 'collection');
$recordInput = (string) ($_GET['key'] ?? '');
$recordKey = $recordInput !== '' ? site_api_key($recordInput, 'record key') : '';

$featureStmt = $pdo->prepare(
    "SELECT feature_key, label FROM site_features
     WHERE site_id = ? AND feature_key = ? AND handler = 'records' AND is_enabled = 1 LIMIT 1"
);
$featureStmt->execute([(int) $site['id'], $collection]);
$businessFeature = $featureStmt->fetch();

if (is_array($businessFeature)) {
    if ($method === 'GET') {
        if ($recordKey !== '') {
            $stmt = $pdo->prepare('SELECT * FROM business_records WHERE site_id = ? AND feature_key = ? AND record_ref = ? LIMIT 1');
            $stmt->execute([(int) $site['id'], $collection, $recordKey]);
            $record = $stmt->fetch();
            if (!is_array($record)) {
                site_api_log($pdo, (int) $site['id'], 'record_not_found', $collection, $recordKey);
                Response::error('Record not found.', 404);
            }
            site_api_log($pdo, (int) $site['id'], 'read_business_record', $collection, $recordKey);
            Response::json(['ok' => true, 'site' => $siteSlug, 'collection' => $collection, 'record' => site_business_record($record)]);
        }

        $stmt = $pdo->prepare('SELECT * FROM business_records WHERE site_id = ? AND feature_key = ? ORDER BY updated_at DESC, id DESC LIMIT 500');
        $stmt->execute([(int) $site['id'], $collection]);
        site_api_log($pdo, (int) $site['id'], 'list_business_records', $collection);
        Response::json([
            'ok' => true,
            'site' => $siteSlug,
            'collection' => $collection,
            'label' => (string) $businessFeature['label'],
            'records' => array_map('site_business_record', $stmt->fetchAll()),
        ]);
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $body = request_json();
        $data = $body['data'] ?? null;
        if (!is_array($data)) {
            Response::error('The data field must be a JSON object.');
        }
        $bodyKey = (string) ($body['key'] ?? '');
        $recordKey = $recordKey !== '' ? $recordKey : ($bodyKey !== '' ? site_api_key($bodyKey, 'record key') : bin2hex(random_bytes(8)));
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            Response::error('The data.title field is required.');
        }
        $status = trim((string) ($data['status'] ?? 'active')) ?: 'active';
        $amount = ($data['amount'] ?? '') === '' || ($data['amount'] ?? null) === null ? null : (float) $data['amount'];
        $details = trim((string) ($data['details'] ?? ''));
        $fields = $data['fields'] ?? null;
        $payload = $fields === null ? null : site_api_payload($fields);
        $stmt = $pdo->prepare(
            'INSERT INTO business_records (site_id, feature_key, record_ref, title, status, amount, currency, details, payload)
             VALUES (?, ?, ?, ?, ?, ?, "TZS", ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), amount = VALUES(amount),
                 details = VALUES(details), payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([(int) $site['id'], $collection, $recordKey, $title, $status, $amount, $details, $payload]);
        site_api_log($pdo, (int) $site['id'], $method === 'POST' ? 'create_business_record' : 'update_business_record', $collection, $recordKey);
        Response::json(['ok' => true, 'site' => $siteSlug, 'collection' => $collection, 'key' => $recordKey], $method === 'POST' ? 201 : 200);
    }

    if ($method === 'DELETE') {
        if ($recordKey === '') {
            Response::error('Record key is required.');
        }
        $stmt = $pdo->prepare('DELETE FROM business_records WHERE site_id = ? AND feature_key = ? AND record_ref = ?');
        $stmt->execute([(int) $site['id'], $collection, $recordKey]);
        if ($stmt->rowCount() < 1) {
            Response::error('Record not found.', 404);
        }
        site_api_log($pdo, (int) $site['id'], 'delete_business_record', $collection, $recordKey);
        Response::json(['ok' => true, 'deleted' => $recordKey]);
    }
}

if ($method === 'GET') {
    if ($recordKey !== '') {
        $stmt = $pdo->prepare('SELECT * FROM site_api_records WHERE site_id = ? AND collection_key = ? AND record_key = ? LIMIT 1');
        $stmt->execute([(int) $site['id'], $collection, $recordKey]);
        $record = $stmt->fetch();
        if (!is_array($record)) {
            site_api_log($pdo, (int) $site['id'], 'record_not_found', $collection, $recordKey);
            Response::error('Record not found.', 404);
        }
        site_api_log($pdo, (int) $site['id'], 'read_record', $collection, $recordKey);
        Response::json(['ok' => true, 'site' => $siteSlug, 'collection' => $collection, 'record' => site_api_record($record)]);
    }

    $stmt = $pdo->prepare('SELECT * FROM site_api_records WHERE site_id = ? AND collection_key = ? ORDER BY updated_at DESC, id DESC LIMIT 500');
    $stmt->execute([(int) $site['id'], $collection]);
    site_api_log($pdo, (int) $site['id'], 'list_records', $collection);
    Response::json([
        'ok' => true,
        'site' => $siteSlug,
        'collection' => $collection,
        'records' => array_map('site_api_record', $stmt->fetchAll()),
    ]);
}

if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $body = request_json();
    $bodyKey = (string) ($body['key'] ?? '');
    $recordKey = $recordKey !== '' ? $recordKey : ($bodyKey !== '' ? site_api_key($bodyKey, 'record key') : bin2hex(random_bytes(8)));
    $payload = site_api_payload($body['data'] ?? null);
    $stmt = $pdo->prepare(
        'INSERT INTO site_api_records (site_id, collection_key, record_key, payload)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([(int) $site['id'], $collection, $recordKey, $payload]);
    site_api_log($pdo, (int) $site['id'], $method === 'POST' ? 'create_record' : 'update_record', $collection, $recordKey);
    $status = $method === 'POST' ? 201 : 200;
    Response::json(['ok' => true, 'site' => $siteSlug, 'collection' => $collection, 'key' => $recordKey], $status);
}

if ($method === 'DELETE') {
    if ($recordKey === '') {
        Response::error('Record key is required.');
    }
    $stmt = $pdo->prepare('DELETE FROM site_api_records WHERE site_id = ? AND collection_key = ? AND record_key = ?');
    $stmt->execute([(int) $site['id'], $collection, $recordKey]);
    if ($stmt->rowCount() < 1) {
        site_api_log($pdo, (int) $site['id'], 'delete_not_found', $collection, $recordKey);
        Response::error('Record not found.', 404);
    }
    site_api_log($pdo, (int) $site['id'], 'delete_record', $collection, $recordKey);
    Response::json(['ok' => true, 'deleted' => $recordKey]);
}

Response::error('Method not allowed.', 405);
