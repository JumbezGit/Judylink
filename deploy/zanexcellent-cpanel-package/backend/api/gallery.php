<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$galleryMaxImages = defined('GALLERY_MAX_IMAGES') ? GALLERY_MAX_IMAGES : 100;

if ($method === 'GET') {
    $pdo = Database::pdo();
    if (!empty($_GET['summary'])) {
        require_admin();
        $siteId = request_site_id();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE site_id = ? AND is_active = 1');
        $countStmt->execute([$siteId]);
        $categoryStmt = $pdo->prepare('SELECT COUNT(DISTINCT category) FROM gallery_items WHERE site_id = ? AND is_active = 1 AND category <> ""');
        $categoryStmt->execute([$siteId]);
        Response::json([
            'ok' => true,
            'count' => (int) $countStmt->fetchColumn(),
            'categories' => (int) $categoryStmt->fetchColumn(),
            'max' => $galleryMaxImages,
        ]);
    }

    if (!empty($_GET['deleted'])) {
        require_admin();
        $siteId = request_site_id();
        if (!function_exists('admin_is_super_admin') || !admin_is_super_admin()) {
            Response::error('Super admin required', 403);
        }
        $stmt = $pdo->prepare('SELECT * FROM gallery_items WHERE site_id = ? AND is_active = 0 ORDER BY updated_at DESC, id DESC');
        $stmt->execute([$siteId]);
        Response::json([
            'ok' => true,
            'items' => $stmt->fetchAll(),
        ]);
    }

    $siteId = request_site_id();
    $stmt = $pdo->prepare('SELECT * FROM gallery_items WHERE site_id = ? AND is_active = 1 ORDER BY sort_order, id DESC');
    $stmt->execute([$siteId]);
    $items = array_map(static function (array $item): array {
        $item['source_type'] = 'database';
        return $item;
    }, $stmt->fetchAll());
    Response::json([
        'ok' => true,
        'items' => $items,
        'count' => count($items),
        'max' => $galleryMaxImages,
    ]);
}

if ($method === 'POST') {
    require_admin();
    $siteId = request_site_id();
    $pdo = Database::pdo();
    $id = (int) ($_POST['id'] ?? 0);

    if ($id === 0) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE site_id = ? AND is_active = 1');
        $countStmt->execute([$siteId]);
        if ((int) $countStmt->fetchColumn() >= $galleryMaxImages) {
            Response::error("Gallery limit reached. Maximum {$galleryMaxImages} images are allowed.");
        }
    }

    $image = save_upload('image', 'gallery');

    if ($id > 0) {
        $current = $pdo->prepare('SELECT image_path FROM gallery_items WHERE id = ? AND site_id = ? AND is_active = 1 LIMIT 1');
        $current->execute([$id, $siteId]);
        $currentImage = $current->fetchColumn();
        if (!$currentImage) {
            Response::error('Gallery image not found', 404);
        }

        $stmt = $pdo->prepare(
            'UPDATE gallery_items
             SET title = :title, location = :location, category = :category, image_path = :image_path,
                 alt_text = :alt_text, span = :span, sort_order = :sort_order
             WHERE id = :id AND site_id = :site_id'
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $siteId,
            'title' => trim($_POST['title'] ?? ''),
            'location' => trim($_POST['location'] ?? 'Zanzibar'),
            'category' => trim($_POST['category'] ?? 'Activities'),
            'image_path' => $image ?? $currentImage,
            'alt_text' => trim($_POST['alt_text'] ?? ''),
            'span' => in_array($_POST['span'] ?? 'normal', ['wide', 'tall', 'normal'], true) ? $_POST['span'] : 'normal',
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);
        Response::json(['ok' => true, 'id' => $id, 'image_path' => $image ?? $currentImage]);
    }

    if ($image === null) {
        Response::error('Gallery image is required');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO gallery_items (site_id, title, location, category, image_path, alt_text, span, sort_order)
         VALUES (:site_id, :title, :location, :category, :image_path, :alt_text, :span, :sort_order)'
    );
    $stmt->execute([
        'site_id' => $siteId,
        'title' => trim($_POST['title'] ?? ''),
        'location' => trim($_POST['location'] ?? 'Zanzibar'),
        'category' => trim($_POST['category'] ?? 'Activities'),
        'image_path' => $image,
        'alt_text' => trim($_POST['alt_text'] ?? ''),
        'span' => in_array($_POST['span'] ?? 'normal', ['wide', 'tall', 'normal'], true) ? $_POST['span'] : 'normal',
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    ]);
    Response::json(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'image_path' => $image], 201);
}

if ($method === 'PATCH') {
    require_admin();
    $siteId = request_site_id();
    if (!function_exists('admin_is_super_admin') || !admin_is_super_admin()) {
        Response::error('Super admin required', 403);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Gallery id is required');
    }
    $pdo = Database::pdo();
    $stmt = $pdo->prepare('UPDATE gallery_items SET is_active = 1 WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    Response::json(['ok' => true]);
}

if ($method === 'DELETE') {
    require_admin();
    $siteId = request_site_id();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        Response::error('Gallery id is required');
    }
    $pdo = Database::pdo();
    if (!empty($_GET['permanent'])) {
        if (!function_exists('admin_is_super_admin') || !admin_is_super_admin()) {
            Response::error('Super admin required', 403);
        }
        $stmt = $pdo->prepare('DELETE FROM gallery_items WHERE id = ? AND site_id = ? AND is_active = 0');
        $stmt->execute([$id, $siteId]);
        Response::json(['ok' => true]);
    }

    $stmt = $pdo->prepare('UPDATE gallery_items SET is_active = 0 WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    Response::json(['ok' => true]);
}

Response::error('Method not allowed', 405);
