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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
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
            <div id="form-kasir" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-md"
                 x-data="cashierPortalLogin()"
                 x-init="init()">
                <div class="bg-white border border-gray-200/90 rounded-2xl p-8 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="material-symbols-rounded text-[28px]">point_of_sale</span>
                        </div>
                        <h2 class="text-[20px] font-bold text-gray-900">Login Akun Petugas Kasir</h2>
                        <p class="text-[13px] text-gray-500 mt-1">Masukkan NIK atau username &amp; password akun portal</p>
                    </div>

                    <form action="{{ url('/select-shift') }}" method="GET"
                          @submit="if (!handleSubmit($event)) return false;"
                          class="space-y-4">

                        <!-- Hidden fields for automated tests & backwards compatibility -->
                        <input type="hidden" id="kasir-username" name="username" :value="selectedStaff ? selectedStaff.username : 'budi_kasir01'">
                        <select id="kasir-staff-select" name="staff_id" class="hidden"></select>

                        <!-- FIELD 1: NIK / USERNAME KASIR (Searchable Autocomplete Input) -->
                        <div class="relative" @click.outside="isOpen = false">
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="kasir-identifier" class="block text-[12.5px] font-bold text-gray-700">NIK / Username Kasir</label>
                                <span class="text-[10px] text-[#0052cc] font-bold">Identitas Resmi Anggota</span>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px] pointer-events-none">badge</span>
                                <input type="text"
                                       id="kasir-identifier"
                                       name="identifier"
                                       role="combobox"
                                       aria-autocomplete="list"
                                       :aria-expanded="isOpen"
                                       aria-controls="kasir-combobox-listbox"
                                       :aria-activedescendant="focusedIndex >= 0 ? 'opt-' + focusedIndex : ''"
                                       x-model="identifierInput"
                                       @focus="isOpen = true"
                                       @input="onInput($event.target.value)"
                                       @keydown="onKeydown($event)"
                                       placeholder="Masukkan NIK atau username kasir..."
                                       required
                                       autocomplete="off"
                                       class="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-mono font-bold">
                                
                                <button type="button" x-show="identifierInput.length > 0"
                                        @click="clearIdentifier()"
                                        class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-0.5"
                                        title="Hapus / Cari Kasir Lain">
                                    <span class="material-symbols-rounded text-[18px]">cancel</span>
                                </button>
                            </div>

                            <!-- Autocomplete Suggestions Popover (Floating Dropdown) -->
                            <div id="kasir-combobox-listbox"
                                 role="listbox"
                                 x-show="isOpen && filteredSuggestions.length > 0"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto divide-y divide-gray-100">
                                
                                <template x-for="(staff, idx) in filteredSuggestions" :key="staff.id">
                                    <div :id="'opt-' + idx"
                                         role="option"
                                         :aria-selected="focusedIndex === idx"
                                         @click="selectStaff(staff)"
                                         @mouseenter="focusedIndex = idx"
                                         :class="focusedIndex === idx ? 'bg-blue-50/80 text-gray-900' : 'hover:bg-gray-50 text-gray-800'"
                                         class="p-2.5 flex items-center justify-between gap-3 cursor-pointer transition-colors">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs shrink-0"
                                                 :class="staff.avatarBg || 'bg-blue-100 text-[#0052cc]'"
                                                 x-text="staff.initials || 'KS'">
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-xs text-gray-900 truncate" x-text="staff.name"></div>
                                                <div class="text-[10.5px] text-gray-500 font-mono truncate" x-text="'@' + staff.username"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 border border-gray-200" x-text="staff.branch || 'Pusat'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- FIELD 2: PASSWORD AKUN PORTAL -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="kasir-password" class="text-[12.5px] font-bold text-gray-700">Password Akun Portal</label>
                                <button type="button" onclick="alert('Silakan hubungi supervisor / manager untuk bantuan reset password portal atau PIN petugas.')" class="text-[12px] text-[#0052cc] hover:underline font-semibold cursor-pointer">Lupa Password?</button>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px]">key</span>
                                <input type="password"
                                       id="kasir-password"
                                       name="password"
                                       x-model="portalPassword"
                                       placeholder="Masukkan password akun..."
                                       required
                                       class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] font-mono text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-bold">
                                <button type="button" onclick="togglePassword('kasir-password', 'kasir-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                    <span id="kasir-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1.5 flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[15px] text-[#0052cc]">info</span>
                                <span>Gunakan Password Akun untuk login portal. PIN 6-digit kasir digunakan saat membuka terminal POS.</span>
                            </p>
                        </div>

                        <!-- Error Message Alert -->
                        <div x-show="errorMsg" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-red-500">error</span>
                            <span x-text="errorMsg"></span>
                        </div>

                        <!-- SUBMIT BUTTON -->
                        <div class="pt-2">
                            <button type="submit"
                                    :disabled="isSubmitting"
                                    class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span x-show="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span x-text="isSubmitting ? 'Memverifikasi...' : 'Lanjut Pilih Terminal & Buka Sesi'"></span>
                                <span x-show="!isSubmitting" class="material-symbols-rounded text-[20px]">login</span>
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

        // Cashier Portal Combobox State & Methods
        window.cashierPortalLogin = function() {
            const defaultCashiers = [
                { id: 'USR-01', name: 'Andi Pratama', username: 'andi_kasir', password: 'andi123', pin: '123456', nik: '202409821001', role: 'Kasir Operasional', status: 'AKTIF', initials: 'AP', avatarBg: 'bg-blue-100 text-[#0052cc]', branch: 'Pusat' },
                { id: 'USR-02', name: 'Budi Kurniawan', username: 'budi_kasir01', password: 'budi123', pin: '123456', nik: '202409821002', role: 'Kasir Utama', status: 'AKTIF', initials: 'BK', avatarBg: 'bg-emerald-100 text-emerald-700', branch: 'Pusat' },
                { id: 'USR-03', name: 'Citra Dewi', username: 'citra_kasir', password: 'citra123', pin: '123456', nik: '202409821003', role: 'Kasir Operasional', status: 'AKTIF', initials: 'CD', avatarBg: 'bg-purple-100 text-purple-700', branch: 'Cabang A' },
                { id: 'USR-04', name: 'Dedi Santoso', username: 'dedi_kasir', password: 'dedi123', pin: '123456', nik: '202409821004', role: 'Kasir Operasional', status: 'AKTIF', initials: 'DS', avatarBg: 'bg-amber-100 text-amber-700', branch: 'Cabang B' }
            ];

            return {
                identifierInput: '202409821002',
                portalPassword: 'budi123',
                isOpen: false,
                focusedIndex: -1,
                selectedStaff: null,
                errorMsg: '',
                isSubmitting: false,
                allStaff: defaultCashiers,

                init() {
                    this.loadStaff();
                    // Pre-select Budi Kurniawan (atau akun tersimpan)
                    const savedStaffId = localStorage.getItem('pos_portal_logged_in_staff_id') || localStorage.getItem('pos_kasir_staff_id') || 'USR-02';
                    const matched = this.allStaff.find(s => s.id === savedStaffId) || this.allStaff[1] || this.allStaff[0];
                    if (matched) {
                        this.selectStaff(matched);
                    }
                },

                loadStaff() {
                    const list = JSON.parse(JSON.stringify(defaultCashiers));
                    try {
                        const rawUsers = localStorage.getItem('pos_ilp_users_v1');
                        if (rawUsers) {
                            const parsed = JSON.parse(rawUsers);
                            if (Array.isArray(parsed)) {
                                parsed.forEach(u => {
                                    if (u.role && u.role.toUpperCase() === 'KASIR' && u.status !== 'NONAKTIF') {
                                        const existIdx = list.findIndex(s => s.id === u.id || (u.username && s.username && s.username.toLowerCase() === u.username.toLowerCase()));
                                        const userBranch = u.branch || (u.assignedBranches && u.assignedBranches[0]?.branch) || 'Pusat';
                                        const userPass = u.password || 'kasir123';
                                        const userPin = u.pin || '123456';
                                        if (existIdx === -1) {
                                            const inits = (u.name || 'Kasir').split(' ').filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase();
                                            list.push({
                                                id: u.id || ('USR-' + Math.floor(10 + Math.random() * 90)),
                                                name: u.name,
                                                username: u.username || 'cashier_' + u.name.toLowerCase().replace(/\s+/g, '_'),
                                                password: userPass,
                                                pin: userPin,
                                                nik: u.nik || '-',
                                                role: 'Kasir Operasional',
                                                status: u.status || 'AKTIF',
                                                initials: inits || 'KS',
                                                avatarBg: 'bg-indigo-100 text-indigo-700',
                                                branch: userBranch
                                            });
                                        } else {
                                            list[existIdx].password = userPass;
                                            list[existIdx].pin = userPin;
                                            list[existIdx].branch = userBranch;
                                        }
                                    }
                                });
                            }
                        }
                    } catch (e) {
                        console.warn('Error loading staff:', e);
                    }
                    this.allStaff = list;
                    this.syncHiddenSelect();
                },

                syncHiddenSelect() {
                    const selectEl = document.getElementById('kasir-staff-select');
                    if (!selectEl) return;
                    selectEl.innerHTML = '';
                    this.allStaff.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.dataset.username = s.username;
                        opt.dataset.password = s.password || 'budi123';
                        opt.dataset.pin = s.pin || '123456';
                        opt.dataset.nik = s.nik || '-';
                        opt.dataset.branch = s.branch || 'Pusat';
                        opt.textContent = `${s.name} (NIK: ${s.nik || '-'}) - ${s.branch}`;
                        selectEl.appendChild(opt);
                    });
                },

                get filteredSuggestions() {
                    const q = (this.identifierInput || '').trim().toLowerCase();
                    if (!q) return this.allStaff.filter(s => (s.status || 'AKTIF').toUpperCase() === 'AKTIF');
                    return this.allStaff.filter(s => {
                        if ((s.status || 'AKTIF').toUpperCase() !== 'AKTIF') return false;
                        const name = (s.name || '').toLowerCase();
                        const u = (s.username || '').toLowerCase();
                        const nik = (s.nik || '').toLowerCase();
                        return name.includes(q) || u.includes(q) || nik.includes(q);
                    });
                },

                onInput(val) {
                    this.isOpen = true;
                    this.focusedIndex = -1;
                    const q = (val || '').trim().toLowerCase();
                    const matched = this.allStaff.find(s => {
                        return (s.username && s.username.toLowerCase() === q) || (s.nik && s.nik.toLowerCase() === q) || (s.name && s.name.toLowerCase() === q);
                    });
                    if (matched) {
                        this.selectedStaff = matched;
                        this.portalPassword = matched.password || 'budi123';
                        this.syncSelectedToStorage(matched);
                    }
                },

                clearIdentifier() {
                    this.identifierInput = '';
                    this.portalPassword = '';
                    this.selectedStaff = null;
                    this.isOpen = true;
                    this.focusedIndex = -1;
                    const inp = document.getElementById('kasir-identifier');
                    if (inp) inp.focus();
                },

                selectStaff(staff) {
                    this.selectedStaff = staff;
                    this.identifierInput = staff.nik && staff.nik !== '-' ? staff.nik : staff.username;
                    this.portalPassword = staff.password || 'budi123';
                    this.isOpen = false;
                    this.focusedIndex = -1;
                    this.errorMsg = '';
                    this.syncSelectedToStorage(staff);
                },

                syncSelectedToStorage(staff) {
                    const selectEl = document.getElementById('kasir-staff-select');
                    if (selectEl) selectEl.value = staff.id;
                    const uInput = document.getElementById('kasir-username');
                    if (uInput) uInput.value = staff.username || '';
                    const passInput = document.getElementById('kasir-password');
                    if (passInput) passInput.value = this.portalPassword;

                    localStorage.setItem('pos_portal_logged_in_staff_id', staff.id);
                    localStorage.setItem('pos_kasir_staff_id', staff.id);
                    localStorage.setItem('pos_portal_logged_in_staff_name', staff.name);
                    if (staff.nik && staff.nik !== '-') {
                        localStorage.setItem('pos_portal_logged_in_nik', staff.nik);
                    }
                    if (staff.branch && staff.branch !== 'Pusat & Cabang A') {
                        localStorage.setItem('pos_kasir_branch', staff.branch);
                        localStorage.setItem('pos_kasir_location', staff.branch);
                    }
                },

                onKeydown(e) {
                    const list = this.filteredSuggestions;
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!this.isOpen) this.isOpen = true;
                        if (list.length > 0) {
                            this.focusedIndex = (this.focusedIndex + 1) % list.length;
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (!this.isOpen) this.isOpen = true;
                        if (list.length > 0) {
                            this.focusedIndex = (this.focusedIndex - 1 + list.length) % list.length;
                        }
                    } else if (e.key === 'Enter') {
                        if (this.isOpen && this.focusedIndex >= 0 && this.focusedIndex < list.length) {
                            e.preventDefault();
                            this.selectStaff(list[this.focusedIndex]);
                        }
                    } else if (e.key === 'Escape') {
                        this.isOpen = false;
                        this.focusedIndex = -1;
                    }
                },

                handleSubmit(e) {
                    const q = (this.identifierInput || '').trim().toLowerCase();
                    let staff = this.selectedStaff;
                    if (!staff) {
                        staff = this.allStaff.find(s => {
                            return (s.username && s.username.toLowerCase() === q) || (s.nik && s.nik.toLowerCase() === q) || (s.name && s.name.toLowerCase() === q) || (s.id && s.id.toLowerCase() === q);
                        });
                    }
                    if (!staff) {
                        if (e && e.preventDefault) e.preventDefault();
                        this.errorMsg = 'Akun kasir tidak ditemukan. Periksa NIK atau Username.';
                        return false;
                    }

                    const expectedPass = staff.password || 'budi123';
                    if (this.portalPassword !== expectedPass) {
                        if (e && e.preventDefault) e.preventDefault();
                        this.errorMsg = `Password portal salah untuk akun ${staff.name}.`;
                        return false;
                    }

                    this.selectStaff(staff);
                    this.isSubmitting = true;
                    this.errorMsg = '';
                    return true;
                }
            };
        };

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

        function handleIdentifierInput(val) {
            const clean = (val || '').trim().toLowerCase();
            const selectEl = document.getElementById('kasir-staff-select');
            if (!selectEl) return;
            const match = Array.from(selectEl.options).find(opt => {
                const text = (opt.text || '').toLowerCase();
                const u = (opt.dataset.username || '').toLowerCase();
                const nik = (opt.dataset.nik || '').toLowerCase();
                return text.includes(clean) || u === clean || nik === clean || opt.value.toLowerCase() === clean;
            });
            if (match) {
                selectEl.value = match.value;
                document.getElementById('kasir-username').value = match.dataset.username || '';
                const passInput = document.getElementById('kasir-password');
                if (passInput && match.dataset.password) {
                    passInput.value = match.dataset.password;
                }
            }
        }

        // Helper Portal Kasir Staff Selection
        function handlePortalStaffSelectChange(selectEl) {
            const opt = selectEl.options[selectEl.selectedIndex];
            if (opt) {
                const idInput = document.getElementById('kasir-identifier');
                if (idInput) {
                    idInput.value = opt.dataset.nik || opt.dataset.username || opt.value;
                }
                document.getElementById('kasir-username').value = opt.dataset.username || '';
                const passInput = document.getElementById('kasir-password');
                if (passInput && opt.dataset.password) {
                    passInput.value = opt.dataset.password;
                }
            }
        }

        function validateAndSavePortalCashierSelection(e) {
            const selectEl = document.getElementById('kasir-staff-select');
            const idInput = document.getElementById('kasir-identifier');
            const passInput = document.getElementById('kasir-password');
            const identifierVal = idInput ? idInput.value.trim().toLowerCase() : '';
            const passwordVal = passInput ? passInput.value.trim() : '';

            let matchedOpt = null;
            if (selectEl) {
                matchedOpt = Array.from(selectEl.options).find(opt => {
                    const nik = (opt.dataset.nik || '').toLowerCase();
                    const u = (opt.dataset.username || '').toLowerCase();
                    const val = opt.value.toLowerCase();
                    const text = opt.text.toLowerCase();
                    return nik === identifierVal || u === identifierVal || val === identifierVal || text.includes(identifierVal);
                }) || selectEl.options[selectEl.selectedIndex];

                const expectedPass = matchedOpt ? (matchedOpt.dataset.password || 'budi123') : 'budi123';
                if (passwordVal !== expectedPass) {
                    if (e && e.preventDefault) e.preventDefault();
                    alert('Password yang dimasukkan tidak sesuai dengan akun ' + (matchedOpt ? matchedOpt.text.split('(')[0].trim() : '') + '!\nSilakan masukkan Password akun kasir yang benar.');
                    return false;
                }
            }
            savePortalCashierSelection(matchedOpt);
            return true;
        }

        function savePortalCashierSelection(optOverride = null) {
            const selectEl = document.getElementById('kasir-staff-select');
            const opt = optOverride || (selectEl ? selectEl.options[selectEl.selectedIndex] : null);
            if (opt) {
                const staffId = opt.value;
                const optText = opt.text || '';
                const staffName = optText.split('(')[0].trim();
                const staffNik = opt.dataset.nik || '';
                localStorage.setItem('pos_portal_logged_in_staff_id', staffId);
                localStorage.setItem('pos_kasir_staff_id', staffId);
                localStorage.setItem('pos_portal_logged_in_staff_name', staffName);
                if (staffNik) {
                    localStorage.setItem('pos_portal_logged_in_nik', staffNik);
                }
            }
        }

        // Sinkronisasi Akun Kasir dari Kelola Akun (pos_ilp_users_v1)
        function syncPortalCashierAccounts() {
            try {
                const selectEl = document.getElementById('kasir-staff-select');
                if (!selectEl) return;
                const rawUsers = localStorage.getItem('pos_ilp_users_v1');
                if (rawUsers) {
                    const users = JSON.parse(rawUsers);
                    if (Array.isArray(users)) {
                        users.forEach(u => {
                            if (u.role && u.role.toUpperCase() === 'KASIR' && u.status !== 'NONAKTIF') {
                                const existingOpt = Array.from(selectEl.options).find(o => o.value === u.id || (u.nik && o.text.includes(u.nik)));
                                const userPass = u.password || (u.username ? u.username.replace('cashier_', '') + '123' : 'kasir123');
                                const userPin = u.pin || '123456';
                                if (!existingOpt) {
                                    const opt = document.createElement('option');
                                    opt.value = u.id;
                                    opt.dataset.username = u.username;
                                    opt.dataset.password = userPass;
                                    opt.dataset.pin = userPin;
                                    opt.dataset.branch = u.branch || (u.assignedBranches && u.assignedBranches[0]?.branch) || 'Pusat';
                                    opt.textContent = `${u.name} (NIK: ${u.nik || '-'}) - ${opt.dataset.branch}`;
                                    selectEl.appendChild(opt);
                                } else {
                                    // Sinkronisasi Password & PIN terbaru jika diperbarui di Kelola Akun
                                    existingOpt.dataset.password = userPass;
                                    existingOpt.dataset.pin = userPin;
                                }
                            }
                        });
                    }
                }
                const savedStaffId = localStorage.getItem('pos_portal_logged_in_staff_id') || localStorage.getItem('pos_kasir_staff_id');
                if (savedStaffId && Array.from(selectEl.options).some(o => o.value === savedStaffId)) {
                    selectEl.value = savedStaffId;
                }
                handlePortalStaffSelectChange(selectEl);
            } catch (e) {
                console.warn('Error syncing portal cashiers:', e);
            }
        }

        // Initialize role if passed in query param or server variable
        @php
            $selectedRole = $initialRole ?? request('role', '');
        @endphp
        window.addEventListener('DOMContentLoaded', function() {
            syncPortalCashierAccounts();
            @if(!empty($selectedRole))
                selectRole('{{ $selectedRole }}', true);
            @endif
        });
    </script>
</body>
</html>
