@extends('layouts.app')

@section('title', 'Login Manager | Minimarket Pro')

@section('content')
<div class="min-h-screen w-full font-sans text-[#202532] antialiased" style="background: radial-gradient(circle at 50% 38%, #f9fbff 0, #eef4ff 56%, #e5effd 100%);">
    <style>
        @keyframes smoothSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-smooth {
            animation: smoothSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
    <main class="flex min-h-screen flex-col items-center justify-center px-5 py-12">
        <section class="w-full max-w-[480px] animate-smooth">
            <div class="rounded-xl border border-[#dfe6f0] bg-white/95 px-8 py-8 shadow-card sm:px-8">
                <header class="mb-8 text-center">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-[#2d64e7] text-white shadow-icon">
                        <span class="material-symbols-rounded text-[32px]">business_center</span>
                    </div>
                    <h1 class="text-[25px] font-bold tracking-[-0.045em] text-[#0b57d0]">Minimarket Pro</h1>
                    <p class="mt-1 text-[15px] text-[#41495c]">Portal Khusus Manajer</p>
                </header>

                <form action="{{ url('/dashboard') }}" class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-[12px] font-bold tracking-[0.02em] text-[#4f5b6f]">Nama manager</span>
                        <span class="flex h-12 items-center rounded-lg border border-[#cdd5e4] bg-white px-3 transition focus-within:border-[#0b57d0] focus-within:ring-2 focus-within:ring-[#0b57d0]/15">
                            <span class="material-symbols-rounded mr-3 text-[21px] text-[#70798c]">person</span>
                            <input name="username" required autocomplete="username" placeholder="Masukkan nama manager" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-[#485368] placeholder:text-[#a6acb9] focus:ring-0" aria-label="Nama manager">
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 flex items-center justify-between text-[12px] font-bold tracking-[0.02em] text-[#4f5b6f]">
                            <span>Kata Sandi</span>
                            <a href="#" class="font-medium text-[#0b57d0] hover:underline">Lupa Sandi?</a>
                        </span>
                        <span class="flex h-12 items-center rounded-lg border border-[#cdd5e4] bg-white px-3 transition focus-within:border-[#0b57d0] focus-within:ring-2 focus-within:ring-[#0b57d0]/15">
                            <span class="material-symbols-rounded mr-3 text-[21px] text-[#70798c]">lock</span>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] tracking-[0.14em] text-[#485368] placeholder:tracking-[0.12em] placeholder:text-[#a6acb9] focus:ring-0" aria-label="Kata Sandi">
                            <button type="button" id="password-toggle" class="flex h-8 w-8 items-center justify-center text-[#70798c] hover:text-[#0b57d0]" aria-label="Tampilkan kata sandi">
                                <span class="material-symbols-rounded text-[22px]">visibility</span>
                            </button>
                        </span>
                    </label>

                    <button type="submit" class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#0b57d0] text-[17px] font-bold text-white shadow-[0_2px_3px_rgba(10,85,212,0.26)] transition hover:bg-[#084ab9] focus:outline-none focus:ring-4 focus:ring-[#0b57d0]/20">
                        <span>Masuk</span>
                        <span class="material-symbols-rounded text-[22px]">login</span>
                    </button>
                </form>

                <div class="mt-8 border-t border-[#e6ebf2] pt-4 text-center">
                    <a href="{{ url('/') }}" data-no-spa class="inline-flex items-center gap-1 text-[13px] font-medium text-[#58667e] transition hover:text-[#0b57d0]">
                        <span class="material-symbols-rounded text-[19px]">arrow_back</span>
                        Kembali ke Pilihan Akses
                    </a>
                </div>
            </div>

            <footer class="mt-8 text-center text-[12px] font-semibold tracking-[0.025em] text-[#516078]">
                <p>© 2024 Minimarket Pro. Secure Retail Management.</p>
                <nav class="mt-2 flex items-center justify-center gap-4 text-[#0b57d0]">
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
</div>
@endsection
