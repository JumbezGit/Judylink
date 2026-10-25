<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "PHP GD extension with WebP support is required." . PHP_EOL);
    exit(1);
}

$uploadRoot = rtrim(UPLOAD_ROOT, '/\\');
$publicBase = rtrim(PUBLIC_UPLOAD_BASE, '/');
$maxBytes = defined('IMAGE_MAX_UPLOAD_BYTES') ? IMAGE_MAX_UPLOAD_BYTES : 2 * 1024 * 1024;
$maxWidth = defined('IMAGE_MAX_WIDTH') ? IMAGE_MAX_WIDTH : 1600;
$maxHeight = defined('IMAGE_MAX_HEIGHT') ? IMAGE_MAX_HEIGHT : 1200;
$quality = defined('IMAGE_WEBP_QUALITY') ? IMAGE_WEBP_QUALITY : 78;
$converted = [];
$skipped = 0;
$failed = 0;

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $sourcePath = $file->getPathname();
    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $skipped++;
        continue;
    }

    if ($extension === 'webp' && $file->getSize() <= $maxBytes) {
        $skipped++;
        continue;
    }

    $relativePath = ltrim(str_replace('\\', '/', substr($sourcePath, strlen($uploadRoot))), '/');
    $oldPublicPath = $publicBase . '/' . $relativePath;
    $existingTargetPath = existing_webp_path($sourcePath);
    if ($extension !== 'webp' && $existingTargetPath !== null && filesize($existingTargetPath) <= $maxBytes) {
        $newRelativePath = ltrim(str_replace('\\', '/', substr($existingTargetPath, strlen($uploadRoot))), '/');
        add_path_mapping($converted, $oldPublicPath, $publicBase . '/' . $newRelativePath);
        $skipped++;
        continue;
    }

    $targetPath = unique_webp_path($sourcePath);
    $newRelativePath = ltrim(str_replace('\\', '/', substr($targetPath, strlen($uploadRoot))), '/');
    $newPublicPath = $publicBase . '/' . $newRelativePath;

    if (convert_image_to_webp($sourcePath, $targetPath, $maxWidth, $maxHeight, $quality, $maxBytes)) {
        add_path_mapping($converted, $oldPublicPath, $newPublicPath);
        echo "Converted: {$oldPublicPath} -> {$newPublicPath}" . PHP_EOL;
        continue;
    }

    $failed++;
    echo "Failed: {$oldPublicPath}" . PHP_EOL;
}

if ($converted !== []) {
    update_database_paths(Database::pdo(), $converted);
}

echo "Images converted: " . count($converted) . PHP_EOL;
echo "Images skipped: {$skipped}" . PHP_EOL;
echo "Images failed: {$failed}" . PHP_EOL;
echo "Original image files were kept." . PHP_EOL;

function unique_webp_path(string $sourcePath): string
{
    $info = pathinfo($sourcePath);
    $base = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'];
    $candidate = $base . '.webp';
    $index = 1;

    while (is_file($candidate)) {
        $candidate = $base . '-' . $index . '.webp';
        $index++;
    }

    return $candidate;
}

function existing_webp_path(string $sourcePath): ?string
{
    $info = pathinfo($sourcePath);
    $candidate = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.webp';
    return is_file($candidate) ? $candidate : null;
}

function add_path_mapping(array &$converted, string $oldPublicPath, string $newPublicPath): void
{
    $converted[$oldPublicPath] = $newPublicPath;
    $converted[encode_public_path($oldPublicPath)] = encode_public_path($newPublicPath);
}

function encode_public_path(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function convert_image_to_webp(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight, int $quality, int $maxBytes): bool
{
    $mime = mime_content_type($sourcePath);
    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
        default => false,
    };

    if (!$source) {
        return false;
    }

    $resized = resize_for_webp($source, $maxWidth, $maxHeight);
    imagedestroy($source);

    $currentQuality = $quality;
    do {
        if (!imagewebp($resized, $targetPath, $currentQuality)) {
            imagedestroy($resized);
            return false;
        }
        clearstatcache(true, $targetPath);
        if (filesize($targetPath) <= $maxBytes || $currentQuality <= 48) {
            break;
        }
        $currentQuality -= 10;
    } while (true);

    imagedestroy($resized);

    if (filesize($targetPath) > $maxBytes) {
        unlink($targetPath);
        return false;
    }

    return true;
}

function resize_for_webp(GdImage $source, int $maxWidth, int $maxHeight): GdImage
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

function update_database_paths(PDO $pdo, array $converted): void
{
    $galleryStmt = $pdo->prepare('UPDATE gallery_items SET image_path = ? WHERE image_path = ?');
    foreach ($converted as $oldPath => $newPath) {
        $galleryStmt->execute([$newPath, $oldPath]);
    }

    $tourImageStmt = $pdo->prepare('UPDATE tours SET image_path = ? WHERE image_path = ?');
    foreach ($converted as $oldPath => $newPath) {
        $tourImageStmt->execute([$newPath, $oldPath]);
    }

    $stmt = $pdo->query('SELECT id, gallery FROM tours');
    $updateGallery = $pdo->prepare('UPDATE tours SET gallery = ? WHERE id = ?');
    foreach ($stmt->fetchAll() as $tour) {
        $gallery = json_decode((string) $tour['gallery'], true);
        if (!is_array($gallery)) {
            continue;
        }

        $changed = false;
        foreach ($gallery as &$path) {
            if (is_string($path) && isset($converted[$path])) {
                $path = $converted[$path];
                $changed = true;
            }
        }
        unset($path);

        if ($changed) {
            $updateGallery->execute([json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $tour['id']]);
        }
    }
}
