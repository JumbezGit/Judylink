<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

admin_ensure_staff_tables();

if (empty($_SESSION['staff_user_id'])) {
    header('Location: /backend/staff-login.php');
    exit;
}

$sessionStaffSiteId = (int) ($_SESSION['staff_site_id'] ?? 0);
if ($sessionStaffSiteId < 1) {
    unset($_SESSION['staff_user_id'], $_SESSION['staff_username'], $_SESSION['staff_site_id']);
    header('Location: /backend/staff-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['staff_alert_status'])) {
    $staffId = (int) $_SESSION['staff_user_id'];
    $soundStmt = Database::pdo()->prepare('SELECT staff_sound_enabled FROM staffs WHERE id = ? AND site_id = ? AND status = "approved" LIMIT 1');
    $soundStmt->execute([$staffId, $sessionStaffSiteId]);
    $soundEnabledForAlert = $soundStmt->fetchColumn();
    if ($soundEnabledForAlert === false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM staff_tasks WHERE staff_id = ? AND site_id = ? AND status = "assigned"');
    $stmt->execute([$staffId, $sessionStaffSiteId]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'newTaskCount' => (int) $stmt->fetchColumn(),
        'soundEnabled' => (int) $soundEnabledForAlert === 1,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = Database::pdo()->prepare('SELECT * FROM staffs WHERE id = ? AND site_id = ? AND status = "approved" LIMIT 1');
$stmt->execute([(int) $_SESSION['staff_user_id'], $sessionStaffSiteId]);
$staff = $stmt->fetch();
if (!$staff) {
    unset($_SESSION['staff_user_id'], $_SESSION['staff_username'], $_SESSION['staff_site_id']);
    header('Location: /backend/staff-login.php');
    exit;
}

function staff_dash_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staff_detail_card(string $label, mixed $value): string
{
    $display = trim((string) $value) !== '' ? staff_dash_h($value) : '-';
    return '<div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-medium uppercase text-slate-400">' . staff_dash_h($label) . '</dt><dd class="mt-1 break-words text-sm font-medium text-slate-800">' . $display . '</dd></div>';
}

function staff_upload_error(string $message): never
{
    staff_json_or_redirect('upload-image', false, $message, 'error');
}

function staff_save_upload_image_file(array $file, int $siteId): string
{
    $maxSavedBytes = IMAGE_MAX_UPLOAD_BYTES;

    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $message = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The server PHP upload limit is below the required 10 MB. Set upload_max_filesize to at least 16M.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Check your connection and try again.',
            default => 'Image upload failed. Please try again.',
        };
        staff_upload_error($message);
    }
    $sourceMaxBytes = defined('IMAGE_MAX_SOURCE_BYTES') ? IMAGE_MAX_SOURCE_BYTES : 10 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $sourceMaxBytes) {
        staff_upload_error('Source image must be 10 MB or smaller');
    }
    if (!function_exists('imagewebp')) {
        staff_upload_error('PHP GD extension is required for image upload');
    }

    $temporaryPath = (string) $file['tmp_name'];
    $mime = (string) (mime_content_type($temporaryPath) ?: '');
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($mime === 'application/octet-stream' && in_array($extension, ['heic', 'heif'], true)) {
        $mime = 'image/heic';
    }
    if (in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true) && !class_exists('Imagick')) {
        staff_upload_error('This server cannot convert HEIC. On iPhone choose Camera > Formats > Most Compatible, or upload a JPG image.');
    }
    if ($mime === 'image/avif' && !function_exists('imagecreatefromavif') && !class_exists('Imagick')) {
        staff_upload_error('This server cannot convert AVIF. Please upload a JPG, PNG, or WEBP image.');
    }

    $dimensions = @getimagesize($temporaryPath);
    if (is_array($dimensions) && ((int) $dimensions[0] * (int) $dimensions[1]) > 64_000_000) {
        staff_upload_error('Image resolution is too large. Use an image smaller than 64 megapixels.');
    }

    $source = staff_decode_upload_image($temporaryPath, $mime);
    if (!$source) {
        staff_upload_error('Could not decode this phone image. Upload JPG, PNG, WEBP, AVIF, HEIC, or HEIF.');
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $ratio = min(IMAGE_MAX_WIDTH / $width, IMAGE_MAX_HEIGHT / $height, 1);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($resized, true);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($source);

    $folder = 'staff-gallery/site-' . $siteId;
    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $folder);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        imagedestroy($resized);
        staff_upload_error('Could not create upload directory');
    }
    $name = bin2hex(random_bytes(12)) . '.webp';
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    $saved = false;
    for ($quality = min(IMAGE_WEBP_QUALITY, 82); $quality >= 45; $quality -= 7) {
        if (!imagewebp($resized, $target, $quality)) {
            imagedestroy($resized);
            staff_upload_error('Could not save uploaded image');
        }
        if (filesize($target) <= $maxSavedBytes) {
            $saved = true;
            break;
        }
    }
    imagedestroy($resized);
    if (!$saved) {
        if (is_file($target)) {
            unlink($target);
        }
        staff_upload_error('Image could not be reduced below 2 MB');
    }

    return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/' . $folder . '/' . $name;
}

