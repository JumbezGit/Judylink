<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

function settings_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function settings_require_selected_site_user(int $userId): array
{
    $siteId = admin_selected_site_id();
    if ($siteId < 1 || $userId < 1) {
        settings_json(['ok' => false, 'error' => 'Choose a site before managing its admin user'], 400);
    }

    $stmt = Database::pdo()->prepare('SELECT * FROM admin_users WHERE id = ? AND site_id = ? LIMIT 1');
    $stmt->execute([$userId, $siteId]);
    $user = $stmt->fetch();
    if (!$user) {
        settings_json(['ok' => false, 'error' => 'Admin user does not belong to the selected site'], 403);
    }

    return $user;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_page();
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        settings_json(['ok' => false, 'error' => 'Admin token required'], 401);
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'theme') {
            $theme = (string) ($_POST['theme'] ?? 'light');
            if (!in_array($theme, ['light', 'dark'], true)) {
                settings_json(['ok' => false, 'error' => 'Invalid mode']);
            }
            admin_update_theme($theme);
            settings_json(['ok' => true, 'theme' => $theme]);
        }

        if ($action === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            if (strlen($new) < 8) {
                settings_json(['ok' => false, 'error' => 'New password must be at least 8 characters']);
            }
            if ($new !== $confirm) {
                settings_json(['ok' => false, 'error' => 'Password confirmation does not match']);
            }
            if (!admin_change_password($current, $new)) {
                settings_json(['ok' => false, 'error' => 'Current password is not correct']);
            }
            settings_json(['ok' => true]);
        }

        if ($action === 'brand') {
            if (!admin_can_manage_selected_site()) {
                settings_json(['ok' => false, 'error' => 'Site access required'], 403);
            }
            $selectedSite = admin_selected_site();
            if (!$selectedSite) {
                settings_json(['ok' => false, 'error' => 'Choose a site before changing its brand'], 400);
            }
            $logoPath = admin_setting('brand_logo', '');
            if (isset($_FILES['brand_logo_file']) && $_FILES['brand_logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $logoPath = save_brand_logo($_FILES['brand_logo_file']);
            }
            admin_update_settings([
                'brand_name' => trim((string) ($_POST['brand_name'] ?? '')) ?: (string) $selectedSite['site_name'],
                'brand_subtitle' => trim((string) ($_POST['brand_subtitle'] ?? '')) ?: 'Website management',
                'brand_logo' => $logoPath,
            ]);
            settings_json(['ok' => true]);
        }

        if ($action === 'user_controls') {
            if (!admin_is_super_admin()) {
                settings_json(['ok' => false, 'error' => 'Super admin required'], 403);
            }
            $userControls = $_POST['users'] ?? [];
            $buildPermissions = static function (int $userId, array $controls): array {
                settings_require_selected_site_user($userId);
                $stmt = Database::pdo()->prepare(
                    'SELECT sites.business_type
                     FROM admin_users AS users
                     LEFT JOIN company_sites AS sites ON sites.id = users.site_id
                     WHERE users.id = ? AND users.site_id = ? LIMIT 1'
                );
                $stmt->execute([$userId, admin_selected_site_id()]);
                $businessType = (string) ($stmt->fetchColumn() ?: 'general-business');
                $permissions = admin_site_permissions($businessType);
                foreach (array_keys(admin_permission_options_for_business_type($businessType)) as $key) {
                    $permissions[$key] = isset($controls['permissions'][$key]);
                }
                return $permissions;
            };
            if (isset($_POST['user_id'])) {
                $userId = (int) $_POST['user_id'];
                $controls = $userControls[$userId] ?? [];
                $permissions = $buildPermissions($userId, $controls);
                admin_update_user_controls($userId, 'admin', $permissions, isset($controls['is_active']) ? 1 : 0);
            } else {
                foreach ($userControls as $id => $controls) {
                    $permissions = $buildPermissions((int) $id, $controls);
                    admin_update_user_controls((int) $id, 'admin', $permissions, isset($controls['is_active']) ? 1 : 0);
                }
            }
            settings_json(['ok' => true]);
        }

        if ($action === 'delete_user') {
            if (!admin_is_super_admin()) {
                settings_json(['ok' => false, 'error' => 'Super admin required'], 403);
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            settings_require_selected_site_user($userId);
            if (!admin_delete_user($userId)) {
                settings_json(['ok' => false, 'error' => 'This user cannot be deleted'], 400);
            }
            settings_json(['ok' => true]);
        }

        settings_json(['ok' => false, 'error' => 'Unknown settings action']);
    } catch (PDOException $error) {
        if ($error->getCode() === '23000') {
            settings_json(['ok' => false, 'error' => 'That username already exists']);
        }
        settings_json(['ok' => false, 'error' => 'Could not save settings'], 500);
    }
}

