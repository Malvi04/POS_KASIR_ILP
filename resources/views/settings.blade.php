@extends('layouts.app')

@section('title', 'Pengaturan Sistem - ILP POS')

@section('content')
<div x-data="{
    activeTab: 'profil',
    searchSetting: '',
    toastMessage: '',
    showToast: false,
    lastSaved: 'Hari ini, 09:15 WIB',

    profileName: 'Andi Darmawan, S.E.',
    profileNik: 'MGR-ILP-2024-004',
    profileEmail: 'andi.darmawan@ilp-pos.co.id',
    profilePhone: '81299887766',
    profileRole: 'Account & Store Manager',
    profileBranch: 'Cabang Pusat - Minimarket ILP',
    avatarInitials: 'AD',
    avatarFileName: '',

    storeName: 'ILP Mart - Koperasi Pekerja Pusat',
    storeCode: 'CAB-JKT-001',
    storePhone: '(021) 5589-2210',
    storeTaxId: '01.882.991.4-012.000',
    storeOpenTime: '07:00',
    storeCloseTime: '22:00',
    storeAddress: 'Jl. Industri Raya No. 45, Kawasan Terpadu ILP',
    storeCity: 'Jakarta Timur, DKI Jakarta',

    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
    twoFactorMode: 'Aktif via WhatsApp & Email',
    posPin: '882910',
    showPin: false,
    maxDiscountNoPin: '0% (Wajib PIN Manager)',
    pinExpiry: '90 Hari',
    idleLockMinutes: '15 Menit',
    pinForVoid: true,
    pinForRefund: true,
    pinForDrawer: true,
    pinForCreditOverride: true,

    sessions: [
        { id: 1, name: 'PC Backoffice Manager (Windows 11)', info: 'Chrome 125 • IP 192.168.1.10', status: 'Sesi Ini', current: true, icon: 'desktop_windows' },
        { id: 2, name: 'Tablet Opname Gudang (iPadOS)', info: 'Safari • IP 192.168.1.45', status: '18 mnt lalu', current: false, icon: 'tablet_mac' },
        { id: 3, name: 'Smartphone Manager (Android 14)', info: 'Mobile App • IP 114.122.89.12', status: 'Kemarin', current: false, icon: 'smartphone' }
    ],

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3000);
    },

    resetForm() {
        if (this.activeTab === 'profil') {
            this.profileName = 'Andi Darmawan, S.E.';
            this.profileEmail = 'andi.darmawan@ilp-pos.co.id';
            this.profilePhone = '81299887766';
            this.storeName = 'ILP Mart - Koperasi Pekerja Pusat';
            this.storePhone = '(021) 5589-2210';
        } else if (this.activeTab === 'keamanan') {
            this.currentPassword = '';
            this.newPassword = '';
            this.confirmPassword = '';
            this.posPin = '882910';
            this.pinForVoid = true;
            this.pinForRefund = true;
            this.pinForDrawer = true;
            this.pinForCreditOverride = true;
        }
        this.triggerToast('Formulir dikembalikan ke pengaturan awal.');
    },

    saveSettings() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        this.lastSaved = 'Baru saja, ' + hh + ':' + mm + ' WIB';
        this.currentPassword = '';
        this.newPassword = '';
        this.confirmPassword = '';
        this.triggerToast('Perubahan pengaturan POS berhasil disimpan.');
    },

    revokeSession(id, name) {
        this.sessions = this.sessions.filter(s => s.id !== id);
        this.triggerToast('Sesi perangkat ' + name + ' telah dihentikan.');
    }
}" class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar -->
    <x-manager-sidebar active="settings" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Header (Identical style to Sales Reports) -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="relative w-[360px]">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                <input type="text" x-model="searchSetting" placeholder="Cari pengaturan akun, toko, atau PIN..."
                       class="w-full pl-11 pr-4 py-2 rounded-full bg-[#f1f5f9] border border-transparent focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 focus:outline-none text-[13px] text-gray-700 placeholder-gray-400 transition-all">
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <button @click="triggerToast('Tidak ada notifikasi sistem baru.')" class="hover:text-gray-900 transition-colors p-1 cursor-pointer" title="Notifikasi">
                    <span class="material-symbols-rounded text-[22px]">notifications</span>
                </button>
                <button @click="triggerToast('Panduan Konfigurasi Vigilant POS v2.4.1')" class="hover:text-gray-900 transition-colors p-1 cursor-pointer" title="Bantuan">
                    <span class="material-symbols-rounded text-[22px]">help_outline</span>
                </button>
                <div class="h-6 w-px bg-gray-200 ml-1"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                    <span class="text-[13px] font-semibold text-gray-600">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 pb-8">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Subheader: Title & Navigation Sub-Menu Tabs -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-[19px] font-bold text-gray-900 tracking-tight">Pengaturan Sistem & Akun Manager</h1>
                        <p class="text-[13px] text-gray-500 mt-0.5">Kelola identitas profil Account Manager, info outlet, serta keamanan dan PIN otorisasi kasir.</p>
                    </div>

                    <!-- Navigation Sub-Menu / Tabs -->
                    <div class="bg-white border border-gray-200 rounded-xl p-1.5 flex flex-wrap items-center gap-1.5 shadow-sm shrink-0">
                        <button type="button"
                                @click="activeTab = 'profil'"
                                :class="activeTab === 'profil' ? 'bg-[#0052cc] text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-4 py-2 rounded-lg text-[13px] font-bold flex items-center gap-2 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">person</span>
                            <span>Profil Akun</span>
                        </button>

                        <button type="button"
                                @click="activeTab = 'keamanan'"
                                :class="activeTab === 'keamanan' ? 'bg-[#0052cc] text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-4 py-2 rounded-lg text-[13px] font-bold flex items-center gap-2 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">shield_lock</span>
                            <span>Keamanan & PIN POS</span>
                        </button>
                    </div>
                </div>

                <!-- 4 Summary KPI / Status Cards (Exact style from Sales Reports) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Card 1 -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">storefront</span>
                            </div>
                            <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                Aktif
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">CABANG OPERASIONAL</p>
                            <h3 class="text-[16px] font-bold text-gray-900 mt-1 tracking-tight truncate">Minimarket Pusat (JKT-01)</h3>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">dialpad</span>
                            </div>
                            <span class="inline-flex items-center bg-gray-100 text-gray-600 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                6 Digit PIN
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">OTORISASI SUPERVISOR</p>
                            <h3 class="text-[16px] font-bold text-gray-900 mt-1 tracking-tight">Void, Refund & Plafon</h3>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#e0e7ff] text-[#3730a3] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">devices</span>
                            </div>
                            <span class="inline-flex items-center bg-[#dbeafe] text-[#0052cc] text-[11px] font-bold px-2.5 py-1 rounded-full" x-text="sessions.length + ' Aktif'">
                                3 Aktif
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">PERANGKAT TERHUBUNG</p>
                            <h3 class="text-[16px] font-bold text-gray-900 mt-1 tracking-tight">Sesi Login Manager</h3>
                        </div>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[120px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#fef3c7] text-[#d97706] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">cloud_done</span>
                            </div>
                            <span class="inline-flex items-center bg-green-50 text-green-600 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                Online
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">SINKRONISASI TERAKHIR</p>
                            <h3 class="text-[15px] font-bold text-gray-900 mt-1 tracking-tight truncate" x-text="lastSaved">Hari ini, 09:15 WIB</h3>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 1: PROFIL AKUN & IDENTITAS TOKO POS                   -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'profil'" class="grid grid-cols-12 gap-6 items-start">

                    <!-- Left Column (8 Cols): Form Cards -->
                    <div class="col-span-12 lg:col-span-8 space-y-6">

                        <!-- Card 1: Informasi Profil Account Manager -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[20px]">person</span>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-gray-900">Profil Account Manager</h2>
                                        <p class="text-[12px] text-gray-500">Data identitas penanggung jawab operasional dan persetujuan laporan cabang.</p>
                                    </div>
                                </div>
                                <span class="bg-[#dbeafe] text-[#0052cc] text-[10px] font-extrabold px-2.5 py-1 rounded-md tracking-wider uppercase shrink-0">
                                    HEAD MANAGER
                                </span>
                            </div>

                            <!-- Photo Upload Box (Aligned & Responsive) -->
                            <div class="bg-[#f8f9fa] border border-gray-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-4 min-w-0">
                                    <div class="w-14 h-14 rounded-xl bg-[#0052cc] text-white font-bold text-[18px] flex items-center justify-center shrink-0 shadow-sm"
                                         x-text="avatarInitials">
                                        AD
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-[14px] font-bold text-gray-900 truncate" x-text="profileName">Andi Darmawan, S.E.</h3>
                                        <p class="text-[12px] text-gray-500 mt-0.5">Format foto JPG atau PNG (Maks. 2MB). Ditampilkan pada BAST & Laporan.</p>
                                        <p x-show="avatarFileName" class="text-[11.5px] font-bold text-green-600 mt-1" x-text="'File terpilih: ' + avatarFileName"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2.5 shrink-0">
                                    <label class="px-4 py-2 bg-white border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 rounded-lg text-[12.5px] font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[17px]">upload</span>
                                        <span>Ubah Foto</span>
                                        <input type="file" accept="image/*" class="hidden"
                                               @change="if($event.target.files[0]) { avatarFileName = $event.target.files[0].name; triggerToast('Foto profil (' + avatarFileName + ') siap disimpan.'); }">
                                    </label>
                                    <button type="button"
                                            @click="avatarFileName = ''; triggerToast('Foto profil direset ke inisial default.')"
                                            class="px-3.5 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 rounded-lg text-[12.5px] font-bold transition-colors cursor-pointer">
                                        Hapus
                                    </button>
                                </div>
                            </div>

                            <!-- 2-Column Balanced Form Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Nama Lengkap</label>
                                    <input type="text" x-model="profileName"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">NIK / ID Otorisasi Manager</label>
                                    <input type="text" x-model="profileNik" readonly
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-[#f8f9fa] text-[13.5px] text-gray-500 font-mono cursor-not-allowed">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Email Korporat</label>
                                    <input type="email" x-model="profileEmail"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Nomor Telepon / WhatsApp</label>
                                    <div class="flex">
                                        <span class="inline-flex items-center px-3.5 py-2.5 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-[13.5px] font-medium">+62</span>
                                        <input type="text" x-model="profilePhone"
                                               class="flex-1 min-w-0 px-4 py-2.5 rounded-r-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Jabatan / Role Sistem</label>
                                    <div class="relative">
                                        <select x-model="profileRole"
                                                class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 pr-10 text-[13.5px] text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                            <option>Account & Store Manager</option>
                                            <option>Kepala Cabang Operasional</option>
                                            <option>Supervisor Audit & Inventaris</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Unit Cabang Utama</label>
                                    <div class="relative">
                                        <select x-model="profileBranch"
                                                class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 pr-10 text-[13.5px] text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                            <option>Cabang Pusat - Minimarket ILP</option>
                                            <option>Cabang Barat - Koperasi ILP</option>
                                            <option>Cabang Selatan - Koperasi ILP</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <span class="text-[12px] text-gray-500">Pastikan email dan nomor WhatsApp aktif untuk pengiriman OTP otorisasi.</span>
                                <div class="flex items-center gap-3 self-end sm:self-auto shrink-0">
                                    <button type="button" @click="resetForm()"
                                            class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold text-[13px] rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="button" @click="saveSettings()"
                                            class="px-5 py-2.5 bg-[#0052cc] text-white font-bold text-[13px] rounded-lg shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Identitas Gerai & Cabang POS -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[20px]">storefront</span>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-gray-900">Identitas Toko & Gerai POS</h2>
                                        <p class="text-[12px] text-gray-500">Informasi resmi gerai yang dicetak pada struk kasir, faktur pajak, dan nota retur.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- 2-Column Balanced Form Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Nama Gerai / Toko POS</label>
                                    <input type="text" x-model="storeName"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Kode Registrasi Cabang</label>
                                    <input type="text" x-model="storeCode"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Nomor Telepon Toko (Tampil di Struk)</label>
                                    <input type="text" x-model="storePhone"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">NPWP / Nomor PKP Toko</label>
                                    <input type="text" x-model="storeTaxId"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Jam Buka Operasional</label>
                                    <input type="text" x-model="storeOpenTime"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Jam Tutup Operasional</label>
                                    <input type="text" x-model="storeCloseTime"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white font-mono">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Alamat Jalan & Gedung</label>
                                    <input type="text" x-model="storeAddress"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Kota & Provinsi</label>
                                    <input type="text" x-model="storeCity"
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <span class="text-[12px] text-gray-500">Perubahan identitas toko akan langsung diterapkan ke seluruh terminal kasir.</span>
                                <div class="flex items-center gap-3 self-end sm:self-auto shrink-0">
                                    <button type="button" @click="resetForm()"
                                            class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold text-[13px] rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="button" @click="saveSettings()"
                                            class="px-5 py-2.5 bg-[#0052cc] text-white font-bold text-[13px] rounded-lg shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column (4 Cols): POS System Status & Authority Card -->
                    <div class="col-span-12 lg:col-span-4 space-y-6">

                        <!-- Blue Info Card (Matching member-detail & cashier-settings style) -->
                        <div class="rounded-2xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                            <div class="bg-[#0052cc] p-6 text-white flex flex-col gap-4">
                                <div class="flex justify-between items-start">
                                    <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center">
                                        <span class="material-symbols-rounded text-white text-[22px]">verified_user</span>
                                    </div>
                                    <span class="text-[10px] font-bold tracking-widest uppercase bg-white/15 px-2.5 py-1 rounded-full">LISENSI ENTERPRISE</span>
                                </div>

                                <div>
                                    <p class="text-[12px] text-blue-100">Versi Sistem POS</p>
                                    <h3 class="text-[22px] font-bold tracking-tight mt-0.5">Vigilant POS v2.4.1</h3>
                                </div>

                                <div class="w-full h-px bg-white/20"></div>

                                <div class="grid grid-cols-2 gap-4 text-[12.5px]">
                                    <div>
                                        <p class="text-[11px] text-blue-200">Terminal Aktif</p>
                                        <span class="font-mono font-bold text-[14px]">12 Kasir</span>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-blue-200">Database</p>
                                        <span class="font-mono font-bold text-[14px]">Cloud Sync</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-6 space-y-4">
                                <h4 class="text-[14px] font-bold text-gray-900">Hak Akses Account Manager</h4>
                                <div class="space-y-3 text-[12.5px] text-gray-600">
                                    <div class="flex items-center justify-between">
                                        <span>Otorisasi Void & Retur Barang</span>
                                        <span class="text-green-600 font-bold">Aktif</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Manajemen Harga & Komisi UMKM</span>
                                        <span class="text-green-600 font-bold">Aktif</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Persetujuan Plafon Kredit Anggota</span>
                                        <span class="text-green-600 font-bold">Aktif</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span>Ekspor Laporan Keuangan & Audit</span>
                                        <span class="text-green-600 font-bold">Aktif</span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-100">
                                    <a href="{{ url('/') }}" data-no-spa
                                       class="w-full py-2.5 px-4 bg-red-50 hover:bg-red-100 text-red-600 font-bold text-[12.5px] rounded-xl transition-colors flex items-center justify-center gap-2">
                                        <span class="material-symbols-rounded text-[18px]">logout</span>
                                        <span>Keluar ke Portal Login</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 2: KEAMANAN & PIN POS                                 -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'keamanan'" style="display: none;" class="grid grid-cols-12 gap-6 items-start">

                    <!-- Left Column (7 Cols): Password & PIN POS Cards -->
                    <div class="col-span-12 lg:col-span-7 space-y-6">

                        <!-- Card 1: PIN Otorisasi Kasir & Supervisor POS -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[20px]">dialpad</span>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-gray-900">PIN Otorisasi Kasir & Supervisor POS</h2>
                                        <p class="text-[12px] text-gray-500">Digunakan untuk validasi tindakan sensitif pada layar kasir (Void, Refund, Diskon Manual).</p>
                                    </div>
                                </div>
                                <span class="bg-green-50 text-green-600 text-[11px] font-bold px-2.5 py-1 rounded-full shrink-0">
                                    PIN Aktif
                                </span>
                            </div>

                            <!-- 2-Column Balanced Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">PIN Otorisasi Manager (6 Digit)</label>
                                    <div class="relative flex items-center">
                                        <input :type="showPin ? 'text' : 'password'" x-model="posPin" maxlength="6"
                                               class="w-full px-4 py-2.5 pr-10 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[14px] font-mono font-bold tracking-widest text-gray-800 bg-white">
                                        <button type="button" @click="showPin = !showPin"
                                                class="absolute right-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                                            <span class="material-symbols-rounded text-[19px]" x-text="showPin ? 'visibility_off' : 'visibility'">visibility</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Batas Diskon Manual Tanpa PIN</label>
                                    <div class="relative">
                                        <select x-model="maxDiscountNoPin"
                                                class="appearance-none w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 pr-10 text-[13.5px] text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                            <option>0% (Wajib PIN Manager)</option>
                                            <option>Maksimal Diskon 5%</option>
                                            <option>Maksimal Diskon 10%</option>
                                        </select>
                                        <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 2-Column Policy Switches -->
                            <div class="mt-6 pt-5 border-t border-gray-100">
                                <h3 class="text-[13px] font-bold text-gray-800 mb-3">Kebijakan Wajib PIN pada Terminal Kasir</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div @click="pinForVoid = !pinForVoid"
                                         class="p-3.5 bg-[#f8f9fa] rounded-xl border border-gray-200 flex items-center justify-between gap-3 cursor-pointer">
                                        <div>
                                            <p class="text-[13px] font-bold text-gray-800">Void / Batal Transaksi</p>
                                            <p class="text-[11px] text-gray-500">Wajib PIN saat hapus item sudah scan</p>
                                        </div>
                                        <div :class="pinForVoid ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                             class="w-11 h-6 rounded-full relative flex items-center px-0.5 transition-colors shrink-0">
                                            <div :class="pinForVoid ? 'right-0.5' : 'left-0.5'"
                                                 class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                                        </div>
                                    </div>

                                    <div @click="pinForRefund = !pinForRefund"
                                         class="p-3.5 bg-[#f8f9fa] rounded-xl border border-gray-200 flex items-center justify-between gap-3 cursor-pointer">
                                        <div>
                                            <p class="text-[13px] font-bold text-gray-800">Retur & Refund Tunai</p>
                                            <p class="text-[11px] text-gray-500">Wajib PIN saat pengembalian uang</p>
                                        </div>
                                        <div :class="pinForRefund ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                             class="w-11 h-6 rounded-full relative flex items-center px-0.5 transition-colors shrink-0">
                                            <div :class="pinForRefund ? 'right-0.5' : 'left-0.5'"
                                                 class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                                        </div>
                                    </div>

                                    <div @click="pinForCreditOverride = !pinForCreditOverride"
                                         class="p-3.5 bg-[#f8f9fa] rounded-xl border border-gray-200 flex items-center justify-between gap-3 cursor-pointer">
                                        <div>
                                            <p class="text-[13px] font-bold text-gray-800">Override Plafon Anggota</p>
                                            <p class="text-[11px] text-gray-500">Persetujuan khusus jika plafon habis</p>
                                        </div>
                                        <div :class="pinForCreditOverride ? 'bg-[#0052cc]' : 'bg-gray-300'"
                                             class="w-11 h-6 rounded-full relative flex items-center px-0.5 transition-colors shrink-0">
                                            <div :class="pinForCreditOverride ? 'right-0.5' : 'left-0.5'"
                                                 class="w-5 h-5 bg-white rounded-full absolute shadow transition-all"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <span class="text-[12px] text-gray-500">Jangan bagikan PIN Otorisasi Manager kepada staf kasir reguler.</span>
                                <div class="flex items-center gap-3 self-end sm:self-auto shrink-0">
                                    <button type="button" @click="resetForm()"
                                            class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold text-[13px] rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="button" @click="saveSettings()"
                                            class="px-5 py-2.5 bg-[#0052cc] text-white font-bold text-[13px] rounded-lg shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                        Simpan PIN POS
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Ubah Password Login Manager -->
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[20px]">lock</span>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-gray-900">Ubah Password Login Manager</h2>
                                        <p class="text-[12px] text-gray-500">Perbarui kata sandi akun dashboard Account Manager secara berkala.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Password Saat Ini</label>
                                    <input type="password" x-model="currentPassword" placeholder="Masukkan password lama..."
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5 md:col-start-1">
                                    <label class="text-[12.5px] font-bold text-gray-600">Password Baru</label>
                                    <input type="password" x-model="newPassword" placeholder="Minimal 8 karakter..."
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[12.5px] font-bold text-gray-600">Konfirmasi Password Baru</label>
                                    <input type="password" x-model="confirmPassword" placeholder="Ulangi password baru..."
                                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc] focus:outline-none text-[13.5px] text-gray-800 bg-white">
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="mt-6 pt-5 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <span class="text-[12px] text-gray-500">Gunakan kombinasi huruf besar, kecil, dan angka.</span>
                                <div class="flex items-center gap-3 self-end sm:self-auto shrink-0">
                                    <button type="button" @click="resetForm()"
                                            class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-bold text-[13px] rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="button" @click="saveSettings()"
                                            class="px-5 py-2.5 bg-[#0052cc] text-white font-bold text-[13px] rounded-lg shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                        Simpan Password
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column (5 Cols): Active Login Sessions -->
                    <div class="col-span-12 lg:col-span-5 space-y-6">
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between pb-4 mb-5 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-[#e0e7ff] text-[#3730a3] flex items-center justify-center shrink-0">
                                        <span class="material-symbols-rounded text-[20px]">devices</span>
                                    </div>
                                    <div>
                                        <h2 class="text-[15px] font-bold text-gray-900">Riwayat Sesi Login Aktif</h2>
                                        <p class="text-[12px] text-gray-500">Perangkat yang sedang terhubung ke akun ini.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3.5">
                                <template x-for="ses in sessions" :key="ses.id">
                                    <div class="p-4 rounded-xl border border-gray-200 bg-[#f8f9fa] flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-[#0052cc] flex items-center justify-center shrink-0">
                                                <span class="material-symbols-rounded text-[19px]" x-text="ses.icon">desktop_windows</span>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-bold text-gray-900 truncate" x-text="ses.name"></p>
                                                <p class="text-[11.5px] text-gray-500 truncate mt-0.5" x-text="ses.info"></p>
                                            </div>
                                        </div>

                                        <div class="shrink-0">
                                            <template x-if="ses.current">
                                                <span class="bg-green-100 text-green-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                                                    Aktif
                                                </span>
                                            </template>
                                            <template x-if="!ses.current">
                                                <button type="button" @click="revokeSession(ses.id, ses.name)"
                                                        class="px-3 py-1.5 bg-white border border-red-200 text-red-600 hover:bg-red-50 rounded-lg text-[11.5px] font-bold transition-colors cursor-pointer">
                                                    Putuskan
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-5 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="sessions = sessions.filter(s => s.current); triggerToast('Semua sesi perangkat lain berhasil dikeluarkan.')"
                                        class="w-full py-2.5 bg-[#e9eef5] hover:bg-[#cbd5e1]/80 text-gray-800 font-bold text-[12.5px] rounded-xl transition-colors cursor-pointer">
                                    Keluar dari Semua Perangkat Lain
                                </button>
                            </div>
                        </div>
                    </div>

                </div>



            </div>
        </div>

        <!-- Floating Toast Notification -->
        <div x-show="showToast" style="display: none;"
             x-transition.opacity.duration.200ms
             class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 text-[13px] font-semibold">
            <span class="material-symbols-rounded text-green-400 text-[20px]">check_circle</span>
            <span x-text="toastMessage"></span>
        </div>

    </main>
</div>
@endsection
