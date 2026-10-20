<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimarket ILP - Portal Akses</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
        }
        .material-symbols-rounded {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-rounded.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-[#f8f9fa] text-gray-800 min-h-screen flex flex-col justify-between antialiased selection:bg-[#0052cc] selection:text-white relative overflow-x-hidden">

    <!-- Background Radial Gradient -->
    <div class="fixed inset-0 pointer-events-none -z-10 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-100/40 via-transparent to-transparent"></div>

    <!-- Top Spacer / Brand Minimal -->
    <div class="w-full h-12"></div>

    <!-- Main Centered Content Container -->
    <main class="w-full max-w-4xl mx-auto px-6 py-6 flex flex-col items-center justify-center my-auto">
        
        <!-- Header -->
        <header id="portal-header" class="text-center mb-9 transition-all duration-300">
            <span class="text-[13px] font-bold tracking-wider text-[#0052cc] uppercase">Minimarket ILP</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight mt-1">Selamat Datang di POS ILP</h1>
            <p id="portal-subtitle" class="text-gray-500 text-[14px] mt-1.5 font-medium transition-all duration-300">Silakan pilih peran Anda untuk memulai sesi</p>
        </header>

        <!-- 1. Role Cards Selector (Smoothly transitions out when any role is picked) -->
        <div id="role-selector" class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-3xl transition-all duration-300 ease-out transform opacity-100 translate-y-0">
            
            <!-- 1. Kasir Card -->
            <button type="button" onclick="selectRole('Kasir')"
                    class="group bg-white rounded-2xl border border-gray-200/90 p-8 shadow-sm hover:shadow-md hover:border-[#0052cc] flex flex-col items-center text-center transition-all duration-200 transform hover:-translate-y-1 cursor-pointer w-full">
                <div class="w-20 h-20 rounded-full bg-blue-50 text-[#0052cc] flex items-center justify-center mb-5 group-hover:bg-[#0052cc] group-hover:text-white transition-colors duration-200 shadow-sm">
                    <span class="material-symbols-rounded text-[40px]">point_of_sale</span>
                </div>
                <h2 class="text-[18px] font-bold text-gray-900">Kasir</h2>
                <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase mt-1">Akses Transaksi</span>
            </button>

            <!-- 2. Manager Card -->
            <button type="button" onclick="selectRole('Manager')"
                    class="group bg-white rounded-2xl border border-gray-200/90 p-8 shadow-sm hover:shadow-md hover:border-[#0052cc] flex flex-col items-center text-center transition-all duration-200 transform hover:-translate-y-1 cursor-pointer w-full">
                <div class="w-20 h-20 rounded-full bg-blue-50 text-[#0052cc] flex items-center justify-center mb-5 group-hover:bg-[#0052cc] group-hover:text-white transition-colors duration-200 shadow-sm">
                    <span class="material-symbols-rounded text-[40px]">inventory_2</span>
                </div>
                <h2 class="text-[18px] font-bold text-gray-900">Manager</h2>
                <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase mt-1">Akses Operasional</span>
            </button>

            <!-- 3. Owner Card -->
            <button type="button" onclick="selectRole('Owner')"
                    class="group bg-white rounded-2xl border border-gray-200/90 p-8 shadow-sm hover:shadow-md hover:border-[#0052cc] flex flex-col items-center text-center transition-all duration-200 transform hover:-translate-y-1 cursor-pointer w-full">
                <div class="w-20 h-20 rounded-full bg-blue-50 text-[#0052cc] flex items-center justify-center mb-5 group-hover:bg-[#0052cc] group-hover:text-white transition-colors duration-200 shadow-sm">
                    <span class="material-symbols-rounded text-[40px]">analytics</span>
                </div>
                <h2 class="text-[18px] font-bold text-gray-900">Owner</h2>
                <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase mt-1">Akses Strategis</span>
            </button>

        </div>

        <!-- 2. Container Forms (Kasir, Manager, Owner with identical smooth animation) -->
        <div id="login-forms-container" class="w-full flex justify-center">

            <!-- FORM 1: LOGIN KASIR -->
            <div id="form-kasir" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-md">
                <div class="bg-white border border-gray-200/90 rounded-2xl p-8 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="material-symbols-rounded text-[28px]">point_of_sale</span>
                        </div>
                        <h2 class="text-[20px] font-bold text-gray-900">Login Akun Petugas Kasir</h2>
                        <p class="text-[13px] text-gray-500 mt-1">Pilih akun petugas resmi &amp; masukkan PIN pribadi</p>
                    </div>

                    <form action="{{ url('/select-shift') }}" method="GET" onsubmit="localStorage.setItem('pos_kasir_staff_id', document.getElementById('kasir-staff-select').value);" class="space-y-4">
                        <div>
                            <label class="block text-[12.5px] font-bold text-gray-700 mb-1.5">Akun Petugas Terdaftar</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">badge</span>
                                <select id="kasir-staff-select" name="staff_id"
                                        onchange="document.getElementById('kasir-username').value = this.options[this.selectedIndex].dataset.username"
                                        class="w-full pl-10 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-bold cursor-pointer">
                                    <option value="USR-02" data-username="budi_kasir01" selected>Budi Kurniawan (NIK: 202409821002)</option>
                                    <option value="USR-01" data-username="andi_kasir">Andi Pratama (NIK: 202409821001)</option>
                                    <option value="USR-03" data-username="citra_kasir">Citra Dewi (NIK: 202409821003)</option>
                                    <option value="USR-04" data-username="dedi_kasir">Dedi Santoso (NIK: 202409821004)</option>
                                </select>
                                <input type="hidden" id="kasir-username" name="username" value="budi_kasir01">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[12.5px] font-bold text-gray-700">PIN Pribadi / Kata Sandi</label>
                                <button type="button" onclick="alert('Silakan hubungi supervisor / manager untuk bantuan reset PIN petugas.')" class="text-[12px] text-[#0052cc] hover:underline font-semibold cursor-pointer">Lupa PIN?</button>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">lock</span>
                                <input type="password" id="kasir-password" name="password" value="123456" required
                                       class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] font-mono text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-bold">
                                <button type="button" onclick="togglePassword('kasir-password', 'kasir-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                    <span id="kasir-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>Lanjut Pilih Terminal &amp; Buka Sesi</span>
                                <span class="material-symbols-rounded text-[20px]">login</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 text-center">
                    <button type="button" onclick="resetSelection()" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-[#0052cc] font-bold text-[13px] transition-colors cursor-pointer py-1 px-3 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                        <span>Kembali Pilih Peran</span>
                    </button>
                </div>
            </div>

            <!-- FORM 2: LOGIN MANAGER -->
            <div id="form-manager" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-md">
                <div class="bg-white border border-gray-200/90 rounded-2xl p-8 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="material-symbols-rounded text-[28px]">inventory_2</span>
                        </div>
                        <h2 class="text-[20px] font-bold text-gray-900">Login Manager</h2>
                        <p class="text-[13px] text-gray-500 mt-1">Portal Khusus Manajer Operasional</p>
                    </div>

                    <form action="{{ url('/dashboard') }}" method="GET" class="space-y-4">
                        <div>
                            <label class="block text-[12.5px] font-bold text-gray-700 mb-1.5">Nama Manager</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">person</span>
                                <input type="text" id="manager-username" name="username" value="andi_manager" placeholder="Masukkan nama manager" required
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-medium">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[12.5px] font-bold text-gray-700">Kata Sandi</label>
                                <button type="button" onclick="alert('Silakan hubungi administrator koperasi untuk reset sandi manager.')" class="text-[12px] text-[#0052cc] hover:underline font-semibold cursor-pointer">Lupa Sandi?</button>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">lock</span>
                                <input type="password" id="manager-password" name="password" value="password" placeholder="••••••••" required
                                       class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-medium">
                                <button type="button" onclick="togglePassword('manager-password', 'manager-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                    <span id="manager-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                </button>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>Masuk Sebagai Manager</span>
                                <span class="material-symbols-rounded text-[20px]">login</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 text-center">
                    <button type="button" onclick="resetSelection()" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-[#0052cc] font-bold text-[13px] transition-colors cursor-pointer py-1 px-3 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                        <span>Kembali Pilih Peran</span>
                    </button>
                </div>
            </div>

            <!-- FORM 3: LOGIN OWNER -->
            <div id="form-owner" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-md">
                <div class="bg-white border border-gray-200/90 rounded-2xl p-8 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="material-symbols-rounded text-[28px]">analytics</span>
                        </div>
                        <h2 class="text-[20px] font-bold text-gray-900">Login Owner</h2>
                        <p class="text-[13px] text-gray-500 mt-1">Masukkan 6 digit PIN otorisasi strategis Anda</p>
                    </div>

                    <form onsubmit="event.preventDefault(); submitOwnerPin();" class="space-y-6">
                        <div class="flex justify-center gap-2.5">
                            <input maxlength="1" oninput="moveToNext(this, 'pin2')" id="pin1" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                            <input maxlength="1" oninput="moveToNext(this, 'pin3')" id="pin2" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                            <input maxlength="1" oninput="moveToNext(this, 'pin4')" id="pin3" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                            <input maxlength="1" oninput="moveToNext(this, 'pin5')" id="pin4" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                            <input maxlength="1" oninput="moveToNext(this, 'pin6')" id="pin5" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                            <input maxlength="1" oninput="submitOwnerPin()" id="pin6" class="pin-dot w-11 h-14 text-center font-mono text-xl font-bold border-2 border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all text-gray-900" type="password" inputmode="numeric" placeholder="•" autocomplete="off">
                        </div>

                        <div class="space-y-3 pt-2">
                            <button type="submit" class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span>Masuk Ke Sistem</span>
                                <span class="material-symbols-rounded text-[20px]">login</span>
                            </button>
                            <div class="text-center">
                                <button type="button" onclick="alert('Silakan hubungi administrator IT koperasi untuk verifikasi reset PIN Owner.')" class="text-[13px] text-[#0052cc] hover:underline font-semibold cursor-pointer">
                                    Lupa PIN Owner?
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="mt-4 text-center">
                    <button type="button" onclick="resetSelection()" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-[#0052cc] font-bold text-[13px] transition-colors cursor-pointer py-1 px-3 rounded-lg hover:bg-gray-100">
                        <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                        <span>Kembali Pilih Peran</span>
                    </button>
                </div>
            </div>

        </div>

    </main>

    <!-- Bottom Footer -->
    <footer class="w-full border-t border-gray-200/80 bg-white/70 backdrop-blur-md px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-[12px] text-gray-500">
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
            <span class="font-bold text-gray-600">Status Sistem: POS Online &amp; Terhubung</span>
        </div>
        <div class="flex items-center gap-4">
            <span>© 2026 Minimarket ILP</span>
            <span class="text-gray-300">•</span>
            <button type="button" onclick="alert('POS Koperasi ILP v2.4.1\nHubungi helpdesk@ilp-pos.co.id untuk bantuan teknis.')" class="hover:text-[#0052cc] font-medium flex items-center gap-1 cursor-pointer">
                <span class="material-symbols-rounded text-[15px]">help</span>
                <span>Bantuan</span>
            </button>
        </div>
    </footer>

    <!-- Script Handling with Identical Smooth Animations -->
    <script>
        let currentActiveForm = null;

        const roleForms = {
            'Kasir': document.getElementById('form-kasir'),
            'Manager': document.getElementById('form-manager'),
            'Owner': document.getElementById('form-owner')
        };

        const roleSubtitles = {
            'Kasir': 'Silakan lengkapi akun Kasir untuk akses transaksi',
            'Manager': 'Silakan lengkapi akun Manager untuk akses operasional',
            'Owner': 'Silakan masukkan PIN otorisasi Owner untuk akses strategis'
        };

        function selectRole(role, instant = false) {
            const roleSelector = document.getElementById('role-selector');
            const targetForm = roleForms[role];
            const subtitle = document.getElementById('portal-subtitle');
            if (!targetForm) return;

            currentActiveForm = targetForm;

            if (subtitle && roleSubtitles[role]) {
                subtitle.innerText = roleSubtitles[role];
            }

            if (instant) {
                roleSelector.classList.add('hidden', 'opacity-0', 'pointer-events-none');
                Object.values(roleForms).forEach(f => f.classList.add('hidden', 'opacity-0', 'translate-y-4'));
                targetForm.classList.remove('hidden');
                targetForm.offsetHeight;
                targetForm.classList.remove('opacity-0', 'translate-y-4');
                focusFirstInput(role);
                return;
            }

            // 1. Smoothly fade out & slide up role selector (Identical to Owner transition)
            roleSelector.classList.add('opacity-0', 'pointer-events-none', '-translate-y-4');

            setTimeout(() => {
                roleSelector.classList.add('hidden');

                // Hide other forms
                Object.values(roleForms).forEach(f => {
                    if (f !== targetForm) {
                        f.classList.add('hidden', 'opacity-0', 'translate-y-4');
                    }
                });

                // Display target form
                targetForm.classList.remove('hidden');
                targetForm.offsetHeight; // force reflow

                setTimeout(() => {
                    targetForm.classList.remove('opacity-0', 'translate-y-4');
                    focusFirstInput(role);
                }, 50);
            }, 250);
        }

        function resetSelection() {
            const roleSelector = document.getElementById('role-selector');
            const subtitle = document.getElementById('portal-subtitle');
            if (!currentActiveForm) return;

            if (subtitle) {
                subtitle.innerText = 'Silakan pilih peran Anda untuk memulai sesi';
            }

            // 1. Smoothly fade out & slide down active form (Identical to Owner transition)
            currentActiveForm.classList.add('opacity-0', 'translate-y-4');

            setTimeout(() => {
                currentActiveForm.classList.add('hidden');
                currentActiveForm = null;

                roleSelector.classList.remove('hidden');
                roleSelector.offsetHeight; // force reflow

                setTimeout(() => {
                    roleSelector.classList.remove('opacity-0', 'pointer-events-none', '-translate-y-4');
                }, 50);
            }, 250);

            // Bersihkan input PIN jika ada
            document.querySelectorAll('.pin-dot').forEach(el => el.value = '');
        }

        function focusFirstInput(role) {
            if (role === 'Kasir') {
                const el = document.getElementById('kasir-username');
                if (el) el.focus();
            } else if (role === 'Manager') {
                const el = document.getElementById('manager-username');
                if (el) el.focus();
            } else if (role === 'Owner') {
                const el = document.getElementById('pin1');
                if (el) el.focus();
            }
        }

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerText = 'visibility_off';
            } else {
                input.type = 'password';
                icon.innerText = 'visibility';
            }
        }

        function moveToNext(current, nextFieldId) {
            if (current.value.length >= 1) {
                const next = document.getElementById(nextFieldId);
                if (next) next.focus();
            }
        }

        function submitOwnerPin() {
            const pinInputs = document.querySelectorAll('.pin-dot');
            let pin = '';
            pinInputs.forEach(input => pin += input.value);

            if (pin.length === 6) {
                window.location.href = "{{ url('/dashboard') }}";
            }
        }

        // Handle keyboard backspace across PIN inputs
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && e.target.classList.contains('pin-dot') && e.target.value === '') {
                const prev = e.target.previousElementSibling;
                if (prev && prev.classList.contains('pin-dot')) {
                    prev.focus();
                }
            }
        });

        // Initialize role if passed in query param or server variable
        @php
            $selectedRole = $initialRole ?? request('role', '');
        @endphp
        @if(!empty($selectedRole))
            window.addEventListener('DOMContentLoaded', function() {
                selectRole('{{ $selectedRole }}', true);
            });
        @endif
    </script>
</body>
</html>
