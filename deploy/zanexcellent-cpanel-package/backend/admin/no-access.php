<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['logout'])) {
    admin_logout();
}

require_admin_page();

$allowedUrl = admin_first_allowed_url();
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'hasAccess' => $allowedUrl !== admin_url('no-access'),
        'url' => $allowedUrl !== admin_url('no-access') ? $allowedUrl : null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($allowedUrl !== admin_url('no-access')) {
    header('Location: ' . $allowedUrl);
    exit;
}

$brandName = admin_setting('brand_name', 'Hyena Admin');
$brandSubtitle = admin_setting('brand_subtitle', 'Tours & travel control');
$logoUrl = admin_setting('brand_logo', '');
$theme = admin_theme();
$username = admin_username();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Loading | <?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .admin-shell { grid-template-columns: 280px minmax(0, 1fr); }
    @media (max-width: 1023px) {
      .admin-shell { grid-template-columns: 1fr; }
      .sidebar { display: none; }
    }
  </style>
</head>
<body class="min-h-screen <?= $theme === 'dark' ? 'bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900' ?>">
  <div class="admin-shell grid min-h-screen">
    <aside class="sidebar flex h-screen flex-col gap-6 border-r <?= $theme === 'dark' ? 'border-slate-800 bg-[#172033] text-white' : 'border-slate-200 bg-white text-slate-900' ?> p-5 lg:sticky lg:top-0">
      <div class="flex items-center gap-3 border-b <?= $theme === 'dark' ? 'border-white/10' : 'border-slate-200' ?> pb-5">
        <?php if ($logoUrl !== ''): ?>
          <img class="h-11 w-11 shrink-0 rounded-lg object-contain" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-sky-500/15 text-sky-500" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
        <?php endif; ?>
        <div class="min-w-0">
          <h1 class="truncate text-base font-medium leading-tight"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="mt-0.5 truncate text-xs <?= $theme === 'dark' ? 'text-white/50' : 'text-slate-500' ?>"><?= htmlspecialchars($brandSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <nav class="mt-4 grid gap-2"></nav>
    </aside>
    <div class="min-w-0">
      <header class="sticky top-0 z-10 border-b <?= $theme === 'dark' ? 'border-slate-800 bg-slate-950/90' : 'border-slate-200 bg-white/90' ?> px-5 py-3 backdrop-blur lg:px-7">
        <div class="flex items-center justify-between gap-3">
          <span class="text-sm font-medium"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
          <a class="grid h-9 w-9 place-items-center rounded-md <?= $theme === 'dark' ? 'bg-slate-800 text-slate-100 hover:bg-slate-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?> text-sm no-underline transition" href="<?= htmlspecialchars(admin_url(), ENT_QUOTES, 'UTF-8') ?>?logout=1" aria-label="Logout" title="Logout">
            <i class="fa-solid fa-power-off"></i>
          </a>
        </div>
      </header>
      <main class="grid min-h-[calc(100vh-4rem)] place-items-center p-5 lg:p-7">
        <div class="grid justify-items-center gap-3">
          <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-300 border-t-[#2FABE1]"></div>
          <p class="text-sm font-medium <?= $theme === 'dark' ? 'text-slate-300' : 'text-slate-500' ?>">Loading...</p>
        </div>
      </main>
    </div>
  </div>
  <script>
    async function checkAccess() {
      try {
        const response = await fetch(`${window.location.pathname}?status=1`, { cache: 'no-store' });
        const data = await response.json();
        if (data?.hasAccess && data?.url) {
          window.location.href = data.url;
        }
      } catch (error) {}
    }

    setInterval(checkAccess, 3000);
  </script>
</body>
</html>
