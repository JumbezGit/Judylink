<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_ensure_staff_tables();
require_admin_page('staffs');
$siteId = admin_selected_site_id();

function staffs_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function staff_register_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? 'localhost');
    return $scheme . '://' . $host . '/backend/staff-register.php?token=' . rawurlencode($token);
}

function whatsapp_number(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function tanzania_whatsapp_phone(string $phone): ?string
{
    $digits = whatsapp_number($phone);
    if (preg_match('/^255([1-9]\d{8})$/', $digits, $matches)) {
        return '+255' . $matches[1];
    }
    if (preg_match('/^[1-9]\d{8}$/', $digits)) {
        return '+255' . $digits;
    }
    return null;
}

function staff_whatsapp_message(string $label, string $link): string
{
    return "{$link}\n\nRegister as {$label} with this one-time link. It expires in 1 day.";
}

function staffs_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staff_invite_status(array $invite): array
{
    if (!empty($invite['used_at'])) {
        return ['label' => 'Expired', 'class' => 'border border-red-500 text-red-700'];
    }

    $expiresAt = (string) ($invite['expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
        return ['label' => 'Expired', 'class' => 'border border-red-500 text-red-700'];
    }

    return ['label' => 'Not used', 'class' => 'border border-emerald-500 text-emerald-700'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_page('staffs');
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        staffs_json(['ok' => false, 'error' => 'Admin token required'], 401);
    }

    $action = (string) ($_POST['action'] ?? '');
    $pdo = Database::pdo();

    if ($action === 'invite') {
        $staffType = (string) ($_POST['staff_type'] ?? '');
        $phone = tanzania_whatsapp_phone((string) ($_POST['phone'] ?? ''));
        if (!in_array($staffType, ['tour_guide', 'driver'], true)) {
            staffs_json(['ok' => false, 'error' => 'Choose driver or tour guide']);
        }
        if ($phone === null) {
            staffs_json(['ok' => false, 'error' => 'Enter 9 digits after +255, starting from 1-9']);
        }

        $token = bin2hex(random_bytes(32));
        $currentUser = admin_current_user();
        $stmt = $pdo->prepare('INSERT INTO staff_invites (site_id, token, staff_type, email, phone, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))');
        $stmt->execute([$siteId, $token, $staffType, '', $phone, $currentUser['id'] ?? null]);
        $inviteId = (int) $pdo->lastInsertId();
        $link = staff_register_url($token);
        $label = $staffType === 'driver' ? 'driver' : 'tour guide';
        $message = staff_whatsapp_message($label, $link);
        staffs_json([
            'ok' => true,
            'id' => $inviteId,
            'link' => $link,
            'phone' => $phone,
            'staffType' => str_replace('_', ' ', $staffType),
            'expiresAt' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'statusLabel' => 'Not used',
            'statusClass' => 'border border-emerald-500 text-emerald-700',
            'whatsappUrl' => 'https://wa.me/' . whatsapp_number($phone) . '?text=' . rawurlencode($message),
        ]);
    }

    if ($action === 'approve') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Staff id is required']);
        }
        $currentUser = admin_current_user();
        $stmt = $pdo->prepare("UPDATE staffs SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND site_id = ?");
        $stmt->execute([$currentUser['id'] ?? null, $id, $siteId]);
        staffs_json(['ok' => true, 'status' => 'approved']);
    }

    if ($action === 'reject') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Staff id is required']);
        }
        $stmt = $pdo->prepare("UPDATE staffs SET status = 'rejected' WHERE id = ? AND site_id = ?");
        $stmt->execute([$id, $siteId]);
        staffs_json(['ok' => true, 'status' => 'rejected']);
    }

    if ($action === 'delete_staff') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Staff id is required']);
        }
        $stmt = $pdo->prepare('DELETE FROM staffs WHERE id = ? AND site_id = ?');
        $stmt->execute([$id, $siteId]);
        staffs_json(['ok' => true]);
    }

    if ($action === 'delete_invite') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Link id is required']);
        }
        $stmt = $pdo->prepare('DELETE FROM staff_invites WHERE id = ? AND site_id = ?');
        $stmt->execute([$id, $siteId]);
        staffs_json(['ok' => true]);
    }

    if ($action === 'upload_to_gallery') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Upload id is required']);
        }
        $uploadStmt = $pdo->prepare('SELECT * FROM staff_uploads WHERE id = ? AND site_id = ? AND status = "pending" LIMIT 1');
        $uploadStmt->execute([$id, $siteId]);
        $upload = $uploadStmt->fetch();
        if (!$upload) {
            staffs_json(['ok' => false, 'error' => 'Staff upload not found']);
        }
        $galleryMaxImages = defined('GALLERY_MAX_IMAGES') ? GALLERY_MAX_IMAGES : 100;
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM gallery_items WHERE site_id = ? AND is_active = 1');
        $countStmt->execute([$siteId]);
        if ((int) $countStmt->fetchColumn() >= $galleryMaxImages) {
            staffs_json(['ok' => false, 'error' => "Gallery limit reached. Maximum {$galleryMaxImages} images are allowed."]);
        }

        $galleryStmt = $pdo->prepare(
            'INSERT INTO gallery_items (site_id, title, location, category, image_path, alt_text, span, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $galleryStmt->execute([
            $siteId,
            $upload['title'],
            $upload['location'] ?: 'Zanzibar',
            $upload['category'] ?: 'Staff',
            $upload['image_path'],
            $upload['title'],
            'normal',
            0,
        ]);
        $galleryId = (int) $pdo->lastInsertId();
        $updateStmt = $pdo->prepare('UPDATE staff_uploads SET status = "gallery", gallery_item_id = ? WHERE id = ? AND site_id = ?');
        $updateStmt->execute([$galleryId, $id, $siteId]);
        staffs_json(['ok' => true, 'status' => 'gallery']);
    }

    if ($action === 'delete_upload') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            staffs_json(['ok' => false, 'error' => 'Upload id is required']);
        }
        $stmt = $pdo->prepare('UPDATE staff_uploads SET status = "deleted" WHERE id = ? AND site_id = ?');
        $stmt->execute([$id, $siteId]);
        staffs_json(['ok' => true]);
    }

    staffs_json(['ok' => false, 'error' => 'Unknown staffs action']);
}

