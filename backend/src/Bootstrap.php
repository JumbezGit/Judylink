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
    $requestedSlug = trim((string) ($_GET['site'] ?? ''));
    if (session_status() === PHP_SESSION_ACTIVE) {
        $isAdminContext = $adminToken !== ''
            && hash_equals(ADMIN_TOKEN, $adminToken)
            && !empty($_SESSION['hyena_admin_logged_in']);
        $selected = $isAdminContext ? (int) ($_SESSION['hyena_admin_selected_site_id'] ?? 0) : 0;
        if ($selected > 0) {
            $stmt = $pdo->prepare('SELECT id, site_slug FROM company_sites WHERE id = ? LIMIT 1');
            $stmt->execute([$selected]);
            $selectedSite = $stmt->fetch();
            $siteId = (int) ($selectedSite['id'] ?? 0);
            if ($siteId > 0) {
                if ($requestedSlug !== '' && !hash_equals((string) $selectedSite['site_slug'], $requestedSlug)) {
                    Response::error('Selected site does not match the requested site.', 409);
                }
                return $siteId;
            }
        }
        if ($isAdminContext) {
            Response::error('Select a valid site before requesting site data.', 409);
        }
    }

    $apiKey = trim((string) ($_SERVER['HTTP_X_SITE_API_KEY'] ?? ''));
    if ($apiKey !== '') {
        $stmt = $pdo->prepare("SELECT id, site_slug FROM company_sites WHERE api_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$apiKey]);
        $apiSite = $stmt->fetch();
        $siteId = (int) ($apiSite['id'] ?? 0);
        if ($siteId > 0) {
            if ($requestedSlug !== '' && !hash_equals((string) $apiSite['site_slug'], $requestedSlug)) {
                Response::error('Site slug and API key do not belong to the same site.', 403);
            }
            return $siteId;
        }
        Response::error('Invalid site API key.', 401);
    }

    if ($requestedSlug !== '') {
        $stmt = $pdo->prepare("SELECT id FROM company_sites WHERE site_slug = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$requestedSlug]);
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

    Response::error('Site identity is required. Configure a site slug, API key, or primary domain.', 400);
}

function site_slug_for_id(int $siteId): string
{
    static $slugs = [];
    if (isset($slugs[$siteId])) {
        return $slugs[$siteId];
    }

    $stmt = Database::pdo()->prepare('SELECT site_slug FROM company_sites WHERE id = ? LIMIT 1');
    $stmt->execute([$siteId]);
    $slugs[$siteId] = (string) ($stmt->fetchColumn() ?: '');
    return $slugs[$siteId];
}

function site_upload_folder(string $folder, int $siteId): string
{
    $stmt = Database::pdo()->prepare('SELECT site_slug FROM company_sites WHERE id = ? LIMIT 1');
    $stmt->execute([$siteId]);
    $slug = trim((string) ($stmt->fetchColumn() ?: ''));
    $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)), '-');
    return trim($folder, '/\\') . '/' . ($slug !== '' ? $slug : 'site-' . $siteId);
}

