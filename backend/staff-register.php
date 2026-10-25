<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staff_register_error(string $message): never
{
    http_response_code(400);
    echo '<!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.tailwindcss.com"></script></head><body class="grid min-h-screen place-items-center bg-slate-100 p-4 font-sans"><main class="w-full max-w-md rounded-xl bg-white p-6 text-center shadow-xl"><h1 class="text-xl font-medium text-slate-900">Registration unavailable</h1><p class="mt-2 text-sm text-slate-500">' . h($message) . '</p></main></body></html>';
    exit;
}

function staff_save_webp_image(string $field, string $folder, int $maxBytes = 524288): string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        staff_register_error('Passport size image is required');
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        staff_register_error('Image upload failed');
    }

    $allowed = [
        'image/jpeg' => 'jpeg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type((string) $_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        staff_register_error('Passport image must be JPG, PNG, or WEBP');
    }
    if (!function_exists('imagewebp')) {
        staff_register_error('PHP GD extension is required for image conversion');
    }

    $source = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg((string) $_FILES[$field]['tmp_name']),
        'image/png' => imagecreatefrompng((string) $_FILES[$field]['tmp_name']),
        'image/webp' => imagecreatefromwebp((string) $_FILES[$field]['tmp_name']),
        default => false,
    };
    if (!$source) {
        staff_register_error('Could not read passport image');
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $ratio = min(600 / $width, 600 / $height, 1);
    $newWidth = max(1, (int) round($width * $ratio));
    $newHeight = max(1, (int) round($height * $ratio));
    $targetImage = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($targetImage, true);
    imagesavealpha($targetImage, true);
    imagecopyresampled($targetImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($source);

    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        staff_register_error('Could not create upload folder');
    }

    $name = bin2hex(random_bytes(12)) . '.webp';
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    for ($quality = 82; $quality >= 45; $quality -= 7) {
        imagewebp($targetImage, $target, $quality);
        if (is_file($target) && filesize($target) <= $maxBytes) {
            imagedestroy($targetImage);
            return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/' . $folder . '/' . $name;
        }
    }
    imagedestroy($targetImage);
    if (is_file($target)) {
        unlink($target);
    }
    staff_register_error('Passport image could not be compressed below 0.5 MB');
}

function staff_save_document(string $field, string $folder): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        staff_register_error('Driver licence upload failed');
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 2 * 1024 * 1024) {
        staff_register_error('Driver licence file must be 2 MB or smaller');
    }
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type((string) $_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        staff_register_error('Driver licence file must be PDF, JPG, PNG, or WEBP');
    }
    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        staff_register_error('Could not create document folder');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $target)) {
        staff_register_error('Could not save driver licence file');
    }
    return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/' . $folder . '/' . $name;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '') {
    staff_register_error('Registration token is missing');
}

$stmt = Database::pdo()->prepare('SELECT * FROM staff_invites WHERE token = ? LIMIT 1');
$stmt->execute([$token]);
$invite = $stmt->fetch();
if (!$invite) {
    staff_register_error('Registration link was not found');
}
if (!empty($invite['used_at'])) {
    staff_register_error('Registration link has already been used');
}
if (!empty($invite['expires_at']) && strtotime((string) $invite['expires_at']) < time()) {
    staff_register_error('Registration link has expired');
}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffType = (string) $invite['staff_type'];
    $required = ['first_name', 'second_name', 'third_name', 'phone', 'email', 'zanzibar_national_id', 'languages', 'username', 'password'];
    foreach ($required as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            staff_register_error('Please fill all required fields');
        }
    }
    if ($staffType === 'tour_guide' && trim((string) ($_POST['tour_guide_id'] ?? '')) === '') {
        staff_register_error('Tour guide ID is required');
    }
    if ($staffType === 'driver' && trim((string) ($_POST['driver_licence'] ?? '')) === '') {
        staff_register_error('Driver licence number is required');
    }
    if (!filter_var((string) $_POST['email'], FILTER_VALIDATE_EMAIL)) {
        staff_register_error('Valid email is required');
    }
    if (strlen((string) $_POST['password']) < 8) {
        staff_register_error('Password must be at least 8 characters');
    }

    $siteFolder = 'site-' . (int) $invite['site_id'];
    $passportPath = staff_save_webp_image('passport_image', 'staff-passports/' . $siteFolder);
    $licenceFile = $staffType === 'driver' ? staff_save_document('driver_licence_file', 'staff-licences/' . $siteFolder) : null;
    $languages = array_values(array_filter(array_map('trim', explode(',', (string) $_POST['languages']))));

    $pdo = Database::pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO staffs (
            site_id, invite_id, staff_type, first_name, second_name, third_name, phone, email, zanzibar_national_id,
            passport_image_path, languages, tour_guide_id, driver_licence, driver_licence_file_path,
            username, password_hash, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    try {
        $stmt->execute([
            (int) $invite['site_id'],
            (int) $invite['id'],
            $staffType,
            trim((string) $_POST['first_name']),
            trim((string) $_POST['second_name']),
            trim((string) $_POST['third_name']),
            trim((string) $_POST['phone']),
            trim((string) $_POST['email']),
            trim((string) $_POST['zanzibar_national_id']),
            $passportPath,
            json_encode($languages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $staffType === 'tour_guide' ? trim((string) $_POST['tour_guide_id']) : null,
            $staffType === 'driver' ? trim((string) $_POST['driver_licence']) : null,
            $licenceFile,
            trim((string) $_POST['username']),
            password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
        ]);
        $mark = $pdo->prepare('UPDATE staff_invites SET used_at = NOW() WHERE id = ? AND site_id = ?');
        $mark->execute([(int) $invite['id'], (int) $invite['site_id']]);
        $saved = true;
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            staff_register_error('Username already exists');
        }
        staff_register_error('Could not save registration');
    }
}