$staffsStmt = Database::pdo()->prepare(
    'SELECT staffs.*, staff_invites.email AS invited_email, staff_invites.phone AS invited_phone
     FROM staffs
     LEFT JOIN staff_invites ON staff_invites.id = staffs.invite_id AND staff_invites.site_id = staffs.site_id
     WHERE staffs.site_id = ?
     ORDER BY FIELD(staffs.status, "pending", "approved", "rejected"), staffs.created_at DESC'
);
$staffsStmt->execute([$siteId]);
$staffs = $staffsStmt->fetchAll();
$invitesStmt = Database::pdo()->prepare('SELECT * FROM staff_invites WHERE site_id = ? ORDER BY created_at DESC');
$invitesStmt->execute([$siteId]);
$invites = $invitesStmt->fetchAll();
$staffUploadsStmt = Database::pdo()->prepare(
    'SELECT staff_uploads.*, staffs.first_name, staffs.second_name, staffs.third_name, staffs.staff_type
     FROM staff_uploads
     INNER JOIN staffs ON staffs.id = staff_uploads.staff_id AND staffs.site_id = staff_uploads.site_id
     WHERE staff_uploads.site_id = ? AND staff_uploads.status <> "deleted"
     ORDER BY staff_uploads.created_at DESC'
);
$staffUploadsStmt->execute([$siteId]);
$staffUploads = $staffUploadsStmt->fetchAll();

