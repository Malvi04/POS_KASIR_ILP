@extends('layouts.app')

@section('title', 'Settings - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    autoPrintStruk: true,
    modeCepat: true,
    selectedPrinter: 'EPSON TM-T82II Receipt (Default)',
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    // Security & User Profile State
    hasPin: @js(!empty(auth()->user()?->pin)),
    userProfile: {
        name: @js(auth()->user()?->name ?? 'Kasir'),
        username: @js(auth()->user()?->username ?? '-'),
        nik: @js(auth()->user()?->nik ?? '-'),
        role: @js(auth()->user()?->role ?? 'Kasir'),
        branch: @js(auth()->user()?->branch ?? '-'),
    },

    // Change Password Form State
    pwdForm: {
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
        showCurrent: false,
        showNew: false,
        showConfirm: false,
        isSubmitting: false,
        errorMessage: '',
        successMessage: ''
    },

    // Change PIN Form State
    pinForm: {
        currentPin: '',
        newPin: '',
        confirmPin: '',
        showCurrent: false,
        showNew: false,
        showConfirm: false,
        isSubmitting: false,
        errorMessage: '',
        successMessage: ''
    },

    initSettings() {
        const stored = localStorage.getItem('pos_ilp_terminal_prefs_v1');
        if (stored) {
            try {
                const prefs = JSON.parse(stored);
                if (prefs.autoPrintStruk !== undefined) this.autoPrintStruk = prefs.autoPrintStruk;
                if (prefs.modeCepat !== undefined) this.modeCepat = prefs.modeCepat;
                if (prefs.selectedPrinter) this.selectedPrinter = prefs.selectedPrinter;
            } catch(e) {}
        }
        this.loadUserProfile();
    },

    async loadUserProfile() {
        try {
            const res = await fetch('/api/auth/me', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.authenticated && json.user) {
                    this.hasPin = Boolean(json.user.has_pin);
                    if (json.user.name) this.userProfile.name = json.user.name;
                    if (json.user.username) this.userProfile.username = json.user.username;
                    if (json.user.nik) this.userProfile.nik = json.user.nik;
                    if (json.user.branch) this.userProfile.branch = json.user.branch;
                }
            }
        } catch(e) {
            console.warn('Failed loading user profile', e);
        }
    },

    async submitChangePassword() {
        this.pwdForm.errorMessage = '';
        this.pwdForm.successMessage = '';

        if (!this.pwdForm.currentPassword) {
            this.pwdForm.errorMessage = 'Password saat ini wajib diisi.';
            return;
        }
        if (!this.pwdForm.newPassword) {
            this.pwdForm.errorMessage = 'Password baru wajib diisi.';
            return;
        }
        if (this.pwdForm.newPassword.length < 4) {
            this.pwdForm.errorMessage = 'Password baru minimal 4 karakter.';
            return;
        }
        if (this.pwdForm.newPassword !== this.pwdForm.confirmPassword) {
            this.pwdForm.errorMessage = 'Konfirmasi password baru tidak cocok.';
            return;
        }

        this.pwdForm.isSubmitting = true;
        try {
            const res = await fetch('/api/auth/password', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    current_password: this.pwdForm.currentPassword,
                    password: this.pwdForm.newPassword,
                    password_confirmation: this.pwdForm.confirmPassword
                })
            });

            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                this.pwdForm.currentPassword = '';
                this.pwdForm.newPassword = '';
                this.pwdForm.confirmPassword = '';
                this.pwdForm.successMessage = data.message || 'Password berhasil diperbarui!';
                this.triggerToast(data.message || 'Password berhasil diperbarui!', 'success');
            } else {
                this.pwdForm.errorMessage = data.message || 'Gagal mengubah password.';
                this.triggerToast(this.pwdForm.errorMessage, 'error');
            }
        } catch (e) {
            this.pwdForm.errorMessage = 'Terjadi kesalahan jaringan atau server.';
            this.triggerToast(this.pwdForm.errorMessage, 'error');
        } finally {
            this.pwdForm.isSubmitting = false;
        }
    },

    async submitChangePin() {
        this.pinForm.errorMessage = '';
        this.pinForm.successMessage = '';

        if (this.hasPin && !this.pinForm.currentPin) {
            this.pinForm.errorMessage = 'PIN saat ini / verifikasi akun wajib diisi.';
            return;
        }
        if (!this.pinForm.newPin) {
            this.pinForm.errorMessage = 'PIN baru 6 digit wajib diisi.';
            return;
        }
        if (!/^[0-9]{6}$/.test(this.pinForm.newPin)) {
            this.pinForm.errorMessage = 'PIN harus tepat 6 digit angka (0-9).';
            return;
        }
        if (this.pinForm.newPin !== this.pinForm.confirmPin) {
            this.pinForm.errorMessage = 'Konfirmasi PIN baru tidak cocok.';
            return;
        }

        this.pinForm.isSubmitting = true;
        try {
            const res = await fetch('/api/auth/pin', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    current_pin: this.pinForm.currentPin,
                    pin: this.pinForm.newPin,
                    pin_confirmation: this.pinForm.confirmPin
                })
            });

            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                this.hasPin = true;
                this.pinForm.currentPin = '';
                this.pinForm.newPin = '';
                this.pinForm.confirmPin = '';
                this.pinForm.successMessage = data.message || 'PIN kasir berhasil diperbarui!';
                this.triggerToast(data.message || 'PIN kasir berhasil diperbarui!', 'success');
            } else {
                this.pinForm.errorMessage = data.message || 'Gagal mengubah PIN.';
                this.triggerToast(this.pinForm.errorMessage, 'error');
            }
        } catch (e) {
            this.pinForm.errorMessage = 'Terjadi kesalahan jaringan atau server.';
            this.triggerToast(this.pinForm.errorMessage, 'error');
        } finally {
            this.pinForm.isSubmitting = false;
        }
    },

    savePrefs() {
        localStorage.setItem('pos_ilp_terminal_prefs_v1', JSON.stringify({
            autoPrintStruk: this.autoPrintStruk,
            modeCepat: this.modeCepat,
            selectedPrinter: this.selectedPrinter
        }));
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); initSettings();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full overflow-hidden">
<!-- Sidebar -->
<x-cashier-sidebar active="settings" />

