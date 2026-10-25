<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function admin_header(string $title, string $active): void
{
    require_admin_page($active);
    $token = admin_token();
    $theme = admin_theme();
    $username = admin_username();
    $brandName = admin_setting('brand_name', 'Judylink');
    $brandSubtitle = admin_setting('brand_subtitle', 'Tours & travel control');
    $logoUrl = admin_setting('brand_logo', '');
    $items = array_filter(
        admin_navigation_items(true),
        fn ($item, $key) => !empty($item['is_visible']) && admin_can_view((string) ($item['permission'] ?? $key)),
        ARRAY_FILTER_USE_BOTH
    );
    $activeNavigationKey = $active === 'business-data'
        ? 'feature:' . trim((string) ($_GET['module'] ?? ''))
        : $active;
    $isSuperAdmin = admin_is_super_admin();
    $selectedSiteLabel = admin_selected_site_label();
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    const serverAdminTheme = <?= json_encode($theme) ?>;
    const storedAdminTheme = localStorage.getItem('hyenaAdminTheme');
    localStorage.removeItem('hyenaAdminFullscreen');
    if ((serverAdminTheme || storedAdminTheme) === 'dark') {
      document.documentElement.classList.add('admin-dark-ready');
    }
    if (localStorage.getItem('hyenaSidebarCollapsed') === '1') {
      document.documentElement.classList.add('sidebar-collapsed-ready');
    }
  </script>
  <style type="text/tailwindcss">
    @layer components {
      .form-control { @apply w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-normal outline-none transition focus:border-[#2FABE1] focus:ring-4 focus:ring-sky-100; }
      .secondary { @apply inline-flex items-center justify-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200; }
      .danger { @apply inline-flex items-center justify-center gap-2 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100; }
      .primary { @apply inline-flex items-center justify-center gap-2 rounded-md bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100; }
      .thumb { @apply h-14 w-20 rounded-lg object-cover; }
      .muted { @apply text-sm text-slate-500; }
      .admin-card { @apply rounded-lg border border-slate-200 bg-white p-5 shadow-xl shadow-slate-900/5; }
      .admin-table th { @apply border-b border-slate-200 bg-slate-50 px-3 py-3 text-xs font-medium uppercase tracking-wide text-slate-500; }
      .admin-table td { @apply border-b border-slate-100 px-3 py-3 align-top text-sm text-slate-700; }
      .gallery-card { @apply min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg shadow-slate-900/5; }
      .gallery-card img { @apply aspect-[4/3] h-auto w-full bg-slate-100 object-cover object-center; }
      .gallery-card-body { @apply grid min-w-0 gap-2 overflow-hidden p-2; }
      .gallery-card-title { @apply block min-w-0 truncate whitespace-nowrap text-sm font-medium leading-snug text-slate-900; }
      .gallery-card-meta { @apply block min-w-0 truncate whitespace-nowrap text-xs text-slate-500; }
      .gallery-actions { @apply flex items-center justify-between gap-2; }
      .gallery-actions button { @apply grid h-8 w-8 place-items-center px-0 py-0; }
      .tour-image-badge { @apply absolute rounded-md px-2.5 py-1 text-xs font-medium shadow; }
      .tour-category-badge { @apply left-3 top-3 bg-white/95 text-[#172033]; }
      .tour-popular-badge { @apply right-3 top-3 bg-[#FAA723] text-[#172033]; }
      .side-link.active { @apply bg-sky-500/15 text-white; }
      .side-link.active .side-icon { @apply bg-[#2FABE1] text-white; }
    }
  </style>
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .sidebar-collapsed-ready body .admin-shell { grid-template-columns: 82px minmax(0, 1fr); }
    .sidebar-collapsed-ready body .sidebar-label,
    .sidebar-collapsed-ready body .sidebar-subtitle,
    .sidebar-collapsed-ready body .sidebar-footer { display: none; }
    .sidebar-collapsed-ready body .sidebar { padding-left: 0.75rem; padding-right: 0.75rem; }
    .sidebar-collapsed-ready body .side-link { width: 3.625rem; justify-content: center; padding-left: 0; padding-right: 0; }
    .sidebar-collapsed-ready body .side-icon { margin-left: auto; margin-right: auto; }
    .sidebar-collapsed-ready body .brand-wrap { justify-content: center; }
    .sidebar-collapsed-ready body .brand-wrap > button { display: none; }
    .modal-backdrop { display: none; }
    .modal-backdrop.active { display: flex; }
    .admin-shell { grid-template-columns: 244px minmax(0, 1fr); }
    .sidebar { transition: transform .08s ease-out; will-change: transform; }
    .sidebar-nav { min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, .45) transparent; }
    .sidebar-nav::-webkit-scrollbar { width: 5px; }
    .sidebar-nav::-webkit-scrollbar-thumb { border-radius: 999px; background: rgba(148, 163, 184, .45); }
    .content-panel { transition: none; }
    .dashboard-stats-grid,
    .dashboard-widgets-grid { display: grid; gap: 1rem; }
    .dashboard-stats-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 13.5rem), 1fr)); }
    .dashboard-widgets-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr)); margin-top: 1rem; }
    .dashboard-widgets-grid > * { max-width: 100%; min-width: 0; overflow: hidden; }
    .dashboard-task-card,
    .dashboard-task-card * { min-width: 0; }
    .dashboard-task-line { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dashboard-stat-card { display: flex; min-height: 7rem; min-width: 0; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 1rem; }
    .dashboard-stat-card p { overflow-wrap: anywhere; }
    #sidebarToggleButton { display: grid; pointer-events: none; visibility: hidden; }
    body.sidebar-collapsed #sidebarToggleButton,
    .sidebar-collapsed-ready body #sidebarToggleButton { pointer-events: auto; visibility: visible; }
    body.sidebar-collapsed .admin-shell { grid-template-columns: 82px minmax(0, 1fr); }
    body.sidebar-collapsed .sidebar-label,
    body.sidebar-collapsed .sidebar-subtitle,
    body.sidebar-collapsed .sidebar-footer { display: none; }
    body.sidebar-collapsed .sidebar { padding-left: 0.75rem; padding-right: 0.75rem; }
    body.sidebar-collapsed .side-link { width: 3.625rem; justify-content: center; padding-left: 0; padding-right: 0; }
    body.sidebar-collapsed .side-icon { margin-left: auto; margin-right: auto; }
    body.sidebar-collapsed .brand-wrap { justify-content: center; }
    body.sidebar-collapsed .brand-wrap > button { display: none; }
    body.admin-dark,
    .admin-dark-ready body { background: #0f172a; color: #e2e8f0; }
    body.admin-dark .content-panel,
    .admin-dark-ready body .content-panel { background: #0f172a; }
    body.admin-dark header,
    .admin-dark-ready body header { border-color: #1e293b; background: rgba(15, 23, 42, 0.92); }
    body.admin-dark main,
    .admin-dark-ready body main { background: #0f172a; }
    body.admin-dark .admin-card,
    .admin-dark-ready body .admin-card,
    body.admin-dark #tourViewModal > div,
    .admin-dark-ready body #tourViewModal > div,
    body.admin-dark #tourFormModal > div,
    .admin-dark-ready body #tourFormModal > div,
    body.admin-dark #bookingModal > div,
    .admin-dark-ready body #bookingModal > div,
    body.admin-dark #galleryModal > div,
    .admin-dark-ready body #galleryModal > div { border-color: #334155; background: #1e293b; color: #e2e8f0; box-shadow: none; }
    body.admin-dark .form-control,
    .admin-dark-ready body .form-control { border-color: #475569; background: #0f172a; color: #e2e8f0; }
    body.admin-dark .form-control:focus,
    .admin-dark-ready body .form-control:focus { border-color: #2FABE1; box-shadow: 0 0 0 4px rgba(47, 171, 225, 0.16); }
    body.admin-dark .secondary,
    .admin-dark-ready body .secondary { background: #334155; color: #e2e8f0; }
    body.admin-dark .secondary:hover,
    .admin-dark-ready body .secondary:hover { background: #475569; }
    body.admin-dark .primary,
    .admin-dark-ready body .primary { background: #0c4a6e; color: #e0f2fe; }
    body.admin-dark .primary:hover,
    .admin-dark-ready body .primary:hover { background: #075985; }
    body.admin-dark .danger,
    .admin-dark-ready body .danger { background: #7f1d1d; color: #fee2e2; }
    body.admin-dark .danger:hover,
    .admin-dark-ready body .danger:hover { background: #991b1b; }
    body.admin-dark .peer:not(:checked) + span,
    .admin-dark-ready body .peer:not(:checked) + span { background: #475569; }
    body.admin-dark .admin-table th,
    .admin-dark-ready body .admin-table th { border-color: #334155; background: #0f172a; color: #94a3b8; }
    body.admin-dark .admin-table td,
    .admin-dark-ready body .admin-table td { border-color: #334155; color: #cbd5e1; }
    body.admin-dark .gallery-card,
    .admin-dark-ready body .gallery-card { border-color: #334155; background: #1e293b; }
    body.admin-dark .gallery-card-title,
    .admin-dark-ready body .gallery-card-title { color: #e2e8f0 !important; }
    body.admin-dark .gallery-card-meta,
    .admin-dark-ready body .gallery-card-meta { color: #94a3b8 !important; }
    body.admin-dark .tour-category-badge,
    .admin-dark-ready body .tour-category-badge { background: rgba(15, 23, 42, 0.92) !important; color: #e2e8f0 !important; }
    body.admin-dark .tour-popular-badge,
    .admin-dark-ready body .tour-popular-badge { background: #FAA723 !important; color: #172033 !important; }
    body.admin-dark .muted,
    .admin-dark-ready body .muted,
    body.admin-dark .text-slate-500,
    .admin-dark-ready body .text-slate-500,
    body.admin-dark .text-slate-400,
    .admin-dark-ready body .text-slate-400 { color: #94a3b8 !important; }
    body.admin-dark .text-slate-900,
    .admin-dark-ready body .text-slate-900,
    body.admin-dark .text-slate-800,
    .admin-dark-ready body .text-slate-800,
    body.admin-dark .text-slate-700,
    .admin-dark-ready body .text-slate-700,
    body.admin-dark .text-slate-600,
    .admin-dark-ready body .text-slate-600,
    body.admin-dark .text-\[\#172033\],
    .admin-dark-ready body .text-\[\#172033\] { color: #e2e8f0 !important; }
    body.admin-dark .bg-white,
    .admin-dark-ready body .bg-white { background-color: #1e293b !important; }
    body.admin-dark .bg-slate-50,
    .admin-dark-ready body .bg-slate-50,
    body.admin-dark .bg-slate-100,
    .admin-dark-ready body .bg-slate-100 { background-color: #0f172a !important; }
    body.admin-dark .border-slate-100,
    .admin-dark-ready body .border-slate-100,
    body.admin-dark .border-slate-200,
    .admin-dark-ready body .border-slate-200 { border-color: #334155 !important; }
    body:not(.admin-dark) .sidebar { border-right: 1px solid #e2e8f0; background: #fff !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .brand-wrap { border-color: #e2e8f0 !important; }
    body:not(.admin-dark) .sidebar .sidebar-label { color: #172033 !important; }
    body:not(.admin-dark) .sidebar .sidebar-subtitle,
    body:not(.admin-dark) .sidebar .sidebar-footer { color: #64748b !important; }
    body:not(.admin-dark) .sidebar .side-link { color: #475569 !important; }
    body:not(.admin-dark) .sidebar .side-link:hover { background: #f1f5f9 !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .side-link.active { background: rgba(47, 171, 225, 0.12) !important; color: #172033 !important; }
    body:not(.admin-dark) .sidebar .side-icon { background: #f1f5f9 !important; color: #FAA723 !important; }
    body:not(.admin-dark) .sidebar .side-link.active .side-icon { background: #2FABE1 !important; color: #fff !important; }
    body:not(.admin-dark) .sidebar .brand-wrap > button { background: #f1f5f9 !important; color: #475569 !important; }
    body.admin-dark .sidebar,
    .admin-dark-ready body .sidebar { background: #172033 !important; color: #fff !important; }
    @media (max-width: 1023px) {
      .admin-shell { grid-template-columns: 1fr; }
      .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 40; width: min(244px, 86vw); transform: translateX(-100%); }
      body.sidebar-open .sidebar { transform: translateX(0); }
      #sidebarToggleButton { pointer-events: auto; visibility: visible; }
      body.sidebar-open #sidebarToggleButton { pointer-events: none; visibility: hidden; }
      .mobile-overlay { display: none; }
      body.sidebar-open .mobile-overlay { display: block; }
      body.sidebar-collapsed .admin-shell { grid-template-columns: 1fr; }
      body.sidebar-collapsed .sidebar-label,
      body.sidebar-collapsed .sidebar-subtitle,
      body.sidebar-collapsed .sidebar-footer { display: block; }
      .side-link,
      body.sidebar-collapsed .side-link { width: 100%; justify-content: flex-start; padding-left: 0.75rem; padding-right: 0.75rem; }
      .side-icon,
      body.sidebar-collapsed .side-icon,
      .sidebar-collapsed-ready body .side-icon { margin-left: 0; margin-right: 0; }
      .sidebar-collapsed-ready body .admin-shell { grid-template-columns: 1fr; }
      .sidebar-collapsed-ready body .sidebar-label,
      .sidebar-collapsed-ready body .sidebar-subtitle,
      .sidebar-collapsed-ready body .sidebar-footer { display: block; }
      .sidebar-collapsed-ready body .sidebar { padding-left: 1rem; padding-right: 1rem; }
      .sidebar-collapsed-ready body .side-link { width: 100%; justify-content: flex-start; padding-left: 0.75rem; padding-right: 0.75rem; }
      .sidebar-collapsed-ready body .brand-wrap { justify-content: space-between; }
      .sidebar-collapsed-ready body .brand-wrap > button { display: grid; }
    }
    @media (max-width: 640px) {
      main { padding: 1rem !important; }
      .dashboard-stats-grid,
      .dashboard-widgets-grid { gap: 0.75rem; }
      .dashboard-stat-card { min-height: 6.5rem; }
      .swal2-modal { width: min(20rem, calc(100vw - 2rem)) !important; padding: 1rem !important; border-radius: 0.875rem !important; }
      .swal2-modal .swal2-title { font-size: 1rem !important; padding: 0 !important; }
      .swal2-modal .swal2-html-container { margin: 0.5rem 0 0 !important; font-size: 0.8125rem !important; }
      .swal2-modal .swal2-icon { width: 2.5rem !important; height: 2.5rem !important; margin: 0.5rem auto 0.75rem !important; }
      .swal2-modal .swal2-actions { margin-top: 0.875rem !important; gap: 0.5rem !important; }
      .swal2-modal .swal2-styled { margin: 0 !important; padding: 0.55rem 0.8rem !important; font-size: 0.75rem !important; border-radius: 0.5rem !important; }
    }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
  <div class="mobile-overlay fixed inset-0 z-30 bg-slate-950/50 lg:hidden" onclick="closeSidebar()"></div>
  <div class="admin-shell grid min-h-screen">
    <aside class="sidebar flex h-screen flex-col gap-2 overflow-hidden bg-[#172033] p-3 text-white lg:sticky lg:top-0">
      <div class="brand-wrap flex items-center justify-between gap-2.5 border-b border-white/10 pb-3">
        <div class="flex min-w-0 items-center gap-3">
          <?php if ($logoUrl !== ''): ?>
            <img class="h-11 w-11 shrink-0 rounded-lg object-contain" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
          <?php else: ?>
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-sky-500/15 text-sky-500" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
          <?php endif; ?>
          <div>
            <h1 class="sidebar-label text-base font-medium leading-tight"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="sidebar-subtitle mt-0.5 text-xs text-white/50"><?= htmlspecialchars($brandSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <button class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white/10 text-xs text-white transition hover:bg-white/15" type="button" onclick="closeSideNav()" aria-label="Close sidebar" title="Close sidebar"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <nav class="sidebar-nav mt-1 grid flex-1 auto-rows-max gap-1 pr-1">
        <?php foreach ($items as $key => $item): ?>
          <?php $label = $item['label']; $href = $item['href']; $icon = $item['icon']; ?>
          <a href="<?= $href ?>" class="side-link <?= $activeNavigationKey === $key ? 'active' : '' ?> flex w-full items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-left text-sm font-medium text-white/65 no-underline hover:bg-white/10 hover:text-white">
            <span class="side-icon grid h-7 w-7 shrink-0 place-items-center rounded-md bg-white/10 text-xs text-[#FAA723]"><i class="fa-solid <?= $icon ?>"></i></span>
            <span class="sidebar-label"><?= $label ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer mt-1 hidden gap-1 border-t border-white/10 pt-2 text-xs text-white/50 lg:grid">
        <span>Signed in as <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </aside>
    <div class="content-panel min-w-0">
      <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 px-5 py-3 backdrop-blur lg:px-7">
        <div class="flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <button id="sidebarToggleButton" class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-slate-100 text-xs text-slate-700 transition hover:bg-slate-200" type="button" onclick="toggleSidebar()" aria-label="Open sidebar" title="Open sidebar"><i class="fa-solid fa-bars"></i></button>
            <span class="max-w-40 truncate text-sm font-medium text-slate-400 sm:max-w-72" title="<?= htmlspecialchars($selectedSiteLabel, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($selectedSiteLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <div id="status" class="hidden"></div>
          <div class="flex min-w-0 flex-1 items-center justify-end gap-2">
            <span class="hidden max-w-32 truncate text-sm text-slate-600 sm:inline"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
            <div id="adminAccountMenuWrap" class="relative">
              <button class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-slate-100 px-2.5 text-sm text-slate-700 transition hover:bg-slate-200" type="button" onclick="event.stopPropagation(); toggleAdminAccountMenu()" aria-label="Open account menu" title="Account menu" aria-haspopup="true" aria-expanded="false">
                <i class="fa-solid fa-power-off"></i>
                <i class="fa-solid fa-chevron-down text-[9px]"></i>
              </button>
              <div id="adminAccountMenu" class="absolute right-0 top-11 z-50 hidden w-48 overflow-hidden rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10">
                <?php if ($isSuperAdmin): ?>
                  <a href="<?= htmlspecialchars(admin_url('select-site'), ENT_QUOTES, 'UTF-8') ?>" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 no-underline transition hover:bg-slate-100">
                    <i class="fa-solid fa-arrow-right-arrow-left w-4 text-sky-600"></i>
                    Change Site
                  </a>
                <?php endif; ?>
                <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50" type="button" onclick="confirmLogout()">
                  <i class="fa-solid fa-right-from-bracket w-4"></i>
                  Logout
                </button>
              </div>
            </div>
          </div>
        </div>
      </header>
      <main class="p-5 lg:p-7">
  <script>
    const api = (path) => `/backend/api/${path}`;
    const adminPage = (path) => `<?= admin_secure_path() ?>${path}`;
    const adminToken = <?= json_encode($token) ?>;
    const initialAdminTheme = <?= json_encode($theme) ?>;
  </script>
<?php
}

function admin_footer(string $pageScript = ''): void
{
    ?>
      </main>
    </div>
  </div>
  <script>
    const status = (msg) => document.querySelector('#status').textContent = msg;
    const adminHeaders = () => ({ 'X-Admin-Token': adminToken });
    const jsonHeaders = () => ({ ...adminHeaders(), 'Content-Type': 'application/json' });
    const asJson = (form) => Object.fromEntries(new FormData(form).entries());
    const pretty = (value) => JSON.stringify(value || [], null, 2);
    const toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });
    function success(message) { status(message); toast.fire({ icon: 'success', title: message }); }
    function failure(message) { status(message); toast.fire({ icon: 'error', title: message }); }
    async function confirmDelete(title = 'Delete this item?') {
      const result = await Swal.fire({ title, text: 'This action cannot be undone from the admin panel.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#64748b', confirmButtonText: 'Delete', cancelButtonText: 'Cancel' });
      return result.isConfirmed;
    }
    async function confirmLogout() {
      const result = await Swal.fire({ title: 'Logout?', text: 'Do you want to leave the admin panel?', icon: 'question', showCancelButton: true, confirmButtonColor: '#2FABE1', cancelButtonColor: '#64748b', confirmButtonText: 'Logout', cancelButtonText: 'Cancel' });
      if (result.isConfirmed) window.location.href = '<?= admin_url() ?>?logout=1';
    }
    function toggleAdminAccountMenu() {
      const menu = document.querySelector('#adminAccountMenu');
      const button = document.querySelector('#adminAccountMenuWrap > button');
      if (!menu || !button) return;
      const willOpen = menu.classList.contains('hidden');
      menu.classList.toggle('hidden', !willOpen);
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }
    function closeAdminAccountMenu() {
      document.querySelector('#adminAccountMenu')?.classList.add('hidden');
      document.querySelector('#adminAccountMenuWrap > button')?.setAttribute('aria-expanded', 'false');
    }
    document.addEventListener('click', (event) => {
      if (!event.target.closest('#adminAccountMenuWrap')) closeAdminAccountMenu();
    });
    if (localStorage.getItem('hyenaSidebarCollapsed') === '1') {
      document.body.classList.add('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-collapsed-ready');
    }
    function setAdminTheme(theme, persist = true) {
      const isDark = theme === 'dark';
      document.body.classList.toggle('admin-dark', isDark);
      document.documentElement.classList.toggle('admin-dark-ready', isDark);
      document.documentElement.style.colorScheme = theme;
      localStorage.setItem('hyenaAdminTheme', theme);
      if (persist) saveAdminTheme(theme);
    }
    async function saveAdminTheme(theme) {
      try {
        const body = new FormData();
        body.set('action', 'theme');
        body.set('theme', theme);
        await request(adminPage('settings/'), { method: 'POST', headers: adminHeaders(), body });
      } catch (error) {
        failure(error.message || 'Could not save theme preference');
      }
    }
    setAdminTheme(initialAdminTheme === 'dark' ? 'dark' : 'light', false);
    function setSidebarCollapsed(collapsed) {
      document.body.classList.toggle('sidebar-collapsed', collapsed);
      if (collapsed) localStorage.setItem('hyenaSidebarCollapsed', '1');
      else localStorage.removeItem('hyenaSidebarCollapsed');
    }
    function toggleSidebar() {
      if (window.matchMedia('(max-width: 1023px)').matches) {
        document.body.classList.toggle('sidebar-open');
        return;
      }
      setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    }
    function closeSideNav() {
      if (window.matchMedia('(max-width: 1023px)').matches) {
        document.body.classList.remove('sidebar-open');
        return;
      }
      setSidebarCollapsed(true);
    }
    function closeSidebar() { document.body.classList.remove('sidebar-open'); }
    document.querySelector('#mobileNav')?.addEventListener('change', (event) => { window.location.href = event.target.value; });
    async function request(url, options = {}) {
      const res = await fetch(url, options);
      const text = await res.text();
      let data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (error) {
        const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(clean || 'Server returned invalid JSON');
      }
      if (!res.ok || !data?.ok) throw new Error(data?.error || 'Request failed');
      return data;
    }
  </script>
  <?php if ($pageScript !== ''): ?><script><?= $pageScript ?></script><?php endif; ?>
</body>
</html>
<?php
}

