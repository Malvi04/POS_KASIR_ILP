<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Smooth UI Fade for fast SPA transitions */
        .spa-fade-in {
            animation: fadeIn 0.12s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0.92; }
            to { opacity: 1; }
        }
    </style>

    <!-- Lightning Fast SPA Navigation Engine with Realtime Cache Invalidation -->
    <script>
        (function () {
            const pageCache = new Map();

            function isSpaEligible(urlStr) {
                try {
                    const u = new URL(urlStr, window.location.origin);
                    if (u.origin !== window.location.origin) return false;
                    // Never intercept root portal login ('/')
                    if (u.pathname === '/' || u.pathname === '') return false;
                    // Never intercept standalone login pages or logout
                    if (u.pathname.includes('/login') || u.pathname.includes('/logout')) return false;
                    // Never intercept direct file exports or APIs
                    if (u.pathname.includes('/export') || u.pathname.startsWith('/api/') || u.pathname.endsWith('.xlsx') || u.pathname.endsWith('.csv') || u.pathname.endsWith('.pdf')) return false;
                    return true;
                } catch {
                    return false;
                }
            }

            function prefetchUrl(url) {
                if (!isSpaEligible(url)) return Promise.resolve(null);
                if (!pageCache.has(url)) {
                    pageCache.set(url, fetch(url, { headers: { 'Accept': 'text/html,application/xhtml+xml', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.ok ? r.text() : null)
                        .catch(() => null));
                }
                return pageCache.get(url);
            }

            // Invalidate cache immediately whenever data updates in realtime!
            window.addEventListener('pos:realtime', () => {
                pageCache.clear();
            });
            window.addEventListener('ilp-sync-data', () => {
                pageCache.clear();
            });

            async function navigateTo(url, push = true) {
                if (!isSpaEligible(url)) {
                    window.location.href = url;
                    return;
                }
                try {
                    const html = await prefetchUrl(url);
                    if (!html) {
                        window.location.href = url;
                        return;
                    }
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    document.title = doc.title;
                    if (doc.body) {
                        document.body.className = doc.body.className;
                        document.body.innerHTML = doc.body.innerHTML;
                        document.body.classList.add('spa-fade-in');

                        // Re-execute scripts from newly loaded HTML so page-level functions & Alpine component definitions exist
                        const scripts = Array.from(document.body.querySelectorAll('script'));
                        scripts.forEach(oldScript => {
                            const newScript = document.createElement('script');
                            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                            newScript.textContent = oldScript.textContent;
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });
                    }
                    if (window.Alpine) {
                        window.Alpine.initTree(document.body);
                    }
                    if (push) {
                        window.history.pushState({ url }, doc.title, url);
                    }
                } catch (err) {
                    window.location.href = url;
                }
            }

            // Prefetch on hover (sub-50ms instant click response)
            document.addEventListener('mouseover', function (e) {
                const link = e.target.closest('a');
                if (!link || !link.href || link.target || link.hasAttribute('download') || link.hasAttribute('data-no-spa')) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
                if (isSpaEligible(link.href)) {
                    prefetchUrl(link.href);
                }
            }, { passive: true });

            // Instant SPA Navigation on click
            document.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                const link = e.target.closest('a');
                if (!link || !link.href || link.target || link.hasAttribute('download') || link.hasAttribute('data-no-spa')) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
                if (isSpaEligible(link.href)) {
                    e.preventDefault();
                    if (link.href !== window.location.href) {
                        navigateTo(link.href, true);
                    }
                }
            });

            window.addEventListener('popstate', function () {
                if (!isSpaEligible(window.location.href)) {
                    window.location.reload();
                    return;
                }
                pageCache.delete(window.location.href);
                navigateTo(window.location.href, false);
            });
        })();

        // Register Offline PWA Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').then(function (reg) {
                    console.log('[PWA] Service Worker registered:', reg.scope);
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