$staffModalData = [];
foreach ($staffs as $staff) {
    $languages = json_decode((string) $staff['languages'], true);
    $staffModalData[(int) $staff['id']] = [
        'id' => (int) $staff['id'],
        'staff_type' => str_replace('_', ' ', (string) $staff['staff_type']),
        'name' => trim((string) $staff['first_name'] . ' ' . (string) $staff['second_name'] . ' ' . (string) $staff['third_name']),
        'phone' => (string) $staff['phone'],
        'email' => (string) $staff['email'],
        'zanzibar_national_id' => (string) $staff['zanzibar_national_id'],
        'passport_image_path' => (string) $staff['passport_image_path'],
        'languages' => is_array($languages) ? implode(', ', $languages) : '',
        'tour_guide_id' => (string) ($staff['tour_guide_id'] ?? ''),
        'driver_licence' => (string) ($staff['driver_licence'] ?? ''),
        'driver_licence_file_path' => (string) ($staff['driver_licence_file_path'] ?? ''),
        'username' => (string) $staff['username'],
        'status' => (string) $staff['status'],
        'created_at' => (string) $staff['created_at'],
        'approved_at' => (string) ($staff['approved_at'] ?? ''),
    ];
}

admin_header('Staffs', 'staffs');
?>
<style>
  body.admin-dark .tz-phone-field,
  .admin-dark-ready body .tz-phone-field {
    border-color: #475569;
    background: #0f172a;
  }
  body.admin-dark .tz-phone-prefix,
  .admin-dark-ready body .tz-phone-prefix {
    border-color: #475569;
    background: #1e293b;
    color: #cbd5e1;
  }
  body.admin-dark .tz-phone-input,
  .admin-dark-ready body .tz-phone-input {
    color: #e2e8f0;
  }
  body.admin-dark .tz-phone-input::placeholder,
  .admin-dark-ready body .tz-phone-input::placeholder {
    color: #64748b;
  }
