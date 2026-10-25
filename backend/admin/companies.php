<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_ensure_company_registration_tables();

function companies_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function companies_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function companies_status_class(string $status): string
{
    return match ($status) {
        'approved', 'paid' => 'border border-emerald-500 text-emerald-700',
        'rejected', 'failed', 'refunded' => 'border border-red-500 text-red-700',
        default => 'border border-amber-500 text-amber-700',
    };
}

function companies_site_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));
    return trim($slug, '-') ?: 'site-' . time();
}

function companies_unique_site_slug(PDO $pdo, string $companyName, int $registrationId): string
{
    $base = companies_site_slug($companyName);
    $slug = $base;
    $suffix = 2;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM company_sites WHERE site_slug = ? AND company_registration_id <> ?');
    while (true) {
        $stmt->execute([$slug, $registrationId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function companies_provision_site(PDO $pdo, int $registrationId, ?int $adminId): array
{
    $stmt = $pdo->prepare('SELECT * FROM company_registrations WHERE id = ? LIMIT 1');
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch();
    if (!$registration) {
        companies_json(['ok' => false, 'error' => 'Company registration not found'], 404);
    }

    $siteRef = 'SITE-' . date('Y') . '-' . random_int(10000, 99999);
    $siteSlug = companies_unique_site_slug($pdo, (string) $registration['company_name'], $registrationId);
    $apiKey = 'judy_' . bin2hex(random_bytes(24));
    $siteStmt = $pdo->prepare(
        'INSERT INTO company_sites (
            company_registration_id, site_ref, site_name, site_slug, api_key, plan_id, plan_name, business_type,
            owner_name, owner_email, owner_phone, status, setup_status, created_by, activated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", "pending", ?, NOW())
        ON DUPLICATE KEY UPDATE
            site_name = VALUES(site_name),
            plan_id = VALUES(plan_id),
            plan_name = VALUES(plan_name),
            business_type = VALUES(business_type),
            owner_name = VALUES(owner_name),
            owner_email = VALUES(owner_email),
            owner_phone = VALUES(owner_phone),
            status = "active",
            activated_at = NOW(),
            suspended_at = NULL'
    );
    $siteStmt->execute([
        $registrationId,
        $siteRef,
        $registration['company_name'],
        $siteSlug,
        $apiKey,
        $registration['plan_id'],
        $registration['plan_name'],
        $registration['plan_id'],
        $registration['owner_name'],
        $registration['email'],
        $registration['phone'],
        $adminId,
    ]);

    $select = $pdo->prepare('SELECT * FROM company_sites WHERE company_registration_id = ? LIMIT 1');
    $select->execute([$registrationId]);
    $site = $select->fetch() ?: [];
    if ($site) {
        admin_seed_site_features((int) $site['id'], (string) $site['business_type']);
    }
    return $site;
}

function companies_activate_registered_login(PDO $pdo, int $registrationId, int $siteId): string
{
    $existingStmt = $pdo->prepare('SELECT username FROM admin_users WHERE site_id = ? LIMIT 1');
    $existingStmt->execute([$siteId]);
    $existingUsername = $existingStmt->fetchColumn();
    if ($existingUsername !== false) {
        $pdo->prepare('UPDATE company_registrations SET requested_password_hash = NULL WHERE id = ?')->execute([$registrationId]);
        return (string) $existingUsername;
    }

    $registrationStmt = $pdo->prepare(
        'SELECT requested_username, requested_password_hash
         FROM company_registrations
         WHERE id = ?
         LIMIT 1'
    );
    $registrationStmt->execute([$registrationId]);
    $registration = $registrationStmt->fetch();
    $username = trim((string) ($registration['requested_username'] ?? ''));
    $passwordHash = (string) ($registration['requested_password_hash'] ?? '');
    if ($username === '' || $passwordHash === '') {
        throw new RuntimeException('This older registration has no chosen login. Ask the company to submit a new registration.');
    }

    $takenStmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ?');
    $takenStmt->execute([$username]);
    if ((int) $takenStmt->fetchColumn() > 0) {
        throw new RuntimeException('The chosen username is no longer available. Ask the company to submit a new registration.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO admin_users (username, password_hash, theme, role, permissions, site_id, is_active)
         VALUES (?, ?, 'light', 'admin', ?, ?, 1)"
    );
    $siteStmt = $pdo->prepare('SELECT business_type FROM company_sites WHERE id = ? LIMIT 1');
    $siteStmt->execute([$siteId]);
    $businessType = (string) ($siteStmt->fetchColumn() ?: 'general-business');
    $insert->execute([$username, $passwordHash, json_encode(admin_site_permissions($businessType)), $siteId]);
    $pdo->prepare('UPDATE company_registrations SET requested_password_hash = NULL WHERE id = ?')->execute([$registrationId]);
    return $username;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin_page('companies');
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        companies_json(['ok' => false, 'error' => 'Admin token required'], 401);
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id < 1) {
        companies_json(['ok' => false, 'error' => 'Company registration id is required']);
    }

    $pdo = Database::pdo();

    if ($action === 'approve') {
        $currentUser = admin_current_user();
        $paymentCheck = $pdo->prepare('SELECT payment_status FROM company_registrations WHERE id = ? LIMIT 1');
        $paymentCheck->execute([$id]);
        if ($paymentCheck->fetchColumn() !== 'paid') {
            companies_json(['ok' => false, 'error' => 'ScayPay payment must be confirmed before approval'], 409);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE company_registrations SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND payment_status = 'paid'");
            $stmt->execute([$currentUser['id'] ?? null, $id]);
            $site = companies_provision_site($pdo, $id, isset($currentUser['id']) ? (int) $currentUser['id'] : null);
            $siteUsername = companies_activate_registered_login($pdo, $id, (int) ($site['id'] ?? 0));
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            companies_json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
        companies_json([
            'ok' => true,
            'status' => 'approved',
            'paymentStatus' => 'paid',
            'siteRef' => $site['site_ref'] ?? null,
            'siteUsername' => $siteUsername,
        ]);
    }

    if ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE company_registrations SET status = 'rejected', requested_password_hash = NULL, rejected_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        companies_json(['ok' => true, 'status' => 'rejected']);
    }

    if ($action === 'payment') {
        $paymentStatus = (string) ($_POST['payment_status'] ?? '');
        if (!in_array($paymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
            companies_json(['ok' => false, 'error' => 'Invalid payment status']);
        }
        $stmt = $pdo->prepare('UPDATE company_registrations SET payment_status = ? WHERE id = ?');
        $stmt->execute([$paymentStatus, $id]);
        companies_json(['ok' => true, 'paymentStatus' => $paymentStatus]);
    }

    if ($action === 'note') {
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        $stmt = $pdo->prepare('UPDATE company_registrations SET admin_note = ? WHERE id = ?');
        $stmt->execute([$note !== '' ? $note : null, $id]);
        companies_json(['ok' => true, 'adminNote' => $note]);
    }

    if ($action === 'delete') {
        $pdo->beginTransaction();
        try {
            $siteStmt = $pdo->prepare('SELECT id FROM company_sites WHERE company_registration_id = ? LIMIT 1');
            $siteStmt->execute([$id]);
            $siteId = (int) ($siteStmt->fetchColumn() ?: 0);
            if ($siteId > 0) {
                $deleteLogin = $pdo->prepare('DELETE FROM admin_users WHERE site_id = ?');
                $deleteLogin->execute([$siteId]);
            }
            $stmt = $pdo->prepare('DELETE FROM company_registrations WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            companies_json(['ok' => false, 'error' => 'Could not delete company registration'], 500);
        }
        companies_json(['ok' => true]);
    }

    companies_json(['ok' => false, 'error' => 'Unknown action'], 400);
}

$pdo = Database::pdo();
$counts = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$countRows = $pdo->query('SELECT status, COUNT(*) AS total FROM company_registrations GROUP BY status')->fetchAll();
foreach ($countRows as $row) {
    $status = (string) $row['status'];
    $count = (int) $row['total'];
    $counts['total'] += $count;
    if (isset($counts[$status])) {
        $counts[$status] = $count;
    }
}

$registrations = $pdo->query(
    'SELECT company_registrations.*, admin_users.username AS approved_by_username
     FROM company_registrations
     LEFT JOIN admin_users ON admin_users.id = company_registrations.approved_by
     ORDER BY FIELD(company_registrations.status, "pending", "approved", "rejected"), company_registrations.created_at DESC
     LIMIT 500'
)->fetchAll();

admin_header('Companies', 'companies');
?>
<section>
  <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <h2 class="text-xl font-medium">Company Registrations</h2>
      <p class="mt-1 text-sm text-slate-500">Review plan requests, confirm payment, and approve company access.</p>
    </div>
  </div>

  <div class="dashboard-stats-grid mb-5">
    <?php foreach ([['Total', 'total', 'fa-building-user', 'sky'], ['Pending', 'pending', 'fa-clock', 'amber'], ['Approved', 'approved', 'fa-circle-check', 'emerald'], ['Rejected', 'rejected', 'fa-circle-xmark', 'red']] as [$label, $key, $icon, $color]): ?>
      <article class="admin-card dashboard-stat-card">
        <div class="min-w-0">
          <span class="text-sm font-medium text-slate-500"><?= companies_h($label) ?></span>
          <div class="mt-1 text-xl font-medium tracking-tight" data-count="<?= companies_h($key) ?>"><?= (int) $counts[$key] ?></div>
        </div>
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-<?= companies_h($color) ?>-50 text-<?= companies_h($color) ?>-600"><i class="fa-solid <?= companies_h($icon) ?>"></i></span>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="admin-card overflow-hidden p-0">
    <div class="overflow-x-auto">
      <table class="admin-table w-full min-w-[980px] text-left">
        <thead>
          <tr>
            <th>Company</th>
            <th>Plan</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="companyRows">
          <?php foreach ($registrations as $registration): ?>
            <?php
              $id = (int) $registration['id'];
              $status = (string) $registration['status'];
              $paymentStatus = (string) $registration['payment_status'];
              $details = [
                  'ref' => (string) $registration['registration_ref'],
                  'company' => (string) $registration['company_name'],
                  'owner' => (string) $registration['owner_name'],
                  'email' => (string) $registration['email'],
                  'phone' => (string) $registration['phone'],
                  'username' => (string) ($registration['requested_username'] ?? ''),
                  'websiteType' => (string) $registration['website_type'],
                  'plan' => (string) $registration['plan_name'],
                  'monthlyPrice' => (string) $registration['monthly_price'],
                  'setupFee' => (string) $registration['setup_fee'],
                  'firstPayment' => (string) $registration['first_payment'],
                  'currency' => (string) $registration['currency'],
                  'paymentMethod' => (string) $registration['payment_method'],
                  'paymentStatus' => $paymentStatus,
                  'status' => $status,
                  'note' => (string) ($registration['admin_note'] ?? ''),
                  'createdAt' => (string) $registration['created_at'],
                  'approvedAt' => (string) ($registration['approved_at'] ?? ''),
                  'approvedBy' => (string) ($registration['approved_by_username'] ?? ''),
              ];
            ?>
            <tr data-company-row="<?= $id ?>" data-company='<?= companies_h(json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'>
              <td>
                <div class="font-medium text-slate-900"><?= companies_h($registration['company_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= companies_h($registration['registration_ref']) ?> | <?= companies_h($registration['owner_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= companies_h($registration['email']) ?> | <?= companies_h($registration['phone']) ?></div>
                <div class="mt-1 text-xs font-medium text-sky-700">Login: <?= companies_h($registration['requested_username'] ?: 'Legacy registration') ?></div>
              </td>
              <td>
                <div class="font-medium text-slate-800"><?= companies_h($registration['plan_name']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= companies_h($registration['website_type']) ?></div>
                <div class="mt-1 text-xs text-slate-500"><?= companies_h($registration['currency']) ?> <?= number_format((float) $registration['monthly_price'], 0) ?>/month + <?= companies_h($registration['currency']) ?> <?= number_format((float) $registration['setup_fee'], 0) ?> fee</div>
              </td>
              <td>
                <div class="font-medium text-slate-800"><?= companies_h($registration['currency']) ?> <?= number_format((float) $registration['first_payment'], 0) ?></div>
                <div class="mt-1 text-xs capitalize text-slate-500"><?= companies_h($registration['payment_method']) ?></div>
                <span data-payment-status class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-medium <?= companies_h(companies_status_class($paymentStatus)) ?>"><?= companies_h($paymentStatus) ?></span>
              </td>
              <td>
                <span data-status class="inline-flex rounded-md px-2 py-1 text-xs font-medium <?= companies_h(companies_status_class($status)) ?>"><?= companies_h($status) ?></span>
              </td>
              <td>
                <div class="text-sm text-slate-700"><?= companies_h(date('d/m/Y H:i', strtotime((string) $registration['created_at']))) ?></div>
                <?php if (!empty($registration['approved_at'])): ?>
                  <div class="mt-1 text-xs text-slate-500">Approved <?= companies_h(date('d/m/Y H:i', strtotime((string) $registration['approved_at']))) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="flex flex-wrap gap-2">
                  <button class="secondary" type="button" onclick="viewCompany(<?= $id ?>)"><i class="fa-regular fa-eye"></i>View</button>
                  <?php if ($status !== 'approved'): ?><button class="primary" type="button" onclick="updateCompany(<?= $id ?>, 'approve')">Approve</button><?php endif; ?>
                  <?php if ($status !== 'rejected'): ?><button class="secondary" type="button" onclick="updateCompany(<?= $id ?>, 'reject')">Reject</button><?php endif; ?>
                  <?php if ($paymentStatus !== 'paid'): ?><button class="primary" type="button" onclick="updatePayment(<?= $id ?>, 'paid')">Paid</button><?php endif; ?>
                  <button class="danger" type="button" onclick="deleteCompany(<?= $id ?>)"><i class="fa-regular fa-trash-can"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$registrations): ?>
            <tr><td colspan="6" class="text-center text-slate-500">No company registration requests yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<div id="companyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
  <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-start justify-between gap-4">
      <div>
        <h3 id="companyModalTitle" class="text-lg font-semibold text-slate-900">Company details</h3>
        <p id="companyModalMeta" class="mt-1 text-sm text-slate-500"></p>
      </div>
      <button class="secondary" type="button" onclick="closeCompanyModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div id="companyModalDetails" class="grid gap-3 sm:grid-cols-2"></div>
    <form id="companyNoteForm" class="mt-5 grid gap-2">
      <input type="hidden" name="action" value="note">
      <input type="hidden" name="id" id="companyNoteId">
      <label class="text-sm font-medium text-slate-700" for="companyAdminNote">Admin note</label>
      <textarea class="form-control min-h-24" name="admin_note" id="companyAdminNote" placeholder="Payment reference, follow-up notes, activation details..."></textarea>
      <button class="primary w-fit" type="submit"><i class="fa-regular fa-floppy-disk"></i>Save Note</button>
    </form>
  </div>
</div>

<?php
admin_footer(<<<'JS'
function companyClass(status) {
  if (status === 'approved' || status === 'paid') return 'border border-emerald-500 text-emerald-700';
  if (status === 'rejected' || status === 'failed' || status === 'refunded') return 'border border-red-500 text-red-700';
  return 'border border-amber-500 text-amber-700';
}

function companyBody(id, action) {
  const body = new FormData();
  body.set('id', id);
  body.set('action', action);
  return body;
}

function companyData(id) {
  const row = document.querySelector(`[data-company-row="${id}"]`);
  if (!row) return null;
  try {
    return JSON.parse(row.dataset.company || '{}');
  } catch {
    return null;
  }
}

function detailItem(label, value) {
  return `<div class="rounded-lg bg-slate-50 p-3"><div class="text-xs font-medium uppercase tracking-wide text-slate-500">${label}</div><div class="mt-1 break-words text-sm text-slate-800">${value || '-'}</div></div>`;
}

function viewCompany(id) {
  const data = companyData(id);
  if (!data) return;
  document.querySelector('#companyModalTitle').textContent = data.company || 'Company details';
  document.querySelector('#companyModalMeta').textContent = `${data.ref || ''} | ${data.status || ''}`;
  document.querySelector('#companyNoteId').value = id;
  document.querySelector('#companyAdminNote').value = data.note || '';
  document.querySelector('#companyModalDetails').innerHTML = [
    detailItem('Owner', data.owner),
    detailItem('Business Type', data.websiteType),
    detailItem('Email', data.email),
    detailItem('Phone', data.phone),
    detailItem('Chosen Username', data.username),
    detailItem('Plan', data.plan),
    detailItem('First Payment', `${data.currency || 'TZS'} ${Number(data.firstPayment || 0).toLocaleString()}`),
    detailItem('Monthly Price', `${data.currency || 'TZS'} ${Number(data.monthlyPrice || 0).toLocaleString()}`),
    detailItem('Setup Fee', `${data.currency || 'TZS'} ${Number(data.setupFee || 0).toLocaleString()}`),
    detailItem('Payment Method', data.paymentMethod),
    detailItem('Payment Status', data.paymentStatus),
    detailItem('Requested', data.createdAt),
    detailItem('Approved', data.approvedAt ? `${data.approvedAt} ${data.approvedBy ? 'by ' + data.approvedBy : ''}` : ''),
  ].join('');
  document.querySelector('#companyModal').classList.remove('hidden');
  document.querySelector('#companyModal').classList.add('flex');
}

function closeCompanyModal() {
  document.querySelector('#companyModal')?.classList.add('hidden');
  document.querySelector('#companyModal')?.classList.remove('flex');
}

async function updateCompany(id, action) {
  try {
    const data = await request(adminPage('companies/'), { method: 'POST', headers: adminHeaders(), body: companyBody(id, action) });
    const row = document.querySelector(`[data-company-row="${id}"]`);
    const status = data.status || (action === 'approve' ? 'approved' : 'rejected');
    const statusEl = row?.querySelector('[data-status]');
    if (statusEl) {
      statusEl.textContent = status;
      statusEl.className = `inline-flex rounded-md px-2 py-1 text-xs font-medium ${companyClass(status)}`;
    }
    if (data.paymentStatus) {
      const paymentEl = row?.querySelector('[data-payment-status]');
      paymentEl.textContent = data.paymentStatus;
      paymentEl.className = `mt-2 inline-flex rounded-md px-2 py-1 text-xs font-medium ${companyClass(data.paymentStatus)}`;
    }
    success(action === 'approve' ? `Company approved. Login ${data.siteUsername || ''} is now active.` : 'Company rejected');
  } catch (error) {
    failure(error.message || 'Could not update company');
  }
}

async function updatePayment(id, paymentStatus) {
  const body = companyBody(id, 'payment');
  body.set('payment_status', paymentStatus);
  try {
    await request(adminPage('companies/'), { method: 'POST', headers: adminHeaders(), body });
    const paymentEl = document.querySelector(`[data-company-row="${id}"] [data-payment-status]`);
    paymentEl.textContent = paymentStatus;
    paymentEl.className = `mt-2 inline-flex rounded-md px-2 py-1 text-xs font-medium ${companyClass(paymentStatus)}`;
    success('Payment status updated');
  } catch (error) {
    failure(error.message || 'Could not update payment');
  }
}

async function deleteCompany(id) {
  if (!await confirmDelete('Delete this company registration?')) return;
  try {
    await request(adminPage('companies/'), { method: 'POST', headers: adminHeaders(), body: companyBody(id, 'delete') });
    document.querySelector(`[data-company-row="${id}"]`)?.remove();
    success('Company registration deleted');
  } catch (error) {
    failure(error.message || 'Could not delete company registration');
  }
}

document.querySelector('#companyNoteForm')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await request(adminPage('companies/'), { method: 'POST', headers: adminHeaders(), body: new FormData(event.currentTarget) });
    const id = document.querySelector('#companyNoteId').value;
    const row = document.querySelector(`[data-company-row="${id}"]`);
    if (row) {
      const current = companyData(id) || {};
      current.note = data.adminNote || '';
      row.dataset.company = JSON.stringify(current);
    }
    success('Company note saved');
  } catch (error) {
    failure(error.message || 'Could not save note');
  }
});
JS);
