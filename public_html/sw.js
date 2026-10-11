/* Service worker: كاش للأصول الثابتة + صفحة عدم الاتصال. لا يخزّن الصفحات الديناميكية أو لوحات التحكم. */
const CACHE = 'aramio-v2';
const ASSETS = ['offline.html', 'assets/css/app.css?v=2', 'assets/js/app.js?v=2', 'assets/logo-desktop.png', 'assets/logo-mobile.png'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET') return;
  const url = new URL(r.url);
  if (url.origin !== location.origin) return;
  if (r.mode === 'navigate') { e.respondWith(fetch(r).catch(() => caches.match('offline.html'))); return; }
  if (/\/assets\//.test(url.pathname)) { e.respondWith(caches.match(r).then(hit => hit || fetch(r).then(res => { const cp = res.clone(); caches.open(CACHE).then(c => c.put(r, cp)); return res; }))); }
});