function site_upload_file_path(string $publicPath, int $siteId, array $allowedFolders): ?string
{
    $uploadPath = rawurldecode((string) (parse_url($publicPath, PHP_URL_PATH) ?: ''));
    $publicBase = rtrim((string) (parse_url(PUBLIC_UPLOAD_BASE, PHP_URL_PATH) ?: PUBLIC_UPLOAD_BASE), '/');
    if ($uploadPath === '' || !str_starts_with($uploadPath, $publicBase . '/')) {
        return null;
    }

    $relativePath = str_replace('\\', '/', ltrim(substr($uploadPath, strlen($publicBase)), '/'));
    if ($relativePath === '' || preg_match('~(^|/)\.\.(/|$)~', $relativePath)) {
        return null;
    }

    $siteSlug = site_slug_for_id($siteId);
    $allowedPrefixes = [];
    foreach ($allowedFolders as $folder) {
        $folder = trim((string) $folder, '/\\');
        if ($folder === 'staff-gallery') {
            $allowedPrefixes[] = $folder . '/site-' . $siteId . '/';
        } elseif ($siteSlug !== '') {
            $allowedPrefixes[] = $folder . '/' . $siteSlug . '/';
        }
    }
    if (!array_filter($allowedPrefixes, static fn (string $prefix): bool => str_starts_with($relativePath, $prefix))) {
        return null;
    }

    $uploadRoot = realpath(UPLOAD_ROOT);
    $filePath = realpath(rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($uploadRoot === false || $filePath === false || !is_file($filePath)) {
        return null;
    }

    $rootPrefix = rtrim($uploadRoot, '/\\') . DIRECTORY_SEPARATOR;
    return str_starts_with($filePath, $rootPrefix) ? $filePath : null;
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
    if (!isset($_FILES[$field])) {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxBytes = ini_size_bytes((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            Response::error('The image is larger than the server request limit. Try a smaller image.', 413);
        }
        return null;
    }

    $uploadError = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        Response::error(upload_error_message($uploadError), $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE ? 413 : 400);
    }

    $sourceMaxBytes = defined('IMAGE_MAX_SOURCE_BYTES') ? IMAGE_MAX_SOURCE_BYTES : 10 * 1024 * 1024;
    if ((int) ($_FILES[$field]['size'] ?? 0) > $sourceMaxBytes) {
        Response::error('Source image must be 10 MB or smaller.', 413);
    }

    $allowed = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
        'image/heic-sequence' => 'heic',
        'image/heif-sequence' => 'heic',
    ];
    $temporaryPath = (string) $_FILES[$field]['tmp_name'];
    $mime = (string) (mime_content_type($temporaryPath) ?: '');
    $extension = strtolower(pathinfo((string) ($_FILES[$field]['name'] ?? ''), PATHINFO_EXTENSION));
    if ($mime === 'application/octet-stream' && in_array($extension, ['heic', 'heif'], true)) {
        $mime = 'image/heic';
    }
    if (!isset($allowed[$mime])) {
        Response::error('Unsupported phone image format. Upload JPG, PNG, WEBP, AVIF, HEIC, or HEIF.');
    }
    if ($allowed[$mime] === 'heic' && !class_exists('Imagick')) {
        Response::error('This server cannot convert HEIC yet. On iPhone choose Camera > Formats > Most Compatible, or upload a JPG image.');
    }
    if ($allowed[$mime] === 'avif' && !function_exists('imagecreatefromavif') && !class_exists('Imagick')) {
        Response::error('This server cannot convert AVIF yet. Please upload a JPG, PNG, or WEBP image.');
    }

    if (!function_exists('imagewebp')) {
        Response::error('PHP GD extension is required to resize and convert images to WebP', 500);
    }

    $folder = site_upload_folder($folder, request_site_id());
    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        Response::error('Could not create upload directory', 500);
    }

    $dimensions = @getimagesize($temporaryPath);
    if (is_array($dimensions)) {
        $sourcePixels = (int) $dimensions[0] * (int) $dimensions[1];
        $maxSourcePixels = defined('IMAGE_MAX_SOURCE_PIXELS') ? IMAGE_MAX_SOURCE_PIXELS : 64_000_000;
        if ($sourcePixels > $maxSourcePixels) {
            Response::error('Image resolution is too large. Use an image smaller than 64 megapixels.', 413);
        }
    }

    $source = image_from_upload($temporaryPath, $mime);
    if (!$source) {
        Response::error('Could not decode the uploaded image. Try exporting it as JPG before uploading.');
    }

    $resized = resize_image($source, IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT);
    imagedestroy($source);

    $name = bin2hex(random_bytes(12)) . '.webp';
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    $quality = IMAGE_WEBP_QUALITY;
    do {
        $saved = imagewebp($resized, $target, $quality);
        if ($saved && filesize($target) <= IMAGE_MAX_UPLOAD_BYTES) {
            break;
        }
        $quality -= 10;
    } while ($quality >= 48);
    imagedestroy($resized);

    if (!$saved || !is_file($target)) {
        Response::error('Could not save uploaded image', 500);
    }

    if (filesize($target) > IMAGE_MAX_UPLOAD_BYTES) {
        unlink($target);
        Response::error('Compressed image is still larger than 2 MB');
    }

    return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/' . $folder . '/' . $name;
}

function ini_size_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $number = (float) $value;
    return match (strtolower(substr($value, -1))) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The server PHP upload limit is below the required 10 MB. Set upload_max_filesize to at least 16M.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Check your connection and try again.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image.',
        UPLOAD_ERR_EXTENSION => 'The server stopped the image upload.',
        default => 'Image upload failed. Please try again.',
    };
}

function image_from_upload(string $path, string $mime): GdImage|false
{
    if ($mime === 'image/jpeg') {
        $image = @imagecreatefromjpeg($path);
        return $image ? orient_jpeg_image($image, $path) : false;
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($path);
    }
    if ($mime === 'image/webp') {
        return @imagecreatefromwebp($path);
    }
    if ($mime === 'image/avif' && function_exists('imagecreatefromavif')) {
        return @imagecreatefromavif($path);
    }
    if (class_exists('Imagick')) {
        try {
            $image = new Imagick();
            $image->readImage($path);
            $image->setIteratorIndex(0);
            $image->setImageFormat('png');
            $decoded = @imagecreatefromstring($image->getImageBlob());
            $image->clear();
            $image->destroy();
            return $decoded;
        } catch (Throwable) {
            return false;
        }
    }
    return false;
}

function orient_jpeg_image(GdImage $image, string $path): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($path);
    $orientation = (int) ($exif['Orientation'] ?? 1);
    $angle = match ($orientation) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };
    if ($angle === 0) {
        return $image;
    }
    $rotated = imagerotate($image, $angle, 0);
    if (!$rotated) {
        return $image;
    }
    imagedestroy($image);
    return $rotated;
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