function save_brand_logo(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        settings_json(['ok' => false, 'error' => 'Logo upload failed']);
    }

    if ((int) ($file['size'] ?? 0) > IMAGE_MAX_UPLOAD_BYTES) {
        settings_json(['ok' => false, 'error' => 'Logo must be 2 MB or smaller']);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $mime = mime_content_type((string) $file['tmp_name']);
    if (!isset($allowed[$mime])) {
        settings_json(['ok' => false, 'error' => 'Logo must be JPG, PNG, WEBP, or SVG']);
    }

    $site = admin_selected_site();
    if (!$site) {
        settings_json(['ok' => false, 'error' => 'Choose a site before uploading its logo'], 400);
    }
    $siteFolder = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $site['site_slug'])) ?: 'site-' . (int) $site['id'];
    $dir = rtrim(UPLOAD_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR . $siteFolder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        settings_json(['ok' => false, 'error' => 'Could not create logo folder'], 500);
    }

    $name = 'admin-logo-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        settings_json(['ok' => false, 'error' => 'Could not save logo'], 500);
    }

    return rtrim(PUBLIC_UPLOAD_BASE, '/') . '/brand/' . rawurlencode($siteFolder) . '/' . $name;
}

require_admin_page('settings');
admin_ensure_users_table();
$currentTheme = admin_theme();
$isSuperAdmin = admin_is_super_admin();
$selectedSite = admin_selected_site();
$users = [];
if ($isSuperAdmin && $selectedSite) {
    $usersStmt = Database::pdo()->prepare(
        'SELECT users.id, users.username, users.theme, users.role, users.permissions, users.site_id, users.is_active, users.created_at,
                sites.site_name, sites.business_type
         FROM admin_users AS users
         LEFT JOIN company_sites AS sites ON sites.id = users.site_id
         WHERE users.site_id = ?
         ORDER BY users.username'
    );
    $usersStmt->execute([(int) $selectedSite['id']]);
    $users = $usersStmt->fetchAll();
}
$brandName = admin_setting('brand_name', 'Judylink');
$brandSubtitle = admin_setting('brand_subtitle', 'Tours & travel control');
$brandLogo = admin_setting('brand_logo', '');

