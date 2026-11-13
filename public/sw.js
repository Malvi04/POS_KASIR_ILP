/**
 * POS Kasir ILP - Offline Resiliency Service Worker
 * Ensures 100% cashier uptime & offline access when LAN or central server is down.
 */

const CACHE_NAME = 'pos-ilp-cache-v2.2';
const PAGES_CACHE = 'pos-ilp-pages-v2.2';

const PRECACHE_ASSETS = [
    '/',
    '/login-kasir',
    '/select-shift',
    '/kasir/select-shift',
    '/pos',
    '/kasir/pos',
    '/cetak-struk',
    '/kasir/cetak-struk',
    '/kasir/transactions',
    '/kasir/transaksi/riwayat',
    '/kasir/transaksi/ditahan',
    '/kasir/sesi',
    '/kasir/inventory',
    '/kasir/members',
    '/kasir/reports',
    '/js/alpine.min.js',
    '/js/cashier-core.js',
    '/js/pos-realtime.js',
    '/js/pos-offline-sync.js',
    '/js/excel-export.js',
    '/manifest.json',
    '/favicon.ico'
];

// 1. Install Event: Cache Core Pages & Automatically Read Vite Build Manifest
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            // A. Pre-cache core static scripts & pages
            await Promise.allSettled(
                PRECACHE_ASSETS.map((url) => {
                    return fetch(url, { cache: 'no-cache' })
                        .then((res) => {
                            if (res && res.status === 200) {
                                // Put pages in PAGES_CACHE as well
                                if (!url.includes('.')) {
                                    caches.open(PAGES_CACHE).then((pCache) => pCache.put(url, res.clone()));
                                }
                                return cache.put(url, res);
                            }
                        })
                        .catch((err) => {
                            console.warn('[SW] Pre-cache item failed for ' + url, err);
                        });
                })
            );

            // B. Dynamically Pre-cache Vite Build Assets (CSS, JS, Fonts)
            try {
                const manifestRes = await fetch('/build/manifest.json', { cache: 'no-cache' });
                if (manifestRes && manifestRes.ok) {
                    const manifest = await manifestRes.json();
                    const assetUrls = Object.values(manifest)
                        .filter(entry => entry.file)
                        .map(entry => '/build/' + entry.file);

                    await Promise.allSettled(
                        assetUrls.map(u => fetch(u, { cache: 'no-cache' }).then(r => r.ok && cache.put(u, r)))
                    );
                }
            } catch (err) {
                console.warn('[SW] Build manifest caching warning:', err);
            }
        })
    );
});

// 2. Activate Event: Clean up old caches & take control immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            caches.keys().then((keys) => {
                return Promise.all(
                    keys.map((key) => {
                        if (key !== CACHE_NAME && key !== PAGES_CACHE) {
                            return caches.delete(key);
                        }
                    })
                );
            }),
            self.clients.claim()
        ])
    );
});

// 3. Fetch Event: Handle Navigation, Static Assets & APIs
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only intercept GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Bypass API calls so client-side JavaScript handles offline fallback locally
    if (url.pathname.startsWith('/api/')) {
        return;
    }

    // Determine if request is a page navigation / HTML view
    const isNavigation = request.mode === 'navigate' ||
        (request.headers.get('accept') && request.headers.get('accept').includes('text/html')) ||
        (url.origin === self.location.origin && !url.pathname.startsWith('/api/') && !url.pathname.match(/\.[a-zA-Z0-9]+$/));

    // A. Page Navigation Requests
    if (isNavigation && url.origin === self.location.origin) {
        event.respondWith(
            fetch(request)
                .then((networkRes) => {
                    if (networkRes && networkRes.status === 200) {
                        const copy = networkRes.clone();
                        caches.open(PAGES_CACHE).then((cache) => {
                            cache.put(request, copy);
                            cache.put(url.pathname, copy.clone()); // Also index by pathname without queries
                        });
                    }
                    return networkRes;
                })
                .catch(async () => {
                    // SERVER DOWN / OFFLINE FALLBACK: Serve cached page
                    const pageCache = await caches.open(PAGES_CACHE);
                    const appCache = await caches.open(CACHE_NAME);

                    // 1. Try exact matching URL
                    let matched = await pageCache.match(request) || await pageCache.match(url.pathname);
                    if (matched) return matched;

                    // 2. Try appCache
                    matched = await appCache.match(url.pathname) || await appCache.match(request);
                    if (matched) return matched;

                    // 3. Contextual fallbacks based on target route
                    if (url.pathname.startsWith('/kasir') || url.pathname === '/pos') {
                        const fallbackPos = await pageCache.match('/kasir/pos') || await pageCache.match('/pos') || await appCache.match('/pos');
                        if (fallbackPos) return fallbackPos;
                    }

                    if (url.pathname.includes('cetak-struk')) {
                        const fallbackStruk = await pageCache.match('/cetak-struk') || await appCache.match('/cetak-struk');
                        if (fallbackStruk) return fallbackStruk;
                    }

                    if (url.pathname.includes('shift')) {
                        const fallbackShift = await pageCache.match('/select-shift') || await appCache.match('/select-shift');
                        if (fallbackShift) return fallbackShift;
                    }

                    // 4. Global root fallback
                    return await pageCache.match('/') || await appCache.match('/') || new Response(
                        '<html><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>POS Minimarket ILP (Mode Offline)</h2><p>Server sedang tidak dapat dihubungi. <a href="/pos">Buka Terminal Kasir</a></p></body></html>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                })
        );
        return;
    }

    // B. Static Assets (CSS, JS, Fonts, Images, Manifest)
    const isStaticAsset = url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.woff') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.ttf') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.json') ||
        url.pathname.startsWith('/build/') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com');

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedRes) => {
                if (cachedRes) {
                    // Update cache in background if network is available
                    fetch(request)
                        .then((freshRes) => {
                            if (freshRes && freshRes.status === 200) {
                                caches.open(CACHE_NAME).then((cache) => cache.put(request, freshRes));
                            }
                        })
                        .catch(() => {});
                    return cachedRes;
                }

                // If not in cache, fetch and store
                return fetch(request)
                    .then((networkRes) => {
                        if (networkRes && networkRes.status === 200) {
                            const copy = networkRes.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return networkRes;
                    })
                    .catch(() => {
                        // Return empty response rather than breaking the UI
                        return new Response('', { status: 408, statusText: 'Offline Fallback' });
                    });
            })
        );
        return;
    }

    // Default: try cache then network
    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request))
    );
});