function staff_decode_upload_image(string $path, string $mime): GdImage|false
{
    if ($mime === 'image/jpeg') {
        $image = @imagecreatefromjpeg($path);
        if (!$image || !function_exists('exif_read_data')) {
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

function staff_uploaded_images(): array
{
    $files = [];
    if (isset($_FILES['image']) && is_array($_FILES['image']['name'])) {
        foreach ($_FILES['image']['name'] as $index => $name) {
            if ($_FILES['image']['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'type' => $_FILES['image']['type'][$index],
                'tmp_name' => $_FILES['image']['tmp_name'][$index],
                'error' => $_FILES['image']['error'][$index],
                'size' => $_FILES['image']['size'][$index],
            ];
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $files[] = $_FILES['image'];
    }
    if (isset($_FILES['camera_image']) && $_FILES['camera_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $files[] = $_FILES['camera_image'];
    }

    return $files;
}

function staff_redirect(string $view, string $type, string $message): never
{
    header('Location: /backend/staff-dashboard.php?view=' . rawurlencode($view) . '&' . rawurlencode($type) . '=' . rawurlencode($message));
    exit;
}

function staff_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
}

function staff_task_json_or_redirect(bool $ok, string $message, string $type = 'saved'): never
{
    staff_json_or_redirect('tasks', $ok, $message, $type);
}

function staff_json_or_redirect(string $view, bool $ok, string $message, string $type = 'saved'): never
{
    if (staff_is_ajax_request()) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, $type => $message, 'view' => $view], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    staff_redirect($view, $type, $message);
}

function staff_public_upload_path_to_file(string $publicPath): ?string
{
    $publicBase = rtrim(PUBLIC_UPLOAD_BASE, '/');
    if (!str_starts_with($publicPath, $publicBase . '/')) {
        return null;
    }
    $relative = ltrim(substr($publicPath, strlen($publicBase)), '/\\');
    $target = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $root = realpath(UPLOAD_ROOT);
    $resolved = realpath($target);
    if (!$root || !$resolved || !str_starts_with($resolved, $root)) {
        return null;
    }

    return $resolved;
}

function staff_task_feedback_meta(?string $feedback): array
{
    return match ($feedback) {
        'excellent' => ['label' => 'Excellent', 'icon' => 'fa-star', 'class' => 'border-emerald-500 text-emerald-700'],
        'good' => ['label' => 'Good', 'icon' => 'fa-thumbs-up', 'class' => 'border-sky-500 text-sky-700'],
        'fair' => ['label' => 'Fair', 'icon' => 'fa-scale-balanced', 'class' => 'border-amber-500 text-amber-700'],
        'poor' => ['label' => 'Poor', 'icon' => 'fa-triangle-exclamation', 'class' => 'border-red-500 text-red-700'],
        default => ['label' => '', 'icon' => 'fa-comment-dots', 'class' => 'border-slate-400 text-slate-600'],
    };
}

function staff_task_status_meta(string $status): array
{
    return match ($status) {
        'assigned' => ['label' => 'New Dutie', 'class' => 'border-amber-500 text-amber-700'],
        'accepted' => ['label' => 'In progress', 'class' => 'border-sky-500 text-sky-700'],
        'completed' => ['label' => 'Completed', 'class' => 'border-emerald-500 text-emerald-700'],
        'declined', 'cancelled' => ['label' => 'Declined', 'class' => 'border-red-500 text-red-700'],
        default => ['label' => ucfirst($status), 'class' => 'border-slate-400 text-slate-600'],
    };
}

function staff_attach_task_guests(array $tasks): array
{
    if (!$tasks) {
        return [];
    }

    $ids = array_map(fn ($task) => (int) $task['id'], $tasks);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = Database::pdo()->prepare("SELECT * FROM staff_task_guests WHERE staff_task_id IN ({$placeholders}) ORDER BY sort_order, id");
    $stmt->execute($ids);
    $guestsByTask = [];
    foreach ($stmt->fetchAll() as $guest) {
        $guestsByTask[(int) $guest['staff_task_id']][] = $guest;
    }

    foreach ($tasks as &$task) {
        $task['guests'] = $guestsByTask[(int) $task['id']] ?? [];
    }
    unset($task);

    return $tasks;
}

function staff_task_guests_markup(array $task): void
{
    $guests = $task['guests'] ?? [];
    if (!$guests) {
        $guests = [[
            'guest_name' => (string) $task['guest_name'],
            'hotel' => (string) $task['hotel'],
            'pax' => (string) $task['pax'],
            'pickup_time' => (string) $task['pickup_time'],
            'phone' => (string) $task['phone'],
            'language_role' => (string) $task['language_role'],
        ]];
    }
    ?>
    <div class="mt-3 grid min-w-0 gap-2">
      <?php foreach ($guests as $index => $guest): ?>
        <div class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-3">
          <p class="text-xs font-semibold uppercase text-slate-400">Task Details</p>
          <div class="mt-1 grid min-w-0 gap-1 text-sm text-slate-600">
            <span class="min-w-0 break-words">Name: <?= staff_dash_h($guest['guest_name'] ?? '') ?></span>
            <span class="min-w-0 break-words">Hotel: <?= staff_dash_h($guest['hotel'] ?? '') ?></span>
            <span class="min-w-0 break-words"><?= staff_dash_h($guest['pax'] ?? '') ?></span>
            <span class="min-w-0 break-words">Pickup <?= staff_dash_h($guest['pickup_time'] ?? '') ?></span>
            <span class="min-w-0 break-words"><?= staff_dash_h($guest['phone'] ?? '') ?></span>
            <span class="min-w-0 break-words"><?= staff_dash_h($guest['language_role'] ?? '') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

function staff_task_card(array $task): string
{
    $status = (string) $task['status'];
    $statusMeta = staff_task_status_meta($status);
    $feedbackMeta = staff_task_feedback_meta((string) ($task['feedback'] ?? ''));
    $guestNames = array_values(array_filter(array_map('trim', explode("\n", (string) $task['guest_name']))));
    $guestPhones = array_values(array_map('trim', explode("\n", (string) $task['phone'])));
    $taskType = (string) ($task['task_type'] ?? 'private');
    $taskTypeLabel = $taskType === 'group' ? 'Group' : 'Private';

    ob_start();
    ?>
    <article class="flex h-full min-w-0 max-w-full flex-col justify-between gap-4 overflow-hidden rounded-lg border border-slate-200 bg-white p-4">
      <div class="min-w-0 max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
          <div class="min-w-0">
            <p class="text-xs font-medium uppercase text-slate-400">Duty Date</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900"><?= staff_dash_h(date('d/m/Y', strtotime((string) $task['task_date']))) ?></p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-600"><?= staff_dash_h($taskTypeLabel) ?></span>
            <span class="rounded-md border px-2 py-1 text-[11px] font-medium <?= staff_dash_h($statusMeta['class']) ?>"><?= staff_dash_h($statusMeta['label']) ?></span>
          </div>
        </div>
        <div class="mt-3 rounded-lg bg-slate-50 p-3">
          <p class="text-xs font-medium uppercase text-slate-400">Tour Title</p>
          <p class="mt-1 break-words whitespace-pre-line text-base font-semibold text-slate-900"><?= staff_dash_h($task['tour_details']) ?></p>
        </div>
        <div class="mt-3 grid min-w-0 gap-2 rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-600">
          <?php foreach ($guestNames ?: [(string) $task['guest_name']] as $index => $guestName): ?>
            <div class="grid min-w-0 gap-1 rounded-md bg-slate-50 p-2">
              <span class="min-w-0 break-words">Name: <?= staff_dash_h($guestName) ?></span>
              <?php if (($guestPhones[$index] ?? '') !== ''): ?><span class="min-w-0 break-words"><?= staff_dash_h($guestPhones[$index]) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <div class="grid min-w-0 gap-1 pt-1 sm:grid-cols-2">
            <span class="min-w-0 break-words">Hotel: <?= staff_dash_h($task['hotel']) ?></span>
            <span class="min-w-0 break-words"><?= staff_dash_h($task['pax']) ?></span>
            <span class="min-w-0 break-words">Pickup <?= staff_dash_h($task['pickup_time']) ?></span>
            <span class="min-w-0 break-words"><?= staff_dash_h($task['language_role']) ?></span>
          </div>
        </div>
        <?php if (!empty($task['completion_note'])): ?>
          <div class="mt-3 min-w-0 rounded-lg border border-emerald-100 bg-emerald-50 p-3">
            <p class="text-xs font-medium uppercase text-slate-400">Completion</p>
            <p class="mt-1 line-clamp-3 break-words whitespace-pre-line text-sm text-slate-700"><?= staff_dash_h($task['completion_note']) ?></p>
          </div>
        <?php endif; ?>
        <?php if (!empty($task['feedback'])): ?>
          <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-medium uppercase text-slate-400">Admin Feedback</p>
            <span class="mt-2 inline-flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs font-semibold <?= staff_dash_h($feedbackMeta['class']) ?>">
              <i class="fa-solid <?= staff_dash_h($feedbackMeta['icon']) ?>"></i><?= staff_dash_h($feedbackMeta['label']) ?>
            </span>
            <?php if (!empty($task['feedback_at'])): ?><p class="mt-2 text-xs text-slate-500"><?= staff_dash_h($task['feedback_at']) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="mt-auto">
        <?php if ($status === 'assigned'): ?>
          <div class="grid grid-cols-2 gap-2">
            <form class="staffTaskActionForm" method="post">
              <input type="hidden" name="action" value="task_response">
              <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
              <input type="hidden" name="response" value="accepted">
              <button class="primary w-full" type="submit"><i class="fa-solid fa-check"></i>Agree</button>
            </form>
            <form class="staffTaskActionForm" method="post">
              <input type="hidden" name="action" value="task_response">
              <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
              <input type="hidden" name="response" value="declined">
              <button class="danger w-full" type="submit"><i class="fa-solid fa-xmark"></i>Disagree</button>
            </form>
          </div>
        <?php elseif ($status === 'accepted'): ?>
          <form method="post" class="staffTaskActionForm grid gap-2">
            <input type="hidden" name="action" value="task_complete">
            <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Completion details
              <textarea class="form-control min-h-20" name="completion_note" placeholder="Write what was completed..." required></textarea>
            </label>
            <button class="primary w-full" type="submit"><i class="fa-solid fa-flag-checkered"></i>Send Completion</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
    <?php
    return (string) ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $zanzibarNationalId = trim((string) ($_POST['zanzibar_national_id'] ?? ''));
        $tourGuideId = trim((string) ($_POST['tour_guide_id'] ?? ''));
        $driverLicence = trim((string) ($_POST['driver_licence'] ?? ''));
        $languages = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['languages'] ?? '')))));
        if ($phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $zanzibarNationalId === '') {
            staff_json_or_redirect('settings', false, 'Enter a valid phone, email, and ID', 'error');
        }

        if ($staff['staff_type'] === 'tour_guide' && $tourGuideId === '') {
            staff_json_or_redirect('settings', false, 'Tour Guide ID is required', 'error');
        }
        if ($staff['staff_type'] === 'driver' && $driverLicence === '') {
            staff_json_or_redirect('settings', false, 'Driver licence is required', 'error');
        }

        $stmt = Database::pdo()->prepare('UPDATE staffs SET phone = ?, email = ?, zanzibar_national_id = ?, languages = ?, tour_guide_id = ?, driver_licence = ? WHERE id = ? AND site_id = ?');
        $stmt->execute([
            $phone,
            $email,
            $zanzibarNationalId,
            json_encode($languages, JSON_UNESCAPED_UNICODE),
            $staff['staff_type'] === 'tour_guide' ? $tourGuideId : null,
            $staff['staff_type'] === 'driver' ? $driverLicence : null,
            (int) $staff['id'],
            (int) $staff['site_id'],
        ]);
        staff_json_or_redirect('settings', true, 'Profile updated');
    }

    if ($action === 'theme') {
        $theme = (string) ($_POST['staff_theme'] ?? 'light');
        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = 'light';
        }
        $stmt = Database::pdo()->prepare('UPDATE staffs SET staff_theme = ? WHERE id = ? AND site_id = ?');
        $stmt->execute([$theme, (int) $staff['id'], (int) $staff['site_id']]);
        staff_json_or_redirect('settings', true, 'Theme updated');
    }

    if ($action === 'notification_sound') {
        $enabled = (int) ($_POST['staff_sound_enabled'] ?? 0) === 1 ? 1 : 0;
        $stmt = Database::pdo()->prepare('UPDATE staffs SET staff_sound_enabled = ? WHERE id = ? AND site_id = ?');
        $stmt->execute([$enabled, (int) $staff['id'], (int) $staff['site_id']]);
        staff_json_or_redirect('settings', true, $enabled === 1 ? 'Notification on' : 'Notification off');
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($currentPassword, (string) $staff['password_hash'])) {
            staff_json_or_redirect('settings', false, 'Current password is incorrect', 'error');
        }
        if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            staff_json_or_redirect('settings', false, 'Password must match and be at least 8 characters', 'error');
        }

        $stmt = Database::pdo()->prepare('UPDATE staffs SET password_hash = ? WHERE id = ? AND site_id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $staff['id'], (int) $staff['site_id']]);
        staff_json_or_redirect('settings', true, 'Password changed');
    }

    if ($action === 'upload') {
        if ((string) $staff['staff_type'] === 'driver') {
            staff_json_or_redirect('dashboard', false, 'Drivers cannot access uploads', 'error');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? 'Zanzibar'));
        $category = trim((string) ($_POST['category'] ?? 'Staff'));
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($title === '') {
            staff_json_or_redirect('upload-image', false, 'Image title is required', 'error');
        }

        $files = staff_uploaded_images();
        if (!$files) {
            staff_json_or_redirect('upload-image', false, 'Choose or take at least one image', 'error');
        }
        if (count($files) > 6) {
            staff_json_or_redirect('upload-image', false, 'Upload up to 6 images', 'error');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO staff_uploads (site_id, staff_id, title, location, category, image_path, note) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($files as $index => $file) {
            $imagePath = staff_save_upload_image_file($file, (int) $staff['site_id']);
            $imageTitle = count($files) > 1 ? $title . ' ' . ($index + 1) : $title;
            $stmt->execute([(int) $staff['site_id'], (int) $staff['id'], $imageTitle, $location ?: 'Zanzibar', $category ?: 'Staff', $imagePath, $note]);
        }
        staff_json_or_redirect('upload-image', true, count($files) > 1 ? count($files) . ' images sent to admin' : 'Image sent to admin');
    }

    if ($action === 'delete_upload') {
        if ((string) $staff['staff_type'] === 'driver') {
            staff_json_or_redirect('dashboard', false, 'Drivers cannot access uploads', 'error');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $returnView = in_array((string) ($_POST['return_view'] ?? 'upload'), ['upload', 'uploads'], true) ? (string) $_POST['return_view'] : 'upload';
        if ($id < 1) {
            staff_json_or_redirect($returnView, false, 'Upload id is required', 'error');
        }
        $stmt = Database::pdo()->prepare('SELECT id, image_path FROM staff_uploads WHERE id = ? AND staff_id = ? AND site_id = ? AND status = "pending" LIMIT 1');
        $stmt->execute([$id, (int) $staff['id'], (int) $staff['site_id']]);
        $upload = $stmt->fetch();
        if (!$upload) {
            staff_json_or_redirect($returnView, false, 'Only waiting uploads can be deleted', 'error');
        }
        $deleteStmt = Database::pdo()->prepare('DELETE FROM staff_uploads WHERE id = ? AND staff_id = ? AND site_id = ? AND status = "pending"');
        $deleteStmt->execute([$id, (int) $staff['id'], (int) $staff['site_id']]);
        $filePath = staff_public_upload_path_to_file((string) $upload['image_path']);
        if ($filePath && is_file($filePath)) {
            unlink($filePath);
        }
        staff_json_or_redirect($returnView, true, 'Upload deleted');
    }

    if ($action === 'task_response') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $response = (string) ($_POST['response'] ?? '');
        if ($taskId < 1 || !in_array($response, ['accepted', 'declined'], true)) {
            staff_task_json_or_redirect(false, 'Choose a valid task response', 'error');
        }
        $stmt = Database::pdo()->prepare('UPDATE staff_tasks SET status = ?, response_at = NOW() WHERE id = ? AND staff_id = ? AND site_id = ? AND status = "assigned"');
        $stmt->execute([$response, $taskId, (int) $staff['id'], (int) $staff['site_id']]);
        if ($stmt->rowCount() < 1) {
            staff_task_json_or_redirect(false, 'Task response could not be saved', 'error');
        }
        staff_task_json_or_redirect(true, $response === 'accepted' ? 'Task accepted' : 'Task declined');
    }

    if ($action === 'task_complete') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $completionNote = trim((string) ($_POST['completion_note'] ?? ''));
        if ($taskId < 1 || $completionNote === '') {
            staff_task_json_or_redirect(false, 'Completion details are required', 'error');
        }
        $stmt = Database::pdo()->prepare('UPDATE staff_tasks SET status = "completed", completion_note = ?, completed_at = NOW() WHERE id = ? AND staff_id = ? AND site_id = ? AND status = "accepted"');
        $stmt->execute([$completionNote, $taskId, (int) $staff['id'], (int) $staff['site_id']]);
        if ($stmt->rowCount() < 1) {
            staff_task_json_or_redirect(false, 'Only accepted tasks can be completed', 'error');
        }
        staff_task_json_or_redirect(true, 'Task completion sent');
    }
}

