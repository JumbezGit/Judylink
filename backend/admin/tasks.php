<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_ensure_staff_tables();
require_admin_page('tasks');
$siteId = admin_selected_site_id();

function tasks_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function tasks_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function task_feedback_meta(?string $feedback): array
{
    return match ($feedback) {
        'excellent' => ['label' => 'Excellent', 'icon' => 'fa-star', 'class' => 'border-emerald-500 text-emerald-700'],
        'good' => ['label' => 'Good', 'icon' => 'fa-thumbs-up', 'class' => 'border-sky-500 text-sky-700'],
        'fair' => ['label' => 'Fair', 'icon' => 'fa-scale-balanced', 'class' => 'border-amber-500 text-amber-700'],
        'poor' => ['label' => 'Poor', 'icon' => 'fa-triangle-exclamation', 'class' => 'border-red-500 text-red-700'],
        default => ['label' => 'No feedback', 'icon' => 'fa-comment-dots', 'class' => 'border-slate-400 text-slate-600'],
    };
}

function task_entries_from_post(): array
{
    $entries = $_POST['task_entries'] ?? [];
    if (!is_array($entries)) {
        return [];
    }

    $clean = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $taskDate = trim((string) ($entry['task_date'] ?? ''));
        $tourTitle = trim((string) ($entry['tour_title'] ?? ''));
        $taskType = (string) ($entry['task_type'] ?? 'private');
        if (!in_array($taskType, ['private', 'group'], true)) {
            $taskType = 'private';
        }
        $guests = task_entry_guests_from_value($entry['guests'] ?? []);
        $hotel = trim((string) ($entry['hotel'] ?? ''));
        $pax = trim((string) ($entry['pax'] ?? ''));
        $pickupTime = trim((string) ($entry['pickup_time'] ?? ''));
        $languageRole = trim((string) ($entry['language_role'] ?? ''));
        $tourGuideIds = selected_staff_ids_from_value($entry['tour_guide_ids'] ?? []);
        $driverIds = selected_staff_ids_from_value($entry['driver_ids'] ?? []);
        $staffIds = array_values(array_unique(array_merge($tourGuideIds, $driverIds)));
        if ($taskDate === '' || $tourTitle === '' || !$guests || $hotel === '' || $pax === '' || $pickupTime === '' || $languageRole === '' || !$staffIds) {
            continue;
        }
        $clean[] = [
            'task_date' => $taskDate,
            'tour_title' => $tourTitle,
            'task_type' => $taskType,
            'guests' => $guests,
            'guest_name' => implode("\n", array_map(fn ($guest) => $guest['name'], $guests)),
            'hotel' => $hotel,
            'pax' => $pax,
            'pickup_time' => $pickupTime,
            'phone' => implode("\n", array_map(fn ($guest) => $guest['phone'], $guests)),
            'language_role' => $languageRole,
            'tour_guide_ids' => $tourGuideIds,
            'driver_ids' => $driverIds,
            'staff_ids' => $staffIds,
        ];
    }

    return $clean;
}

