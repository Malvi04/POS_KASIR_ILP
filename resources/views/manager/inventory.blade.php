@extends('layouts.app')

@section('title', 'Inventory Management & Data Barang - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    selectedCategory: 'all',
    showDetailModal: false,
    selectedProduct: null,
    showScannerModal: false,
    scannerInput: '',

    // Toast Notification
    toastMessage: '',
    toastType: 'success',
    showToast: false,
    toastTimeout: null,
    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        if (this.toastTimeout) clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => {
            this.showToast = false;
        }, 3500);
    },

    // Default Seed Products (10 items)
    defaultProducts: [
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
            expiredDate: '2027-11-15'
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
            expiredDate: '2027-01-12'
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
            expiredDate: '2026-11-10'
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
            expiredDate: '2027-04-30'
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
            expiredDate: '2028-09-20'
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
            expiredDate: '2027-08-18'
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
            expiredDate: '2027-12-22'
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
            expiredDate: '2027-10-05'
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
            expiredDate: '2026-11-15'
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
            expiredDate: '2028-01-10'
        }
    ],

    products: [],

    initProducts() {
        try {
            const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.products = parsed;
                    return;
                }
            }
        } catch (e) {
            console.error('Failed to parse inventory storage:', e);
        }
        this.products = JSON.parse(JSON.stringify(this.defaultProducts));
        this.saveProductsToStorage();
    },

    saveProductsToStorage() {
        try {
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(this.products));
            window.dispatchEvent(new CustomEvent('pos-inventory-synced', { detail: { count: this.products.length } }));
        } catch (e) {
            console.error('Failed to save inventory products:', e);
        }
    },

    resetToDefaultCatalog() {
        if (confirm('Kembalikan seluruh katalog ke data bawaan simulasi (10 produk)? Data yang telah dibuat atau diedit akan ditimpa.')) {
            this.products = JSON.parse(JSON.stringify(this.defaultProducts));
            this.saveProductsToStorage();
            this.triggerToast('Katalog berhasil di-reset ke 10 produk bawaan.', 'info');
        }
    },

    get availableCategories() {
        const standard = ['Minuman', 'Snack', 'Kebutuhan Pokok', 'Pembersih', 'Makanan', 'Obat & Kesehatan'];
        const existing = this.products.map(p => p.category).filter(Boolean);
        return Array.from(new Set([...standard, ...existing]));
    },

    get filteredProducts() {
        return this.products.filter(p => {
            if (this.selectedCategory !== 'all' && p.category !== this.selectedCategory) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = (p.name || '').toLowerCase().includes(q);
                const matchBrand = (p.brand || '').toLowerCase().includes(q);
                const matchBarcode = (p.barcode || '').includes(q);
                const matchSku = (p.sku || '').toLowerCase().includes(q);
                const matchRack = (p.rackLocation || '').toLowerCase().includes(q);
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

    get totalInventoryValue() {
        return this.products.reduce((acc, p) => acc + ((Number(p.stock) || 0) * (Number(p.price) || 0)), 0);
    },

    calculateStatus(stock, minStock, expiredDate) {
        const s = Number(stock) || 0;
        const ms = Number(minStock) || 0;
        if (s === 0) return 'HABIS';

        if (expiredDate) {
            try {
                let expTime = null;
                if (/^\d{4}-\d{2}-\d{2}$/.test(expiredDate)) {
                    expTime = new Date(expiredDate + 'T23:59:59').getTime();
                } else {
                    const parsed = Date.parse(expiredDate);
                    if (!isNaN(parsed)) expTime = parsed;
                }
                if (expTime) {
                    const diffDays = Math.ceil((expTime - Date.now()) / (1000 * 60 * 60 * 24));
                    if (diffDays <= 30 && diffDays >= 0) {
                        return 'NEAR_EXPIRED';
                    }
                }
            } catch (err) {}
        }

        if (s <= ms) return 'STOK_RENDAH';
        return 'TERSEDIA';
    },

    // Form Modal (Create & Edit) State
    showProductModal: false,
    modalMode: 'create',
    formSkuOriginal: '',
    formData: {
        sku: '',
        barcode: '',
        name: '',
        brand: '',
        category: 'Minuman',
        rackLocation: '',
        stock: 0,
        minStock: 10,
        unit: 'Pcs',
        price: 0,
        memberPrice: 0,
        batchLot: '',
        expiredDate: ''
    },
    formErrors: {},

    openCreateModal() {
        this.modalMode = 'create';
        this.formSkuOriginal = '';
        this.formErrors = {};

        let maxNum = 0;
        this.products.forEach(p => {
            const m = (p.sku || '').match(/PRD-(\d+)/i);
            if (m) {
                const num = parseInt(m[1], 10);
                if (num > maxNum) maxNum = num;
            }
        });
        const nextSku = 'PRD-' + String(maxNum + 1).padStart(3, '0');
        const randomBarcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);

        const oneYearFromNow = new Date();
        oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
        const defaultExpDate = oneYearFromNow.toISOString().split('T')[0];

        this.formData = {
            sku: nextSku,
            barcode: randomBarcode,
            name: '',
            brand: '',
            category: 'Minuman',
            rackLocation: 'Lorong A - Rak 01',
            stock: 24,
            minStock: 10,
            unit: 'Pcs',
            price: 5000,
            memberPrice: 4500,
            batchLot: 'BATCH-' + new Date().getFullYear() + String(new Date().getMonth() + 1).padStart(2, '0'),
            expiredDate: defaultExpDate
        };
        this.showProductModal = true;
    },

    openEditModal(product) {
        this.modalMode = 'edit';
        this.formSkuOriginal = product.sku;
        this.formErrors = {};

        let expVal = '';
        if (product.expiredDate) {
            if (/^\d{4}-\d{2}-\d{2}$/.test(product.expiredDate)) {
                expVal = product.expiredDate;
            } else {
                const months = { 'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04', 'Mei': '05', 'May': '05', 'Jun': '06', 'Jul': '07', 'Agu': '08', 'Aug': '08', 'Sep': '09', 'Okt': '10', 'Oct': '10', 'Nov': '11', 'Des': '12', 'Dec': '12' };
                const parts = product.expiredDate.split(' ');
                if (parts.length === 3) {
                    const day = parts[0].padStart(2, '0');
                    const m = months[parts[1]] || '01';
                    const yr = parts[2];
                    expVal = `${yr}-${m}-${day}`;
                }
            }
        }

        this.formData = {
            sku: product.sku,
            barcode: product.barcode,
            name: product.name,
            brand: product.brand || '',
            category: product.category || 'Minuman',
            rackLocation: product.rackLocation || '',
            stock: Number(product.stock) || 0,
            minStock: Number(product.minStock) || 0,
            unit: product.unit || 'Pcs',
            price: Number(product.price) || 0,
            memberPrice: Number(product.memberPrice || product.price) || 0,
            batchLot: product.batchLot || '',
            expiredDate: expVal
        };
        this.showProductModal = true;
    },

    generateRandomBarcode() {
        this.formData.barcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);
    },

    saveProduct() {
        this.formErrors = {};
        const f = this.formData;

        if (!f.name || !f.name.trim()) {
            this.formErrors.name = 'Nama produk wajib diisi.';
        }
        if (!f.barcode || !f.barcode.trim()) {
            this.formErrors.barcode = 'Barcode produk wajib diisi.';
        } else {
            const dupBarcode = this.products.find(p => p.barcode.trim() === f.barcode.trim() && (this.modalMode === 'create' || p.sku !== this.formSkuOriginal));
            if (dupBarcode) {
                this.formErrors.barcode = `Barcode '${f.barcode}' sudah digunakan oleh produk '${dupBarcode.name}'.`;
            }
        }
        if (!f.sku || !f.sku.trim()) {
            this.formErrors.sku = 'SKU produk wajib diisi.';
        } else {
            const dupSku = this.products.find(p => p.sku.trim().toUpperCase() === f.sku.trim().toUpperCase() && (this.modalMode === 'create' || p.sku !== this.formSkuOriginal));
            if (dupSku) {
                this.formErrors.sku = `SKU '${f.sku}' sudah terdaftar pada produk lain.`;
            }
        }
        if (f.price === null || f.price === '' || Number(f.price) < 0) {
            this.formErrors.price = 'Harga jual umum harus &ge; 0.';
        }
        if (f.memberPrice === null || f.memberPrice === '' || Number(f.memberPrice) < 0) {
            this.formErrors.memberPrice = 'Harga member harus &ge; 0.';
        }
        if (f.stock === null || f.stock === '' || Number(f.stock) < 0) {
            this.formErrors.stock = 'Jumlah stok tidak boleh negatif.';
        }
        if (f.minStock === null || f.minStock === '' || Number(f.minStock) < 0) {
            this.formErrors.minStock = 'Minimum stok buffer tidak boleh negatif.';
        }

        if (Object.keys(this.formErrors).length > 0) {
            return;
        }

        const calculatedStatus = this.calculateStatus(f.stock, f.minStock, f.expiredDate);

        let displayExpDate = f.expiredDate || '-';
        if (/^\d{4}-\d{2}-\d{2}$/.test(f.expiredDate)) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const [y, m, d] = f.expiredDate.split('-');
            displayExpDate = `${parseInt(d, 10)} ${months[parseInt(m, 10) - 1]} ${y}`;
        }

        const payload = {
            sku: f.sku.trim().toUpperCase(),
            barcode: f.barcode.trim(),
            name: f.name.trim(),
            brand: f.brand.trim() || 'Lokal Mandiri',
            category: f.category,
            rackLocation: f.rackLocation.trim() || 'Display Rak',
            stock: Number(f.stock),
            minStock: Number(f.minStock),
            unit: f.unit.trim() || 'Pcs',
            price: Number(f.price),
            memberPrice: Number(f.memberPrice),
            status: calculatedStatus,
            batchLot: f.batchLot.trim() || '-',
            expiredDate: displayExpDate
        };

        if (this.modalMode === 'create') {
            this.products.unshift(payload);
            this.saveProductsToStorage();
            this.showProductModal = false;
            this.triggerToast(`Produk '${payload.name}' berhasil ditambahkan ke katalog stok.`, 'success');
        } else {
            const idx = this.products.findIndex(p => p.sku === this.formSkuOriginal);
            if (idx !== -1) {
                this.products[idx] = payload;
                this.saveProductsToStorage();
                if (this.selectedProduct && this.selectedProduct.sku === this.formSkuOriginal) {
                    this.selectedProduct = payload;
                }
                this.showProductModal = false;
                this.triggerToast(`Produk '${payload.name}' berhasil diperbarui.`, 'success');
            } else {
                this.formErrors.general = 'Produk tidak ditemukan di database katalog.';
            }
        }
    },

    // Strict Delete Confirmation Modal State
    showDeleteModal: false,
    productToDelete: null,
    deleteConfirmInput: '',

    openDeleteModal(product) {
        this.productToDelete = product;
        this.deleteConfirmInput = '';
        this.showDeleteModal = true;
    },

    confirmDelete() {
        if (!this.productToDelete) return;
        if (this.deleteConfirmInput.trim().toLowerCase() !== 'hapus') {
            return;
        }

        const name = this.productToDelete.name;
        const targetSku = this.productToDelete.sku;

        this.products = this.products.filter(p => p.sku !== targetSku);
        this.saveProductsToStorage();

        if (this.selectedProduct && this.selectedProduct.sku === targetSku) {
            this.showDetailModal = false;
            this.selectedProduct = null;
        }

        this.showDeleteModal = false;
        this.productToDelete = null;
        this.deleteConfirmInput = '';
        this.triggerToast(`Produk '${name}' berhasil dihapus dari database.`, 'info');
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
        this.triggerToast(`Menyaring produk dengan barcode '${this.searchQuery}'`, 'info');
    }
}"
x-init="initOpening(); initProducts();"
@pos-inventory-synced.window="initProducts()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Inventory Management & Data Barang</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">inventory_2</span>
                    <span>Kelola Master Produk</span>
                </span>
            </div>

            <div class="flex items-center gap-6 text-gray-500">
                <button class="hover:text-gray-900 transition-colors relative" title="Notifikasi">
                    <span class="material-symbols-rounded text-[24px]">notifications</span>
                </button>
                <button @click="initProducts()" class="hover:text-gray-900 transition-colors" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <span class="material-symbols-rounded text-green-500 text-[20px]">wifi</span>
                    <span class="text-[14px] font-semibold text-gray-600">Terhubung</span>
                </div>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">

            <!-- Filter & Search Bar with Add Product Button -->
            <div class="flex flex-col md:flex-row items-center gap-4">
                <div class="relative flex-[2] w-full">
                    <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Cari nama produk, brand, barcode, SKU, atau rak..."
                           class="w-full pl-12 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-white">
                </div>

                <div class="relative flex-1 w-full md:w-auto">
                    <select x-model="selectedCategory"
                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs transition-all font-semibold cursor-pointer">
                        <option value="all">Semua Kategori</option>
                        <template x-for="cat in availableCategories" :key="cat">
                            <option :value="cat" x-text="cat"></option>
                        </template>
                    </select>
                    <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                </div>

                <button type="button"
                        @click="openCreateModal()"
                        class="bg-[#0052cc] text-white px-5 py-2.5 rounded-xl font-bold text-[13px] flex items-center justify-center gap-2 shadow-xs hover:bg-[#0043a8] transition-colors shrink-0 cursor-pointer active:scale-95">
                    <span class="material-symbols-rounded text-[20px]">add</span>
                    Tambah Produk Baru
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL SKU KATALOG</span>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-3xl font-black text-gray-800 tracking-tight" x-text="totalItems">0</span>
                        <span class="bg-[#dbeafe] text-[#0052cc] text-[11px] font-bold px-2.5 py-1 rounded-md" x-text="totalItems + ' Aktif'"></span>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex flex-col justify-between relative overflow-hidden border-l-4 border-l-amber-500">
                    <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">STOK RENDAH</span>
                    <div class="flex items-center justify-between mt-3 relative z-10">
                        <span class="text-3xl font-black text-amber-500 tracking-tight" x-text="lowStockCount">0</span>
                        <span class="material-symbols-rounded text-amber-500 text-[26px]">warning</span>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white rounded-2xl border border-red-200 p-5 shadow-xs flex flex-col justify-between border-l-4 border-l-[#e11d48] relative overflow-hidden">
                    <span class="text-[11px] font-bold text-[#e11d48] tracking-wider uppercase">MENDEKATI EXPIRED</span>
                    <div class="flex items-center justify-between mt-3 relative z-10">
                        <span class="text-3xl font-black text-[#e11d48] tracking-tight" x-text="nearExpiredCount">0</span>
                        <span class="material-symbols-rounded text-[#e11d48] text-[26px]">event_busy</span>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">NILAI INVENTARIS</span>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-[24px] font-black text-gray-800 tracking-tight font-mono" x-text="formatRupiah(totalInventoryValue)">Rp 0</span>
                        <span class="text-[12px] font-bold text-gray-400">IDR</span>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory</span>
                        <h2 class="text-sm font-bold text-gray-900">Tabel Master Data Barang</h2>
                    </div>
                    <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredProducts.length + ' dari ' + totalItems + ' produk'"></span>
                </div>

                <div class="overflow-x-auto min-w-0 w-full">
                    <table class="w-full text-left border-collapse min-w-[950px]">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                <th class="px-6 py-3.5 text-left w-[280px]">PRODUK & BRAND</th>
                                <th class="px-4 py-3.5 text-left w-[150px]">BARCODE / SKU</th>
                                <th class="px-4 py-3.5 text-center w-[130px]">KATEGORI</th>
                                <th class="px-4 py-3.5 text-center w-[110px]">STOK</th>
                                <th class="px-4 py-3.5 text-right w-[150px]">HARGA JUAL</th>
                                <th class="px-6 py-3.5 text-center w-[130px]">STATUS</th>
                                <th class="px-4 py-3.5 text-center w-[110px]">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px] divide-y divide-gray-100">
                            <template x-for="p in filteredProducts" :key="p.sku">
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-3.5">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-gray-900 text-[14px]" x-text="p.name"></span>
                                            <span class="text-[12px] text-gray-500 mt-0.5" x-text="p.brand || 'Lokal'"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono font-medium text-gray-700 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-[12px]" x-text="p.barcode"></span>
                                            <span class="text-[10px] text-gray-400" x-text="p.sku"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        <span class="bg-[#dbeafe] text-[#0052cc] text-[11px] font-bold px-3 py-1 rounded-full whitespace-nowrap" x-text="p.category"></span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        <div class="flex flex-col items-center">
                                            <span class="font-bold text-[14px]"
                                                  :class="p.stock === 0 ? 'text-gray-400 line-through' : (p.stock <= p.minStock ? 'text-amber-600' : 'text-gray-900')"
                                                  x-text="p.stock"></span>
                                            <span class="text-[11px] text-gray-500" x-text="p.unit || 'Pcs'"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex flex-col items-end">
                                            <span class="font-mono font-bold text-gray-900 text-[14px]" x-text="formatRupiah(p.price)"></span>
                                            <span class="text-[10.5px] text-emerald-600 font-semibold" x-text="'Mbr: ' + formatRupiah(p.memberPrice || p.price)"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                                        <template x-if="p.status === 'TERSEDIA'">
                                            <div class="inline-flex items-center gap-1.5 text-green-600 font-bold text-[12px]">
                                                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                                Tersedia
                                            </div>
                                        </template>
                                        <template x-if="p.status === 'STOK_RENDAH'">
                                            <div class="inline-flex items-center gap-1.5 bg-[#fef3c7] text-amber-700 border border-amber-200 px-2 py-1 rounded-md font-bold text-[11px]">
                                                <span class="material-symbols-rounded text-[14px]">warning</span>
                                                Stok Rendah
                                            </div>
                                        </template>
                                        <template x-if="p.status === 'NEAR_EXPIRED'">
                                            <div class="inline-flex items-center gap-1.5 bg-[#ffe4e6] text-[#e11d48] border border-red-200 px-2 py-1 rounded-md font-bold text-[11px]">
                                                <span class="material-symbols-rounded text-[14px]">event_busy</span>
                                                Near Expired
                                            </div>
                                        </template>
                                        <template x-if="p.status === 'HABIS'">
                                            <div class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-600 border border-gray-200 px-2 py-1 rounded-md font-bold text-[11px]">
                                                Habis
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center justify-center gap-1.5">
                                            <button type="button"
                                                    @click="openDetail(p)"
                                                    title="Lihat Detail Produk"
                                                    class="p-1.5 text-gray-400 hover:text-[#0052cc] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[20px]">visibility</span>
                                            </button>
                                            <button type="button"
                                                    @click="openEditModal(p)"
                                                    title="Edit Data Produk"
                                                    class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[20px]">edit</span>
                                            </button>
                                            <button type="button"
                                                    @click="openDeleteModal(p)"
                                                    title="Hapus Produk (Perlu Konfirmasi)"
                                                    class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                                <span class="material-symbols-rounded text-[20px]">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="filteredProducts.length === 0">
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-3">
                                            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400">
                                                <span class="material-symbols-rounded text-[28px]">search_off</span>
                                            </div>
                                            <h4 class="text-sm font-bold text-gray-800">Tidak Ada Produk Ditemukan</h4>
                                            <p class="text-xs text-gray-500">Tidak ada produk yang sesuai dengan filter pencarian.</p>
                                            <button type="button"
                                                    @click="searchQuery = ''; selectedCategory = 'all'"
                                                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-all">
                                                Reset Filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Footer reset data helper -->
                <div class="border-t border-gray-200 px-6 py-4 flex items-center justify-between bg-white text-xs text-gray-500">
                    <span x-text="'Menampilkan ' + filteredProducts.length + ' dari total ' + totalItems + ' produk katalog toko.'"></span>
                    <button type="button"
                            @click="resetToDefaultCatalog()"
                            class="text-[#0052cc] hover:underline font-bold flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">restart_alt</span>
                        <span>Reset Data Default</span>
                    </button>
                </div>
            </div>

            <div class="h-10"></div>
        </div>

        <!-- Floating Action Button -->
        <button type="button"
                @click="showScannerModal = true"
                title="Scan Barcode Produk"
                class="absolute bottom-8 right-8 w-14 h-14 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105 active:scale-95 z-20 cursor-pointer">
            <span class="material-symbols-rounded text-[28px]">qr_code_scanner</span>
        </button>
    </main>

    <!-- TOAST NOTIFICATION -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         style="display: none;"
         class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl text-xs font-bold text-white border"
         :class="{
             'bg-emerald-600 border-emerald-500': toastType === 'success',
             'bg-rose-600 border-rose-500': toastType === 'error',
             'bg-blue-600 border-blue-500': toastType === 'info'
         }">
        <span class="material-symbols-rounded text-[20px]"
              x-text="toastType === 'success' ? 'check_circle' : (toastType === 'error' ? 'error' : 'info')"></span>
        <span x-text="toastMessage"></span>
    </div>

    <!-- MODAL FORM TAMBAH / EDIT PRODUK (CRUD) -->
    <template x-teleport="body">
        <div x-show="showProductModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showProductModal"
                 @click.away="showProductModal = false"
                 x-transition.scale.origin.center
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden border border-gray-200 flex flex-col">

                <!-- Header -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]" x-text="modalMode === 'create' ? 'add_circle' : 'edit_document'"></span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Tambah Master Produk Baru' : 'Edit Informasi & Stok Master Produk'"></h3>
                            <p class="text-xs text-gray-500 font-medium" x-text="modalMode === 'create' ? 'Daftarkan SKU baru ke katalog inventaris toko.' : 'Perbarui data barang, harga, lokasi rak, atau status stok.'"></p>
                        </div>
                    </div>
                    <button @click="showProductModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <!-- Body (Scrollable Form) -->
                <div class="p-6 space-y-4 overflow-y-auto text-xs flex-1">
                    <template x-if="formErrors.general">
                        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 flex items-center gap-2 font-medium">
                            <span class="material-symbols-rounded text-[18px]">error</span>
                            <span x-text="formErrors.general"></span>
                        </div>
                    </template>

                    <!-- Section: Identitas Produk -->
                    <div class="space-y-3">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Identitas Master Produk</span>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Nama Produk <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formData.name"
                                       placeholder="Contoh: Aqua Mineral 600ml"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs">
                                <template x-if="formErrors.name">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.name"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Barcode (EAN-13) <span class="text-rose-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                           x-model="formData.barcode"
                                           placeholder="899..."
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs">
                                    <button type="button"
                                            @click="generateRandomBarcode()"
                                            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl font-bold text-[11px] shrink-0">
                                        Acak
                                    </button>
                                </div>
                                <template x-if="formErrors.barcode">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.barcode"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kode SKU Toko <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formData.sku"
                                       placeholder="PRD-001"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs uppercase">
                                <template x-if="formErrors.sku">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.sku"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Brand / Produsen</label>
                                <input type="text"
                                       x-model="formData.brand"
                                       placeholder="Contoh: Indofood, Unilever, Lokal"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs">
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kategori Produk</label>
                                <select x-model="formData.category"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold text-xs bg-white">
                                    <template x-for="cat in availableCategories" :key="cat">
                                        <option :value="cat" x-text="cat"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Stok & Lokasi Rak -->
                    <div class="space-y-3 pt-2 border-t border-gray-100">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Inventaris & Lokasi Display</span>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-3">
                                <label class="block font-bold text-gray-700 mb-1">Lokasi Rak Display</label>
                                <input type="text"
                                       x-model="formData.rackLocation"
                                       placeholder="Contoh: Lorong A - Rak 01"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs">
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Stok Fisik Aktif <span class="text-rose-500">*</span></label>
                                <input type="number"
                                       min="0"
                                       x-model.number="formData.stock"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-bold text-xs">
                                <template x-if="formErrors.stock">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.stock"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Buffer Minimum Stok <span class="text-rose-500">*</span></label>
                                <input type="number"
                                       min="0"
                                       x-model.number="formData.minStock"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-bold text-xs">
                                <template x-if="formErrors.minStock">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.minStock"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Satuan Unit</label>
                                <select x-model="formData.unit"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold text-xs bg-white">
                                    <option value="Pcs">Pcs</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Pack">Pack</option>
                                    <option value="Pouch">Pouch</option>
                                    <option value="Karton">Karton</option>
                                    <option value="Tetra">Tetra</option>
                                    <option value="Kaleng">Kaleng</option>
                                    <option value="Sachet">Sachet</option>
                                    <option value="Kg">Kg</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Harga Jual -->
                    <div class="space-y-3 pt-2 border-t border-gray-100">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Struktur Harga Jual</span>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Harga Umum (Non-Member) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.price"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                </div>
                                <template x-if="formErrors.price">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.price"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-[#0052cc] mb-1">Harga Anggota Kopkar <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#0052cc] font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.memberPrice"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                </div>
                                <template x-if="formErrors.memberPrice">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.memberPrice"></p>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Batch & Tanggal Kedaluwarsa -->
                    <div class="space-y-3 pt-2 border-t border-gray-100">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Batch & Kedaluwarsa</span>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Nomor Batch / Lot</label>
                                <input type="text"
                                       x-model="formData.batchLot"
                                       placeholder="Contoh: BATCH-202610-01"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs">
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tanggal Kedaluwarsa (Expired)</label>
                                <input type="date"
                                       x-model="formData.expiredDate"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="showProductModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Batal
                    </button>

                    <button type="button"
                            @click="saveProduct()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer active:scale-95">
                        <span class="material-symbols-rounded text-[18px]">save</span>
                        <span x-text="modalMode === 'create' ? 'Simpan Produk Baru' : 'Perbarui Data Produk'"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- STRICT DELETE CONFIRMATION MODAL (HARUS KETIK 'hapus' DULU) -->
    <template x-teleport="body">
        <div x-show="showDeleteModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDeleteModal"
                 @click.away="showDeleteModal = false"
                 x-transition.scale.origin.center
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-rose-200 flex flex-col">

                <div class="px-6 py-5 border-b border-rose-100 bg-rose-50/70 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[24px]">delete_forever</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-rose-900">Konfirmasi Hapus Produk</h3>
                            <p class="text-xs text-rose-700 font-medium">Tindakan ini permanen dan tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                    <button @click="showDeleteModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[22px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="p-4 bg-slate-50 border border-gray-200 rounded-xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Nama Produk:</span>
                            <span class="font-bold text-gray-900" x-text="productToDelete?.name"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Barcode / SKU:</span>
                            <span class="font-mono font-bold text-gray-800" x-text="(productToDelete?.barcode || '-') + ' (' + (productToDelete?.sku || '-') + ')'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Sisa Stok:</span>
                            <span class="font-bold text-rose-600" x-text="(productToDelete?.stock || 0) + ' ' + (productToDelete?.unit || 'Pcs')"></span>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-[11px] leading-relaxed">
                        <strong>Perhatian:</strong> Menghapus produk ini akan menghilangkannya dari master inventory toko dan katalog POS.
                    </div>

                    <div class="space-y-2 pt-1">
                        <label class="block font-bold text-gray-800 text-xs">
                            Untuk mengonfirmasi penghapusan, ketik kata <span class="text-rose-600 font-mono text-sm underline">hapus</span> di bawah ini:
                        </label>
                        <input type="text"
                               x-model="deleteConfirmInput"
                               @keyup.enter="if(deleteConfirmInput.trim().toLowerCase() === 'hapus') confirmDelete()"
                               placeholder="Ketik kata 'hapus' di sini..."
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 font-bold text-xs tracking-wider">

                        <div class="flex items-center justify-between text-[11px] pt-1">
                            <span :class="deleteConfirmInput.trim().toLowerCase() === 'hapus' ? 'text-emerald-600 font-bold' : 'text-gray-400 font-medium'">
                                <template x-if="deleteConfirmInput.trim().toLowerCase() === 'hapus'">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px]">check</span>
                                        <span>Kata kunci sesuai. Tombol hapus aktif.</span>
                                    </span>
                                </template>
                                <template x-if="deleteConfirmInput.trim().toLowerCase() !== 'hapus'">
                                    <span>Wajib mengetik "hapus" dengan tepat</span>
                                </template>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="showDeleteModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Batal
                    </button>

                    <button type="button"
                            @click="confirmDelete()"
                            :disabled="deleteConfirmInput.trim().toLowerCase() !== 'hapus'"
                            :class="deleteConfirmInput.trim().toLowerCase() === 'hapus'
                                ? 'bg-rose-600 hover:bg-rose-700 text-white cursor-pointer shadow-xs active:scale-95'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed border border-gray-300'"
                            class="px-6 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all">
                        <span class="material-symbols-rounded text-[18px]">delete</span>
                        <span>Hapus Produk Permanen</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL DETAIL PRODUK -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDetailModal"
                 @click.away="showDetailModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden border border-gray-200 flex flex-col">

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

                <div class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 bg-slate-50 rounded-xl border border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">LOKASI RAK / LORONG</span>
                            <div class="flex items-center gap-1.5 text-gray-900 font-bold text-sm">
                                <span class="material-symbols-rounded text-[18px] text-[#0052cc]">shelves</span>
                                <span x-text="selectedProduct?.rackLocation || 'Rak Display'"></span>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 rounded-xl border border-gray-200">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">SISA STOK AKTIF</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-xl font-black text-gray-900" x-text="selectedProduct?.stock + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                                <span class="text-[10px] text-gray-400 font-medium" x-text="'(Buffer: ' + selectedProduct?.minStock + ')'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                        <div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase block">HARGA UMUM</span>
                            <span class="text-lg font-black text-gray-900 font-mono mt-0.5 block" x-text="formatRupiah(selectedProduct?.price)"></span>
                            <span class="text-[10px] text-gray-500">Harga non-member</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-[#0052cc] uppercase block">HARGA ANGGOTA KOPKAR</span>
                            <span class="text-lg font-black text-[#0052cc] font-mono mt-0.5 block" x-text="formatRupiah(selectedProduct?.memberPrice || selectedProduct?.price)"></span>
                            <span class="text-[10px] text-emerald-700 font-bold">Harga member toko</span>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Nomor Batch / Lot Produksi:</span>
                            <span class="font-mono font-bold text-gray-800" x-text="selectedProduct?.batchLot || '-'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Batas Tanggal Kedaluwarsa:</span>
                            <span class="font-bold text-gray-800" x-text="selectedProduct?.expiredDate || '-'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500 font-medium">Kategori & Brand:</span>
                            <span class="font-bold text-gray-800" x-text="(selectedProduct?.category || '-') + ' • ' + (selectedProduct?.brand || 'Lokal')"></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button"
                                @click="showDetailModal = false; openEditModal(selectedProduct)"
                                class="px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">edit</span>
                            <span>Edit Produk Ini</span>
                        </button>
                        <button type="button"
                                @click="showDetailModal = false; openDeleteModal(selectedProduct)"
                                class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">delete</span>
                            <span>Hapus Produk Ini</span>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="showDetailModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- SIMULASI SCAN BARCODE MODAL -->
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
                        <h3 class="font-bold text-gray-900 text-sm">Simulasi Scan Barcode</h3>
                    </div>
                    <button @click="showScannerModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <p class="text-xs text-gray-500 mb-4">Scan atau masukkan barcode untuk menyaring daftar produk:</p>

                <div class="space-y-3">
                    <input type="text"
                           x-model="scannerInput"
                           @keyup.enter="handleScan()"
                           placeholder="Scan atau ketik barcode..."
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                @click="scannerInput = '8991234567890'; handleScan();"
                                class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-[10px] font-mono font-bold text-gray-700 cursor-pointer">
                            Aqua 600ml
                        </button>
                        <button type="button"
                                @click="scannerInput = '899275321004'; handleScan();"
                                class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-[10px] font-mono font-bold text-gray-700 cursor-pointer">
                            Indomie Goreng
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button type="button"
                            @click="showScannerModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            @click="handleScan()"
                            class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold cursor-pointer">
                        Cari Produk
                    </button>
                </div>

            </div>
        </div>
    </template>

</div>
@endsection
