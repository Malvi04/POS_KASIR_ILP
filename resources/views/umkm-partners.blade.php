@extends('layouts.app')

@section('title', 'Manajemen Mitra UMKM - ILP POS')

@section('content')
<div x-data="{
    subPage: 'mitra',
    searchQuery: '',
    categoryFilter: 'all',
    statusFilter: 'all',
    productSearch: '',
    productPartnerFilter: 'all',
    showAddModal: false,
    selectedPartner: null,
    showDetailModal: false,
    newName: '',
    newLocation: '',
    newCategory: 'Snack',
    newSkuCount: 5,
    newPhone: '0812-3456-7890',
    toastMessage: '',
    showToast: false,

    selectedMitraKey: 'dapur_bu_eni',
    formProductName: 'Keripik Singkong Balado Renyah 150gr',
    formBarcode: 'KNS-8991204-551',
    formProductCategory: 'Makanan Ringan Tradisional',
    formShelfLife: 'awet',
    formDropDate: '05/14/2025',
    formExpDate: '11/14/2025',
    formCommissionMethod: 'persentase',
    formCommissionPercent: 15,
    formNetPrice: 8500,
    formPosMargin: 1500,
    formSellPrice: 10000,
    formDropQty: 50,
    formRackLocation: 'Cabang Utama - Rak Display UMKM 01',
    formReturnPolicy: 'retur_100',
    formSettlement: 'mingguan',
    formPhotoLabel: 'Keripik Balado Bu Eni',
    formPirtLabel: 'P-IRT: 215327301099-26 • Halal ID',

    onNetPriceChange() {
        const net = parseInt(this.formNetPrice) || 0;
        if (this.formCommissionMethod === 'persentase') {
            this.formSellPrice = Math.round(net / 0.85);
            this.formPosMargin = this.formSellPrice - net;
        } else if (this.formCommissionMethod === 'custom') {
            const pct = parseFloat(this.formCommissionPercent) || 0;
            this.formPosMargin = Math.round(net * (pct / 100));
            this.formSellPrice = net + this.formPosMargin;
        } else {
            this.formSellPrice = net + (parseInt(this.formPosMargin) || 0);
        }
    },

    onMarginChange() {
        const net = parseInt(this.formNetPrice) || 0;
        const margin = parseInt(this.formPosMargin) || 0;
        this.formSellPrice = net + margin;
        if (net > 0) {
            this.formCommissionPercent = Math.round((margin / this.formSellPrice) * 100);
        }
    },

    onPercentChange() {
        const net = parseInt(this.formNetPrice) || 0;
        const pct = Math.min(95, Math.max(0, parseFloat(this.formCommissionPercent) || 0));
        this.formPosMargin = Math.round(net * (pct / 100));
        this.formSellPrice = net + this.formPosMargin;
    },

    onSellPriceChange() {
        const net = parseInt(this.formNetPrice) || 0;
        const sell = parseInt(this.formSellPrice) || 0;
        this.formPosMargin = Math.max(0, sell - net);
        if (sell > 0) {
            this.formCommissionPercent = Math.round((this.formPosMargin / sell) * 100);
        }
    },

    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3200);
    },

    partners: [
        {
            id: 5,
            key: 'dapur_bu_eni',
            initial: 'D',
            name: 'Dapur Bu Eni - Keripik Nusantara',
            location: 'Jakarta Timur, DKI Jakarta',
            category: 'Snack',
            sku: 8,
            stockStatus: 'aman',
            stockLabel: 'Aman',
            totalSales: 'Rp 6.450.000',
            status: 'AKTIF',
            regCode: 'UMKM-JKT-042',
            picWhatsapp: 'Ibu Eni Suhartini - 0813-8899-7711',
            bankName: 'BCA',
            bankAccount: '8830-192-881',
            bankHolder: 'a/n Dapur Eni Nusantara',
            phone: '0813-8899-7711'
        },
        {
            id: 1,
            key: 'keripik_ibu_siti',
            initial: 'K',
            name: 'Keripik Ibu Siti',
            location: 'Sleman, Yogyakarta',
            category: 'Snack',
            sku: 12,
            stockStatus: 'aman',
            stockLabel: 'Aman',
            totalSales: 'Rp 4.250.000',
            status: 'AKTIF',
            regCode: 'UMKM-YGY-018',
            picWhatsapp: 'Ibu Siti Aminah - 0812-8890-1122',
            bankName: 'BRI',
            bankAccount: '0341-01-029384-502',
            bankHolder: 'a/n Keripik Ibu Siti Sleman',
            phone: '0812-8890-1122'
        },
        {
            id: 2,
            key: 'batik_cantik_arum',
            initial: 'B',
            name: 'Batik Cantik Arum',
            location: 'Solo, Jawa Tengah',
            category: 'Kerajinan',
            sku: 45,
            stockStatus: 'restock',
            stockLabel: 'Perlu Restock',
            totalSales: 'Rp 15.800.000',
            status: 'AKTIF',
            regCode: 'UMKM-SLO-009',
            picWhatsapp: 'Ibu Arum Kusuma - 0813-4455-9900',
            bankName: 'MANDIRI',
            bankAccount: '138-00-1928374-2',
            bankHolder: 'a/n Galeri Batik Cantik Arum',
            phone: '0813-4455-9900'
        },
        {
            id: 3,
            key: 'madu_hutan_asli',
            initial: 'M',
            name: 'Madu Hutan Asli',
            location: 'Bogor, Jawa Barat',
            category: 'Minuman',
            sku: 5,
            stockStatus: 'aman',
            stockLabel: 'Aman',
            totalSales: 'Rp 2.100.000',
            status: 'AKTIF',
            regCode: 'UMKM-BGR-027',
            picWhatsapp: 'Bpk. Hendra Wijaya - 0857-1122-3344',
            bankName: 'BSI',
            bankAccount: '7192-8374-11',
            bankHolder: 'a/n Koperasi Madu Hutan Bogor',
            phone: '0857-1122-3344'
        },
        {
            id: 4,
            key: 'telor_asin_brebes',
            initial: 'T',
            name: 'Telor Asin Brebes Maknyus',
            location: 'Brebes, Jawa Tengah',
            category: 'Snack',
            sku: 2,
            stockStatus: 'kosong',
            stockLabel: 'Kosong',
            totalSales: 'Rp 0',
            status: 'NON-AKTIF',
            regCode: 'UMKM-BRB-034',
            picWhatsapp: 'Bpk. Wahyu Hidayat - 0819-7788-5566',
            bankName: 'BNI',
            bankAccount: '0892-1123-44',
            bankHolder: 'a/n Telor Asin Maknyus Brebes',
            phone: '0819-7788-5566'
        }
    ],

    partnerProducts: [
        {
            id: 101,
            partnerKey: 'dapur_bu_eni',
            partnerName: 'Dapur Bu Eni - Keripik Nusantara',
            regCode: 'UMKM-JKT-042',
            name: 'Keripik Singkong Balado Renyah 150gr',
            barcode: 'KNS-8991204-551',
            category: 'Makanan Ringan Tradisional',
            shelfLife: 'Kemasan Awet (3-6 Bln)',
            expDate: '11/14/2025',
            netPrice: 8500,
            posMargin: 1500,
            sellPrice: 10000,
            stockQty: 50,
            rack: 'Cabang Utama - Rak Display UMKM 01',
            returnPolicy: 'Retur 100% ke Mitra UMKM',
            settlement: 'Mingguan (Tiap Jumat Sore)'
        },
        {
            id: 102,
            partnerKey: 'keripik_ibu_siti',
            partnerName: 'Keripik Ibu Siti',
            regCode: 'UMKM-YGY-018',
            name: 'Keripik Singkong Pedas Daun Jeruk 250g',
            barcode: 'KNS-8991204-118',
            category: 'Makanan Ringan Tradisional',
            shelfLife: 'Kemasan Awet (3-6 Bln)',
            expDate: '10/20/2025',
            netPrice: 12750,
            posMargin: 2250,
            sellPrice: 15000,
            stockQty: 5,
            rack: 'Cabang Utama - Rak Display UMKM 01',
            returnPolicy: 'Retur 100% ke Mitra UMKM',
            settlement: 'Mingguan (Tiap Jumat Sore)'
        },
        {
            id: 103,
            partnerKey: 'batik_cantik_arum',
            partnerName: 'Batik Cantik Arum',
            regCode: 'UMKM-SLO-009',
            name: 'Kain Batik Parang Klasik Solo',
            barcode: 'KNS-8991204-210',
            category: 'Kerajinan & Wastra Lokal',
            shelfLife: 'Kemasan Awet (Non-Expired)',
            expDate: '-',
            netPrice: 72250,
            posMargin: 12750,
            sellPrice: 85000,
            stockQty: 2,
            rack: 'Cabang Utama - Galeri Kerajinan 02',
            returnPolicy: 'Retur 100% ke Mitra UMKM',
            settlement: 'Bulanan (Tanggal 1)'
        },
        {
            id: 104,
            partnerKey: 'madu_hutan_asli',
            partnerName: 'Madu Hutan Asli',
            regCode: 'UMKM-BGR-027',
            name: 'Madu Hutan Odeng Murni 350ml',
            barcode: 'KNS-8991204-305',
            category: 'Minuman Herbal & Kesehatan',
            shelfLife: 'Kemasan Awet (3-6 Bln)',
            expDate: '12/30/2025',
            netPrice: 55250,
            posMargin: 9750,
            sellPrice: 65000,
            stockQty: 28,
            rack: 'Cabang Utama - Rak Kesehatan 03',
            returnPolicy: 'Diskon Clearance Toko (-30%)',
            settlement: 'Bi-Weekly (2 Minggu Sekali)'
        }
    ],

    get currentFormMitra() {
        return this.partners.find(p => p.key === this.selectedMitraKey) || this.partners[0];
    },

    get calculatedMargin() {
        return parseInt(this.formPosMargin) || 0;
    },

    formatNumber(num) {
        return (parseInt(num) || 0).toLocaleString('id-ID');
    },

    get filteredPartners() {
        return this.partners.filter(p => {
            if (p.key === 'dapur_bu_eni' && this.searchQuery.trim() === '' && this.categoryFilter === 'all' && this.statusFilter === 'all') {
                return false;
            }
            if (this.categoryFilter !== 'all' && p.category !== this.categoryFilter) return false;
            if (this.statusFilter !== 'all' && p.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                return p.name.toLowerCase().includes(q) || p.location.toLowerCase().includes(q) || p.category.toLowerCase().includes(q);
            }
            return true;
        });
    },

    get filteredProducts() {
        return this.partnerProducts.filter(item => {
            if (this.productPartnerFilter !== 'all' && item.partnerKey !== this.productPartnerFilter) return false;
            if (this.productSearch.trim() !== '') {
                const q = this.productSearch.toLowerCase();
                return item.name.toLowerCase().includes(q) || item.barcode.toLowerCase().includes(q) || item.partnerName.toLowerCase().includes(q);
            }
            return true;
        });
    },

    openInputProductFor(partnerKey) {
        if (partnerKey) {
            this.selectedMitraKey = partnerKey;
        }
        this.subPage = 'input_produk';
    },

    openProductListFor(partnerKey) {
        this.productPartnerFilter = partnerKey || 'all';
        this.subPage = 'produk';
    },

    generateRandomKnsBarcode() {
        const rand = Math.floor(100 + Math.random() * 899);
        this.formBarcode = 'KNS-8991204-' + rand;
        this.triggerToast('Kode Barcode KNS baru berhasil digenerate: ' + this.formBarcode);
    },

    saveConsignmentProduct() {
        if (!this.formProductName.trim()) {
            this.triggerToast('Mohon isi Nama Produk Titipan Kasir terlebih dahulu.');
            return;
        }
        const mitra = this.currentFormMitra;
        this.partnerProducts.unshift({
            id: Date.now(),
            partnerKey: mitra.key,
            partnerName: mitra.name,
            regCode: mitra.regCode,
            name: this.formProductName.trim(),
            barcode: this.formBarcode.trim() || 'KNS-8991204-999',
            category: this.formProductCategory,
            shelfLife: this.formShelfLife === 'awet' ? 'Kemasan Awet (3-6 Bln)' : 'Cepat Basi (< 3 Hari)',
            expDate: this.formExpDate,
            netPrice: parseInt(this.formNetPrice) || 8500,
            posMargin: this.calculatedMargin,
            sellPrice: parseInt(this.formSellPrice) || 10000,
            stockQty: parseInt(this.formDropQty) || 50,
            rack: this.formRackLocation,
            returnPolicy: this.formReturnPolicy === 'retur_100' ? 'Retur 100% ke Mitra UMKM' : 'Diskon Clearance Toko (-30%)',
            settlement: this.formSettlement === 'mingguan' ? 'Mingguan (Tiap Jumat Sore)' : (this.formSettlement === 'biweekly' ? 'Bi-Weekly (2 Minggu Sekali)' : 'Bulanan (Tanggal 1)')
        });
        mitra.sku = (parseInt(mitra.sku) || 0) + 1;
        this.productPartnerFilter = 'all';
        this.subPage = 'produk';
        this.triggerToast('Produk konsinyasi (' + this.formProductName.trim() + ') berhasil disimpan ke List Produk Mitra!');
    },

    addPartner() {
        if (!this.newName.trim()) return;
        const initChar = this.newName.trim().charAt(0).toUpperCase();
        const keySlug = 'mitra_' + Date.now();
        const codeNum = Math.floor(45 + Math.random() * 50);
        this.partners.push({
            id: Date.now(),
            key: keySlug,
            initial: initChar,
            name: this.newName.trim(),
            location: this.newLocation.trim() || 'Jakarta, Indonesia',
            category: this.newCategory,
            sku: parseInt(this.newSkuCount) || 1,
            stockStatus: 'aman',
            stockLabel: 'Aman',
            totalSales: 'Rp 0',
            status: 'AKTIF',
            regCode: 'UMKM-JKT-0' + codeNum,
            picWhatsapp: this.newName.trim() + ' - ' + this.newPhone,
            bankName: 'BCA',
            bankAccount: '8830-551-' + codeNum,
            bankHolder: 'a/n ' + this.newName.trim(),
            phone: this.newPhone
        });
        this.selectedMitraKey = keySlug;
        this.showAddModal = false;
        this.triggerToast('Mitra UMKM (' + this.newName.trim() + ') berhasil didaftarkan.');
        this.newName = '';
        this.newLocation = '';
        this.newSkuCount = 5;
    },

    deletePartner(id, name) {
        this.partners = this.partners.filter(p => p.id !== id);
        this.triggerToast('Data mitra (' + name + ') telah dihapus.');
    },

    toggleStatus(partner) {
        partner.status = partner.status === 'AKTIF' ? 'NON-AKTIF' : 'AKTIF';
        this.triggerToast('Status mitra (' + partner.name + ') diubah menjadi ' + partner.status + '.');
    }
}" class="flex h-screen w-full bg-[#f8fafc] font-sans text-slate-800 overflow-hidden">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="umkm" />

    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">

        <!-- Top Navbar (Dynamic Title Matching Design Reference) -->
        <header class="h-[64px] bg-white border-b border-slate-200/80 px-8 flex items-center justify-between shrink-0 z-10">
            <div class="flex items-center gap-4">
                <h2 class="text-[18px] font-black text-[#0f172a] tracking-tight"
                    x-text="subPage === 'input_produk' ? 'Input Produk Mitra UMKM' : (subPage === 'produk' ? 'List Produk Mitra UMKM' : 'Manajemen Mitra UMKM')">
                    Manajemen Mitra UMKM
                </h2>
                <span class="h-5 w-px bg-slate-200 hidden sm:inline-block"></span>
            </div>
            <div class="flex items-center gap-3">
                <button class="relative w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[21px]">notifications</span>
                    <span class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                </button>
                <button class="w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-600 transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[21px]">help_outline</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Main Content -->
        <main class="flex-1 overflow-y-auto px-6 lg:px-8 py-6">
            <div class="max-w-[1440px] mx-auto space-y-6">

                <!-- ========================================================= -->
                <!-- SUB-PAGE 1: DAFTAR MITRA UMKM (MANAJEMEN MITRA UMKM)      -->
                <!-- ========================================================= -->
                <div x-show="subPage === 'mitra'" class="space-y-6">
                    
                    <!-- Breadcrumb, Title & Sub-Navigation Buttons -->
                    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-400 mb-1.5">
                                <span>Manajemen</span>
                                <span>/</span>
                                <span class="text-[#0052cc] font-bold">Mitra UMKM</span>
                            </div>
                            <h1 class="text-[26px] lg:text-[28px] font-black text-[#0f172a] tracking-tight leading-tight">Manajemen Mitra UMKM</h1>
                            <p class="text-[13.5px] text-slate-500 font-medium mt-1">
                                Kelola data mitra produsen dan produk konsinyasi Anda secara efisien.
                            </p>
                        </div>

                        <!-- Action Buttons: List Produk Mitra, Tambah Produk Mitra, Tambah Mitra Baru -->
                        <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-auto shrink-0">
                            <button @click="subPage = 'produk'"
                                    class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-4 py-3 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">inventory_2</span>
                                <span>List Produk Mitra</span>
                                <span class="bg-[#dbeafe] text-[#0052cc] text-[10.5px] font-extrabold px-2 py-0.5 rounded-full" x-text="partnerProducts.length">4</span>
                            </button>

                            <button @click="openInputProductFor('dapur_bu_eni')"
                                    class="bg-[#eff6ff] hover:bg-[#dbeafe] text-[#0052cc] border border-blue-200 px-4 py-3 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2 transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">add_box</span>
                                <span>Tambah Produk Mitra</span>
                            </button>

                            <button @click="showAddModal = true"
                                    class="bg-[#0047b3] hover:bg-[#003994] text-white px-5 py-3 rounded-xl text-[13px] font-extrabold inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">add</span>
                                <span>Tambah Mitra Baru</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 KPI Summary Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                        <!-- Card 1 -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded text-[22px]">handshake</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[12.5px] font-semibold text-slate-500">Total Mitra Aktif</div>
                                <div class="text-[22px] font-black text-[#0f172a] tracking-tight mt-0.5 leading-tight">42 Mitra</div>
                                <div class="text-[11px] font-extrabold text-[#16a34a] mt-1.5">+3 bulan ini</div>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div @click="subPage = 'produk'" class="bg-white hover:border-blue-300 border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex items-start gap-4 transition-colors cursor-pointer" title="Klik untuk melihat List Produk Mitra">
                            <div class="w-11 h-11 rounded-xl bg-[#e2e8f0]/80 text-[#1e3a8a] flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[12.5px] font-semibold text-slate-500">Produk Titipan (SKU)</div>
                                <div class="text-[22px] font-black text-[#0f172a] tracking-tight mt-0.5 leading-tight">158 SKU</div>
                                <div class="text-[11px] font-medium text-slate-400 mt-1.5">Rata-rata 4 SKU/Mitra</div>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-[#ffedd5] text-[#9a3412] flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded text-[22px]">shopping_bag</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[12.5px] font-semibold text-slate-500">Penjualan Konsinyasi</div>
                                <div class="text-[21px] font-black text-[#0f172a] font-mono tracking-tight mt-0.5 leading-tight">Rp 12.4M</div>
                                <div class="text-[11px] font-extrabold text-[#16a34a] mt-1.5">Bulan Berjalan</div>
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-[#fee2e2] text-[#b91c1c] flex items-center justify-center shrink-0">
                                <span class="material-symbols-rounded text-[22px]">account_balance_wallet</span>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[12.5px] font-semibold text-slate-500">Tagihan Unpaid</div>
                                <div class="text-[21px] font-black text-[#dc2626] font-mono tracking-tight mt-0.5 leading-tight">Rp 4.1M</div>
                                <div class="text-[11px] font-extrabold text-[#dc2626] mt-1.5">8 Invoice Jatuh Tempo</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter Bar Card -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 shadow-2xs">
                        <div class="relative flex-1 max-w-xl">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[19px]">search</span>
                            <input type="text" x-model="searchQuery"
                                   placeholder="Cari nama mitra atau nama produk..."
                                   class="w-full bg-[#f8fafc] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-10 pr-4 py-2.5 text-[13px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none transition-all">
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="text-[12.5px] font-semibold text-slate-500 mr-0.5">Filter:</span>

                            <div class="relative">
                                <select x-model="categoryFilter"
                                        class="bg-white hover:bg-slate-50 border border-slate-200/90 text-slate-700 text-[12.5px] font-semibold pl-3.5 pr-9 py-2.5 rounded-xl appearance-none cursor-pointer focus:outline-none focus:border-blue-500 transition-colors">
                                    <option value="all">Semua Kategori</option>
                                    <option value="Snack">Snack</option>
                                    <option value="Kerajinan">Kerajinan</option>
                                    <option value="Minuman">Minuman</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                            </div>

                            <div class="relative">
                                <select x-model="statusFilter"
                                        class="bg-white hover:bg-slate-50 border border-slate-200/90 text-slate-700 text-[12.5px] font-semibold pl-3.5 pr-9 py-2.5 rounded-xl appearance-none cursor-pointer focus:outline-none focus:border-blue-500 transition-colors">
                                    <option value="all">Semua Status</option>
                                    <option value="AKTIF">Aktif</option>
                                    <option value="NON-AKTIF">Non-Aktif</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                            </div>

                            <button @click="triggerToast('Mengekspor data Mitra UMKM ke format CSV...')"
                                    class="bg-white hover:bg-slate-50 border border-slate-200/90 text-slate-700 text-[12.5px] font-bold px-4 py-2.5 rounded-xl inline-flex items-center gap-2 transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[17px]">download</span>
                                <span>Ekspor CSV</span>
                            </button>

                            <button @click="searchQuery = ''; categoryFilter = 'all'; statusFilter = 'all'; triggerToast('Data tabel mitra telah diperbarui.')"
                                    title="Muat Ulang Data"
                                    class="w-10 h-10 rounded-xl bg-white hover:bg-slate-50 border border-slate-200/90 text-slate-700 flex items-center justify-center transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[19px]">refresh</span>
                            </button>
                        </div>
                    </div>

                    <!-- Partners Data Table Card -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[960px]">
                                <thead>
                                    <tr class="bg-[#f8fafc] border-b border-slate-200/80 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                                        <th class="py-4 px-6 w-[270px]">NAMA MITRA</th>
                                        <th class="py-4 px-4 w-[130px]">KATEGORI</th>
                                        <th class="py-4 px-4 w-[90px]">SKU</th>
                                        <th class="py-4 px-4 w-[155px]">STATUS STOK</th>
                                        <th class="py-4 px-4 w-[165px]">TOTAL PENJUALAN</th>
                                        <th class="py-4 px-4 w-[120px]">STATUS</th>
                                        <th class="py-4 px-6 text-center w-[190px]">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-[13.5px]">
                                    <template x-for="partner in filteredPartners" :key="partner.id">
                                        <tr class="hover:bg-slate-50/70 transition-colors"
                                            :class="partner.status === 'NON-AKTIF' ? 'bg-slate-50/30' : ''">
                                            
                                            <!-- 1. NAMA MITRA -->
                                            <td class="py-4 px-6">
                                                <div class="min-w-0">
                                                    <div class="font-extrabold leading-snug truncate"
                                                         :class="partner.status === 'NON-AKTIF' ? 'text-slate-600' : 'text-[#0f172a]'"
                                                         x-text="partner.name"></div>
                                                    <div class="text-[12px] font-medium truncate mt-0.5"
                                                         :class="partner.status === 'NON-AKTIF' ? 'text-slate-400' : 'text-slate-500'"
                                                         x-text="partner.location"></div>
                                                </div>
                                            </td>

                                            <!-- 2. KATEGORI -->
                                            <td class="py-4 px-4 font-medium whitespace-nowrap"
                                                :class="partner.status === 'NON-AKTIF' ? 'text-slate-400' : 'text-slate-700'"
                                                x-text="partner.category"></td>

                                            <!-- 3. SKU -->
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <button @click="openProductListFor(partner.key)"
                                                        title="Lihat daftar produk mitra ini"
                                                        class="font-bold hover:text-[#0052cc] hover:underline cursor-pointer"
                                                        :class="partner.status === 'NON-AKTIF' ? 'text-slate-400' : 'text-slate-800'"
                                                        x-text="partner.sku"></button>
                                            </td>

                                            <!-- 4. STATUS STOK -->
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <template x-if="partner.stockStatus === 'aman'">
                                                    <span class="inline-flex items-center gap-1.5 text-[13px] font-bold text-[#16a34a]">
                                                        <span class="material-symbols-rounded text-[17px]">check_circle</span>
                                                        <span>Aman</span>
                                                    </span>
                                                </template>
                                                <template x-if="partner.stockStatus === 'restock'">
                                                    <span class="inline-flex items-center gap-1.5 text-[13px] font-extrabold text-[#d97706]">
                                                        <span class="material-symbols-rounded text-[17px]">error</span>
                                                        <span>Perlu Restock</span>
                                                    </span>
                                                </template>
                                                <template x-if="partner.stockStatus === 'kosong'">
                                                    <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-slate-400">
                                                        <span class="material-symbols-rounded text-[17px]">inventory_2</span>
                                                        <span>Kosong</span>
                                                    </span>
                                                </template>
                                            </td>

                                            <!-- 5. TOTAL PENJUALAN -->
                                            <td class="py-4 px-4 font-mono font-bold whitespace-nowrap"
                                                :class="partner.status === 'NON-AKTIF' ? 'text-slate-400' : 'text-slate-800'"
                                                x-text="partner.totalSales"></td>

                                            <!-- 6. STATUS -->
                                            <td class="py-4 px-4 whitespace-nowrap">
                                                <button @click="toggleStatus(partner)"
                                                        title="Klik untuk ubah status"
                                                        class="px-3 py-1 rounded-full text-[10.5px] font-extrabold tracking-wide uppercase transition-all cursor-pointer"
                                                        :class="partner.status === 'AKTIF' ? 'bg-[#dcfce7] text-[#15803d] hover:bg-emerald-200/70' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                                        x-text="partner.status">
                                                </button>
                                            </td>

                                            <!-- 7. AKSI -->
                                            <td class="py-4 px-6">
                                                <div class="flex items-center justify-center gap-1.5"
                                                     :class="partner.status === 'NON-AKTIF' ? 'opacity-65 hover:opacity-100 transition-opacity' : ''">
                                                    <button @click="openInputProductFor(partner.key)"
                                                            title="Tambah Produk Konsinyasi Mitra Ini"
                                                            class="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#0052cc] text-[11px] font-extrabold inline-flex items-center gap-1 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[15px]">add_circle</span>
                                                        <span>Produk</span>
                                                    </button>
                                                    <button @click="openProductListFor(partner.key)"
                                                            title="Lihat Produk Mitra"
                                                            class="w-8 h-8 rounded-lg hover:bg-blue-50 text-[#0052cc] flex items-center justify-center transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                                    </button>
                                                    <button @click="selectedPartner = partner; showDetailModal = true"
                                                            title="Edit Data Mitra"
                                                            class="w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-600 flex items-center justify-center transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                                    </button>
                                                    <button @click="deletePartner(partner.id, partner.name)"
                                                            title="Hapus Mitra"
                                                            class="w-8 h-8 rounded-lg hover:bg-red-50 text-red-600 flex items-center justify-center transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="px-6 py-4 bg-white border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-[12.5px]">
                            <div class="text-slate-500 font-medium">
                                Menampilkan <span class="font-extrabold text-slate-800" x-text="'1 - ' + filteredPartners.length">1 - 4</span> dari <span class="font-extrabold text-slate-800">42</span> mitra
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 flex items-center justify-center cursor-not-allowed">
                                    <span class="material-symbols-rounded text-[16px]">chevron_left</span>
                                </button>
                                <button class="w-8 h-8 rounded-lg bg-[#0047b3] text-white font-extrabold text-[12px] flex items-center justify-center shadow-2xs">1</button>
                                <button class="w-8 h-8 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-[12px] flex items-center justify-center transition-colors cursor-pointer">2</button>
                                <button class="w-8 h-8 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-[12px] flex items-center justify-center transition-colors cursor-pointer">3</button>
                                <button class="w-8 h-8 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 flex items-center justify-center transition-colors cursor-pointer">
                                    <span class="material-symbols-rounded text-[16px]">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Section: Peringatan Restock Produk Konsinyasi & Insight Bulan Ini -->
                    <div class="grid grid-cols-12 gap-6">
                        <div class="col-span-12 lg:col-span-8 bg-white border border-slate-200/80 border-l-4 border-l-[#ea580c] rounded-2xl p-6 shadow-2xs flex flex-col justify-between">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-rounded text-[20px] text-[#ea580c]">campaign</span>
                                        <h3 class="text-[15.5px] font-black text-[#0f172a]">Peringatan Restock Produk Konsinyasi</h3>
                                    </div>
                                    <p class="text-[12.5px] text-slate-500 font-medium mt-1">
                                        Mitra berikut memiliki stok produk yang menipis atau kosong di toko.
                                    </p>
                                </div>
                                <button @click="subPage = 'produk'"
                                        class="text-[12.5px] font-extrabold text-[#0052cc] hover:underline whitespace-nowrap self-start cursor-pointer">
                                    Lihat Semua Draft PO
                                </button>
                            </div>

                            <div class="mt-5 space-y-3.5">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-orange-200/80 transition-colors">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#fff7ed] text-[#ea580c] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">warning</span>
                                        </div>
                                        <div>
                                            <div class="text-[13.5px] text-slate-700 font-bold">
                                                <strong class="font-extrabold text-[#0f172a]">Batik Cantik Arum</strong> - Kain Batik Parang
                                            </div>
                                            <div class="text-[11.5px] font-extrabold text-[#dc2626] mt-0.5">
                                                Sisa Stok: 2 pcs (Min: 10)
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="triggerToast('Menghubungi Batik Cantik Arum via WhatsApp Terintegrasi...')"
                                            class="bg-[#e2e8f0]/80 hover:bg-[#cbd5e1] text-[#0f172a] text-[12px] font-extrabold px-4 py-2 rounded-xl transition-colors self-end sm:self-center shrink-0 cursor-pointer">
                                        Hubungi Mitra
                                    </button>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-slate-50/70 border border-slate-100 hover:border-orange-200/80 transition-colors">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#fff7ed] text-[#ea580c] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">warning</span>
                                        </div>
                                        <div>
                                            <div class="text-[13.5px] text-slate-700 font-bold">
                                                <strong class="font-extrabold text-[#0f172a]">Keripik Ibu Siti</strong> - Keripik Singkong Pedas 250g
                                            </div>
                                            <div class="text-[11.5px] font-extrabold text-[#dc2626] mt-0.5">
                                                Sisa Stok: 5 pcs (Min: 20)
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="triggerToast('Menghubungi Keripik Ibu Siti via WhatsApp Terintegrasi...')"
                                            class="bg-[#e2e8f0]/80 hover:bg-[#cbd5e1] text-[#0f172a] text-[12px] font-extrabold px-4 py-2 rounded-xl transition-colors self-end sm:self-center shrink-0 cursor-pointer">
                                        Hubungi Mitra
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-span-12 lg:col-span-4 bg-gradient-to-br from-[#0047b3] to-[#0052cc] text-white rounded-2xl p-6 shadow-md flex flex-col justify-between">
                            <div>
                                <h3 class="text-[16.5px] font-extrabold tracking-tight">Insight Bulan Ini</h3>
                                <p class="text-[13px] text-blue-100 font-medium leading-relaxed mt-2.5">
                                    Produk Kerajinan mengalami kenaikan permintaan sebesar 24% minggu ini.
                                </p>
                            </div>

                            <div class="mt-8 pt-4">
                                <div class="flex items-center justify-between text-[11px] font-extrabold tracking-wider uppercase">
                                    <span class="text-blue-100">TARGET PENJUALAN UMKM</span>
                                    <span class="text-[13px] text-white">82%</span>
                                </div>
                                <div class="w-full h-2 bg-blue-950/40 rounded-full overflow-hidden mt-2">
                                    <div class="w-[82%] h-full bg-white rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- SUB-PAGE 2: LIST PRODUK MITRA UMKM (KATALOG KONSINYASI)   -->
                <!-- ========================================================= -->
                <div x-show="subPage === 'produk'" style="display: none;" class="space-y-6">
                    
                    <!-- Top Navigation & Action Bar -->
                    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 text-[12px] font-semibold text-slate-400 mb-1.5">
                                <button @click="subPage = 'mitra'" class="hover:text-[#0052cc] cursor-pointer">Manajemen Mitra UMKM</button>
                                <span>/</span>
                                <span class="text-[#0052cc] font-bold">List Produk Mitra</span>
                            </div>
                            <h1 class="text-[25px] lg:text-[27px] font-black text-[#0f172a] tracking-tight leading-tight">Daftar Produk Konsinyasi Mitra UMKM</h1>
                            <p class="text-[13px] text-slate-500 font-medium mt-1">
                                Pantau seluruh katalog SKU titipan mitra, skema bagi hasil POS, lokasi rak display, dan masa kedaluwarsa.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5 self-start lg:self-auto shrink-0">
                            <button @click="subPage = 'mitra'"
                                    class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px] text-slate-600">storefront</span>
                                <span>Lihat Daftar Mitra</span>
                            </button>

                            <button @click="openInputProductFor(productPartnerFilter !== 'all' ? productPartnerFilter : 'dapur_bu_eni')"
                                    class="bg-[#0047b3] hover:bg-[#003994] text-white px-5 py-2.5 rounded-xl text-[12.5px] font-extrabold inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">add_circle</span>
                                <span>+ Tambah Produk Mitra</span>
                            </button>
                        </div>
                    </div>

                    <!-- Filter Bar for Products -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 shadow-2xs">
                        <div class="relative flex-1 max-w-md">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input type="text" x-model="productSearch"
                                   placeholder="Cari nama produk, barcode KNS, atau nama mitra..."
                                   class="w-full bg-[#f8fafc] border border-slate-200/90 focus:border-blue-500 focus:bg-white rounded-xl pl-10 pr-4 py-2.5 text-[12.5px] font-medium text-slate-700 placeholder-slate-400 focus:outline-none transition-all">
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="text-[12px] font-bold text-slate-500">Filter Mitra:</span>
                            <div class="relative">
                                <select x-model="productPartnerFilter"
                                        class="bg-[#f8fafc] hover:bg-slate-100 border border-slate-200/90 text-slate-800 text-[12.5px] font-bold pl-3.5 pr-9 py-2.5 rounded-xl appearance-none cursor-pointer focus:outline-none focus:border-blue-500">
                                    <option value="all">Semua Mitra UMKM Terdaftar</option>
                                    <template x-for="m in partners" :key="m.key">
                                        <option :value="m.key" x-text="m.name + ' (' + m.regCode + ')'"></option>
                                    </template>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                            </div>

                            <button @click="productPartnerFilter = 'all'; productSearch = ''"
                                    class="px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12px] font-extrabold transition-colors cursor-pointer">
                                Reset
                            </button>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[1060px]">
                                <thead>
                                    <tr class="bg-[#f8fafc] border-b border-slate-200/80 text-[10.5px] font-extrabold text-slate-500 uppercase tracking-wider">
                                        <th class="py-3.5 px-5 w-[250px]">PRODUK & BARCODE KASIR</th>
                                        <th class="py-3.5 px-4 w-[210px]">MITRA PENYUPLAI</th>
                                        <th class="py-3.5 px-4 w-[200px]">SKEMA HARGA & MARGIN</th>
                                        <th class="py-3.5 px-4 w-[180px]">STOK DROP & DAYA TAHAN</th>
                                        <th class="py-3.5 px-4 w-[190px]">KEBIJAKAN RETUR & RAK</th>
                                        <th class="py-3.5 px-5 text-center w-[130px]">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-[12.5px]">
                                    <template x-for="prod in filteredProducts" :key="prod.id">
                                        <tr class="hover:bg-slate-50/70 transition-colors align-top">
                                            <td class="py-4 px-5">
                                                <div class="font-extrabold text-[#0f172a] text-[13px] leading-snug" x-text="prod.name"></div>
                                                <div class="inline-flex items-center gap-1.5 bg-[#f1f5f9] text-[#0052cc] font-mono font-bold text-[11px] px-2 py-0.5 rounded mt-1.5">
                                                    <span class="material-symbols-rounded text-[13px]">barcode</span>
                                                    <span x-text="prod.barcode"></span>
                                                </div>
                                                <div class="text-[11px] text-slate-400 font-medium mt-1" x-text="prod.category"></div>
                                            </td>

                                            <td class="py-4 px-4">
                                                <div class="font-extrabold text-slate-800" x-text="prod.partnerName"></div>
                                                <div class="text-[11px] font-mono font-bold text-slate-500 mt-0.5" x-text="'#' + prod.regCode"></div>
                                                <span class="inline-block mt-1.5 bg-[#dcfce7] text-[#15803d] text-[10px] font-extrabold px-2 py-0.5 rounded-md">Aktif Sinergi</span>
                                            </td>

                                            <td class="py-4 px-4">
                                                <div class="space-y-1 text-[11.5px]">
                                                    <div class="flex justify-between gap-2">
                                                        <span class="text-slate-400 font-medium">Titipan Net:</span>
                                                        <span class="font-mono font-bold text-slate-700" x-text="'Rp ' + formatNumber(prod.netPrice)"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-2">
                                                        <span class="text-[#0052cc] font-medium">Bagi Hasil (15%):</span>
                                                        <span class="font-mono font-bold text-[#0052cc]" x-text="'Rp ' + formatNumber(prod.posMargin)"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-2 pt-1 border-t border-slate-100">
                                                        <span class="font-extrabold text-slate-800">Harga Jual:</span>
                                                        <span class="font-mono font-black text-[#16a34a]" x-text="'Rp ' + formatNumber(prod.sellPrice)"></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="py-4 px-4">
                                                <div class="font-mono font-black text-[13.5px] text-slate-900" x-text="prod.stockQty + ' Pcs / Bks'"></div>
                                                <div class="text-[11px] font-bold text-[#0052cc] mt-1" x-text="prod.shelfLife"></div>
                                                <div class="text-[11px] text-slate-400 font-mono mt-0.5" x-text="'ED: ' + prod.expDate"></div>
                                            </td>

                                            <td class="py-4 px-4">
                                                <div class="text-[11.5px] font-bold text-slate-800 leading-snug" x-text="prod.rack"></div>
                                                <div class="text-[11px] text-slate-500 font-medium mt-1" x-text="prod.returnPolicy"></div>
                                                <div class="text-[10.5px] font-extrabold text-[#15803d] mt-0.5" x-text="'Settlement: ' + prod.settlement"></div>
                                            </td>

                                            <td class="py-4 px-5 text-center">
                                                <button @click="openInputProductFor(prod.partnerKey)"
                                                        class="px-3 py-1.5 rounded-lg bg-[#eff6ff] hover:bg-[#dbeafe] text-[#0052cc] text-[11px] font-extrabold inline-flex items-center gap-1 transition-colors cursor-pointer">
                                                    <span class="material-symbols-rounded text-[14px]">add</span>
                                                    <span>Tambah SKU</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- SUB-PAGE 3: INPUT / TAMBAH PRODUK MITRA UMKM (SLICING)    -->
                <!-- ========================================================= -->
                <div x-show="subPage === 'input_produk'" style="display: none;" class="space-y-5">

                    <!-- Top Action Buttons Row (Lihat Daftar Mitra & Simpan Draft) -->
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button"
                                @click="subPage = 'mitra'"
                                class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12.5px] font-bold inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px] text-slate-600">storefront</span>
                            <span>Lihat Daftar Mitra</span>
                        </button>

                        <button type="button"
                                @click="subPage = 'produk'"
                                class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12.5px] font-bold inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px] text-[#0052cc]">inventory_2</span>
                            <span>List Produk Mitra</span>
                        </button>

                        <button type="button"
                                @click="triggerToast('Draft produk konsinyasi berhasil disimpan sementara.')"
                                class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 px-4 py-2.5 rounded-xl text-[12.5px] font-bold inline-flex items-center gap-2 shadow-2xs transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[17px] text-slate-600">bookmark</span>
                            <span>Simpan Draft</span>
                        </button>
                    </div>

                    <!-- Main Form Container -->
                    <div class="grid grid-cols-12 gap-6 items-start">

                        <!-- 4 Form Section Cards -->
                        <div class="col-span-12 space-y-5">

                            <!-- CARD 1: 1. Informasi Mitra UMKM -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                                <!-- Section Header -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">handshake</span>
                                        </div>
                                        <div>
                                            <h3 class="text-[16px] font-black text-[#0f172a] leading-tight">1. Informasi Mitra UMKM</h3>
                                            <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider mt-0.5">IDENTITAS & LEGALITAS PENYUPLAI</p>
                                        </div>
                                    </div>
                                    <span class="bg-[#f1f5f9] text-slate-600 text-[11px] font-extrabold font-mono px-3 py-1.5 rounded-lg shrink-0">
                                        Seksi 01 / 04
                                    </span>
                                </div>

                                <!-- Pilih Mitra UMKM Terdaftar -->
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <label class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider">
                                            PILIH MITRA UMKM TERDAFTAR <span class="text-red-500">*</span>
                                        </label>
                                        <button type="button" @click="showAddModal = true"
                                                class="text-[11px] font-extrabold text-[#0052cc] hover:underline inline-flex items-center gap-1 cursor-pointer">
                                            <span class="material-symbols-rounded text-[14px]">add_circle</span>
                                            <span>Tambah Mitra Baru</span>
                                        </button>
                                    </div>

                                    <div class="relative flex items-center bg-[#f1f5f9] border border-slate-200/90 focus-within:border-blue-500 focus-within:bg-white rounded-xl px-3.5 py-2.5 transition-all">
                                        <span class="material-symbols-rounded text-[18px] text-[#0052cc] mr-2.5 shrink-0">verified</span>
                                        <select x-model="selectedMitraKey"
                                                class="w-full bg-transparent text-[13px] font-bold text-[#0f172a] appearance-none cursor-pointer focus:outline-none pr-28">
                                            <template x-for="m in partners" :key="m.key">
                                                <option :value="m.key" x-text="m.name"></option>
                                            </template>
                                        </select>
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2 pointer-events-none">
                                            <span class="bg-[#dcfce7] text-[#15803d] text-[10.5px] font-extrabold px-2.5 py-0.5 rounded-md">
                                                Aktif Sinergi
                                            </span>
                                            <span class="material-symbols-rounded text-slate-400 text-[18px]">expand_more</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kode Registrasi Mitra & Penanggung Jawab / WhatsApp -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">
                                            KODE REGISTRASI MITRA
                                        </label>
                                        <div class="bg-[#f1f5f9] border border-slate-200/80 rounded-xl px-3.5 py-2.5 flex items-center gap-2">
                                            <span class="text-slate-400 font-mono font-bold text-[13px]">#</span>
                                            <span class="font-mono font-extrabold text-[13px] text-[#0f172a]" x-text="currentFormMitra.regCode">UMKM-JKT-042</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">
                                            PENANGGUNG JAWAB / WHATSAPP
                                        </label>
                                        <div class="bg-[#f1f5f9] border border-slate-200/80 rounded-xl px-3.5 py-2.5 flex items-center gap-2.5">
                                            <span class="material-symbols-rounded text-[17px] text-[#16a34a] shrink-0">call</span>
                                            <span class="text-[12.5px] font-semibold text-slate-700 truncate" x-text="currentFormMitra.picWhatsapp">Ibu Eni Suhartini - 0813-8899-7711</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rekening Transfer Bagi Hasil -->
                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">
                                        REKENING TRANSFER BAGI HASIL (REKENING RESMI)
                                    </label>
                                    <div class="bg-[#f1f5f9] border border-slate-200/80 rounded-xl px-3.5 py-2.5 flex items-center justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                                            <span class="bg-white border border-slate-200/90 text-[#0f172a] text-[11px] font-black px-2 py-0.5 rounded shadow-2xs"
                                                  x-text="currentFormMitra.bankName">BCA</span>
                                            <span class="font-mono font-extrabold text-[13px] text-[#0f172a]"
                                                  x-text="currentFormMitra.bankAccount">8830-192-881</span>
                                            <span class="text-[12px] text-slate-500 font-medium truncate"
                                                  x-text="'(' + currentFormMitra.bankHolder + ')'">(a/n Dapur Eni Nusantara)</span>
                                        </div>
                                        <span class="material-symbols-rounded text-[19px] text-[#16a34a] shrink-0">verified_user</span>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2: 2. Detail Produk Konsinyasi -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                                <!-- Section Header -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#dbeafe] text-[#0052cc] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">category</span>
                                        </div>
                                        <div>
                                            <h3 class="text-[16px] font-black text-[#0f172a] leading-tight">2. Detail Produk Konsinyasi</h3>
                                            <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider mt-0.5">SPESIFIKASI, BARCODE & KEDALUWARSA</p>
                                        </div>
                                    </div>
                                    <span class="bg-[#f1f5f9] text-slate-600 text-[11px] font-extrabold font-mono px-3 py-1.5 rounded-lg shrink-0">
                                        Seksi 02 / 04
                                    </span>
                                </div>

                                <!-- Nama Produk Titipan Kasir -->
                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                        NAMA PRODUK TITIPAN KASIR <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative flex items-center bg-white border border-slate-200/90 focus-within:border-blue-500 rounded-xl px-3.5 py-2.5 transition-all">
                                        <span class="material-symbols-rounded text-[18px] text-slate-400 mr-2.5 shrink-0">inventory_2</span>
                                        <input type="text" x-model="formProductName"
                                               class="w-full bg-transparent text-[13px] font-semibold text-[#0f172a] focus:outline-none"
                                               placeholder="Masukkan nama produk konsinyasi...">
                                    </div>
                                </div>

                                <!-- Barcode Label Kasir & Kategori Produk UMKM -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <label class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider">
                                                BARCODE LABEL KASIR <span class="text-red-500">*</span>
                                            </label>
                                            <button type="button" @click="generateRandomKnsBarcode()"
                                                    class="text-[10.5px] font-extrabold text-[#0052cc] hover:underline cursor-pointer">
                                                Auto KNS Prefix
                                            </button>
                                        </div>
                                        <div class="bg-[#f8fafc] border border-slate-200/90 focus-within:border-blue-500 focus-within:bg-white rounded-xl px-3.5 py-2 flex items-center justify-between gap-2 transition-all">
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0">qr_code_scanner</span>
                                                <input type="text" x-model="formBarcode"
                                                       class="w-full bg-transparent font-mono font-extrabold text-[13px] text-[#0f172a] focus:outline-none">
                                            </div>
                                            <button type="button" @click="generateRandomKnsBarcode()"
                                                    class="bg-slate-200/80 hover:bg-slate-300/70 text-slate-700 text-[11px] font-extrabold px-3 py-1 rounded-lg shrink-0 transition-colors cursor-pointer">
                                                Scan
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            KATEGORI PRODUK UMKM
                                        </label>
                                        <div class="relative">
                                            <select x-model="formProductCategory"
                                                    class="w-full bg-white border border-slate-200/90 focus:border-blue-500 rounded-xl pl-3.5 pr-9 py-2.5 text-[13px] font-semibold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option>Makanan Ringan Tradisional</option>
                                                <option>Kerajinan & Wastra Lokal</option>
                                                <option>Minuman Herbal & Kesehatan</option>
                                                <option>Roti & Kue Basah Harian</option>
                                                <option>Bumbu & Sambal Kemasan</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">unfold_more</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Karakteristik Daya Tahan & Tgl Drop / Kedaluwarsa -->
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                                    <!-- Karakteristik Daya Tahan (6 Cols) -->
                                    <div class="md:col-span-6">
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            KARAKTERISTIK DAYA TAHAN
                                        </label>
                                        <div class="grid grid-cols-2 gap-2.5">
                                            <label @click="formShelfLife = 'cepat_basi'"
                                                   :class="formShelfLife === 'cepat_basi' ? 'bg-[#eff6ff] border-[#93c5fd]' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/70'"
                                                   class="border rounded-xl p-3 flex items-center gap-2.5 cursor-pointer transition-all">
                                                <input type="radio" name="shelf_life" value="cepat_basi" x-model="formShelfLife" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                                <div class="min-w-0">
                                                    <div class="text-[11.5px] font-extrabold leading-tight"
                                                         :class="formShelfLife === 'cepat_basi' ? 'text-[#0052cc]' : 'text-slate-800'">Cepat Basi</div>
                                                    <div class="text-[10px] text-slate-500 font-medium mt-0.5">&lt; 3 hari simpan</div>
                                                </div>
                                            </label>

                                            <label @click="formShelfLife = 'awet'"
                                                   :class="formShelfLife === 'awet' ? 'bg-[#eff6ff] border-[#93c5fd]' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/70'"
                                                   class="border rounded-xl p-3 flex items-center gap-2.5 cursor-pointer transition-all">
                                                <input type="radio" name="shelf_life" value="awet" x-model="formShelfLife" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                                <div class="min-w-0">
                                                    <div class="text-[11.5px] font-extrabold leading-tight"
                                                         :class="formShelfLife === 'awet' ? 'text-[#0052cc]' : 'text-slate-800'">Kemasan Awet</div>
                                                    <div class="text-[10px] font-medium mt-0.5"
                                                         :class="formShelfLife === 'awet' ? 'text-[#0052cc]/80' : 'text-slate-500'">3 - 6 Bulan shelf</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Tgl Drop Hari Ini (3 Cols) -->
                                    <div class="md:col-span-3">
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            TGL DROP HARI INI
                                        </label>
                                        <input type="text" x-model="formDropDate"
                                               class="w-full bg-[#f1f5f9] border border-slate-200/80 focus:border-blue-500 focus:bg-white rounded-xl px-3.5 py-3 font-mono font-bold text-[12.5px] text-slate-800 focus:outline-none transition-all">
                                    </div>

                                    <!-- Tgl Kedaluwarsa (3 Cols) -->
                                    <div class="md:col-span-3">
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            TGL KEDALUWARSA
                                        </label>
                                        <input type="text" x-model="formExpDate"
                                               class="w-full bg-[#f1f5f9] border border-slate-200/80 focus:border-blue-500 focus:bg-white rounded-xl px-3.5 py-3 font-mono font-bold text-[12.5px] text-slate-800 focus:outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 3: 3. Skema Harga & Bagi Hasil (Margin Sharing) -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                                <!-- Section Header -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#ffedd5] text-[#c2410c] flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">percent</span>
                                        </div>
                                        <div>
                                            <h3 class="text-[16px] font-black text-[#0f172a] leading-tight">3. Skema Harga & Bagi Hasil (Margin Sharing)</h3>
                                            <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider mt-0.5">PEMBAGIAN KEUNTUNGAN TOKO & PENYUPLAI</p>
                                        </div>
                                    </div>
                                    <span class="bg-[#f1f5f9] text-slate-600 text-[11px] font-extrabold font-mono px-3 py-1.5 rounded-lg shrink-0">
                                        Seksi 03 / 04
                                    </span>
                                </div>

                                <!-- Metode Komisi Bar -->
                                <div class="bg-[#f1f5f9] rounded-xl p-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                    <span class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider shrink-0">
                                        METODE KOMISI:
                                    </span>
                                    <div class="flex flex-wrap items-center gap-5">
                                        <label @click="formCommissionMethod = 'persentase'; formCommissionPercent = 15; onNetPriceChange()" class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="commission_method" value="persentase" x-model="formCommissionMethod" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span class="text-[12px]" :class="formCommissionMethod === 'persentase' ? 'font-extrabold text-[#0f172a]' : 'font-medium text-slate-600'">Persentase Bagi Hasil (Toko 15% : UMKM 85%)</span>
                                        </label>
                                        <label @click="formCommissionMethod = 'markup'" class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="commission_method" value="markup" x-model="formCommissionMethod" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span class="text-[12px]" :class="formCommissionMethod === 'markup' ? 'font-extrabold text-[#0f172a]' : 'font-medium text-slate-600'">Selisih Flat Markup</span>
                                        </label>
                                        <label @click="formCommissionMethod = 'custom'" class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="commission_method" value="custom" x-model="formCommissionMethod" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span class="text-[12px]" :class="formCommissionMethod === 'custom' ? 'font-extrabold text-[#0052cc]' : 'font-medium text-slate-600'">Custom</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- 3 Pricing Columns -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <!-- Col 1: Harga Titipan Mitra (Net) -->
                                    <div>
                                        <label class="block text-[10px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">
                                            HARGA TITIPAN MITRA (NET)
                                        </label>
                                        <div class="bg-[#f1f5f9] border border-slate-200/80 focus-within:border-blue-500 focus-within:bg-white rounded-xl px-3.5 py-3 flex items-center gap-2 transition-all">
                                            <span class="text-[12.5px] font-bold text-slate-500">Rp</span>
                                            <input type="number" x-model="formNetPrice" @input="onNetPriceChange()"
                                                   class="w-full bg-transparent font-mono font-black text-[16px] text-[#0f172a] focus:outline-none">
                                        </div>
                                        <p class="text-[10.5px] text-slate-500 font-medium mt-1.5"
                                           x-text="formCommissionMethod === 'persentase' ? 'Dibayar ke mitra per pcs laku (85%)' : 'Nominal bersih yang diterima mitra per pcs'"></p>
                                    </div>

                                    <!-- Col 2: Bagi Hasil Toko POS (Editable / Custom Setting) -->
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <label class="block text-[10px] font-extrabold text-slate-600 uppercase tracking-wider"
                                                   x-text="formCommissionMethod === 'persentase' ? 'BAGI HASIL TOKO POS (15%)' : (formCommissionMethod === 'custom' ? 'BAGI HASIL TOKO POS (CUSTOM)' : 'BAGI HASIL TOKO POS (MARKUP)')">
                                                BAGI HASIL TOKO POS (15%)
                                            </label>
                                            <template x-if="formCommissionMethod === 'custom'">
                                                <div class="inline-flex items-center gap-1 bg-blue-50 border border-blue-200 rounded-md px-2 py-0.5">
                                                    <input type="number" x-model="formCommissionPercent" @input="onPercentChange()" min="0" max="95"
                                                           class="w-9 bg-transparent font-mono font-extrabold text-[11px] text-[#0052cc] text-right focus:outline-none">
                                                    <span class="text-[10px] font-extrabold text-[#0052cc]">%</span>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="bg-[#eff6ff] border border-blue-200/80 focus-within:border-[#0052cc] focus-within:bg-white rounded-xl px-3.5 py-3 flex items-center justify-between gap-2 transition-all">
                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                <span class="text-[12.5px] font-bold text-[#0052cc]">Rp</span>
                                                <input type="number" x-model="formPosMargin" @input="formCommissionMethod = 'custom'; onMarginChange()"
                                                       class="w-full bg-transparent font-mono font-black text-[16px] text-[#0052cc] focus:outline-none"
                                                       title="Atur nominal bagi hasil toko POS">
                                            </div>
                                            <span class="material-symbols-rounded text-[16px] text-[#0052cc]/60 shrink-0" title="Dapat diatur sesuai kesepakatan">edit</span>
                                        </div>
                                        <p class="text-[10.5px] text-slate-500 font-medium mt-1.5"
                                           x-text="formCommissionMethod === 'custom' ? 'Dapat diatur manual (Rp atau %) sesuai kesepakatan' : 'Otomatis masuk pos pendapatan komisi'"></p>
                                    </div>

                                    <!-- Col 3: Harga Jual Rak di Kasir -->
                                    <div>
                                        <label class="block text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider mb-1.5">
                                            HARGA JUAL RAK DI KASIR
                                        </label>
                                        <div class="bg-[#eff6ff] border border-blue-200/80 focus-within:border-blue-500 rounded-xl px-3.5 py-3 flex items-center gap-2 transition-all">
                                            <span class="text-[12.5px] font-bold text-slate-700">Rp</span>
                                            <input type="number" x-model="formSellPrice" @input="onSellPriceChange()"
                                                   class="w-full bg-transparent font-mono font-black text-[16px] text-[#0052cc] focus:outline-none">
                                        </div>
                                        <p class="text-[10.5px] font-bold text-[#16a34a] mt-1.5"
                                           x-text="'Margin Toko: Rp ' + formatNumber(calculatedMargin) + ' / kemasan'">Margin Toko: Rp 1.500 / kemasan</p>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 4: 4. Jumlah Titipan & Kebijakan Retur -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
                                <!-- Section Header -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-10 h-10 rounded-xl bg-[#f1f5f9] text-slate-700 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[20px]">inventory</span>
                                        </div>
                                        <div>
                                            <h3 class="text-[16px] font-black text-[#0f172a] leading-tight">4. Jumlah Titipan & Kebijakan Retur</h3>
                                            <p class="text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider mt-0.5">MANAJEMEN STOK & REKONSILIASI KADALUWARSA</p>
                                        </div>
                                    </div>
                                    <span class="bg-[#f1f5f9] text-slate-600 text-[11px] font-extrabold font-mono px-3 py-1.5 rounded-lg shrink-0">
                                        Seksi 04 / 04
                                    </span>
                                </div>

                                <!-- Jumlah Drop Masuk Hari Ini & Lokasi Penempatan Rak -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            JUMLAH DROP MASUK HARI INI <span class="text-red-500">*</span>
                                        </label>
                                        <div class="bg-white border border-slate-200/90 focus-within:border-blue-500 rounded-xl px-4 py-2.5 flex items-center justify-between gap-3 transition-all">
                                            <input type="number" x-model="formDropQty" min="1"
                                                   class="w-full bg-transparent font-mono font-black text-[16px] text-[#0f172a] focus:outline-none">
                                            <span class="text-[11.5px] font-semibold text-slate-500 shrink-0 leading-tight text-right">Pcs /<br>Bks</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">
                                            LOKASI PENEMPATAN RAK
                                        </label>
                                        <div class="relative">
                                            <select x-model="formRackLocation"
                                                    class="w-full bg-[#f1f5f9] border border-slate-200/80 focus:border-blue-500 focus:bg-white rounded-xl pl-3.5 pr-9 py-3.5 text-[12.5px] font-semibold text-[#0f172a] appearance-none cursor-pointer focus:outline-none transition-all">
                                                <option>Cabang Utama - Rak Display UMKM 01</option>
                                                <option>Cabang Utama - Rak Kasir Impulsif 02</option>
                                                <option>Cabang Utama - Galeri Kerajinan 02</option>
                                                <option>Cabang Utama - Rak Kesehatan 03</option>
                                            </select>
                                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kebijakan Barang Tidak Laku / Kedaluwarsa -->
                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                                        KEBIJAKAN BARANG TIDAK LAKU / KEDALUWARSA
                                    </label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Option 1 -->
                                        <label @click="formReturnPolicy = 'retur_100'"
                                               :class="formReturnPolicy === 'retur_100' ? 'bg-[#f1f5f9] border-slate-300' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/60'"
                                               class="border rounded-xl p-4 flex items-start gap-3 cursor-pointer transition-all">
                                            <input type="radio" name="return_policy" value="retur_100" x-model="formReturnPolicy" class="mt-0.5 w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <div>
                                                <div class="text-[12.5px] font-extrabold text-[#0f172a]">Retur 100% ke Mitra UMKM</div>
                                                <p class="text-[11px] text-slate-500 font-medium leading-relaxed mt-1">
                                                    Toko tidak menanggung barang rusak atau basi. Sisa barang dikembalikan saat rekapitulasi.
                                                </p>
                                            </div>
                                        </label>

                                        <!-- Option 2 -->
                                        <label @click="formReturnPolicy = 'clearance'"
                                               :class="formReturnPolicy === 'clearance' ? 'bg-[#f1f5f9] border-slate-300' : 'bg-[#f8fafc] border-slate-200/80 hover:bg-slate-100/60'"
                                               class="border rounded-xl p-4 flex items-start gap-3 cursor-pointer transition-all">
                                            <input type="radio" name="return_policy" value="clearance" x-model="formReturnPolicy" class="mt-0.5 w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <div>
                                                <div class="text-[12.5px] font-extrabold text-[#0f172a]">Diskon Clearance Toko</div>
                                                <p class="text-[11px] text-slate-500 font-medium leading-relaxed mt-1">
                                                    Dipindahkan ke rak obral diskon -30% 7 hari menjelang masa kedaluwarsa.
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Periode Rekapitulasi Pembayaran (Settlement) -->
                                <div>
                                    <label class="block text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider mb-2">
                                        PERIODE REKAPITULASI PEMBAYARAN (SETTLEMENT)
                                    </label>
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <label @click="formSettlement = 'mingguan'"
                                               :class="formSettlement === 'mingguan' ? 'bg-[#eff6ff] border-[#93c5fd] text-[#0052cc]' : 'bg-[#f1f5f9] border-transparent text-slate-600 hover:bg-slate-200/70'"
                                               class="border rounded-xl px-3.5 py-2 inline-flex items-center gap-2 text-[12px] font-extrabold cursor-pointer transition-all">
                                            <input type="radio" name="settlement" value="mingguan" x-model="formSettlement" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span>Mingguan (Tiap Jumat Sore)</span>
                                        </label>

                                        <label @click="formSettlement = 'biweekly'"
                                               :class="formSettlement === 'biweekly' ? 'bg-[#eff6ff] border-[#93c5fd] text-[#0052cc]' : 'bg-[#f1f5f9] border-transparent text-slate-600 hover:bg-slate-200/70'"
                                               class="border rounded-xl px-3.5 py-2 inline-flex items-center gap-2 text-[12px] font-semibold cursor-pointer transition-all">
                                            <input type="radio" name="settlement" value="biweekly" x-model="formSettlement" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span>Bi-Weekly (2 Minggu Sekali)</span>
                                        </label>

                                        <label @click="formSettlement = 'bulanan'"
                                               :class="formSettlement === 'bulanan' ? 'bg-[#eff6ff] border-[#93c5fd] text-[#0052cc]' : 'bg-[#f1f5f9] border-transparent text-slate-600 hover:bg-slate-200/70'"
                                               class="border rounded-xl px-3.5 py-2 inline-flex items-center gap-2 text-[12px] font-semibold cursor-pointer transition-all">
                                            <input type="radio" name="settlement" value="bulanan" x-model="formSettlement" class="w-3.5 h-3.5 text-[#0052cc] focus:ring-[#0052cc]">
                                            <span>Bulanan (Tanggal 1)</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Submit Bar -->
                                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                                    <span class="text-[11.5px] text-slate-500 font-medium">
                                        Pastikan fisik barang titipan telah dihitung bersama mitra sebelum diterbitkan.
                                    </span>
                                    <div class="flex items-center gap-2.5 justify-end shrink-0">
                                        <button type="button"
                                                @click="subPage = 'produk'"
                                                class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12px] font-extrabold transition-colors cursor-pointer">
                                            Batal
                                        </button>
                                        <button type="button"
                                                @click="saveConsignmentProduct()"
                                                class="px-5 py-2.5 rounded-xl bg-[#0047b3] hover:bg-[#003994] text-white text-[12.5px] font-extrabold inline-flex items-center gap-2 shadow-sm transition-all cursor-pointer">
                                            <span class="material-symbols-rounded text-[17px]">check_circle</span>
                                            <span>Terbitkan Produk Konsinyasi</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Modal: Tambah Mitra Baru -->
    <div x-show="showAddModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showAddModal = false"
             class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-[#f8fafc] border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#0047b3] text-white flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">handshake</span>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900">Tambah Mitra UMKM Baru</h3>
                        <p class="text-[11px] font-medium text-slate-500">Daftarkan produsen lokal & produk konsinyasi baru</p>
                    </div>
                </div>
                <button @click="showAddModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-200/60 flex items-center justify-center text-slate-500 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">NAMA MITRA UMKM</label>
                    <input type="text" x-model="newName" placeholder="Contoh: Bakpia Pathok 88"
                           class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">ASAL KOTA / DAERAH</label>
                    <input type="text" x-model="newLocation" placeholder="Contoh: Bantul, Yogyakarta"
                           class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-medium text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">KATEGORI PRODUK</label>
                        <select x-model="newCategory" class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                            <option value="Snack">Snack</option>
                            <option value="Kerajinan">Kerajinan</option>
                            <option value="Minuman">Minuman</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-600 uppercase tracking-wider mb-1.5">JUMLAH SKU TITIPAN</label>
                        <input type="number" x-model="newSkuCount" min="1"
                               class="w-full bg-[#f1f5f9] border border-slate-200 rounded-xl px-3.5 py-2.5 text-[13px] font-bold text-slate-800 focus:bg-white focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-[#f8fafc] border-t border-slate-200 flex items-center justify-end gap-2.5">
                <button @click="showAddModal = false" class="px-4 py-2 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-700 text-[12px] font-extrabold cursor-pointer">Batal</button>
                <button @click="addPartner()" class="px-5 py-2 rounded-xl bg-[#0047b3] hover:bg-[#003994] text-white text-[12px] font-extrabold shadow-xs cursor-pointer">
                    Simpan Mitra Baru
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Detail Mitra -->
    <div x-show="showDetailModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-4"
         x-transition.opacity>
        <div @click.away="showDetailModal = false"
             class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden"
             x-show="selectedPartner">
            <div class="px-6 py-4 bg-[#f8fafc] border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-[#dbeafe] text-[#0052cc] flex items-center justify-center font-black text-[15px]"
                         x-text="selectedPartner ? selectedPartner.initial : ''"></div>
                    <div>
                        <h3 class="text-[15px] font-black text-slate-900" x-text="selectedPartner ? selectedPartner.name : ''"></h3>
                        <p class="text-[11.5px] font-medium text-slate-500" x-text="selectedPartner ? selectedPartner.location : ''"></p>
                    </div>
                </div>
                <button @click="showDetailModal = false" class="w-8 h-8 rounded-lg hover:bg-slate-200/60 flex items-center justify-center text-slate-500 cursor-pointer">
                    <span class="material-symbols-rounded text-[18px]">close</span>
                </button>
            </div>
            <div class="p-6 space-y-3 text-[13px]">
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500 font-medium">Kode Registrasi Mitra</span>
                    <span class="font-mono font-extrabold text-slate-800" x-text="selectedPartner ? '#' + selectedPartner.regCode : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500 font-medium">Kategori Konsinyasi</span>
                    <span class="font-extrabold text-slate-800" x-text="selectedPartner ? selectedPartner.category : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500 font-medium">Jumlah Produk (SKU)</span>
                    <span class="font-extrabold text-slate-800" x-text="selectedPartner ? selectedPartner.sku + ' SKU Terdaftar' : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-100">
                    <span class="text-slate-500 font-medium">Total Penjualan Bulan Ini</span>
                    <span class="font-mono font-extrabold text-[#0052cc]" x-text="selectedPartner ? selectedPartner.totalSales : ''"></span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-slate-500 font-medium">Kontak WhatsApp Produsen</span>
                    <span class="font-mono font-bold text-slate-800" x-text="selectedPartner ? selectedPartner.phone : ''"></span>
                </div>
            </div>
            <div class="px-6 py-4 bg-[#f8fafc] border-t border-slate-200 flex justify-between items-center gap-2">
                <button @click="showDetailModal = false; openInputProductFor(selectedPartner.key)"
                        class="px-4 py-2 rounded-xl bg-[#eff6ff] hover:bg-[#dbeafe] text-[#0052cc] text-[12px] font-extrabold inline-flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-rounded text-[16px]">add_circle</span>
                    <span>Tambah Produk Mitra Ini</span>
                </button>
                <button @click="showDetailModal = false" class="px-4 py-2 rounded-xl bg-[#0047b3] text-white text-[12px] font-extrabold cursor-pointer">Tutup</button>
            </div>
        </div>
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