$brandName = 'Judylink';
$staffLabel = $invite['staff_type'] === 'driver' ? 'Driver' : 'Tour Guide';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($staffLabel) ?> Registration</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body { font-family: Arial, sans-serif; }</style>
</head>
<body class="bg-slate-100 p-4 text-slate-900">
  <main class="mx-auto w-full max-w-4xl rounded-xl bg-white p-5 shadow-xl">
    <?php if ($saved): ?>
      <section class="py-10 text-center">
        <h1 class="text-2xl font-semibold">Registration Submitted</h1>
        <p class="mt-2 text-sm text-slate-500">Please wait for admin approval before logging in.</p>
        <a class="mt-5 inline-flex items-center justify-center gap-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 no-underline transition hover:bg-sky-100" href="/backend/staff-login.php">Staff Login</a>
      </section>
    <?php else: ?>
      <div class="mb-6">
        <h1 class="text-2xl font-semibold"><?= h($staffLabel) ?> Registration</h1>
        <p class="mt-1 text-sm text-slate-500">Complete the sections below. Your account will need admin approval before login.</p>
      </div>
      <form class="grid gap-5" method="post" enctype="multipart/form-data" id="staffRegisterForm">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <section class="rounded-lg bg-slate-50 p-4">
          <h2 class="text-base font-semibold">1. Staff Type</h2>
          <p class="mt-2 text-sm text-slate-600"><?= h($staffLabel) ?></p>
        </section>

        <section class="grid gap-4 rounded-lg bg-slate-50 p-4">
          <h2 class="text-base font-semibold">2. Information</h2>
          <div class="grid gap-3 md:grid-cols-3">
            <label class="grid gap-1 text-sm font-medium">First name <input class="rounded-lg border border-slate-300 px-3 py-2" name="first_name" required></label>
            <label class="grid gap-1 text-sm font-medium">Second name <input class="rounded-lg border border-slate-300 px-3 py-2" name="second_name" required></label>
            <label class="grid gap-1 text-sm font-medium">Third name <input class="rounded-lg border border-slate-300 px-3 py-2" name="third_name" required></label>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <label class="grid gap-1 text-sm font-medium">Phone <input class="rounded-lg border border-slate-300 px-3 py-2" name="phone" value="<?= h($invite['phone'] ?? '') ?>" required></label>
            <label class="grid gap-1 text-sm font-medium">Email <input class="rounded-lg border border-slate-300 px-3 py-2" name="email" type="email" value="<?= h($invite['email']) ?>" required></label>
            <label class="grid gap-1 text-sm font-medium">Zanzibar/National ID <input class="rounded-lg border border-slate-300 px-3 py-2" name="zanzibar_national_id" required></label>
            <label class="grid gap-1 text-sm font-medium">Languages <input class="rounded-lg border border-slate-300 px-3 py-2" name="languages" placeholder="English, Swahili" required></label>
            <?php if ($invite['staff_type'] === 'tour_guide'): ?>
              <label class="grid gap-1 text-sm font-medium">Tour guide ID <input class="rounded-lg border border-slate-300 px-3 py-2" name="tour_guide_id" required></label>
            <?php else: ?>
              <label class="grid gap-1 text-sm font-medium">Driver licence number <input class="rounded-lg border border-slate-300 px-3 py-2" name="driver_licence" required></label>
              <label class="grid gap-1 text-sm font-medium">Driver licence file
                <button class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200" type="button" onclick="pickRegisterFile('driverLicenceFileInput')">Choose File</button>
                <input id="driverLicenceFileInput" class="hidden" name="driver_licence_file" type="file" accept="application/pdf,image/*" onchange="setRegisterFileName(this, 'driverLicenceFileName')">
                <span id="driverLicenceFileName" class="text-xs text-slate-500">No file selected</span>
              </label>
            <?php endif; ?>
            <label class="grid gap-1 text-sm font-medium">Passport size image
              <button class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200" type="button" onclick="pickRegisterFile('passportImageInput')">Choose Image</button>
              <input id="passportImageInput" class="hidden" name="passport_image" type="file" accept="image/jpeg,image/png,image/webp" onchange="setRegisterFileName(this, 'passportImageFileName')">
              <span id="passportImageFileName" class="text-xs text-slate-500">No image selected</span>
            </label>
            <label class="grid gap-1 text-sm font-medium">Username <input class="rounded-lg border border-slate-300 px-3 py-2" name="username" required></label>
            <label class="grid gap-1 text-sm font-medium">Password <input class="rounded-lg border border-slate-300 px-3 py-2" name="password" type="password" minlength="8" required></label>
          </div>
        </section>

        <section class="rounded-lg bg-slate-50 p-4">
          <h2 class="text-base font-semibold">3. Review & Submit</h2>
          <p class="mt-2 text-sm text-slate-600">Review your details carefully before submitting. After submission, this one-time link cannot be used again.</p>
          <button class="mt-4 inline-flex items-center justify-center gap-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100" type="submit">Submit for Approval</button>
        </section>
      </form>
    <?php endif; ?>
  </main>
  <script>
    function pickRegisterFile(id) {
      document.querySelector(`#${id}`)?.click();
    }

    function setRegisterFileName(input, labelId) {
      const label = document.querySelector(`#${labelId}`);
      const file = input.files?.[0];
      if (label && file) label.textContent = file.name;
    }
  </script>
</body>
</html>
