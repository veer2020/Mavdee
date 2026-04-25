/**
 * service-worker.js
 * Mavdee PWA — Cache-first for static assets, network-first for dynamic pages.
 * v2: offline fallback page, push notification support, background sync placeholder.
 */
const CACHE_NAME = 'mavdee-v3';
const OFFLINE_URL = '/offline.php';
const STATIC_ASSETS = [
  '/',
  '/shop.php',
  '/offline.php',
  '/assets/css/global.css',
  '/assets/js/app.js',
  '/assets/js/cart.js',
  '/manifest.json',
];

// Install: pre-cache static assets + offline page
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activate: remove old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Fetch: cache-first for static, network-first for PHP/API, offline fallback
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET and cross-origin requests
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;

  // Network-first for PHP pages and API calls, with offline fallback
  if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache successful PHP page responses for offline use
          if (response && response.status === 200 && !url.pathname.startsWith('/api/')) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        })
        .catch(async () => {
          // Try cache first, then serve offline page
          const cached = await caches.match(event.request);
          if (cached) return cached;
          // For navigation requests, show offline page
          if (event.request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
          }
          return new Response('Offline', { status: 503 });
        })
    );
    return;
  }

  // Cache-first for everything else (CSS, JS, images)
  // Uses stale-while-revalidate: serve cached copy immediately, then update cache in background
  event.respondWith(
    caches.open(CACHE_NAME).then(cache =>
      cache.match(event.request).then(cached => {
        const networkFetch = fetch(event.request).then(response => {
          if (response && response.status === 200) {
            cache.put(event.request, response.clone());
          }
          return response;
        }).catch(() => {
          if (event.request.destination === 'image') {
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
            return new Response(svg, { status: 200, headers: { 'Content-Type': 'image/svg+xml' } });
          }
        });
        return cached || networkFetch;
      })
    )
  );
});

// Push notifications
self.addEventListener('push', event => {
  if (!event.data) return;
  let data = {};
  try { data = event.data.json(); } catch (e) { data = { title: 'Mavdee', body: event.data.text() }; }
  event.waitUntil(
    self.registration.showNotification(data.title || 'Mavdee', {
      body: data.body || 'You have a new notification',
      icon: '/assets/icons/icon-192.png',
      badge: '/assets/icons/icon-192.png',
      data: { url: data.url || '/' },
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(clients.openWindow(url));
});

