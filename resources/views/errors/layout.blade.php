<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Terjadi Kesalahan') - POS PT Indo Lampung Perkasa</title>

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <link rel="manifest" href="{{ asset('manifest.json') }}?v=2">

    <!-- Self-Hosted Critical Fonts -->
    <link rel="preload" href="/fonts/material-symbols-rounded.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Tailwind / Application Styles with robust inline fallbacks -->
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif

    <style>
        @font-face {
            font-family: 'Material Symbols Rounded';
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url('/fonts/material-symbols-rounded.woff2') format('woff2');
        }
        :root {
            --primary-blue: #0052cc;
            --primary-hover: #0043a8;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Instrument Sans', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            -webkit-font-smoothing: antialiased;
        }
        .material-symbols-rounded {
            font-family: 'Material Symbols Rounded';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            height: 1em;
            overflow: hidden;
            white-space: nowrap;
            -webkit-font-feature-settings: 'liga';
            font-feature-settings: 'liga';
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-rounded.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 selection:bg-[#0052cc] selection:text-white relative min-h-screen flex flex-col justify-between">

    <!-- Top Glow Background Decorator -->
    <div class="fixed inset-0 pointer-events-none -z-10 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/50 via-transparent to-transparent"></div>

    <!-- Top Navigation / Brand Bar -->
    <header class="w-full bg-white/90 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30 px-4 sm:px-8 py-3.5 shadow-xs">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="/" class="flex items-center gap-3 group text-decoration-none">
                <div class="w-10 h-10 rounded-xl bg-[#0052cc] text-white flex items-center justify-center font-black text-lg shadow-sm group-hover:scale-105 transition-transform duration-200">
                    <span class="material-symbols-rounded text-2xl">point_of_sale</span>
                </div>
                <div>
                    <span class="text-[11px] font-bold tracking-widest text-[#0052cc] uppercase block leading-tight">POS KASIR ILP</span>
                    <span class="text-sm font-extrabold text-slate-900 leading-tight">PT Indo Lampung Perkasa</span>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200/60">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    Status @yield('code', '500')
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="w-full max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12 flex-1 flex flex-col justify-center items-center">
        
        <!-- Main Card -->
        <div class="w-full bg-white rounded-3xl border border-slate-200/90 shadow-xl shadow-slate-200/50 p-6 sm:p-10 text-center relative overflow-hidden transition-all duration-300">
            
            <!-- Top Subtle Accent Bar -->
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-blue-600 via-indigo-500 to-rose-500"></div>

            <!-- Big Icon with Soft Background -->
            <div class="mx-auto w-24 h-24 rounded-3xl @yield('icon_bg', 'bg-rose-50 text-rose-600') border @yield('icon_border', 'border-rose-100') flex items-center justify-center mb-6 shadow-inner">
                <span class="material-symbols-rounded text-5xl">@yield('icon', 'error_outline')</span>
            </div>

            <!-- Error Code Pill -->
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 @yield('badge_classes', 'bg-slate-100 text-slate-700 border border-slate-200')">
                <span class="material-symbols-rounded text-[14px]">info</span>
                KODE KESALAHAN @yield('code', '500')
            </div>

            <!-- Error Title -->
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-2">
                @yield('heading', $__env->yieldContent('title', 'Terjadi Kesalahan pada Halaman'))
            </h1>

            <!-- Friendly Message -->
            <p class="text-slate-600 text-sm sm:text-base max-w-xl mx-auto mb-6 leading-relaxed">
                @yield('message', 'Halaman yang Anda minta tidak dapat ditampilkan atau terjadi gangguan teknis sementara pada server.')
            </p>

            <!-- ======================================================== -->
            <!-- NOTE: SILAHKAN HUBUNGI TEAM IT (HIGHLIGHTED NOTICE BOX)   -->
            <!-- ======================================================== -->
            <div class="w-full bg-gradient-to-br from-amber-50/90 via-orange-50/60 to-amber-50/80 border-2 border-amber-300/80 rounded-2xl p-5 sm:p-6 text-left mb-8 shadow-xs">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                        <span class="material-symbols-rounded text-2xl">support_agent</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <h3 class="text-sm sm:text-base font-bold text-amber-950 flex items-center gap-1.5">
                                <span>Pemberitahuan: Silahkan Hubungi Team IT</span>
                                <span class="bg-amber-200/90 text-amber-900 text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full">Bantuan Teknis</span>
                            </h3>
                        </div>
                        <p class="text-xs sm:text-sm text-amber-900/90 leading-relaxed mb-4">
                            Jika halaman tidak muncul, transaksi terhambat, atau kendala ini terus berulang, <strong>silahkan segera hubungi Team IT Support PT Indo Lampung Perkasa</strong> melalui saluran resmi di bawah ini:
                        </p>

                        <!-- Contact Channels Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                            <!-- WhatsApp IT Support -->
                            <a href="https://wa.me/6281234567890?text={{ urlencode('Halo Team IT ILP, saya mengalami kendala pada POS Kasir: Error ' . ($__env->yieldContent('code') ?: '500') . ' di URL: ' . request()->fullUrl()) }}" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white border border-amber-200/80 hover:border-emerald-500 hover:bg-emerald-50/50 transition-all text-slate-800 text-decoration-none group shadow-2xs">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                    <span class="material-symbols-rounded text-[18px]">chat</span>
                                </span>
                                <div class="overflow-hidden">
                                    <span class="text-[10px] font-bold text-slate-400 block uppercase leading-tight">WhatsApp IT</span>
                                    <span class="text-xs font-bold text-slate-800 truncate block">+62 812-3456-7890</span>
                                </div>
                            </a>

                            <!-- Internal Extension -->
                            <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white border border-amber-200/80 text-slate-800 shadow-2xs">
                                <span class="w-7 h-7 rounded-lg bg-blue-100 text-[#0052cc] flex items-center justify-center shrink-0">
                                    <span class="material-symbols-rounded text-[18px]">call</span>
                                </span>
                                <div class="overflow-hidden">
                                    <span class="text-[10px] font-bold text-slate-400 block uppercase leading-tight">Ekstensi Internal</span>
                                    <span class="text-xs font-bold text-slate-800 truncate block">Ext. 104 / 201</span>
                                </div>
                            </div>

                            <!-- Email IT Helpdesk -->
                            <a href="mailto:it-support@ilp.co.id?subject={{ urlencode('Laporan Kendala POS ILP - Error ' . ($__env->yieldContent('code') ?: '500')) }}&body={{ urlencode('Halo Team IT, berikut detail kendala:\nURL: ' . request()->fullUrl() . '\nWaktu: ' . now()->toIso8601String()) }}" 
                               class="flex items-center gap-2.5 p-2.5 rounded-xl bg-white border border-amber-200/80 hover:border-blue-500 hover:bg-blue-50/50 transition-all text-slate-800 text-decoration-none group shadow-2xs">
                                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0 group-hover:bg-[#0052cc] group-hover:text-white transition-colors">
                                    <span class="material-symbols-rounded text-[18px]">mail</span>
                                </span>
                                <div class="overflow-hidden">
                                    <span class="text-[10px] font-bold text-slate-400 block uppercase leading-tight">Email IT Helpdesk</span>
                                    <span class="text-xs font-bold text-slate-800 truncate block">it-support@ilp.co.id</span>
                                </div>
                            </a>
                        </div>

                        <!-- Copy Incident Info Button -->
                        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-amber-200/60">
                            <span class="text-[11px] text-amber-800/80 flex items-center gap-1">
                                <span class="material-symbols-rounded text-[14px]">schedule</span>
                                Siaga Operasional Kasir & Retail Minimarket
                            </span>
                            <button type="button" 
                                    onclick="copyErrorInfo()" 
                                    id="btn-copy-error"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-amber-900 border border-amber-300 text-xs font-bold transition-all shadow-2xs cursor-pointer">
                                <span class="material-symbols-rounded text-[15px]" id="copy-icon">content_copy</span>
                                <span id="copy-text">Salin Info Error untuk Tim IT</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Grid -->
            <div class="flex flex-wrap items-center justify-center gap-3">
                <!-- Refresh Button -->
                <button type="button" 
                        onclick="window.location.reload()" 
                        class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white text-xs sm:text-sm font-bold flex items-center gap-2 transition-all shadow-sm hover:shadow-md cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">refresh</span>
                    <span>Muat Ulang Halaman</span>
                </button>

                <!-- Back to Previous Page -->
                <button type="button" 
                        onclick="goBackSafe()" 
                        class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-bold flex items-center gap-2 transition-all cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                    <span>Kembali ke Halaman Sebelumnya</span>
                </button>

                <!-- Back to Home / Portal -->
                <a href="/" 
                   class="px-5 py-2.5 rounded-xl bg-white border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-slate-900 text-xs sm:text-sm font-bold flex items-center gap-2 transition-all text-decoration-none shadow-2xs">
                    <span class="material-symbols-rounded text-[18px]">home</span>
                    <span>Halaman Utama (Portal POS)</span>
                </a>
            </div>

            <!-- Collapsible Technical Details (for IT Debugging) -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-left">
                <details class="group cursor-pointer">
                    <summary class="flex items-center justify-between text-xs font-semibold text-slate-500 hover:text-slate-800 select-none py-1">
                        <span class="flex items-center gap-1.5">
                            <span class="material-symbols-rounded text-[16px]">terminal</span>
                            Detail Teknis & Diagnostik Sistem (Untuk Tim IT)
                        </span>
                        <span class="material-symbols-rounded text-[18px] transition-transform duration-200 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="mt-3 p-4 rounded-xl bg-slate-900 text-slate-200 text-xs font-mono space-y-1.5 overflow-x-auto shadow-inner">
                        <div><span class="text-slate-400">STATUS_CODE :</span> <span class="text-emerald-400">@yield('code', '500')</span></div>
                        <div><span class="text-slate-400">URL         :</span> <span class="text-blue-300 break-all">{{ request()->fullUrl() }}</span></div>
                        <div><span class="text-slate-400">METHOD      :</span> <span class="text-amber-300">{{ request()->method() }}</span></div>
                        <div><span class="text-slate-400">TIMESTAMP   :</span> <span class="text-slate-300">{{ now()->toIso8601String() }}</span></div>
                        <div><span class="text-slate-400">CLIENT_IP   :</span> <span class="text-slate-300">{{ request()->ip() }}</span></div>
                        @if(config('app.debug'))
                            @if(isset($exception) && $exception && method_exists($exception, 'getMessage') && $exception->getMessage())
                                <div class="pt-2 border-t border-slate-800">
                                    <span class="text-rose-400">DEBUG_MESSAGE:</span>
                                    <div class="text-slate-300 mt-1 whitespace-pre-wrap">{{ $exception->getMessage() }}</div>
                                </div>
                            @elseif(!empty($customMessage))
                                <div class="pt-2 border-t border-slate-800">
                                    <span class="text-rose-400">DEBUG_MESSAGE:</span>
                                    <div class="text-slate-300 mt-1 whitespace-pre-wrap">{{ $customMessage }}</div>
                                </div>
                            @endif
                        @else
                            <div class="pt-2 border-t border-slate-800 text-slate-400">
                                Detail error internal disembunyikan dalam mode produksi demi keamanan.
                            </div>
                        @endif
                    </div>
                </details>
            </div>

        </div>

        <!-- Help Info Quick Pills -->
        <div class="mt-6 flex flex-wrap items-center justify-center gap-3 text-xs text-slate-500">
            <span class="flex items-center gap-1">
                <span class="material-symbols-rounded text-[15px] text-emerald-600">verified_user</span>
                Data Transaksi Tetap Tersimpan Aman
            </span>
            <span>•</span>
            <span class="flex items-center gap-1">
                <span class="material-symbols-rounded text-[15px] text-[#0052cc]">desktop_windows</span>
                POS Minimarket ILP v2.0
            </span>
        </div>

    </main>

    <!-- Footer -->
    <footer class="w-full text-center py-5 text-xs text-slate-400 border-t border-slate-200/60 bg-white/50">
        <p>&copy; {{ date('Y') }} PT Indo Lampung Perkasa. Seluruh hak cipta dilindungi.</p>
    </footer>

    <!-- Copy Helper Script & Back Navigation Safe Logic -->
    <script>
        function goBackSafe() {
            if (window.history.length > 1 && document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        }

        function copyErrorInfo() {
            const code = document.querySelector('meta[name="error-code"]')?.content || '@yield("code", "500")';
            const info = [
                '=== LAPORAN KENDALA POS ILP ===',
                'Status: Error ' + code,
                'URL: ' + window.location.href,
                'Waktu: ' + new Date().toLocaleString('id-ID'),
                'User Agent: ' + navigator.userAgent,
                'Catatan: Silahkan hubungi Team IT untuk penanganan teknis.'
            ].join('\n');

            navigator.clipboard.writeText(info).then(() => {
                const btn = document.getElementById('btn-copy-error');
                const copyText = document.getElementById('copy-text');
                const copyIcon = document.getElementById('copy-icon');

                if (copyText && copyIcon) {
                    copyText.textContent = 'Berhasil Disalin!';
                    copyIcon.textContent = 'check';
                    btn.classList.remove('bg-white/90', 'text-amber-900');
                    btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600');

                    setTimeout(() => {
                        copyText.textContent = 'Salin Info Error untuk Tim IT';
                        copyIcon.textContent = 'content_copy';
                        btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
                        btn.classList.add('bg-white/90', 'text-amber-900');
                    }, 2500);
                }
            }).catch(() => {
                alert('Detail error:\n' + info);
            });
        }
    </script>
</body>
</html>
