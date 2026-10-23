@extends('layouts.app')

@section('title', 'Informasi Stok Produk - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    selectedCategory: 'all',
    showDetailModal: false,
    selectedProduct: null,
    showScannerModal: false,
    scannerInput: '',

    products: [
        {
            sku: 'PRD-001',
            barcode: '8991234567890',
            name: 'Aqua Mineral 600ml',
            brand: 'Danone Indonesia',
            category: 'Minuman',
            rackLocation: 'Lorong A - Rak 01 (Minuman Dingin)',
            stock: 450,
            minStock: 50,
            unit: 'Botol',
            price: 5500,
            memberPrice: 5000,
            status: 'TERSEDIA',
            batchLot: 'AQ-202610-09',
            expiredDate: '15 Nov 2027'
        },
        {
            sku: 'PRD-002',
            barcode: '8992345678901',
            name: 'Chitato Sapi Panggang 68g',
            brand: 'Indofood',
            category: 'Snack',
            rackLocation: 'Lorong B - Rak 03 (Makanan Ringan)',
            stock: 8,
            minStock: 20,
            unit: 'Pcs',
            price: 12800,
            memberPrice: 12000,
            status: 'STOK_RENDAH',
            batchLot: 'CT-202609-02',
            expiredDate: '12 Jan 2027'
        },
        {
            sku: 'PRD-003',
            barcode: '8993456789012',
            name: 'Ultra Milk Full Cream 1L',
            brand: 'Ultrajaya',
            category: 'Minuman',
            rackLocation: 'Chiller Susu 02',
            stock: 32,
            minStock: 15,
            unit: 'Tetra',
            price: 19200,
            memberPrice: 18500,
            status: 'NEAR_EXPIRED',
            batchLot: 'UM-202608-11',
            expiredDate: '10 Nov 2026'
        },
        {
            sku: 'PRD-004',
            barcode: '8994567890123',
            name: 'Beras Pandan Wangi 5kg',
            brand: 'Lokal Berdikari',
            category: 'Kebutuhan Pokok',
            rackLocation: 'Palet Depan Kasir - Sembako',
            stock: 120,
            minStock: 30,
            unit: 'Pack',
            price: 82000,
            memberPrice: 79000,
            status: 'TERSEDIA',
            batchLot: 'BPW-202610-01',
            expiredDate: '30 Apr 2027'
        },
        {
            sku: 'PRD-005',
            barcode: '8995678901234',
            name: 'Sunlight Jeruk Nipis 755ml',
            brand: 'Unilever',
            category: 'Pembersih',
            rackLocation: 'Lorong C - Rak 02 (Kebersihan)',
            stock: 54,
            minStock: 25,
            unit: 'Botol',
            price: 15500,
            memberPrice: 14500,
            status: 'TERSEDIA',
            batchLot: 'SN-202609-15',
            expiredDate: '20 Sep 2028'
        },
        {
            sku: 'PRD-006',
            barcode: '899275321004',
            name: 'Indomie Goreng Spesial 85g',
            brand: 'Indofood',
            category: 'Makanan',
            rackLocation: 'Lorong B - Rak 01 (Mie Instan)',
            stock: 320,
            minStock: 80,
            unit: 'Pcs',
            price: 3500,
            memberPrice: 3200,
            status: 'TERSEDIA',
            batchLot: 'IDM-202610-44',
            expiredDate: '18 Agu 2027'
        },
        {
            sku: 'PRD-007',
            barcode: '8996789012345',
            name: 'Minyak Goreng Sania 2L',
            brand: 'Wilmar',
            category: 'Kebutuhan Pokok',
            rackLocation: 'Lorong A - Rak 04 (Minyak Goreng)',
            stock: 6,
            minStock: 24,
            unit: 'Pouch',
            price: 36000,
            memberPrice: 34500,
            status: 'STOK_RENDAH',
            batchLot: 'SN-202610-08',
            expiredDate: '22 Des 2027'
        },
        {
            sku: 'PRD-008',
            barcode: '8997890123456',
            name: 'Teh Pucuk Harum 350ml',
            brand: 'Mayora',
            category: 'Minuman',
            rackLocation: 'Lorong A - Rak 02 (Teh Siap Minum)',
            stock: 140,
            minStock: 40,
            unit: 'Botol',
            price: 4000,
            memberPrice: 3800,
            status: 'TERSEDIA',
            batchLot: 'TP-202609-29',
            expiredDate: '05 Okt 2027'
        },
        {
            sku: 'PRD-009',
            barcode: '8998901234567',
            name: 'Oreo Vanilla 133g',
            brand: 'Mondelez',
            category: 'Snack',
            rackLocation: 'Lorong B - Rak 02 (Biskuit)',
            stock: 14,
            minStock: 25,
            unit: 'Pcs',
            price: 9500,
            memberPrice: 9000,
            status: 'NEAR_EXPIRED',
            batchLot: 'OR-202608-05',
            expiredDate: '15 Nov 2026'
        },
        {
            sku: 'PRD-010',
            barcode: '8999012345678',
            name: 'Gula Pasir Gulaku 1kg',
            brand: 'Sugar Group',
            category: 'Kebutuhan Pokok',
            rackLocation: 'Palet Depan Kasir - Sembako',
            stock: 0,
            minStock: 20,
            unit: 'Pack',
            price: 18000,
            memberPrice: 17500,
            status: 'HABIS',
            batchLot: 'GL-202607-12',
            expiredDate: '10 Jan 2028'
        }
    ],

    get filteredProducts() {
        return this.products.filter(p => {
            if (this.selectedCategory !== 'all' && p.category !== this.selectedCategory) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = p.name.toLowerCase().includes(q);
                const matchBrand = p.brand.toLowerCase().includes(q);
                const matchBarcode = p.barcode.includes(q);
                const matchSku = p.sku.toLowerCase().includes(q);
                const matchRack = p.rackLocation.toLowerCase().includes(q);
                return matchName || matchBrand || matchBarcode || matchSku || matchRack;
            }
            return true;
        });
    },

    get totalItems() {
        return this.products.length;
    },

    get lowStockCount() {
        return this.products.filter(p => p.status === 'STOK_RENDAH').length;
    },

    get nearExpiredCount() {
        return this.products.filter(p => p.status === 'NEAR_EXPIRED').length;
    },

    get outOfStockCount() {
        return this.products.filter(p => p.status === 'HABIS').length;
    },

    openDetail(product) {
        this.selectedProduct = product;
        this.showDetailModal = true;
    },

    handleScan() {
        if (!this.scannerInput.trim()) return;
        this.searchQuery = this.scannerInput.trim();
        this.scannerInput = '';
        this.showScannerModal = false;
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Informasi Stok Produk">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">lock</span>
                    <span>Mode Kasir (Read-Only)</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>PRODUK</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">INFORMASI STOK & LOKASI RAK</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Katalog Informasi Stok Toko</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Informasi stok produk, lokasi rak display, harga jual, dan masa kedaluwarsa.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ url('/pos') }}" class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                            <span>Buka Layar POS</span>
                        </a>
                    </div>
                </div>

                <!-- 4 Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1 -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">TOTAL KATALOG</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-900" x-text="totalItems">10</h3>
                                <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-md">SKU Aktif</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Display rak & gudang toko</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">inventory_2</span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">STOK MENIPIS</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-amber-600" x-text="lowStockCount">2</h3>
                                <span class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">Perlu Restock</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Di bawah buffer rak</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">warning</span>
                        </div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[11px] font-bold text-rose-700 tracking-wider uppercase">MENDEKATI EXPIRED</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-rose-600" x-text="nearExpiredCount">2</h3>
                                <span class="text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-md">&le; H-30 Hari</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Prioritas display depan</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">event_busy</span>
                        </div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">STOK HABIS (KOSONG)</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-700" x-text="outOfStockCount">1</h3>
                                <span class="text-[11px] font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-md">0 Unit</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Ajukan barang masuk DC</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-gray-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">production_quantity_limits</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nama produk, barcode, SKU, atau lokasi rak..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <div class="relative w-full md:w-auto">
                            <select x-model="selectedCategory"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer min-w-[200px]">
                                <option value="all">Semua Kategori</option>
                                <option value="Minuman">Minuman</option>
                                <option value="Snack">Snack</option>
                                <option value="Kebutuhan Pokok">Kebutuhan Pokok</option>
                                <option value="Pembersih">Pembersih</option>
                                <option value="Makanan">Makanan</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Reset Filter -->
                        <button type="button"
                                x-show="searchQuery || selectedCategory !== 'all'"
                                @click="searchQuery = ''; selectedCategory = 'all'"
                                class="px-3 py-2.5 text-xs text-gray-500 hover:text-gray-800 font-bold transition-colors cursor-pointer shrink-0">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory</span>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Stok Produk</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredProducts.length + ' dari ' + totalItems + ' produk'"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-gray-200">
                                    <th class="px-6 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">PRODUK & BRAND</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">BARCODE / SKU</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">LOKASI RAK</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-center">SISA STOK</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-right">HARGA UMUM</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-center">STATUS</th>
                                    <th class="px-6 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-right">DETAIL</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100">
                                <template x-for="p in filteredProducts" :key="p.sku">
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <!-- Produk & Brand -->
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900 text-[13px]" x-text="p.name"></span>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[11px] text-gray-500 font-medium" x-text="p.brand"></span>
                                                    <span class="text-gray-300">•</span>
                                                    <span class="bg-[#eef2ff] text-[#0052cc] text-[10px] font-bold px-2 py-0.5 rounded-full" x-text="p.category"></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Barcode / SKU -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-mono text-[12px] font-bold text-gray-700" x-text="p.barcode"></span>
                                                <span class="font-mono text-[10px] text-gray-400 mt-0.5" x-text="p.sku"></span>
                                            </div>
                                        </td>

                                        <!-- Lokasi Rak -->
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-1.5 text-gray-700">
                                                <span class="material-symbols-rounded text-[16px] text-gray-400">shelves</span>
                                                <span class="text-xs font-semibold" x-text="p.rackLocation"></span>
                                            </div>
                                        </td>

                                        <!-- Sisa Stok -->
                                        <td class="px-4 py-4 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="font-black text-[14px]"
                                                      :class="p.stock === 0 ? 'text-gray-400 line-through' : (p.stock <= p.minStock ? 'text-amber-600' : 'text-gray-900')"
                                                      x-text="p.stock"></span>
                                                <span class="text-[10.5px] text-gray-500 font-medium" x-text="p.unit"></span>
                                            </div>
                                        </td>

                                        <!-- Harga Umum -->
                                        <td class="px-4 py-4 text-right">
                                            <div class="flex flex-col items-end">
                                                <span class="font-mono font-bold text-gray-900 text-[13px]" x-text="formatRupiah(p.price)"></span>
                                                <span class="text-[10px] text-emerald-600 font-semibold" x-text="'Member: ' + formatRupiah(p.memberPrice)"></span>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-4 py-4 text-center">
                                            <template x-if="p.status === 'TERSEDIA'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Tersedia</span>
                                                </span>
                                            </template>
                                            <template x-if="p.status === 'STOK_RENDAH'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="material-symbols-rounded text-[13px]">warning</span>
                                                    <span>Stok Rendah</span>
                                                </span>
                                            </template>
                                            <template x-if="p.status === 'NEAR_EXPIRED'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="material-symbols-rounded text-[13px]">event_busy</span>
                                                    <span>Hampir ED</span>
                                                </span>
                                            </template>
                                            <template x-if="p.status === 'HABIS'">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600 border border-gray-200">
                                                    <span>Kosong</span>
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="px-6 py-4 text-right">
                                            <button type="button"
                                                    @click="openDetail(p)"
                                                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-[#0052cc] hover:text-white text-gray-700 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 ml-auto cursor-pointer group">
                                                <span class="material-symbols-rounded text-[16px] text-gray-400 group-hover:text-white transition-colors">visibility</span>
                                                <span>Detail Stok</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Floating Barcode Scanner Lookup Button -->
        <button type="button"
                @click="showScannerModal = true"
                class="absolute bottom-8 right-8 w-14 h-14 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-2xl shadow-lg flex items-center justify-center transition-all hover:scale-105 active:scale-95 cursor-pointer">
            <span class="material-symbols-rounded text-[26px]">qr_code_scanner</span>
        </button>
    </main>

    <!-- MODAL DETAIL PRODUK & INFORMASI STOK (READ-ONLY) -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDetailModal"
                 @click.away="showDetailModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden border border-gray-200 flex flex-col">

                <!-- Header -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="selectedProduct?.name"></h3>
                            <span class="font-mono text-xs text-gray-500" x-text="'Barcode: ' + selectedProduct?.barcode + ' • SKU: ' + selectedProduct?.sku"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4 text-xs">
                    <!-- Stock & Shelf Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 bg-slate-50 rounded-xl border border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">LOKASI RAK / LORONG</span>
                            <div class="flex items-center gap-1.5 text-gray-900 font-bold text-sm">
                                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">shelves</span>
                                <span x-text="selectedProduct?.rackLocation"></span>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-xl border border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">SISA STOK AKTIF</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-xl font-black text-gray-900" x-text="selectedProduct?.stock + ' ' + selectedProduct?.unit"></span>
                                <span class="text-[10px] text-gray-400 font-medium" x-text="'(Min: ' + selectedProduct?.minStock + ')'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Info -->
                    <div class="grid grid-cols-2 gap-3 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                        <div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase block">HARGA UMUM</span>
                            <span class="text-lg font-black text-gray-900 font-mono mt-0.5 block" x-text="formatRupiah(selectedProduct?.price)"></span>
                            <span class="text-[10px] text-gray-500">Harga jual non-member</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-[#0052cc] uppercase block">HARGA ANGGOTA KOPKAR</span>
                            <span class="text-lg font-black text-[#0052cc] font-mono mt-0.5 block" x-text="formatRupiah(selectedProduct?.memberPrice)"></span>
                            <span class="text-[10px] text-emerald-700 font-bold">Hemat Rp 500 - Rp 3.000</span>
                        </div>
                    </div>

                    <!-- Batch & Expiry -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Nomor Batch / Lot Produksi:</span>
                            <span class="font-mono font-bold text-gray-800" x-text="selectedProduct?.batchLot"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Batas Tanggal Kedaluwarsa:</span>
                            <span class="font-bold text-gray-800" x-text="selectedProduct?.expiredDate"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Kategori & Brand:</span>
                            <span class="font-bold text-gray-800" x-text="selectedProduct?.category + ' • ' + selectedProduct?.brand"></span>
                        </div>
                    </div>

                    <!-- Security Notice -->
                    <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl flex items-center gap-2.5 text-[11px] text-amber-900">
                        <span class="material-symbols-rounded text-[18px] text-amber-600 shrink-0">admin_panel_settings</span>
                        <span>Kasir hanya memiliki izin baca (read-only). Perubahan harga atau mutasi stok fisik diotorisasi oleh Manager Toko.</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="showDetailModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Tutup
                    </button>

                    <a href="{{ url('/pos') }}"
                       class="px-5 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors">
                        <span class="material-symbols-rounded text-[16px]">point_of_sale</span>
                        <span>Buka di Terminal POS</span>
                    </a>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL SIMULASI SCAN BARCODE -->
    <template x-teleport="body">
        <div x-show="showScannerModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showScannerModal"
                 @click.away="showScannerModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 p-6 flex flex-col">

                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[20px]">qr_code_scanner</span>
                        </div>
                        <h3 class="font-bold text-gray-900 text-sm">Simulasi Scan Barcode Kasir</h3>
                    </div>
                    <button @click="showScannerModal = false" class="text-gray-400 hover:text-gray-600">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <p class="text-xs text-gray-500 mb-4">Scan barcode atau ketik nomor barcode produk untuk pencarian cepat di rak:</p>

                <div class="space-y-3">
                    <input type="text"
                           x-model="scannerInput"
                           @keyup.enter="handleScan()"
                           placeholder="Scan atau ketik barcode (contoh: 8991234567890)..."
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="scannerInput = '8991234567890'; handleScan();"
                                class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-[10px] font-mono font-bold text-gray-700">
                            Aqua 600ml
                        </button>
                        <button type="button"
                                @click="scannerInput = '899275321004'; handleScan();"
                                class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-[10px] font-mono font-bold text-gray-700">
                            Indomie Goreng
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button"
                            @click="showScannerModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="button"
                            @click="handleScan()"
                            class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold">
                        Cari Produk
                    </button>
                </div>

            </div>
        </div>
    </template>

</div>
@endsection
