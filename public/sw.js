/* IPO Darbaar service worker: fast repeat visits and an offline fallback.
 * Pages are network-first (IPO data changes through the day); static files are
 * served from cache and refreshed in the background. Bump VERSION to reset caches. */
const VERSION = 'v1';
const STATIC_CACHE = 'darbaar-static-' + VERSION;
const PAGE_CACHE = 'darbaar-pages-' + VERSION;
const OFFLINE_URL = '/offline';
const MAX_PAGES = 30;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png', '/manifest.webmanifest']))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys
        .filter((key) => key.startsWith('darbaar-') && key !== STATIC_CACHE && key !== PAGE_CACHE)
        .map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function isStatic(url) {
  if (url.origin === 'https://fonts.googleapis.com' || url.origin === 'https://fonts.gstatic.com') return true;
  return url.origin === self.location.origin && /^\/(assets|fonts|logos|icons|images|og)\//.test(url.pathname);
}

function isPrivate(url) {
  return /^\/(admin|subscribe|unsubscribe|watchlist\/items|ipo\/search|shorts\/feed)/.test(url.pathname);
}

async function trimPages() {
  const cache = await caches.open(PAGE_CACHE);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - MAX_PAGES; i++) await cache.delete(keys[i]);
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  if (isStatic(url)) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const cached = await cache.match(request);
        const network = fetch(request).then((response) => {
          if (response.ok || response.type === 'opaque') cache.put(request, response.clone());
          return response;
        }).catch(() => cached);
        return cached || network;
      })
    );
    return;
  }

  if (request.mode === 'navigate' && url.origin === self.location.origin && !isPrivate(url)) {
    event.respondWith(
      fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy)).then(trimPages);
        }
        return response;
      }).catch(async () => (await caches.match(request)) || caches.match(OFFLINE_URL))
    );
  }
});
