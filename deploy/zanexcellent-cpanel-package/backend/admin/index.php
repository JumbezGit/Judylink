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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (admin_login($username, $password)) {
        header('Location: ' . admin_after_login_url());
        exit;
    }

    $loginError = 'Invalid username or password';
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?> Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script>
    (() => {
      const stored = localStorage.getItem('hyena-theme');
      const theme = stored === 'dark' ? 'dark' : 'light';
      document.documentElement.classList.toggle('login-dark-ready', theme === 'dark');
      document.documentElement.style.colorScheme = theme;
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    .login-dark-ready body { background: #0f172a !important; color: #e2e8f0 !important; }
    .login-dark-ready main { border-color: #334155 !important; background: #1e293b !important; box-shadow: none !important; }
    .login-dark-ready .text-slate-900,
    .login-dark-ready .text-slate-700 { color: #e2e8f0 !important; }
    .login-dark-ready .text-slate-500 { color: #94a3b8 !important; }
    .login-dark-ready input { border-color: #475569 !important; background: #0f172a !important; color: #e2e8f0 !important; }
    .login-dark-ready input:focus { border-color: #2FABE1 !important; box-shadow: 0 0 0 4px rgba(47, 171, 225, .16) !important; }
    .login-dark-ready #passwordIcon,
    .login-dark-ready button[aria-label="Show password"] { color: #94a3b8 !important; }
  </style>
</head>
<body class="grid min-h-[100svh] place-items-center overflow-hidden bg-slate-100 p-2 text-slate-900 sm:p-4">
  <main class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 sm:p-5">
    <div class="mb-4 text-center">
      <span class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-sky-500 text-xl text-white shadow-lg shadow-sky-500/20 sm:h-16 sm:w-16"><i class="fa-solid fa-globe"></i></span>
      <p class="text-sm font-semibold text-sky-600"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></p>
      <h1 class="mt-1 text-xl font-medium tracking-tight sm:text-2xl">Login</h1>
      <p class="mt-1 text-sm text-slate-500">Sign in to manage your assigned website.</p>
    </div>
    <?php if ($loginError): ?><p class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-normal text-red-600"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" class="grid gap-3">
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Username <input class="rounded-xl border border-slate-300 px-3 py-2 font-normal outline-none transition focus:border-[#2FABE1] focus:ring-4 focus:ring-sky-100" name="username" autocomplete="username" required autofocus></label>
      <label class="grid gap-1.5 text-sm font-medium text-slate-700">Password
        <span class="relative block">
          <input id="passwordInput" class="w-full rounded-xl border border-slate-300 px-3 py-2 pr-11 font-normal outline-none transition focus:border-[#2FABE1] focus:ring-4 focus:ring-sky-100" name="password" type="password" autocomplete="current-password" required>
          <button class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-500 transition hover:text-slate-900" type="button" onclick="togglePassword()" aria-label="Show password" title="Show password">
            <i id="passwordIcon" class="fa-solid fa-eye"></i>
          </button>
        </span>
      </label>
      <button class="inline-flex items-center justify-center gap-2 rounded-md bg-sky-500 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-600" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
    </form>
  </main>
  <script>
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

