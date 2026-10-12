/* Service worker – Rehab Rumah
 * - File tampilan (CSS/JS/ikon) disimpan di HP → aplikasi cepat & tetap terbuka saat sinyal hilang.
 * - Halaman: ambil dari server dulu; kalau offline, tampilkan versi terakhir yang pernah dibuka.
 * - Form (POST) tidak pernah disimpan/diputar ulang; saat offline aplikasi memberi peringatan.
 * Naikkan VERSION setiap kali file di assets/ diubah agar HP mengambil versi baru.
 */
const VERSION = 'rehab-v1';
const STATIC = VERSION + '-static';
const PAGES = VERSION + '-pages';

const ASSETS = [
  './offline.html',
  './manifest.json',
  './assets/style.css',
  './assets/app.js',
  './assets/dashboard.js',
  './assets/vendor/bootstrap.min.css',
  './assets/vendor/bootstrap.bundle.min.js',
  './assets/vendor/bootstrap-icons.min.css',
  './assets/vendor/fonts/bootstrap-icons.woff2',
  './assets/vendor/sweetalert2.all.min.js',
  './assets/vendor/chart.umd.min.js',
  './assets/icons/icon-192.png',
  './assets/icons/favicon-32.png',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(STATIC).then(c => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Halaman memanggil postMessage('clear-pages') saat keluar, agar data tidak tertinggal di HP
self.addEventListener('message', e => {
  if (e.data === 'clear-pages') e.waitUntil(caches.delete(PAGES));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // POST langsung ke server
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // File statis: ambil dari cache, perbarui di belakang layar
  if (url.pathname.includes('/assets/') || url.pathname.endsWith('/manifest.json')) {
    e.respondWith(
      caches.open(STATIC).then(async cache => {
        const hit = await cache.match(req, { ignoreSearch: true });
        const net = fetch(req).then(res => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }

  // Navigasi halaman: jaringan dulu, cadangan dari cache
  if (req.mode === 'navigate') {
    const isLogout = url.pathname.endsWith('/logout.php');
    const noCache = isLogout || url.pathname.endsWith('/login.php') || url.searchParams.has('csv');
    e.respondWith((async () => {
      if (isLogout) await caches.delete(PAGES);
      try {
        const res = await fetch(req);
        if (!noCache && res.ok && !res.redirected) {
          const copy = res.clone();
          caches.open(PAGES).then(c => c.put(req, copy));
        }
        return res;
      } catch (err) {
        const cached = await caches.match(req, { cacheName: PAGES })
                    || await caches.match(req, { cacheName: PAGES, ignoreSearch: true });
        return cached || caches.match('./offline.html');
      }
    })());
  }
});