$_SESSION['hyena_admin_selected_site_id'] = (int) $staff['site_id'];
$brandName = admin_setting('brand_name', 'Judylink');
$brandSubtitle = admin_setting('brand_subtitle', 'Tours & travel control');
$logoUrl = admin_setting('brand_logo', '/backend/assets/pwa-logo.webp');
$staffName = trim((string) $staff['first_name'] . ' ' . (string) $staff['second_name'] . ' ' . (string) $staff['third_name']);
$staffRole = str_replace('_', ' ', (string) $staff['staff_type']);
$canAccessStaffUploads = (string) $staff['staff_type'] !== 'driver';
$languages = json_decode((string) $staff['languages'], true);
$languageText = is_array($languages) ? implode(', ', $languages) : '';
$view = (string) ($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'tasks', 'upload', 'upload-image', 'uploads', 'settings'], true)) {
    $view = 'dashboard';
}
if (!$canAccessStaffUploads && in_array($view, ['upload', 'upload-image', 'uploads'], true)) {
    $view = 'dashboard';
}
$pageTitle = ['dashboard' => 'Staff Dashboard', 'tasks' => 'Tasks', 'upload' => 'Upload', 'upload-image' => 'Upload Image', 'uploads' => 'All Uploads', 'settings' => 'Settings'][$view];
$themeMode = in_array((string) ($staff['staff_theme'] ?? 'light'), ['light', 'dark'], true) ? (string) $staff['staff_theme'] : 'light';
$soundEnabled = (int) ($staff['staff_sound_enabled'] ?? 1) === 1;
$notice = (string) ($_GET['saved'] ?? '');
$error = (string) ($_GET['error'] ?? '');

$newTaskStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM staff_tasks WHERE staff_id = ? AND site_id = ? AND status = "assigned"');
$newTaskStmt->execute([(int) $staff['id'], (int) $staff['site_id']]);
$newTaskCount = (int) $newTaskStmt->fetchColumn();

$staffUploads = [];
$staffTasks = [];
$totalTaskCount = 0;
$uploadCount = 0;
$acceptedTaskCount = 0;
$completedTaskCount = 0;
$declinedTaskCount = 0;

if ($view === 'dashboard' || $view === 'tasks') {
    $taskCountStmt = Database::pdo()->prepare('SELECT status, COUNT(*) AS total FROM staff_tasks WHERE staff_id = ? AND site_id = ? GROUP BY status');
    $taskCountStmt->execute([(int) $staff['id'], (int) $staff['site_id']]);
    foreach ($taskCountStmt->fetchAll() as $taskCountRow) {
        $status = (string) $taskCountRow['status'];
        $count = (int) $taskCountRow['total'];
        $totalTaskCount += $count;
        if ($status === 'accepted') {
            $acceptedTaskCount += $count;
        } elseif ($status === 'completed') {
            $completedTaskCount += $count;
        } elseif (in_array($status, ['declined', 'cancelled'], true)) {
            $declinedTaskCount += $count;
        }
    }
}

if ($canAccessStaffUploads && $view === 'dashboard') {
    $uploadCountStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM staff_uploads WHERE staff_id = ? AND site_id = ? AND status = "pending"');
    $uploadCountStmt->execute([(int) $staff['id'], (int) $staff['site_id']]);
    $uploadCount = (int) $uploadCountStmt->fetchColumn();
}

if ($view === 'tasks') {
    $taskStmt = Database::pdo()->prepare('SELECT * FROM staff_tasks WHERE staff_id = ? AND site_id = ? ORDER BY task_date DESC, created_at DESC LIMIT 120');
    $taskStmt->execute([(int) $staff['id'], (int) $staff['site_id']]);
    $staffTasks = $taskStmt->fetchAll();
    $staffTasks = staff_attach_task_guests($staffTasks);
}

if ($canAccessStaffUploads && $view === 'uploads') {
    $uploadStmt = Database::pdo()->prepare('SELECT * FROM staff_uploads WHERE staff_id = ? AND site_id = ? AND status = "pending" ORDER BY created_at DESC');
    $uploadStmt->execute([(int) $staff['id'], (int) $staff['site_id']]);
    $staffUploads = $uploadStmt->fetchAll();
    $uploadCount = count($staffUploads);
}

