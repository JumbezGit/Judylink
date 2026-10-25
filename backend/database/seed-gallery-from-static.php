<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

$pdo = Database::pdo();
$requestedSlug = trim((string) ($argv[1] ?? ''));
$siteStmt = $requestedSlug !== ''
    ? $pdo->prepare('SELECT id, site_slug FROM company_sites WHERE site_slug = ? LIMIT 1')
    : $pdo->prepare('SELECT id, site_slug FROM company_sites ORDER BY id LIMIT 1');
$siteStmt->execute($requestedSlug !== '' ? [$requestedSlug] : []);
$site = $siteStmt->fetch();
if (!is_array($site)) {
    fwrite(STDERR, "Site not found. Pass a valid site slug as the first argument." . PHP_EOL);
    exit(1);
}

$sourceDir = dirname(__DIR__, 2) . '/src/assets/imgs/gallary';
$uploadDir = dirname(__DIR__) . '/uploads/gallery/' . $site['site_slug'];
$publicBase = rtrim(PUBLIC_UPLOAD_BASE, '/') . '/gallery/' . $site['site_slug'];

if (!is_dir($sourceDir)) {
    fwrite(STDERR, "Source gallery folder not found: {$sourceDir}" . PHP_EOL);
    exit(1);
}

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    fwrite(STDERR, "Could not create upload folder: {$uploadDir}" . PHP_EOL);
    exit(1);
}

$files = glob($sourceDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

$exists = $pdo->prepare('SELECT id FROM gallery_items WHERE site_id = ? AND image_path = ? LIMIT 1');
$insert = $pdo->prepare(
    'INSERT INTO gallery_items (site_id, title, location, category, image_path, alt_text, span, sort_order, is_active)
     VALUES (:site_id, :title, :location, :category, :image_path, :alt_text, :span, :sort_order, 1)'
);

$inserted = 0;
$skipped = 0;
$copied = 0;

foreach ($files as $index => $file) {
    $fileName = basename($file);
    $target = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    $imagePath = $publicBase . '/' . rawurlencode($fileName);

    $exists->execute([(int) $site['id'], $imagePath]);
    if ($exists->fetchColumn()) {
        $skipped++;
        continue;
    }

    if (!is_file($target)) {
        if (!copy($file, $target)) {
            fwrite(STDERR, "Could not copy: {$fileName}" . PHP_EOL);
            continue;
        }
        $copied++;
    }

    $name = pathinfo($fileName, PATHINFO_FILENAME);
    $title = ucwords(preg_replace('/[-_]+/', ' ', $name) ?: $name);
    $span = $index % 7 === 0 ? 'wide' : ($index % 5 === 0 ? 'tall' : 'normal');

    $insert->execute([
        'site_id' => (int) $site['id'],
        'title' => $title,
        'location' => 'Zanzibar',
        'category' => detect_category($name),
        'image_path' => $imagePath,
        'alt_text' => $title,
        'span' => $span,
        'sort_order' => $index,
    ]);

    $inserted++;
}

echo "Copied: {$copied}" . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Skipped existing: {$skipped}" . PHP_EOL;

function detect_category(string $name): string
{
    $lower = strtolower($name);
    if (str_contains($lower, 'elephant') || str_contains($lower, 'zebra') || str_contains($lower, 'giraffe') || str_contains($lower, 'rhino') || str_contains($lower, 'safari')) {
        return 'Safaris';
    }
    if (str_contains($lower, 'jozani') || str_contains($lower, 'chumbe') || str_contains($lower, 'kilimanjaro') || str_contains($lower, 'forest') || str_contains($lower, 'foggy')) {
        return 'Nature';
    }
    if (str_contains($lower, 'forodhani') || str_contains($lower, 'slave') || str_contains($lower, 'stone') || str_contains($lower, 'kizimbani')) {
        return 'Culture';
    }
    if (str_contains($lower, 'mnemba') || str_contains($lower, 'nungwi') || str_contains($lower, 'nugwi') || str_contains($lower, 'michamvi') || str_contains($lower, 'kizimkazi') || str_contains($lower, 'beach') || str_contains($lower, 'zanzibar')) {
        return 'Beaches';
    }
    return 'Activities';
}
