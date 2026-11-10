<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_ensure_company_registration_tables();

function sites_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sites_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sites_badge_class(string $status): string
{
    return match ($status) {
        'active', 'ready' => 'border border-emerald-500 text-emerald-700',
        'suspended', 'archived', 'blocked' => 'border border-red-500 text-red-700',
        'in_progress' => 'border border-sky-500 text-sky-700',
        default => 'border border-amber-500 text-amber-700',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_page('sites');
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        sites_json(['ok' => false, 'error' => 'Admin token required'], 401);
    }

    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id < 1) {
        sites_json(['ok' => false, 'error' => 'Site id is required']);
    }

    $pdo = Database::pdo();

    if ($action === 'regenerate_api_key') {
        if (!admin_is_super_admin()) {
            sites_json(['ok' => false, 'error' => 'Super admin only'], 403);
        }
        $apiKey = 'judy_' . bin2hex(random_bytes(24));
        $stmt = $pdo->prepare('UPDATE company_sites SET api_key = ? WHERE id = ?');
        $stmt->execute([$apiKey, $id]);
        if ($stmt->rowCount() < 1) {
            sites_json(['ok' => false, 'error' => 'Site not found'], 404);
        }
        sites_json(['ok' => true, 'apiKey' => $apiKey]);
    }

    if ($action === 'status') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'suspended', 'pending_setup', 'archived'], true)) {
            sites_json(['ok' => false, 'error' => 'Invalid site status']);
        }
        $stmt = $pdo->prepare(
            'UPDATE company_sites
             SET status = ?,
                 activated_at = CASE WHEN ? = "active" THEN NOW() ELSE activated_at END,
                 suspended_at = CASE WHEN ? = "suspended" THEN NOW() ELSE suspended_at END
             WHERE id = ?'
        );
        $stmt->execute([$status, $status, $status, $id]);
        sites_json(['ok' => true, 'status' => $status]);
    }

    if ($action === 'setup') {
        $setupStatus = (string) ($_POST['setup_status'] ?? '');
        if (!in_array($setupStatus, ['pending', 'in_progress', 'ready', 'blocked'], true)) {
            sites_json(['ok' => false, 'error' => 'Invalid setup status']);
        }
        $stmt = $pdo->prepare('UPDATE company_sites SET setup_status = ? WHERE id = ?');
        $stmt->execute([$setupStatus, $id]);
        sites_json(['ok' => true, 'setupStatus' => $setupStatus]);
    }

    if ($action === 'details') {
        $domain = trim((string) ($_POST['primary_domain'] ?? ''));
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        if ($domain !== '' && !preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }
        if ($domain !== '' && (!filter_var($domain, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($domain, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            sites_json(['ok' => false, 'error' => 'Site website URL must be a valid HTTP or HTTPS address'], 400);
        }
        $stmt = $pdo->prepare('UPDATE company_sites SET primary_domain = ?, admin_note = ? WHERE id = ?');
        $stmt->execute([$domain !== '' ? $domain : null, $note !== '' ? $note : null, $id]);
        sites_json(['ok' => true, 'primaryDomain' => $domain, 'adminNote' => $note]);
    }

    sites_json(['ok' => false, 'error' => 'Unknown action'], 400);
}

$pdo = Database::pdo();
$counts = ['total' => 0, 'active' => 0, 'suspended' => 0, 'pending_setup' => 0, 'archived' => 0];
$countRows = $pdo->query('SELECT status, COUNT(*) AS total FROM company_sites GROUP BY status')->fetchAll();
foreach ($countRows as $row) {
    $status = (string) $row['status'];
    $count = (int) $row['total'];
    $counts['total'] += $count;
    if (isset($counts[$status])) {
        $counts[$status] = $count;
    }
}

$sites = $pdo->query(
    'SELECT company_sites.*, company_registrations.registration_ref, company_registrations.website_type,
            site_admin.username AS site_admin_username
     FROM company_sites
     INNER JOIN company_registrations ON company_registrations.id = company_sites.company_registration_id
     LEFT JOIN admin_users AS site_admin ON site_admin.site_id = company_sites.id
     ORDER BY FIELD(company_sites.status, "pending_setup", "active", "suspended", "archived"), company_sites.created_at DESC
     LIMIT 500'
)->fetchAll();

admin_header('Sites', 'sites');
?>
<section>
  <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <h2 class="text-xl font-medium">All Sites</h2>
      <p class="mt-1 text-sm text-slate-500">Super admin control for approved company sites.</p>
    </div>
  </div>

  <div class="dashboard-stats-grid mb-5">
    <?php foreach ([['Total', 'total', 'fa-globe', 'sky'], ['Active', 'active', 'fa-circle-check', 'emerald'], ['Suspended', 'suspended', 'fa-ban', 'red'], ['Pending Setup', 'pending_setup', 'fa-screwdriver-wrench', 'amber']] as [$label, $key, $icon, $color]): ?>
      <article class="admin-card dashboard-stat-card">
        <div class="min-w-0">
          <span class="text-sm font-medium text-slate-500"><?= sites_h($label) ?></span>
          <div class="mt-1 text-xl font-medium tracking-tight"><?= (int) $counts[$key] ?></div>
        </div>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-<?= sites_h($color) ?>-50 text-<?= sites_h($color) ?>-600"><i class="fa-solid <?= sites_h($icon) ?>"></i></span>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="admin-card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="admin-table w-full min-w-[980px] text-left">
        <thead>
          <tr>
            <th>Site</th>
            <th>Owner</th>
            <th>Plan</th>
            <th>Status</th>
            <th>Setup</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sites as $site): ?>
            <?php $id = (int) $site['id']; ?>
            <tr data-site-row="<?= $id ?>">
              <td>
                <div class="font-medium text-slate-900"><?= sites_h($site['site_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= sites_h($site['site_ref']) ?> | /<?= sites_h($site['site_slug']) ?></div>
                <div class="mt-1 text-xs text-slate-500" data-domain><?= sites_h($site['primary_domain'] ?: 'No domain set') ?></div>
              </td>
              <td>
                <div class="font-medium text-slate-800"><?= sites_h($site['owner_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= sites_h($site['owner_email']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= sites_h($site['owner_phone']) ?></div>
                <div class="mt-2 text-xs font-medium text-sky-700">Login: <span data-site-username><?= sites_h($site['site_admin_username'] ?: 'Not created') ?></span></div>
              </td>
              <td>
                <div class="font-medium text-slate-800"><?= sites_h($site['plan_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= sites_h($site['website_type']) ?></div>
              </td>
              <td>
                <span data-status class="inline-flex rounded-md px-2 py-1 text-xs font-medium <?= sites_h(sites_badge_class((string) $site['status'])) ?>"><?= sites_h($site['status']) ?></span>
              </td>
              <td>
                <span data-setup-status class="inline-flex rounded-md px-2 py-1 text-xs font-medium <?= sites_h(sites_badge_class((string) $site['setup_status'])) ?>"><?= sites_h($site['setup_status']) ?></span>
              </td>
              <td>
                <div class="flex flex-wrap gap-2">
                  <button class="primary" type="button" onclick="siteStatus(<?= $id ?>, 'active')">Activate</button>
                  <button class="secondary" type="button" onclick="siteStatus(<?= $id ?>, 'suspended')">Suspend</button>
                  <button class="secondary" type="button" onclick="siteSetup(<?= $id ?>, 'in_progress')">Setup</button>
                  <button class="primary" type="button" onclick="siteSetup(<?= $id ?>, 'ready')">Ready</button>
                  <button class="secondary" type="button" onclick="editSite(<?= $id ?>, <?= sites_h(json_encode((string) ($site['primary_domain'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>, <?= sites_h(json_encode((string) ($site['admin_note'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>)">Edit</button>
                  <button class="secondary" type="button" onclick="copySiteLoginLink(<?= sites_h(json_encode(admin_url() . '?site=' . rawurlencode((string) $site['site_slug']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>)"><i class="fa-solid fa-link"></i>Login Link</button>
                  <button class="secondary" type="button" onclick="openSiteApi(<?= $id ?>, <?= sites_h(json_encode((string) $site['site_slug'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>, <?= sites_h(json_encode((string) ($site['api_key'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>)"><i class="fa-solid fa-code"></i>API</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$sites): ?>
            <tr><td colspan="6" class="text-center text-slate-500">No approved sites yet. Approve a company to create one.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<div id="siteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
  <form id="siteDetailsForm" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-start justify-between gap-4">
      <div>
        <h3 class="text-lg font-semibold text-slate-900">Site Details</h3>
        <p class="mt-1 text-sm text-slate-500">Set the site website URL used by its login page’s “Go back home” link.</p>
      </div>
      <button class="secondary" type="button" onclick="closeSiteModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <input type="hidden" name="action" value="details">
    <input type="hidden" name="id" id="siteEditId">
    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="siteDomain">Site website URL</label>
    <input class="form-control mb-4" name="primary_domain" id="siteDomain" placeholder="https://example.com/">
    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="siteNote">Admin note</label>
    <textarea class="form-control min-h-28" name="admin_note" id="siteNote" placeholder="DNS, setup, owner request, suspension reason..."></textarea>
    <button class="primary mt-4" type="submit"><i class="fa-regular fa-floppy-disk"></i>Save</button>
  </form>
</div>

<div id="siteApiModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
  <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-2xl">
    <div class="mb-5 flex items-start justify-between gap-4">
      <div>
        <h3 class="text-lg font-semibold text-slate-900">Site API</h3>
        <p class="mt-1 text-sm text-slate-500">Unique endpoint and key for isolated site data.</p>
      </div>
      <button class="secondary" type="button" onclick="closeSiteApi()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <input type="hidden" id="siteApiId">
    <div class="grid gap-4">
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Endpoint
        <span class="flex gap-2"><input id="siteApiEndpoint" class="form-control min-w-0 flex-1 font-mono text-xs" readonly><button class="secondary" type="button" onclick="copySiteApi('siteApiEndpoint')">Copy</button></span>
      </label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">API key
        <span class="flex gap-2"><input id="siteApiKey" class="form-control min-w-0 flex-1 font-mono text-xs" readonly><button class="secondary" type="button" onclick="copySiteApi('siteApiKey')">Copy</button></span>
      </label>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs leading-6 text-slate-600">
        <p><strong>Header:</strong> <code>X-Site-API-Key: your-key</code></p>
        <p><strong>Collection:</strong> add <code>&amp;collection=tours</code></p>
        <p><strong>One record:</strong> add <code>&amp;collection=tours&amp;key=stone-town</code></p>
        <p><strong>Write JSON:</strong> <code>{"key":"stone-town","data":{"title":"Stone Town"}}</code></p>
      </div>
      <div class="flex flex-wrap justify-between gap-3">
        <p class="text-xs text-amber-700">Regenerating immediately disables the old key.</p>
        <span class="flex flex-wrap gap-2">
          <button class="secondary" type="button" onclick="testSiteApi()"><i class="fa-solid fa-plug-circle-check"></i>Test connection</button>
          <button class="danger" type="button" onclick="regenerateSiteApiKey()"><i class="fa-solid fa-rotate"></i>Regenerate key</button>
        </span>
      </div>
    </div>
  </div>
</div>

<?php
admin_footer(<<<'JS'
async function copySiteLoginLink(path) {
  const url = new URL(path, window.location.origin).toString();
  try {
    await navigator.clipboard.writeText(url);
    success('Site login link copied');
  } catch (error) {
    window.prompt('Copy this site login link:', url);
  }
}

function siteClass(status) {
  if (status === 'active' || status === 'ready') return 'border border-emerald-500 text-emerald-700';
  if (status === 'suspended' || status === 'archived' || status === 'blocked') return 'border border-red-500 text-red-700';
  if (status === 'in_progress') return 'border border-sky-500 text-sky-700';
  return 'border border-amber-500 text-amber-700';
}

function siteBody(id, action) {
  const body = new FormData();
  body.set('id', id);
  body.set('action', action);
  return body;
}

async function siteStatus(id, status) {
  const body = siteBody(id, 'status');
  body.set('status', status);
  try {
    const data = await request(adminPage('sites/'), { method: 'POST', headers: adminHeaders(), body });
    const el = document.querySelector(`[data-site-row="${id}"] [data-status]`);
    el.textContent = data.status;
    el.className = `inline-flex rounded-md px-2 py-1 text-xs font-medium ${siteClass(data.status)}`;
    success('Site status updated');
  } catch (error) {
    failure(error.message || 'Could not update site');
  }
}

async function siteSetup(id, setupStatus) {
  const body = siteBody(id, 'setup');
  body.set('setup_status', setupStatus);
  try {
    const data = await request(adminPage('sites/'), { method: 'POST', headers: adminHeaders(), body });
    const el = document.querySelector(`[data-site-row="${id}"] [data-setup-status]`);
    el.textContent = data.setupStatus;
    el.className = `inline-flex rounded-md px-2 py-1 text-xs font-medium ${siteClass(data.setupStatus)}`;
    success('Site setup updated');
  } catch (error) {
    failure(error.message || 'Could not update setup');
  }
}

function editSite(id, domain, note) {
  document.querySelector('#siteEditId').value = id;
  document.querySelector('#siteDomain').value = domain || '';
  document.querySelector('#siteNote').value = note || '';
  document.querySelector('#siteModal').classList.remove('hidden');
  document.querySelector('#siteModal').classList.add('flex');
}

function closeSiteModal() {
  document.querySelector('#siteModal')?.classList.add('hidden');
  document.querySelector('#siteModal')?.classList.remove('flex');
}

function openSiteApi(id, slug, apiKey) {
  document.querySelector('#siteApiId').value = id;
  document.querySelector('#siteApiEndpoint').value = `${window.location.origin}/backend/api/site-data.php?site=${encodeURIComponent(slug)}`;
  document.querySelector('#siteApiKey').value = apiKey;
  document.querySelector('#siteApiModal').classList.remove('hidden');
  document.querySelector('#siteApiModal').classList.add('flex');
}

function closeSiteApi() {
  document.querySelector('#siteApiModal')?.classList.add('hidden');
  document.querySelector('#siteApiModal')?.classList.remove('flex');
}

async function copySiteApi(inputId) {
  const value = document.querySelector(`#${inputId}`)?.value || '';
  try {
    await navigator.clipboard.writeText(value);
    success('Copied');
  } catch (_) {
    failure('Could not copy automatically');
  }
}

async function testSiteApi() {
  const endpoint = document.querySelector('#siteApiEndpoint')?.value || '';
  const apiKey = document.querySelector('#siteApiKey')?.value || '';
  if (!endpoint || !apiKey) {
    failure('This site does not have a complete API configuration');
    return;
  }
  try {
    const response = await fetch(endpoint, { headers: { 'X-Site-API-Key': apiKey } });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.error || `API returned ${response.status}`);
    success('Site API connection is working');
  } catch (error) {
    failure(error.message || 'Site API connection failed');
  }
}

async function regenerateSiteApiKey() {
  const result = await Swal.fire({ title: 'Regenerate API key?', text: 'The existing key will stop working immediately.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Regenerate', confirmButtonColor: '#dc2626' });
  if (!result.isConfirmed) return;
  const id = document.querySelector('#siteApiId').value;
  const body = siteBody(id, 'regenerate_api_key');
  try {
    const data = await request(adminPage('sites/'), { method: 'POST', headers: adminHeaders(), body });
    document.querySelector('#siteApiKey').value = data.apiKey;
    success('API key regenerated');
    window.setTimeout(() => window.location.reload(), 900);
  } catch (error) {
    failure(error.message || 'Could not regenerate API key');
  }
}

document.querySelector('#siteDetailsForm')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await request(adminPage('sites/'), { method: 'POST', headers: adminHeaders(), body: new FormData(event.currentTarget) });
    const id = document.querySelector('#siteEditId').value;
    const domain = document.querySelector(`[data-site-row="${id}"] [data-domain]`);
    if (domain) domain.textContent = data.primaryDomain || 'No domain set';
    closeSiteModal();
    success('Site details saved');
  } catch (error) {
    failure(error.message || 'Could not save site details');
  }
});
JS);