</style>
<section class="grid gap-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-medium">Staffs</h2>
      <p class="mt-1 text-sm text-slate-500">Send WhatsApp registration links and approve tour guides or drivers.</p>
    </div>
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Staff sections">
      <button class="primary staff-tab-btn inline-flex items-center gap-2" type="button" data-staff-tab="add"><i class="fa-solid fa-user-plus"></i>Add Staff</button>
      <button class="secondary staff-tab-btn inline-flex items-center gap-2" type="button" data-staff-tab="links"><i class="fa-solid fa-link"></i>Staff Links</button>
      <button class="secondary staff-tab-btn inline-flex items-center gap-2" type="button" data-staff-tab="uploads"><i class="fa-solid fa-cloud-arrow-up"></i>Uploads</button>
      <button class="secondary staff-tab-btn inline-flex items-center gap-2" type="button" data-staff-tab="all"><i class="fa-solid fa-users"></i>All Staff</button>
    </div>
  </div>

  <article class="admin-card staff-panel" data-staff-panel="add">
    <h3 class="mb-4 text-base font-medium">Send WhatsApp Registration Link</h3>
    <form id="staffInviteForm" class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)_auto] md:items-end">
      <input type="hidden" name="action" value="invite">
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Staff type
        <select class="form-control" name="staff_type" required>
          <option value="tour_guide">Tour Guide</option>
          <option value="driver">Driver</option>
        </select>
      </label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">WhatsApp phone
        <span class="tz-phone-field flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#2FABE1]">
          <span class="tz-phone-prefix grid place-items-center border-r border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600">+255</span>
          <input class="tz-phone-input min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm font-normal text-slate-900 outline-none" name="phone" type="text" inputmode="numeric" pattern="[1-9][0-9]{8}" maxlength="9" placeholder="777123456" autocomplete="tel-national" required>
        </span>
      </label>
      <button class="primary inline-flex items-center justify-center gap-2" type="submit"><i class="fa-brands fa-whatsapp"></i>Open WhatsApp</button>
    </form>
    <div id="inviteResult" class="mt-4 hidden rounded-lg bg-slate-50 p-3 text-sm text-slate-600"></div>
  </article>

  <article class="admin-card staff-panel hidden" data-staff-panel="links">
    <h3 class="mb-4 text-base font-medium">Staff Links</h3>
    <div id="staffLinkList" class="grid gap-2 text-sm">
      <?php foreach ($invites as $invite): ?>
        <?php
        $link = staff_register_url((string) $invite['token']);
        $inviteStatus = staff_invite_status($invite);
        ?>
        <div class="grid gap-3 rounded-lg bg-slate-50 p-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center" data-invite-row="<?= (int) $invite['id'] ?>">
          <div class="min-w-0">
            <p class="font-medium text-slate-800"><?= staffs_h(str_replace('_', ' ', (string) $invite['staff_type'])) ?> | <?= staffs_h($invite['phone'] ?: $invite['email']) ?></p>
            <p class="mt-1 truncate text-xs text-slate-500"><?= staffs_h($link) ?></p>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
              <span class="rounded-md px-2 py-1 font-medium <?= staffs_h($inviteStatus['class']) ?>"><?= staffs_h($inviteStatus['label']) ?></span>
              <span>Expires: <?= staffs_h($invite['expires_at'] ?: '-') ?></span>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="secondary inline-flex items-center justify-center gap-2" type="button" title="Copy link" onclick="copyStaffLink('<?= staffs_h($link) ?>')"><i class="fa-regular fa-copy"></i></button>
            <a class="secondary inline-flex items-center justify-center gap-2 no-underline" href="<?= staffs_h($link) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i>Open</a>
            <a class="primary inline-flex items-center justify-center gap-2 no-underline" href="https://wa.me/<?= staffs_h(whatsapp_number((string) $invite['phone'])) ?>?text=<?= rawurlencode(staff_whatsapp_message(str_replace('_', ' ', (string) $invite['staff_type']), $link)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>
            <button class="danger inline-flex items-center justify-center gap-2" type="button" onclick="deleteStaffInvite(<?= (int) $invite['id'] ?>)"><i class="fa-regular fa-trash-can"></i>Delete</button>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$invites): ?><p class="text-sm text-slate-500">No registration links sent yet.</p><?php endif; ?>
    </div>
  </article>

  <article class="admin-card staff-panel hidden" data-staff-panel="uploads">
    <h3 class="mb-4 text-base font-medium">Staff Uploads</h3>
    <div id="staffUploadList" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($staffUploads as $upload): ?>
        <?php $staffName = trim((string) $upload['first_name'] . ' ' . (string) $upload['second_name'] . ' ' . (string) $upload['third_name']); ?>
        <article class="overflow-hidden rounded-lg bg-slate-50" data-upload-row="<?= (int) $upload['id'] ?>">
          <img class="aspect-[4/3] w-full object-cover" src="<?= staffs_h($upload['image_path']) ?>" alt="">
          <div class="grid gap-3 p-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800"><?= staffs_h($upload['title']) ?></p>
              <p class="mt-1 text-xs text-slate-500"><?= staffs_h($staffName) ?> | <?= staffs_h(str_replace('_', ' ', (string) $upload['staff_type'])) ?></p>
              <p class="mt-1 text-xs text-slate-400"><?= staffs_h($upload['location']) ?> | <?= staffs_h($upload['category']) ?> | <span data-upload-status><?= staffs_h($upload['status']) ?></span></p>
              <?php if ($upload['note']): ?><p class="mt-2 text-xs text-slate-500"><?= staffs_h($upload['note']) ?></p><?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-2">
              <a class="secondary inline-flex items-center justify-center gap-2 no-underline" href="<?= staffs_h($upload['image_path']) ?>" download><i class="fa-solid fa-download"></i>Download</a>
              <?php if ($upload['status'] === 'pending'): ?><button class="primary inline-flex items-center justify-center gap-2" type="button" onclick="sendUploadToGallery(<?= (int) $upload['id'] ?>)"><i class="fa-solid fa-images"></i>Gallery</button><?php endif; ?>
              <button class="danger inline-flex items-center justify-center gap-2" type="button" onclick="deleteStaffUpload(<?= (int) $upload['id'] ?>)"><i class="fa-regular fa-trash-can"></i>Delete</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$staffUploads): ?><p class="text-sm text-slate-500">No staff uploads yet.</p><?php endif; ?>
    </div>
  </article>

  <article class="admin-card staff-panel hidden" data-staff-panel="all">
    <h3 class="mb-4 text-base font-medium">All Staff</h3>
    <div id="staffList" class="grid gap-3">
      <?php foreach ($staffs as $staff): ?>
        <?php $languages = json_decode((string) $staff['languages'], true) ?: []; ?>
        <div class="grid gap-3 rounded-lg bg-slate-50 p-3 lg:grid-cols-[64px_minmax(0,1fr)_auto] lg:items-center" data-staff-row="<?= (int) $staff['id'] ?>">
          <img class="h-16 w-16 rounded-lg bg-slate-100 object-cover" src="<?= staffs_h($staff['passport_image_path']) ?>" alt="">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h4 class="font-medium text-slate-900"><?= staffs_h(trim((string) $staff['first_name'] . ' ' . (string) $staff['second_name'] . ' ' . (string) $staff['third_name'])) ?></h4>
              <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600"><?= staffs_h(str_replace('_', ' ', (string) $staff['staff_type'])) ?></span>
              <span class="rounded-md bg-sky-50 px-2 py-1 text-xs text-sky-700" data-status><?= staffs_h($staff['status']) ?></span>
            </div>
            <p class="mt-1 text-sm text-slate-500"><?= staffs_h($staff['email']) ?> | <?= staffs_h($staff['phone']) ?> | <?= staffs_h(implode(', ', $languages)) ?></p>
            <p class="mt-1 text-xs text-slate-400">ID: <?= staffs_h($staff['zanzibar_national_id']) ?><?= $staff['tour_guide_id'] ? ' | Guide ID: ' . staffs_h($staff['tour_guide_id']) : '' ?><?= $staff['driver_licence'] ? ' | Licence: ' . staffs_h($staff['driver_licence']) : '' ?></p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="secondary inline-flex items-center justify-center gap-2" type="button" onclick="viewStaff(<?= (int) $staff['id'] ?>)"><i class="fa-regular fa-eye"></i>View</button>
            <?php if ($staff['status'] !== 'approved'): ?><button class="primary" type="button" onclick="updateStaffStatus(<?= (int) $staff['id'] ?>, 'approve')">Approve</button><?php endif; ?>
            <?php if ($staff['status'] !== 'rejected'): ?><button class="secondary" type="button" onclick="updateStaffStatus(<?= (int) $staff['id'] ?>, 'reject')">Reject</button><?php endif; ?>
            <button class="danger inline-flex items-center justify-center gap-2" type="button" onclick="deleteStaff(<?= (int) $staff['id'] ?>)"><i class="fa-regular fa-trash-can"></i>Delete</button>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$staffs): ?><p class="text-sm text-slate-500">No staff registrations yet.</p><?php endif; ?>
    </div>
  </article>
