self.addEventListener('message', event => {
  if (event.data && event.data.type === 'UPDATE_BADGE') {
    const count = event.data.count;

    if ('setAppBadge' in navigator) {
      if (count > 0) {
        navigator.setAppBadge(count);
      } else {
        navigator.clearAppBadge();
      }
    }
  }
});

// Cache básico para PWA
const CACHE_NAME = 'ruta11-v1';
const urlsToCache = [
  '/',
  '/icon.png',
  '/notificacion.mp3'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  // Bypass cache for API calls to ensure fresh session/data
  if (event.request.url.includes('/api/')) {
    return event.respondWith(fetch(event.request));
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});