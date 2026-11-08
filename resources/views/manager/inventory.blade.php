@extends('layouts.app')

@section('title', 'Inventaris, Stok & Konsinyasi UMKM - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    // API State
    products: [],
    summary: {
        total_products: 0,
        low_stock_count: 0,
        out_of_stock_count: 0,
        critical_expired_count: 0,
        warning_expired_count: 0,
        consignment_count: 0,
        total_stock_units: 0,
        total_inventory_value: 0
    },
    categories: ['Semua'],
    suppliers: [],

    // Filters
    searchQuery: '',
    selectedCategory: 'all',
    stockFilter: 'all', // all | STOK_RENDAH | HABIS | TERSEDIA
    expiredFilter: 'all', // all | CRITICAL | WARNING | EXPIRED
    consignmentFilter: 'all', // all | only_consignment | only_regular
    currentPage: 1,
    lastPage: 1,
    totalRecords: 0,
    perPage: 20,
    isLoading: false,
    debounceTimer: null,

    // Modals
    showProductModal: false,
    modalMode: 'create', // 'create' | 'edit'
    showUnitModal: false,
    selectedProductForUnit: null,
    showDeleteModal: false,
    productToDelete: null,
    showScannerModal: false,
    scannerInput: '',

    // Product Form
    formData: {
        id: null,
        sku: '',
        barcode: '',
        name: '',
        category: 'Minuman',
        unit: 'Pcs',
        cost_price: 0,
        price: 0,
        member_price: 0,
        stock: 0,
        min_stock: 10,
        is_taxable: true,
        tax_rate: 11,
        expired_date: '',
        clearance_discount_percent: 20,
        rack_location: 'Lorong A - Rak 01',
        is_consignment: false,
        supplier_id: '',
        commission_percent: 15
    },
    formErrors: {},

    // Unit Conversion Form
    unitFormData: {
        unit_name: 'Dus',
        conversion_factor: 24,
        price: 0,
        barcode: ''
    },

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initInventory() {
        await this.loadSuppliers();
        await this.fetchProducts(1);
    },

    onSearchInput() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.fetchProducts(1);
        }, 300);
    },

    async loadSuppliers() {
        try {
            const res = await fetch('/api/consignment/partners', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) this.suppliers = json.data;
            }
        } catch (e) {
            console.error('Error loading suppliers:', e);
        }
    },

    async fetchProducts(page = 1) {
        this.isLoading = true;
        this.currentPage = page;

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                search: this.searchQuery.trim(),
                category: this.selectedCategory !== 'Semua' ? this.selectedCategory : '',
                stock_filter: this.stockFilter !== 'all' ? this.stockFilter : '',
                expired_filter: this.expiredFilter !== 'all' ? this.expiredFilter : '',
                consignment: this.consignmentFilter !== 'all' ? this.consignmentFilter : ''
            });

            const res = await fetch(`/api/products?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.products = json.data;
                    this.summary = json.summary || this.summary;
                    this.categories = json.categories || this.categories;
                    this.currentPage = json.meta.current_page;
                    this.lastPage = json.meta.last_page;
                    this.totalRecords = json.meta.total;
                }
            }
        } catch (e) {
            console.error('Error fetching inventory:', e);
            this.triggerToast('Gagal memuat data inventaris dari API.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    openCreateModal() {
        this.modalMode = 'create';
        this.formErrors = {};
        const randomBarcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);
        const expDate = new Date();
        expDate.setMonth(expDate.getMonth() + 6);

        this.formData = {
            id: null,
            sku: '',
            barcode: randomBarcode,
            name: '',
            category: this.categories[1] || 'Makanan',
            unit: 'Pcs',
            cost_price: 3500,
            price: 5000,
            member_price: 4800,
            stock: 50,
            min_stock: 10,
            is_taxable: true,
            tax_rate: 11,
            expired_date: expDate.toISOString().split('T')[0],
            clearance_discount_percent: 20,
            rack_location: 'Lorong A - Rak 01',
            is_consignment: false,
            supplier_id: this.suppliers[0]?.id || '',
            commission_percent: 15
        };
        this.showProductModal = true;
    },

    openEditModal(p) {
        this.modalMode = 'edit';
        this.formErrors = {};
        this.formData = {
            id: p.id,
            sku: p.sku,
            barcode: p.barcode,
            name: p.name,
            category: p.category,
            unit: p.unit,
            cost_price: p.cost_price,
            price: p.original_price,
            member_price: p.original_member_price,
            stock: p.stock,
            min_stock: p.min_stock,
            is_taxable: p.is_taxable,
            tax_rate: p.tax_rate,
            expired_date: p.expired_date || '',
            clearance_discount_percent: p.clearance_discount_percent,
            rack_location: p.rack_location || 'Lorong Umum',
            is_consignment: p.is_consignment,
            supplier_id: p.supplier_id || '',
            commission_percent: p.commission_percent || 15
        };
        this.showProductModal = true;
    },

    async saveProduct() {
        this.formErrors = {};
        if (!this.formData.name || !this.formData.name.trim()) this.formErrors.name = 'Nama produk wajib diisi.';
        if (!this.formData.barcode || !this.formData.barcode.trim()) this.formErrors.barcode = 'Barcode wajib diisi.';
        if (Object.keys(this.formErrors).length > 0) return;

        this.isLoading = true;
        try {
            const url = this.modalMode === 'create' ? '/api/products' : `/api/products/${this.formData.id}`;
            const method = this.modalMode === 'create' ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.formData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showProductModal = false;
                this.triggerToast(json.message);
                await this.fetchProducts(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan produk.', 'error');
                if (json.errors) this.formErrors = json.errors;
            }
        } catch (e) {
            this.triggerToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    openUnitModal(p) {
        this.selectedProductForUnit = p;
        this.unitFormData = {
            unit_name: 'Dus',
            conversion_factor: 24,
            price: Math.round(p.price * 24 * 0.95), // default slight discount for bulk
            barcode: p.barcode + '-DUS'
        };
        this.showUnitModal = true;
    },

    async saveUnitConversion() {
        if (!this.selectedProductForUnit) return;
        this.isLoading = true;

        try {
            const res = await fetch(`/api/products/${this.selectedProductForUnit.id}/units`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.unitFormData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showUnitModal = false;
                this.triggerToast(json.message);
                await this.fetchProducts(this.currentPage);
            } else {
                this.triggerToast(json.message || 'Gagal menambahkan satuan konversi.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async deleteUnit(productId, unitId) {
        if (!confirm('Hapus konversi satuan ini?')) return;
        try {
            const res = await fetch(`/api/products/${productId}/units/${unitId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(json.message);
                await this.fetchProducts(this.currentPage);
            }
        } catch (e) {
            this.triggerToast('Gagal menghapus satuan.', 'error');
        }
    },

    openDeleteModal(p) {
        this.productToDelete = p;
        this.showDeleteModal = true;
    },

    async confirmDelete() {
        if (!this.productToDelete) return;
        this.isLoading = true;
        try {
            const res = await fetch(`/api/products/${this.productToDelete.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.showDeleteModal = false;
                this.triggerToast(json.message);
                await this.fetchProducts(1);
            } else {
                this.triggerToast(json.message || 'Gagal menghapus produk.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    }
}"
x-init="initOpening(); initInventory();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar -->
    <x-manager-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[72px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[18px] font-black text-gray-900 tracking-tight">Manajemen Inventaris, Stok &amp; Konsinyasi</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">Koperasi ILP</span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <a href="{{ url('/stock-opname') }}" class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 transition-colors">
                    <span class="material-symbols-rounded text-[18px] text-[#0052cc]">fact_check</span>
                    <span>Stock Opname</span>
                </a>
                <a href="{{ url('/umkm-partners') }}" class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 transition-colors">
                    <span class="material-symbols-rounded text-[18px] text-amber-600">handshake</span>
                    <span>Mitra Konsinyasi</span>
                </a>
                <button @click="fetchProducts(currentPage)" class="p-2 hover:bg-gray-100 rounded-lg text-gray-600 transition-colors" title="Refresh Data API">
                    <span class="material-symbols-rounded text-[22px]" :class="isLoading ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col">
            
            <!-- 5 Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <!-- Card 1: Total SKU -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500">TOTAL PRODUK</span>
                        <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[24px] font-black text-gray-900 leading-none" x-text="summary.total_products">0</h3>
                        <p class="text-[11px] text-gray-400 mt-1" x-text="summary.total_stock_units.toLocaleString('id-ID') + ' unit stok fisik'"></p>
                    </div>
                </div>

                <!-- Card 2: Stok Rendah Alert -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col justify-between cursor-pointer hover:border-amber-300 transition-colors"
                     @click="stockFilter = (stockFilter === 'STOK_RENDAH' ? 'all' : 'STOK_RENDAH'); fetchProducts(1);">
                    <div class="flex justify-between items-center">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-amber-700">STOK RENDAH</span>
                        <span class="material-symbols-rounded text-[20px] text-amber-600">warning</span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[24px] font-black text-amber-600 leading-none" x-text="summary.low_stock_count">0</h3>
                        <p class="text-[11px] text-gray-400 mt-1">Stok &le; ambang batas min</p>
                    </div>
                </div>

                <!-- Card 3: Alarm Merah Kritis Kadaluwarsa -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col justify-between cursor-pointer hover:border-red-300 transition-colors"
                     @click="expiredFilter = (expiredFilter === 'CRITICAL' ? 'all' : 'CRITICAL'); fetchProducts(1);">
                    <div class="flex justify-between items-center">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-red-600">KRITIS H-14 (ALARM MERAH)</span>
                        <span class="material-symbols-rounded text-[20px] text-red-600">alarm_on</span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[24px] font-black text-red-600 leading-none" x-text="summary.critical_expired_count">0</h3>
                        <p class="text-[11px] text-gray-400 mt-1">Otomatis diskon kasir</p>
                    </div>
                </div>

                <!-- Card 4: Alarm Kuning Warning H-30..H-15 -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col justify-between cursor-pointer hover:border-amber-300 transition-colors"
                     @click="expiredFilter = (expiredFilter === 'WARNING' ? 'all' : 'WARNING'); fetchProducts(1);">
                    <div class="flex justify-between items-center">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-yellow-700">WARNING H-30 (KUNING)</span>
                        <span class="material-symbols-rounded text-[20px] text-yellow-600">notifications_active</span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[24px] font-black text-yellow-600 leading-none" x-text="summary.warning_expired_count">0</h3>
                        <p class="text-[11px] text-gray-400 mt-1">Mendekati kedaluwarsa</p>
                    </div>
                </div>

                <!-- Card 5: Nilai Total Inventaris -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-center text-gray-400">
                        <span class="text-[10.5px] font-bold uppercase tracking-wider text-emerald-700">ASET INVENTARIS</span>
                        <span class="material-symbols-rounded text-[20px] text-emerald-600">account_balance</span>
                    </div>
                    <div class="mt-2">
                        <h3 class="text-[18px] font-mono font-black text-emerald-600 leading-none" x-text="formatRupiah(summary.total_inventory_value)">Rp 0</h3>
                        <p class="text-[11px] text-gray-400 mt-1" x-text="summary.consignment_count + ' SKU Konsinyasi UMKM'"></p>
                    </div>
                </div>
            </div>

            <!-- Filter Toolbar -->
            <div class="bg-white rounded-2xl border border-gray-200/90 p-3 shadow-xs mb-6 flex flex-wrap items-center gap-3">
                <!-- Search with Debounce -->
                <div class="relative flex-1 min-w-[220px]">
                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                    <input type="text"
                           x-model="searchQuery"
                           @input="onSearchInput()"
                           placeholder="Cari barcode, SKU, nama barang..."
                           class="w-full pl-9 pr-3 py-2 rounded-xl border border-gray-200 text-xs focus:outline-none focus:border-[#0052cc]">
                </div>

                <!-- Category Filter -->
                <select x-model="selectedCategory"
                        @change="fetchProducts(1)"
                        class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#0052cc]">
                    <template x-for="c in categories" :key="c">
                        <option :value="c" x-text="c"></option>
                    </template>
                </select>

                <!-- Stock Alert Filter -->
                <select x-model="stockFilter"
                        @change="fetchProducts(1)"
                        class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#0052cc]">
                    <option value="all">Semua Kondisi Stok</option>
                    <option value="STOK_RENDAH">Hanya Stok Rendah (&le; Min)</option>
                    <option value="HABIS">Hanya Stok Habis (0)</option>
                    <option value="TERSEDIA">Stok Aman</option>
                </select>

                <!-- Expired Alarm Filter -->
                <select x-model="expiredFilter"
                        @change="fetchProducts(1)"
                        class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#0052cc]">
                    <option value="all">Semua Kedaluwarsa</option>
                    <option value="CRITICAL">Alarm Merah: Kritis (&le; H-14)</option>
                    <option value="WARNING">Alarm Kuning: Warning (H-15 s/d H-30)</option>
                    <option value="EXPIRED">Sudah Kedaluwarsa</option>
                </select>

                <!-- Consignment Filter -->
                <select x-model="consignmentFilter"
                        @change="fetchProducts(1)"
                        class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700 focus:outline-none focus:border-[#0052cc]">
                    <option value="all">Semua Tipe Barang</option>
                    <option value="only_consignment">Titipan Konsinyasi UMKM</option>
                    <option value="only_regular">Barang Dagang Reguler</option>
                </select>

                <!-- Add Button -->
                <button type="button"
                        @click="openCreateModal()"
                        class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 shrink-0 active:scale-95">
                    <span class="material-symbols-rounded text-[18px]">add</span>
                    <span>Tambah Produk</span>
                </button>
            </div>

            <!-- Table Card -->
            <div class="bg-white border border-gray-200/90 rounded-2xl shadow-xs overflow-hidden flex flex-col mb-6">
                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left text-xs min-w-[1100px] border-collapse">
                        <thead class="bg-gray-50/90 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[10.5px]">
                            <tr>
                                <th class="px-5 py-3.5">PRODUK &amp; BARCODE</th>
                                <th class="px-5 py-3.5">KATEGORI &amp; RAK</th>
                                <th class="px-5 py-3.5 text-center">STOK &amp; ALERT</th>
                                <th class="px-5 py-3.5">KEDALUWARSA &amp; ALARM</th>
                                <th class="px-5 py-3.5">SATUAN &amp; MULTI-KONVERSI</th>
                                <th class="px-5 py-3.5">KONSINYASI UMKM</th>
                                <th class="px-5 py-3.5 text-right">HARGA UMUM / MEMBER</th>
                                <th class="px-5 py-3.5 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <!-- Loading Skeleton -->
                            <template x-if="isLoading && products.length === 0">
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                        <div class="flex items-center justify-center gap-2">
                                            <span class="material-symbols-rounded animate-spin text-[22px] text-[#0052cc]">progress_activity</span>
                                            <span>Memuat katalog inventaris dari server...</span>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty -->
                            <template x-if="!isLoading && products.length === 0">
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                        Tidak ada produk yang sesuai dengan kriteria filter.
                                    </td>
                                </tr>
                            </template>

                            <!-- Data Rows -->
                            <template x-for="p in products" :key="p.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <!-- Nama & Barcode -->
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900 leading-tight" x-text="p.name"></div>
                                        <div class="flex items-center gap-2 mt-0.5 text-gray-400 font-mono text-[11px]">
                                            <span x-text="p.barcode"></span>
                                            <span>&bull;</span>
                                            <span x-text="p.sku"></span>
                                        </div>
                                    </td>

                                    <!-- Kategori & Lokasi Rak -->
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-gray-800" x-text="p.category"></div>
                                        <div class="text-[11px] text-gray-400 flex items-center gap-1 mt-0.5">
                                            <span class="material-symbols-rounded text-[13px]">location_on</span>
                                            <span x-text="p.rack_location || '-'"></span>
                                        </div>
                                    </td>

                                    <!-- Stok & Stock Threshold Alert -->
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="font-mono font-black text-sm"
                                             :class="p.stock <= 0 ? 'text-red-600' : (p.is_low_stock ? 'text-amber-600' : 'text-gray-900')"
                                             x-text="p.stock + ' ' + p.unit">
                                        </div>
                                        <div class="text-[10px] text-gray-400" x-text="'Min: ' + p.min_stock"></div>
                                        <span class="inline-block mt-1 px-2 py-0.2 rounded-full text-[9.5px] font-bold border"
                                              :class="p.stock <= 0 ? 'bg-red-50 text-red-700 border-red-200' : (p.is_low_stock ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200')"
                                              x-text="p.stock_status">
                                        </span>
                                    </td>

                                    <!-- Kedaluwarsa & Two-Tier Alarm System -->
                                    <td class="px-5 py-3.5 whitespace-nowrap">
                                        <div class="font-mono text-xs font-semibold" x-text="p.expired_date || '-'"></div>
                                        <template x-if="p.expiration_alert_level === 'CRITICAL'">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 rounded text-[10px] font-bold bg-red-100 text-red-700 border border-red-200 animate-pulse">
                                                <span class="material-symbols-rounded text-[12px]">alarm</span>
                                                <span x-text="'Alarm Merah H-' + p.days_until_expiration + ' (Diskon ' + p.clearance_discount_percent + '%)'"></span>
                                            </span>
                                        </template>
                                        <template x-if="p.expiration_alert_level === 'WARNING'">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 rounded text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-300">
                                                <span class="material-symbols-rounded text-[12px]">notification_important</span>
                                                <span x-text="'Alarm Kuning H-' + p.days_until_expiration"></span>
                                            </span>
                                        </template>
                                        <template x-if="p.expiration_alert_level === 'NORMAL' && p.expired_date">
                                            <span class="text-[10px] text-emerald-600 font-bold block mt-0.5">Aman</span>
                                        </template>
                                    </td>

                                    <!-- Multi-Satuan Unit Conversion -->
                                    <td class="px-5 py-3.5">
                                        <div class="text-[11px] font-semibold text-gray-700" x-text="'Eceran: ' + p.unit"></div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <template x-for="u in p.units" :key="u.id">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 border border-blue-200 rounded text-[10px] font-bold text-[#0052cc]">
                                                    <span x-text="u.unit_name + ' (' + u.conversion_factor + ' ' + p.unit + ')'"></span>
                                                    <button type="button" @click="deleteUnit(p.id, u.id)" class="text-blue-400 hover:text-red-500 font-mono text-[9px]">&times;</button>
                                                </span>
                                            </template>
                                            <button type="button"
                                                    @click="openUnitModal(p)"
                                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 rounded text-[10px] font-bold text-gray-600 transition-colors"
                                                    title="Tambah Konversi Satuan Dus / Pack">
                                                <span class="material-symbols-rounded text-[12px]">add</span>
                                                <span>Satuan</span>
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Konsinyasi UMKM -->
                                    <td class="px-5 py-3.5">
                                        <template x-if="p.is_consignment">
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                    <span class="material-symbols-rounded text-[12px]">store</span>
                                                    <span>Titipan UMKM</span>
                                                </span>
                                                <div class="text-[11px] font-bold text-gray-800 mt-0.5" x-text="p.supplier_name"></div>
                                                <div class="text-[10px] text-gray-500" x-text="'Komisi: ' + p.commission_percent + '%'"></div>
                                            </div>
                                        </template>
                                        <template x-if="!p.is_consignment">
                                            <span class="text-[11px] text-gray-400">Reguler Koperasi</span>
                                        </template>
                                    </td>

                                    <!-- Harga Jual -->
                                    <td class="px-5 py-3.5 text-right font-mono whitespace-nowrap">
                                        <div class="font-bold text-gray-900" x-text="formatRupiah(p.original_price)"></div>
                                        <div class="text-[11px] text-blue-600" x-text="'Mbr: ' + formatRupiah(p.original_member_price)"></div>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            <button type="button"
                                                    @click="openEditModal(p)"
                                                    class="p-1.5 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                                    title="Edit Data Produk">
                                                <span class="material-symbols-rounded text-[18px]">edit</span>
                                            </button>
                                            <button type="button"
                                                    @click="openDeleteModal(p)"
                                                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                    title="Hapus Produk">
                                                <span class="material-symbols-rounded text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600">
                    <div>
                        Menampilkan <span class="font-bold text-gray-900" x-text="products.length"></span> dari
                        <span class="font-bold text-gray-900" x-text="totalRecords"></span> produk
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="fetchProducts(currentPage - 1)"
                                :disabled="currentPage <= 1 || isLoading"
                                class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40">
                            Sebelumnya
                        </button>
                        <span class="font-mono font-bold px-2" x-text="currentPage + ' / ' + lastPage"></span>
                        <button type="button"
                                @click="fetchProducts(currentPage + 1)"
                                :disabled="currentPage >= lastPage || isLoading"
                                class="px-3 py-1.5 bg-white border border-gray-200 rounded-lg font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-40">
                            Selanjutnya
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Tambah / Edit Produk -->
    <div x-show="showProductModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-gray-200 max-h-[90vh] overflow-y-auto"
             @click.outside="showProductModal = false">
            
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-[#0052cc] text-[24px]">inventory_2</span>
                    <h3 class="font-black text-gray-900 text-base" x-text="modalMode === 'create' ? 'Tambah Produk Master' : 'Edit Data Produk'"></h3>
                </div>
                <button type="button" @click="showProductModal = false" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form @submit.prevent="saveProduct()" class="space-y-4 mt-4 text-xs">
                <!-- Barcode & SKU -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Barcode Scanner <span class="text-red-500">*</span></label>
                        <input type="text" x-model="formData.barcode" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs focus:outline-none focus:border-[#0052cc]">
                        <p x-show="formErrors.barcode" x-text="formErrors.barcode" class="text-[11px] text-red-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">SKU Produk</label>
                        <input type="text" x-model="formData.sku" :disabled="modalMode === 'edit'" placeholder="Auto-generate" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs focus:outline-none">
                    </div>
                </div>

                <!-- Nama Produk -->
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Produk <span class="text-red-500">*</span></label>
                    <input type="text" x-model="formData.name" placeholder="Nama lengkap produk dagang" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    <p x-show="formErrors.name" x-text="formErrors.name" class="text-[11px] text-red-500 mt-1"></p>
                </div>

                <!-- Kategori, Satuan Eceran & Lokasi Rak -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Kategori</label>
                        <input type="text" x-model="formData.category" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Satuan Dasar</label>
                        <input type="text" x-model="formData.unit" placeholder="Pcs, Botol" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Lokasi Rak</label>
                        <input type="text" x-model="formData.rack_location" placeholder="Lorong A - Rak 01" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs focus:outline-none">
                    </div>
                </div>

                <!-- Harga Pokok, Harga Jual Umum & Member -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Harga Beli / Pokok</label>
                        <input type="number" x-model="formData.cost_price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Harga Jual Umum</label>
                        <input type="number" x-model="formData.price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs font-bold text-gray-900 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Harga Member</label>
                        <input type="number" x-model="formData.member_price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs text-blue-700 focus:outline-none">
                    </div>
                </div>

                <!-- Stok Fisik & Minimum Stock Threshold -->
                <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-2xl border border-gray-200">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Stok Fisik Saat Ini</label>
                        <input type="number" x-model="formData.stock" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-amber-800 mb-1">Ambang Batas Minimum (Alert)</label>
                        <input type="number" x-model="formData.min_stock" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs font-bold text-amber-700">
                        <p class="text-[10px] text-gray-500 mt-1">Muncul notifikasi jika stok &le; angka ini</p>
                    </div>
                </div>

                <!-- Expired Date & Clearance Discount -->
                <div class="grid grid-cols-2 gap-3 p-3 bg-red-50/50 rounded-2xl border border-red-200">
                    <div>
                        <label class="block font-bold text-red-900 mb-1">Tanggal Kedaluwarsa</label>
                        <input type="date" x-model="formData.expired_date" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-red-900 mb-1">Auto Diskon Clearance (%)</label>
                        <input type="number" x-model="formData.clearance_discount_percent" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono font-bold text-red-700">
                        <p class="text-[10px] text-red-600 mt-1">Otomatis aktif saat H-14 di kasir</p>
                    </div>
                </div>

                <!-- Bagian Konsinyasi UMKM -->
                <div class="p-3 bg-amber-50/50 rounded-2xl border border-amber-200 space-y-3">
                    <label class="flex items-center gap-2 cursor-pointer font-bold text-amber-900">
                        <input type="checkbox" x-model="formData.is_consignment" class="rounded text-[#0052cc]">
                        <span>Ini adalah Barang Titipan Konsinyasi UMKM Lokal</span>
                    </label>

                    <div x-show="formData.is_consignment" class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Mitra UMKM Penyuplai</label>
                            <select x-model="formData.supplier_id" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-semibold">
                                <option value="">Pilih Mitra UMKM</option>
                                <template x-for="s in suppliers" :key="s.id">
                                    <option :value="s.id" x-text="s.name + ' (' + s.code + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Komisi Koperasi (%)</label>
                            <input type="number" step="0.5" x-model="formData.commission_percent" placeholder="15" class="w-full px-3 py-2 rounded-xl border border-gray-300 text-xs font-mono font-bold text-amber-800">
                            <p class="text-[10px] text-gray-500 mt-0.5">Bagi hasil: UMKM <span x-text="(100 - formData.commission_percent) + '%'"></span></p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showProductModal = false" class="px-4 py-2 border rounded-xl text-xs font-bold text-gray-600">Batal</button>
                    <button type="submit" :disabled="isLoading" class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Multi-Satuan Unit Conversion -->
    <div x-show="showUnitModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showUnitModal = false">
            <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                <span class="material-symbols-rounded text-[#0052cc] text-[22px]">category</span>
                <div>
                    <h3 class="font-black text-gray-900 text-base">Tambah Hierarki Satuan</h3>
                    <p class="text-xs text-gray-400" x-text="selectedProductForUnit ? selectedProductForUnit.name : ''"></p>
                </div>
            </div>

            <form @submit.prevent="saveUnitConversion()" class="space-y-4 mt-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Satuan Lebih Tinggi</label>
                    <input type="text" x-model="unitFormData.unit_name" placeholder="Contoh: Dus, Karton, Pack" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-bold text-xs focus:outline-none">
                </div>

                <div class="p-3 bg-blue-50 rounded-2xl border border-blue-200 space-y-1">
                    <label class="block font-bold text-blue-900 mb-1">Faktor Konversi Isi</label>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">1</span>
                        <span class="font-bold text-[#0052cc]" x-text="unitFormData.unit_name || 'Dus'"></span>
                        <span class="font-bold text-gray-700">=</span>
                        <input type="number" min="2" x-model="unitFormData.conversion_factor" class="w-20 px-3 py-1.5 rounded-xl border border-gray-300 font-mono font-black text-center text-sm">
                        <span class="font-bold text-gray-700" x-text="selectedProductForUnit ? selectedProductForUnit.unit : 'Pcs'"></span>
                    </div>
                    <p class="text-[10px] text-blue-700 mt-1">Saat kasir menjual 1 satuan ini, stok eceran otomatis terpotong presisi.</p>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Harga Jual Satuan Grosir (Rp)</label>
                    <input type="number" x-model="unitFormData.price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono font-bold text-sm text-gray-900 focus:outline-none">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Barcode Khusus Kemasan Grosir</label>
                    <input type="text" x-model="unitFormData.barcode" placeholder="Barcode di kardus/kemasan luar" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs focus:outline-none">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showUnitModal = false" class="px-4 py-2 border rounded-xl text-xs font-bold text-gray-600">Batal</button>
                    <button type="submit" :disabled="isLoading" class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold">Simpan Konversi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="showDeleteModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showDeleteModal = false">
            <h3 class="text-center font-black text-gray-900 text-base mb-1">Hapus Produk?</h3>
            <p class="text-center text-xs text-gray-500 mb-4" x-text="'Produk ' + (productToDelete ? productToDelete.name : '') + ' akan dihapus dari katalog master.'"></p>
            <div class="flex gap-2">
                <button type="button" @click="showDeleteModal = false" class="flex-1 py-2 rounded-xl border text-xs font-bold text-gray-600">Batal</button>
                <button type="button" @click="confirmDelete()" :disabled="isLoading" class="flex-1 py-2 rounded-xl bg-red-600 text-white text-xs font-bold">Hapus</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="showToast"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'"
         style="display: none;">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
