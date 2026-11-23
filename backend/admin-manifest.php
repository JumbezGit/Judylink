<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/includes/auth.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: private, max-age=300');

$siteSlug = trim((string) ($_GET['site'] ?? ''));
$site = $siteSlug !== '' ? admin_login_site_by_slug($siteSlug) : null;
$brandName = $site ? trim((string) $site['site_name']) : 'Judylink';
$safeSiteSlug = $site ? (string) $site['site_slug'] : '';
$siteQuery = $safeSiteSlug !== '' ? '?site=' . rawurlencode($safeSiteSlug) : '';
$iconQuery = $safeSiteSlug !== '' ? '?site=' . rawurlencode($safeSiteSlug) . '&size=' : '?size=';
$firstBrandWord = (string) (preg_split('/\s+/', $brandName)[0] ?? 'Admin');
$shortName = mb_strlen($brandName) <= 12 ? $brandName : mb_substr($firstBrandWord . ' Admin', 0, 12);

$manifest = [
    'id' => '/backend/admin-app/' . ($safeSiteSlug !== '' ? $safeSiteSlug : 'judylink'),
    'name' => $brandName . ' Admin',
    'short_name' => $shortName,
    'description' => 'Secure, quick access to the ' . $brandName . ' administration portal.',
    'start_url' => admin_secure_path() . $siteQuery,
    'scope' => '/backend/',
    'display' => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
    'background_color' => '#f8fafc',
    'theme_color' => '#0ea5e9',
    'orientation' => 'portrait-primary',
    'categories' => ['business', 'productivity'],
    'icons' => [
        [
            'src' => '/backend/admin-app-icon.php' . $iconQuery . '192',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => '/backend/admin-app-icon.php' . $iconQuery . '512',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Open admin login',
            'short_name' => 'Admin login',
            'url' => admin_secure_path() . $siteQuery,
            'icons' => [[
                'src' => '/backend/admin-app-icon.php' . $iconQuery . '192',
                'sizes' => '192x192',
                'type' => 'image/png',
            ]],
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
