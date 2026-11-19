<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS System - PT Indo Lampung Perkasa')</title>

    <!-- Preload Self-Hosted Critical Fonts (Zero external latency, zero text-icon flash) -->
    <link rel="preload" href="/fonts/material-symbols-rounded.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Critical First-Paint Rules (Active before external CSS/JS finishes) -->
    <style>
        @font-face {
            font-family: 'Material Symbols Rounded';
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url('/fonts/material-symbols-rounded.woff2') format('woff2');
        }
        [x-cloak] {
            display: none !important;
        }
        body {
            background-color: #f8fafc; /* Slate-50 */
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }
        .material-symbols-rounded {
            font-family: 'Material Symbols Rounded';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 1em;
            overflow: hidden;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            flex-shrink: 0;
            -webkit-font-feature-settings: 'liga';
            font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            user-select: none;
        }
        .material-symbols-rounded:not(.block) {
            width: 1em;
        }
        .material-symbols-rounded.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <!-- Application CSS & Realtime Echo/Reverb JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- PWA Manifest & Offline Resiliency Meta -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0052cc">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Dexie.js (IndexedDB) Offline Storage Engine & Migration (Non-blocking & Defer) -->
    <script src="/js/vendor/dexie.min.js?v=3.0.3" defer></script>
    <script src="/js/db/pos-db.js?v=3.0.3" defer></script>
    <script src="/js/db/pos-migration.js?v=3.0.3" defer></script>

    <!-- Modular Cacheable Scripts (Non-blocking & Defer) -->
    <script src="/js/excel-export.js" defer></script>
    <script src="/js/pos-realtime.js?v=3.0.3" defer></script>
    <script src="/js/pos-offline-sync.js?v=3.0.3" defer></script>
    <script src="/js/cashier-core.js?v=3.0.3" defer></script>

    <!-- Local Alpine.js -->
    <script defer src="/js/alpine.min.js"></script>

    <style>
        /* Fast Thin Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

    <script>
        (function () {
            // Global error redirection helper
            window.redirectToError = function(message, code = 500) {
                const params = new URLSearchParams();
                if (code) params.set('code', code);
                if (message) params.set('message', message);
                window.location.href = '/error?' + params.toString();
            };

            // Generate a unique idempotency key per form submission attempt
            window.createIdempotencyKey = function() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return window.crypto.randomUUID();
                }
                return 'req-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
            };

            // Instant Speculative Link Prefetcher (Accelerate page transitions on hover/touch)
            const prefetchedUrls = new Set();
            const currentOrigin = window.location.origin;

            function prefetchPage(url) {
                if (!url) return;
                try {
                    const parsed = new URL(url, currentOrigin);
                    if (parsed.origin !== currentOrigin) return;
                    if (parsed.pathname === window.location.pathname && parsed.search === window.location.search) return;
                    if (
                        parsed.pathname.startsWith('/api/') ||
                        parsed.pathname.includes('logout') ||
                        parsed.pathname.includes('/export') ||
                        parsed.pathname.includes('/template') ||
                        parsed.pathname.includes('/download') ||
                        prefetchedUrls.has(parsed.href)
                    ) {
                        return;
                    }

                    prefetchedUrls.add(parsed.href);
                    const link = document.createElement('link');
                    link.rel = 'prefetch';
                    link.href = parsed.href;
                    link.as = 'document';
                    document.head.appendChild(link);
                } catch (e) {}
            }

            let hoverTimer = null;
            document.addEventListener('mouseover', function (e) {
                const a = e.target.closest('a');
                if (!a || !a.href) return;
                if (hoverTimer) clearTimeout(hoverTimer);
                hoverTimer = setTimeout(function () {
                    prefetchPage(a.href);
                }, 40);
            }, { passive: true });

            document.addEventListener('touchstart', function (e) {
                const a = e.target.closest('a');
                if (a && a.href) prefetchPage(a.href);
            }, { passive: true });

            document.addEventListener('mousedown', function (e) {
                const a = e.target.closest('a');
                if (a && a.href) prefetchPage(a.href);
            }, { passive: true });

            // Instant Top-bar Feedback on Navigation Click
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a');
                if (!a || !a.href) return;
                if (a.target && a.target !== '_self') return;
                if (a.hasAttribute('download')) return;
                if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

                try {
                    const parsed = new URL(a.href, currentOrigin);
                    if (parsed.origin === currentOrigin && (parsed.pathname !== window.location.pathname || parsed.search !== window.location.search)) {
                        let bar = document.getElementById('top-nav-loader');
                        if (!bar) {
                            bar = document.createElement('div');
                            bar.id = 'top-nav-loader';
                            bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,#0052cc,#38bdf8);z-index:99999;transition:width 0.2s ease-out;pointer-events:none;width:0%;';
                            document.body.appendChild(bar);
                        }
                        bar.style.width = '40%';
                        setTimeout(() => { if (bar) bar.style.width = '80%'; }, 80);
                    }
                } catch (err) {}
            }, { passive: true });
        })();

        // Register Offline PWA Service Worker and purge legacy v2 caches that caused stale redirects
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                if ('caches' in window) {
                    caches.keys().then(function (keys) {
                        keys.filter(function (k) { return k.indexOf('pos-ilp-') === 0 && (k.indexOf('-v1') !== -1 || k.indexOf('-v2') !== -1); })
                            .forEach(function (k) { caches.delete(k); });
                    }).catch(function () {});
                }
                navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then(function (reg) {
                    if (typeof reg.update === 'function') {
                        reg.update().catch(function () {});
                    }
                }).catch(function (err) {
                    console.warn('[PWA] Service Worker registration failed:', err);
                });
            });
        }
    </script>
</head>
<body class="font-sans text-slate-800 antialiased overflow-hidden h-screen w-screen flex">
    @yield('content')

    <!-- Global Offline Sync Modal -->
    <x-offline-sync-modal />
</body>
</html>
