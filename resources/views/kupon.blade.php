@extends('layouts.app')

@section('title', 'Kupon Terpadu - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeCategory: 'all', // all | bbm | galon | pulsa | beras
    searchQuery: '',
    statusFilter: 'all',
    showNewKuponModal: false,
    showDetailModal: false,
    selectedKupon: null,
    toastMessage: '',
    showToast: false,

    // Form Catat Kupon
    formKupon: {
        category: 'bbm',
        code: '',
        recipientName: '',
        recipientNik: '',
        recipientDept: 'Logistik & Armada',
        volume: '20 Liter',
        nominal: 200000,
        notes: ''
    },

    coupons: [
        {
            id: '#KPN-BBM-8841',
            category: 'bbm',
            categoryLabel: 'BBM & Armada',
            badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
            dotClass: 'bg-blue-500',
            itemDetail: 'BBM Pertalite (Armada Toko)',
            recipientName: 'Andi Pratama',
            recipientRole: 'Driver Logistik #03',
            recipientNik: '327501002230004',
            vehicleNo: 'B 9481 UQ',
            qty: '20 Liter',
            nominal: 200000,
            date: '24 Okt 2026 • 08:30 WIB',
            cashier: 'Budi Kurniawan',
            status: 'TERVALIDASI',
            statusLabel: 'Tervalidasi (Siap Pakai)'
        },
        {
            id: '#KPN-BRS-1092',
            category: 'beras',
            categoryLabel: 'Beras Subsidi',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            dotClass: 'bg-emerald-500',
            itemDetail: 'Beras Premium Subsidi 5kg',
            recipientName: 'Dedi Mulyadi',
            recipientRole: 'Divisi Gudang ILP',
            recipientNik: '327501004421009',
            vehicleNo: '-',
            qty: '1 Sak (5 kg)',
            nominal: 65000,
            date: '24 Okt 2026 • 09:12 WIB',
            cashier: 'Budi Kurniawan',
            status: 'SIAP_AMBIL',
            statusLabel: 'Siap Ambil di Kasir'
        },
        {
            id: '#KPN-GLN-0043',
            category: 'galon',
            categoryLabel: 'Galon Air Mineral',
            badgeClass: 'bg-cyan-50 text-cyan-700 border-cyan-200',
            dotClass: 'bg-cyan-500',
            itemDetail: 'Tukar Galon Aqua 19L',
            recipientName: 'Kantin ILP Central',
            recipientRole: 'Mitra Usaha Koperasi',
            recipientNik: 'MITRA-KOP-001',
            vehicleNo: '-',
            qty: '6 Unit Galon',
            nominal: 120000,
            date: '24 Okt 2026 • 09:45 WIB',
            cashier: 'Budi Kurniawan',
            status: 'SELESAI',
            statusLabel: 'Selesai Ditukar'
        },
        {
            id: '#KPN-PLS-9218',
            category: 'pulsa',
            categoryLabel: 'Kuota & Pulsa',
            badgeClass: 'bg-orange-50 text-orange-700 border-orange-200',
            dotClass: 'bg-orange-500',
            itemDetail: 'Token Listrik Prabayar 100k',
            recipientName: 'Operasional Freezer Toko',
            recipientRole: 'Sarana & Prasarana',
            recipientNik: 'MTR-32019981',
            vehicleNo: '-',
            qty: 'Token PLN 100k',
            nominal: 102500,
            date: '24 Okt 2026 • 10:15 WIB',
            cashier: 'Budi Kurniawan',
            status: 'SELESAI',
            statusLabel: 'Selesai Ditukar'
        },
        {
            id: '#KPN-BBM-8842',
            category: 'bbm',
            categoryLabel: 'BBM & Armada',
            badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
            dotClass: 'bg-blue-500',
            itemDetail: 'BBM Bio Solar (Genset Toko)',
            recipientName: 'Tim Maintenance Listrik',
            recipientRole: 'Pemeliharaan Gedung',
            recipientNik: '327501009930011',
            vehicleNo: 'GENSET-01',
            qty: '35 Liter',
            nominal: 238000,
            date: '24 Okt 2026 • 10:40 WIB',
            cashier: 'Budi Kurniawan',
            status: 'TERVALIDASI',
            statusLabel: 'Tervalidasi (Siap Pakai)'
        },
        {
            id: '#KPN-PLS-9225',
            category: 'pulsa',
            categoryLabel: 'Kuota & Pulsa',
            badgeClass: 'bg-orange-50 text-orange-700 border-orange-200',
            dotClass: 'bg-orange-500',
            itemDetail: 'Voucher Kuota Data Telkomsel 25GB',
            recipientName: 'Citra Dewi',
            recipientRole: 'Staff Layanan Pelanggan',
            recipientNik: '327501440230012',
            vehicleNo: '-',
            qty: '1 Voucher 25GB',
            nominal: 85000,
            date: '24 Okt 2026 • 11:05 WIB',
            cashier: 'Budi Kurniawan',
            status: 'TERVALIDASI',
            statusLabel: 'Tervalidasi (Siap Pakai)'
        },
        {
            id: '#KPN-GLN-0044',
            category: 'galon',
            categoryLabel: 'Galon Air Mineral',
            badgeClass: 'bg-cyan-50 text-cyan-700 border-cyan-200',
            dotClass: 'bg-cyan-500',
            itemDetail: 'Tukar Galon Aqua 19L',
            recipientName: 'Ruang Rapat Pengurus Koperasi',
            recipientRole: 'Internal Koperasi',
            recipientNik: 'INT-KOP-002',
            vehicleNo: '-',
            qty: '2 Unit Galon',
            nominal: 40000,
            date: '24 Okt 2026 • 11:30 WIB',
            cashier: 'Budi Kurniawan',
            status: 'SIAP_AMBIL',
            statusLabel: 'Siap Ambil di Kasir'
        }
    ],

    get filteredCoupons() {
        return this.coupons.filter(k => {
            if (this.activeCategory !== 'all' && k.category !== this.activeCategory) return false;
            if (this.statusFilter !== 'all' && k.status !== this.statusFilter) return false;
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                return k.id.toLowerCase().includes(q) ||
                       k.recipientName.toLowerCase().includes(q) ||
                       k.recipientNik.toLowerCase().includes(q) ||
                       k.itemDetail.toLowerCase().includes(q);
            }
            return true;
        });
    },

    openDetail(k) {
        this.selectedKupon = k;
        this.showDetailModal = true;
    },

    redeemKupon(k) {
        k.status = 'SELESAI';
        k.statusLabel = 'Selesai Ditukar';
        this.triggerToast('Kupon ' + k.id + ' berhasil diverifikasi dan ditukarkan kepada ' + k.recipientName + '!');
        this.showDetailModal = false;
    },

    saveNewKupon() {
        if (!this.formKupon.code || !this.formKupon.recipientName) {
            alert('Mohon lengkapi nomor kupon dan nama penerima!');
            return;
        }

        let label = 'BBM & Armada';
        let badge = 'bg-blue-50 text-blue-700 border-blue-200';
        let dot = 'bg-blue-500';
        if (this.formKupon.category === 'beras') { label = 'Beras Subsidi'; badge = 'bg-emerald-50 text-emerald-700 border-emerald-200'; dot = 'bg-emerald-500'; }
        if (this.formKupon.category === 'galon') { label = 'Galon Air Mineral'; badge = 'bg-cyan-50 text-cyan-700 border-cyan-200'; dot = 'bg-cyan-500'; }
        if (this.formKupon.category === 'pulsa') { label = 'Kuota & Pulsa'; badge = 'bg-orange-50 text-orange-700 border-orange-200'; dot = 'bg-orange-500'; }

        const newEntry = {
            id: this.formKupon.code.toUpperCase(),
            category: this.formKupon.category,
            categoryLabel: label,
            badgeClass: badge,
            dotClass: dot,
            itemDetail: this.formKupon.notes || label,
            recipientName: this.formKupon.recipientName,
            recipientRole: this.formKupon.recipientDept,
            recipientNik: this.formKupon.recipientNik || 'NIK-PENDING',
            vehicleNo: '-',
            qty: this.formKupon.volume || '1 Unit',
            nominal: Number(this.formKupon.nominal) || 0,
            date: 'Hari Ini • Baru',
            cashier: 'Budi Kurniawan',
            status: 'TERVALIDASI',
            statusLabel: 'Tervalidasi (Siap Pakai)'
        };

        this.coupons.unshift(newEntry);
        this.showNewKuponModal = false;
        this.triggerToast('Kupon ' + newEntry.id + ' berhasil didaftarkan dan tervalidasi!');

        // Reset form
        this.formKupon.code = '';
        this.formKupon.recipientName = '';
        this.formKupon.recipientNik = '';
        this.formKupon.notes = '';
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="kupon" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Kupon Terpadu" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Kupon & Komoditas Khusus</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Terpadu 4 Kategori
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Verifikasi kupon BBM Armada, Tukar Galon, Kuota Pulsa, dan Beras Subsidi.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="showNewKuponModal = true"
                                class="px-4 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">add_box</span>
                            <span>Catat Kupon Baru</span>
                        </button>
                    </div>
                </div>

                <!-- 4 Unified Category Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                    <!-- Card 1: BBM -->
                    <div class="bg-white rounded-xl p-5 border border-blue-200 shadow-xs cursor-pointer transition-all hover:border-[#0052cc]"
                         @click="activeCategory = 'bbm'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-blue-700 uppercase tracking-wider">KUPON BBM & ARMADA</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">local_gas_station</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight">340 Lembar</h3>
                        <p class="text-[#0052cc] font-bold text-xs mt-0.5">850 Liter Sisa Kuota</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Alokasi Armada</span>
                            <span class="font-bold text-emerald-600">68% Kuota Aman</span>
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
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight">54 Isi <span class="text-sm font-normal text-gray-400">/ 28 Ksg</span></h3>
                        <p class="text-cyan-700 font-bold text-xs mt-0.5">Siap Tukar Suplier: 28 Unit</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Deposit Aset Toko</span>
                            <span class="font-bold text-gray-800">82 Tabung Galon</span>
                        </div>
                    </div>

                    <!-- Card 3: Kuota & Pulsa -->
                    <div class="bg-white rounded-xl p-5 border border-orange-200 shadow-xs cursor-pointer transition-all hover:border-orange-500"
                         @click="activeCategory = 'pulsa'">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[11px] font-extrabold text-orange-800 uppercase tracking-wider">KUOTA DATA & PULSA</span>
                            <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                                <span class="material-symbols-rounded text-[18px]">bolt</span>
                            </div>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight">Rp 8.450.000</h3>
                        <p class="text-orange-700 font-bold text-xs mt-0.5">Saldo Deposit Kasir</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Aktivitas Hari Ini</span>
                            <span class="font-bold text-emerald-600">38 Transaksi Sukses</span>
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
                        <h3 class="text-2xl font-black text-gray-900 font-mono tracking-tight">125 Sak (5kg)</h3>
                        <p class="text-emerald-700 font-bold text-xs mt-0.5">Tersedia di Gudang Toko</p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center text-[11px]">
                            <span class="text-gray-500 font-medium">Klaim Minggu Ini</span>
                            <span class="font-bold text-blue-600">42 Sak Terdistribusi</span>
                        </div>
                    </div>

                </div>

                <!-- Unified Filter Bar with 4 Specific Category Switchers -->
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

                <!-- Table of Coupons -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs overflow-hidden">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-[10px] font-black uppercase tracking-widest">
                                <th class="px-5 py-3.5">No. Ref / Kupon</th>
                                <th class="px-5 py-3.5">Kategori & Detail</th>
                                <th class="px-5 py-3.5">Penerima / Karyawan</th>
                                <th class="px-5 py-3.5 text-right">Qty / Volume</th>
                                <th class="px-5 py-3.5">Waktu Registrasi</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="k in filteredCoupons" :key="k.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <button type="button"
                                                @click="openDetail(k)"
                                                class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer"
                                                x-text="k.id"></button>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full shrink-0" :class="k.dotClass"></span>
                                            <div>
                                                <div class="font-bold text-gray-900" x-text="k.itemDetail"></div>
                                                <span class="text-[10px] text-gray-400 font-mono uppercase" x-text="k.categoryLabel"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900" x-text="k.recipientName"></div>
                                        <div class="text-[11px] text-gray-500 flex items-center gap-1">
                                            <span x-text="k.recipientRole"></span>
                                            <template x-if="k.vehicleNo && k.vehicleNo !== '-'">
                                                <span class="font-mono text-gray-400" x-text="'(' + k.vehicleNo + ')'"></span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-gray-900" x-text="k.qty"></td>
                                    <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap font-mono text-[11px]" x-text="k.date"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                              :class="{
                                                  'bg-blue-50 text-blue-700 border border-blue-200': k.status === 'TERVALIDASI',
                                                  'bg-amber-50 text-amber-700 border border-amber-200': k.status === 'SIAP_AMBIL',
                                                  'bg-emerald-50 text-emerald-700 border border-emerald-200': k.status === 'SELESAI'
                                              }"
                                              x-text="k.statusLabel"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button"
                                                    @click="openDetail(k)"
                                                    title="Lihat Detail & Cetak Bukti"
                                                    class="p-1 rounded-lg text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                                            </button>
                                            <template x-if="k.status !== 'SELESAI'">
                                                <button type="button"
                                                        @click="redeemKupon(k)"
                                                        title="Tukarkan Kupon Sekarang"
                                                        class="p-1 rounded-lg text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50 transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[18px]">check_circle</span>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredCoupons.length === 0">
                                <td colspan="7" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">confirmation_number</span>
                                    <p class="font-bold text-gray-600">Tidak ada kupon yang cocok</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Silakan pilih kategori lain atau periksa kata kunci pencarian.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL CATAT KUPON BARU -->
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
                            <h3 class="font-bold text-gray-900 text-sm">Registrasi & Verifikasi Kupon Baru</h3>
                            <p class="text-[11px] text-gray-500">Penukaran komoditas khusus di kasir</p>
                        </div>
                    </div>
                    <button @click="showNewKuponModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <form @submit.prevent="saveNewKupon()" class="p-6 space-y-4 text-xs font-sans">

                    <!-- Kategori Kupon -->
                    <div>
                        <label class="font-bold text-gray-700 block mb-1">Pilih Kategori Kupon <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-4 gap-2">
                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'bbm' ? 'border-[#0052cc] bg-blue-50 text-[#0052cc] font-bold' : 'border-gray-200 text-gray-600'">
                                <input type="radio" x-model="formKupon.category" value="bbm" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">local_gas_station</span>
                                <span class="text-[11px]">BBM</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'galon' ? 'border-cyan-600 bg-cyan-50 text-cyan-700 font-bold' : 'border-gray-200 text-gray-600'">
                                <input type="radio" x-model="formKupon.category" value="galon" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">water_drop</span>
                                <span class="text-[11px]">Galon</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'pulsa' ? 'border-orange-600 bg-orange-50 text-orange-700 font-bold' : 'border-gray-200 text-gray-600'">
                                <input type="radio" x-model="formKupon.category" value="pulsa" class="sr-only">
                                <span class="material-symbols-rounded text-[20px] block mx-auto mb-0.5">bolt</span>
                                <span class="text-[11px]">Pulsa</span>
                            </label>

                            <label class="border rounded-xl p-2.5 text-center cursor-pointer transition-all"
                                   :class="formKupon.category === 'beras' ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-bold' : 'border-gray-200 text-gray-600'">
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

                    <!-- Penerima -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Nama Penerima <span class="text-red-500">*</span></label>
                            <input type="text"
                                   x-model="formKupon.recipientName"
                                   placeholder="Nama karyawan / anggota"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                        </div>
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">NIK / ID Karyawan</label>
                            <input type="text"
                                   x-model="formKupon.recipientNik"
                                   placeholder="Nomor NIK"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl font-mono focus:outline-none focus:border-[#0052cc]" />
                        </div>
                    </div>

                    <!-- Volume & Nilai -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-gray-700 block mb-1">Kuantitas / Volume</label>
                            <input type="text"
                                   x-model="formKupon.volume"
                                   placeholder="Misal: 20 Liter / 1 Sak"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
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
                        <label class="font-bold text-gray-700 block mb-1">Keterangan Tambahan</label>
                        <input type="text"
                               x-model="formKupon.notes"
                               placeholder="Misal: Pengisian armada pengiriman sayur B 9481 UQ"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-[#0052cc]" />
                    </div>

                    <div class="p-3 bg-blue-50/60 border border-blue-100 rounded-xl text-blue-900 flex items-start gap-2">
                        <span class="material-symbols-rounded text-blue-600 text-[18px] shrink-0">info</span>
                        <span>Kupon yang disimpan akan langsung berstatus <strong>Tervalidasi</strong> dan siap ditukarkan di meja kasir.</span>
                    </div>

                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button"
                                @click="showNewKuponModal = false"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl font-bold transition-colors cursor-pointer">
                            Simpan & Validasi
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </template>

    <!-- MODAL DETAIL & BUKTI PENUKARAN KUPON -->
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
                            <h3 class="font-bold text-gray-900 text-sm">Bukti Penukaran Kupon</h3>
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
                            <span class="font-bold text-blue-700" x-text="selectedKupon?.itemDetail"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Penerima:</span>
                            <span class="text-gray-800" x-text="selectedKupon?.recipientName"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">NIK / ID:</span>
                            <span class="text-gray-800" x-text="selectedKupon?.recipientNik"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kuantitas:</span>
                            <span class="font-black text-gray-900" x-text="selectedKupon?.qty"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Waktu Registrasi:</span>
                            <span class="text-gray-700" x-text="selectedKupon?.date"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Petugas Kasir:</span>
                            <span class="text-gray-700" x-text="selectedKupon?.cashier"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50/60 border border-emerald-200 rounded-xl flex justify-between items-center">
                        <span class="font-bold text-emerald-900">Status Penukaran:</span>
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
                    <template x-if="selectedKupon?.status !== 'SELESAI'">
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