admin_header('Settings', 'settings');
?>
<style>
  .access-switch {
    position: relative;
    display: inline-flex;
    height: 1.35rem;
    width: 3.35rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #e2e8f0;
    padding: 0 0.42rem;
    font-size: 0.52rem;
    font-weight: 700;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
    transition: background-color .18s ease, border-color .18s ease, color .18s ease;
  }
  .access-switch::before {
    content: "";
    position: absolute;
    left: 0.16rem;
    top: 0.16rem;
    height: 0.98rem;
    width: 0.98rem;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .18);
    transition: transform .18s ease;
  }
  .access-switch .switch-on { opacity: 0; color: #fff; }
  .access-switch .switch-off { margin-left: auto; opacity: 1; }
  input:checked + .access-switch {
    border-color: #2FABE1;
    background: #2FABE1;
    color: #fff;
  }
  input:checked + .access-switch::before { transform: translateX(1.95rem); }
  input:checked + .access-switch .switch-on { opacity: 1; }
  input:checked + .access-switch .switch-off { opacity: 0; }
  body.admin-dark .access-switch,
  .admin-dark-ready body .access-switch {
    border-color: #475569;
    background: #334155;
    color: #cbd5e1;
  }
  body.admin-dark input:checked + .access-switch,
  .admin-dark-ready body input:checked + .access-switch {
    border-color: #2FABE1;
    background: #2FABE1;
    color: #fff;
  }
  .access-toggle-card {
    border: 1px solid rgba(226, 232, 240, .9);
    background: rgba(255, 255, 255, .72);
  }
  body.admin-dark .access-toggle-card,
  .admin-dark-ready body .access-toggle-card {
    border-color: #334155;
    background: rgba(15, 23, 42, .72);
  }
</style>
<section class="grid gap-4">
  <div class="mb-2">
    <h2 class="text-xl font-medium">Settings</h2>
    <p class="mt-1 text-sm text-slate-500">Manage admin access and system preferences.</p>
  </div>

  <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
    <article class="rounded-lg border border-slate-200 bg-white p-4">
      <h3 class="mb-4 text-base font-medium">System Settings</h3>
      <div class="flex flex-wrap items-center gap-2">
        <button id="themeToggleButton" class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="toggleSettingsTheme()" aria-pressed="<?= $currentTheme === 'dark' ? 'true' : 'false' ?>" title="<?= $currentTheme === 'dark' ? 'Current mode: Dark' : 'Current mode: Light' ?>"><i class="fa-solid <?= $currentTheme === 'dark' ? 'fa-sun' : 'fa-moon' ?> text-amber-600"></i><span><?= $currentTheme === 'dark' ? 'Dark Mode' : 'Light Mode' ?></span></button>
        <button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="openSettingsModal('passwordModal')"><i class="fa-solid fa-key text-emerald-600"></i><span>Change Password</span></button>
        <?php if ($isSuperAdmin): ?><button class="secondary inline-flex items-center justify-center gap-2 px-3 py-2 text-sm" type="button" onclick="openDeletedGalleryModal()"><i class="fa-solid fa-trash-can text-red-600"></i><span>Deleted Gallery</span></button><?php endif; ?>
      </div>
    </article>

    <?php if ($isSuperAdmin && $selectedSite): ?>
      <article class="admin-card">
        <h3 class="mb-1 text-base font-medium">Admin Users</h3>
        <p class="mb-4 text-xs text-slate-500">Only for <?= htmlspecialchars((string) $selectedSite['site_name'], ENT_QUOTES, 'UTF-8') ?></p>
        <div id="usersList" class="grid gap-2 text-sm">
          <?php foreach ($users as $user): ?>
            <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2" data-user-row="<?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?>">
              <div class="min-w-0">
                <p class="truncate font-medium text-slate-800"><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-slate-500"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars((string) $user['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600" data-user-theme><?= htmlspecialchars((string) $user['theme'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">No admin user is assigned to this site.</p>
          <?php endif; ?>
        </div>
      </article>
    <?php endif; ?>
  </div>

  <?php if ($selectedSite): ?>
    <div class="grid gap-4 xl:grid-cols-2">
      <article class="admin-card">
        <h3 class="mb-1 text-base font-medium">Brand</h3>
        <p class="mb-4 text-xs text-slate-500">Only for <?= htmlspecialchars((string) ($selectedSite['site_name'] ?? 'the selected site'), ENT_QUOTES, 'UTF-8') ?></p>
        <form id="brandForm" class="grid gap-4" enctype="multipart/form-data">
          <input type="hidden" name="action" value="brand">
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Brand name <input class="form-control" name="brand_name" value="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>"></label>
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Brand subtitle <input class="form-control" name="brand_subtitle" value="<?= htmlspecialchars($brandSubtitle, ENT_QUOTES, 'UTF-8') ?>"></label>
          </div>
          <div class="grid gap-3 sm:grid-cols-[96px_minmax(0,1fr)] sm:items-center">
            <div class="grid h-24 w-24 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
              <?php if ($brandLogo !== ''): ?>
                <img id="brandLogoPreview" class="max-h-full max-w-full object-contain" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
              <?php else: ?>
                <img id="brandLogoPreview" class="hidden max-h-full max-w-full object-contain" src="" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
                <i id="brandLogoPlaceholder" class="fa-solid fa-building text-3xl text-slate-300" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <label class="grid gap-1.5 text-sm font-medium text-slate-700">Logo
              <button class="secondary inline-flex w-fit items-center justify-center gap-2" type="button" onclick="pickBrandLogo()"><i class="fa-solid fa-image"></i>Choose Logo</button>
              <input id="brandLogoFileInput" class="hidden" name="brand_logo_file" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml">
              <span id="brandLogoFileName" class="text-xs font-normal text-slate-500">No logo selected</span>
            </label>
          </div>
          <div class="flex justify-end"><button class="primary" type="submit">Save Brand</button></div>
        </form>
      </article>

      <?php if ($isSuperAdmin): ?>
      <article class="admin-card">
        <h3 class="mb-1 text-base font-medium">Admin Access Control</h3>
        <p class="mb-4 text-xs text-slate-500">Only for <?= htmlspecialchars((string) $selectedSite['site_name'], ENT_QUOTES, 'UTF-8') ?></p>
        <form id="userControlsForm" class="grid gap-3">
          <input type="hidden" name="action" value="user_controls">
          <?php foreach (array_filter($users, fn ($user) => ($user['username'] ?? '') !== ADMIN_USERNAME) as $user): ?>
            <?php
              $permissions = admin_permissions($user);
              $userBusinessType = (string) ($user['business_type'] ?? 'general-business');
              $userBusinessProfile = admin_business_profile($userBusinessType);
              $permissionOptions = admin_permission_options_for_business_type($userBusinessType);
            ?>
            <div class="grid gap-3 rounded-lg bg-slate-50 p-3" data-access-row data-user-id="<?= (int) $user['id'] ?>">
              <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                <div class="min-w-0">
                  <p class="truncate font-medium text-slate-800"><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($user['site_name'] ?: 'No assigned site'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $userBusinessProfile['label'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="access-toggle-card grid w-full min-w-0 grid-cols-[3.35rem_minmax(0,1fr)] items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-slate-700 sm:w-36">
                  <label class="contents">
                    <input class="peer sr-only" name="users[<?= (int) $user['id'] ?>][is_active]" type="checkbox" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>>
                    <span class="access-switch"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
                  </label>
                  <span>Active</span>
                </div>
              </div>
              <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($permissionOptions as $permission => $permissionLabel): ?>
                  <div class="access-toggle-card grid min-w-0 grid-cols-[3.35rem_minmax(0,1fr)] items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-slate-600">
                    <label class="contents">
                      <input class="peer sr-only" name="users[<?= (int) $user['id'] ?>][permissions][<?= htmlspecialchars((string) $permission, ENT_QUOTES, 'UTF-8') ?>]" type="checkbox" <?= !empty($permissions[$permission]) ? 'checked' : '' ?>>
                      <span class="access-switch"><span class="switch-on">ON</span><span class="switch-off">OFF</span></span>
                    </label>
                    <span class="truncate"><?= htmlspecialchars((string) $permissionLabel, ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
              <button class="danger inline-flex w-fit items-center justify-center gap-2" type="button" onclick="deleteAdminUser(<?= (int) $user['id'] ?>, '<?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?>')"><i class="fa-solid fa-trash"></i>Delete User</button>
            </div>
          <?php endforeach; ?>
          <?php if (!array_filter($users, fn ($user) => ($user['username'] ?? '') !== ADMIN_USERNAME)): ?>
            <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">No admin access is available for this site.</p>
          <?php endif; ?>
        </form>
      </article>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<div id="passwordModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
  <div class="my-3 w-full max-w-md rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
    <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
      <h3 class="text-lg font-medium">Change Password</h3>
      <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeSettingsModal('passwordModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="passwordForm" class="grid gap-4">
      <input type="hidden" name="action" value="password">
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Current password <input class="form-control" name="current_password" type="password" autocomplete="current-password" required></label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">New password <input class="form-control" name="new_password" type="password" autocomplete="new-password" minlength="8" required></label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Confirm password <input class="form-control" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required></label>
      <div class="grid gap-2 sm:flex sm:justify-end">
        <button class="secondary w-full sm:w-auto" type="button" onclick="closeSettingsModal('passwordModal')">Cancel</button>
        <button class="primary w-full sm:w-auto" type="submit"><i class="fa-solid fa-key mr-2"></i>Change Password</button>
      </div>
    </form>
  </div>
</div>

<?php if ($isSuperAdmin): ?>
<div id="deletedGalleryModal" class="modal-backdrop fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-slate-950/60 p-3 sm:p-4 md:items-center">
  <div class="my-3 w-full max-w-4xl rounded-lg bg-white p-4 shadow-2xl sm:my-6 sm:p-5">
    <div class="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
      <h3 class="text-lg font-medium">Deleted Gallery</h3>
      <button class="secondary grid h-8 w-8 place-items-center px-0 py-0" type="button" onclick="closeSettingsModal('deletedGalleryModal')" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="deletedGalleryList" class="grid gap-2">
      <div class="grid min-h-32 place-items-center sm:col-span-2 lg:col-span-3">
        <div class="grid justify-items-center gap-2">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-slate-300 border-t-[#2FABE1]"></div>
          <p class="text-sm text-slate-500">Loading...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
<?php
$script = 'const currentAdminUsername = ' . json_encode(admin_username()) . ';' . PHP_EOL . <<<'JS'
function openSettingsModal(id) {
  document.querySelector(`#${id}`)?.classList.add('active');
}

function closeSettingsModal(id) {
  document.querySelector(`#${id}`)?.classList.remove('active');
}

async function submitSettingsForm(form, message) {
  try {
    const data = await request(adminPage('settings/'), {
      method: 'POST',
      headers: adminHeaders(),
      body: new FormData(form),
    });
    success(message);
    return data;
  } catch (error) {
    failure(error.message || 'Could not save settings');
    return null;
  }
}

function updateSettingsThemeButton(theme) {
  const button = document.querySelector('#themeToggleButton');
  const icon = button?.querySelector('i');
  const label = button?.querySelector('span');
  if (icon) icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  if (label) label.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
  if (button) {
    button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    button.title = `Current mode: ${theme === 'dark' ? 'Dark' : 'Light'}`;
  }
}

async function toggleSettingsTheme() {
  const nextTheme = document.body.classList.contains('admin-dark') ? 'light' : 'dark';
  const body = new FormData();
  body.set('action', 'theme');
  body.set('theme', nextTheme);
  try {
    const data = await request(adminPage('settings/'), {
      method: 'POST',
      headers: adminHeaders(),
      body,
    });
    if (data?.theme) {
      setAdminTheme(data.theme, false);
      updateSettingsThemeButton(data.theme);
      updateUserTheme(currentAdminUsername, data.theme);
    }
  } catch (error) {
    failure(error.message || 'Could not save mode');
  }
}

updateSettingsThemeButton(document.body.classList.contains('admin-dark') ? 'dark' : 'light');

document.querySelector('#passwordForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const saved = await submitSettingsForm(event.currentTarget, 'Password changed');
  if (saved) {
    event.currentTarget.reset();
    closeSettingsModal('passwordModal');
  }
});

document.querySelector('#brandForm')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const saved = await submitSettingsForm(event.currentTarget, 'Brand saved');
  if (saved) window.location.reload();
});

document.querySelector('#brandForm [name="brand_logo_file"]')?.addEventListener('change', (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  document.querySelector('#brandLogoFileName').textContent = file.name;
  const preview = document.querySelector('#brandLogoPreview');
  preview.classList.remove('hidden');
  preview.src = URL.createObjectURL(file);
  document.querySelector('#brandLogoPlaceholder')?.classList.add('hidden');
});

function pickBrandLogo() {
  document.querySelector('#brandLogoFileInput')?.click();
}

function media(path) {
  if (!path) return '';
  if (/^https?:\/\//.test(path)) return path;
  return path.startsWith('/') ? path : `/${path.replace(/^\/+/, '')}`;
}

function deletedGalleryLoadingMarkup() {
  return '<div class="grid min-h-32 place-items-center sm:col-span-2 lg:col-span-3"><div class="grid justify-items-center gap-2"><div class="h-8 w-8 animate-spin rounded-full border-4 border-slate-300 border-t-[#2FABE1]"></div><p class="text-sm text-slate-500">Loading...</p></div></div>';
}

async function openDeletedGalleryModal() {
  openSettingsModal('deletedGalleryModal');
  await loadDeletedGallery();
}

async function loadDeletedGallery() {
  const list = document.querySelector('#deletedGalleryList');
  list.innerHTML = deletedGalleryLoadingMarkup();
  try {
    const data = await request(api('gallery.php', { deleted: 1 }), { headers: adminHeaders() });
    const items = data.items || [];
    list.innerHTML = items.map((item) => `
      <article class="grid gap-3 rounded-lg border border-slate-200 bg-white p-2 shadow-lg shadow-slate-900/5 sm:grid-cols-[88px_minmax(0,1fr)] sm:items-center">
        <img class="h-20 w-full rounded-md bg-slate-100 object-cover sm:h-16 sm:w-22" src="${media(item.image_path)}" alt="${escapeHtml(item.alt_text || item.title || 'Deleted gallery image')}">
        <div class="grid gap-2">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-900" title="${escapeHtml(item.title || 'Untitled image')}">${escapeHtml(item.title || 'Untitled image')}</p>
            <p class="truncate text-xs text-slate-500">${escapeHtml(item.location || '')}${item.category ? ' | ' + escapeHtml(item.category) : ''}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="secondary inline-flex items-center justify-center gap-2" type="button" onclick="restoreDeletedGallery(${item.id})"><i class="fa-solid fa-rotate-left"></i>Undo</button>
            <button class="danger inline-flex items-center justify-center gap-2" type="button" onclick="permanentDeleteGallery(${item.id})"><i class="fa-solid fa-trash"></i>Delete</button>
          </div>
        </div>
      </article>
    `).join('') || '<p class="text-sm text-slate-500 sm:col-span-2 lg:col-span-3">No deleted gallery images.</p>';
  } catch (error) {
    list.innerHTML = `<p class="text-sm text-red-600 sm:col-span-2 lg:col-span-3">${escapeHtml(error.message || 'Could not load deleted gallery')}</p>`;
  }
}

async function restoreDeletedGallery(id) {
  try {
    await request(api('gallery.php', { id }), { method: 'PATCH', headers: adminHeaders() });
    success('Gallery image restored');
    loadDeletedGallery();
  } catch (error) {
    failure(error.message || 'Could not restore image');
  }
}

async function permanentDeleteGallery(id) {
  if (!await confirmDelete('Permanently delete this image and its unused cPanel upload?')) return;
  try {
    const result = await request(api('gallery.php', { id, permanent: 1 }), { method: 'DELETE', headers: adminHeaders() });
    success(result.message || 'Gallery image permanently deleted');
    await loadDeletedGallery();
  } catch (error) {
    failure(error.message || 'Could not permanently delete image');
  }
}

async function deleteAdminUser(id, username) {
  const result = await Swal.fire({
    title: `Delete ${username}?`,
    text: 'This permanently removes the admin account. This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Delete User',
    cancelButtonText: 'Cancel',
  });
  if (!result.isConfirmed) return;

  const body = new FormData();
  body.set('action', 'delete_user');
  body.set('user_id', id);
  try {
    await request(adminPage('settings/'), {
      method: 'POST',
      headers: adminHeaders(),
      body,
    });
    document.querySelector(`[data-access-row][data-user-id="${id}"]`)?.remove();
    document.querySelector(`[data-user-row="${CSS.escape(username)}"]`)?.remove();
    success('Admin user deleted');
  } catch (error) {
    failure(error.message || 'Could not delete admin user');
  }
}

document.querySelector('#userControlsForm')?.addEventListener('change', async (event) => {
  if (!event.target.matches('input[type="checkbox"]')) return;
  const input = event.target;
  const row = input.closest('[data-access-row]');
  if (!row) return;

  const previousChecked = !input.checked;
  const staffPermission = row.querySelector('input[name$="[permissions][staffs]"]');
  const taskPermission = row.querySelector('input[name$="[permissions][tasks]"]');
  const previousTaskChecked = taskPermission?.checked;
  if (input === staffPermission && !input.checked && taskPermission) {
    taskPermission.checked = false;
    taskPermission.disabled = true;
  }
  if (input === staffPermission && input.checked && taskPermission) {
    taskPermission.disabled = false;
  }
  if (input === taskPermission && staffPermission && !staffPermission.checked) {
    input.checked = false;
    failure('Turn on Staffs before enabling Task');
    return;
  }
  if (input.name.endsWith('[is_active]') && !input.checked) {
    const result = await Swal.fire({
      title: 'Deactivate admin?',
      text: 'This admin will not be able to log in, and an active session will be logged out.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Deactivate',
      cancelButtonText: 'Cancel',
    });
    if (!result.isConfirmed) {
      input.checked = true;
      return;
    }
  }

  input.disabled = true;
  try {
    const body = new FormData();
    body.set('action', 'user_controls');
    body.set('user_id', row.dataset.userId);
    row.querySelectorAll('input[type="checkbox"]').forEach((field) => {
      if (field.checked) body.append(field.name, 'on');
    });
    await request(adminPage('settings/'), {
      method: 'POST',
      headers: adminHeaders(),
      body,
    });
    success('Access updated');
  } catch (error) {
    input.checked = previousChecked;
    if (taskPermission && previousTaskChecked !== undefined) taskPermission.checked = previousTaskChecked;
    failure(error.message || 'Could not save access');
  } finally {
    input.disabled = false;
    if (taskPermission) taskPermission.disabled = !!staffPermission && !staffPermission.checked;
  }
});

document.querySelectorAll('[data-access-row]').forEach((row) => {
  const staffPermission = row.querySelector('input[name$="[permissions][staffs]"]');
  const taskPermission = row.querySelector('input[name$="[permissions][tasks]"]');
  if (taskPermission && staffPermission && !staffPermission.checked) {
    taskPermission.checked = false;
    taskPermission.disabled = true;
  }
});

function updateUserTheme(username, theme) {
  const row = [...document.querySelectorAll('[data-user-row]')].find((entry) => entry.dataset.userRow === username);
  const badge = row?.querySelector('[data-user-theme]');
  if (badge) badge.textContent = theme;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  })[char]);
}
JS;

admin_footer($script);
