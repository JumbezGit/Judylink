<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['logout'])) {
    admin_logout();
}

if (admin_is_logged_in()) {
    $destination = admin_is_super_admin() && admin_selected_site_id() < 1
        ? admin_url('select-site')
        : admin_first_allowed_url();
    header('Location: ' . $destination);
    exit;
}

$loginError = '';
$brandName = 'Judylink';
$brandLogo = '';
$homeUrl = admin_site_home_url();
$requestedSiteSlug = trim((string) ($_GET['site'] ?? $_POST['site'] ?? ''));
$loginSite = $requestedSiteSlug !== '' ? admin_login_site_by_slug($requestedSiteSlug) : null;
if ($loginSite) {
    $brandName = (string) $loginSite['site_name'];
    $brandLogo = trim((string) ($loginSite['brand_logo'] ?? ''));
    $homeUrl = admin_site_home_url((string) ($loginSite['primary_domain'] ?? ''));
}

$installSiteSlug = $loginSite ? (string) $loginSite['site_slug'] : '';
$installQuery = $installSiteSlug !== '' ? '?site=' . rawurlencode($installSiteSlug) : '';
$manifestUrl = '/backend/admin-manifest.php' . $installQuery;
$appIconUrl = '/backend/admin-app-icon.php' . ($installSiteSlug !== '' ? '?site=' . rawurlencode($installSiteSlug) . '&size=192' : '?size=192');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($requestedSiteSlug !== '' && !$loginSite) {
        $loginError = 'This site login link is not valid.';
    } elseif (admin_login($username, $password, $loginSite ? (int) $loginSite['id'] : null)) {
        header('Location: ' . admin_after_login_url());
        exit;
    } else {
        $loginError = $loginSite
            ? 'Invalid credentials for this site'
            : 'Invalid username or password';
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0ea5e9">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?> Admin">
  <title><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?> Login</title>
  <link rel="manifest" href="<?= htmlspecialchars($manifestUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($appIconUrl, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script>
    (() => {
      const stored = localStorage.getItem('judylink-theme');
      const theme = stored === 'dark' ? 'dark' : 'light';
      document.documentElement.classList.toggle('login-dark-ready', theme === 'dark');
      document.documentElement.style.colorScheme = theme;
    })();
  </script>
  <link rel="stylesheet" href="/backend/assets/admin.css?v=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .form-control {
      display: block;
      width: 100%;
      padding: .375rem .75rem;
      font-size: 1rem;
      font-weight: 400;
      line-height: 1.5;
      color: #212529;
      background-color: #fff;
      background-clip: padding-box;
      border: 1px solid #ced4da;
      border-radius: .375rem;
      transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    .form-control:focus { color: #212529; background-color: #fff; border-color: #86b7fe; outline: 0; box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25); }
    .password-control { padding-right: 2.75rem; }
    .login-dark-ready body { background: #0f172a !important; color: #e2e8f0 !important; }
    .login-dark-ready main { border-color: #334155 !important; background: #1e293b !important; box-shadow: none !important; }
    .login-dark-ready .text-slate-900,
    .login-dark-ready .text-slate-700 { color: #e2e8f0 !important; }
    .login-dark-ready .text-slate-500 { color: #94a3b8 !important; }
    .login-dark-ready .form-control { border-color: #475569 !important; background: #0f172a !important; color: #e2e8f0 !important; }
    .login-dark-ready .form-control:focus { border-color: #86b7fe !important; box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25) !important; }
    .login-dark-ready #passwordIcon,
    .login-dark-ready button[aria-label="Show password"] { color: #94a3b8 !important; }
    .login-dark-ready #installAppPanel { border-color: #334155 !important; background: #0f172a !important; }
    .login-dark-ready #installAppPanel p { color: #94a3b8 !important; }
    .login-dark-ready #exitAppNotice { border-color: #334155 !important; background: #0f172a !important; color: #cbd5e1 !important; }
    @media (display-mode: standalone) {
      #installAppPanel, #browserHomeLink { display: none !important; }
      #exitAppButton { display: inline-flex !important; }
    }
  </style>
</head>
<body class="grid min-h-[100svh] place-items-center bg-slate-100 p-2 text-slate-900 sm:p-4">
  <main class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 sm:p-5">
    <div class="mb-4 text-center">
      <?php if ($brandLogo !== ''): ?>
        <span class="mx-auto mb-3 grid h-14 w-14 place-items-center overflow-hidden rounded-full border border-slate-200 bg-white p-1 shadow-lg shadow-slate-900/10 sm:h-16 sm:w-16"><img class="max-h-full max-w-full object-contain" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?> logo"></span>
      <?php else: ?>
        <span class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-sky-500 text-xl text-white shadow-lg shadow-sky-500/20 sm:h-16 sm:w-16"><i class="fa-solid fa-globe"></i></span>
      <?php endif; ?>
      <p class="text-sm font-semibold text-sky-600"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></p>
      <h1 class="mt-1 text-xl font-medium tracking-tight sm:text-2xl">Login</h1>
    </div>
    <section id="installAppPanel" class="mb-3 hidden items-center gap-3 rounded-xl border border-sky-100 bg-sky-50 p-3" aria-live="polite">
      <img class="h-11 w-11 shrink-0 rounded-xl bg-white object-contain p-1 shadow-sm" src="<?= htmlspecialchars($appIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <div class="min-w-0 flex-1">
        <strong class="block text-sm font-semibold text-slate-900">Install Admin App</strong>
        <p id="installAppHint" class="mt-0.5 text-xs leading-4 text-slate-500">Add secure, one-tap access to your home screen.</p>
      </div>
      <button id="installAppButton" class="shrink-0 rounded-lg bg-sky-500 px-3 py-2 text-xs font-bold text-white transition hover:bg-sky-600 focus:outline-none focus:ring-4 focus:ring-sky-200" type="button">Install</button>
    </section>
    <?php if ($loginError): ?><p class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-normal text-red-600"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" class="grid gap-3">
      <?php if ($loginSite): ?><input type="hidden" name="site" value="<?= htmlspecialchars((string) $loginSite['site_slug'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Username <input class="form-control" name="username" autocomplete="username" required autofocus></label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Password
        <span class="relative block">
          <input id="passwordInput" class="form-control password-control" name="password" type="password" autocomplete="current-password" required>
          <button class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-500 transition hover:text-slate-900" type="button" onclick="togglePassword()" aria-label="Show password" title="Show password">
            <i id="passwordIcon" class="fa-solid fa-eye"></i>
          </button>
        </span>
      </label>
      <button class="inline-flex items-center justify-center gap-2 rounded-md bg-sky-500 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-600" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
      <a id="browserHomeLink" class="text-center text-sm font-semibold text-sky-600 no-underline transition hover:text-sky-700" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-arrow-left mr-1"></i>Go back home</a>
      <button id="exitAppButton" class="hidden items-center justify-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100" type="button"><i class="fa-solid fa-right-from-bracket"></i>Exit app</button>
    </form>
    <p id="exitAppNotice" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-center text-xs leading-5 text-slate-600" role="status">Your phone blocked automatic closing. Swipe up or use the app switcher to close the app.</p>
  </main>
  <script>
    let deferredInstallPrompt = null;
    const installPanel = document.querySelector('#installAppPanel');
    const installButton = document.querySelector('#installAppButton');
    const installHint = document.querySelector('#installAppHint');
    const browserHomeLink = document.querySelector('#browserHomeLink');
    const exitAppButton = document.querySelector('#exitAppButton');
    const exitAppNotice = document.querySelector('#exitAppNotice');
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isMobile = isIos || /android|mobile/i.test(navigator.userAgent);

    if (isStandalone) {
      browserHomeLink.classList.add('hidden');
      exitAppButton.classList.remove('hidden');
      exitAppButton.classList.add('inline-flex');
    }

    exitAppButton.addEventListener('click', () => {
      exitAppNotice.classList.add('hidden');
      window.close();
      window.setTimeout(() => {
        if (!document.hidden) exitAppNotice.classList.remove('hidden');
      }, 400);
    });

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/backend/admin-sw.js', { scope: '/backend/' }).catch((error) => {
          console.error('Admin app service worker registration failed:', error);
        });
      });
    }

    if (!isStandalone && isMobile) {
      installPanel.classList.remove('hidden');
      installPanel.classList.add('flex');
      if (isIos) {
        installButton.textContent = 'How';
        installHint.textContent = 'Tap Install to see the iPhone home-screen steps.';
      }
    }

    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredInstallPrompt = event;
      installPanel.classList.remove('hidden');
      installPanel.classList.add('flex');
    });

    installButton.addEventListener('click', async () => {
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        if (choice.outcome === 'accepted') installPanel.classList.add('hidden');
        return;
      }

      if (isIos) {
        installHint.innerHTML = 'Tap <strong>Share</strong> <span aria-hidden="true">□↑</span>, then <strong>Add to Home Screen</strong>.';
        installButton.textContent = 'Got it';
        return;
      }

      installHint.textContent = 'Open your browser menu and choose “Install app” or “Add to Home screen”.';
      installButton.textContent = 'Got it';
    });

    window.addEventListener('appinstalled', () => {
      installPanel.classList.add('hidden');
      deferredInstallPrompt = null;
    });

    function togglePassword() {
      const input = document.querySelector('#passwordInput');
      const icon = document.querySelector('#passwordIcon');
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    }
  </script>
</body>
</html>

