<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimarket ILP - Portal Akses</title>

    <!-- Favicon & Touch Icons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="preload" href="/fonts/material-symbols-rounded.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face {
            font-family: 'Material Symbols Rounded';
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url('/fonts/material-symbols-rounded.woff2') format('woff2');
        }
        [x-cloak] { display: none !important; }
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
        .material-symbols-rounded:not(.block) { width: 1em; }
        .material-symbols-rounded.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
    @vite(['resources/css/app.css'])
    <script defer src="/js/alpine.min.js"></script>
    <style>
        body {
            font-family: 'Instrument Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
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

                    <form @submit.prevent="handleSubmit($event)"
                          class="space-y-4">

                        <!-- Hidden fields for automated tests & backwards compatibility -->
                        <input type="hidden" id="kasir-username" name="username" :value="identifierInput">

                        <!-- FIELD 1: NIK / USERNAME KASIR (Manual Input) -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="kasir-identifier" class="block text-[12.5px] font-bold text-gray-700">NIK / Username Kasir</label>
                                <span class="text-[10px] text-[#0052cc] font-bold">Identitas Resmi Anggota</span>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px] pointer-events-none">badge</span>
                                <input type="text"
                                       id="kasir-identifier"
                                       name="identifier"
                                       x-model="identifierInput"
                                       placeholder="Masukkan NIK atau username kasir..."
                                       required
                                       autocomplete="off"
                                       class="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-mono font-bold">
                                
                                <button type="button" x-show="identifierInput.length > 0"
                                        @click="identifierInput = ''; portalPassword = ''; errorMsg = ''"
                                        class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-0.5"
                                        title="Hapus">
                                    <span class="material-symbols-rounded text-[18px]">cancel</span>
                                </button>
                            </div>
                        </div>

                        <!-- FIELD 2: PASSWORD AKUN PORTAL ATAU PIN -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="kasir-password" class="text-[12.5px] font-bold text-gray-700">Password Akun atau PIN Kasir</label>
                                <button type="button" @click="openRecovery('password')" class="text-[12px] text-[#0052cc] hover:underline font-semibold cursor-pointer">Bantuan Login?</button>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px]">key</span>
                                <input type="password"
                                       id="kasir-password"
                                       name="password"
                                       x-model="portalPassword"
                                       placeholder="Masukkan password atau PIN kasir..."
                                       required
                                       class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] font-mono text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-bold">
                                <button type="button" onclick="togglePassword('kasir-password', 'kasir-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                    <span id="kasir-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1.5 flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[15px] text-[#0052cc]">info</span>
                                <span>Gunakan <strong>Password Akun</strong> atau <strong>PIN Kasir</strong> terdaftar.</span>
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

                <!-- MODAL BANTUAN PEMULIHAN AKUN KASIR (PASSWORD / PIN) -->
                <template x-teleport="body">
                    <div x-show="showRecoveryModal"
                         x-transition.opacity
                         style="display: none;"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                        <div @click.away="showRecoveryModal = false"
                             class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-lg w-full overflow-hidden animate-scale-up text-left">
                            
                            <!-- Modal Header -->
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                                         :class="recoveryType === 'password' ? 'bg-blue-100 text-[#0052cc]' : 'bg-amber-100 text-amber-700'">
                                        <span class="material-symbols-rounded text-[22px]" x-text="recoveryType === 'password' ? 'lock_reset' : 'pin'"></span>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-base" x-text="recoveryType === 'password' ? 'Bantuan Lupa Password Portal' : 'Bantuan Lupa PIN Kasir (POS)'"></h3>
                                        <p class="text-[11.5px] text-slate-500 font-medium">Prosedur pemulihan kredensial resmi POS Kopkar ILP</p>
                                    </div>
                                </div>
                                <button type="button" @click="showRecoveryModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 cursor-pointer">
                                    <span class="material-symbols-rounded text-[20px]">close</span>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="p-6 space-y-4 text-xs font-sans">
                                <!-- Toggle Tab Kendala -->
                                <div class="flex bg-slate-100 p-1 rounded-xl text-xs font-bold">
                                    <button type="button" 
                                            @click="recoveryType = 'password'"
                                            :class="recoveryType === 'password' ? 'bg-white text-[#0052cc] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                            class="flex-1 py-2 rounded-lg text-center transition-all cursor-pointer">
                                        Kendala Password Portal
                                    </button>
                                    <button type="button" 
                                            @click="recoveryType = 'pin'"
                                            :class="recoveryType === 'pin' ? 'bg-white text-amber-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                            class="flex-1 py-2 rounded-lg text-center transition-all cursor-pointer">
                                        Kendala PIN Terminal POS
                                    </button>
                                </div>

                                <!-- Explanation Box -->
                                <div class="p-3.5 rounded-2xl border text-xs space-y-1.5"
                                     :class="recoveryType === 'password' ? 'bg-blue-50/60 border-blue-200 text-blue-950' : 'bg-amber-50/60 border-amber-200 text-amber-950'">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[17px]" :class="recoveryType === 'password' ? 'text-[#0052cc]' : 'text-amber-700'">info</span>
                                        <span x-text="recoveryType === 'password' ? 'Password Portal (Login Web Kasir)' : 'PIN Kasir (6 Digit Pembukaan Terminal POS)'"></span>
                                    </div>
                                    <p class="leading-relaxed text-[12px] text-slate-700"
                                       x-text="recoveryType === 'password' 
                                            ? 'Password portal digunakan saat awal masuk aplikasi di web. Karena akun kasir terintegrasi dengan identitas anggota koperasi, reset password tidak dilakukan via tautan email publik melainkan melalui verifikasi tatap muka oleh Manager Cabang.' 
                                            : 'PIN 6-digit digunakan saat verifikasi serah terima kas atau pembukaan sesi kasir di terminal POS. Jika lupa PIN, Manager Cabang yang bertugas dapat menerbitkan PIN sementara baru setelah memverifikasi identitas Anda.'">
                                    </p>
                                </div>

                                <!-- SOP Steps -->
                                <div class="space-y-2">
                                    <span class="font-bold text-slate-800 uppercase tracking-wider text-[11px] block">Langkah Bantuan Reset Sesuai SOP Koperasi:</span>
                                    
                                    <div class="space-y-2.5">
                                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                            <span class="w-6 h-6 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">1</span>
                                            <div>
                                                <span class="font-bold text-slate-900 block text-xs">Temui Manager Cabang yang Bertugas</span>
                                                <p class="text-[11.5px] text-slate-600 mt-0.5">Kunjungi kantor operasional gerai minimarket tempat Anda ditugaskan (Cabang Pusat, Cabang A, atau Cabang B).</p>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                            <span class="w-6 h-6 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">2</span>
                                            <div>
                                                <span class="font-bold text-slate-900 block text-xs">Bawa Bukti Identitas Anggota</span>
                                                <p class="text-[11.5px] text-slate-600 mt-0.5">Siapkan Kartu Tanda Anggota (KTA) Koperasi atau ID Karyawan dengan NIK terdaftar untuk verifikasi fisik.</p>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80">
                                            <span class="w-6 h-6 rounded-full bg-[#0052cc] text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">3</span>
                                            <div>
                                                <span class="font-bold text-slate-900 block text-xs">Penerbitan Kredensial Sementara</span>
                                                <p class="text-[11.5px] text-slate-600 mt-0.5">Manager akan membuka panel <em>Kelola Akun &amp; Pemulihan</em> untuk menerbitkan kredensial sementara yang wajib Anda ganti saat login berikutnya.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Security Assurance -->
                                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-500 space-y-1">
                                    <div class="font-bold text-slate-700 flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px] text-emerald-600">security</span>
                                        <span>Kebijakan Keamanan Koperasi</span>
                                    </div>
                                    <p>Sistem tidak pernah menanyakan password/PIN lama Anda. Petugas koperasi dilarang meminta atau membagikan kredensial melalui pesan singkat atau panggilan telepon tanpa tatap muka.</p>
                                </div>

                                <!-- Footer Action Buttons -->
                                <div class="flex justify-end pt-2 border-t border-slate-100">
                                    <button type="button" 
                                            @click="showRecoveryModal = false"
                                            class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
                                        Kembali ke Login Kasir
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </template>
            </div>

            <!-- FORM 2: LOGIN MANAGER -->
            <div id="form-manager" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-md"
                 x-data="managerPortalLogin()">
                <div class="bg-white border border-gray-200/90 rounded-2xl p-8 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-14 h-14 bg-blue-50 text-[#0052cc] rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="material-symbols-rounded text-[28px]">inventory_2</span>
                        </div>
                        <h2 class="text-[20px] font-bold text-gray-900">Login Manager</h2>
                        <p class="text-[13px] text-gray-500 mt-1">Portal Khusus Manajer Operasional</p>
                    </div>

                    <form @submit.prevent="handleSubmit()" class="space-y-4">
                        <input type="hidden" name="role" value="manager">
                        <div>
                            <label class="block text-[12.5px] font-bold text-gray-700 mb-1.5">Nama / Username Manager</label>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">person</span>
                                <input type="text" id="manager-username" name="username" x-model="managerUsername" placeholder="Masukkan username atau email manager..." required
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-medium">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-[12.5px] font-bold text-gray-700">Kata Sandi / PIN</label>
                                <button type="button" onclick="alert('Silakan hubungi administrator sistem untuk bantuan pemulihan akses akun Manager.')" class="text-[12px] text-[#0052cc] hover:underline font-semibold cursor-pointer">Bantuan?</button>
                            </div>
                            <div class="relative flex items-center">
                                <span class="material-symbols-rounded absolute left-3 text-gray-400 text-[20px]">lock</span>
                                <input type="password" id="manager-password" name="password" x-model="managerPassword" placeholder="Masukkan kata sandi atau PIN manager..." required
                                       class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] font-mono text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-bold">
                                <button type="button" onclick="togglePassword('manager-password', 'manager-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                    <span id="manager-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1.5 flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[15px] text-[#0052cc]">info</span>
                                <span>Masukkan kata sandi atau PIN terdaftar yang diberikan administrator.</span>
                            </p>
                        </div>

                        <!-- Error Message Alert -->
                        <div x-show="errorMsg" class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-red-500">error</span>
                            <span x-text="errorMsg"></span>
                        </div>

                        <div class="pt-2">
                            <button type="submit" :disabled="isSubmitting" class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                <span x-show="isSubmitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span x-text="isSubmitting ? 'Memverifikasi...' : 'Masuk Sebagai Manager'"></span>
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

            <!-- FORM 3: LOGIN OWNER (Faithful to Screenshot) -->
            <div id="form-owner" class="hidden opacity-0 translate-y-4 transition-all duration-300 ease-out transform w-full max-w-[940px]">
                <div class="bg-white border border-gray-200/90 rounded-[24px] sm:rounded-[28px] shadow-xl overflow-hidden grid grid-cols-1 md:grid-cols-2">
                    
                    <!-- LEFT COLUMN: Exclusive Branding & Analytics Preview -->
                    <div class="bg-[#edf2fe] p-6 sm:p-9 flex flex-col justify-between border-b md:border-b-0 md:border-r border-blue-100/60">
                        <div>
                            <!-- Brand & Icon -->
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#0052cc] text-white flex items-center justify-center shadow-xs">
                                    <span class="material-symbols-rounded text-[18px]">signal_cellular_alt</span>
                                </div>
                                <span class="text-[17px] font-black text-[#0052cc] tracking-tight">Minimarket ILP</span>
                            </div>

                            <!-- Headline -->
                            <div class="mt-4">
                                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-tight">
                                    Owner Console
                                </h2>
                                <p class="text-xs text-slate-500 font-medium mt-1">
                                    Akses monitoring operasional dan laporan keuangan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Akses Pemilik Form -->
                    <div class="bg-white p-6 sm:p-9 md:p-10 flex flex-col justify-between">
                        <div>
                            <!-- Header -->
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900 tracking-tight">Akses Pemilik</h3>
                                <p class="text-[13px] text-gray-500 mt-1 font-medium">Silakan masuk untuk mengelola sistem Anda secara aman.</p>
                            </div>

                            <!-- Form -->
                            <form onsubmit="submitOwnerLogin(event);" class="space-y-4 mt-6">
                                <!-- Input 1: Nama Owner -->
                                <div>
                                    <label class="block text-[12.5px] font-bold text-gray-700 mb-1.5">Nama Owner</label>
                                    <div class="relative flex items-center">
                                        <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px] pointer-events-none">person</span>
                                        <input type="text"
                                               id="owner-username"
                                               name="username"
                                               placeholder="Masukkan username"
                                               required
                                               class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-medium">
                                    </div>
                                </div>

                                <!-- Input 2: Kata Sandi -->
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="text-[12.5px] font-bold text-gray-700">Kata Sandi</label>
                                        <button type="button" onclick="alert('Silakan hubungi administrator koperasi untuk reset sandi Owner.')" class="text-[12px] text-[#0052cc] hover:underline font-bold cursor-pointer">Lupa Sandi?</button>
                                    </div>
                                    <div class="relative flex items-center">
                                        <span class="material-symbols-rounded absolute left-3.5 text-gray-400 text-[20px] pointer-events-none">lock</span>
                                        <input type="password"
                                               id="owner-password"
                                               name="password"
                                               placeholder="••••••••"
                                               required
                                               class="w-full pl-10 pr-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[14px] text-gray-800 focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 outline-none transition-all font-medium">
                                        <button type="button" onclick="togglePassword('owner-password', 'owner-eye')" class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer p-1">
                                            <span id="owner-eye" class="material-symbols-rounded text-[20px]">visibility</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Checkbox: Ingat saya -->
                                <div class="flex items-center gap-2 pt-0.5">
                                    <input type="checkbox" id="owner-remember" checked class="w-4 h-4 text-[#0052cc] border-gray-300 rounded focus:ring-[#0052cc]/20 cursor-pointer">
                                    <label for="owner-remember" class="text-[12.5px] text-gray-600 font-medium cursor-pointer select-none">Ingat saya pada perangkat ini</label>
                                </div>

                                <!-- Error Message Alert -->
                                <div id="owner-error" class="hidden p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-bold flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[18px] text-red-500">error</span>
                                    <span id="owner-error-text"></span>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-2">
                                    <button type="submit" class="w-full h-12 bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[14px] rounded-xl shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer">
                                        <span>Masuk</span>
                                        <span class="material-symbols-rounded text-[20px]">login</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Bottom: Atau & Kembali -->
                        <div class="mt-6 pt-2">
                            <div class="relative flex py-2 items-center">
                                <div class="flex-grow border-t border-gray-200"></div>
                                <span class="flex-shrink mx-4 text-xs font-semibold text-gray-400">Atau</span>
                                <div class="flex-grow border-t border-gray-200"></div>
                            </div>
                            <button type="button" onclick="resetSelection()" class="w-full mt-1.5 inline-flex items-center justify-center gap-1.5 text-gray-500 hover:text-[#0052cc] font-bold text-[13px] transition-colors cursor-pointer py-2 px-3 rounded-lg hover:bg-gray-50">
                                <span class="material-symbols-rounded text-[18px]">arrow_back</span>
                                <span>Kembali ke Pilihan Akses</span>
                            </button>
                        </div>
                    </div>

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

        // Cashier Portal State & Authentication (Strict PostgreSQL Validation)
        window.cashierPortalLogin = function() {
            return {
                identifierInput: '',
                portalPassword: '',
                errorMsg: '',
                isSubmitting: false,
                showRecoveryModal: false,
                recoveryType: 'password', // 'password' or 'pin'

                openRecovery(type) {
                    this.recoveryType = type || 'password';
                    this.showRecoveryModal = true;
                },

                init() {
                    this.identifierInput = '';
                    this.portalPassword = '';
                    this.errorMsg = '';
                },

                async handleSubmit(e) {
                    if (e && e.preventDefault) e.preventDefault();
                    const identifier = (this.identifierInput || '').trim();
                    const password = (this.portalPassword || '').trim();

                    if (!identifier) {
                        this.errorMsg = 'Silakan masukkan NIK atau Username kasir.';
                        const idEl = document.getElementById('kasir-identifier');
                        if (idEl) idEl.focus();
                        return false;
                    }

                    if (!password) {
                        this.errorMsg = 'Silakan masukkan password akun atau PIN kasir Anda secara manual.';
                        const passEl = document.getElementById('kasir-password');
                        if (passEl) passEl.focus();
                        return false;
                    }

                    this.isSubmitting = true;
                    this.errorMsg = '';

                    try {
                        const res = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                role: 'kasir',
                                identifier: identifier,
                                password: password
                            })
                        });

                        const json = await res.json();

                        if (res.ok && json.success) {
                            const u = json.user;
                            try {
                                sessionStorage.setItem('pos_tab_role', 'kasir');
                                sessionStorage.setItem('pos_user_role', 'Kasir');
                                sessionStorage.setItem('pos_user_name', u.name || 'Kasir');
                            } catch (e) {}
                            localStorage.setItem('pos_portal_logged_in_staff_id', u.id);
                            localStorage.setItem('pos_kasir_staff_id', u.id);
                            localStorage.setItem('pos_portal_logged_in_staff_name', u.name);
                            localStorage.setItem('pos_user_name', u.name);
                            localStorage.setItem('pos_user_role', 'Kasir');
                            if (u.username) localStorage.setItem('pos_portal_logged_in_username', u.username);
                            if (u.nik) localStorage.setItem('pos_portal_logged_in_nik', u.nik);
                            if (u.branch) {
                                const primary = String(u.branch).split(',')[0].trim();
                                localStorage.setItem('pos_kasir_branch', primary);
                                localStorage.setItem('pos_kasir_location', primary);
                            }

                            window.location.href = json.redirect_url || "{{ url('/select-shift') }}";
                            return true;
                        } else {
                            this.errorMsg = json.message || 'Login gagal. NIK/Username atau kata sandi tidak cocok.';
                            this.portalPassword = '';
                            const passEl = document.getElementById('kasir-password');
                            if (passEl) passEl.focus();
                            return false;
                        }
                    } catch (err) {
                        this.errorMsg = 'Gagal menghubungi server autentikasi: ' + err.message;
                        return false;
                    } finally {
                        this.isSubmitting = false;
                    }
                }
            };
        };

        // Manager Portal Auth State & Methods
        window.managerPortalLogin = function() {
            return {
                managerUsername: '',
                managerPassword: '',
                errorMsg: '',
                isSubmitting: false,

                async handleSubmit() {
                    const uName = (this.managerUsername || '').trim();
                    const uPass = (this.managerPassword || '').trim();

                    if (!uName || !uPass) {
                        this.errorMsg = 'Silakan masukkan username dan kata sandi Manager.';
                        return;
                    }

                    this.isSubmitting = true;
                    this.errorMsg = '';

                    try {
                        const res = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                role: 'manager',
                                identifier: uName,
                                password: uPass
                            })
                        });

                        const json = await res.json();

                        if (res.ok && json.success) {
                            const u = json.user;
                            try {
                                sessionStorage.setItem('pos_tab_role', 'manager');
                                sessionStorage.setItem('pos_user_role', 'Manager');
                                sessionStorage.setItem('pos_user_name', u.name || 'Manager');
                            } catch (e) {}
                            localStorage.setItem('pos_manager_user_name', u.name || 'Manager');
                            localStorage.setItem('pos_manager_user_branch', u.branch || '');

                            window.location.href = json.redirect_url || "{{ url('/dashboard') }}";
                        } else {
                            this.errorMsg = json.message || 'Username atau kata sandi Manager salah!';
                            this.managerPassword = '';
                            const pEl = document.getElementById('manager-password');
                            if (pEl) pEl.focus();
                        }
                    } catch (err) {
                        this.errorMsg = 'Gagal menghubungi server autentikasi: ' + err.message;
                    } finally {
                        this.isSubmitting = false;
                    }
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

        function getNormalizedRole(role) {
            if (!role) return '';
            const r = role.toString().trim().toLowerCase();
            if (r === 'kasir' || r === 'cashier') return 'Kasir';
            if (r === 'manager' || r === 'manajerial' || r === 'supervisor' || r === 'spv') return 'Manager';
            if (r === 'owner' || r === 'admin' || r === 'superadmin') return 'Owner';
            return role.charAt(0).toUpperCase() + role.slice(1).toLowerCase();
        }

        function selectRole(role, instant = false) {
            const normalized = getNormalizedRole(role);
            const roleSelector = document.getElementById('role-selector');
            const targetForm = roleForms[normalized] || roleForms[role];
            const subtitle = document.getElementById('portal-subtitle');
            const header = document.getElementById('portal-header');
            const mainEl = document.querySelector('main');
            if (!targetForm) return;

            currentActiveForm = targetForm;

            if (subtitle && (roleSubtitles[normalized] || roleSubtitles[role])) {
                subtitle.innerText = roleSubtitles[normalized] || roleSubtitles[role];
            }

            if (normalized === 'Owner') {
                if (header) header.classList.add('hidden');
                if (mainEl) {
                    mainEl.classList.remove('max-w-4xl');
                    mainEl.classList.add('max-w-5xl');
                }
            } else {
                if (header) header.classList.remove('hidden');
                if (mainEl) {
                    mainEl.classList.remove('max-w-5xl');
                    mainEl.classList.add('max-w-4xl');
                }
            }

            if (instant) {
                roleSelector.classList.add('hidden', 'opacity-0', 'pointer-events-none');
                Object.values(roleForms).forEach(f => f.classList.add('hidden', 'opacity-0', 'translate-y-4'));
                targetForm.classList.remove('hidden');
                targetForm.offsetHeight;
                targetForm.classList.remove('opacity-0', 'translate-y-4');
                focusFirstInput(normalized);
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
                    focusFirstInput(normalized);
                }, 50);
            }, 250);
        }

        function resetSelection() {
            const roleSelector = document.getElementById('role-selector');
            const subtitle = document.getElementById('portal-subtitle');
            const header = document.getElementById('portal-header');
            const mainEl = document.querySelector('main');
            if (!currentActiveForm) return;

            if (subtitle) {
                subtitle.innerText = 'Silakan pilih peran Anda untuk memulai sesi';
            }

            if (header) header.classList.remove('hidden');
            if (mainEl) {
                mainEl.classList.remove('max-w-5xl');
                mainEl.classList.add('max-w-4xl');
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
        }

        function focusFirstInput(role) {
            const normalized = getNormalizedRole(role);
            if (normalized === 'Kasir') {
                const el = document.getElementById('kasir-identifier') || document.getElementById('kasir-username');
                if (el) el.focus();
            } else if (normalized === 'Manager') {
                const el = document.getElementById('manager-username');
                if (el) el.focus();
            } else if (normalized === 'Owner') {
                const el = document.getElementById('owner-username');
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

        async function submitOwnerLogin(e) {
            if (e && e.preventDefault) e.preventDefault();
            const usernameInput = document.getElementById('owner-username');
            const passwordInput = document.getElementById('owner-password');
            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value.trim() : '';
            const errEl = document.getElementById('owner-error');
            const errText = document.getElementById('owner-error-text');

            if (!username || !password) {
                if (errEl && errText) {
                    errText.innerText = 'Silakan masukkan username dan password Owner.';
                    errEl.classList.remove('hidden');
                }
                return false;
            }

            if (errEl) errEl.classList.add('hidden');

            try {
                const res = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        role: 'owner',
                        identifier: username,
                        password: password
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    const u = data.user;
                    try {
                        sessionStorage.setItem('pos_tab_role', 'owner');
                        sessionStorage.setItem('pos_user_role', u.role || 'Owner');
                        sessionStorage.setItem('pos_user_name', u.name || 'Owner');
                    } catch (e) {}
                    localStorage.setItem('pos_owner_user_name', u.name || 'Owner');
                    localStorage.setItem('pos_owner_user_branch', u.branch || '');
                    window.location.href = data.redirect_url || "{{ url('/owner') }}";
                    return false;
                } else {
                    if (errEl && errText) {
                        errText.innerText = data.message || 'Username atau kata sandi Owner salah!';
                        errEl.classList.remove('hidden');
                    } else {
                        alert(data.message || 'Username atau kata sandi Owner salah!');
                    }
                    if (passwordInput) passwordInput.focus();
                }
            } catch (err) {
                if (errEl && errText) {
                    errText.innerText = 'Gagal menghubungi server autentikasi: ' + err.message;
                    errEl.classList.remove('hidden');
                } else {
                    alert('Gagal login: ' + err.message);
                }
            }
            return false;
        }

        async function submitOwnerPin() {
            const pinInputs = document.querySelectorAll('.pin-dot');
            let pin = '';
            pinInputs.forEach(input => pin += input.value);

            if (pin.length === 6) {
                try {
                    const res = await fetch('/api/auth/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ pin: pin, role: 'owner' })
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        try {
                            sessionStorage.setItem('pos_tab_role', 'owner');
                            sessionStorage.setItem('pos_user_role', data.user.role || 'Owner');
                            sessionStorage.setItem('pos_user_name', data.user.name || 'Owner');
                        } catch (e) {}
                        localStorage.setItem('pos_owner_user_name', data.user.name || 'Owner');
                        localStorage.setItem('pos_owner_user_branch', data.user.branch || '');
                        window.location.href = data.redirect_url || "{{ url('/owner') }}";
                        return;
                    } else {
                        alert(data.message || 'PIN Otorisasi Owner tidak valid! Silakan masukkan PIN 6 digit yang benar.');
                        pinInputs.forEach(input => input.value = '');
                        if (pinInputs[0]) pinInputs[0].focus();
                    }
                } catch(e) {
                    alert('Gagal menghubungi server autentikasi: ' + e.message);
                }
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
        window.addEventListener('DOMContentLoaded', function() {
            @if(!empty($selectedRole))
                selectRole('{{ $selectedRole }}', true);
            @endif
        });
    </script>
</body>
</html>
