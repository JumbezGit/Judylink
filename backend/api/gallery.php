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
            'site' => site_slug_for_id($siteId),
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
            'site' => site_slug_for_id($siteId),
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
        'site' => site_slug_for_id($siteId),
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
        $itemStmt = $pdo->prepare('SELECT image_path FROM gallery_items WHERE id = ? AND site_id = ? AND is_active = 0 LIMIT 1');
        $itemStmt->execute([$id, $siteId]);
        $imagePath = (string) ($itemStmt->fetchColumn() ?: '');
        if ($imagePath === '') {
            Response::error('Deleted gallery image not found', 404);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM gallery_items WHERE id = ? AND site_id = ? AND is_active = 0');
            $stmt->execute([$id, $siteId]);
            $staffStmt = $pdo->prepare("UPDATE staff_uploads SET status = 'deleted' WHERE site_id = ? AND image_path = ? AND status = 'gallery'");
            $staffStmt->execute([$siteId, $imagePath]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        $fileDeleted = false;
        $fileRetained = gallery_image_is_referenced($pdo, $siteId, $imagePath);
        if (!$fileRetained) {
            $filePath = site_upload_file_path($imagePath, $siteId, ['gallery', 'staff-gallery']);
            $fileDeleted = $filePath !== null && unlink($filePath);
        }
        Response::json([
            'ok' => true,
            'fileDeleted' => $fileDeleted,
            'fileRetained' => $fileRetained,
            'message' => $fileDeleted
                ? 'Gallery image and cPanel upload deleted.'
                : ($fileRetained ? 'Gallery record deleted; the shared image file is still in use.' : 'Gallery record deleted; the upload file was already missing or could not be removed.'),
        ]);
    }

    $stmt = $pdo->prepare('UPDATE gallery_items SET is_active = 0 WHERE id = ? AND site_id = ?');
    $stmt->execute([$id, $siteId]);
    Response::json(['ok' => true]);
}

Response::error('Method not allowed', 405);

function gallery_image_is_referenced(PDO $pdo, int $siteId, string $imagePath): bool
{
    $galleryStmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE site_id = ? AND image_path = ?');
    $galleryStmt->execute([$siteId, $imagePath]);
    if ((int) $galleryStmt->fetchColumn() > 0) {
        return true;
    }

    $staffStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_uploads WHERE site_id = ? AND image_path = ? AND status <> 'deleted'");
    $staffStmt->execute([$siteId, $imagePath]);
    if ((int) $staffStmt->fetchColumn() > 0) {
        return true;
    }

    $tourStmt = $pdo->prepare('SELECT image_path, gallery FROM tours WHERE site_id = ?');
    $tourStmt->execute([$siteId]);
    foreach ($tourStmt->fetchAll() as $tour) {
        if (hash_equals((string) ($tour['image_path'] ?? ''), $imagePath)) {
            return true;
        }
        $gallery = json_decode((string) ($tour['gallery'] ?? '[]'), true);
        if (is_array($gallery) && in_array($imagePath, $gallery, true)) {
            return true;
        }
    }
    return false;
}
