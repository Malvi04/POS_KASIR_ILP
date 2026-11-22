@extends('layouts.app')

@section('title', 'Kupon Komoditas & Voucher - Manajerial ILP POS')

@section('content')
<div x-data="{
    coupons: [],
    isLoadingCoupons: false,
    activeCategory: 'all', // all | bbm | galon | pulsa | beras
    searchQuery: '',
    statusFilter: 'all', // all | TERVALIDASI | SIAP_AMBIL | SELESAI
    showNewKuponModal: false,
    showEditKuponModal: false,
    showDeleteModal: false,
    showDetailModal: false,
    isSavingKupon: false,
    deleteInputText: '',
    selectedKupon: null,
    editingKupon: null,
    toastMessage: '',
    showToast: false,

    // Form Catat Kupon Baru (Manager)
    formKupon: {
        category: 'bbm',
        code: '',
        recipientName: '',
        recipientNik: '',
        recipientDept: 'Operasional Toko',
        volume: '20 Liter',
        quota: 10,
        nominal: 200000,
        notes: ''
    },

    async initCoupons() {
        this.isLoadingCoupons = true;
        try {
            const res = await fetch('/api/coupons?per_page=100', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.coupons = json.data;
                    this.saveCoupons();
                    this.isLoadingCoupons = false;
                    return;
                }
            }
        } catch (e) {
            console.warn('Coupon API fetch error:', e);
        } finally {
            this.isLoadingCoupons = false;
        }

        this.coupons = [];
    },

    saveCoupons() {
        try {
            localStorage.setItem('pos_ilp_coupons_manager_v1', JSON.stringify(this.coupons));
        } catch(e) {}
    },

    get filteredCoupons() {
        return this.coupons.filter(k => {
            if (this.activeCategory !== 'all' && k.category !== this.activeCategory) return false;
            if (this.statusFilter !== 'all') {
                if (this.statusFilter === 'TERVALIDASI' && !(k.status === 'TERVALIDASI' || k.status === 'AVAILABLE')) return false;
                else if (this.statusFilter === 'SIAP_AMBIL' && !(k.status === 'SIAP_AMBIL' || k.status === 'ISSUED')) return false;
                else if (this.statusFilter === 'SELESAI' && !(k.status === 'SELESAI' || k.status === 'REDEEMED')) return false;
                else if (this.statusFilter !== 'TERVALIDASI' && this.statusFilter !== 'SIAP_AMBIL' && this.statusFilter !== 'SELESAI' && k.status !== this.statusFilter) return false;
            }
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                return (k.id || '').toLowerCase().includes(q) ||
                       (k.recipientName || k.recipient || '').toLowerCase().includes(q) ||
                       (k.recipientNik || k.nik || '').toLowerCase().includes(q) ||
                       (k.itemDetail || k.detail || '').toLowerCase().includes(q) ||
                       (k.notes || '').toLowerCase().includes(q);
            }
            return true;
        });
    },

    // 4 Unified Category KPI Computations
    get bbmCount() {
        return this.coupons.filter(k => k.category === 'bbm').length;
    },
    get galonCount() {
        return this.coupons.filter(k => k.category === 'galon').length;
    },
    get pulsaTotalNominal() {
        return this.coupons.filter(k => k.category === 'pulsa').reduce((sum, k) => sum + (Number(k.nominal || k.nominal_value) || 0), 0);
    },
    get berasCount() {
        return this.coupons.filter(k => k.category === 'beras').length;
    },

    openNewKuponModal() {
        const rand = Math.floor(1000 + Math.random() * 9000);
        this.formKupon = {
            category: 'bbm',
            code: '#KPN-BBM-' + rand,
            recipientName: '',
            recipientNik: '',
            recipientDept: 'Operasional Toko',
            volume: '20 Liter',
            quota: 10,
            nominal: 200000,
            notes: 'BBM Pertalite Armada Toko'
        };
        this.showNewKuponModal = true;
    },

    onCategorySelect(cat) {
        this.formKupon.category = cat;
        const rand = Math.floor(1000 + Math.random() * 9000);
        if (cat === 'bbm') {
            this.formKupon.code = '#KPN-BBM-' + rand;
            this.formKupon.volume = '20 Liter';
            this.formKupon.nominal = 200000;
            this.formKupon.notes = 'BBM Pertalite Armada Toko';
        } else if (cat === 'galon') {
            this.formKupon.code = '#KPN-GLN-' + rand;
            this.formKupon.volume = '5 Galon';
            this.formKupon.nominal = 100000;
            this.formKupon.notes = 'Tukar Galon Aqua 19L';
        } else if (cat === 'pulsa') {
            this.formKupon.code = '#KPN-PLS-' + rand;
            this.formKupon.volume = '1 Voucher';
            this.formKupon.nominal = 100000;
            this.formKupon.notes = 'Paket Data Driver & Operasional';
        } else if (cat === 'beras') {
            this.formKupon.code = '#KPN-BRS-' + rand;
            this.formKupon.volume = '2 Sak (10kg)';
            this.formKupon.nominal = 150000;
            this.formKupon.notes = 'Beras Premium Subsidi 5kg';
        }
    },

    openDetail(k) {
        this.selectedKupon = k;
        this.showDetailModal = true;
    },

    async redeemKupon(k) {
        if (k.raw_id) {
            try {
                const res = await fetch(`/api/coupons/${k.raw_id}/redeem`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        user_id: localStorage.getItem('pos_portal_logged_in_staff_id') || null
                    })
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || !json.success) {
                    alert(json.message || 'Gagal menukarkan kupon.');
                    return;
                }
            } catch (err) {
                alert('Gagal menghubungi server: ' + err.message);
                return;
            }
        }
        await this.initCoupons();
        this.triggerToast('Kupon ' + k.id + ' berhasil diverifikasi dan ditukarkan kepada ' + (k.recipientName || k.recipient) + '!');
        this.showDetailModal = false;
    },

    async saveNewKupon() {
        if (this.isSavingKupon) return;
        if (!this.formKupon.code) {
            alert('Mohon lengkapi nomor seri kupon!');
            return;
        }

        this.isSavingKupon = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('kpn-mgr-' + Date.now());
        try {
            const payload = {
                category: this.formKupon.category,
                serial_number: this.formKupon.code.toUpperCase().trim(),
                recipient_name: this.formKupon.recipientName ? this.formKupon.recipientName.trim() : '-',
                recipient_nik: this.formKupon.recipientNik ? this.formKupon.recipientNik.trim() : null,
                recipient_dept: this.formKupon.recipientDept || 'Operasional Toko',
                volume: this.formKupon.volume || '1 Unit',
                quota: Number(this.formKupon.quota) || 10,
                nominal_value: Number(this.formKupon.nominal) || 0,
                notes: this.formKupon.notes || null,
                status: 'TERVALIDASI'
            };

            const r = await fetch('/api/coupons', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify(payload)
            });

            const res = await r.json().catch(() => ({}));
            if (!r.ok || !res.success) {
                alert(res.message || 'Gagal menyimpan kupon ke database.');
                return;
            }

            await this.initCoupons();
            this.showNewKuponModal = false;
            this.triggerToast('Kupon ' + (res.data?.serial_number || payload.serial_number) + ' berhasil didaftarkan & stok tercatat di Gudang Pusat!');

            // Emit broadcast event agar fitur Kirim Barang segera memuat produk kupon baru
            window.dispatchEvent(new CustomEvent('pos:inventory_updated'));
            if (window.PosRealtime?.broadcast) {
                window.PosRealtime.broadcast('inventory_updated', { coupon_id: res.data?.id });
            }
        } catch (e) {
            console.error('Save coupon error:', e);
            alert('Terjadi kesalahan jaringan.');
        } finally {
            this.isSavingKupon = false;
        }
    },

    openEditKupon(k) {
        this.editingKupon = {
            raw_id: k.raw_id,
            id: k.id,
            category: k.category || 'bbm',
            recipientName: k.recipientName || k.recipient || '',
            recipientNik: k.recipientNik && k.recipientNik !== '-' ? k.recipientNik : (k.nik && k.nik !== '-' ? k.nik : ''),
            recipientRole: k.recipientRole || k.dept || 'Operasional Toko',
            qty: k.qty || k.volume || '1 Unit',
            nominal: Number(k.nominal || k.nominal_value) || 0,
            itemDetail: k.itemDetail || k.detail || '',
            status: k.status || 'TERVALIDASI',
            statusLabel: k.statusLabel || 'Tervalidasi (Siap Pakai)'
        };
        this.showEditKuponModal = true;
    },

    async saveEditKupon() {
        if (!this.editingKupon) return;
        if (!this.editingKupon.recipientName) {
            alert('Nama penerima wajib diisi!');
            return;
        }

        try {
            const payload = {
                category: this.editingKupon.category,
                recipient_name: this.editingKupon.recipientName,
                recipient_nik: this.editingKupon.recipientNik || null,
                recipient_dept: this.editingKupon.recipientRole,
                volume: this.editingKupon.qty,
                nominal_value: Number(this.editingKupon.nominal) || 0,
                notes: this.editingKupon.itemDetail,
                status: this.editingKupon.status
            };

            const res = await fetch(`/api/coupons/${this.editingKupon.raw_id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            if (res.ok) {
                await this.initCoupons();
                this.triggerToast('Kupon ' + this.editingKupon.id + ' berhasil diperbarui!');
            } else {
                alert('Gagal memperbarui kupon di server.');
            }
        } catch (err) {
            console.warn('Coupon edit API sync', err);
            alert('Terjadi kesalahan koneksi.');
        }

        this.showEditKuponModal = false;
        this.editingKupon = null;
    },

    openDeleteKupon(k) {
        this.selectedKupon = k;
        this.deleteInputText = '';
        this.showDeleteModal = true;
    },

    async confirmDeleteKupon() {
        if (!this.selectedKupon) return;
        if (this.deleteInputText.trim().toLowerCase() !== 'hapus') {
            alert('Ketik \'hapus\' untuk mengonfirmasi pembatalan kupon!');
            return;
        }
        const idToDelete = this.selectedKupon.id;
        const rawId = this.selectedKupon.raw_id;

        if (rawId) {
            try {
                await fetch(`/api/coupons/${rawId}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
            } catch (err) {
                console.warn('Coupon delete API sync', err);
            }
        }

        await this.initCoupons();
        this.showDeleteModal = false;
        this.selectedKupon = null;
        this.deleteInputText = '';
        this.triggerToast('Kupon ' + idToDelete + ' berhasil dihapus dari sistem!');
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initCoupons()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manajer -->
    <x-manager-sidebar active="kupon" />

    <!-- Main Area -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Kupon Komoditas</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">confirmation_number</span>
                    <span>Alokasi &amp; Voucher Terpadu</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="initCoupons()" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data Kupon">
                    <span class="material-symbols-rounded text-[24px]" :class="isLoadingCoupons ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">
            <div class="max-w-[1400px] mx-auto">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Kupon Komoditas &amp; Voucher</h1>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Pencatatan kupon BBM armada, galon air, pulsa operasional, dan beras subsidi untuk didistribusikan ke cabang</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="openNewKuponModal()"
                                class="px-4 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">add_box</span>
                            <span>Catat Kupon Baru</span>
                        </button>
                    </div>
                </div>

                <!-- 4 Unified Category Stat Cards (Identik dengan Kasir) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                    <!-- Card 1: BBM -->
                    <div class="bg-white rounded-xl p-5 border border-blue-200 shadow-xs cursor-pointer transition-all hover:border-[#0052cc]"
                         @click="activeCategory = 'bbm'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-blue-700 uppercase tracking-wider">KUPON BBM &amp; ARMADA</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">local_gas_station</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight" x-text="bbmCount + ' Lembar'"></h3>
                        <p class="text-[#0052cc] font-bold text-xs mt-0.5">Alokasi BBM Terdaftar</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Alokasi Armada</span>
                            <span class="font-bold text-emerald-600">Terpantau</span>
                        </div>
                    </div>

                    <!-- Card 2: Galon -->
                    <div class="bg-white rounded-xl p-5 border border-cyan-200 shadow-xs cursor-pointer transition-all hover:border-cyan-500"
                         @click="activeCategory = 'galon'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-cyan-800 uppercase tracking-wider">GALON AIR MINERAL</span>
                            <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">water_drop</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight" x-text="galonCount + ' Kupon'"></h3>
                        <p class="text-cyan-700 font-bold text-xs mt-0.5">Penukaran Galon</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Deposit Aset Toko</span>
                            <span class="font-bold text-gray-800">Siap Ditukar</span>
                        </div>
                    </div>

                    <!-- Card 3: Kuota & Pulsa -->
                    <div class="bg-white rounded-xl p-5 border border-orange-200 shadow-xs cursor-pointer transition-all hover:border-orange-500"
                         @click="activeCategory = 'pulsa'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-orange-800 uppercase tracking-wider">KUOTA DATA &amp; PULSA</span>
                            <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">bolt</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight" x-text="'Rp ' + pulsaTotalNominal.toLocaleString('id-ID')"></h3>
                        <p class="text-orange-700 font-bold text-xs mt-0.5">Total Nilai Kupon Pulsa</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Deposit Kasir</span>
                            <span class="font-bold text-emerald-600">Siap Validasi</span>
                        </div>
                    </div>

                    <!-- Card 4: Beras Subsidi -->
                    <div class="bg-white rounded-xl p-5 border border-emerald-200 shadow-xs cursor-pointer transition-all hover:border-emerald-500"
                         @click="activeCategory = 'beras'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-emerald-800 uppercase tracking-wider">BERAS KHUSUS / SUBSIDI</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">agriculture</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight" x-text="berasCount + ' Entri'"></h3>
                        <p class="text-emerald-700 font-bold text-xs mt-0.5">Klaim Beras Subsidi</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Distribusi Anggota</span>
                            <span class="font-bold text-blue-600">Terdaftar</span>
                        </div>
                    </div>

                </div>

                <!-- Unified Filter Bar with 4 Specific Category Switchers (Identik dengan Kasir) -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-xs flex flex-col gap-4 border-b-0">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <!-- Category Filter Pills -->
                        <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl overflow-x-auto w-full sm:w-auto">
                            <button type="button"
                                    @click="activeCategory = 'all'"
                                    :class="activeCategory === 'all' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap cursor-pointer">
                                Semua Kategori
                            </button>
                            <button type="button"
                                    @click="activeCategory = 'bbm'"
                                    :class="activeCategory === 'bbm' ? 'bg-white text-[#0052cc] shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>BBM</span>
                            </button>
                            <button type="button"
                                    @click="activeCategory = 'galon'"
                                    :class="activeCategory === 'galon' ? 'bg-white text-cyan-700 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                                <span>Galon</span>
                            </button>
                            <button type="button"
                                    @click="activeCategory = 'pulsa'"
                                    :class="activeCategory === 'pulsa' ? 'bg-white text-orange-700 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                <span>Kuota Pulsa</span>
                            </button>
                            <button type="button"
                                    @click="activeCategory = 'beras'"
                                    :class="activeCategory === 'beras' ? 'bg-white text-emerald-700 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all whitespace-nowrap flex items-center gap-1.5 cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>Beras</span>
                            </button>
                        </div>

                        <!-- Search Box -->
                        <div class="relative w-full sm:w-64">
                            <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="Cari no kupon, nama, NIK..."
                                   class="w-full pl-9 pr-3.5 py-1.5 bg-slate-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:bg-white transition-colors" />
                        </div>
                    </div>

                    <!-- Status Filter & Counter -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 flex-wrap gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 font-bold text-[11px] uppercase tracking-wider">Status:</span>
                            <div class="flex bg-gray-100 p-0.5 rounded-lg">
                                <button type="button" @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-white font-bold text-gray-900 shadow-2xs' : 'text-gray-500'" class="px-2.5 py-1 rounded text-[11px] cursor-pointer">Semua</button>
                                <button type="button" @click="statusFilter = 'TERVALIDASI'" :class="statusFilter === 'TERVALIDASI' ? 'bg-white font-bold text-blue-700 shadow-2xs' : 'text-gray-500'" class="px-2.5 py-1 rounded text-[11px] cursor-pointer">Tervalidasi</button>
                                <button type="button" @click="statusFilter = 'SIAP_AMBIL'" :class="statusFilter === 'SIAP_AMBIL' ? 'bg-white font-bold text-amber-700 shadow-2xs' : 'text-gray-500'" class="px-2.5 py-1 rounded text-[11px] cursor-pointer">Siap Ambil</button>
                                <button type="button" @click="statusFilter = 'SELESAI'" :class="statusFilter === 'SELESAI' ? 'bg-white font-bold text-emerald-700 shadow-2xs' : 'text-gray-500'" class="px-2.5 py-1 rounded text-[11px] cursor-pointer">Selesai Ditukar</button>
                            </div>
                        </div>

                        <span class="text-gray-500 font-mono text-[11px]" x-text="'Menampilkan ' + filteredCoupons.length + ' entri'"></span>
                    </div>
                </div>

                <!-- Table of Coupons (Identik dengan Kasir) -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[950px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                    <th class="px-5 py-3.5 text-left w-[140px]">No. Ref / Kupon</th>
                                    <th class="px-5 py-3.5 text-left w-[200px]">Kategori &amp; Detail</th>
                                    <th class="px-5 py-3.5 text-left w-[180px]">Penerima / Karyawan</th>
                                    <th class="px-5 py-3.5 text-right w-[110px]">Qty / Volume</th>
                                    <th class="px-5 py-3.5 text-left w-[140px]">Waktu Registrasi</th>
                                    <th class="px-5 py-3.5 text-center w-[120px]">Status</th>
                                    <th class="px-5 py-3.5 text-center w-[120px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="k in filteredCoupons" :key="k.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <button type="button"
                                                    @click="openDetail(k)"
                                                    class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer"
                                                    x-text="k.id"></button>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full shrink-0" :class="k.dotClass"></span>
                                                <div>
                                                    <div class="font-bold text-gray-900" x-text="k.itemDetail || k.detail"></div>
                                                    <span class="text-[10px] text-gray-400 font-mono uppercase" x-text="k.categoryLabel"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-gray-900" x-text="k.recipientName || k.recipient"></div>
                                            <div class="text-[11px] text-gray-500 flex items-center gap-1">
                                                <span x-text="k.recipientRole || k.dept"></span>
                                                <template x-if="k.vehicleNo && k.vehicleNo !== '-'">
                                                    <span class="font-mono text-gray-400" x-text="'(' + k.vehicleNo + ')'"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono font-black text-gray-900 whitespace-nowrap" x-text="k.qty || k.volume"></td>
                                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap font-mono text-[11px]" x-text="k.date || k.created_at"></td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                                  :class="{
                                                      'bg-blue-50 text-blue-700 border border-blue-200': k.status === 'TERVALIDASI' || k.status === 'AVAILABLE',
                                                      'bg-amber-50 text-amber-700 border border-amber-200': k.status === 'SIAP_AMBIL' || k.status === 'ISSUED',
                                                      'bg-emerald-50 text-emerald-700 border border-emerald-200': k.status === 'SELESAI' || k.status === 'REDEEMED'
                                                  }"
                                                  x-text="k.statusLabel"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center justify-center gap-1">
                                                <button type="button"
                                                        @click="openDetail(k)"
                                                        title="Lihat Detail & Cetak Bukti"
                                                        class="p-1 rounded-lg text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                                                </button>
                                                <button type="button"
                                                        @click="openEditKupon(k)"
                                                        title="Edit Data Kupon"
                                                        class="p-1 rounded-lg text-gray-500 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[18px]">edit</span>
                                                </button>
                                                <template x-if="k.status !== 'SELESAI' && k.status !== 'REDEEMED'">
                                                    <button type="button"
                                                            @click="redeemKupon(k)"
                                                            title="Tukarkan Kupon Sekarang"
                                                            class="p-1 rounded-lg text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">check_circle</span>
                                                    </button>
                                                </template>
                                                <button type="button"
                                                        @click="openDeleteKupon(k)"
                                                        title="Hapus / Batalkan Kupon"
                                                        class="p-1 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[18px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!filteredCoupons || filteredCoupons.length === 0">
                                    <td colspan="7" class="text-center py-12 text-gray-400 font-medium">
                                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-2 text-gray-400">
                                            <span class="material-symbols-rounded text-[24px]">confirmation_number</span>
                                        </div>
                                        <p class="text-sm">Belum ada data kupon komoditas.</p>
                                        <div class="mt-3">
                                            <button type="button" @click="openNewKuponModal()" class="px-4 py-2 bg-[#0052cc] text-white rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-sm hover:bg-[#0043a8] transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[16px]">add_box</span>
                                                <span>Catat Kupon Baru</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- MODAL CATAT KUPON BARU (Identik Kasir + Integrasi Kirim Barang Gudang Pusat) -->
    <template x-teleport="body">
        <div x-show="showNewKuponModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showNewKuponModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">add_box</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Registrasi &amp; Alokasi Kupon Baru</h3>
                            <p class="text-[11px] text-gray-500">Tersedia untuk pengiriman ke cabang via Kirim Barang &amp; kasir</p>
                        </div>
                    </div>
                    <button @click="showNewKuponModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveNewKupon()" class="p-6 space-y-4 text-xs font-sans">

                    <!-- Kategori Kupon (4 Pilihan Identik Kasir) -->
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Pilih Kategori Kupon <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-4 gap-2">
                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'bbm' ? 'border-[#0052cc] bg-blue-50 text-[#0052cc] font-bold' : 'border-gray-200 text-gray-600'"
                                   @click="onCategorySelect('bbm')">
                                <input type="radio" x-model="formKupon.category" value="bbm" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">local_gas_station</span>
                                <span class="text-[11px]">BBM</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'galon' ? 'border-cyan-600 bg-cyan-50 text-cyan-700 font-bold' : 'border-gray-200 text-gray-600'"
                                   @click="onCategorySelect('galon')">
                                <input type="radio" x-model="formKupon.category" value="galon" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">water_drop</span>
                                <span class="text-[11px]">Galon</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'pulsa' ? 'border-orange-600 bg-orange-50 text-orange-700 font-bold' : 'border-gray-200 text-gray-600'"
                                   @click="onCategorySelect('pulsa')">
                                <input type="radio" x-model="formKupon.category" value="pulsa" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">bolt</span>
                                <span class="text-[11px]">Pulsa</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'beras' ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-bold' : 'border-gray-200 text-gray-600'"
                                   @click="onCategorySelect('beras')">
                                <input type="radio" x-model="formKupon.category" value="beras" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">agriculture</span>
                                <span class="text-[11px]">Beras</span>
                            </label>
                        </div>
                    </div>

                    <!-- Nomor Kupon / Kode Ref -->
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Nomor Seri / Kode Kupon Fisik <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="formKupon.code"
                               placeholder="Contoh: #KPN-BBM-9921"
                               required
                               class="w-full px-3.5 py-2 border border-gray-300 rounded-xl font-mono uppercase focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <!-- Volume, Kuota Lembar Gudang, & Nilai -->
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Kuantitas / Volume</label>
                            <input type="text"
                                   x-model="formKupon.volume"
                                   placeholder="Misal: 20 Liter / 1 Sak"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Kuota Stok Gudang</label>
                            <input type="number"
                                   x-model="formKupon.quota"
                                   min="1"
                                   placeholder="10"
                                   title="Jumlah lembar stok fisik kupon di Gudang Pusat untuk dikirim ke cabang"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Estimasi Nilai (Rp)</label>
                            <input type="number"
                                   x-model="formKupon.nominal"
                                   placeholder="200000"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                        </div>
                    </div>

                    <!-- Keterangan / Deskripsi -->
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Keterangan / Nama Produk</label>
                        <input type="text"
                               x-model="formKupon.notes"
                               placeholder="Misal: BBM Pertalite Armada Pengiriman"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <!-- Banner Info Integrasi Kirim Barang -->
                    <div class="p-3 bg-blue-50/80 border border-blue-200 rounded-xl text-blue-900 flex items-start gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[20px] shrink-0 mt-0.5">local_shipping</span>
                        <div class="text-[11px] leading-relaxed">
                            <span class="font-bold text-blue-950 block">Otomatis Terhubung ke "Kirim Barang":</span>
                            Kupon ini akan langsung terdaftar di katalog master produk &amp; tercatat sebagai stok tersedia di <strong>Gudang Pusat</strong> sehingga dapat dipilih dan dikirimkan ke cabang toko manapun.
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                :disabled="isSavingKupon"
                                @click="showNewKuponModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            Batal
                        </button>
                        <button type="submit"
                                :disabled="isSavingKupon"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold transition-colors cursor-pointer flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="isSavingKupon" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                            <span x-text="isSavingKupon ? 'Menyimpan...' : 'Simpan & Daftarkan Kupon'"></span>
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </template>

    <!-- MODAL EDIT KUPON (Identik Kasir) -->
    <template x-teleport="body">
        <div x-show="showEditKuponModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showEditKuponModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-amber-600 text-[22px]">edit</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Edit Data Kupon</h3>
                            <p class="text-[11px] text-gray-500" x-text="editingKupon?.id"></p>
                        </div>
                    </div>
                    <button @click="showEditKuponModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <template x-if="editingKupon">
                    <form @submit.prevent="saveEditKupon()" class="p-6 space-y-4 text-xs font-sans">

                        <!-- Kategori Kupon -->
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Kategori Kupon</label>
                            <select x-model="editingKupon.category" class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]">
                                <option value="bbm">BBM &amp; Armada</option>
                                <option value="galon">Galon Air Mineral</option>
                                <option value="pulsa">Kuota &amp; Pulsa</option>
                                <option value="beras">Beras Subsidi</option>
                            </select>
                        </div>

                        <!-- Penerima -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Nama Penerima <span class="text-red-500">*</span></label>
                                <input type="text"
                                       x-model="editingKupon.recipientName"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">NIK / ID</label>
                                <input type="text"
                                       x-model="editingKupon.recipientNik"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                            </div>
                        </div>

                        <!-- Volume & Nilai -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Kuantitas / Volume</label>
                                <input type="text"
                                       x-model="editingKupon.qty"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                            </div>
                            <div>
                                <label class="font-bold text-gray-700 block mb-1">Estimasi Nilai (Rp)</label>
                                <input type="number"
                                       x-model="editingKupon.nominal"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                            </div>
                        </div>

                        <!-- Keterangan / Deskripsi -->
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Detail Barang / Komoditas</label>
                            <input type="text"
                                   x-model="editingKupon.itemDetail"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Status Kupon</label>
                            <select x-model="editingKupon.status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]">
                                <option value="TERVALIDASI">TERVALIDASI</option>
                                <option value="SIAP_AMBIL">SIAP_AMBIL</option>
                                <option value="SELESAI">SELESAI</option>
                            </select>
                        </div>

                        <div class="pt-2 flex justify-end gap-2">
                            <button type="button"
                                    @click="showEditKuponModal = false"
                                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition-colors cursor-pointer">
                                Batal
                            </button>
                            <button type="submit"
                                    class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold transition-colors cursor-pointer">
                                Simpan Perubahan
                            </button>
                        </div>

                    </form>
                </template>

            </div>
        </div>
    </template>

    <!-- MODAL HAPUS KUPON DENGAN KETIK 'hapus' (Identik Kasir) -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showDeleteModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-red-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-red-600 text-[22px]">warning</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Hapus / Batalkan Kupon</h3>
                            <p class="text-[11px] text-gray-500">Konfirmasi pembatalan kupon permanen</p>
                        </div>
                    </div>
                    <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <p class="text-gray-600">
                        Anda akan menghapus kupon <strong class="text-gray-900" x-text="selectedKupon?.id"></strong> atas nama <strong class="text-gray-900" x-text="selectedKupon?.recipientName || selectedKupon?.recipient"></strong>. Tindakan ini tidak dapat dibatalkan.
                    </p>

                    <div class="bg-gray-50 p-3 rounded-xl border border-gray-200">
                        <label class="block font-bold text-gray-700 mb-1">
                            Ketik kata <span class="text-red-600 font-mono font-black">hapus</span> untuk konfirmasi:
                        </label>
                        <input type="text"
                               x-model="deleteInputText"
                               placeholder="ketik hapus di sini"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono focus:outline-none focus:border-red-500" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button"
                                @click="showDeleteModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="button"
                                @click="confirmDeleteKupon()"
                                :disabled="deleteInputText.trim().toLowerCase() !== 'hapus'"
                                :class="deleteInputText.trim().toLowerCase() === 'hapus' ? 'bg-red-600 hover:bg-red-700 text-white cursor-pointer' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-5 py-2 rounded-xl font-bold transition-colors">
                            Hapus Kupon
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL DETAIL & BUKTI PENUKARAN KUPON (Identik Kasir) -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">confirmation_number</span>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Bukti Alokasi &amp; Penukaran Kupon</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedKupon?.id"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-sans">
                    <div class="bg-slate-50 p-4 rounded-xl border border-gray-200 space-y-1.5 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kategori:</span>
                            <span class="font-bold text-gray-900 uppercase" x-text="selectedKupon?.categoryLabel"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Komoditas:</span>
                            <span class="font-bold text-blue-700" x-text="selectedKupon?.itemDetail || selectedKupon?.detail"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Penerima:</span>
                            <span class="text-gray-800" x-text="selectedKupon?.recipientName || selectedKupon?.recipient"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">NIK / ID:</span>
                            <span class="text-gray-800" x-text="selectedKupon?.recipientNik || selectedKupon?.nik"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kuantitas:</span>
                            <span class="font-black text-gray-900" x-text="selectedKupon?.qty || selectedKupon?.volume"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nilai Kupon:</span>
                            <span class="font-bold text-emerald-700" x-text="'Rp ' + Number(selectedKupon?.nominal || selectedKupon?.nominal_value || 0).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Waktu Registrasi:</span>
                            <span class="text-gray-700" x-text="selectedKupon?.date || selectedKupon?.created_at"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl flex justify-between items-center">
                        <span class="font-bold text-emerald-900">Status Kupon:</span>
                        <span class="font-bold text-emerald-700" x-text="selectedKupon?.statusLabel"></span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Bukti</span>
                    </button>
                    <template x-if="selectedKupon?.status !== 'SELESAI' && selectedKupon?.status !== 'REDEEMED'">
                        <button type="button"
                                @click="redeemKupon(selectedKupon)"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-rounded text-[16px]">check_circle</span>
                            <span>Konfirmasi Tukar</span>
                        </button>
                    </template>
                    <button type="button"
                            @click="showDetailModal = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
