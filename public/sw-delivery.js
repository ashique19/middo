/* Middo Rider PWA — shell cache + network-first for Livewire. */
const CACHE = 'middo-rider-shell-v1';
const SHELL = [
  '/delivery/dashboard',
  '/manifest-delivery.webmanifest',
  '/img/settings/logo.png',
  '/img/settings/favicon.ico',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL).catch(() => undefined)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Never cache Livewire/API/auth mutations surfaces.
  if (
    url.pathname.startsWith('/livewire') ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/login') ||
    url.pathname.startsWith('/logout')
  ) {
    return;
  }

  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res.ok && (url.pathname.startsWith('/delivery') || url.pathname.match(/\.(css|js|png|jpg|svg|webp|woff2)$/))) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(req, copy)).catch(() => undefined);
        }
        return res;
      })
      .catch(async () => {
        const cached = await caches.match(req);
        if (cached) {
          return cached;
        }
        if (req.mode === 'navigate') {
          const dash = await caches.match('/delivery/dashboard');
          if (dash) {
            return dash;
          }
        }
        return new Response('You appear to be offline.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
      })
  );
});
