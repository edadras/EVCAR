/* SafirCharge PWA service worker — فقط دارایی‌های ثابت کش می‌شوند؛ داده‌ها همیشه از شبکه. */
const VERSION = 'sc-v1';
const STATIC = ['/offline.html', '/assets/mobile/app-mobile.css', '/assets/mobile/app-mobile.js', '/assets/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // صفحات: شبکه، در صورت قطع اینترنت صفحهٔ آفلاین (با راهنمای اتصال به WiFi ایستگاه)
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // CSS/JS/تصاویر: کش با به‌روزرسانی در پس‌زمینه
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.open(VERSION).then((cache) =>
        cache.match(req).then((hit) => {
          const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
          return hit || net;
        })
      )
    );
  }
});
