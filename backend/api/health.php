<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$result = [
    'ok' => true,
    'php' => PHP_VERSION,
    'database' => [
        'host' => DB_HOST,
        'name' => DB_NAME,
        'connected' => false,
    ],
    'tables' => [],
    'extensions' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'gd' => extension_loaded('gd'),
        'fileinfo' => extension_loaded('fileinfo'),
        'exif' => extension_loaded('exif'),
        'imagick' => extension_loaded('imagick'),
    ],
    'uploads' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
    ],
];

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $result['database']['connected'] = true;

    foreach (['tours', 'gallery_items', 'bookings', 'staffs', 'staff_tasks', 'company_sites', 'site_api_records', 'site_api_activity'] as $table) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $result['tables'][$table] = (int) $stmt->fetchColumn() === 1;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    $result['ok'] = false;
    $result['error'] = $exception->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
