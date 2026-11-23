const ADMIN_CACHE = "judylink-admin-shell-v2";
const ADMIN_SHELL = [
  "/backend/admin-app-icon.php?size=192",
  "/backend/admin-app-icon.php?size=512",
];

self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(ADMIN_CACHE).then((cache) => cache.addAll(ADMIN_SHELL)));
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith("judylink-admin-") && key !== ADMIN_CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});
