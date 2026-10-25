<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root . '/src/logo/Hyena Tours & Travel Logo S-1-Recovered.webp';
$target = $root . '/src/logo/Hyena Tours & Travel Logo S-1-Recovered-clean.webp';

if (!function_exists('imagecreatefromwebp')) {
    fwrite(STDERR, "PHP GD extension with WebP support is required." . PHP_EOL);
    exit(1);
}

$image = imagecreatefromwebp($source);
if (!$image) {
    fwrite(STDERR, "Could not read logo image." . PHP_EOL);
    exit(1);
}

imagepalettetotruecolor($image);
imagealphablending($image, false);
imagesavealpha($image, true);

$width = imagesx($image);
$height = imagesy($image);

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $rgba = imagecolorat($image, $x, $y);
        $colors = imagecolorsforindex($image, $rgba);
        $isOuterBlack = $colors['red'] < 18 && $colors['green'] < 18 && $colors['blue'] < 18;
        if ($isOuterBlack) {
            imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, 0, 0, 0, 127));
        }
    }
}

if (!imagewebp($image, $target, 90)) {
    imagedestroy($image);
    fwrite(STDERR, "Could not save clean logo." . PHP_EOL);
    exit(1);
}

imagedestroy($image);
echo "Created: {$target}" . PHP_EOL;
