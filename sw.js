// Minimal hand-written service worker for offline app-shell caching.
// No external libraries (e.g. Workbox) required.
//
// Strategy:
// - App shell (start page, main routes, offline fallback, manifest, icons): precached on install.
// - Navigations (HTML pages): network-first, falling back to cache, then to /offline.
// - Hashed build assets (/_next/static/...): cache-first (filenames are content-hashed, so
//   a cached copy is always valid for its version).
// - Everything else same-origin GET: stale-while-revalidate.
//
// Every strategy below is guaranteed to resolve to a Response (never undefined/throw) -
// respondWith() resolving to undefined or a rejected promise is what produces Chrome's
// "This page couldn't load" error, so every fallback path ends in a real Response.

const CACHE_VERSION = "v8";
const CACHE_NAME = `schulorg-${CACHE_VERSION}`;

const APP_SHELL = [
  "/",
  "/hausaufgaben",
  "/kalender",
  "/pruefungen",
  "/noten",
  "/faecher",
  "/offline",
  "/manifest.webmanifest",
  "/icons/icon-192.png",
  "/icons/icon-512.png",
  "/icons/icon-maskable-192.png",
  "/icons/icon-maskable-512.png",
  "/icons/apple-touch-icon.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      await Promise.all(
        APP_SHELL.map(async (url) => {
          try {
            await cache.add(url);
          } catch {
            // Ignore individual failures (e.g. server unreachable during install) so
            // the rest of the app shell still gets cached.
          }
        })
      );
      await self.skipWaiting();
    })()
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
      await self.clients.claim();
    })()
  );
});

function isNavigationRequest(request) {
  return (
    request.mode === "navigate" ||
    (request.method === "GET" && request.headers.get("accept")?.includes("text/html"))
  );
}

function isBuildAsset(url) {
  return url.pathname.startsWith("/_next/static/");
}

function fallbackResponse() {
  return new Response("Diese Seite ist offline nicht verfügbar.", {
    status: 503,
    statusText: "Offline",
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
}

async function safeCachePut(cache, request, response) {
  try {
    if (response && response.ok) {
      await cache.put(request, response.clone());
    }
  } catch {
    // Ignore cache write failures (e.g. quota exceeded).
  }
}

async function networkFirst(request, offlineFallbackUrl) {
  const cache = await caches.open(CACHE_NAME);
  try {
    const fresh = await fetch(request);
    await safeCachePut(cache, request, fresh);
    return fresh;
  } catch {
    const cached = await cache.match(request);
    if (cached) return cached;
    if (offlineFallbackUrl) {
      const offline = await cache.match(offlineFallbackUrl);
      if (offline) return offline;
    }
    return fallbackResponse();
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);
  if (cached) return cached;
  try {
    const fresh = await fetch(request);
    await safeCachePut(cache, request, fresh);
    return fresh;
  } catch {
    return fallbackResponse();
  }
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(CACHE_NAME);
  const cached = await cache.match(request);
  const networkUpdate = fetch(request)
    .then(async (fresh) => {
      await safeCachePut(cache, request, fresh);
      return fresh;
    })
    .catch(() => null);

  if (cached) return cached;
  const fresh = await networkUpdate;
  return fresh || fallbackResponse();
}

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Login, Registrierung, Abmelden und Admin immer direkt vom Server laden (nie cachen).
  if (url.pathname.endsWith(".php")) return;

  if (isNavigationRequest(request)) {
    event.respondWith(networkFirst(request, "/offline"));
    return;
  }

  if (isBuildAsset(url)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  event.respondWith(staleWhileRevalidate(request));
});