</section>

<div id="staffModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4">
  <div class="max-h-[90vh] w-full max-w-3xl overflow-auto rounded-xl bg-white p-5 shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h3 id="staffModalName" class="text-lg font-semibold text-slate-900">Staff details</h3>
        <p id="staffModalMeta" class="mt-1 text-sm text-slate-500"></p>
      </div>
      <button class="secondary" type="button" onclick="closeStaffModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mt-5 grid gap-4 md:grid-cols-[160px_minmax(0,1fr)]">
      <img id="staffModalImage" class="h-40 w-40 rounded-lg bg-slate-100 object-cover" src="" alt="">
      <dl id="staffModalDetails" class="grid gap-3 sm:grid-cols-2"></dl>
    </div>
  </div>
</div>
<?php
$staffRecordsJson = json_encode($staffModalData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
admin_footer(str_replace('STAFF_RECORDS_JSON', $staffRecordsJson ?: '{}', <<<'JS'
const staffRecords = STAFF_RECORDS_JSON;

function setStaffTab(tab) {
  document.querySelectorAll('[data-staff-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.staffPanel !== tab));
  document.querySelectorAll('[data-staff-tab]').forEach((button) => {
    const active = button.dataset.staffTab === tab;
    button.classList.toggle('primary', active);
    button.classList.toggle('secondary', !active);
  });
}

document.querySelectorAll('[data-staff-tab]').forEach((button) => {
  button.addEventListener('click', () => setStaffTab(button.dataset.staffTab));
});

document.querySelector('#staffInviteForm [name="phone"]')?.addEventListener('input', (event) => {
  let digits = event.currentTarget.value.replace(/\D/g, '').slice(0, 9);
  digits = digits.replace(/^0+/, '');
  event.currentTarget.value = digits;
});

async function copyStaffLink(link) {
  try {
    await navigator.clipboard.writeText(link);
    success('Link copied');
  } catch (error) {
    const input = document.createElement('input');
    input.value = link;
    document.body.append(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    success('Link copied');
  }
}

function escapeStaffValue(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function detailItem(label, value) {
  if (!value) return '';
  return `<div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs uppercase text-slate-400">${label}</dt><dd class="mt-1 break-words text-sm font-medium text-slate-800">${escapeStaffValue(value)}</dd></div>`;
}

function makeStaffLinkRow(data) {
  return `<div class="grid gap-3 rounded-lg bg-slate-50 p-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center" data-invite-row="${data.id}">
    <div class="min-w-0">
      <p class="font-medium text-slate-800">${escapeStaffValue(data.staffType)} | ${escapeStaffValue(data.phone)}</p>
      <p class="mt-1 truncate text-xs text-slate-500">${escapeStaffValue(data.link)}</p>
      <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
        <span class="rounded-md px-2 py-1 font-medium ${escapeStaffValue(data.statusClass)}">${escapeStaffValue(data.statusLabel)}</span>
        <span>Expires: ${escapeStaffValue(data.expiresAt)}</span>
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button class="secondary inline-flex items-center justify-center gap-2" type="button" title="Copy link" onclick="copyStaffLink('${escapeStaffValue(data.link)}')"><i class="fa-regular fa-copy"></i></button>
      <a class="secondary inline-flex items-center justify-center gap-2 no-underline" href="${escapeStaffValue(data.link)}" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i>Open</a>
      <a class="primary inline-flex items-center justify-center gap-2 no-underline" href="${escapeStaffValue(data.whatsappUrl)}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>
      <button class="danger inline-flex items-center justify-center gap-2" type="button" onclick="deleteStaffInvite(${data.id})"><i class="fa-regular fa-trash-can"></i>Delete</button>
    </div>
  </div>`;
}

function viewStaff(id) {
  const staff = staffRecords[id];
  if (!staff) return;
  document.querySelector('#staffModalName').textContent = staff.name || 'Staff details';
  document.querySelector('#staffModalMeta').textContent = `${staff.staff_type || ''} | ${staff.status || ''}`;
  document.querySelector('#staffModalImage').src = staff.passport_image_path || '';
  const licenceFile = staff.driver_licence_file_path ? `<a class="text-sky-700 underline" href="${escapeStaffValue(staff.driver_licence_file_path)}" target="_blank" rel="noopener">View licence file</a>` : '';
  document.querySelector('#staffModalDetails').innerHTML = [
    detailItem('Status', staff.status),
    detailItem('Username', staff.username),
    detailItem('Phone', staff.phone),
    detailItem('Email', staff.email),
    detailItem('Zanzibar/National ID', staff.zanzibar_national_id),
    detailItem('Languages', staff.languages),
    detailItem('Tour Guide ID', staff.tour_guide_id),
    detailItem('Driver Licence', staff.driver_licence),
    detailItem('Registered', staff.created_at),
    detailItem('Approved', staff.approved_at),
    licenceFile ? `<div class="rounded-lg bg-slate-50 p-3 sm:col-span-2">${licenceFile}</div>` : '',
  ].join('');
  document.querySelector('#staffModal').classList.remove('hidden');
  document.querySelector('#staffModal').classList.add('flex');
}

function closeStaffModal() {
  document.querySelector('#staffModal')?.classList.add('hidden');
  document.querySelector('#staffModal')?.classList.remove('flex');
}

document.querySelector('#staffInviteForm')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const result = document.querySelector('#inviteResult');
  try {
    const data = await request(adminPage('staffs/'), {
      method: 'POST',
      headers: adminHeaders(),
      body: new FormData(event.currentTarget),
    });
    result.classList.remove('hidden');
    result.innerHTML = `<p class="font-medium text-slate-800">WhatsApp message ready</p><div class="mt-2 flex gap-2"><input class="form-control min-w-0" readonly value="${escapeStaffValue(data.link)}"><button class="secondary" type="button" onclick="copyStaffLink('${escapeStaffValue(data.link)}')"><i class="fa-regular fa-copy"></i></button></div><div class="mt-2 flex flex-wrap gap-2"><a class="secondary inline-flex items-center justify-center gap-2 no-underline" href="${escapeStaffValue(data.link)}" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i>Open Registration Link</a><a class="primary inline-flex items-center justify-center gap-2 no-underline" href="${escapeStaffValue(data.whatsappUrl)}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Open WhatsApp</a></div>`;
    document.querySelector('#staffLinkList')?.insertAdjacentHTML('afterbegin', makeStaffLinkRow(data));
    if (data.whatsappUrl) window.open(data.whatsappUrl, '_blank', 'noopener');
    event.currentTarget.reset();
    success('WhatsApp registration link created');
  } catch (error) {
    failure(error.message || 'Could not create link');
  }
});

async function updateStaffStatus(id, action) {
  const body = new FormData();
  body.set('action', action);
  body.set('id', id);
  try {
    const data = await request(adminPage('staffs/'), { method: 'POST', headers: adminHeaders(), body });
    const row = document.querySelector(`[data-staff-row="${id}"]`);
    const status = data.status || (action === 'approve' ? 'approved' : 'rejected');
    row?.querySelector('[data-status]')?.replaceChildren(document.createTextNode(status));
    if (staffRecords[id]) {
      staffRecords[id].status = status;
      if (status === 'approved') staffRecords[id].approved_at = 'Just now';
    }
    success(action === 'approve' ? 'Staff approved' : 'Staff rejected');
  } catch (error) {
    failure(error.message || 'Could not update staff');
  }
}

async function deleteStaff(id) {
  if (!await confirmDelete('Delete this staff?')) return;
  const body = new FormData();
  body.set('action', 'delete_staff');
  body.set('id', id);
  try {
    await request(adminPage('staffs/'), { method: 'POST', headers: adminHeaders(), body });
    document.querySelector(`[data-staff-row="${id}"]`)?.remove();
    delete staffRecords[id];
    success('Staff deleted');
  } catch (error) {
    failure(error.message || 'Could not delete staff');
  }
}

async function deleteStaffInvite(id) {
  if (!await confirmDelete('Delete this registration link?')) return;
  const body = new FormData();
  body.set('action', 'delete_invite');
  body.set('id', id);
  try {
    await request(adminPage('staffs/'), { method: 'POST', headers: adminHeaders(), body });
    document.querySelector(`[data-invite-row="${id}"]`)?.remove();
    success('Registration link deleted');
  } catch (error) {
    failure(error.message || 'Could not delete link');
  }
}

async function sendUploadToGallery(id) {
  const body = new FormData();
  body.set('action', 'upload_to_gallery');
  body.set('id', id);
  try {
    await request(adminPage('staffs/'), { method: 'POST', headers: adminHeaders(), body });
    const row = document.querySelector(`[data-upload-row="${id}"]`);
    row?.querySelector('button.primary')?.remove();
    const meta = row?.querySelector('[data-upload-status]');
    if (meta) meta.textContent = 'gallery';
    success('Staff image added to gallery');
  } catch (error) {
    failure(error.message || 'Could not add upload to gallery');
  }
}

async function deleteStaffUpload(id) {
  if (!await confirmDelete('Delete this staff upload?')) return;
  const body = new FormData();
  body.set('action', 'delete_upload');
  body.set('id', id);
  try {
    await request(adminPage('staffs/'), { method: 'POST', headers: adminHeaders(), body });
    document.querySelector(`[data-upload-row="${id}"]`)?.remove();
    success('Staff upload deleted');
  } catch (error) {
    failure(error.message || 'Could not delete staff upload');
  }
}
JS));
