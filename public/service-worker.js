const CACHE_NAME = 'delegacje-shell-v1';
const STATIC_ASSETS = ['./', './offline.html', './manifest.webmanifest'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;

  const sensitive = ['/api/', '/uploads/', '/documents/', '/pdf/', '/logout'];
  if (sensitive.some(path => url.pathname.includes(path))) return;

  event.respondWith(
    fetch(event.request)
      .then(response => {
        if (
          response.ok &&
          ['style', 'script', 'image', 'font'].includes(event.request.destination)
        ) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
        }
        return response;
      })
      .catch(() => caches.match(event.request).then(hit => hit || caches.match('./offline.html')))
  );
});
