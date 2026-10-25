<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin_page();

if (!admin_is_super_admin()) {
    header('Location: ' . admin_first_allowed_url());
    exit;
}

$sites = admin_available_sites();
$selectionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteId = (int) ($_POST['site_id'] ?? 0);
    $site = admin_set_selected_site($siteId);

    if ($site) {
        header('Location: ' . admin_first_allowed_url());
        exit;
    }

    $selectionError = 'Choose a valid site to continue.';
}

$username = admin_username();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Choose a Site | Judylink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
  <main class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <header class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <span class="grid h-12 w-12 place-items-center rounded-xl bg-sky-500 text-xl text-white shadow-lg shadow-sky-500/20"><i class="fa-solid fa-globe"></i></span>
        <div>
          <p class="text-sm font-semibold text-sky-600">Judylink</p>
          <h1 class="text-2xl font-bold tracking-tight">Choose a site to manage</h1>
        </div>
      </div>
      <div class="flex items-center gap-3 text-sm text-slate-500">
        <span>Signed in as <strong class="font-semibold text-slate-700"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></span>
        <a class="rounded-lg bg-white px-3 py-2 font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" href="<?= htmlspecialchars(admin_url(), ENT_QUOTES, 'UTF-8') ?>?logout=1">Logout</a>
      </div>
    </header>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5 sm:p-7">
      <div class="mb-6">
        <h2 class="text-lg font-bold">Available sites</h2>
        <p class="mt-1 text-sm text-slate-500">Select the website you want to work with. You can switch sites later from the admin header.</p>
      </div>

      <?php if ($selectionError): ?>
        <p class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($selectionError, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <?php if ($sites): ?>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <?php foreach ($sites as $site): ?>
            <?php
              $status = str_replace('_', ' ', (string) $site['status']);
              $setupStatus = str_replace('_', ' ', (string) $site['setup_status']);
              $domain = (string) ($site['primary_domain'] ?: $site['site_slug']);
              $websiteType = trim((string) ($site['website_type'] ?? '')) ?: 'Not specified';
            ?>
            <form method="post" class="group flex min-h-56 flex-col rounded-2xl border border-slate-200 bg-slate-50 p-5 transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-white hover:shadow-lg">
              <input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>">
              <div class="mb-5 flex items-start justify-between gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-100 text-sky-600"><i class="fa-solid fa-display"></i></span>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold capitalize text-emerald-700"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <h3 class="text-lg font-bold"><?= htmlspecialchars((string) $site['site_name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="mt-1 break-all text-sm text-slate-500"><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?></p>
              <p class="mt-3 text-sm font-semibold text-slate-700"><?= htmlspecialchars($websiteType, ENT_QUOTES, 'UTF-8') ?></p>
              <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                <span class="rounded-md bg-white px-2 py-1"><?= htmlspecialchars((string) $site['site_ref'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="rounded-md bg-white px-2 py-1 capitalize">Setup: <?= htmlspecialchars($setupStatus, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <button class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-xl bg-sky-500 px-4 py-3 text-sm font-bold text-white transition hover:bg-sky-600" type="submit">
                Work with this site <i class="fa-solid fa-arrow-right"></i>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center">
          <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400"><i class="fa-solid fa-globe"></i></span>
          <h3 class="mt-4 font-bold">No sites are available</h3>
          <p class="mt-1 text-sm text-slate-500">Approve a company registration to create the first site.</p>
          <a class="mt-5 inline-flex rounded-xl bg-sky-500 px-4 py-3 text-sm font-bold text-white" href="<?= htmlspecialchars(admin_url('companies'), ENT_QUOTES, 'UTF-8') ?>">Open company registrations</a>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
