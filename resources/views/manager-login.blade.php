<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Manager | Minimarket Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
                    colors: { primary: '#0a55d4', slate: '#505b70' },
                    boxShadow: { card: '0 1px 2px rgb(15 23 42 / 0.04), 0 9px 24px rgb(15 23 42 / 0.045)', icon: '0 3px 6px rgb(15 23 42 / 0.20)' }
                }
            }
        };
    </script>
    <style>
        body { background: radial-gradient(circle at 50% 38%, #f9fbff 0, #eef4ff 56%, #e5effd 100%); }
        .material-symbols-outlined { font-variation-settings: 'opsz' 24, 'wght' 500, 'FILL' 0, 'GRAD' 0; }
    </style>
</head>
<body class="min-h-screen font-sans text-[#202532] antialiased">
    <main class="flex min-h-screen flex-col items-center justify-center px-5 py-12">
        <section class="w-full max-w-[480px]">
            <div class="rounded-xl border border-[#dfe6f0] bg-white/95 px-8 py-8 shadow-card sm:px-8">
                <header class="mb-8 text-center">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[#2d64e7] text-white shadow-icon">
                        <span class="material-symbols-outlined text-[32px]">business_center</span>
                    </div>
                    <h1 class="text-[25px] font-bold tracking-[-0.045em] text-primary">Minimarket Pro</h1>
                    <p class="mt-1 text-[15px] text-[#41495c]">Portal Khusus Manajer</p>
                </header>

                <form action="{{ url('/dashboard') }}" class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-[12px] font-bold tracking-[0.02em] text-[#4f5b6f]">Nama manager</span>
                        <span class="flex h-12 items-center rounded-lg border border-[#cdd5e4] bg-white px-3 transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                            <span class="material-symbols-outlined mr-3 text-[21px] text-[#70798c]">person</span>
                            <input name="username" required autocomplete="username" placeholder="Masukkan nama manager" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-[#485368] placeholder:text-[#a6acb9] focus:ring-0" aria-label="Nama manager">
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center justify-between text-[12px] font-bold tracking-[0.02em] text-[#4f5b6f]">
                            <span>Kata Sandi</span>
                            <a href="#" class="font-medium text-primary hover:underline">Lupa Sandi?</a>
                        </span>
                        <span class="flex h-12 items-center rounded-lg border border-[#cdd5e4] bg-white px-3 transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15">
                            <span class="material-symbols-outlined mr-3 text-[21px] text-[#70798c]">lock</span>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] tracking-[0.14em] text-[#485368] placeholder:tracking-[0.12em] placeholder:text-[#a6acb9] focus:ring-0" aria-label="Kata Sandi">
                            <button type="button" id="password-toggle" class="flex h-8 w-8 items-center justify-center text-[#70798c] hover:text-primary" aria-label="Tampilkan kata sandi">
                                <span class="material-symbols-outlined text-[22px]">visibility</span>
                            </button>
                        </span>
                    </label>

                    <button type="submit" class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary text-[17px] font-bold text-white shadow-[0_2px_3px_rgba(10,85,212,0.26)] transition hover:bg-[#084ab9] focus:outline-none focus:ring-4 focus:ring-primary/20">
                        <span>Masuk</span>
                        <span class="material-symbols-outlined text-[22px]">login</span>
                    </button>
                </form>

                <div class="mt-8 border-t border-[#e6ebf2] pt-4 text-center">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-1 text-[13px] font-medium text-[#58667e] transition hover:text-primary">
                        <span class="material-symbols-outlined text-[19px]">arrow_back</span>
                        Kembali ke Pilihan Akses
                    </a>
                </div>
            </div>

            <footer class="mt-8 text-center text-[12px] font-semibold tracking-[0.025em] text-[#516078]">
                <p>© 2024 Minimarket Pro. Secure Retail Management.</p>
                <nav class="mt-2 flex items-center justify-center gap-4 text-primary">
                    <a href="#" class="hover:underline">Privacy Policy</a>
                    <span class="h-1 w-1 rounded-full bg-[#c0c9d8]"></span>
                    <a href="#" class="hover:underline">Support</a>
                </nav>
            </footer>
        </section>
    </main>

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
