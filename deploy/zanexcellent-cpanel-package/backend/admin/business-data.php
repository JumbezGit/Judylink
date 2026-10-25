<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin_page('business-data');
admin_ensure_company_registration_tables();

$siteId = admin_selected_site_id();
$moduleKey = trim((string) ($_GET['module'] ?? $_POST['module'] ?? ''));
$feature = admin_selected_business_feature($moduleKey);
if (!$feature || (string) $feature['handler'] !== 'records') {
    header('Location: ' . admin_url('dashboard'));
    exit;
}
if (!admin_can_view('feature:' . $moduleKey)) {
    header('Location: ' . admin_first_allowed_url());
    exit;
}

function business_data_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function business_data_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        business_data_json(['ok' => false, 'error' => 'Unauthorized request'], 401);
    }
    if (!admin_can_manage_selected_site()) {
        business_data_json(['ok' => false, 'error' => 'You cannot manage this site'], 403);
    }

    $action = trim((string) ($_POST['action'] ?? 'save'));
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $stmt = Database::pdo()->prepare('DELETE FROM business_records WHERE id = ? AND site_id = ? AND feature_key = ?');
        $stmt->execute([$id, $siteId, $moduleKey]);
        business_data_json(['ok' => true]);
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'active')) ?: 'active';
    $amountText = trim((string) ($_POST['amount'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));
    if ($title === '') {
        business_data_json(['ok' => false, 'error' => 'Name or title is required'], 422);
    }
    $amount = $amountText === '' ? null : (float) $amountText;

    if ($id > 0) {
        $stmt = Database::pdo()->prepare(
            'UPDATE business_records SET title = ?, status = ?, amount = ?, details = ?
             WHERE id = ? AND site_id = ? AND feature_key = ?'
        );
        $stmt->execute([$title, $status, $amount, $details, $id, $siteId, $moduleKey]);
    } else {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]+/', '', $moduleKey) ?: 'ITEM', 0, 10));
        $recordRef = $prefix . '-' . date('ymd') . '-' . random_int(1000, 9999);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO business_records (site_id, feature_key, record_ref, title, status, amount, currency, details)
             VALUES (?, ?, ?, ?, ?, ?, "TZS", ?)'
        );
        $stmt->execute([$siteId, $moduleKey, $recordRef, $title, $status, $amount, $details]);
    }
    business_data_json(['ok' => true]);
}

$stmt = Database::pdo()->prepare(
    'SELECT id, record_ref, title, status, amount, currency, details, updated_at
     FROM business_records
     WHERE site_id = ? AND feature_key = ?
     ORDER BY updated_at DESC, id DESC'
);
$stmt->execute([$siteId, $moduleKey]);
$records = $stmt->fetchAll();
$featureLabel = (string) $feature['label'];