function task_entry_guests_from_value(mixed $values): array
{
    if (!is_array($values)) {
        return [];
    }

    $guests = [];
    foreach ($values as $guest) {
        if (!is_array($guest)) {
            continue;
        }
        $name = trim((string) ($guest['name'] ?? ''));
        $phone = trim((string) ($guest['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            continue;
        }
        $guests[] = [
            'name' => $name,
            'phone' => $phone,
        ];
    }

    return $guests;
}

function attach_task_guests(array $tasks): array
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

function render_task_guests(array $task): void
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
    <div class="mt-3 grid gap-2">
      <?php foreach ($guests as $index => $guest): ?>
        <div class="rounded-lg border border-slate-200 bg-white p-3">
          <p class="text-xs font-semibold uppercase text-slate-400">Task <?= $index + 1 ?></p>
          <div class="mt-1 grid gap-1 text-sm text-slate-600">
            <span class="break-words">Name: <?= tasks_h($guest['guest_name'] ?? '') ?></span>
            <span class="break-words">Hotel: <?= tasks_h($guest['hotel'] ?? '') ?></span>
            <span class="break-words"><?= tasks_h($guest['pax'] ?? '') ?></span>
            <span class="break-words">Pickup <?= tasks_h($guest['pickup_time'] ?? '') ?></span>
            <span class="break-words"><?= tasks_h($guest['phone'] ?? '') ?></span>
            <span class="break-words"><?= tasks_h($guest['language_role'] ?? '') ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

function render_task_entry(array $task): void
{
    $guestNames = array_values(array_filter(array_map('trim', explode("\n", (string) $task['guest_name']))));
    $guestPhones = array_values(array_map('trim', explode("\n", (string) $task['phone'])));
    $taskType = (string) ($task['task_type'] ?? 'private');
    $taskTypeLabel = $taskType === 'group' ? 'Group' : 'Private';
    ?>
    <div class="mt-2 rounded-lg border border-slate-200 bg-white p-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="break-words whitespace-pre-line text-sm font-semibold text-slate-900"><?= tasks_h($task['tour_details']) ?></p>
        <span class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-semibold text-slate-600"><?= tasks_h($taskTypeLabel) ?></span>
      </div>
      <div class="mt-2 grid gap-1 text-sm text-slate-600">
        <?php foreach ($guestNames ?: [(string) $task['guest_name']] as $index => $guestName): ?>
          <span class="break-words">Name: <?= tasks_h($guestName) ?></span>
          <?php if (($guestPhones[$index] ?? '') !== ''): ?><span class="break-words"><?= tasks_h($guestPhones[$index]) ?></span><?php endif; ?>
        <?php endforeach; ?>
        <span class="break-words">Hotel: <?= tasks_h($task['hotel']) ?></span>
        <span class="break-words"><?= tasks_h($task['pax']) ?></span>
        <span class="break-words">Pickup <?= tasks_h($task['pickup_time']) ?></span>
        <span class="break-words"><?= tasks_h($task['language_role']) ?></span>
      </div>
      <?php if (!empty($task['guests'])): ?>
        <?php render_task_guests($task); ?>
      <?php endif; ?>
    </div>
    <?php
}

function selected_staff_ids_from_post(string $key): array
{
    return selected_staff_ids_from_value($_POST[$key] ?? []);
}

function selected_staff_ids_from_value(mixed $values): array
{
    if (!is_array($values)) {
        $values = [$values];
    }

    $ids = [];
    foreach ($values as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function staff_display_name(array $staff): string
{
    return trim((string) $staff['first_name'] . ' ' . (string) $staff['second_name'] . ' ' . (string) $staff['third_name']);
}

function staff_languages_label(array $staff): string
{
    $languages = json_decode((string) ($staff['languages'] ?? ''), true);
    if (!is_array($languages)) {
        return '';
    }

    $languages = array_values(array_filter(array_map('trim', array_map('strval', $languages))));
    return $languages ? ' (' . implode(', ', $languages) . ')' : '';
}

function group_tasks_by_duty(array $tasks): array
{
    $groups = [];
    foreach ($tasks as $task) {
        $groupKey = (string) ($task['duty_group_id'] ?? '');
        if ($groupKey === '') {
            $groupKey = 'task_' . (string) $task['id'];
        }

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'date' => (string) $task['task_date'],
                'tasks' => [],
            ];
        }

        $taskKey = implode('|', [
            (string) ($task['task_type'] ?? 'private'),
            (string) $task['tour_details'],
            (string) $task['guest_name'],
            (string) $task['hotel'],
            (string) $task['pax'],
            (string) $task['pickup_time'],
            (string) $task['phone'],
            (string) $task['language_role'],
        ]);

        if (!isset($groups[$groupKey]['tasks'][$taskKey])) {
            $groups[$groupKey]['tasks'][$taskKey] = [
                'task' => $task,
                'assignees' => [],
            ];
        }

        $groups[$groupKey]['tasks'][$taskKey]['assignees'][] = [
            'id' => (int) $task['id'],
            'name' => staff_display_name($task),
            'type' => (string) $task['staff_type'],
            'status' => (string) $task['status'],
            'response_at' => (string) ($task['response_at'] ?? ''),
            'completed_at' => (string) ($task['completed_at'] ?? ''),
            'completion_note' => (string) ($task['completion_note'] ?? ''),
            'feedback' => (string) ($task['feedback'] ?? ''),
            'feedback_at' => (string) ($task['feedback_at'] ?? ''),
        ];
    }

    foreach ($groups as &$group) {
        $group['tasks'] = array_values($group['tasks']);
    }
    unset($group);

    return array_values($groups);
}

function render_task_assignees(array $assignees): void
{
    ?>
    <div class="grid content-start gap-2 rounded-lg bg-white p-3 text-xs">
      <p class="font-medium text-slate-800">Assigned Staff</p>
      <?php foreach ($assignees as $assignee): ?>
        <?php $feedbackMeta = task_feedback_meta((string) ($assignee['feedback'] ?? '')); ?>
        <div class="grid gap-1 rounded-md border border-slate-200 p-2">
          <span class="font-medium text-slate-800"><?= tasks_h($assignee['name']) ?></span>
          <span class="capitalize text-slate-500"><?= tasks_h(str_replace('_', ' ', (string) $assignee['type'])) ?></span>
          <span class="w-fit rounded-md border border-sky-500 px-2 py-1 text-sky-700"><?= tasks_h($assignee['status']) ?></span>
          <?php if (!empty($assignee['response_at'])): ?><span class="text-slate-500">Response: <?= tasks_h($assignee['response_at']) ?></span><?php endif; ?>
          <?php if (!empty($assignee['completed_at'])): ?><span class="text-slate-500">Completed: <?= tasks_h($assignee['completed_at']) ?></span><?php endif; ?>
          <?php if (!empty($assignee['completion_note'])): ?>
            <p class="whitespace-pre-line break-words text-slate-500">Completion: <?= tasks_h($assignee['completion_note']) ?></p>
          <?php endif; ?>
          <?php if (!empty($assignee['feedback'])): ?>
            <span class="inline-flex w-fit items-center gap-2 rounded-md border px-2 py-1 font-semibold <?= tasks_h($feedbackMeta['class']) ?>">
              <i class="fa-solid <?= tasks_h($feedbackMeta['icon']) ?>"></i><?= tasks_h($feedbackMeta['label']) ?>
            </span>
          <?php elseif ((string) $assignee['status'] === 'completed'): ?>
            <form class="taskFeedbackForm mt-1 grid grid-cols-2 gap-1" data-current-feedback="">
              <input type="hidden" name="action" value="feedback">
              <input type="hidden" name="task_id" value="<?= (int) $assignee['id'] ?>">
              <?php foreach (['excellent', 'good', 'fair', 'poor'] as $feedback): ?>
                <?php $meta = task_feedback_meta($feedback); ?>
                <button class="secondary inline-flex items-center justify-center gap-1 px-2 py-1 text-[11px]" type="submit" name="feedback" value="<?= tasks_h($feedback) ?>" title="<?= tasks_h($meta['label']) ?>"><i class="fa-solid <?= tasks_h($meta['icon']) ?>"></i><?= tasks_h($meta['label']) ?></button>
              <?php endforeach; ?>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(admin_token(), (string) ($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        tasks_json(['ok' => false, 'error' => 'Admin token required'], 401);
    }

    $action = (string) ($_POST['action'] ?? '');
    $pdo = Database::pdo();

    if ($action === 'create_task') {
        $taskEntries = task_entries_from_post();

        if (!$taskEntries) {
            tasks_json(['ok' => false, 'error' => 'Fill all task details']);
        }

        $staffIds = [];
        foreach ($taskEntries as $entry) {
            foreach ($entry['staff_ids'] as $staffId) {
                $staffIds[$staffId] = $staffId;
            }
        }
        $staffIds = array_values($staffIds);
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $staffStmt = $pdo->prepare("SELECT id, staff_type FROM staffs WHERE site_id = ? AND id IN ({$placeholders}) AND status = 'approved'");
        $staffStmt->execute(array_merge([$siteId], $staffIds));
        $approvedStaff = [];
        foreach ($staffStmt->fetchAll() as $staffRow) {
            $approvedStaff[(int) $staffRow['id']] = (string) $staffRow['staff_type'];
        }

        foreach ($taskEntries as $entry) {
            foreach ($entry['tour_guide_ids'] as $id) {
                if (($approvedStaff[$id] ?? '') !== 'tour_guide') {
                    tasks_json(['ok' => false, 'error' => 'Choose approved tour guides only']);
                }
            }
            foreach ($entry['driver_ids'] as $id) {
                if (($approvedStaff[$id] ?? '') !== 'driver') {
                    tasks_json(['ok' => false, 'error' => 'Choose approved drivers only']);
                }
            }
        }

        $currentUser = admin_current_user();
        $dutyGroupId = bin2hex(random_bytes(16));
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO staff_tasks (site_id, duty_group_id, task_type, staff_id, task_date, tour_details, guest_name, hotel, pax, pickup_time, phone, language_role, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($taskEntries as $entry) {
                foreach ($entry['staff_ids'] as $staffId) {
                    $stmt->execute([
                        $siteId,
                        $dutyGroupId,
                        $entry['task_type'],
                        $staffId,
                        $entry['task_date'],
                        $entry['tour_title'],
                        $entry['guest_name'],
                        $entry['hotel'],
                        $entry['pax'],
                        $entry['pickup_time'],
                        $entry['phone'],
                        $entry['language_role'],
                        $currentUser['id'] ?? null,
                    ]);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            tasks_json(['ok' => false, 'error' => 'Could not assign task'], 500);
        }

        tasks_json(['ok' => true]);
    }

    if ($action === 'feedback') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $feedback = (string) ($_POST['feedback'] ?? '');
        if ($taskId < 1 || !in_array($feedback, ['excellent', 'good', 'fair', 'poor'], true)) {
            tasks_json(['ok' => false, 'error' => 'Choose valid feedback']);
        }
        $stmt = $pdo->prepare('UPDATE staff_tasks SET feedback = ?, feedback_at = NOW() WHERE id = ? AND site_id = ? AND status = "completed" AND (feedback IS NULL OR feedback = "")');
        $stmt->execute([$feedback, $taskId, $siteId]);
        if ($stmt->rowCount() < 1) {
            tasks_json(['ok' => false, 'error' => 'Feedback can be sent once after completion only']);
        }
        tasks_json(['ok' => true]);
    }

    tasks_json(['ok' => false, 'error' => 'Unknown task action']);
}

$staffsStmt = Database::pdo()->prepare(
    'SELECT id, staff_type, first_name, second_name, third_name, phone, languages
     FROM staffs
     WHERE site_id = ? AND status = "approved"
     ORDER BY staff_type, first_name, second_name, third_name'
);
$staffsStmt->execute([$siteId]);
$staffs = $staffsStmt->fetchAll();
$tourGuides = array_values(array_filter($staffs, fn ($staff) => (string) $staff['staff_type'] === 'tour_guide'));
$drivers = array_values(array_filter($staffs, fn ($staff) => (string) $staff['staff_type'] === 'driver'));
$tourOptions = [];
try {
    $tourOptionsStmt = Database::pdo()->prepare(
        'SELECT name FROM tours WHERE site_id = ? AND is_active = 1 ORDER BY display_order, name'
    );
    $tourOptionsStmt->execute([$siteId]);
    $tourOptions = $tourOptionsStmt->fetchAll();
} catch (Throwable $exception) {
    $tourOptions = [];
}

$tasksStmt = Database::pdo()->prepare(
    'SELECT staff_tasks.*, staffs.staff_type, staffs.first_name, staffs.second_name, staffs.third_name
     FROM staff_tasks
     INNER JOIN staffs ON staffs.id = staff_tasks.staff_id AND staffs.site_id = staff_tasks.site_id
     WHERE staff_tasks.site_id = ?
     ORDER BY staff_tasks.task_date DESC, staff_tasks.created_at DESC
     LIMIT 80'
);
$tasksStmt->execute([$siteId]);
$tasks = $tasksStmt->fetchAll();
$tasks = attach_task_guests($tasks);
$taskGroups = group_tasks_by_duty($tasks);
$taskView = (string) ($_GET['task_view'] ?? 'assign');
if (!in_array($taskView, ['assign', 'all'], true)) {
    $taskView = 'assign';
}
$taskBaseUrl = admin_url('tasks');

admin_header('Task', 'tasks');
?>
<style>
  .task-create-form .secondary,
  .task-create-form .primary,
  .task-create-form .danger { min-height: 2rem; padding: 0.375rem 0.625rem; font-size: 0.75rem; }
  .task-create-form .task-add-button { min-height: 2.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
  .task-create-form .selected-staff-list span { max-width: 100%; padding: 0.25rem 0.5rem; font-size: 0.75rem; }
  .task-create-form .tour-title-menu button { padding: 0.375rem 0.625rem; font-size: 0.8125rem; }
  .task-create-form .tour-title-input,
  .task-create-form select.form-control { background: #fff; color: #172033; }
  .task-create-form select.form-control option,
  .task-create-form .tour-title-menu,
  .task-create-form .tour-title-menu button { background: #fff; color: #172033; }
  .task-create-form .tour-title-menu button:hover { background: #f1f5f9; }
  body.admin-dark .task-create-form .tour-title-input,
  .admin-dark-ready body .task-create-form .tour-title-input,
  body.admin-dark .task-create-form select.form-control,
  .admin-dark-ready body .task-create-form select.form-control { background: #0f172a; color: #e2e8f0; }
  body.admin-dark .task-create-form select.form-control option,
  .admin-dark-ready body .task-create-form select.form-control option,
  body.admin-dark .task-create-form .tour-title-menu,
  .admin-dark-ready body .task-create-form .tour-title-menu,
  body.admin-dark .task-create-form .tour-title-menu button,
  .admin-dark-ready body .task-create-form .tour-title-menu button { background: #1e293b; color: #e2e8f0; }
  body.admin-dark .task-create-form .tour-title-menu button:hover,
  .admin-dark-ready body .task-create-form .tour-title-menu button:hover { background: #334155; }
</style>
<section class="grid gap-4">
  <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div>
      <h2 class="text-xl font-medium">Task</h2>
      <p class="mt-1 text-sm text-slate-500">Assign duties to approved tour guides and drivers.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a class="secondary inline-flex items-center justify-center gap-2 <?= $taskView === 'assign' ? 'bg-sky-50 text-sky-700 ring-2 ring-sky-200' : '' ?>" href="<?= tasks_h($taskBaseUrl) ?>?task_view=assign"><i class="fa-solid fa-plus"></i>Assign Task</a>
      <a class="secondary inline-flex items-center justify-center gap-2 <?= $taskView === 'all' ? 'bg-sky-50 text-sky-700 ring-2 ring-sky-200' : '' ?>" href="<?= tasks_h($taskBaseUrl) ?>?task_view=all"><i class="fa-solid fa-list"></i>View All Tasks</a>
    </div>
  </div>

  <?php if ($taskView === 'assign'): ?>
  <article class="admin-card p-4">
    <h3 class="mb-3 text-base font-medium">Create Duty</h3>
    <form id="taskForm" class="task-create-form grid gap-3 md:grid-cols-2">
      <input type="hidden" name="action" value="create_task">
      <script type="application/json" id="tourOptionsJson"><?= json_encode(array_values(array_map(fn ($tour) => (string) $tour['name'], $tourOptions)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
      <div class="grid gap-2 md:col-span-2">
        <div class="flex items-center justify-between gap-2">
          <h4 class="text-sm font-semibold text-slate-800">Tasks</h4>
          <button class="task-add-button secondary inline-flex items-center justify-center gap-2" type="button" onclick="addTaskEntry()"><i class="fa-solid fa-plus"></i>Add New</button>
        </div>
        <div id="taskEntries" class="grid gap-2">
          <div class="task-entry grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2.5 md:grid-cols-4">
            <p class="task-entry-title text-xs font-semibold uppercase text-slate-400 md:col-span-4">Task 1</p>
            <div class="grid gap-2 md:col-span-4 md:grid-cols-[minmax(0,1fr)_150px_140px]">
              <label class="relative grid gap-1 text-xs font-medium text-slate-700">Tour Title
                <input class="tour-title-input form-control" name="task_entries[0][tour_title]" placeholder="Stone town, Prison and Nakupenda" autocomplete="off" required>
                <div class="tour-title-menu absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg"></div>
              </label>
              <label class="grid gap-1 text-xs font-medium text-slate-700">Date
                <input class="form-control" name="task_entries[0][task_date]" type="date" required>
              </label>
              <label class="grid gap-1 text-xs font-medium text-slate-700">Type
                <select class="task-type-select form-control" name="task_entries[0][task_type]">
                  <option value="private">Private</option>
                  <option value="group">Group</option>
                </select>
              </label>
            </div>
            <div class="grid gap-1 text-xs font-medium text-slate-700 md:col-span-2">
              <span>Tour Guide</span>
              <select class="staff-choice-select form-control" data-staff-field="tour_guide_ids">
                <option value="">Choose tour guide</option>
                <?php foreach ($tourGuides as $staff): ?>
                  <?php $name = staff_display_name($staff); ?>
                  <option value="<?= (int) $staff['id'] ?>"><?= tasks_h($name . staff_languages_label($staff)) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="selected-staff-list flex min-h-8 flex-wrap gap-1.5 rounded-md border border-slate-200 bg-white p-1.5" data-selected-field="tour_guide_ids"></div>
            </div>
            <div class="grid gap-1 text-xs font-medium text-slate-700 md:col-span-2">
              <span>Driver</span>
              <select class="staff-choice-select form-control" data-staff-field="driver_ids">
                <option value="">Choose driver</option>
                <?php foreach ($drivers as $staff): ?>
                  <?php $name = staff_display_name($staff); ?>
                  <option value="<?= (int) $staff['id'] ?>"><?= tasks_h($name) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="selected-staff-list flex min-h-8 flex-wrap gap-1.5 rounded-md border border-slate-200 bg-white p-1.5" data-selected-field="driver_ids"></div>
            </div>
            <div class="task-guests grid gap-1.5 md:col-span-4">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium text-slate-700">Guest</span>
                <button class="add-task-guest secondary hidden items-center justify-center gap-2 px-3 py-2 text-xs" type="button" onclick="addTaskGuest(this)"><i class="fa-solid fa-plus"></i>Add Name</button>
              </div>
              <div class="task-guest-list grid gap-1.5">
                <div class="task-guest-row grid gap-2 rounded-md border border-slate-200 bg-white p-1.5 md:grid-cols-2">
                  <label class="grid gap-1 text-xs font-medium text-slate-700">Name
                    <input class="task-guest-name form-control" name="task_entries[0][guests][0][name]" placeholder="Dianne Hope" required>
                  </label>
                  <label class="task-guest-phone-label grid gap-1 text-xs font-medium text-slate-700">Phone
                    <input class="form-control" name="task_entries[0][guests][0][phone]" placeholder="+61438021061" required>
                  </label>
                </div>
              </div>
            </div>
            <label class="grid gap-1 text-xs font-medium text-slate-700">
              <span>Hotel</span>
              <input class="form-control" name="task_entries[0][hotel]" placeholder="Diamond Mapenzi" required>
            </label>
            <label class="grid gap-1 text-xs font-medium text-slate-700">
              <span>Pax</span>
              <input class="form-control" name="task_entries[0][pax]" placeholder="6 pax" required>
            </label>
            <label class="grid gap-1 text-xs font-medium text-slate-700">
              <span>Pickup</span>
              <input class="form-control" name="task_entries[0][pickup_time]" placeholder="08h20" required>
            </label>
            <label class="grid gap-1 text-xs font-medium text-slate-700">
              <span>Language / Role</span>
              <input class="form-control" name="task_entries[0][language_role]" placeholder="English guide" required>
            </label>
          </div>
        </div>
      </div>
      <div class="flex justify-end md:col-span-2">
        <button class="primary inline-flex items-center justify-center gap-2" type="submit"><i class="fa-solid fa-list-check"></i>Assign Task</button>
      </div>
    </form>
  </article>
  <?php endif; ?>

  <?php if ($taskView === 'all'): ?>
  <article class="admin-card">
    <h3 class="mb-4 text-base font-medium">Assigned Duties</h3>
    <div id="taskList" class="grid gap-3">
      <?php foreach ($taskGroups as $taskGroup): ?>
        <article class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
          <p class="text-sm font-semibold text-slate-900">Duty Date: <?= tasks_h(date('d/m/Y', strtotime((string) $taskGroup['date']))) ?></p>
          <?php foreach ($taskGroup['tasks'] as $index => $taskEntry): ?>
            <?php $task = $taskEntry['task']; ?>
            <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-100 p-3 md:grid-cols-[minmax(0,1fr)_220px]">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-400">Task <?= $index + 1 ?></p>
                <?php render_task_entry($task); ?>
              </div>
              <?php render_task_assignees($taskEntry['assignees']); ?>
            </div>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
      <?php if (!$taskGroups): ?><p class="text-sm text-slate-500">No duties assigned yet.</p><?php endif; ?>
    </div>
  </article>
  <?php endif; ?>
</section>
<?php
admin_footer(<<<'JS'
window.hyenaTourOptions = (() => {
  try {
    return JSON.parse(document.querySelector('#tourOptionsJson')?.textContent || '[]');
  } catch (error) {
    return [];
  }
})();

window.taskEntryIndex = function taskEntryIndex(entry) {
  return Array.from(document.querySelectorAll('.task-entry')).indexOf(entry);
}

window.renderTourTitleMenu = function renderTourTitleMenu(input) {
  const menu = input.parentElement?.querySelector('.tour-title-menu');
  if (!menu) return;
  const search = input.value.trim().toLowerCase();
  const matches = window.hyenaTourOptions
    .filter((title) => !search || title.toLowerCase().includes(search))
    .slice(0, 8);
  menu.innerHTML = '';
  if (!matches.length) {
    menu.classList.add('hidden');
    return;
  }
  matches.forEach((title) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'block w-full rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100';
    button.textContent = title;
    button.addEventListener('mousedown', (event) => {
      event.preventDefault();
      input.value = title;
      menu.classList.add('hidden');
    });
    menu.appendChild(button);
  });
  menu.classList.remove('hidden');
}

window.addSelectedStaff = function addSelectedStaff(select) {
  const entry = select.closest('.task-entry');
  const field = select.dataset.staffField || '';
  const value = select.value;
  const label = select.options[select.selectedIndex]?.textContent || '';
  if (!entry || !field || !value) return;
  const list = entry.querySelector(`[data-selected-field="${field}"]`);
  const exists = list && Array.from(list.querySelectorAll('input[type="hidden"]')).some((input) => input.value === value);
  if (!list || exists) {
    select.value = '';
    return;
  }
  const index = window.taskEntryIndex(entry);
  const chip = document.createElement('span');
  chip.className = 'inline-flex max-w-full items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700';
  chip.innerHTML = `<span class="min-w-0 truncate"></span><button class="grid h-5 w-5 place-items-center rounded bg-white text-slate-500 hover:text-red-600" type="button" title="Remove"><i class="fa-solid fa-xmark"></i></button><input type="hidden" name="task_entries[${index}][${field}][]" value="">`;
  chip.querySelector('span').textContent = label;
  chip.querySelector('input').value = value;
  chip.querySelector('button').addEventListener('click', () => chip.remove());
  list.appendChild(chip);
  select.value = '';
}

window.reindexTaskGuests = function reindexTaskGuests(entry) {
  const entryIndex = window.taskEntryIndex(entry);
  entry.querySelectorAll('.task-guest-row').forEach((row, guestIndex) => {
    row.querySelectorAll('[name]').forEach((field) => {
      field.name = field.name
        .replace(/task_entries\[\d+\]/, `task_entries[${entryIndex}]`)
        .replace(/guests\[\d+\]/, `guests[${guestIndex}]`);
    });
    const removeButton = row.querySelector('.remove-task-guest');
    if (removeButton) {
      removeButton.classList.toggle('hidden', guestIndex === 0);
    }
  });
}

window.updateGuestPhoneLabel = function updateGuestPhoneLabel(input) {
  const row = input.closest('.task-guest-row');
  const label = row?.querySelector('.task-guest-phone-label');
  if (!label) return;
  const name = input.value.trim();
  label.childNodes[0].textContent = name ? `Phone for ${name}` : 'Phone';
}

window.addTaskGuest = function addTaskGuest(button) {
  const entry = button.closest('.task-entry');
  const list = entry?.querySelector('.task-guest-list');
  const firstRow = entry?.querySelector('.task-guest-row');
  if (!entry || !list || !firstRow) return;
  const row = firstRow.cloneNode(true);
  row.querySelectorAll('input').forEach((input) => { input.value = ''; });
  if (!row.querySelector('.remove-task-guest')) {
    row.insertAdjacentHTML('beforeend', '<button class="remove-task-guest secondary inline-flex w-fit items-center justify-center gap-2 px-2 py-1 text-xs md:col-span-2" type="button" onclick="removeTaskGuest(this)"><i class="fa-solid fa-minus"></i>Remove Name</button>');
  }
  delete row.querySelector('.task-guest-name')?.dataset.guestNameReady;
  list.appendChild(row);
  window.reindexTaskGuests(entry);
  window.initTaskEntryControls(row);
}

window.removeTaskGuest = function removeTaskGuest(button) {
  const entry = button.closest('.task-entry');
  const row = button.closest('.task-guest-row');
  if (!entry || !row || entry.querySelectorAll('.task-guest-row').length <= 1) return;
  row.remove();
  window.reindexTaskGuests(entry);
}

window.syncTaskType = function syncTaskType(select) {
  const entry = select.closest('.task-entry');
  if (!entry) return;
  const isGroup = select.value === 'group';
  entry.querySelector('.add-task-guest')?.classList.toggle('hidden', !isGroup);
  entry.querySelector('.add-task-guest')?.classList.toggle('inline-flex', isGroup);
  if (!isGroup) {
    entry.querySelectorAll('.task-guest-row').forEach((row, index) => {
      if (index > 0) row.remove();
    });
  }
  window.reindexTaskGuests(entry);
}

window.initTaskEntryControls = function initTaskEntryControls(scope = document) {
  scope.querySelectorAll('.tour-title-input').forEach((input) => {
    if (input.dataset.taskTourReady === '1') return;
    input.dataset.taskTourReady = '1';
    input.addEventListener('focus', () => window.renderTourTitleMenu(input));
    input.addEventListener('input', () => window.renderTourTitleMenu(input));
    input.addEventListener('blur', () => {
      window.setTimeout(() => input.parentElement?.querySelector('.tour-title-menu')?.classList.add('hidden'), 150);
    });
  });

  scope.querySelectorAll('.staff-choice-select').forEach((select) => {
    if (select.dataset.staffSelectReady === '1') return;
    select.dataset.staffSelectReady = '1';
    select.addEventListener('change', () => window.addSelectedStaff(select));
  });

  scope.querySelectorAll('.task-type-select').forEach((select) => {
    if (select.dataset.taskTypeReady === '1') return;
    select.dataset.taskTypeReady = '1';
    select.addEventListener('change', () => window.syncTaskType(select));
    window.syncTaskType(select);
  });

  scope.querySelectorAll('.task-guest-name').forEach((input) => {
    if (input.dataset.guestNameReady === '1') return;
    input.dataset.guestNameReady = '1';
    input.addEventListener('input', () => window.updateGuestPhoneLabel(input));
    window.updateGuestPhoneLabel(input);
  });
}

window.reindexTaskEntries = function reindexTaskEntries() {
  document.querySelectorAll('.task-entry').forEach((entry, index) => {
    const title = entry.querySelector('.task-entry-title');
    if (title) title.textContent = `Task ${index + 1}`;
    entry.querySelectorAll('[name]').forEach((field) => {
      field.name = field.name.replace(/task_entries\[\d+\]/, `task_entries[${index}]`);
    });
    window.reindexTaskGuests(entry);
  });
}

window.addTaskEntry = function addTaskEntry() {
  const taskEntries = document.querySelector('#taskEntries');
  const firstEntry = document.querySelector('.task-entry');
  if (!taskEntries || !firstEntry) return;
  const clone = firstEntry.cloneNode(true);
  clone.querySelectorAll('[data-task-tour-ready], [data-staff-select-ready], [data-task-type-ready], [data-guest-name-ready]').forEach((element) => {
    delete element.dataset.taskTourReady;
    delete element.dataset.staffSelectReady;
    delete element.dataset.taskTypeReady;
    delete element.dataset.guestNameReady;
  });
  clone.querySelectorAll('input').forEach((input) => { input.value = ''; });
  clone.querySelectorAll('select').forEach((select) => {
    select.selectedIndex = 0;
  });
  clone.querySelectorAll('.task-guest-row').forEach((row, index) => {
    if (index > 0) row.remove();
  });
  clone.querySelectorAll('.remove-task-guest').forEach((button) => button.remove());
  clone.querySelectorAll('.tour-title-menu').forEach((menu) => {
    menu.innerHTML = '';
    menu.classList.add('hidden');
  });
  clone.querySelectorAll('.selected-staff-list').forEach((list) => { list.innerHTML = ''; });
  if (!clone.querySelector('.remove-task-entry')) {
    const header = clone.querySelector('.task-entry-title');
    header?.insertAdjacentHTML('afterend', '<button class="remove-task-entry secondary inline-flex w-fit items-center justify-center gap-2 px-2 py-1 text-xs md:justify-self-end" type="button" onclick="removeTaskEntry(this)"><i class="fa-solid fa-minus"></i>Remove</button>');
  }
  taskEntries.appendChild(clone);
  window.reindexTaskEntries();
  window.initTaskEntryControls(clone);
}

window.removeTaskEntry = function removeTaskEntry(button) {
  const entry = button.closest('.task-entry');
  if (!entry || document.querySelectorAll('.task-entry').length <= 1) return;
  entry.remove();
  window.reindexTaskEntries();
}

window.initTaskEntryControls();

document.querySelector('#taskForm')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    await request(adminPage('tasks/'), {
      method: 'POST',
      headers: adminHeaders(),
      body: new FormData(event.currentTarget),
    });
    success('Task assigned');
    window.location.href = `${window.location.pathname}?task_view=all`;
  } catch (error) {
    failure(error.message || 'Could not assign task');
  }
});

document.querySelectorAll('.taskFeedbackForm').forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitter = event.submitter;
    const body = new FormData(form);
    if (submitter?.name) body.set(submitter.name, submitter.value);
    try {
      await request(adminPage('tasks/'), {
        method: 'POST',
        headers: adminHeaders(),
        body,
      });
      success('Feedback sent to staff');
      window.location.reload();
    } catch (error) {
      failure(error.message || 'Could not save feedback');
    }
  });
});
JS);
