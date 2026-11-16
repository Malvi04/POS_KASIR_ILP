<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS System - PT Indo Lampung Perkasa')</title>

    <!-- Preconnect & Asynchronous Web Fonts (Zero render-blocking for instantaneous initial paint) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..24,400..700,0..1,0" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..24,400..700,0..1,0">
    </noscript>

    <!-- Application CSS & Realtime Echo/Reverb JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- PWA Manifest & Offline Resiliency Meta -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0052cc">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Modular Cacheable Scripts (Non-blocking & Defer) -->
    <script src="/js/excel-export.js" defer></script>
    <script src="/js/pos-realtime.js?v=2.0" defer></script>
    <script src="/js/pos-offline-sync.js?v=2.1" defer></script>
    <script src="/js/cashier-core.js?v=2.0" defer></script>

    <!-- Local Alpine.js with CDN Fallback -->
    <script defer src="/js/alpine.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
        body {
            background-color: #f8fafc; /* Slate-50 */
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            user-select: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .material-symbols-rounded.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        
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
        })();

        // Register Offline PWA Service Worker and purge legacy v2 caches that caused stale redirects
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                if ('caches' in window) {
                    caches.keys().then(function (keys) {
                        keys.filter(function (k) { return k.indexOf('pos-ilp-') === 0 && k.indexOf('-v3') === -1; })
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
