<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $reqPath = request()->path();
        $reqRoleParam = strtolower(trim((string) request()->query('role', '')));
        if ($reqRoleParam === 'owner' || str_starts_with($reqPath, 'owner') || in_array($reqPath, ['cabang', 'audit-log'], true)) {
            $posActiveRoleSlot = 'owner';
        } elseif ($reqRoleParam === 'kasir' || str_starts_with($reqPath, 'pos') || str_starts_with($reqPath, 'kasir') || str_starts_with($reqPath, 'cashier') || in_array($reqPath, ['select-shift', 'proses-kredit', 'cetak-struk', 'kupon'], true)) {
            $posActiveRoleSlot = 'kasir';
        } elseif (str_starts_with($reqPath, 'manager') || in_array($reqPath, ['dashboard', 'inventory', 'members', 'member-detail', 'piutang', 'umkm-partners', 'promosi', 'absensi', 'reports', 'kelola-akun', 'settings', 'stock-opname', 'retur', 'retur-masuk', 'kirim-barang', 'pembelian-barang', 'suppliers'], true) || str_starts_with($reqPath, 'suppliers/')) {
            $posActiveRoleSlot = 'manager';
        } else {
            $posActiveRoleSlot = '';
        }
        $posServerAuthUser = auth()->user();
    @endphp
    <meta name="pos-active-role" content="{{ $posActiveRoleSlot }}">
    <title>@yield('title', 'POS System - PT Indo Lampung Perkasa')</title>

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <script>
        (function () {
            const serverRole = @js($posActiveRoleSlot);
            let activeRole = serverRole;
            try {
                if (serverRole) {
                    sessionStorage.setItem('pos_tab_role', serverRole);
                } else {
                    activeRole = sessionStorage.getItem('pos_tab_role') || '';
                }
            } catch (e) {}
            window.POS_ACTIVE_ROLE = activeRole;

            @if($posActiveRoleSlot === 'kasir' && $posServerAuthUser)
            try {
                const kasirUser = @js([
                    'id' => $posServerAuthUser->id,
                    'name' => $posServerAuthUser->name,
                    'username' => $posServerAuthUser->username,
                    'nik' => $posServerAuthUser->nik,
                    'branch' => $posServerAuthUser->branch,
                ]);
                if (kasirUser && kasirUser.id) {
                    localStorage.setItem('pos_portal_logged_in_staff_id', String(kasirUser.id));
                    localStorage.setItem('pos_kasir_staff_id', String(kasirUser.id));
                    if (kasirUser.name) {
                        localStorage.setItem('pos_portal_logged_in_staff_name', kasirUser.name);
                        localStorage.setItem('pos_user_name', kasirUser.name);
                    }
                    if (kasirUser.username) localStorage.setItem('pos_portal_logged_in_username', kasirUser.username);
                    if (kasirUser.nik) localStorage.setItem('pos_portal_logged_in_nik', kasirUser.nik);
                }
            } catch (e) {}
            @endif

            if (typeof window.fetch === 'function' && !window.__posFetchWrapped) {
                window.__posFetchWrapped = true;
                const originalFetch = window.fetch.bind(window);
                window.fetch = function (input, init) {
                    try {
                        let roleHeader = window.POS_ACTIVE_ROLE || '';
                        if (!roleHeader) {
                            try { roleHeader = sessionStorage.getItem('pos_tab_role') || ''; } catch (e) {}
                        }
                        if (roleHeader) {
                            const urlStr = typeof input === 'string' ? input : (input && input.url ? input.url : '');
                            const isSameOrigin = !urlStr || urlStr.startsWith('/') || urlStr.startsWith(window.location.origin);
                            if (isSameOrigin) {
                                init = init ? Object.assign({}, init) : {};
                                const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined) || {});
                                if (!headers.has('X-POS-Role')) {
                                    headers.set('X-POS-Role', roleHeader);
                                }
                                init.headers = headers;
                            }
                        }
                    } catch (e) {}
                    return originalFetch(input, init);
                };
            }
        })();
    </script>

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

        /* Sidebar Attached Toggle Buttons */
        .sidebar-toggle-btn {
            position: absolute;
            right: -14px;
            top: 76px;
            z-index: 50;
            width: 28px;
            height: 28px;
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 9999px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.12), 0 2px 4px -2px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .sidebar-toggle-btn:hover {
            background-color: #eff6ff;
            color: #0052cc;
            border-color: #93c5fd;
            transform: scale(1.1);
        }
        .sidebar-collapsed-btn {
            position: fixed;
            left: 0;
            top: 76px;
            z-index: 50;
            background-color: #0052cc;
            color: #ffffff;
            padding: 8px 12px 8px 8px;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 82, 204, 0.35);
            border: 1px solid #2563eb;
            border-left: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .sidebar-collapsed-btn:hover {
            background-color: #0043a8;
            padding-left: 11px;
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
    <script src="/js/pos-realtime.js?v=3.2.1" defer></script>
    <script src="/js/pos-offline-sync.js?v=3.2.1" defer></script>
    <script src="/js/cashier-core.js?v={{ file_exists(public_path('js/cashier-core.js')) ? filemtime(public_path('js/cashier-core.js')) : '3.1.0' }}" defer></script>

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

    <!-- Global Floating Fullscreen Button & Shortcuts -->
    <x-fullscreen-toggle />
</body>
</html>