<!-- Main Content -->
<main class="flex-1 flex flex-col min-w-0 bg-white relative h-full">
    <!-- Header -->
    <x-cashier-header title="Pengaturan" />

    <!-- Scrollable Content -->
    <div class="flex-1 overflow-auto p-8 bg-slate-50">
        <div class="max-w-[1200px] mx-auto space-y-6">

            <!-- Top Section: Profile & System Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- User & Active Session Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col justify-between relative overflow-hidden">
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-50 text-[#0052cc] border border-blue-200 uppercase">PETUGAS AKTIF</span>
                            <h3 class="text-xl font-black text-gray-900 mt-1.5" x-text="activeSession?.cashierName || userProfile.name || '-'">-</h3>
                            <p class="text-gray-500 text-xs font-mono mt-0.5" x-text="'NIK: ' + (activeSession?.cashierNik || userProfile.nik || '-')">NIK: -</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="activeSession?.terminalLabel || '-'">-</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-5">
                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                            <p class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">ID SESI</p>
                            <p class="text-blue-700 font-mono font-bold text-xs" x-text="activeSession?.sessionId || '-'">-</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 border border-green-100">
                            <p class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">UANG AWAL</p>
                            <p class="text-green-700 font-mono font-bold text-sm" x-text="formatRupiah(activeSession?.openingCash || 0)">Rp 0</p>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <a href="{{ url('/kasir/sesi') }}" class="flex-1 flex items-center justify-center gap-2 bg-[#0052cc] text-white font-bold text-xs py-2.5 rounded-lg hover:bg-blue-800 transition-colors">
                            <span class="material-symbols-rounded text-[18px]">swap_horiz</span>
                            Sesi Kasir
                        </a>
                        <button type="button" @click="$dispatch('open-closing-wizard')" class="flex-1 flex items-center justify-center gap-2 border border-blue-600 text-blue-600 font-bold text-xs py-2.5 rounded-lg hover:bg-blue-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">logout</span>
                            Tutup Sesi
                        </button>
                    </div>
                </div>

                <!-- System Info Card -->
                <div class="bg-blue-600 rounded-xl p-6 text-white relative overflow-hidden flex flex-col justify-between">
                    <div class="relative z-10">
                        <span class="inline-block bg-blue-500 text-blue-50 text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3">
                            INFORMASI TERMINAL
                        </span>
                        <h3 class="text-xl font-bold">Kopkar ILP POS v2.5</h3>

                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-2">
                                <span class="text-blue-100">Lokasi</span>
                                <span class="font-bold" x-text="activeSession?.storeLocation || userProfile.branch || localStorage.getItem('pos_kasir_branch') || '-'">-</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-blue-500/50 pb-2">
                                <span class="text-blue-100">Terminal</span>
                                <span class="font-bold font-mono" x-text="activeSession?.terminalLabel || '-'">-</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-blue-100">Tanggal</span>
                                <span class="font-bold" x-text="activeSession?.businessDayDate || businessDayDate">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Keamanan Akun: Ganti Password & PIN Kasir -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">shield</span>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Keamanan Kredensial Kasir</h2>
                            <p class="text-xs text-gray-500">Kelola kata sandi akun portal dan PIN 6-digit untuk otorisasi cepat</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <template x-if="hasPin">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>PIN Aktif (6 Digit)</span>
                            </span>
                        </template>
                        <template x-if="!hasPin">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>PIN Belum Dibuat</span>
                            </span>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <!-- CARD 1: GANTI PASSWORD AKUN -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs flex flex-col justify-between space-y-5">
                        <div class="space-y-4">
                            <!-- Card Header -->
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[20px]">key</span>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-sm">Ganti Password Akun</h3>
                                        <p class="text-[11px] text-gray-500">Kata sandi masuk ke portal kasir</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase">Kredensial</span>
                            </div>

                            <!-- Alert Messages -->
                            <template x-if="pwdForm.errorMessage">
                                <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-semibold flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[18px] shrink-0">error</span>
                                    <span x-text="pwdForm.errorMessage"></span>
                                </div>
                            </template>
                            <template x-if="pwdForm.successMessage">
                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-semibold flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[18px] shrink-0">check_circle</span>
                                    <span x-text="pwdForm.successMessage"></span>
                                </div>
                            </template>

                            <!-- Password Fields -->
                            <div class="space-y-3.5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">Password Saat Ini</label>
                                    <div class="relative flex items-center">
                                        <input :type="pwdForm.showCurrent ? 'text' : 'password'"
                                               x-model="pwdForm.currentPassword"
                                               placeholder="Masukkan kata sandi saat ini..."
                                               class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13px] text-gray-800 bg-white">
                                        <button type="button"
                                                @click="pwdForm.showCurrent = !pwdForm.showCurrent"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="pwdForm.showCurrent ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">Password Baru</label>
                                    <div class="relative flex items-center">
                                        <input :type="pwdForm.showNew ? 'text' : 'password'"
                                               x-model="pwdForm.newPassword"
                                               placeholder="Minimal 4 karakter kombinasi..."
                                               class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13px] text-gray-800 bg-white">
                                        <button type="button"
                                                @click="pwdForm.showNew = !pwdForm.showNew"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="pwdForm.showNew ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">Konfirmasi Password Baru</label>
                                    <div class="relative flex items-center">
                                        <input :type="pwdForm.showConfirm ? 'text' : 'password'"
                                               x-model="pwdForm.confirmPassword"
                                               placeholder="Ketik ulang password baru..."
                                               class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13px] text-gray-800 bg-white">
                                        <button type="button"
                                                @click="pwdForm.showConfirm = !pwdForm.showConfirm"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="pwdForm.showConfirm ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="button"
                                    @click="submitChangePassword()"
                                    :disabled="pwdForm.isSubmitting"
                                    class="w-full py-2.5 px-4 rounded-xl bg-[#0052cc] hover:bg-blue-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs disabled:opacity-50">
                                <span class="material-symbols-rounded text-[18px]">lock_reset</span>
                                <span x-text="pwdForm.isSubmitting ? 'Menyimpan Password...' : 'Simpan Password Baru'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- CARD 2: GANTI / SET PIN 6 DIGIT -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs flex flex-col justify-between space-y-5">
                        <div class="space-y-4">
                            <!-- Card Header -->
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[20px]">dialpad</span>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-sm">Ganti / Atur PIN Kasir</h3>
                                        <p class="text-[11px] text-gray-500">6 digit angka untuk login cepat & otorisasi kasir</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 uppercase">PIN 6-DIGIT</span>
                            </div>

                            <!-- Alert Messages -->
                            <template x-if="pinForm.errorMessage">
                                <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-semibold flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[18px] shrink-0">error</span>
                                    <span x-text="pinForm.errorMessage"></span>
                                </div>
                            </template>
                            <template x-if="pinForm.successMessage">
                                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-semibold flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[18px] shrink-0">check_circle</span>
                                    <span x-text="pinForm.successMessage"></span>
                                </div>
                            </template>

                            <!-- PIN Fields -->
                            <div class="space-y-3.5">
                                <template x-if="hasPin">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">PIN Saat Ini (atau Password Akun)</label>
                                        <div class="relative flex items-center">
                                            <input :type="pinForm.showCurrent ? 'text' : 'password'"
                                                   x-model="pinForm.currentPin"
                                                   placeholder="Masukkan 6-digit PIN saat ini..."
                                                   class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 focus:outline-none text-[13px] text-gray-800 bg-white">
                                            <button type="button"
                                                    @click="pinForm.showCurrent = !pinForm.showCurrent"
                                                    class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]" x-text="pinForm.showCurrent ? 'visibility_off' : 'visibility'"></span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">PIN Baru (Tepat 6 Digit Angka)</label>
                                    <div class="relative flex items-center">
                                        <input :type="pinForm.showNew ? 'text' : 'password'"
                                               x-model="pinForm.newPin"
                                               maxlength="6"
                                               inputmode="numeric"
                                               pattern="[0-9]*"
                                               placeholder="Contoh: 123456"
                                               class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 focus:outline-none text-[15px] font-mono font-bold tracking-widest text-gray-800 bg-white">
                                        <button type="button"
                                                @click="pinForm.showNew = !pinForm.showNew"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="pinForm.showNew ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                    <span class="text-[10.5px] text-gray-500">Hanya angka (0-9). Digunakan saat login cepat portal kasir.</span>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11.5px] font-bold text-gray-700 uppercase tracking-wider">Konfirmasi PIN Baru</label>
                                    <div class="relative flex items-center">
                                        <input :type="pinForm.showConfirm ? 'text' : 'password'"
                                               x-model="pinForm.confirmPin"
                                               maxlength="6"
                                               inputmode="numeric"
                                               pattern="[0-9]*"
                                               placeholder="Ketik ulang 6-digit PIN..."
                                               class="w-full px-3.5 py-2.5 pr-10 rounded-xl border border-gray-300 focus:border-purple-600 focus:ring-1 focus:ring-purple-600 focus:outline-none text-[15px] font-mono font-bold tracking-widest text-gray-800 bg-white">
                                        <button type="button"
                                                @click="pinForm.showConfirm = !pinForm.showConfirm"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="pinForm.showConfirm ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="button"
                                    @click="submitChangePin()"
                                    :disabled="pinForm.isSubmitting"
                                    class="w-full py-2.5 px-4 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer shadow-xs disabled:opacity-50">
                                <span class="material-symbols-rounded text-[18px]">dialpad</span>
                                <span x-text="pinForm.isSubmitting ? 'Menyimpan PIN...' : (hasPin ? 'Perbarui PIN Kasir' : 'Aktifkan PIN Kasir')"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Section: Hardware & Prefs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Printer Config -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex justify-between items-center mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                <span class="material-symbols-rounded">print</span>
                            </div>
                            <h3 class="font-bold text-gray-800 text-sm">Konfigurasi Printer</h3>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 rounded-full border border-green-200 text-[11px] font-bold">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                            Terhubung
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-[11px] font-bold text-gray-700 mb-2">Printer Struk</label>
                        <div class="relative">
                            <select class="w-full pl-4 pr-10 py-2.5 appearance-none rounded-lg bg-slate-50 border border-gray-200 text-gray-700 text-sm font-medium outline-none cursor-pointer">
                                <option>EPSON TM-T82II Receipt (Default)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <button type="button" @click="window.print()" class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-xs py-2.5 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">print</span>
                            <span>Test Print</span>
                        </button>
                        <button type="button" @click="triggerToast('Koneksi printer dan service QZ Tray berhasil direset!')" class="flex-1 flex items-center justify-center gap-2 border border-gray-200 bg-white font-bold text-gray-700 text-xs py-2.5 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">refresh</span>
                            <span>Reset QZ</span>
                        </button>
                    </div>
                </div>

                <!-- Terminal Prefs -->
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center">
                            <span class="material-symbols-rounded">tune</span>
                        </div>
                        <h3 class="font-bold text-gray-800 text-sm">Preferensi Terminal</h3>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3.5 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-rounded text-gray-400">receipt_long</span>
                                <p class="font-bold text-gray-800 text-sm">Auto-print Struk</p>
                            </div>
                            <div @click="autoPrintStruk = !autoPrintStruk; savePrefs(); triggerToast('Auto-print struk: ' + (autoPrintStruk ? 'Aktif' : 'Nonaktif'))"
                                 :class="autoPrintStruk ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                 class="w-11 h-6 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div :class="autoPrintStruk ? 'right-0.5' : 'left-0.5'" class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3.5 bg-slate-50/50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-rounded text-gray-400">bolt</span>
                                <p class="font-bold text-gray-800 text-sm">Mode Cepat (F2 Focus)</p>
                            </div>
                            <div @click="modeCepat = !modeCepat; savePrefs(); triggerToast('Mode Cepat: ' + (modeCepat ? 'Aktif' : 'Nonaktif'))"
                                 :class="modeCepat ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                 class="w-11 h-6 rounded-full relative cursor-pointer flex items-center px-0.5 transition-colors">
                                <div :class="modeCepat ? 'right-0.5' : 'left-0.5'" class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Data & Sync -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">sync_alt</span>
                        </div>
                        <h3 class="font-bold text-gray-800 text-sm">Sinkronisasi Data</h3>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-gray-500 uppercase">DATABASE</p>
                            <p class="font-mono text-gray-800 font-bold text-xs">v14.2-local (OK)</p>
                        </div>
                        <button type="button"
                                @click="initOpening(); initSettings(); triggerToast('Semua data lokal berhasil disinkronkan!')"
                                class="bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs py-2.5 px-5 rounded-lg shadow-sm flex items-center gap-2 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">sync</span>
                            <span>Sinkronisasi</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Toast Notification -->
            <div x-show="showToast"
                 x-transition.opacity
                 style="display: none;"
                 class="fixed bottom-6 right-6 z-50 px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border text-xs font-semibold text-white"
                 :class="toastType === 'error' ? 'bg-rose-900 border-rose-800' : 'bg-gray-900 border-gray-800'">
                <span class="material-symbols-rounded text-[20px]" :class="toastType === 'error' ? 'text-rose-400' : 'text-emerald-400'">
                    <span x-text="toastType === 'error' ? 'error' : 'check_circle'"></span>
                </span>
                <span x-text="toastMessage"></span>
            </div>

        </div>
    </div>
</main>
</div>
@endsection
