<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir | Minimarket Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    colors: { primary: '#0b57d0', ink: '#202124', muted: '#4d576b' },
                    boxShadow: { card: '0 1px 2px rgb(15 23 42 / 0.04), 0 10px 28px rgb(15 23 42 / 0.04)' }
                }
            }
        };
    </script>
    <style>
        body { background: radial-gradient(circle at 50% 38%, #e1e8ff 0, #f7f9fd 38%, #f8fafc 100%); }
        .material-symbols-outlined { font-variation-settings: 'opsz' 24, 'wght' 500, 'FILL' 0, 'GRAD' 0; }
    </style>
</head>
<body class="min-h-screen font-sans text-ink antialiased">
    <main class="min-h-[calc(100vh-37px)] px-5 py-12 flex flex-col items-center justify-center">
        <section class="w-full max-w-[400px] -mt-5">
            <header class="mb-8 text-center">
                <h1 class="text-[25px] font-bold tracking-[-0.04em] text-primary">Minimarket Pro</h1>
                <p class="mt-1 text-[16px] text-[#404656]">Sistem Kasir ILP</p>
            </header>

            <div class="rounded-xl border border-[#e2e8f2] bg-white/95 px-8 py-8 shadow-card sm:px-8">
                <div class="mb-5 text-center">
                    <h2 class="text-[18px] font-bold tracking-[-0.03em]">Login Kasir</h2>
                    <p class="mt-1 text-[12px] font-bold tracking-[0.035em] text-[#526078]">SILAKAN MASUKKAN AKUN ANDA</p>
                </div>

                <form action="{{ url('/pos') }}" class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-[12px] font-bold tracking-[0.02em] text-[#4b556b]">Nama Pengguna</span>
                        <span class="flex h-12 items-center rounded-lg border border-[#dbe3ef] bg-[#fcfdff] px-3 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                            <span class="material-symbols-outlined mr-3 text-[21px] text-[#4c5568]">person</span>
                            <input name="username" value="budi_kasir01" required autocomplete="username" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-[#687386] outline-none focus:ring-0" aria-label="Nama Pengguna">
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center justify-between text-[12px] font-bold tracking-[0.02em] text-[#4b556b]">
                            <span>Kata Sandi</span>
                            <a href="#" class="text-primary hover:underline">Lupa?</a>
                        </span>
                        <span class="flex h-12 items-center rounded-lg border border-[#dbe3ef] bg-[#fcfdff] px-3 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                            <span class="material-symbols-outlined mr-3 text-[21px] text-[#4c5568]">lock</span>
                            <input id="password" name="password" type="password" value="password" required autocomplete="current-password" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] tracking-[0.16em] text-[#687386] outline-none focus:ring-0" aria-label="Kata Sandi">
                            <button type="button" id="password-toggle" class="flex h-8 w-8 items-center justify-center text-[#4c5568] hover:text-primary" aria-label="Tampilkan kata sandi">
                                <span class="material-symbols-outlined text-[22px]">visibility</span>
                            </button>
                        </span>
                    </label>

                    <button type="submit" class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary text-[17px] font-bold text-white shadow-[0_2px_3px_rgba(11,87,208,0.28)] transition hover:bg-[#0847ae] focus:outline-none focus:ring-4 focus:ring-primary/20">
                        <span>Masuk</span>
                        <span class="material-symbols-outlined text-[22px]">login</span>
                    </button>
                </form>

                <div class="mt-8 border-t border-[#e8edf3] pt-4 text-center">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-[12px] font-bold tracking-[0.02em] text-[#526078] transition hover:text-primary">
                        <span class="material-symbols-outlined text-[19px]">arrow_back</span>
                        Kembali ke Pilihan Akses
                    </a>
                </div>
            </div>

            <div class="mt-8 flex justify-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-[#dfe6f0] bg-[#f8fafc]/85 px-4 py-1.5 text-[12px] font-semibold tracking-[0.025em] text-[#526078]">
                    <span class="h-2 w-2 rounded-full bg-[#18ae5b]"></span>
                    Sistem Kasir Online &amp; Terenkripsi
                </span>
            </div>
        </section>
    </main>

    <footer class="flex min-h-[37px] items-center justify-between border-t border-[#e0e7ef] bg-white/45 px-6 py-2 text-[11px] font-semibold tracking-[0.02em] text-[#526078] max-sm:flex-col max-sm:gap-2 max-sm:px-4">
        <span>© 2026 Cloudlink Secure Retail Management.</span>
        <nav class="flex items-center gap-5 underline underline-offset-2">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Contact Support</a>
        </nav>
    </footer>

    <script>
        const password = document.getElementById('password');
        const passwordToggle = document.getElementById('password-toggle');
        passwordToggle.addEventListener('click', () => {
            const isHidden = password.type === 'password';
            password.type = isHidden ? 'text' : 'password';
            passwordToggle.firstElementChild.textContent = isHidden ? 'visibility_off' : 'visibility';
            passwordToggle.setAttribute('aria-label', isHidden ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    </script>
</body>
</html>
