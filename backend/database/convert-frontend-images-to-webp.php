<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "PHP GD extension with WebP support is required." . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$srcDir = $root . DIRECTORY_SEPARATOR . 'src';
$maxWidth = defined('IMAGE_MAX_WIDTH') ? IMAGE_MAX_WIDTH : 1600;
$maxHeight = defined('IMAGE_MAX_HEIGHT') ? IMAGE_MAX_HEIGHT : 1200;
$quality = defined('IMAGE_WEBP_QUALITY') ? IMAGE_WEBP_QUALITY : 78;
$converted = 0;
$skipped = 0;
$failed = 0;
$pathMap = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $extension = strtolower($file->getExtension());
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        continue;
    }

    $sourcePath = $file->getPathname();
    $targetPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $sourcePath) ?? ($sourcePath . '.webp');
    $relativeSource = relative_path($root, $sourcePath);
    $relativeTarget = relative_path($root, $targetPath);

    if (is_file($targetPath)) {
        $pathMap[$relativeSource] = $relativeTarget;
        $skipped++;
        continue;
    }

    if (convert_image_to_webp($sourcePath, $targetPath, $maxWidth, $maxHeight, $quality)) {
        $pathMap[$relativeSource] = $relativeTarget;
        $converted++;
        echo "Converted: {$relativeSource} -> {$relativeTarget}" . PHP_EOL;
        continue;
    }

    $failed++;
    echo "Failed: {$relativeSource}" . PHP_EOL;
}

if ($pathMap !== []) {
    update_code_references($srcDir, $pathMap);
}

echo "Images converted: {$converted}" . PHP_EOL;
echo "Images skipped: {$skipped}" . PHP_EOL;
echo "Images failed: {$failed}" . PHP_EOL;
echo "Original frontend JPG/PNG files were kept." . PHP_EOL;

function relative_path(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(substr($path, strlen($root)), '/\\'));
}

function convert_image_to_webp(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight, int $quality): bool
{
    $mime = mime_content_type($sourcePath);
    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        default => false,
    };

    if (!$source) {
        return false;
    }

    $resized = resize_for_webp($source, $maxWidth, $maxHeight);
    imagedestroy($source);

    $saved = imagewebp($resized, $targetPath, $quality);
    imagedestroy($resized);

    return $saved;
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

function update_code_references(string $srcDir, array $pathMap): void
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (!in_array(strtolower($file->getExtension()), ['ts', 'tsx', 'js', 'jsx', 'css'], true)) {
            continue;
        }

        $path = $file->getPathname();
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        $updated = $content;
        foreach ($pathMap as $source => $target) {
            $updated = str_replace(basename($source), basename($target), $updated);
        }

        if ($updated !== $content) {
            file_put_contents($path, $updated);
        }
    }
}
