<?php

declare(strict_types=1);

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';

set_exception_handler(static function (Throwable $exception): never {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => defined('APP_ENV') && APP_ENV === 'production'
            ? 'Server error'
            : $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    APP_URL,
];
$originHost = parse_url($origin, PHP_URL_HOST);
$originScheme = parse_url($origin, PHP_URL_SCHEME);
$isLocalDevOrigin = in_array($originHost, ['localhost', '127.0.0.1'], true) && in_array($originScheme, ['http', 'https'], true);

if ($origin !== '' && (in_array($origin, $allowedOrigins, true) || $isLocalDevOrigin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . APP_URL);
}

header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token, X-Site-API-Key');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON body');
    }

    return $data;
}

function require_admin(): void
{
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!hash_equals(ADMIN_TOKEN, $token) || empty($_SESSION['hyena_admin_logged_in'])) {
        Response::error('Admin token required', 401);
    }

    $authFile = __DIR__ . '/../admin/includes/auth.php';
    if (is_file($authFile)) {
        require_once $authFile;
        if (!admin_current_user()) {
            admin_clear_session();
            Response::error('Admin account is inactive', 401);
        }
        $page = admin_api_permission_page();
        if ($page !== '' && !admin_can_view($page)) {
            if (admin_is_dashboard_readonly_request()) {
                return;
            }
            Response::error('You do not have permission to use this admin action', 403);
        }
    }
}

function request_site_id(): int
{
    static $siteId = null;
    if (is_int($siteId) && $siteId > 0) {
        return $siteId;
    }

    $pdo = Database::pdo();
    $adminToken = (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
    if ($adminToken !== '' && session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $isAdminContext = $adminToken !== ''
            && hash_equals(ADMIN_TOKEN, $adminToken)
            && !empty($_SESSION['hyena_admin_logged_in']);
        $selected = $isAdminContext ? (int) ($_SESSION['hyena_admin_selected_site_id'] ?? 0) : 0;
        if ($selected > 0) {
            $stmt = $pdo->prepare('SELECT id FROM company_sites WHERE id = ? LIMIT 1');
            $stmt->execute([$selected]);
            $siteId = (int) ($stmt->fetchColumn() ?: 0);
            if ($siteId > 0) {
                return $siteId;
            }
        }
    }

    $apiKey = trim((string) ($_SERVER['HTTP_X_SITE_API_KEY'] ?? ''));
    if ($apiKey !== '') {
        $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE api_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$apiKey]);
        $siteId = (int) ($stmt->fetchColumn() ?: 0);
        if ($siteId > 0) {
            return $siteId;
        }
    }

    $slug = trim((string) ($_GET['site'] ?? ''));
    if ($slug !== '') {
        $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE site_slug = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$slug]);
        $siteId = (int) ($stmt->fetchColumn() ?: 0);
        if ($siteId > 0) {
            return $siteId;
        }
        Response::error('Site not found', 404);
    }

    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host !== '' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
        $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE LOWER(primary_domain) = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$host]);
        $siteId = (int) ($stmt->fetchColumn() ?: 0);
        if ($siteId > 0) {
            return $siteId;
        }
    }

    $siteId = (int) ($pdo->query("SELECT id FROM company_sites WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    if ($siteId < 1) {
        Response::error('No active site is configured', 503);
    }
    return $siteId;
}

function site_upload_folder(string $folder, int $siteId): string
{
    $stmt = Database::pdo()->prepare('SELECT site_slug FROM company_sites WHERE id = ? LIMIT 1');
    $stmt->execute([$siteId]);
    $slug = trim((string) ($stmt->fetchColumn() ?: ''));
    $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)), '-');
    return trim($folder, '/\\') . '/' . ($slug !== '' ? $slug : 'site-' . $siteId);
}

function admin_is_dashboard_readonly_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
        && ($_SERVER['HTTP_X_ADMIN_CONTEXT'] ?? '') === 'dashboard'
        && admin_can_view('dashboard');
}

function admin_api_permission_page(): string
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return match ($script) {
        'bookings.php' => 'bookings',
        'gallery.php' => 'gallery',
        'tours.php', 'tour-image.php' => 'tours',
        'company-registrations.php' => 'companies',
        default => '',
    };
}

function as_bool(mixed $value): int
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
}

function json_field(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $value = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }

    return json_encode($value ?: [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function decode_tour(array $row): array
{
    foreach (['highlights', 'includes', 'excludes', 'what_to_bring', 'languages', 'gallery', 'itinerary', 'faqs', 'tags'] as $field) {
        $row[$field] = json_decode((string) $row[$field], true) ?: [];
    }
    $row['pickup'] = (bool) $row['pickup'];
    $row['is_popular'] = (bool) ($row['is_popular'] ?? false);
    $row['price'] = (float) $row['price'];
    $row['rating'] = (float) $row['rating'];
    $row['review_count'] = (int) $row['review_count'];
    return $row;
}

function slugify(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));
    return trim($slug, '-') ?: 'tour-' . time();
}

function save_upload(string $field, string $folder): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        Response::error('Image upload failed');
    }

    if ((int) ($_FILES[$field]['size'] ?? 0) > IMAGE_MAX_UPLOAD_BYTES) {
        Response::error('Image must be 2 MB or smaller');
    }

    $allowed = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        Response::error('Only JPG, PNG, and WEBP images are allowed');
    }

    if (!function_exists('imagewebp')) {
        Response::error('PHP GD extension is required to resize and convert images to WebP', 500);
    }

    $folder = site_upload_folder($folder, request_site_id());
    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        Response::error('Could not create upload directory', 500);
    }

    $source = image_from_upload($_FILES[$field]['tmp_name'], $mime);
    if (!$source) {
        Response::error('Could not read uploaded image');
    }

    $resized = resize_image($source, IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT);
    imagedestroy($source);

    $name = bin2hex(random_bytes(12)) . '.webp';
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!imagewebp($resized, $target, IMAGE_WEBP_QUALITY)) {
        imagedestroy($resized);
        Response::error('Could not save uploaded image', 500);
    }
    imagedestroy($resized);

    if (filesize($target) > IMAGE_MAX_UPLOAD_BYTES) {
        unlink($target);
        Response::error('Compressed image is still larger than 2 MB');
    }

    return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/' . $folder . '/' . $name;
}

function image_from_upload(string $path, string $mime): GdImage|false
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($path),
        'image/png' => imagecreatefrompng($path),
        'image/webp' => imagecreatefromwebp($path),
        default => false,
    };
}

function resize_image(GdImage $source, int $maxWidth, int $maxHeight): GdImage
{
    $width = imagesx($source);
    $height = imagesy($source);
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));

    $target = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($target, true);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
    imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    return $target;
}