$staffTaskSections = [
    [
        'key' => 'new',
        'title' => 'New Dutie',
        'icon' => 'fa-bell',
        'class' => 'border-amber-200 bg-amber-50 text-amber-700',
        'accent' => 'text-amber-600',
        'badge' => 'bg-amber-50 text-amber-700',
        'tasks' => array_values(array_filter($staffTasks, fn ($task) => ($task['status'] ?? '') === 'assigned')),
    ],
    [
        'key' => 'progress',
        'title' => 'In progress',
        'icon' => 'fa-spinner',
        'class' => 'border-sky-200 bg-sky-50 text-sky-700',
        'accent' => 'text-sky-600',
        'badge' => 'bg-sky-50 text-sky-700',
        'tasks' => array_values(array_filter($staffTasks, fn ($task) => ($task['status'] ?? '') === 'accepted')),
    ],
    [
        'key' => 'completed',
        'title' => 'Completed',
        'icon' => 'fa-circle-check',
        'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'accent' => 'text-emerald-600',
        'badge' => 'bg-emerald-50 text-emerald-700',
        'tasks' => array_values(array_filter($staffTasks, fn ($task) => ($task['status'] ?? '') === 'completed')),
    ],
    [
        'key' => 'declined',
        'title' => 'Declined',
        'icon' => 'fa-circle-xmark',
        'class' => 'border-red-200 bg-red-50 text-red-700',
        'accent' => 'text-red-600',
        'badge' => 'bg-red-50 text-red-700',
        'tasks' => array_values(array_filter($staffTasks, fn ($task) => in_array(($task['status'] ?? ''), ['declined', 'cancelled'], true))),
    ],
];
$taskPage = (string) ($_GET['task_page'] ?? 'new');
$taskSectionKeys = array_column($staffTaskSections, 'key');
if (!in_array($taskPage, $taskSectionKeys, true)) {
    $taskPage = 'new';
}
$activeTaskSection = $staffTaskSections[array_search($taskPage, $taskSectionKeys, true)];
if (count($activeTaskSection['tasks']) === 0) {
    foreach ($staffTaskSections as $section) {
        if (count($section['tasks']) > 0) {
            $taskPage = (string) $section['key'];
            $activeTaskSection = $section;
            break;
        }
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= staff_dash_h($pageTitle) ?> | <?= staff_dash_h($brandName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style type="text/tailwindcss">
    @layer components {
      .admin-card { @apply rounded-lg border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5; }
      .form-control { @apply w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal outline-none transition focus:border-[#2FABE1] focus:ring-4 focus:ring-sky-100; }
      .secondary { @apply inline-flex items-center justify-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200; }
      .primary { @apply inline-flex items-center justify-center gap-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100; }
      .danger { @apply inline-flex items-center justify-center gap-2 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100; }
      .side-link.active { @apply bg-sky-500/15 text-white; }
      .side-link.active .side-icon { @apply bg-[#2FABE1] text-white; }
    }
  </style>
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .staff-shell { grid-template-columns: 244px minmax(0, 1fr); }
    .sidebar { transition: transform .08s ease-out; will-change: transform; }
    .modal-backdrop { display: none; }
    .modal-backdrop.active { display: flex; }
    .upload-readable-text { text-shadow: 0 1px 2px rgba(15, 23, 42, .28); }
    #sidebarToggleButton { display: grid; pointer-events: none; visibility: hidden; }
    body.sidebar-collapsed #sidebarToggleButton { pointer-events: auto; visibility: visible; }
    body.sidebar-collapsed .staff-shell { grid-template-columns: 82px minmax(0, 1fr); }
    body.sidebar-collapsed .sidebar-label,
    body.sidebar-collapsed .sidebar-subtitle,
    body.sidebar-collapsed .sidebar-footer { display: none; }
    body.sidebar-collapsed .sidebar { padding-left: 0.75rem; padding-right: 0.75rem; }
    body.sidebar-collapsed .side-link { width: 3.625rem; justify-content: center; padding-left: 0; padding-right: 0; }
    body.sidebar-collapsed .side-icon { margin-left: auto; margin-right: auto; }
    body.sidebar-collapsed .brand-wrap { justify-content: center; }
    body.sidebar-collapsed .brand-wrap > button { display: none; }
    body:not(.admin-dark) .sidebar { border-right: 1px solid #e2e8f0; background: #fff !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .brand-wrap { border-color: #e2e8f0 !important; }
    body:not(.admin-dark) .sidebar .sidebar-label { color: #172033 !important; }
    body:not(.admin-dark) .sidebar .sidebar-subtitle,
    body:not(.admin-dark) .sidebar .sidebar-footer { color: #64748b !important; }
    body:not(.admin-dark) .sidebar .side-link { color: #475569 !important; }
    body:not(.admin-dark) .sidebar .side-link:hover { background: #f1f5f9 !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .side-link.active { background: rgba(47, 171, 225, 0.12) !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .side-icon { background: #f1f5f9 !important; color: #FAA723 !important; }
    body:not(.admin-dark) .sidebar .side-link.active .side-icon { background: #2FABE1 !important; color: #fff !important; }
    body:not(.admin-dark) .sidebar .brand-wrap > button { background: #f1f5f9 !important; color: #475569 !important; }
    body.admin-dark { background: #0f172a; color: #e2e8f0; }
    body.admin-dark header { border-color: #1e293b; background: rgba(15, 23, 42, 0.92); }
    body.admin-dark main { background: #0f172a; }
    body.admin-dark .admin-card { border-color: #334155; background: #1e293b; color: #e2e8f0; box-shadow: none; }
    body.admin-dark .form-control { border-color: #475569; background: #0f172a; color: #e2e8f0; }
    body.admin-dark .secondary { background: #334155; color: #e2e8f0; }
    body.admin-dark .secondary:hover { background: #475569; }
    body.admin-dark .primary { background: #0c4a6e; color: #e0f2fe; }
    body.admin-dark .primary:hover { background: #075985; }
    body.admin-dark .danger { background: #7f1d1d; color: #fee2e2; }
    body.admin-dark .danger:hover { background: #991b1b; }
    body.admin-dark .text-slate-900,
    body.admin-dark .text-slate-800,
    body.admin-dark .text-slate-700,
    body.admin-dark .text-slate-600 { color: #e2e8f0 !important; }
    body.admin-dark .text-slate-500,
    body.admin-dark .text-slate-400 { color: #94a3b8 !important; }
    body.admin-dark .bg-white { background-color: #1e293b !important; }
    body.admin-dark .bg-slate-50,
    body.admin-dark .bg-slate-100 { background-color: #0f172a !important; }
    body.admin-dark .border-slate-200 { border-color: #334155 !important; }
    @media (max-width: 1023px) {
      .staff-shell { grid-template-columns: 1fr; }
      .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 40; width: min(244px, 86vw); transform: translateX(-100%); }
      body.sidebar-open .sidebar { transform: translateX(0); }
      #sidebarToggleButton { pointer-events: auto; visibility: visible; }
      body.sidebar-open #sidebarToggleButton { pointer-events: none; visibility: hidden; }
      .mobile-overlay { display: none; }
      body.sidebar-open .mobile-overlay { display: block; }
      body.sidebar-collapsed .staff-shell { grid-template-columns: 1fr; }
      body.sidebar-collapsed .sidebar-label,
      body.sidebar-collapsed .sidebar-subtitle,
      body.sidebar-collapsed .sidebar-footer { display: block; }
      .side-link,
      body.sidebar-collapsed .side-link { width: 100%; justify-content: flex-start; padding-left: 0.75rem; padding-right: 0.75rem; }
      .side-icon,
      body.sidebar-collapsed .side-icon { margin-left: 0; margin-right: 0; }
      body.sidebar-collapsed .brand-wrap { justify-content: space-between; }
      body.sidebar-collapsed .brand-wrap > button { display: grid; }
    }
  </style>
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900">
  <div class="mobile-overlay fixed inset-0 z-30 bg-slate-950/50 lg:hidden" onclick="closeSidebar()"></div>
  <div class="staff-shell grid min-h-screen">
    <aside class="sidebar flex h-screen flex-col gap-4 bg-[#172033] p-3.5 text-white lg:sticky lg:top-0 lg:p-4">
      <div class="brand-wrap flex items-center justify-between gap-2.5 border-b border-white/10 pb-4">
        <div class="flex min-w-0 items-center gap-3">
          <img class="h-11 w-11 shrink-0 rounded-lg object-contain" src="<?= staff_dash_h($logoUrl) ?>" alt="<?= staff_dash_h($brandName) ?>">
          <div>
            <h1 class="sidebar-label text-base font-medium leading-tight"><?= staff_dash_h($brandName) ?></h1>
            <p class="sidebar-subtitle mt-0.5 text-xs text-white/50"><?= staff_dash_h($brandSubtitle) ?></p>
          </div>
        </div>
        <button class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white/10 text-xs text-white transition hover:bg-white/15" type="button" onclick="closeSideNav()" aria-label="Close sidebar" title="Close sidebar"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <nav id="staffSideNav" class="mt-2 grid gap-1.5">
        <a href="/backend/staff-dashboard.php" class="staffPanelLink side-link <?= $view === 'dashboard' ? 'active' : '' ?> flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-white/65 no-underline hover:bg-white/10 hover:text-white">
          <span class="side-icon grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-xs text-[#FAA723]"><i class="fa-solid fa-chart-line"></i></span>
          <span class="sidebar-label">Dashboard</span>
        </a>
        <a href="/backend/staff-dashboard.php?view=tasks&amp;task_page=new" class="staffPanelLink side-link <?= $view === 'tasks' ? 'active' : '' ?> flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-white/65 no-underline hover:bg-white/10 hover:text-white">
          <span class="side-icon grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-xs text-[#FAA723]"><i class="fa-solid fa-list-check"></i></span>
          <span class="sidebar-label">Tasks</span>
          <?php if ($newTaskCount > 0): ?><span class="ml-auto rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-semibold text-white sidebar-label"><?= $newTaskCount ?></span><?php endif; ?>
        </a>
        <?php if ($canAccessStaffUploads): ?>
          <a href="/backend/staff-dashboard.php?view=upload" class="staffPanelLink side-link <?= in_array($view, ['upload', 'upload-image', 'uploads'], true) ? 'active' : '' ?> flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-white/65 no-underline hover:bg-white/10 hover:text-white">
            <span class="side-icon grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-xs text-[#FAA723]"><i class="fa-solid fa-cloud-arrow-up"></i></span>
            <span class="sidebar-label">Upload</span>
          </a>
        <?php endif; ?>
        <a href="/backend/staff-dashboard.php?view=settings" class="staffPanelLink side-link <?= $view === 'settings' ? 'active' : '' ?> flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-white/65 no-underline hover:bg-white/10 hover:text-white">
          <span class="side-icon grid h-8 w-8 place-items-center rounded-lg bg-white/10 text-xs text-[#FAA723]"><i class="fa-solid fa-gear"></i></span>
          <span class="sidebar-label">Settings</span>
        </a>
      </nav>
      <div class="sidebar-footer mt-auto hidden gap-3 text-sm text-white/50 lg:grid">
        <span>Signed in as <?= staff_dash_h($staff['username']) ?></span>
      </div>
    </aside>
    <div class="min-w-0">
      <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 px-5 py-3 backdrop-blur lg:px-7">
        <div class="flex items-center justify-between gap-3">
          <button id="sidebarToggleButton" class="grid h-8 w-8 place-items-center rounded-md bg-slate-100 text-xs text-slate-700 transition hover:bg-slate-200" type="button" onclick="toggleSidebar()" aria-label="Open sidebar" title="Open sidebar"><i class="fa-solid fa-bars"></i></button>
          <div class="flex items-center gap-2">
            <span class="hidden max-w-40 truncate text-sm text-slate-600 sm:inline"><?= staff_dash_h($staffName) ?></span>
            <button class="grid h-9 w-9 place-items-center rounded-md bg-slate-100 text-sm text-slate-700 transition hover:bg-slate-200" type="button" onclick="confirmStaffLogout()" aria-label="Logout" title="Logout"><i class="fa-solid fa-power-off"></i></button>
          </div>
        </div>
      </header>
      <main class="max-w-full overflow-x-hidden p-5 lg:p-7">
        <section id="staffPanelContent" class="grid min-w-0 max-w-full gap-4" data-view="<?= staff_dash_h($view) ?>" data-new-task-count="<?= (int) $newTaskCount ?>" data-sound-enabled="<?= $soundEnabled ? '1' : '0' ?>">
          <?php if ($view === 'dashboard'): ?>
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h2 class="text-xl font-medium">Staff Dashboard</h2>
              <p class="mt-1 text-sm text-slate-500">Welcome, <?= staff_dash_h($staff['first_name']) ?>. Your work summary is ready.</p>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="admin-card flex min-h-28 items-center justify-between gap-3 p-4">
              <div class="min-w-0">
                <span class="text-sm font-medium text-slate-500">Role</span>
                <div class="mt-1 truncate text-xl font-medium capitalize tracking-tight"><?= staff_dash_h($staffRole) ?></div>
                <p class="mt-1 truncate text-sm text-slate-500"><?= staff_dash_h($staff['username']) ?></p>
              </div>
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600"><i class="fa-solid fa-id-badge"></i></span>
            </article>
            <article class="admin-card flex min-h-28 items-center justify-between gap-3 p-4">
              <div class="min-w-0">
                <span class="text-sm font-medium text-slate-500">Languages</span>
                <div class="mt-1 text-xl font-medium tracking-tight"><?= is_array($languages) ? count($languages) : 0 ?></div>
                <p class="mt-1 truncate text-sm text-slate-500"><?= staff_dash_h($languageText ?: 'No languages set') ?></p>
              </div>
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600"><i class="fa-solid fa-language"></i></span>
            </article>
            <article class="admin-card flex min-h-28 items-center justify-between gap-3 p-4">
              <div class="min-w-0">
                <span class="text-sm font-medium text-slate-500">Duties</span>
                <div class="mt-1 text-xl font-medium tracking-tight"><?= $totalTaskCount ?></div>
                <p class="mt-1 truncate text-sm text-slate-500"><?= $newTaskCount ?> new, <?= $acceptedTaskCount ?> accepted</p>
              </div>
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i class="fa-solid fa-clipboard-list"></i></span>
            </article>
            <?php if ($canAccessStaffUploads): ?>
              <article class="admin-card flex min-h-28 items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                  <span class="text-sm font-medium text-slate-500">Uploads</span>
                  <div class="mt-1 text-xl font-medium tracking-tight"><?= $uploadCount ?></div>
                  <p class="mt-1 truncate text-sm text-slate-500">Recent images sent</p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600"><i class="fa-solid fa-images"></i></span>
              </article>
            <?php endif; ?>
          </div>
          <?php elseif ($view === 'profile'): ?>
          <div>
            <h2 class="text-xl font-medium">Profile</h2>
            <p class="mt-1 text-sm text-slate-500">Review your profile and update editable contact details.</p>
          </div>
          <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
            <article class="admin-card">
              <img class="mx-auto h-28 w-28 rounded-xl bg-slate-100 object-cover" src="<?= staff_dash_h($staff['passport_image_path']) ?>" alt="">
              <h3 class="mt-4 text-center text-lg font-semibold text-slate-900"><?= staff_dash_h($staffName) ?></h3>
              <p class="mt-1 text-center text-sm capitalize text-slate-500"><?= staff_dash_h($staffRole) ?></p>
            </article>

            <article class="admin-card">
              <h3 class="mb-4 text-base font-medium">Profile Details</h3>
              <form method="post" class="grid gap-4">
                <input type="hidden" name="action" value="profile">
                <dl class="grid gap-3 sm:grid-cols-2">
                  <?= staff_detail_card('Username', $staff['username']) ?>
                  <label class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-medium uppercase text-slate-400">Phone</dt>
                    <dd class="mt-1"><input class="form-control" name="phone" value="<?= staff_dash_h($staff['phone']) ?>" required></dd>
                  </label>
                  <label class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-medium uppercase text-slate-400">Email</dt>
                    <dd class="mt-1"><input class="form-control" name="email" type="email" value="<?= staff_dash_h($staff['email']) ?>" required></dd>
                  </label>
                  <label class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-medium uppercase text-slate-400">Zanzibar/National ID</dt>
                    <dd class="mt-1"><input class="form-control" name="zanzibar_national_id" value="<?= staff_dash_h($staff['zanzibar_national_id']) ?>" required></dd>
                  </label>
                  <label class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs font-medium uppercase text-slate-400">Languages</dt>
                    <dd class="mt-1"><input class="form-control" name="languages" value="<?= staff_dash_h($languageText) ?>" placeholder="English, Swahili"></dd>
                  </label>
                  <?php if ($staff['staff_type'] === 'tour_guide'): ?>
                    <label class="rounded-lg bg-slate-50 p-3">
                      <dt class="text-xs font-medium uppercase text-slate-400">Tour Guide ID</dt>
                      <dd class="mt-1"><input class="form-control" name="tour_guide_id" value="<?= staff_dash_h($staff['tour_guide_id'] ?? '') ?>" required></dd>
                    </label>
                  <?php endif; ?>
                  <?php if ($staff['staff_type'] === 'driver'): ?>
                    <label class="rounded-lg bg-slate-50 p-3">
                      <dt class="text-xs font-medium uppercase text-slate-400">Driver Licence</dt>
                      <dd class="mt-1"><input class="form-control" name="driver_licence" value="<?= staff_dash_h($staff['driver_licence'] ?? '') ?>" required></dd>
                    </label>
                  <?php endif; ?>
                  <?= staff_detail_card('Registered', $staff['created_at']) ?>
                </dl>
                <button class="primary w-fit" type="submit">Save Profile</button>
              </form>
            </article>
          </div>
          <?php elseif ($view === 'tasks'): ?>
          <div id="staffTaskPanel" class="grid gap-4" data-task-page="<?= staff_dash_h($taskPage) ?>">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
              <div>
                <h2 class="text-xl font-medium"><?= staff_dash_h($activeTaskSection['title']) ?></h2>
                <p class="mt-1 text-sm text-slate-500">Review duties from admin and send completion details.</p>
              </div>
              <?php if ($newTaskCount > 0): ?><span class="w-fit rounded-md border border-red-500 px-3 py-1.5 text-xs font-medium text-red-600"><?= $newTaskCount ?> new task<?= $newTaskCount === 1 ? '' : 's' ?></span><?php endif; ?>
            </div>
            <nav class="grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2 xl:grid-cols-4">
              <?php foreach ($staffTaskSections as $section): ?>
                <?php $sectionTasks = $section['tasks']; ?>
                <?php if (count($sectionTasks) === 0) continue; ?>
                <?php $isActiveTaskPage = $taskPage === $section['key']; ?>
                <a class="staffPanelLink staffTaskPageLink secondary min-w-0 justify-between no-underline <?= $isActiveTaskPage ? 'ring-2 ring-sky-200' : '' ?>" href="/backend/staff-dashboard.php?view=tasks&amp;task_page=<?= staff_dash_h($section['key']) ?>">
                  <span class="inline-flex min-w-0 items-center gap-2 truncate"><i class="fa-solid <?= staff_dash_h($section['icon']) ?> <?= staff_dash_h($section['accent']) ?>"></i><span class="truncate"><?= staff_dash_h($section['title']) ?></span></span>
                  <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] <?= staff_dash_h($section['badge']) ?>"><?= count($sectionTasks) ?></span>
                </a>
              <?php endforeach; ?>
            </nav>
            <div class="grid gap-3">
              <?php $activeTasks = $activeTaskSection['tasks']; ?>
              <?php if ($activeTasks): ?>
                <div class="grid min-w-0 gap-3 xl:grid-cols-[repeat(2,minmax(0,1fr))]">
                  <?php foreach ($activeTasks as $task): ?>
                    <?= staff_task_card($task) ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <article class="admin-card p-3">
                  <p class="text-sm text-slate-500">No <?= staff_dash_h(strtolower((string) $activeTaskSection['title'])) ?> tasks.</p>
                </article>
              <?php endif; ?>
            </div>
          </div>
          <?php elseif ($view === 'upload'): ?>
          <div>
            <h2 class="text-xl font-medium">Upload</h2>
            <p class="mt-1 text-sm text-slate-500">Send tour photos from your phone gallery, PC, or camera.</p>
          </div>
          <article class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
              <a class="staffPanelLink secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm no-underline" href="/backend/staff-dashboard.php?view=upload-image"><i class="fa-solid fa-cloud-arrow-up text-sky-600"></i><span>Upload Image</span></a>
              <a class="staffPanelLink secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm no-underline" href="/backend/staff-dashboard.php?view=uploads"><i class="fa-solid fa-images text-rose-600"></i><span>View Uploads</span></a>
            </div>
          </article>
          <?php elseif ($view === 'upload-image'): ?>
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h2 class="text-xl font-medium">Upload Image</h2>
              <p class="mt-1 text-sm text-slate-500">Send tour photos from your phone gallery, PC, or camera.</p>
            </div>
            <a class="staffPanelLink secondary inline-flex w-fit items-center justify-center gap-2 no-underline" href="/backend/staff-dashboard.php?view=upload"><i class="fa-solid fa-arrow-left"></i>Previous</a>
          </div>
          <article class="admin-card max-w-2xl">
            <form id="staffUploadForm" method="post" enctype="multipart/form-data" class="grid gap-4">
              <input type="hidden" name="action" value="upload">
              <label class="grid gap-1.5 text-sm font-medium text-slate-700">Title
                <input class="form-control" name="title" placeholder="Stone Town sunset" required>
              </label>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium text-slate-700">Location
                  <input class="form-control" name="location" value="Zanzibar">
                </label>
                <label class="grid gap-1.5 text-sm font-medium text-slate-700">Category
                  <select class="form-control" name="category">
                    <option value="Staff">Staff</option>
                    <option value="Activities">Activities</option>
                    <option value="Beach">Beach</option>
                    <option value="Culture">Culture</option>
                    <option value="Nature">Nature</option>
                    <option value="Tours">Tours</option>
                    <option value="Hotels">Hotels</option>
                    <option value="Food">Food</option>
                  </select>
                </label>
              </div>
              <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="pickStaffImage('staffImageInput')"><i class="fa-solid fa-image text-sky-600"></i><span>Choose Images</span></button>
                <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="openStaffCamera()"><i class="fa-solid fa-camera text-emerald-600"></i><span>Take Picture</span></button>
                <input id="staffImageInput" class="hidden" name="image[]" type="file" accept="image/*" multiple onchange="addStaffUploadFiles(this.files)">
                <input id="staffCameraInput" class="hidden" name="camera_image" type="file" accept="image/*" capture="environment" onchange="addStaffUploadFiles(this.files)">
              </div>
              <div id="staffUploadPreviewBox" class="hidden grid-cols-3 gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2"></div>
              <label class="grid gap-1.5 text-sm font-medium text-slate-700">Note
                <textarea class="form-control min-h-24" name="note" placeholder="Optional note for admin"></textarea>
              </label>
              <div class="grid gap-2 sm:flex sm:justify-end">
                <a class="staffPanelLink secondary inline-flex w-full items-center justify-center gap-2 no-underline sm:w-auto" href="/backend/staff-dashboard.php?view=upload">Cancel</a>
                <button class="primary w-full sm:w-auto" type="submit"><i class="fa-solid fa-cloud-arrow-up mr-2"></i>Send Images</button>
              </div>
            </form>
          </article>
          <?php elseif ($view === 'uploads'): ?>
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h2 class="text-xl font-medium">All Uploads</h2>
              <p class="mt-1 text-sm text-slate-500">Every image you have sent to admin.</p>
            </div>
            <a class="staffPanelLink secondary inline-flex w-fit items-center justify-center gap-2 no-underline" href="/backend/staff-dashboard.php?view=upload"><i class="fa-solid fa-arrow-left"></i>Previous</a>
          </div>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
            <?php foreach ($staffUploads as $upload): ?>
              <article class="overflow-hidden rounded-lg border border-slate-200 bg-white p-1.5">
                <div class="grid aspect-square w-full place-items-center overflow-hidden rounded-md border border-slate-100 bg-slate-100">
                  <img class="block max-h-full max-w-full object-contain p-1" src="<?= staff_dash_h($upload['image_path']) ?>" alt="">
                </div>
                <div class="grid gap-1 pt-1.5 sm:grid-cols-2">
                  <button
                    class="secondary inline-flex items-center justify-center gap-1.5 px-2 py-1 text-[11px]"
                    type="button"
                    onclick="openStaffUploadPreview(this)"
                    data-image="<?= staff_dash_h($upload['image_path']) ?>"
                    data-title="<?= staff_dash_h($upload['title']) ?>"
                    data-status="<?= staff_dash_h($upload['status']) ?>"
                    data-location="<?= staff_dash_h($upload['location']) ?>"
                    data-category="<?= staff_dash_h($upload['category']) ?>"
                    data-date="<?= staff_dash_h($upload['created_at']) ?>"
                    data-note="<?= staff_dash_h($upload['note'] ?? '') ?>"
                  ><i class="fa-regular fa-eye"></i>Preview</button>
                  <?php if ($upload['status'] === 'pending'): ?>
                    <form method="post" onsubmit="confirmStaffUploadDelete(event, this)">
                      <input type="hidden" name="action" value="delete_upload">
                      <input type="hidden" name="return_view" value="uploads">
                      <input type="hidden" name="id" value="<?= (int) $upload['id'] ?>">
                      <button class="danger inline-flex w-full items-center justify-center gap-1.5 px-2 py-1 text-[11px]" type="submit"><i class="fa-regular fa-trash-can"></i>Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
            <?php if (!$staffUploads): ?><p class="col-span-2 text-sm text-slate-500">No uploads yet.</p><?php endif; ?>
          </div>
          <?php else: ?>
          <div>
            <h2 class="text-xl font-medium">Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Choose appearance mode and update your password.</p>
          </div>
          <article class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-2">
              <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="openStaffModal('staffProfileModal')">
                <i class="fa-solid fa-user"></i><span>Profile</span>
              </button>
              <form method="post">
                <input type="hidden" name="action" value="theme">
                <input type="hidden" name="staff_theme" value="<?= $themeMode === 'dark' ? 'light' : 'dark' ?>">
                <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="submit" title="Current mode: <?= staff_dash_h(ucfirst($themeMode)) ?>">
                  <i class="fa-solid <?= $themeMode === 'dark' ? 'fa-moon' : 'fa-sun' ?>"></i><span><?= $themeMode === 'dark' ? 'Dark Mode' : 'Light Mode' ?></span>
                </button>
              </form>
              <form method="post">
                <input type="hidden" name="action" value="notification_sound">
                <input type="hidden" name="staff_sound_enabled" value="<?= $soundEnabled ? '0' : '1' ?>">
                <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="submit" title="Task notification is <?= $soundEnabled ? 'on' : 'off' ?>">
                  <i class="fa-solid <?= $soundEnabled ? 'fa-volume-high' : 'fa-volume-xmark' ?>"></i><span>Notification <?= $soundEnabled ? 'On' : 'Off' ?></span>
                </button>
              </form>
              <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="openStaffModal('staffPasswordModal')">
                <i class="fa-solid fa-key"></i><span>Change Password</span>
              </button>
            </div>
          </article>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>

  <div id="staffProfileModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
    <div class="my-3 w-full max-w-4xl rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
      <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h3 class="text-lg font-medium">Profile Details</h3>
        <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeStaffModal('staffProfileModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
        <article class="admin-card">
          <img class="mx-auto h-28 w-28 rounded-xl bg-slate-100 object-cover" src="<?= staff_dash_h($staff['passport_image_path']) ?>" alt="">
          <h3 class="mt-4 text-center text-lg font-semibold text-slate-900"><?= staff_dash_h($staffName) ?></h3>
          <p class="mt-1 text-center text-sm capitalize text-slate-500"><?= staff_dash_h($staffRole) ?></p>
        </article>

        <article class="admin-card">
          <form method="post" class="grid gap-4">
            <input type="hidden" name="action" value="profile">
            <dl class="grid gap-3 sm:grid-cols-2">
              <?= staff_detail_card('Username', $staff['username']) ?>
              <label class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-medium uppercase text-slate-400">Phone</dt>
                <dd class="mt-1"><input class="form-control" name="phone" value="<?= staff_dash_h($staff['phone']) ?>" required></dd>
              </label>
              <label class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-medium uppercase text-slate-400">Email</dt>
                <dd class="mt-1"><input class="form-control" name="email" type="email" value="<?= staff_dash_h($staff['email']) ?>" required></dd>
              </label>
              <label class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-medium uppercase text-slate-400">Zanzibar/National ID</dt>
                <dd class="mt-1"><input class="form-control" name="zanzibar_national_id" value="<?= staff_dash_h($staff['zanzibar_national_id']) ?>" required></dd>
              </label>
              <label class="rounded-lg bg-slate-50 p-3">
                <dt class="text-xs font-medium uppercase text-slate-400">Languages</dt>
                <dd class="mt-1"><input class="form-control" name="languages" value="<?= staff_dash_h($languageText) ?>" placeholder="English, Swahili"></dd>
              </label>
              <?php if ($staff['staff_type'] === 'tour_guide'): ?>
                <label class="rounded-lg bg-slate-50 p-3">
                  <dt class="text-xs font-medium uppercase text-slate-400">Tour Guide ID</dt>
                  <dd class="mt-1"><input class="form-control" name="tour_guide_id" value="<?= staff_dash_h($staff['tour_guide_id'] ?? '') ?>" required></dd>
                </label>
              <?php endif; ?>
              <?php if ($staff['staff_type'] === 'driver'): ?>
                <label class="rounded-lg bg-slate-50 p-3">
                  <dt class="text-xs font-medium uppercase text-slate-400">Driver Licence</dt>
                  <dd class="mt-1"><input class="form-control" name="driver_licence" value="<?= staff_dash_h($staff['driver_licence'] ?? '') ?>" required></dd>
                </label>
              <?php endif; ?>
              <?= staff_detail_card('Registered', $staff['created_at']) ?>
            </dl>
            <div class="grid gap-2 sm:flex sm:justify-end">
              <button class="secondary w-full sm:w-auto" type="button" onclick="closeStaffModal('staffProfileModal')">Cancel</button>
              <button class="primary w-full sm:w-auto" type="submit">Save Profile</button>
            </div>
          </form>
        </article>
      </div>
    </div>
  </div>

  <div id="staffPasswordModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
    <div class="my-3 w-full max-w-md rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
      <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h3 class="text-lg font-medium">Change Password</h3>
        <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeStaffModal('staffPasswordModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form method="post" class="grid gap-4">
        <input type="hidden" name="action" value="password">
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Current password
          <input class="form-control" name="current_password" type="password" autocomplete="current-password" required>
        </label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">New password
          <input class="form-control" name="new_password" type="password" autocomplete="new-password" required minlength="8">
        </label>
        <label class="grid gap-1.5 text-sm font-medium text-slate-700">Confirm password
          <input class="form-control" name="confirm_password" type="password" autocomplete="new-password" required minlength="8">
        </label>
        <div class="grid gap-2 sm:flex sm:justify-end">
          <button class="secondary w-full sm:w-auto" type="button" onclick="closeStaffModal('staffPasswordModal')">Cancel</button>
          <button class="primary w-full sm:w-auto" type="submit"><i class="fa-solid fa-key mr-2"></i>Change Password</button>
        </div>
      </form>
    </div>
  </div>

  <div id="staffUploadPreviewModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
    <div class="my-3 w-full max-w-3xl rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
      <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h3 class="text-lg font-medium">Upload Preview</h3>
        <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeStaffModal('staffUploadPreviewModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="grid gap-4 md:grid-cols-[minmax(0,1.35fr)_minmax(220px,.65fr)]">
        <div class="grid max-h-[70vh] place-items-center rounded-lg bg-slate-100 p-3">
          <img id="staffUploadPreviewModalImage" class="max-h-[66vh] max-w-full object-contain" src="" alt="">
        </div>
        <dl class="grid content-start gap-3">
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Title</dt>
            <dd id="staffUploadPreviewModalTitle" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Status</dt>
            <dd id="staffUploadPreviewModalStatus" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Location</dt>
            <dd id="staffUploadPreviewModalLocation" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Category</dt>
            <dd id="staffUploadPreviewModalCategory" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Date</dt>
            <dd id="staffUploadPreviewModalDate" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <dt class="text-xs font-medium uppercase text-slate-400">Note</dt>
            <dd id="staffUploadPreviewModalNote" class="mt-1 break-words text-sm font-semibold text-slate-900"></dd>
          </div>
        </dl>
      </div>
    </div>
  </div>

  <div id="staffCameraModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
    <div class="my-3 w-full max-w-md rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
      <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <h3 class="text-lg font-medium">Take Picture</h3>
        <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeStaffCamera()" title="Close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="overflow-hidden rounded-lg bg-slate-950">
        <video id="staffCameraVideo" class="aspect-[3/4] w-full object-cover" autoplay playsinline muted></video>
      </div>
      <div class="mt-4 grid gap-2 sm:flex sm:justify-end">
        <button class="secondary w-full sm:w-auto" type="button" onclick="closeStaffCamera()">Cancel</button>
        <button class="primary w-full sm:w-auto" type="button" onclick="captureStaffCameraImage()"><i class="fa-solid fa-camera mr-2"></i>Capture</button>
      </div>
    </div>
  </div>

  <audio id="staffTaskNotificationAudio" src="/backend/alerts/task-notification.mp3" preload="auto" loop></audio>

  <script>
    let savedStaffTheme = <?= json_encode($themeMode) ?>;
    let staffSoundEnabled = <?= json_encode($soundEnabled) ?>;
    let staffNewTaskCount = <?= (int) $newTaskCount ?>;
    let staffAudioUnlocked = false;
    const staffView = <?= json_encode($view) ?>;
    const staffNotice = <?= json_encode($notice) ?>;
    const staffError = <?= json_encode($error) ?>;
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) window.location.reload();
    });
    function resolvedStaffTheme(mode) {
      return mode === 'dark' ? 'dark' : 'light';
    }
    function previewStaffTheme(mode) {
      const theme = resolvedStaffTheme(mode || savedStaffTheme);
      document.body.classList.toggle('admin-dark', theme === 'dark');
      document.documentElement.style.colorScheme = theme;
    }

    if (localStorage.getItem('hyenaStaffSidebarCollapsed') === '1') {
      document.body.classList.add('sidebar-collapsed');
    }
    previewStaffTheme(savedStaffTheme);
    const staffToast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2200,
      timerProgressBar: true,
      width: '20rem',
    });
    if (staffNotice) {
      staffToast.fire({
        icon: 'success',
        title: staffNotice,
      });
      const cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete('saved');
      cleanUrl.searchParams.delete('error');
      history.replaceState(null, '', cleanUrl.toString());
    }
    if (staffError) {
      staffToast.fire({
        icon: 'error',
        title: staffError,
      });
      const cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete('saved');
      cleanUrl.searchParams.delete('error');
      history.replaceState(null, '', cleanUrl.toString());
    }

    function updateStaffPanelFromDocument(nextDocument) {
      const nextContent = nextDocument.querySelector('#staffPanelContent');
      const currentContent = document.querySelector('#staffPanelContent');
      if (!nextContent || !currentContent) return false;
      currentContent.replaceWith(nextContent);
      staffNewTaskCount = Number(nextContent.dataset.newTaskCount || 0);
      staffSoundEnabled = nextContent.dataset.soundEnabled === '1';

      const nextNav = nextDocument.querySelector('#staffSideNav');
      const currentNav = document.querySelector('#staffSideNav');
      if (nextNav && currentNav) currentNav.replaceWith(nextNav);

      if (nextDocument.title) document.title = nextDocument.title;
      closeSidebar();
      syncStaffTaskNotificationSound();
      return true;
    }

    function staffTaskNotificationAudio() {
      return document.querySelector('#staffTaskNotificationAudio');
    }

    function pauseStaffTaskNotificationSound() {
      const audio = staffTaskNotificationAudio();
      if (!audio) return;
      audio.pause();
      audio.currentTime = 0;
    }

    async function syncStaffTaskNotificationSound() {
      const audio = staffTaskNotificationAudio();
      if (!audio) return;
      if (!staffSoundEnabled || staffNewTaskCount < 1) {
        pauseStaffTaskNotificationSound();
        return;
      }
      try {
        await audio.play();
        staffAudioUnlocked = true;
      } catch (error) {
        staffAudioUnlocked = false;
      }
    }

    document.addEventListener('click', () => {
      if (!staffAudioUnlocked) syncStaffTaskNotificationSound();
    }, { once: false });

    async function pollStaffTaskAlertStatus() {
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('staff_alert_status', '1');
        const response = await fetch(url.toString(), {
          headers: { 'X-Requested-With': 'fetch' },
        });
        const data = await response.json();
        if (!response.ok || !data?.ok) return;
        const previousNewTaskCount = staffNewTaskCount;
        staffNewTaskCount = Number(data.newTaskCount || 0);
        staffSoundEnabled = Boolean(data.soundEnabled);
        syncStaffTaskNotificationSound();
        if (staffNewTaskCount !== previousNewTaskCount && document.querySelector('#staffPanelContent')) {
          await loadStaffPanelPage(window.location.href, false);
        }
      } catch (error) {
      }
    }

    async function loadStaffPanelPage(url, pushHistory = true) {
      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'fetch' },
      });
      const html = await response.text();
      const nextDocument = new DOMParser().parseFromString(html, 'text/html');
      if (!response.ok || !updateStaffPanelFromDocument(nextDocument)) {
        window.location.href = url;
        return;
      }
      if (pushHistory) history.pushState({ staffPanelUrl: url }, '', url);
    }

    async function submitStaffAjaxForm(form, options = {}) {
      const response = await fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch' },
        body: new FormData(form),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok || !data?.ok) throw new Error(data?.error || options.errorMessage || 'Could not save');
      staffToast.fire({ icon: 'success', title: data.saved || options.successMessage || 'Saved' });
      if (options.closeModal) closeStaffModal(options.closeModal);
      if (options.resetForm) form.reset();
      if (options.afterSuccess) options.afterSuccess(data);
      const nextUrl = options.nextUrl || window.location.href;
      await loadStaffPanelPage(nextUrl, false);
      return data;
    }

    document.addEventListener('click', async (event) => {
      const link = event.target.closest?.('.staffPanelLink');
      if (!link) return;
      event.preventDefault();
      try {
        await loadStaffPanelPage(link.href);
      } catch (error) {
        window.location.href = link.href;
      }
    });

    document.addEventListener('submit', async (event) => {
      const form = event.target.closest?.('.staffTaskActionForm');
      if (!form) return;
      event.preventDefault();
      const submitButton = event.submitter;
      if (submitButton) submitButton.disabled = true;
      try {
        await submitStaffAjaxForm(form, { errorMessage: 'Could not save task' });
      } catch (error) {
        staffToast.fire({ icon: 'error', title: error.message || 'Could not save task' });
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });

    document.addEventListener('submit', async (event) => {
      const form = event.target.closest?.('form');
      if (!form || form.matches('.staffTaskActionForm') || form.id === 'staffUploadForm') return;
      const action = form.querySelector('input[name="action"]')?.value;
      if (!['profile', 'password', 'theme', 'notification_sound'].includes(action)) return;
      event.preventDefault();
      const submitButton = event.submitter || form.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;
      try {
        await submitStaffAjaxForm(form, {
          closeModal: action === 'profile' ? 'staffProfileModal' : (action === 'password' ? 'staffPasswordModal' : ''),
          resetForm: action === 'password',
          afterSuccess: () => {
            if (action === 'theme') {
              savedStaffTheme = form.querySelector('input[name="staff_theme"]')?.value || savedStaffTheme;
              previewStaffTheme(savedStaffTheme);
            }
            if (action === 'notification_sound') {
              staffSoundEnabled = form.querySelector('input[name="staff_sound_enabled"]')?.value === '1';
              syncStaffTaskNotificationSound();
            }
          },
        });
      } catch (error) {
        staffToast.fire({ icon: 'error', title: error.message || 'Could not save' });
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });

    window.addEventListener('popstate', () => {
      loadStaffPanelPage(window.location.href, false).catch(() => window.location.reload());
    });

    syncStaffTaskNotificationSound();
    window.setInterval(pollStaffTaskAlertStatus, 10000);

    function openStaffModal(id) {
      document.querySelector(`#${id}`)?.classList.add('active');
    }

    function closeStaffModal(id) {
      document.querySelector(`#${id}`)?.classList.remove('active');
      previewStaffTheme(savedStaffTheme);
    }

    function setStaffPreviewText(id, value) {
      const element = document.querySelector(`#${id}`);
      if (element) element.textContent = value || '-';
    }

    function openStaffUploadPreview(button) {
      const image = document.querySelector('#staffUploadPreviewModalImage');
      if (image) image.src = button.dataset.image || '';
      setStaffPreviewText('staffUploadPreviewModalTitle', button.dataset.title);
      setStaffPreviewText('staffUploadPreviewModalStatus', button.dataset.status);
      setStaffPreviewText('staffUploadPreviewModalLocation', button.dataset.location);
      setStaffPreviewText('staffUploadPreviewModalCategory', button.dataset.category);
      setStaffPreviewText('staffUploadPreviewModalDate', button.dataset.date);
      setStaffPreviewText('staffUploadPreviewModalNote', button.dataset.note);
      openStaffModal('staffUploadPreviewModal');
    }

    async function confirmStaffUploadDelete(event, form) {
      event.preventDefault();
      const result = await Swal.fire({
        title: 'Delete this upload?',
        text: 'You can delete it only before admin approval.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Delete',
      });
      if (result.isConfirmed) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        try {
          await submitStaffAjaxForm(form, { errorMessage: 'Could not delete upload' });
        } catch (error) {
          staffToast.fire({ icon: 'error', title: error.message || 'Could not delete upload' });
        } finally {
          if (submitButton) submitButton.disabled = false;
        }
      }
    }

    const maxStaffUploadImages = 6;
    let staffUploadFiles = [];

    function syncStaffUploadInput(files = staffUploadFiles) {
      const imageInput = document.querySelector('#staffImageInput');
      const cameraInput = document.querySelector('#staffCameraInput');
      if (!imageInput || !window.DataTransfer) return;
      const transfer = new DataTransfer();
      files.forEach((file) => transfer.items.add(file));
      imageInput.files = transfer.files;
      if (cameraInput) cameraInput.value = '';
    }

    function pickStaffImage(id) {
      const input = document.querySelector(`#${id}`);
      if (!input) return;
      input.value = '';
      input.click();
    }

    function addStaffUploadFiles(fileList) {
      const incomingFiles = Array.from(fileList || []);
      if (!incomingFiles.length) return;
      const availableSlots = maxStaffUploadImages - staffUploadFiles.length;
      if (availableSlots <= 0) {
        staffToast.fire({ icon: 'error', title: 'Upload up to 6 images' });
        syncStaffUploadInput();
        return;
      }
      if (incomingFiles.length > availableSlots) {
        staffToast.fire({ icon: 'error', title: `Only ${availableSlots} more image${availableSlots === 1 ? '' : 's'} allowed` });
      }
      staffUploadFiles = staffUploadFiles.concat(incomingFiles.slice(0, availableSlots));
      syncStaffUploadInput();
      setStaffUploadPreview();
    }

    function removeStaffUploadImage(index) {
      staffUploadFiles.splice(index, 1);
      syncStaffUploadInput();
      setStaffUploadPreview();
    }

    function setStaffUploadPreview() {
      const box = document.querySelector('#staffUploadPreviewBox');
      const files = staffUploadFiles;
      if (!box) return;
      box.innerHTML = '';
      if (!files.length) {
        box.classList.add('hidden');
        box.classList.remove('grid');
        return;
      }
      if (files.length > maxStaffUploadImages) {
        staffToast.fire({ icon: 'error', title: 'Upload up to 6 images' });
      }
      files.slice(0, maxStaffUploadImages).forEach((file, index) => {
        const frame = document.createElement('div');
        frame.className = 'relative grid h-16 min-w-0 place-items-center overflow-hidden rounded-md bg-white p-1 sm:h-20';
        const preview = document.createElement('img');
        preview.className = 'block h-full w-full object-contain';
        preview.src = URL.createObjectURL(file);
        preview.alt = '';
        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.className = 'absolute right-1 top-1 grid h-6 w-6 place-items-center rounded-full bg-red-500 text-[11px] text-white shadow';
        deleteButton.title = 'Delete image';
        deleteButton.innerHTML = '<i class="fa-solid fa-trash"></i>';
        deleteButton.addEventListener('click', () => removeStaffUploadImage(index));
        frame.appendChild(preview);
        frame.appendChild(deleteButton);
        box.appendChild(frame);
      });
      if (staffUploadFiles.length < maxStaffUploadImages) {
        const addTile = document.createElement('button');
        addTile.type = 'button';
        addTile.className = 'grid h-16 min-w-0 place-items-center rounded-md border border-dashed border-slate-300 bg-white p-2 text-center text-xs font-medium text-slate-600 transition hover:border-sky-400 hover:text-sky-600 sm:h-20';
        addTile.title = 'Add another image';
        addTile.innerHTML = '<span class="grid place-items-center gap-1"><i class="fa-solid fa-plus text-base"></i><span>Add image</span></span>';
        addTile.addEventListener('click', () => pickStaffImage('staffImageInput'));
        box.appendChild(addTile);
      }
      box.classList.remove('hidden');
      box.classList.add('grid');
    }

    let staffCameraStream = null;

    async function openStaffCamera() {
      if (!navigator.mediaDevices?.getUserMedia) {
        document.querySelector('#staffCameraInput')?.click();
        return;
      }

      try {
        staffCameraStream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1600 },
            height: { ideal: 1200 },
          },
          audio: false,
        });
        const video = document.querySelector('#staffCameraVideo');
        video.srcObject = staffCameraStream;
        document.querySelector('#staffCameraModal')?.classList.add('active');
      } catch (error) {
        staffToast.fire({ icon: 'error', title: 'Back camera unavailable, opening picker' });
        document.querySelector('#staffCameraInput')?.click();
      }
    }

    function closeStaffCamera() {
      if (staffCameraStream) {
        staffCameraStream.getTracks().forEach((track) => track.stop());
        staffCameraStream = null;
      }
      document.querySelector('#staffCameraModal')?.classList.remove('active');
    }

    async function captureStaffCameraImage() {
      const video = document.querySelector('#staffCameraVideo');
      const input = document.querySelector('#staffCameraInput');
      if (!video || !input || !video.videoWidth || !video.videoHeight) return;

      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      const blob = await canvasToBlob(canvas, 0.86);
      if (!blob || !window.DataTransfer) {
        staffToast.fire({ icon: 'error', title: 'Could not capture image' });
        return;
      }
      const file = new File([blob], `camera-${Date.now()}.webp`, { type: 'image/webp' });
      addStaffUploadFiles([file]);
      closeStaffCamera();
    }

    function imageFileToElement(file) {
      return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
          URL.revokeObjectURL(image.src);
          resolve(image);
        };
        image.onerror = reject;
        image.src = URL.createObjectURL(file);
      });
    }

    function canvasToBlob(canvas, quality) {
      return new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
    }

    async function compressStaffImage(file) {
      const maxBytes = 2 * 1024 * 1024;
      const image = await imageFileToElement(file);
      let maxSide = 1600;
      let blob = null;

      for (let attempt = 0; attempt < 8; attempt += 1) {
        const ratio = Math.min(maxSide / image.width, maxSide / image.height, 1);
        const width = Math.max(1, Math.round(image.width * ratio));
        const height = Math.max(1, Math.round(image.height * ratio));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(image, 0, 0, width, height);

        for (let quality = 0.82; quality >= 0.45; quality -= 0.08) {
          blob = await canvasToBlob(canvas, quality);
          if (blob && blob.size <= maxBytes) {
            return new File([blob], `${file.name.replace(/\.[^.]+$/, '') || 'staff-image'}.webp`, { type: 'image/webp' });
          }
        }
        maxSide = Math.round(maxSide * 0.82);
      }

      throw new Error('Image could not be reduced below 2 MB');
    }

    async function prepareStaffUpload(event) {
      event.preventDefault();
      const form = event.target.closest('#staffUploadForm');
      if (!form) return;
      const imageInput = document.querySelector('#staffImageInput');
      const cameraInput = document.querySelector('#staffCameraInput');
      const cameraFiles = Array.from(cameraInput?.files || []);
      const imageFiles = Array.from(imageInput?.files || []);
      const selectedInput = imageInput;
      const selectedFiles = staffUploadFiles.length ? staffUploadFiles : (imageFiles.length ? imageFiles : cameraFiles);
      if (!selectedFiles.length) {
        staffToast.fire({ icon: 'error', title: 'Choose or take at least one image' });
        return;
      }
      if (selectedFiles.length > maxStaffUploadImages) {
        staffToast.fire({ icon: 'error', title: 'Upload up to 6 images' });
        return;
      }
      if (!window.DataTransfer) {
        staffToast.fire({ icon: 'error', title: 'This browser cannot prepare the image before upload' });
        return;
      }

      const submitButton = form.querySelector('button[type="submit"]');
      const originalLabel = submitButton?.innerHTML;
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Preparing...';
      }

      try {
        const transfer = new DataTransfer();
        for (let index = 0; index < selectedFiles.length; index += 1) {
          if (submitButton) {
            submitButton.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>Preparing ${index + 1}/${selectedFiles.length}`;
          }
          transfer.items.add(await compressStaffImage(selectedFiles[index]));
        }
        selectedInput.files = transfer.files;
        if (cameraInput) cameraInput.value = '';
        staffUploadFiles = Array.from(selectedInput.files || []);
        setStaffUploadPreview();
        await submitStaffAjaxForm(form, {
          resetForm: true,
          errorMessage: 'Could not upload image',
          afterSuccess: () => {
            staffUploadFiles = [];
            setStaffUploadPreview();
          },
        });
      } catch (error) {
        staffToast.fire({ icon: 'error', title: error.message || 'Could not prepare image' });
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.innerHTML = originalLabel;
        }
      }
    }

    document.addEventListener('submit', (event) => {
      if (event.target.closest?.('#staffUploadForm')) {
        prepareStaffUpload(event);
      }
    });

    function setSidebarCollapsed(collapsed) {
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      if (collapsed) localStorage.setItem('hyenaStaffSidebarCollapsed', '1');
      else localStorage.removeItem('hyenaStaffSidebarCollapsed');
    }

    function toggleSidebar() {
      if (window.matchMedia('(max-width: 1023px)').matches) {
        document.body.classList.toggle('sidebar-open');
        return;
      }
      setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    }

    function closeSideNav() {
      if (window.matchMedia('(max-width: 1023px)').matches) {
        document.body.classList.remove('sidebar-open');
        return;
      }
      setSidebarCollapsed(true);
    }

    function closeSidebar() {
      document.body.classList.remove('sidebar-open');
    }

    async function confirmStaffLogout() {
      const result = await Swal.fire({
        title: 'Logout?',
        text: 'Do you want to leave the staff dashboard?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2FABE1',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Logout',
        cancelButtonText: 'Cancel',
      });
      if (result.isConfirmed) window.location.href = '/backend/staff-login.php?logout=1';
    }
  </script>
</body>
</html>