admin_header($featureLabel, 'business-data');
?>
<section>
  <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <h2 class="text-xl font-medium"><?= business_data_h($featureLabel) ?></h2>
      <p class="mt-1 text-sm text-slate-500">Manage <?= business_data_h(strtolower($featureLabel)) ?> for <?= business_data_h(admin_selected_site_label()) ?> only.</p>
    </div>
    <button class="primary" type="button" onclick="openBusinessRecord()"><i class="fa-solid fa-plus"></i> Add <?= business_data_h(rtrim($featureLabel, 's')) ?></button>
  </div>

  <div class="admin-card overflow-x-auto p-0">
    <table class="admin-table w-full min-w-[680px] text-left">
      <thead><tr><th>Reference</th><th>Name / title</th><th>Status</th><th>Amount</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($records as $record): ?>
          <tr data-row="<?= (int) $record['id'] ?>" data-record="<?= business_data_h(json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">
            <td class="font-medium text-slate-500"><?= business_data_h($record['record_ref']) ?></td>
            <td><strong class="font-medium text-slate-900"><?= business_data_h($record['title']) ?></strong><p class="mt-1 max-w-sm truncate text-xs text-slate-500"><?= business_data_h($record['details']) ?></p></td>
            <td><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium capitalize text-slate-700"><?= business_data_h($record['status']) ?></span></td>
            <td><?= $record['amount'] === null ? '—' : business_data_h(number_format((float) $record['amount'], 0) . ' ' . $record['currency']) ?></td>
            <td class="text-xs text-slate-500"><?= business_data_h(date('d M Y, H:i', strtotime((string) $record['updated_at']))) ?></td>
            <td><div class="flex justify-end gap-2"><button class="secondary" type="button" onclick="editBusinessRecord(<?= (int) $record['id'] ?>)"><i class="fa-solid fa-pen"></i></button><button class="danger" type="button" onclick="deleteBusinessRecord(<?= (int) $record['id'] ?>)"><i class="fa-solid fa-trash"></i></button></div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$records): ?><tr><td colspan="6" class="py-10 text-center text-slate-500">No <?= business_data_h(strtolower($featureLabel)) ?> have been added yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div id="businessRecordModal" class="modal-backdrop fixed inset-0 z-50 items-center justify-center bg-slate-950/60 p-4">
  <div class="w-full max-w-xl rounded-xl border border-slate-200 bg-white p-5 shadow-2xl">
    <div class="mb-4 flex items-center justify-between gap-3"><h3 id="businessRecordTitle" class="text-lg font-medium">Add <?= business_data_h(rtrim($featureLabel, 's')) ?></h3><button class="secondary" type="button" onclick="closeBusinessRecord()"><i class="fa-solid fa-xmark"></i></button></div>
    <form id="businessRecordForm" class="grid gap-4">
      <input type="hidden" name="module" value="<?= business_data_h($moduleKey) ?>">
      <input id="businessRecordId" type="hidden" name="id">
      <label class="grid gap-1.5 text-sm font-medium">Name or title<input id="businessRecordName" class="form-control" name="title" maxlength="180" required></label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1.5 text-sm font-medium">Status<input id="businessRecordStatus" class="form-control" name="status" maxlength="60" value="active" required></label>
        <label class="grid gap-1.5 text-sm font-medium">Amount (TZS)<input id="businessRecordAmount" class="form-control" name="amount" type="number" min="0" step="0.01"></label>
      </div>
      <label class="grid gap-1.5 text-sm font-medium">Details<textarea id="businessRecordDetails" class="form-control min-h-28" name="details"></textarea></label>
      <div class="flex justify-end gap-2"><button class="secondary" type="button" onclick="closeBusinessRecord()">Cancel</button><button class="primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>
<?php
$moduleJson = json_encode($moduleKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
admin_footer(str_replace('MODULE_JSON', $moduleJson ?: '""', <<<'JS'
const businessModule = MODULE_JSON;
const businessModal = document.querySelector('#businessRecordModal');
const businessForm = document.querySelector('#businessRecordForm');

function openBusinessRecord(record = null) {
  businessForm.reset();
  document.querySelector('#businessRecordId').value = record?.id || '';
  document.querySelector('#businessRecordName').value = record?.title || '';
  document.querySelector('#businessRecordStatus').value = record?.status || 'active';
  document.querySelector('#businessRecordAmount').value = record?.amount ?? '';
  document.querySelector('#businessRecordDetails').value = record?.details || '';
  document.querySelector('#businessRecordTitle').textContent = record ? 'Edit record' : 'Add record';
  businessModal.classList.add('active');
}
function closeBusinessRecord() { businessModal.classList.remove('active'); }
function editBusinessRecord(id) {
  const row = document.querySelector(`[data-row="${id}"]`);
  openBusinessRecord(JSON.parse(row.dataset.record));
}
async function deleteBusinessRecord(id) {
  if (!await confirmDelete('Delete this record?')) return;
  const body = new FormData(); body.set('action', 'delete'); body.set('id', id); body.set('module', businessModule);
  try { await request(`${adminPage('business-data/')}?module=${encodeURIComponent(businessModule)}`, { method: 'POST', headers: adminHeaders(), body }); window.location.reload(); }
  catch (error) { failure(error.message || 'Could not delete record'); }
}
businessForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  try { await request(`${adminPage('business-data/')}?module=${encodeURIComponent(businessModule)}`, { method: 'POST', headers: adminHeaders(), body: new FormData(businessForm) }); window.location.reload(); }
  catch (error) { failure(error.message || 'Could not save record'); }
});
JS));
