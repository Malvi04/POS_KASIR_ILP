<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'POS System')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    @vite('resources/css/app.css')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <style>
        body {
            background-color: #f8fafc; /* Slate-50 */
        }
        .material-symbols-rounded {
          font-variation-settings:
          'FILL' 0,
          'wght' 400,
          'GRAD' 0,
          'opsz' 24
        }
        .material-symbols-rounded.filled {
          font-variation-settings:
          'FILL' 1,
          'wght' 400,
          'GRAD' 0,
          'opsz' 24
        }
        
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    <script>
        (function () {
            const pageCache = new Map();

            function prefetchUrl(url) {
                if (!pageCache.has(url)) {
                    pageCache.set(url, fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.ok ? r.text() : null)
                        .catch(() => null));
                }
                return pageCache.get(url);
            }

            async function navigateTo(url, push = true) {
                try {
                    const html = await prefetchUrl(url);
                    if (!html) {
                        window.location.href = url;
                        return;
                    }
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    document.title = doc.title;
                    document.body.innerHTML = doc.body.innerHTML;
                    if (push) {
                        window.history.pushState({ url }, doc.title, url);
                    }
                } catch (err) {
                    window.location.href = url;
                }
            }

            document.addEventListener('mouseover', function (e) {
                const link = e.target.closest('a');
                if (!link || !link.href || link.target || link.hasAttribute('download')) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
                if (link.origin === window.location.origin) {
                    prefetchUrl(link.href);
                }
            }, { passive: true });

            document.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                const link = e.target.closest('a');
                if (!link || !link.href || link.target || link.hasAttribute('download')) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
                if (link.origin === window.location.origin) {
                    e.preventDefault();
                    if (link.href !== window.location.href) {
                        navigateTo(link.href, true);
                    }
                }
            });

            window.addEventListener('popstate', function () {
                pageCache.delete(window.location.href);
                navigateTo(window.location.href, false);
            });
        })();
    </script>
</head>
<body class="font-sans text-slate-800 antialiased overflow-hidden h-screen w-screen flex">
    @yield('content')
</body>
</html>
