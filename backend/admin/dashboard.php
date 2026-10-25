<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_admin_page('dashboard');
$siteId = admin_selected_site_id();
$businessType = admin_selected_business_type();

if ($businessType !== 'tours-travel') {
    admin_ensure_company_registration_tables();
    $profile = admin_business_profile($businessType);
    $featureStmt = Database::pdo()->prepare(
        'SELECT features.feature_key, features.label, features.icon, features.sort_order, COUNT(records.id) AS record_count
         FROM site_features AS features
         LEFT JOIN business_records AS records
           ON records.site_id = features.site_id AND records.feature_key = features.feature_key
         WHERE features.site_id = ? AND features.is_enabled = 1
         GROUP BY features.feature_key, features.label, features.icon, features.sort_order
         ORDER BY features.sort_order, features.feature_key'
    );
    $featureStmt->execute([$siteId]);
    $businessFeatures = array_values(array_filter(
        $featureStmt->fetchAll(),
        fn (array $feature): bool => admin_can_view('feature:' . (string) $feature['feature_key'])
    ));
    $recentStmt = Database::pdo()->prepare(
        'SELECT records.feature_key, records.record_ref, records.title, records.status, records.updated_at, features.label AS feature_label
         FROM business_records AS records
         INNER JOIN site_features AS features ON features.site_id = records.site_id AND features.feature_key = records.feature_key
         WHERE records.site_id = ?
         ORDER BY records.updated_at DESC
         LIMIT 8'
    );
    $recentStmt->execute([$siteId]);
    $recentRecords = array_values(array_filter(
        $recentStmt->fetchAll(),
        fn (array $record): bool => admin_can_view('feature:' . (string) $record['feature_key'])
    ));

    admin_header('Dashboard', 'dashboard');
    ?>
    <section>
      <div class="mb-5">
        <h2 class="text-xl font-medium"><?= htmlspecialchars((string) $profile['label'], ENT_QUOTES, 'UTF-8') ?> Dashboard</h2>
        <p class="mt-1 text-sm text-slate-500">Your modules and records are isolated to <?= htmlspecialchars(admin_selected_site_label(), ENT_QUOTES, 'UTF-8') ?>.</p>
      </div>
      <div class="dashboard-stats-grid">
        <?php foreach ($businessFeatures as $feature): ?>
          <a class="admin-card dashboard-stat-card no-underline transition hover:-translate-y-0.5 hover:border-sky-300" href="<?= htmlspecialchars(admin_url('business-data') . '?module=' . rawurlencode((string) $feature['feature_key']), ENT_QUOTES, 'UTF-8') ?>">
            <div class="min-w-0"><span class="text-sm font-medium text-slate-500"><?= htmlspecialchars((string) $feature['label'], ENT_QUOTES, 'UTF-8') ?></span><div class="mt-1 text-xl font-medium tracking-tight text-slate-900"><?= (int) $feature['record_count'] ?></div><p class="mt-1 text-sm text-slate-500">Stored records</p></div>
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600"><i class="fa-solid <?= htmlspecialchars((string) $feature['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="dashboard-widgets-grid">
        <article class="admin-card">
          <h3 class="mb-3 text-base font-medium">Recent activity</h3>
          <div class="grid gap-2 text-sm text-slate-600">
            <?php foreach ($recentRecords as $record): ?>
              <a class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 no-underline" href="<?= htmlspecialchars(admin_url('business-data') . '?module=' . rawurlencode((string) $record['feature_key']), ENT_QUOTES, 'UTF-8') ?>">
                <div class="min-w-0"><div class="truncate font-medium text-slate-800"><?= htmlspecialchars((string) $record['title'], ENT_QUOTES, 'UTF-8') ?></div><div class="truncate text-xs text-slate-500"><?= htmlspecialchars((string) $record['feature_label'] . ' | ' . (string) $record['record_ref'], ENT_QUOTES, 'UTF-8') ?></div></div>
                <span class="shrink-0 text-xs font-medium capitalize text-slate-500"><?= htmlspecialchars((string) $record['status'], ENT_QUOTES, 'UTF-8') ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (!$recentRecords): ?><p class="text-slate-500">No records have been added yet. Choose a module above to begin.</p><?php endif; ?>
          </div>
        </article>
      </div>
    </section>
    <?php
    admin_footer();
    return;
}

$canBookings = admin_can_view('bookings');
$canGallery = admin_can_view('gallery');
$canTours = admin_can_view('tours');
$canStaffs = admin_can_view('staffs');
$canTasks = $canStaffs && admin_can_view('tasks');
$staffTotal = 0;
$staffPending = 0;
$taskTotal = 0;
$taskAssigned = 0;
$taskCompleted = 0;
$taskFeedbackDue = 0;
$latestTasks = [];
$visibleStatsCount = count(array_filter([$canBookings, $canGallery, $canTours, $canStaffs, $canTasks]));
$visibleWidgetCount = count(array_filter([$canBookings, $canGallery || $canTours, $canTasks]));

if ($canBookings) {
    admin_ensure_index('bookings', 'idx_bookings_created', 'ALTER TABLE bookings ADD INDEX idx_bookings_created (created_at)');
    admin_ensure_index('bookings', 'idx_bookings_status_payment', 'ALTER TABLE bookings ADD INDEX idx_bookings_status_payment (status, payment_status)');
}

if ($canStaffs || $canTasks) {
    admin_ensure_staff_tables();
}

if ($canStaffs) {
    $staffCountsStmt = Database::pdo()->prepare('SELECT status, COUNT(*) AS total FROM staffs WHERE site_id = ? GROUP BY status');
    $staffCountsStmt->execute([$siteId]);
    $staffCounts = $staffCountsStmt->fetchAll();
    foreach ($staffCounts as $staffCount) {
        $count = (int) $staffCount['total'];
        $staffTotal += $count;
        if ((string) $staffCount['status'] === 'pending') {
            $staffPending = $count;
        }
    }
}

if ($canTasks) {
    $taskCountsStmt = Database::pdo()->prepare(
        "SELECT status, COUNT(*) AS total, SUM(CASE WHEN status = 'completed' AND (feedback IS NULL OR feedback = '') THEN 1 ELSE 0 END) AS feedback_due
         FROM staff_tasks
         WHERE site_id = ?
         GROUP BY status"
    );
    $taskCountsStmt->execute([$siteId]);
    $taskCounts = $taskCountsStmt->fetchAll();
    foreach ($taskCounts as $taskCount) {
        $count = (int) $taskCount['total'];
        $taskTotal += $count;
        if ((string) $taskCount['status'] === 'assigned') {
            $taskAssigned = $count;
        } elseif ((string) $taskCount['status'] === 'completed') {
            $taskCompleted = $count;
        }
        $taskFeedbackDue += (int) $taskCount['feedback_due'];
    }
    $latestTasksStmt = Database::pdo()->prepare(
        'SELECT staff_tasks.id, staff_tasks.duty_group_id, staff_tasks.task_date, staff_tasks.tour_details, staff_tasks.guest_name, staff_tasks.hotel, staff_tasks.pickup_time, staff_tasks.status, staff_tasks.feedback,
                staffs.staff_type, staffs.first_name, staffs.second_name, staffs.third_name
         FROM staff_tasks
         INNER JOIN staffs ON staffs.id = staff_tasks.staff_id AND staffs.site_id = staff_tasks.site_id
         WHERE staff_tasks.site_id = ?
         ORDER BY staff_tasks.created_at DESC
         LIMIT 30'
    );
    $latestTasksStmt->execute([$siteId]);
    $latestTasks = $latestTasksStmt->fetchAll();
    $latestTaskGroups = [];
    foreach ($latestTasks as $task) {
        $key = (string) ($task['duty_group_id'] ?? '');
        if ($key === '') {
            $key = 'task_' . (string) $task['id'];
        }
        if (!isset($latestTaskGroups[$key])) {
            if (count($latestTaskGroups) >= 3) {
                continue;
            }
            $latestTaskGroups[$key] = [
                'task' => $task,
                'assignees' => [],
            ];
        }
        $staffName = trim((string) $task['first_name'] . ' ' . (string) $task['second_name'] . ' ' . (string) $task['third_name']);
        $latestTaskGroups[$key]['assignees'][] = $staffName . ' - ' . str_replace('_', ' ', (string) $task['staff_type']);
    }
}

admin_header('Dashboard', 'dashboard');
?>
<section>
  <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <h2 class="text-xl font-medium">Dashboard</h2>
      <p class="mt-1 text-sm text-slate-500">Quick view of website content and customer activity.</p>
      <?php if (admin_is_super_admin()): ?>
        <p class="mt-2 inline-flex max-w-full rounded-md bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700">
          Working site: <?= htmlspecialchars(admin_selected_site_label(), ENT_QUOTES, 'UTF-8') ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
  <div class="dashboard-stats-grid">
    <?php if ($canBookings): ?>
      <article class="admin-card dashboard-stat-card"><div class="min-w-0"><span class="text-sm font-medium text-slate-500">Bookings</span><div id="statBookings" class="mt-1 text-xl font-medium tracking-tight">0</div><p id="statBookingsMeta" class="mt-1 text-sm text-slate-500">Total stored bookings</p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-600"><i class="fa-solid fa-calendar-check"></i></span></article>
    <?php endif; ?>
    <?php if ($canGallery): ?>
      <article class="admin-card dashboard-stat-card"><div class="min-w-0"><span class="text-sm font-medium text-slate-500">Gallery</span><div id="statGallery" class="mt-1 text-xl font-medium tracking-tight">0/100</div><p class="mt-1 text-sm text-slate-500">Active database images</p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600"><i class="fa-solid fa-images"></i></span></article>
    <?php endif; ?>
    <?php if ($canTours): ?>
      <article class="admin-card dashboard-stat-card"><div class="min-w-0"><span class="text-sm font-medium text-slate-500">Tours</span><div id="statTours" class="mt-1 text-xl font-medium tracking-tight">0/20</div><p class="mt-1 text-sm text-slate-500">Active tour packages</p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fa-solid fa-route"></i></span></article>
    <?php endif; ?>
    <?php if ($canStaffs): ?>
      <article class="admin-card dashboard-stat-card"><div class="min-w-0"><span class="text-sm font-medium text-slate-500">Staff</span><div id="statStaffs" class="mt-1 text-xl font-medium tracking-tight"><?= $staffTotal ?></div><p id="statStaffsMeta" class="mt-1 text-sm text-slate-500"><?= $staffPending ?> pending approval</p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i class="fa-solid fa-users-gear"></i></span></article>
    <?php endif; ?>
    <?php if ($canTasks): ?>
      <article class="admin-card dashboard-stat-card"><div class="min-w-0"><span class="text-sm font-medium text-slate-500">Task</span><div class="mt-1 text-xl font-medium tracking-tight"><?= $taskTotal ?></div><p class="mt-1 text-sm text-slate-500"><?= $taskAssigned ?> new, <?= $taskCompleted ?> completed, <?= $taskFeedbackDue ?> feedback due</p></div><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600"><i class="fa-solid fa-list-check"></i></span></article>
    <?php endif; ?>
    <?php if ($visibleStatsCount === 0): ?>
      <article class="admin-card"><p class="text-sm text-slate-500">No dashboard cards are enabled for this admin.</p></article>
    <?php endif; ?>
  </div>
  <div class="dashboard-widgets-grid">
    <?php if ($canBookings): ?>
      <article class="admin-card"><h3 class="mb-3 text-base font-medium">Latest Bookings</h3><div id="latestBookings" class="grid gap-2 text-sm text-slate-600"></div></article>
    <?php endif; ?>
    <?php if ($canGallery || $canTours): ?>
      <article class="admin-card"><h3 class="mb-3 text-base font-medium">Content Health</h3><div id="contentHealth" class="grid gap-2 text-sm text-slate-600"></div></article>
    <?php endif; ?>
    <?php if ($canTasks): ?>
      <article class="admin-card dashboard-task-card w-full max-w-full overflow-hidden">
        <div class="mb-3 flex min-w-0 items-center justify-between gap-3">
          <h3 class="min-w-0 truncate text-base font-medium">Latest Tasks</h3>
          <a class="secondary shrink-0 px-3 py-2 text-xs" href="<?= htmlspecialchars(admin_url('tasks'), ENT_QUOTES, 'UTF-8') ?>">Open Task</a>
        </div>
        <div class="grid w-full min-w-0 max-w-full gap-2 text-sm text-slate-600">
          <?php foreach ($latestTaskGroups as $taskGroup): ?>
            <?php
              $task = $taskGroup['task'];
              $status = (string) $task['status'];
              $statusClass = match ($status) {
                  'assigned' => 'border-sky-500 text-sky-700',
                  'accepted' => 'border-emerald-500 text-emerald-700',
                  'completed' => empty($task['feedback']) ? 'border-amber-500 text-amber-700' : 'border-violet-500 text-violet-700',
                  'declined', 'cancelled' => 'border-red-500 text-red-700',
                  default => 'border-slate-400 text-slate-600',
              };
              $taskMeta = date('d/m/Y', strtotime((string) $task['task_date'])) . ' | ' . (string) $task['pickup_time'] . ' | ' . (string) $task['hotel'];
              $assignees = implode(', ', $taskGroup['assignees']);
            ?>
            <div class="w-full min-w-0 max-w-full overflow-hidden rounded-lg bg-slate-50 px-3 py-2">
              <div class="flex w-full min-w-0 items-center justify-between gap-3">
                <strong class="dashboard-task-line flex-1 text-slate-800" title="<?= htmlspecialchars((string) $task['guest_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $task['guest_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="max-w-[45%] shrink-0 truncate rounded-md border px-2 py-1 text-[11px] font-semibold capitalize <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <p class="dashboard-task-line mt-1 text-xs text-slate-500" title="<?= htmlspecialchars($taskMeta, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($taskMeta, ENT_QUOTES, 'UTF-8') ?></p>
              <p class="dashboard-task-line mt-1 text-xs text-slate-500" title="<?= htmlspecialchars($assignees, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($assignees, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          <?php endforeach; ?>
          <?php if (!$latestTaskGroups): ?><p class="text-slate-500">No duties assigned yet.</p><?php endif; ?>
        </div>
      </article>
    <?php endif; ?>
  </div>
</section>
<?php
$dashboardAccess = json_encode([
    'bookings' => $canBookings,
    'gallery' => $canGallery,
    'tours' => $canTours,
    'staffs' => $canStaffs,
    'tasks' => $canTasks,
], JSON_UNESCAPED_SLASHES);

admin_footer(str_replace('DASHBOARD_ACCESS_JSON', $dashboardAccess ?: '{}', <<<'JS'
const dashboardAccess = DASHBOARD_ACCESS_JSON;

async function loadDashboard() {
  try {
    const [bookings, gallery, tours] = await Promise.all([
      dashboardAccess.bookings ? request(api('bookings.php?summary=1'), { headers: adminHeaders() }) : Promise.resolve({ count: 0, pending: 0, latest: [] }),
      dashboardAccess.gallery ? request(api('gallery.php?summary=1'), { headers: adminHeaders() }) : Promise.resolve({ count: 0, categories: 0, max: 100 }),
      dashboardAccess.tours ? request(api('tours.php?summary=1'), { headers: adminHeaders() }) : Promise.resolve({ count: 0, withoutImages: 0, totalImages: 0, max: 20 }),
    ]);
    const latestBookings = bookings.latest || [];
    const galleryCount = Number(gallery.count || 0);
    const galleryCategories = Number(gallery.categories || 0);
    const galleryMax = Number(gallery.max || 100);
    const tourCount = Number(tours.count || 0);
    const toursWithoutImages = Number(tours.withoutImages || 0);
    const totalImages = Number(tours.totalImages || 0);
    const toursMax = Number(tours.max || 20);
    if (dashboardAccess.bookings) {
      document.querySelector('#statBookings').textContent = Number(bookings.count || 0);
      document.querySelector('#statBookingsMeta').textContent = `${Number(bookings.pending || 0)} pending or new`;
      document.querySelector('#latestBookings').innerHTML = latestBookings.map((booking) => `<div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2"><div><div class="font-medium text-slate-800">${booking.booking_ref}</div><div class="text-xs text-slate-500">${booking.first_name} ${booking.last_name} | ${booking.tour_name}</div></div><span class="text-xs font-medium text-slate-500">${booking.status}</span></div>`).join('') || '<p class="text-slate-500">No bookings yet.</p>';
    }
    if (dashboardAccess.gallery) {
      document.querySelector('#statGallery').textContent = `${galleryCount}/${galleryMax}`;
    }
    if (dashboardAccess.tours) {
      document.querySelector('#statTours').textContent = `${tourCount}/${toursMax}`;
    }
    const healthItems = [];
    if (dashboardAccess.gallery) {
      healthItems.push(`<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Gallery storage slots</span><strong>${galleryCount}/${galleryMax}</strong></div>`);
      healthItems.push(`<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Gallery categories</span><strong>${galleryCategories}</strong></div>`);
    }
    if (dashboardAccess.tours) {
      healthItems.push(`<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Tour slots</span><strong>${tourCount}/${toursMax}</strong></div>`);
      healthItems.push(`<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Tours without main image</span><strong>${toursWithoutImages}</strong></div>`);
      healthItems.push(`<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Average images per tour</span><strong>${tourCount ? (totalImages / tourCount).toFixed(1) : 0}</strong></div>`);
    }
    if (healthItems.length) {
      document.querySelector('#contentHealth').innerHTML = healthItems.join('');
    }
  } catch (error) {
    failure(error.message || 'Could not load dashboard stats');
  }
}
loadDashboard();
JS));
