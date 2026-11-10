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
$theme = admin_theme();
$token = admin_token();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Choose a Site | Judylink</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script>
    if (<?= json_encode($theme) ?> === 'dark') document.documentElement.classList.add('site-dark-ready');
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .site-dark-ready { color-scheme: dark; }
    .site-dark-ready body { background: #0f172a !important; color: #e2e8f0 !important; }
    .site-dark-ready .site-header,
    .site-dark-ready .site-card { border-color: #334155 !important; background: #1e293b !important; }
    .site-dark-ready .site-icon,
    .site-dark-ready .site-meta,
    .site-dark-ready .header-action { background: #334155 !important; color: #cbd5e1 !important; }
    .site-dark-ready .site-logo { border-color: #e2e8f0 !important; background: #fff !important; }
    .site-dark-ready .text-slate-900,
    .site-dark-ready .text-slate-800,
    .site-dark-ready .text-slate-700 { color: #e2e8f0 !important; }
    .site-dark-ready .text-slate-500 { color: #94a3b8 !important; }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
  <header class="site-header sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex w-full max-w-screen-2xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
      <div class="flex min-w-0 items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-500 text-lg text-white shadow-lg shadow-sky-500/20"><i class="fa-solid fa-globe"></i></span>
        <div>
          <p class="text-sm font-semibold text-sky-600">Judylink</p>
          <h1 class="truncate text-base font-bold tracking-tight sm:text-lg">Choose a site to manage</h1>
        </div>
      </div>
      <div class="flex shrink-0 items-center gap-2 text-sm text-slate-500">
        <span class="hidden sm:inline">Signed in as <strong class="font-semibold text-slate-700"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong></span>
        <button id="siteThemeButton" class="header-action grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-slate-700 transition hover:bg-slate-200" type="button" onclick="toggleSiteTheme()" aria-label="Change color mode" title="Change color mode"><i class="fa-solid <?= $theme === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i></button>
        <a class="header-action inline-flex h-9 items-center gap-2 rounded-lg bg-slate-100 px-3 font-semibold text-slate-700 no-underline transition hover:bg-slate-200" href="<?= htmlspecialchars(admin_url(), ENT_QUOTES, 'UTF-8') ?>?logout=1"><i class="fa-solid fa-right-from-bracket"></i><span class="hidden sm:inline">Logout</span></a>
      </div>
    </div>
  </header>

  <main class="mx-auto w-full max-w-screen-2xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
      <div class="mb-5">
        <h2 class="text-lg font-bold">Available sites</h2>
        <p class="mt-1 text-sm text-slate-500">Select the website you want to work with. You can switch sites later from the admin header.</p>
      </div>

      <?php if ($selectionError): ?>
        <p class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($selectionError, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <?php if ($sites): ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <?php foreach ($sites as $site): ?>
            <?php
              $status = str_replace('_', ' ', (string) $site['status']);
              $setupStatus = str_replace('_', ' ', (string) $site['setup_status']);
              $domain = (string) ($site['primary_domain'] ?: $site['site_slug']);
              $websiteType = trim((string) ($site['website_type'] ?? '')) ?: 'Not specified';
              $brandLogo = trim((string) ($site['brand_logo'] ?? ''));
            ?>
            <form method="post" class="site-card flex min-h-56 flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>">
              <div class="mb-5 flex items-start justify-between gap-3">
                <?php if ($brandLogo !== ''): ?>
                  <span class="site-icon site-logo grid h-11 w-11 place-items-center overflow-hidden rounded-full border border-slate-200 bg-white p-1"><img class="max-h-full max-w-full object-contain" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $site['site_name'], ENT_QUOTES, 'UTF-8') ?> logo"></span>
                <?php else: ?>
                  <span class="site-icon grid h-11 w-11 place-items-center rounded-xl bg-sky-100 text-sky-600"><i class="fa-solid fa-display"></i></span>
                <?php endif; ?>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold capitalize text-emerald-700"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <h3 class="text-lg font-bold"><?= htmlspecialchars((string) $site['site_name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="mt-1 break-all text-sm text-slate-500"><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?></p>
              <p class="mt-3 text-sm font-semibold text-slate-700"><?= htmlspecialchars($websiteType, ENT_QUOTES, 'UTF-8') ?></p>
              <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                <span class="site-meta rounded-md bg-slate-100 px-2 py-1"><?= htmlspecialchars((string) $site['site_ref'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="site-meta rounded-md bg-slate-100 px-2 py-1 capitalize">Setup: <?= htmlspecialchars($setupStatus, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="mt-auto pt-6">
                <button class="inline-flex w-full items-center justify-center rounded-lg bg-sky-500 px-4 py-3 text-sm font-bold text-white hover:bg-sky-600" type="submit">Choose</button>
              </div>
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
  </main>
  <script>
    const siteThemeToken = <?= json_encode($token) ?>;
    const siteSettingsUrl = <?= json_encode(admin_url('settings/')) ?>;

    async function toggleSiteTheme() {
      const root = document.documentElement;
      const buttonIcon = document.querySelector('#siteThemeButton i');
      const dark = !root.classList.contains('site-dark-ready');
      const theme = dark ? 'dark' : 'light';
      root.classList.toggle('site-dark-ready', dark);
      buttonIcon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
      localStorage.setItem('hyenaAdminTheme', theme);

      const body = new FormData();
      body.set('action', 'theme');
      body.set('theme', theme);
      try {
        await fetch(siteSettingsUrl, {
          method: 'POST',
          headers: { 'X-Admin-Token': siteThemeToken },
          body,
        });
      } catch (error) {
        console.error('Could not save color mode', error);
      }
    }
  </script>
</body>
</html>
