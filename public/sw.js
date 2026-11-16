/**
 * POS Kasir ILP - Offline Resiliency Service Worker (v3)
 *
 * Perbaikan:
 *  - Tidak lagi membatalkan navigasi setelah 1.8 detik (penyebab halaman menu
 *    manajerial/owner "terlempar" ke portal login / logout).
 *  - Tidak menyimpan respons hasil redirect (mis. redirect ke halaman login)
 *    ke cache, sehingga cache tidak lagi "teracuni" oleh halaman portal.
 *  - Fallback offline hanya untuk halaman kasir; halaman lain menampilkan
 *    pesan offline, bukan halaman portal login.
 *  - Versi cache dinaikkan agar cache lama yang rusak otomatis dihapus.
 */

const CACHE_NAME = 'pos-ilp-cache-v3.2';
const PAGES_CACHE = 'pos-ilp-pages-v3.2';

const PRECACHE_STATIC = [
    '/fonts/material-symbols-rounded.woff2',
    '/fonts/inter-latin.woff2',
    '/js/alpine.min.js',
    '/js/cashier-core.js?v=2.1',
    '/js/pos-realtime.js?v=2.1',
    '/js/pos-offline-sync.js?v=2.1',
    '/js/excel-export.js',
    '/manifest.json',
    '/favicon.ico'
];

// Halaman kasir yang boleh disajikan dari cache saat server mati
const OFFLINE_CASHIER_PATHS = ['/pos', '/kasir/pos', '/select-shift', '/kasir/select-shift', '/cetak-struk', '/kasir/cetak-struk'];

function isCacheableResponse(res) {
    return res && res.status === 200 && !res.redirected && res.type !== 'opaqueredirect';
}

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            await Promise.allSettled(
                PRECACHE_STATIC.map((url) =>
                    fetch(url, { cache: 'no-cache' })
                        .then((res) => { if (isCacheableResponse(res)) return cache.put(url, res); })
                        .catch(() => {})
                )
            );
            try {
                const manifestRes = await fetch('/build/manifest.json', { cache: 'no-cache' });
                if (manifestRes && manifestRes.ok) {
                    const manifest = await manifestRes.json();
                    const assetUrls = Object.values(manifest).filter(e => e.file).map(e => '/build/' + e.file);
                    await Promise.allSettled(
                        assetUrls.map(u => fetch(u, { cache: 'no-cache' }).then(r => isCacheableResponse(r) && cache.put(u, r)))
                    );
                }
            } catch (err) { /* abaikan */ }
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            caches.keys().then((keys) => Promise.all(
                keys.map((key) => (key !== CACHE_NAME && key !== PAGES_CACHE) ? caches.delete(key) : null)
            )),
            self.clients.claim()
        ])
    );
});

const OFFLINE_HTML = '<html><head><meta charset="utf-8"><title>Offline</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>POS Minimarket ILP (Mode Offline)</h2><p>Server sedang tidak dapat dihubungi. Silakan coba lagi beberapa saat.</p><p><a href="">Muat Ulang</a></p></body></html>';

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) {
        // Google Fonts: cache-first
        if (url.hostname.includes('fonts.googleapis.com') || url.hostname.includes('fonts.gstatic.com')) {
            event.respondWith(
                caches.match(request).then((cached) => cached || fetch(request).then((res) => {
                    if (res && res.status === 200) {
                        const copy = res.clone();
                        caches.open(CACHE_NAME).then((c) => c.put(request, copy));
                    }
                    return res;
                }).catch(() => new Response('', { status: 408 })))
            );
        }
        return;
    }

    // Jangan pernah intersep API, login/logout, dan export file
    if (url.pathname.startsWith('/api/') ||
        url.pathname.includes('login') ||
        url.pathname.includes('logout') ||
        url.pathname.includes('/export') ||
        url.pathname.includes('/template')) {
        return;
    }

    const isNavigation = request.mode === 'navigate' ||
        (request.headers.get('accept') || '').includes('text/html');

    // A. Navigasi halaman: network-first TANPA timeout paksa
    if (isNavigation) {
        event.respondWith(
            fetch(request)
                .then((networkRes) => {
                    const isCashierPage = OFFLINE_CASHIER_PATHS.includes(url.pathname);
                    if (isCashierPage && isCacheableResponse(networkRes)) {
                        const copy = networkRes.clone();
                        caches.open(PAGES_CACHE).then((cache) => cache.put(url.pathname, copy));
                    }
                    return networkRes;
                })
                .catch(async () => {
                    // Hanya jika jaringan/server benar-benar mati
                    const pageCache = await caches.open(PAGES_CACHE);
                    let matched = await pageCache.match(url.pathname);
                    if (matched) return matched;

                    if (url.pathname.startsWith('/kasir') || url.pathname === '/pos') {
                        matched = await pageCache.match('/pos') || await pageCache.match('/kasir/pos');
                        if (matched) return matched;
                    }
                    return new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
                })
        );
        return;
    }

    // B. Aset statis: Cache-First untuk asset versioned/build/font agar navigasi tidak memicu revalidation storm
    const isImmutableStatic = url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/fonts/') ||
        (url.pathname.startsWith('/js/') && (url.search.includes('v=') || url.pathname.endsWith('alpine.min.js') || url.pathname.endsWith('xlsx.full.min.js')));

    if (isImmutableStatic) {
        event.respondWith(
            caches.match(request).then((cachedRes) => {
                if (cachedRes) return cachedRes;
                return fetch(request).then((freshRes) => {
                    if (isCacheableResponse(freshRes)) {
                        const copy = freshRes.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return freshRes;
                }).catch(() => new Response('', { status: 408, statusText: 'Offline Fallback' }));
            })
        );
        return;
    }

    const isStaticAsset = /\.(js|css|woff2?|ttf|ico|png|jpg|jpeg|svg|webp|json)$/.test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedRes) => {
                const networkFetch = fetch(request)
                    .then((freshRes) => {
                        if (isCacheableResponse(freshRes)) {
                            const copy = freshRes.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }
                        return freshRes;
                    })
                    .catch(() => cachedRes || new Response('', { status: 408, statusText: 'Offline Fallback' }));
                return cachedRes || networkFetch;
            })
        );
    }
    // Selain itu: biarkan browser menangani secara normal
});
