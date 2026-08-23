<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimarket ILP - Portal Akses</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "tertiary-fixed-dim": "#ffb596",
                        "surface-container-lowest": "#ffffff",
                        "on-tertiary": "#ffffff",
                        "on-secondary-fixed-variant": "#38485d",
                        "surface-variant": "#e0e3e5",
                        "error": "#ba1a1a",
                        "on-tertiary-fixed-variant": "#7d2d00",
                        "on-secondary-fixed": "#0b1c30",
                        "border-subtle": "#e2e8f0",
                        "surface-container-high": "#e6e8ea",
                        "secondary-container": "#d0e1fb",
                        "on-background": "#191c1e",
                        "on-primary-fixed": "#00174b",
                        "on-primary": "#ffffff",
                        "on-surface-variant": "#434655",
                        "on-surface": "#191c1e",
                        "error-container": "#ffdad6",
                        "background": "#f7f9fb",
                        "tertiary-container": "#bc4800",
                        "surface-card": "#ffffff",
                        "tertiary": "#943700",
                        "surface-container": "#eceef0",
                        "surface-bright": "#f7f9fb",
                        "primary-fixed-dim": "#b4c5ff",
                        "tertiary-fixed": "#ffdbcd",
                        "inverse-on-surface": "#eff1f3",
                        "primary": "#004ac6",
                        "text-primary": "#0f172a",
                        "surface-container-highest": "#e0e3e5",
                        "surface-container-low": "#f2f4f6",
                        "status-warning": "#d97706",
                        "surface-tint": "#0053db",
                        "on-primary-fixed-variant": "#003ea8",
                        "on-error-container": "#93000a",
                        "on-primary-container": "#eeefff",
                        "inverse-primary": "#b4c5ff",
                        "text-secondary": "#475569",
                        "status-success": "#16a34a",
                        "on-tertiary-container": "#ffede6",
                        "secondary-fixed-dim": "#b7c8e1",
                        "outline": "#737686",
                        "secondary-fixed": "#d3e4fe",
                        "status-error": "#dc2626",
                        "secondary": "#505f76",
                        "on-error": "#ffffff",
                        "surface-dim": "#d8dadc",
                        "primary-fixed": "#dbe1ff",
                        "primary-container": "#2563eb",
                        "on-secondary": "#ffffff",
                        "inverse-surface": "#2d3133",
                        "on-secondary-container": "#54647a",
                        "surface": "#f7f9fb",
                        "on-tertiary-fixed": "#360f00",
                        "outline-variant": "#c3c6d7"
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    },
                    spacing: {
                        gutter: "16px",
                        "stack-sm": "8px",
                        "stack-lg": "32px",
                        "container-padding": "24px",
                        "stack-md": "16px",
                        base: "4px"
                    },
                    fontFamily: {
                        "financial-lg": ["JetBrains Mono"],
                        "headline-sm": ["Inter"],
                        "financial-md": ["JetBrains Mono"],
                        "display-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "label-caps": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-lg": ["Inter"]
                    },
                    fontSize: {
                        "financial-lg": ["20px", {lineHeight: "28px", fontWeight: "600"}],
                        "headline-sm": ["18px", {lineHeight: "24px", fontWeight: "600"}],
                        "financial-md": ["14px", {lineHeight: "20px", fontWeight: "500"}],
                        "display-lg": ["32px", {lineHeight: "40px", letterSpacing: "-0.02em", fontWeight: "700"}],
                        "body-md": ["14px", {lineHeight: "20px", fontWeight: "400"}],
                        "label-caps": ["12px", {lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "700"}],
                        "headline-md": ["24px", {lineHeight: "32px", fontWeight: "600"}],
                        "body-lg": ["16px", {lineHeight: "24px", fontWeight: "400"}]
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-background text-on-surface font-body-md text-body-md min-h-screen flex flex-col items-center justify-center overflow-hidden relative bg-gradient-to-br from-surface-bright via-surface-container-low to-secondary-container/10">

    <div class="z-10 w-full max-w-4xl px-container-padding flex flex-col items-center space-y-stack-lg">
        <header class="text-center space-y-base mb-8">
            <h1 class="font-headline-md text-headline-md font-bold text-primary tracking-tight">Minimarket ILP</h1>
            <h2 class="font-display-lg text-display-lg text-on-surface">Selamat Datang di POS ILP</h2>
            <p class="text-on-surface-variant font-body-lg text-body-lg">Silakan pilih peran Anda untuk memulai sesi</p>
        </header>

        <main class="w-full flex flex-col items-center space-y-12">
            <!-- Pilihan Role -->
            <div id="role-selector" class="grid grid-cols-1 md:grid-cols-3 gap-stack-md w-full max-w-3xl transition-all duration-300">
                <a href="{{ route('cashier.login') }}" data-role="Kasir" class="role-card group flex flex-col items-center p-stack-lg bg-surface-card border border-border-subtle rounded-xl shadow-sm hover:shadow-md transition-all duration-300 hover:border-primary active:scale-95">
                    <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-stack-md group-hover:bg-primary-fixed-dim transition-colors">
                        <span class="material-symbols-outlined text-[40px] text-primary">point_of_sale</span>
                    </div>
                    <span class="font-headline-sm text-headline-sm text-on-surface mb-1">Kasir</span>
                    <span class="text-on-surface-variant text-label-caps font-label-caps uppercase tracking-widest">Akses Transaksi</span>
                </a>

                <a href="{{ route('manager.login') }}" data-role="Manager" class="role-card group flex flex-col items-center p-stack-lg bg-surface-card border border-border-subtle rounded-xl shadow-sm hover:shadow-md transition-all duration-300 hover:border-primary active:scale-95">
                    <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-stack-md group-hover:bg-primary-fixed-dim transition-colors">
                        <span class="material-symbols-outlined text-[40px] text-primary">inventory_2</span>
                    </div>
                    <span class="font-headline-sm text-headline-sm text-on-surface mb-1">Manager</span>
                    <span class="text-on-surface-variant text-label-caps font-label-caps uppercase tracking-widest">Akses Operasional</span>
                </a>

                <button onclick="selectRole('Owner')" data-role="Owner" class="role-card group flex flex-col items-center p-stack-lg bg-surface-card border border-border-subtle rounded-xl shadow-sm hover:shadow-md transition-all duration-300 hover:border-primary active:scale-95">
                    <div class="w-20 h-20 rounded-full bg-surface-container flex items-center justify-center mb-stack-md group-hover:bg-primary-fixed-dim transition-colors">
                        <span class="material-symbols-outlined text-[40px] text-primary">analytics</span>
                    </div>
                    <span class="font-headline-sm text-headline-sm text-on-surface mb-1">Owner</span>
                    <span class="text-on-surface-variant text-label-caps font-label-caps uppercase tracking-widest">Akses Strategis</span>
                </button>
            </div>

            <!-- Form Login PIN -->
            <div id="login-container" class="hidden opacity-0 translate-y-4 transition-all duration-500 w-full max-w-sm">
                <div class="bg-surface-card border border-border-subtle rounded-xl p-stack-lg shadow-sm space-y-stack-md">
                    <div class="text-center space-y-1">
                        <p id="active-role-text" class="font-headline-sm text-headline-sm text-on-surface">Masuk Sebagai Kasir</p>
                        <p class="text-on-surface-variant text-body-md font-body-md">Masukkan PIN Keamanan</p>
                    </div>

                    <div class="flex justify-center space-x-2">
                        <input maxlength="1" oninput="moveToNext(this, 'pin2')" id="pin1" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                        <input maxlength="1" oninput="moveToNext(this, 'pin3')" id="pin2" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                        <input maxlength="1" oninput="moveToNext(this, 'pin4')" id="pin3" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                        <input maxlength="1" oninput="moveToNext(this, 'pin5')" id="pin4" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                        <input maxlength="1" oninput="moveToNext(this, 'pin6')" id="pin5" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                        <input maxlength="1" oninput="submitPin()" id="pin6" class="pin-dot w-12 h-14 text-center text-financial-lg font-financial-lg border-2 border-border-subtle rounded-lg bg-surface-container-low focus:border-primary focus:ring-0 transition-all outline-none" type="password" inputmode="numeric" placeholder="•">
                    </div>

                    <div class="space-y-stack-sm pt-2">
                        <button class="w-full h-12 bg-primary text-on-primary font-headline-sm text-headline-sm rounded-lg hover:bg-on-primary-fixed-variant active:scale-[0.98] transition-all flex items-center justify-center space-x-2">
                            <span>Masuk Ke Sistem</span>
                            <span class="material-symbols-outlined">login</span>
                        </button>
                        <div class="flex justify-center">
                            <a href="#" class="text-primary font-body-md hover:underline decoration-2 underline-offset-4">Lupa PIN?</a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-center">
                    <button onclick="resetSelection()" class="text-on-surface-variant flex items-center space-x-1 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        <span class="font-label-caps text-label-caps uppercase tracking-wider">Kembali Pilih Peran</span>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <footer class="fixed bottom-0 w-full flex justify-between items-center px-container-padding py-stack-md bg-surface-container-low/80 backdrop-blur-md border-t border-border-subtle">
        <div class="flex items-center space-x-2">
            <div class="w-2 h-2 rounded-full bg-status-success animate-pulse"></div>
            <span class="text-label-caps font-label-caps text-on-surface-variant uppercase">System Status: Online</span>
        </div>
        <div class="flex items-center space-x-gutter">
            <span class="text-label-caps font-label-caps text-text-secondary uppercase">© 2026 Minimarket ILP</span>
            <a href="#" class="text-primary hover:underline text-label-caps font-label-caps uppercase flex items-center space-x-1">
                <span class="material-symbols-outlined text-[14px]">help</span>
                <span>Bantuan</span>
            </a>
        </div>
    </footer>

    <script>
        function selectRole(role) {
            const roleSelector = document.getElementById('role-selector');
            const loginContainer = document.getElementById('login-container');
            const roleText = document.getElementById('active-role-text');
            
            roleText.innerText = `Masuk Sebagai ${role}`;
            
            roleSelector.classList.add('opacity-0', 'pointer-events-none', '-translate-y-8');
            setTimeout(() => {
                roleSelector.classList.add('hidden');
                loginContainer.classList.remove('hidden');
                setTimeout(() => {
                    loginContainer.classList.remove('opacity-0', 'translate-y-4');
                    document.getElementById('pin1').focus();
                }, 50);
            }, 300);
        }

        function resetSelection() {
            const roleSelector = document.getElementById('role-selector');
            const loginContainer = document.getElementById('login-container');
            
            loginContainer.classList.add('opacity-0', 'translate-y-4');
            setTimeout(() => {
                loginContainer.classList.add('hidden');
                roleSelector.classList.remove('hidden');
                setTimeout(() => {
                    roleSelector.classList.remove('opacity-0', 'pointer-events-none', '-translate-y-8');
                }, 50);
            }, 300);

            // Bersihkan PIN ketika kembali
            document.querySelectorAll('.pin-dot').forEach(el => el.value = '');
        }

        function moveToNext(current, nextFieldId) {
            if (current.value.length >= 1) {
                document.getElementById(nextFieldId).focus();
            }
        }
        
        function submitPin() {
            // Animasi atau logika submit bisa ditambahkan di sini
            if (document.getElementById('pin6').value.length >= 1) {
                // contoh: auto klik tombol Masuk
            }
        }
    </script>
</body>
</html>
