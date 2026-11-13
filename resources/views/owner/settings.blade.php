@extends('layouts.owner-admin', ['active' => 'settings'])

@section('title', 'Pengaturan Toko & Kebijakan - ILP POS Owner Console')

@section('header')
<x-admin.header title="Pengaturan Toko &amp; Kebijakan" :showSearch="false">
    <x-slot:leftSlot>
        <div class="flex items-center gap-2">
            <a href="{{ url('/owner') }}" class="text-xs font-black uppercase text-slate-800 tracking-wider hover:text-[#0052cc] transition-colors">Owner Console</a>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-slate-500">PENGATURAN TOKO</span>
            <span class="text-slate-300">&gt;</span>
            <span class="text-xs font-bold text-[#0052cc]">KEBIJAKAN KOPERASI</span>
        </div>
    </x-slot:leftSlot>
</x-admin.header>
@endsection

@section('admin_content')
<div x-data="ownerSettingsApp()" x-init="init()" class="space-y-6">

    <!-- Subheader: Page Title & Save Status Badge -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-[28px] font-black text-slate-900 tracking-tight">Pengaturan Toko &amp; Kebijakan</h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs font-medium text-slate-400" x-text="'Tersimpan: ' + lastSaved"></span>
            <button type="button" 
                    @click="saveAllSettings()"
                    class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs inline-flex items-center gap-2 shadow-sm shadow-blue-600/20 transition-all cursor-pointer">
                <span class="material-symbols-rounded text-[18px]">save</span>
                <span>Simpan Pengaturan</span>
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-0.5">
        <button type="button" 
                @click="activeTab = 'identitas'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap"
                :class="activeTab === 'identitas' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[17px]">store</span>
            <span>Identitas Toko &amp; Koperasi</span>
        </button>

        <button type="button" 
                @click="activeTab = 'kebijakan'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap"
                :class="activeTab === 'kebijakan' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[17px]">lock</span>
            <span>Otorisasi &amp; PIN Kasir</span>
        </button>

        <button type="button" 
                @click="activeTab = 'data'"
                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap"
                :class="activeTab === 'data' ? 'border-[#0052cc] text-[#0052cc] bg-blue-50/50 rounded-t-xl' : 'border-transparent text-slate-600 hover:text-slate-900'">
            <span class="material-symbols-rounded text-[17px]">database</span>
            <span>Data &amp; Backup Koperasi</span>
        </button>
    </div>

    <!-- TAB 1: IDENTITAS TOKO & KOPERASI -->
    <div x-show="activeTab === 'identitas'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                    <span class="material-symbols-rounded text-[22px]">domain</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Profil Badan Usaha Koperasi</h2>
                    <p class="text-xs text-slate-500">Informasi ini dicetak pada struk belanja kasir dan laporan keuangan resmi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Nama Usaha / Toko Koperasi *</label>
                    <input type="text" x-model="settings.storeName" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Nomor Izin Koperasi (NIK Kemenkop)</label>
                    <input type="text" x-model="settings.coopLicense" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Nomor Pokok Wajib Pajak (NPWP)</label>
                    <input type="text" x-model="settings.taxId" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Nomor Telepon Layanan Anggota</label>
                    <input type="text" x-model="settings.phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>

                <div class="md:col-span-2">
                    <label class="block font-bold text-slate-700 mb-1.5">Alamat Kantor Pusat &amp; Gerai Utama</label>
                    <textarea x-model="settings.address" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Jam Buka Operasional</label>
                    <input type="time" x-model="settings.openTime" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Jam Tutup Operasional</label>
                    <input type="time" x-model="settings.closeTime" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc]">
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: OTORISASI & PIN KASIR -->
    <div x-show="activeTab === 'kebijakan'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                    <span class="material-symbols-rounded text-[22px]">verified_user</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Kebijakan Otorisasi PIN Kasir</h2>
                    <p class="text-xs text-slate-500">Tentukan transaksi apa saja yang wajib meminta PIN Supervisor / Owner saat kasir bertugas.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-medium">
                <div class="p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800">Wajib PIN untuk Pembatalan (Void)</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Kasir tidak dapat membatalkan item tanpa persetujuan SPV/Owner</div>
                    </div>
                    <button type="button" @click="settings.pinForVoid = !settings.pinForVoid"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                            :class="settings.pinForVoid ? 'bg-[#0052cc]' : 'bg-slate-300'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                              :class="settings.pinForVoid ? 'translate-x-4' : 'translate-x-0'"></span>
                    </button>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800">Wajib PIN untuk Retur &amp; Refund Barang</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Pengembalian dana pelanggan wajib diverifikasi</div>
                    </div>
                    <button type="button" @click="settings.pinForRefund = !settings.pinForRefund"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                            :class="settings.pinForRefund ? 'bg-[#0052cc]' : 'bg-slate-300'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                              :class="settings.pinForRefund ? 'translate-x-4' : 'translate-x-0'"></span>
                    </button>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800">Wajib PIN untuk Buka Laci Kas Manual</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Membuka laci kasir di luar transaksi pembayaran normal</div>
                    </div>
                    <button type="button" @click="settings.pinForDrawer = !settings.pinForDrawer"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                            :class="settings.pinForDrawer ? 'bg-[#0052cc]' : 'bg-slate-300'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                              :class="settings.pinForDrawer ? 'translate-x-4' : 'translate-x-0'"></span>
                    </button>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800">Wajib PIN untuk Override Plafon Anggota</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Meloloskan transaksi kredit saat plafon anggota telah mencapai 100%</div>
                    </div>
                    <button type="button" @click="settings.pinForCreditOverride = !settings.pinForCreditOverride"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                            :class="settings.pinForCreditOverride ? 'bg-[#0052cc]' : 'bg-slate-300'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
                              :class="settings.pinForCreditOverride ? 'translate-x-4' : 'translate-x-0'"></span>
                    </button>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Master PIN Supervisor / Owner (6 Digit)</label>
                    <input type="password" maxlength="6" x-model="settings.masterPin" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 outline-none focus:border-[#0052cc] font-mono">
                    <span class="text-[11px] text-slate-400 mt-1 block">PIN ini digunakan untuk membuka otorisasi di seluruh terminal kasir.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: DATA & BACKUP KOPERASI -->
    <div x-show="activeTab === 'data'" class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                    <span class="material-symbols-rounded text-[22px]">cloud_sync</span>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Penyimpanan &amp; Sinkronisasi Data</h2>
                    <p class="text-xs text-slate-500">Kelola cadangan data lokal koperasi (localStorage), ekspor berkas, dan reset data simulasi.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-2">
                    <div class="font-bold text-slate-900">Cadangkan Data Koperasi</div>
                    <p class="text-slate-500 text-[11.5px]">Unduh seluruh data pengguna, anggota, cabang, dan mitra konsinyasi dalam format JSON.</p>
                    <button type="button" @click="backupDataJson()" class="w-full py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 font-bold text-slate-700 transition-colors">
                        Unduh Backup JSON
                    </button>
                </div>

                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-2">
                    <div class="font-bold text-slate-900">Sinkronkan Ulang Semua Modul</div>
                    <p class="text-slate-500 text-[11.5px]">Segarkan status konsistensi antar modul Cabang, Plafon Anggota, dan Laporan.</p>
                    <button type="button" @click="syncAllModules()" class="w-full py-2 rounded-lg bg-[#0052cc] text-white hover:bg-[#0043a8] font-bold transition-colors">
                        Jalankan Sinkronisasi
                    </button>
                </div>

                <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/40 space-y-2">
                    <div class="font-bold text-rose-700">Bersihkan Data Operasional</div>
                    <p class="text-rose-600/80 text-[11.5px]">Kosongkan data sementara aplikasi ILP POS pada browser storage.</p>
                    <button type="button" @click="resetOperationalData()" class="w-full py-2 rounded-lg bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 font-bold transition-colors cursor-pointer">
                        Bersihkan Data
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function ownerSettingsApp() {
    return {
        activeTab: 'identitas',
        lastSaved: 'Hari ini, 09:15 WIB',

        settings: {
            storeName: 'Koperasi Pekerja ILP Pusat',
            coopLicense: 'AHU-0012984.AH.01.26.TAHUN 2024',
            taxId: '01.882.991.4-012.000',
            phone: '(021) 5589-2210',
            address: 'Jl. Industri Raya No. 45, Kawasan Terpadu ILP, Jakarta Timur',
            openTime: '07:00',
            closeTime: '22:00',
            pinForVoid: true,
            pinForRefund: true,
            pinForDrawer: true,
            pinForCreditOverride: true,
            masterPin: '882910'
        },

        async init() {
            try {
                const res = await fetch('/api/settings');
                if (res.ok) {
                    const result = await res.json();
                    if (result.success && result.data) {
                        const st = result.data.store || {};
                        const sec = result.data.security || {};
                        this.settings.storeName = st.store_name || this.settings.storeName;
                        this.settings.coopLicense = st.coop_license || this.settings.coopLicense;
                        this.settings.taxId = st.store_tax_id || this.settings.taxId;
                        this.settings.phone = st.store_phone || this.settings.phone;
                        this.settings.address = st.store_address || this.settings.address;
                        this.settings.openTime = st.open_time || this.settings.openTime;
                        this.settings.closeTime = st.close_time || this.settings.closeTime;

                        if (sec.pin_for_void !== undefined) this.settings.pinForVoid = sec.pin_for_void;
                        if (sec.pin_for_refund !== undefined) this.settings.pinForRefund = sec.pin_for_refund;
                        if (sec.pin_for_drawer !== undefined) this.settings.pinForDrawer = sec.pin_for_drawer;
                        if (sec.pin_for_credit_override !== undefined) this.settings.pinForCreditOverride = sec.pin_for_credit_override;
                        if (sec.master_pin) this.settings.masterPin = sec.master_pin;
                        return;
                    }
                }
            } catch(e) {
                console.warn('API /api/settings error, reading local cache', e);
            }

            try {
                const stored = localStorage.getItem('pos_ilp_store_settings_v1') || localStorage.getItem('pos_ilp_manager_settings_v1');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    this.settings = { ...this.settings, ...parsed };
                    if (parsed.savedAt) this.lastSaved = parsed.savedAt;
                }
            } catch(e) {}
        },

        async saveAllSettings() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            this.lastSaved = 'Baru saja, ' + hh + ':' + mm + ' WIB';
            this.settings.savedAt = this.lastSaved;

            try {
                await fetch('/api/settings/store', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        storeName: this.settings.storeName,
                        coopLicense: this.settings.coopLicense,
                        taxId: this.settings.taxId,
                        phone: this.settings.phone,
                        address: this.settings.address,
                        openTime: this.settings.openTime,
                        closeTime: this.settings.closeTime
                    })
                });

                await fetch('/api/settings/security', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        pinForVoid: this.settings.pinForVoid,
                        pinForRefund: this.settings.pinForRefund,
                        pinForDrawer: this.settings.pinForDrawer,
                        pinForCreditOverride: this.settings.pinForCreditOverride,
                        masterPin: this.settings.masterPin
                    })
                });
            } catch(e) {
                console.warn('API save settings error', e);
            }

            try {
                localStorage.setItem('pos_ilp_store_settings_v1', JSON.stringify(this.settings));
                localStorage.setItem('pos_ilp_manager_settings_v1', JSON.stringify(this.settings));
                if (window.triggerToast) {
                    window.triggerToast('Pengaturan Toko & Kebijakan Owner berhasil disimpan ke database!', 'success');
                }
            } catch(e) {
                if (window.triggerToast) window.triggerToast('Gagal menyimpan pengaturan: ' + e.message, 'error');
            }
        },

        backupDataJson() {
            try {
                const dump = {
                    users: JSON.parse(localStorage.getItem('pos_ilp_users_v1') || '[]'),
                    members: JSON.parse(localStorage.getItem('pos_ilp_members_v1') || '[]'),
                    umkm: JSON.parse(localStorage.getItem('pos_ilp_umkm_partners_v1') || '[]'),
                    branches: JSON.parse(localStorage.getItem('pos_ilp_branches_v1') || '[]'),
                    settings: this.settings,
                    exportedAt: new Date().toISOString()
                };
                const blob = new Blob([JSON.stringify(dump, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'backup_pos_ilp_' + new Date().toISOString().slice(0, 10) + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                if (window.triggerToast) window.triggerToast('Berkas cadangan JSON berhasil diunduh.', 'success');
            } catch(e) {
                if (window.triggerToast) window.triggerToast('Gagal membuat backup: ' + e.message, 'error');
            }
        },

        syncAllModules() {
            window.dispatchEvent(new CustomEvent('ilp-sync-data'));
            if (window.triggerToast) {
                window.triggerToast('Semua modul Owner & Terminal POS berhasil disinkronkan!', 'success');
            }
        },

        resetOperationalData() {
            if (confirm('Kosongkan seluruh data operasional sementara pada penyimpanan browser?')) {
                const keys = [
                    'pos_ilp_users_v1', 'pos_ilp_members_v1', 'pos_ilp_umkm_partners_v1',
                    'pos_ilp_inventory_products_v1', 'pos_ilp_branches_v1', 'pos_ilp_transactions_v1',
                    'pos_ilp_held_bills_v1', 'pos_ilp_audit_logs_v1', 'pos_ilp_credit_limit_requests_v1',
                    'pos_ilp_goods_receipts_v1', 'pos_ilp_returns_out_v1', 'pos_ilp_returns_in_v1'
                ];
                keys.forEach(k => localStorage.removeItem(k));
                if (window.triggerToast) {
                    window.triggerToast('Data operasional berhasil dikosongkan.', 'info');
                }
                setTimeout(() => window.location.reload(), 1000);
            }
        }
    };
}
</script>
@endsection
