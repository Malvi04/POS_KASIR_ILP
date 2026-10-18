@extends('layouts.app')

@section('title', 'Pengaturan Akun & Preferensi - ILP POS Manager')

@section('content')
<div x-data="{
    activeTab: 'profil',
    toastMessage: '',
    showToast: false,
    lastSaved: 'Hari ini, 09:15 WIB',

    profileName: 'Andi Darmawan, S.E.',
    profileEmail: 'andi.darmawan@ilp-pos.co.id',
    profilePhone: '0812-9988-7766',
    profileNik: 'MGR-ILP-2021-004',
    profileRole: 'Account & Store Manager',
    profileBranch: 'Minimarket Pusat - Koperasi Pekerja ILP',
    profileTimezone: 'WIB (UTC+07:00) - Jakarta',
    profileBio: 'Penanggung jawab operasional harian, otorisasi persediaan gudang, kemitraan konsinyasi UMKM, dan audit laporan penjualan cabang.',
    avatarInitials: 'AD',
    avatarFileName: '',

    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
    showCurrentPass: false,
    showNewPass: false,
    currentPin: '••••••',
    newPin: '882910',
    showPinText: false,
    requirePinVoid: true,
    requirePinDiscount: true,
    twoFactorEnabled: true,

    sessions: [
        {
            id: 1,
            device: 'Windows 11 Pro • Google Chrome 125',
            location: 'PC Backoffice Manager • IP 192.168.1.10 (Jakarta)',
            lastActive: 'Sedang Aktif Sekarang',
            isCurrent: true,
            icon: 'desktop_windows'
        },
        {
            id: 2,
            device: 'iPadOS 17.4 • Safari Mobile',
            location: 'Tablet Audit Opname Gudang • IP 192.168.1.45',
            lastActive: 'Aktif 18 menit yang lalu',
            isCurrent: false,
            icon: 'tablet_mac'
        },
        {
            id: 3,
            device: 'Android 14 • Aplikasi Mobile ILP Manager',
            location: 'Samsung Galaxy S23 • IP 114.122.89.12',
            lastActive: 'Kemarin, 21:40 WIB',
            isCurrent: false,
            icon: 'smartphone'
        }
    ],

    notifDailyReport: true,
    notifLowStock: true,
    notifReturUmkm: true,
    notifAnomalyVoid: true,
    dailyTargetAmount: '25.000.000',
    defaultExportFormat: 'xlsx',
    defaultPaperSize: 'A4 Portrait (210 x 297 mm)',
    includeDigitalSign: true,
    currencyCode: 'IDR (Rp) - Rupiah Indonesia',
    ppnRate: 11,
    taxCalculationMode: 'inclusive',
    cashRounding: '100',

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    },

    revokeSession(id, deviceName) {
        this.sessions = this.sessions.filter(s => s.id !== id);
        this.triggerToast('Akses sesi (' + deviceName + ') berhasil diputuskan.');
    },

    revokeAllOtherSessions() {
        this.sessions = this.sessions.filter(s => s.isCurrent);
        this.triggerToast('Semua perangkat lain berhasil dikeluarkan dari akun Manager.');
    },

    resetForm() {
        if (this.activeTab === 'profil') {
            this.profileName = 'Andi Darmawan, S.E.';
            this.profileEmail = 'andi.darmawan@ilp-pos.co.id';
            this.profilePhone = '0812-9988-7766';
            this.profileTimezone = 'WIB (UTC+07:00) - Jakarta';
        } else if (this.activeTab === 'keamanan') {
            this.currentPassword = '';
            this.newPassword = '';
            this.confirmPassword = '';
            this.newPin = '882910';
            this.requirePinVoid = true;
            this.requirePinDiscount = true;
        } else {
            this.notifDailyReport = true;
            this.notifLowStock = true;
            this.notifReturUmkm = true;
            this.defaultExportFormat = 'xlsx';
            this.ppnRate = 11;
            this.taxCalculationMode = 'inclusive';
            this.cashRounding = '100';
        }
        this.triggerToast('Perubahan pada tab ini telah dikembalikan ke pengaturan awal.');
    },

    saveSettings() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        this.lastSaved = 'Baru saja, ' + hh + ':' + mm + ' WIB';
        this.currentPassword = '';
        this.newPassword = '';
        this.confirmPassword = '';
        this.triggerToast('Pengaturan Manager berhasil disimpan dan disinkronkan ke sistem POS.');
    }
}" class="flex h-screen w-full bg-[#f8fafc] font-sans text-slate-800 overflow-hidden">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="settings" />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">

        <!-- Top Navbar -->
        <header class="h-[64px] bg-white border-b border-slate-200/80 px-8 flex items-center justify-between shrink-0 z-10">
            <div class="flex items-center gap-4">
                <h2 class="text-[18px] font-black text-[#0f172a] tracking-tight">Pengaturan Sistem & Akun</h2>
                <span class="h-5 w-px bg-slate-200 hidden sm:inline-block"></span>
                <span class="hidden md:inline-flex items-center gap-1.5 bg-[#f1f5f9] text-slate-600 text-[11px] font-bold px-2.5 py-1 rounded-lg">
                    <span class="material-symbols-rounded text-[15px] text-[#0052cc]">verified_user</span>
                    <span>Otorisasi Level Manager</span>
                </span>
            </div>

            <div class="flex items-center gap-3">
                <button @click="activeTab = 'preferensi'"
                        class="relative w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer"
                        title="Notifikasi Sistem">
                    <span class="material-symbols-rounded text-[21px]">notifications</span>
                    <span class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-orange-500 ring-2 ring-white"></span>
                </button>
                <button @click="triggerToast('Pusat Bantuan Dokumentasi ILP POS Manager v4.2')"
                        class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer"
                        title="Bantuan">
                    <span class="material-symbols-rounded text-[21px]">help_outline</span>
                </button>
                <a href="{{ url('/login-manager') }}"
                   class="ml-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200/80 px-3.5 py-1.5 rounded-xl text-[12px] font-extrabold inline-flex items-center gap-1.5 transition-colors">
                    <span class="material-symbols-rounded text-[16px]">logout</span>
                    <span class="hidden sm:inline">Keluar</span>
                </a>
            </div>
        </header>

        <!-- Scrollable Body -->
        <main class="flex-1 overflow-y-auto px-6 lg:px-8 py-6">
            <div class="max-w-[1440px] mx-auto space-y-6">

                <!-- Page Breadcrumb & Title Header -->
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-extrabold text-slate-400 tracking-wider uppercase mb-1.5">
                            <span>OPERASIONAL CABANG</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span>KONFIGURASI SISTEM</span>
                            <span class="material-symbols-rounded text-[13px]">chevron_right</span>
                            <span class="text-[#0052cc]">PENGATURAN MANAGER</span>
                        </div>
                        <h1 class="text-[25px] lg:text-[27px] font-black text-[#0f172a] tracking-tight leading-tight">
                            Pengaturan Akun & Preferensi POS
                        </h1>
                        <p class="text-[13px] text-slate-500 font-medium mt-1 max-w-2xl leading-relaxed">
                            Kelola identitas profil Account Manager, kredensial keamanan & PIN otorisasi kasir, serta parameter laporan, pajak, dan notifikasi operasional cabang.
                        </p>
                    </div>

                    <!-- Sync Status Card -->
                    <div class="bg-[#f4f7fe] border border-[#dce5fc] rounded-xl px-4 py-2.5 flex items-center gap-3 shrink-0 self-start shadow-2xs">
                        <div class="w-9 h-9 rounded-lg bg-[#e0eaff] text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[20px]">cloud_done</span>
                        </div>
                        <div class="flex flex-col leading-tight">
                            <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">KONFIGURASI CLOUD ERP</span>
                            <span class="text-[12px] font-extrabold text-[#16a34a] flex items-center gap-1.5 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#16a34a]"></span>
                                <span x-text="'Tersimpan • ' + lastSaved">Tersimpan • Hari ini, 09:15 WIB</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Sub-Navigation Tabs Bar -->
                <div class="bg-white border border-slate-200/80 rounded-2xl p-2 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tab 1: Profil & Akun -->
                        <button type="button"
                                @click="activeTab = 'profil'"
                                :class="activeTab === 'profil' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-transparent hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">badge</span>
                            <span>Profil & Akun</span>
                        </button>

                        <!-- Tab 2: Keamanan & Akses -->
                        <button type="button"
                                @click="activeTab = 'keamanan'"
                                :class="activeTab === 'keamanan' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-transparent hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">shield_lock</span>
                            <span>Keamanan & Akses</span>
                            <span :class="activeTab === 'keamanan' ? 'bg-white text-[#0047b3]' : 'bg-emerald-50 text-[#15803d] border border-emerald-200'"
                                  class="text-[10px] font-extrabold px-2 py-0.5 rounded-full">PIN Aktif</span>
                        </button>

                        <!-- Tab 3: Preferensi POS & Laporan -->
                        <button type="button"
                                @click="activeTab = 'preferensi'"
                                :class="activeTab === 'preferensi' ? 'bg-[#0047b3] text-white shadow-xs' : 'bg-transparent hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2.5 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">tune</span>
                            <span>Preferensi POS & Laporan</span>
                        </button>
                    </div>

                    <!-- Quick Branch Pill -->
                    <div class="hidden xl:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200/70 text-[11.5px] font-bold text-slate-600">
                        <span class="material-symbols-rounded text-[16px] text-[#0052cc]">storefront</span>
                        <span>Cabang: <strong class="font-extrabold text-slate-900">Minimarket Pusat (JKT-01)</strong></span>
                    </div>
                </div>

                <!-- Main Settings Card Container -->
                <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">

                    <!-- ========================================================= -->
                    <!-- TAB 1: PROFIL & AKUN                                      -->
                    <!-- ========================================================= -->
                    <div x-show="activeTab === 'profil'" class="divide-y divide-slate-100">

                        <!-- Section 1.1: Foto Profil & Identitas Manager -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">account_circle</span>
                                    <span>IDENTITAS VISUAL</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Foto Profil & Kartu Otorisasi</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Foto dan identitas ini ditampilkan pada log persetujuan retur, berita acara stock opname (BAST), dan header laporan cabang.
                                </p>
                            </div>

                            <div class="lg:col-span-8">
                                <div class="bg-[#f8fafc] border border-slate-200/80 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#0047b3] to-[#0052cc] text-white font-black text-[22px] flex items-center justify-center shadow-sm shrink-0 ring-4 ring-blue-100"
                                             x-text="avatarInitials">
                                            AD
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-[16px] font-black text-[#0f172a]" x-text="profileName">Andi Darmawan, S.E.</h4>
                                                <span class="bg-[#dbeafe] text-[#1e40af] text-[10px] font-extrabold px-2.5 py-0.5 rounded-md uppercase tracking-wider">
                                                    HEAD MANAGER
                                                </span>
                                            </div>
                                            <p class="text-[12px] text-slate-500 font-mono font-semibold mt-0.5" x-text="'ID Otorisasi: #' + profileNik">ID Otorisasi: #MGR-ILP-2021-004</p>
                                            <p x-show="avatarFileName" class="text-[11px] font-bold text-[#16a34a] mt-1" x-text="'File dipilih: ' + avatarFileName"></p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2.5 shrink-0">
                                        <label class="bg-white hover:bg-slate-50 text-[#0052cc] border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12px] font-extrabold inline-flex items-center gap-2 shadow-2xs transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[17px]">upload</span>
                                            <span>Ubah Foto</span>
                                            <input type="file" accept="image/*" class="hidden"
                                                   @change="if($event.target.files[0]) { avatarFileName = $event.target.files[0].name; triggerToast('Foto profil (' + avatarFileName + ') siap disimpan.'); }">
                                        </label>
                                        <button type="button"
                                                @click="avatarFileName = ''; triggerToast('Foto profil dikembalikan ke avatar inisial default.')"
                                                class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3.5 py-2.5 rounded-xl text-[12px] font-bold transition-colors cursor-pointer">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 1.2: Data Diri & Kontak Resmi -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">contact_mail</span>
                                    <span>DATA PERSONAL</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Informasi Akun & Kontak</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Pastikan email dan nomor WhatsApp aktif untuk menerima pengiriman rekapitulasi penjualan harian dan kode OTP persetujuan.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            NAMA LENGKAP & GELAR <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">person</span>
                                            <input type="text" x-model="profileName"
                                                   class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-bold text-[#0f172a] focus:outline-none transition-all">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            NOMOR INDUK / ID MANAGER
                                        </label>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">tag</span>
                                            <input type="text" x-model="profileNik" readonly
                                                   class="w-full bg-slate-100 border border-slate-200/80 rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-mono font-extrabold text-slate-500 cursor-not-allowed">
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider">
                                                EMAIL KORPORAT <span class="text-red-500">*</span>
                                            </label>
                                            <span class="text-[10px] font-extrabold text-[#16a34a] inline-flex items-center gap-1">
                                                <span class="material-symbols-rounded text-[13px]">verified</span>
                                                Terverifikasi
                                            </span>
                                        </div>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">mail</span>
                                            <input type="email" x-model="profileEmail"
                                                   class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-semibold text-[#0f172a] focus:outline-none transition-all">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            NOMOR TELEPON / WHATSAPP <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-[#16a34a] pointer-events-none">call</span>
                                            <input type="text" x-model="profilePhone"
                                                   class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-mono font-bold text-[#0f172a] focus:outline-none transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 1.3: Jabatan / Role & Penempatan Cabang -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">work</span>
                                    <span>WEWENANG JABATAN</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Jabatan, Role & Unit Cabang</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Hak akses jabatan diatur terpusat oleh sistem ERP Koperasi ILP untuk menjamin keamanan audit.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            JABATAN / ROLE SISTEM
                                        </label>
                                        <div class="bg-[#f1f5f9] border border-slate-200/80 rounded-xl px-3.5 py-2.5 flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">admin_panel_settings</span>
                                                <span class="text-[13px] font-extrabold text-[#0f172a] truncate" x-text="profileRole">Account & Store Manager</span>
                                            </div>
                                            <span class="bg-[#dcfce7] text-[#15803d] text-[10px] font-extrabold px-2 py-0.5 rounded shrink-0">Full Akses</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            ZONA WAKTU OPERASIONAL
                                        </label>
                                        <div class="relative">
                                            <select x-model="profileTimezone"
                                                    class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-9 py-2.5 text-[13px] font-semibold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option>WIB (UTC+07:00) - Jakarta</option>
                                                <option>WITA (UTC+08:00) - Makassar / Bali</option>
                                                <option>WIT (UTC+09:00) - Jayapura</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                        DESKRIPSI TUGAS & TANGGUNG JAWAB OTORISASI
                                    </label>
                                    <textarea rows="2" x-model="profileBio"
                                              class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl p-3.5 text-[12.5px] font-medium text-slate-700 focus:outline-none transition-all resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: KEAMANAN & AKSES                                   -->
                    <!-- ========================================================= -->
                    <div x-show="activeTab === 'keamanan'" style="display: none;" class="divide-y divide-slate-100">

                        <!-- Section 2.1: Ubah Password -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">key</span>
                                    <span>KREDENSIAL LOGIN</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Ubah Kata Sandi Akun</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Gunakan kombinasi minimal 8 karakter yang terdiri dari huruf besar, huruf kecil, dan angka untuk menjaga keamanan akun Manager.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                        PASSWORD SAAT INI <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative flex items-center max-w-md">
                                        <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">lock</span>
                                        <input :type="showCurrentPass ? 'text' : 'password'" x-model="currentPassword"
                                               placeholder="Masukkan password saat ini..."
                                               class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-10 py-2.5 text-[13px] font-semibold text-[#0f172a] focus:outline-none transition-all">
                                        <button type="button" @click="showCurrentPass = !showCurrentPass"
                                                class="absolute right-3 text-slate-400 hover:text-slate-600 flex items-center cursor-pointer">
                                            <span class="material-symbols-rounded text-[18px]" x-text="showCurrentPass ? 'visibility_off' : 'visibility'"></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            PASSWORD BARU
                                        </label>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">enhanced_encryption</span>
                                            <input :type="showNewPass ? 'text' : 'password'" x-model="newPassword"
                                                   placeholder="Minimal 8 karakter..."
                                                   class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-10 py-2.5 text-[13px] font-semibold text-[#0f172a] focus:outline-none transition-all">
                                            <button type="button" @click="showNewPass = !showNewPass"
                                                    class="absolute right-3 text-slate-400 hover:text-slate-600 flex items-center cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]" x-text="showNewPass ? 'visibility_off' : 'visibility'"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            KONFIRMASI PASSWORD BARU
                                        </label>
                                        <div class="relative flex items-center">
                                            <span class="material-symbols-rounded absolute left-3.5 text-[18px] text-slate-400 pointer-events-none">check_circle</span>
                                            <input :type="showNewPass ? 'text' : 'password'" x-model="confirmPassword"
                                                   placeholder="Ulangi password baru..."
                                                   class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/15 rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-semibold text-[#0f172a] focus:outline-none transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2.2: PIN Otorisasi POS / Manager -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">dialpad</span>
                                    <span>OTORISASI TERMINAL KASIR</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">PIN Otorisasi POS & Supervisor</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    PIN 6-digit digunakan saat Manager mengotorisasi pembatalan transaksi (void), refund barang, buka laci kasir darurat, dan persetujuan selisih opname.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div class="bg-[#eff6ff] border border-blue-200/80 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[11px] font-extrabold text-[#0052cc] uppercase tracking-wider">PIN OTORISASI AKTIF (6 DIGIT)</span>
                                            <span class="bg-[#dcfce7] text-[#15803d] text-[10px] font-extrabold px-2 py-0.5 rounded">Terenkripsi</span>
                                        </div>
                                        <p class="text-[12px] text-slate-600 font-medium">
                                            Jangan bagikan PIN ini kepada staf kasir reguler.
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2.5 shrink-0">
                                        <div class="bg-white border border-blue-200 rounded-xl px-3.5 py-2 flex items-center gap-2 shadow-2xs">
                                            <input :type="showPinText ? 'text' : 'password'" x-model="newPin" maxlength="6"
                                                   class="w-24 bg-transparent font-mono font-black text-[16px] tracking-[0.25em] text-[#0052cc] text-center focus:outline-none">
                                            <button type="button" @click="showPinText = !showPinText"
                                                    class="text-slate-400 hover:text-[#0052cc] flex items-center cursor-pointer"
                                                    title="Tampilkan / Sembunyikan PIN">
                                                <span class="material-symbols-rounded text-[18px]" x-text="showPinText ? 'visibility_off' : 'visibility'"></span>
                                            </button>
                                        </div>
                                        <button type="button"
                                                @click="newPin = String(Math.floor(100000 + Math.random() * 900000)); showPinText = true; triggerToast('PIN Otorisasi baru berhasil digenerate: ' + newPin)"
                                                class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-3.5 py-2.5 rounded-xl text-[12px] font-extrabold inline-flex items-center gap-1.5 shadow-2xs transition-colors cursor-pointer">
                                            <span class="material-symbols-rounded text-[16px] text-[#0052cc]">autorenew</span>
                                            <span>Acak PIN</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- PIN Policy Toggles -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div @click="requirePinVoid = !requirePinVoid"
                                         class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex items-center justify-between gap-3 cursor-pointer hover:border-blue-300 transition-colors">
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Wajib PIN saat Void & Refund</div>
                                            <div class="text-[11px] text-slate-500 font-medium mt-0.5">Transaksi batal di atas Rp 50.000</div>
                                        </div>
                                        <button type="button"
                                                :class="requirePinVoid ? 'bg-[#0047b3]' : 'bg-slate-300'"
                                                class="w-11 h-6 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer">
                                            <span :class="requirePinVoid ? 'translate-x-5' : 'translate-x-0.5'"
                                                  class="w-5 h-5 bg-white rounded-full shadow-xs absolute top-0.5 left-0 transition-transform duration-200"></span>
                                        </button>
                                    </div>

                                    <div @click="requirePinDiscount = !requirePinDiscount"
                                         class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex items-center justify-between gap-3 cursor-pointer hover:border-blue-300 transition-colors">
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Wajib PIN Diskon Manual</div>
                                            <div class="text-[11px] text-slate-500 font-medium mt-0.5">Override harga jual / kupon khusus</div>
                                        </div>
                                        <button type="button"
                                                :class="requirePinDiscount ? 'bg-[#0047b3]' : 'bg-slate-300'"
                                                class="w-11 h-6 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer">
                                            <span :class="requirePinDiscount ? 'translate-x-5' : 'translate-x-0.5'"
                                                  class="w-5 h-5 bg-white rounded-full shadow-xs absolute top-0.5 left-0 transition-transform duration-200"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2.3: Riwayat Sesi Login Aktif -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-2">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">devices</span>
                                    <span>MONITORING PERANGKAT</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Riwayat Sesi Login Aktif</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Daftar perangkat yang saat ini masuk menggunakan akun Manager Anda. Putuskan akses jika terdapat perangkat yang tidak dikenali.
                                </p>
                                <button type="button"
                                        @click="revokeAllOtherSessions()"
                                        class="mt-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-3.5 py-2 rounded-xl text-[11.5px] font-extrabold inline-flex items-center gap-1.5 transition-colors cursor-pointer">
                                    <span class="material-symbols-rounded text-[16px]">logout</span>
                                    <span>Keluar dari Semua Perangkat Lain</span>
                                </button>
                            </div>

                            <div class="lg:col-span-8 space-y-3">
                                <template x-for="sess in sessions" :key="sess.id">
                                    <div class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-start gap-3.5">
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                                 :class="sess.isCurrent ? 'bg-[#dbeafe] text-[#0052cc]' : 'bg-slate-200/70 text-slate-600'">
                                                <span class="material-symbols-rounded text-[20px]" x-text="sess.icon"></span>
                                            </div>
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-[13.5px] font-extrabold text-[#0f172a]" x-text="sess.device"></span>
                                                    <template x-if="sess.isCurrent">
                                                        <span class="bg-[#dcfce7] text-[#15803d] text-[10px] font-extrabold px-2 py-0.5 rounded-md">
                                                            Perangkat Ini
                                                        </span>
                                                    </template>
                                                </div>
                                                <div class="text-[12px] text-slate-500 font-medium mt-0.5" x-text="sess.location"></div>
                                                <div class="text-[11px] font-bold mt-1"
                                                     :class="sess.isCurrent ? 'text-[#16a34a]' : 'text-slate-400'"
                                                     x-text="sess.lastActive"></div>
                                            </div>
                                        </div>

                                        <div class="shrink-0 self-end sm:self-center">
                                            <template x-if="!sess.isCurrent">
                                                <button type="button"
                                                        @click="revokeSession(sess.id, sess.device)"
                                                        class="bg-white hover:bg-red-50 text-red-600 border border-slate-200 hover:border-red-200 px-3.5 py-2 rounded-xl text-[11.5px] font-extrabold transition-colors cursor-pointer">
                                                    Putuskan Akses
                                                </button>
                                            </template>
                                            <template x-if="sess.isCurrent">
                                                <span class="inline-flex items-center gap-1.5 text-[11.5px] font-extrabold text-[#16a34a] px-3 py-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#16a34a]"></span>
                                                    <span>Sesi Terautentikasi</span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 3: PREFERENSI POS & LAPORAN                           -->
                    <!-- ========================================================= -->
                    <div x-show="activeTab === 'preferensi'" style="display: none;" class="divide-y divide-slate-100">

                        <!-- Section 3.1: Notifikasi Target & Laporan Penjualan Harian -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">notifications_active</span>
                                    <span>NOTIFIKASI & TARGET</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Notifikasi Target & Laporan Harian</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Atur pengiriman otomatis ringkasan performa kasir, peringatan stok kritis, dan target omzet harian cabang.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <!-- Target Omzet Harian -->
                                <div class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <div class="text-[13px] font-extrabold text-[#0f172a]">Target Omzet Penjualan Harian Cabang</div>
                                        <div class="text-[11.5px] text-slate-500 font-medium mt-0.5">Digunakan sebagai acuan persentase pencapaian pada halaman Sales Reports</div>
                                    </div>
                                    <div class="bg-white border border-slate-200/90 focus-within:border-blue-500 rounded-xl px-3.5 py-2 flex items-center gap-2 shrink-0 w-full sm:w-52">
                                        <span class="text-[12.5px] font-bold text-slate-500">Rp</span>
                                        <input type="text" x-model="dailyTargetAmount"
                                               class="w-full bg-transparent font-mono font-black text-[14.5px] text-[#0052cc] focus:outline-none">
                                    </div>
                                </div>

                                <!-- Toggle 1 -->
                                <div @click="notifDailyReport = !notifDailyReport"
                                     class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex items-center justify-between gap-4 cursor-pointer hover:border-blue-300 transition-colors">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0 mt-0.5">
                                            <span class="material-symbols-rounded text-[19px]">summarize</span>
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Kirim Laporan Tutup Shift & Omzet Harian Otomatis</div>
                                            <div class="text-[11.5px] text-slate-500 font-medium mt-0.5">Dikirim setiap pukul 22:00 WIB ke email & WhatsApp Manager setelah kasir tutup sesi</div>
                                        </div>
                                    </div>
                                    <button type="button"
                                            :class="notifDailyReport ? 'bg-[#0047b3]' : 'bg-slate-300'"
                                            class="w-11 h-6 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer">
                                        <span :class="notifDailyReport ? 'translate-x-5' : 'translate-x-0.5'"
                                              class="w-5 h-5 bg-white rounded-full shadow-xs absolute top-0.5 left-0 transition-transform duration-200"></span>
                                    </button>
                                </div>

                                <!-- Toggle 2 -->
                                <div @click="notifLowStock = !notifLowStock"
                                     class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex items-center justify-between gap-4 cursor-pointer hover:border-blue-300 transition-colors">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center shrink-0 mt-0.5">
                                            <span class="material-symbols-rounded text-[19px]">inventory_2</span>
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Peringatan Stok Kritis & Selisih Stock Opname</div>
                                            <div class="text-[11.5px] text-slate-500 font-medium mt-0.5">Notifikasi instan saat stok SKU berada di bawah batas aman atau selisih opname &gt; Rp 100.000</div>
                                        </div>
                                    </div>
                                    <button type="button"
                                            :class="notifLowStock ? 'bg-[#0047b3]' : 'bg-slate-300'"
                                            class="w-11 h-6 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer">
                                        <span :class="notifLowStock ? 'translate-x-5' : 'translate-x-0.5'"
                                              class="w-5 h-5 bg-white rounded-full shadow-xs absolute top-0.5 left-0 transition-transform duration-200"></span>
                                    </button>
                                </div>

                                <!-- Toggle 3 -->
                                <div @click="notifReturUmkm = !notifReturUmkm"
                                     class="bg-[#f8fafc] border border-slate-200/80 rounded-xl p-4 flex items-center justify-between gap-4 cursor-pointer hover:border-blue-300 transition-colors">
                                    <div class="flex items-start gap-3.5">
                                        <div class="w-9 h-9 rounded-lg bg-emerald-50 text-[#15803d] flex items-center justify-center shrink-0 mt-0.5">
                                            <span class="material-symbols-rounded text-[19px]">local_shipping</span>
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Notifikasi Pengajuan Retur & Konsinyasi Mitra UMKM</div>
                                            <div class="text-[11.5px] text-slate-500 font-medium mt-0.5">Beritahu ketika ada ajuan retur Toko &rarr; Gudang yang menunggu approval atau jadwal settlement mitra</div>
                                        </div>
                                    </div>
                                    <button type="button"
                                            :class="notifReturUmkm ? 'bg-[#0047b3]' : 'bg-slate-300'"
                                            class="w-11 h-6 rounded-full relative transition-colors duration-200 shrink-0 cursor-pointer">
                                        <span :class="notifReturUmkm ? 'translate-x-5' : 'translate-x-0.5'"
                                              class="w-5 h-5 bg-white rounded-full shadow-xs absolute top-0.5 left-0 transition-transform duration-200"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3.2: Format Ekspor Default -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">file_present</span>
                                    <span>DOKUMEN & PELAPORAN</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Format Ekspor Default</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Pilih format berkas utama saat mengunduh laporan penjualan, data mitra UMKM, dan berita acara stock opname.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                                    <!-- Format 1: Excel -->
                                    <label @click="defaultExportFormat = 'xlsx'"
                                           :class="defaultExportFormat === 'xlsx' ? 'bg-[#eff6ff] border-[#0052cc] ring-1 ring-[#0052cc]/20' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/70'"
                                           class="border rounded-xl p-4 flex flex-col justify-between gap-2 cursor-pointer transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="bg-[#dcfce7] text-[#15803d] text-[10px] font-extrabold px-2 py-0.5 rounded uppercase">XLSX / EXCEL</span>
                                            <input type="radio" name="export_fmt" value="xlsx" x-model="defaultExportFormat" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Microsoft Excel (.xlsx)</div>
                                            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Ideal untuk audit akuntansi & olah tabel</p>
                                        </div>
                                    </label>

                                    <!-- Format 2: PDF -->
                                    <label @click="defaultExportFormat = 'pdf'"
                                           :class="defaultExportFormat === 'pdf' ? 'bg-[#eff6ff] border-[#0052cc] ring-1 ring-[#0052cc]/20' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/70'"
                                           class="border rounded-xl p-4 flex flex-col justify-between gap-2 cursor-pointer transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="bg-[#fee2e2] text-[#b91c1c] text-[10px] font-extrabold px-2 py-0.5 rounded uppercase">PDF RESMI</span>
                                            <input type="radio" name="export_fmt" value="pdf" x-model="defaultExportFormat" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Dokumen Cetak (.pdf)</div>
                                            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Siap cetak lengkap dengan tanda tangan BAST</p>
                                        </div>
                                    </label>

                                    <!-- Format 3: CSV -->
                                    <label @click="defaultExportFormat = 'csv'"
                                           :class="defaultExportFormat === 'csv' ? 'bg-[#eff6ff] border-[#0052cc] ring-1 ring-[#0052cc]/20' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/70'"
                                           class="border rounded-xl p-4 flex flex-col justify-between gap-2 cursor-pointer transition-all">
                                        <div class="flex items-center justify-between">
                                            <span class="bg-slate-200 text-slate-700 text-[10px] font-extrabold px-2 py-0.5 rounded uppercase">CSV / ERP</span>
                                            <input type="radio" name="export_fmt" value="csv" x-model="defaultExportFormat" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                        </div>
                                        <div>
                                            <div class="text-[13px] font-extrabold text-[#0f172a]">Data Mentah (.csv)</div>
                                            <p class="text-[11px] text-slate-500 font-medium mt-0.5">Sinkronisasi cepat ke database ERP pusat</p>
                                        </div>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            UKURAN KERTAS CETAK LAPORAN
                                        </label>
                                        <div class="relative">
                                            <select x-model="defaultPaperSize"
                                                    class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-9 py-2.5 text-[13px] font-semibold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option>A4 Portrait (210 x 297 mm)</option>
                                                <option>A4 Landscape (Tabel Rekap Lebar)</option>
                                                <option>F4 / Folio Resmi Koperasi</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                        </div>
                                    </div>

                                    <div class="flex items-end">
                                        <label class="w-full bg-[#f8fafc] border border-slate-200/80 rounded-xl px-4 py-2.5 flex items-center gap-3 cursor-pointer select-none">
                                            <input type="checkbox" x-model="includeDigitalSign" class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc] border-slate-300">
                                            <span class="text-[12px] font-bold text-slate-700">Sertakan QR Validasi & Tanda Tangan Manager di PDF</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3.3: Preferensi Mata Uang & Pajak Cabang -->
                        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <div class="lg:col-span-4 space-y-1">
                                <div class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider">
                                    <span class="material-symbols-rounded text-[14px]">payments</span>
                                    <span>FINANSIAL & PAJAK POS</span>
                                </div>
                                <h3 class="text-[15.5px] font-black text-[#0f172a]">Preferensi Mata Uang & Pajak Cabang</h3>
                                <p class="text-[12.5px] text-slate-500 font-medium leading-relaxed">
                                    Konfigurasi format mata uang kasir, tarif PPN barang kena pajak, dan aturan pembulatan transaksi tunai.
                                </p>
                            </div>

                            <div class="lg:col-span-8 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            MATA UANG UTAMA
                                        </label>
                                        <div class="relative">
                                            <select x-model="currencyCode"
                                                    class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-9 py-2.5 text-[13px] font-bold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option>IDR (Rp) - Rupiah Indonesia</option>
                                                <option>USD ($) - US Dollar</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            TARIF PPN DEFAULT (%)
                                        </label>
                                        <div class="bg-[#f1f5f9] border border-slate-200/90 focus-within:border-blue-500 focus-within:bg-white rounded-xl px-3.5 py-2.5 flex items-center justify-between transition-all">
                                            <input type="number" x-model="ppnRate" min="0" max="25"
                                                   class="w-full bg-transparent font-mono font-black text-[14px] text-[#0f172a] focus:outline-none">
                                            <span class="text-[12px] font-extrabold text-[#0052cc] shrink-0">% PPN</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            PEMBULATAN TUNAI KASIR
                                        </label>
                                        <div class="relative">
                                            <select x-model="cashRounding"
                                                    class="w-full bg-[#f1f5f9] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-9 py-2.5 text-[13px] font-semibold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option value="100">Ke Rp 100 Terdekat</option>
                                                <option value="500">Ke Rp 500 Terdekat</option>
                                                <option value="0">Tanpa Pembulatan (Rp 1)</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Skema Perhitungan Pajak -->
                                <div class="bg-[#f1f5f9] rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <span class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider shrink-0">
                                        METODE HARGA & PAJAK RAK:
                                    </span>
                                    <div class="flex flex-wrap items-center gap-5">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="tax_mode" value="inclusive" x-model="taxCalculationMode" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span class="text-[12px]" :class="taxCalculationMode === 'inclusive' ? 'font-extrabold text-[#0f172a]' : 'font-medium text-slate-600'">Harga Rak Sudah Termasuk PPN (Inclusive)</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="tax_mode" value="exclusive" x-model="taxCalculationMode" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span class="text-[12px]" :class="taxCalculationMode === 'exclusive' ? 'font-extrabold text-[#0f172a]' : 'font-medium text-slate-600'">Tambah PPN Saat Checkout Kasir (Exclusive)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Consistent Form Footer Action Bar -->
                    <div class="px-6 lg:px-8 py-4 bg-[#f8fafc] border-t border-slate-200/80 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                            <span class="material-symbols-rounded text-[18px] text-[#16a34a]">verified_user</span>
                            <span>Seluruh perubahan dicatat pada <strong class="font-extrabold text-slate-800">Audit Log Cabang Minimarket Pusat</strong></span>
                        </div>

                        <div class="flex items-center justify-end gap-3 shrink-0">
                            <button type="button"
                                    @click="resetForm()"
                                    class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200/90 font-extrabold text-[12.5px] px-5 py-2.5 rounded-xl transition-colors cursor-pointer">
                                Reset / Batal
                            </button>
                            <button type="button"
                                    @click="saveSettings()"
                                    class="bg-[#0047b3] hover:bg-[#003994] text-white font-extrabold text-[12.5px] px-6 py-2.5 rounded-xl inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">save</span>
                                <span>Simpan Perubahan</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Interactive Toast Notification -->
    <div x-show="showToast" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         class="fixed bottom-6 right-6 z-50 bg-[#0f172a] text-white px-4 py-3 rounded-xl shadow-xl border border-slate-700 flex items-center gap-3 text-[12.5px] font-bold">
        <span class="material-symbols-rounded text-[18px] text-emerald-400">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
