/*
 * Lobby display Service Worker: cache-first for signage media (images and
 * videos under /storage/), so content keeps playing through short network
 * outages (digital-signage "Remote publishing"). Everything else goes to
 * the network untouched.
 */
const CACHE = 'signage-media-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const isMedia = event.request.method === 'GET' && url.pathname.includes('/signage/');
    // Range requests (video seeking) are passed through; full responses are cached.
    if (!isMedia || event.request.headers.has('range')) return;

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const hit = await cache.match(event.request);
            if (hit) return hit;
            const response = await fetch(event.request);
            if (response.ok && response.status === 200) cache.put(event.request, response.clone());
            return response;
        }),
    );
});
