<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/includes/auth.php';

$requestedSize = (int) ($_GET['size'] ?? 192);
$size = in_array($requestedSize, [192, 512], true) ? $requestedSize : 192;
$siteSlug = trim((string) ($_GET['site'] ?? ''));
$site = $siteSlug !== '' ? admin_login_site_by_slug($siteSlug) : null;
$logoUrl = trim((string) ($site['brand_logo'] ?? ''));

function admin_app_local_logo(string $logoUrl): string
{
    $fallback = dirname(__DIR__) . '/public/pwa-logo.webp';
    if ($logoUrl === '') {
        return $fallback;
    }

    $path = (string) parse_url($logoUrl, PHP_URL_PATH);
    $path = rawurldecode($path);
    $root = realpath(dirname(__DIR__));
    $candidate = realpath(dirname(__DIR__) . '/' . ltrim($path, '/\\'));
    if ($root === false || $candidate === false || !str_starts_with($candidate, $root) || !is_file($candidate)) {
        return $fallback;
    }

    return $candidate;
}

function admin_app_load_image(string $path): GdImage|false
{
    $info = @getimagesize($path);
    if (!$info) {
        return false;
    }

    return match ($info[2]) {
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

$source = admin_app_load_image(admin_app_local_logo($logoUrl));
$canvas = imagecreatetruecolor($size, $size);
imagealphablending($canvas, true);
imagesavealpha($canvas, true);
$background = imagecolorallocate($canvas, 255, 255, 255);
imagefill($canvas, 0, 0, $background);

if ($source !== false) {
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $available = (int) round($size * 0.78);
    $scale = min($available / max(1, $sourceWidth), $available / max(1, $sourceHeight));
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));
    $left = (int) floor(($size - $width) / 2);
    $top = (int) floor(($size - $height) / 2);
    imagecopyresampled($canvas, $source, $left, $top, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
    imagedestroy($source);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
imagepng($canvas, null, 9);
imagedestroy($canvas);
