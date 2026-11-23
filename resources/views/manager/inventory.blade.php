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
    tableMode: 'standard', // 'standard' | 'ilp_25'
    selectedBranch: 'all', // 'all' (Produk Master (Stok Gudang)), 'CAB-001' (Cabang Pusat), 'CAB-002' (Cabang A), 'CAB-003' (Cabang B)

    getBranchName(code) {
        if (code === 'all') return 'Produk Master (Stok Gudang)';
        if (code === 'CAB-001') return 'Cabang Pusat';
        if (code === 'CAB-002') return 'Cabang A';
        if (code === 'CAB-003') return 'Cabang B';
        if (code === 'GDG-001') return 'Gudang Pusat (DC)';
        return code;
    },

    getProductStock(p, branchCode = null) {
        const b = branchCode || this.selectedBranch;
        if (!p) return 0;
        if (b === 'all' || b === 'GDG-001') {
            if (p.branchStock && p.branchStock['GDG-001'] !== undefined) {
                return Number(p.branchStock['GDG-001']) || 0;
            }
            return Number(p.warehouseStock !== undefined ? p.warehouseStock : (p.masterStock !== undefined ? p.masterStock : p.stock)) || 0;
        }
        if (p.branchStock && p.branchStock[b] !== undefined) {
            return Number(p.branchStock[b]) || 0;
        }
        return 0;
    },

    getProductStatus(p, branchCode = null) {
        const st = this.getProductStock(p, branchCode);
        const min = Number(p.minStock ?? p.stockMin) || 5;
        if (p.act === 'N' || p.status === 'NONAKTIF') return 'NONAKTIF';
        if (st <= 0) return 'HABIS';
        if (st <= min) return 'STOK_RENDAH';
        return 'TERSEDIA';
    },

    isProductInBranch(p, branchCode = null) {
        const b = branchCode || this.selectedBranch;
        if (!b || b === 'all') return true;
        if (!p) return false;

        if (Array.isArray(p.allocatedBranches)) {
            return p.allocatedBranches.includes(b);
        }
        if (p.branchStock && typeof p.branchStock === 'object') {
            return Object.prototype.hasOwnProperty.call(p.branchStock, b);
        }
        return false;
    },

    get branchProducts() {
        if (this.selectedBranch === 'all') {
            return this.products;
        }
        return this.products.filter(p => this.isProductInBranch(p, this.selectedBranch));
    },

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

    formatRupiah(val) {
        const n = Number(val) || 0;
        const abs = Math.abs(n);
        const formatted = new Intl.NumberFormat('id-ID').format(abs);
        return (n < 0 ? '-Rp ' : 'Rp ') + formatted;
    },

    defaultProducts: [],

    products: [],

    deduplicateProducts(items) {
        if (!Array.isArray(items)) return { unique: [], hasDuplicates: false };
        const unique = [];
        const seenSku = new Set();
        const seenBarcode = new Set();
        let hasDuplicates = false;

        items.forEach(p => {
            if (!p) return;
            const sku = p.sku !== undefined && p.sku !== null ? String(p.sku).trim().toUpperCase() : '';
            const barcode = p.barcode !== undefined && p.barcode !== null ? String(p.barcode).trim() : '';

            const isDupSku = Boolean(sku && seenSku.has(sku));
            const isDupBarcode = Boolean(barcode && seenBarcode.has(barcode));

            if (isDupSku || isDupBarcode) {
                hasDuplicates = true;
                return;
            }

            if (sku) seenSku.add(sku);
            if (barcode) seenBarcode.add(barcode);
            unique.push(p);
        });

        return { unique, hasDuplicates };
    },

    async initProducts() {
        try {
            const res = await fetch('/api/products?per_page=100', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                const items = json.data || (Array.isArray(json) ? json : []);
                if (Array.isArray(items)) {
                    const mapped = items.map(p => ({
                        id: p.id,
                        ilp_id: p.ilp_id || String(p.id),
                        sku: p.sku,
                        barcode: p.barcode,
                        name: p.name,
                        brand: p.supplier_name || 'Lokal',
                        category: p.category || 'Umum',
                        unit: p.unit || 'Pcs',
                        costPrice: Number(p.cost_price) || 0,
                        lastCostPrice: Number(p.last_cost_price ?? p.cost_price) || 0,
                        price: Number(p.price ?? p.original_price) || 0,
                        memberPrice: Number(p.member_price ?? p.original_member_price ?? p.price) || 0,
                        minQty1: p.min_qty1 !== null && p.min_qty1 !== undefined ? Number(p.min_qty1) : 1,
                        minQty2: p.min_qty2 !== null && p.min_qty2 !== undefined ? Number(p.min_qty2) : null,
                        minQty3: p.min_qty3 !== null && p.min_qty3 !== undefined ? Number(p.min_qty3) : null,
                        hargaLevel1: Number(p.harga_level1 ?? p.member_price ?? p.price) || 0,
                        disc: Number(p.clearance_discount_percent) || 0,
                        minStock: Number(p.min_stock) || 5,
                        stockMin: Number(p.min_stock) || 5,
                        reorder: Number(p.reorder_point ?? ((p.min_stock || 5) * 2)),
                        ppnOut: Number(p.tax_rate) || 11,
                        ppnChk: (p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N',
                        statusBarang: p.status || 'TERSEDIA',
                        koSup: p.ko_sup || (p.supplier_code || ''),
                        act: p.act || 'Y',
                        masa: p.masa || '',
                        expiredDate: p.expired_date || '',
                        up: Number(p.markup_percent) || 0,
                        dump: p.dump || '',
                        rackLocation: p.rack_location || 'Lorong Toko',
                        branchStock: (p.branch_stock && typeof p.branch_stock === 'object') ? p.branch_stock : {},
                        allocatedBranches: Array.isArray(p.allocated_branches) ? p.allocated_branches : Object.keys(p.branch_stock || {}),
                        masterStock: Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0,
                        warehouseStock: Number(p.warehouse_stock !== undefined ? p.warehouse_stock : (p.branch_stock && p.branch_stock['GDG-001'] !== undefined ? p.branch_stock['GDG-001'] : p.stock)) || 0,
                        stock: Number(p.stock) || 0,
                        status: p.stock_status || this.calculateStatus(p.stock, p.min_stock, p.expired_date),
                        batchLot: 'LOT-' + p.sku,
                        suppliedBy: Array.isArray(p.supplied_by) ? p.supplied_by : [],
                        // 19 Kolom Standar Master Produk Gudang
                        id_produk: p.id_produk || p.ilp_id || String(p.id),
                        kode_barcode: p.kode_barcode || p.barcode || '',
                        kode_produk: p.kode_produk || p.sku || '',
                        qty_produk: p.qty_produk !== undefined ? Number(p.qty_produk) : (Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0),
                        nama_produk: p.nama_produk || p.name || '',
                        satuan_unit: p.satuan_unit || p.unit || 'Pcs',
                        harga_beli: Number(p.harga_beli ?? p.cost_price) || 0,
                        harga_jual_umum: Number(p.harga_jual_umum ?? p.price ?? p.original_price) || 0,
                        harga_jual_member: Number(p.harga_jual_member ?? p.member_price ?? p.original_member_price ?? p.price) || 0,
                        produk_disc: Number(p.produk_disc ?? p.clearance_discount_percent ?? p.discount_percent ?? 0),
                        minimum_stock: Number(p.minimum_stock ?? p.min_stock ?? 5),
                        ppn_out: Number(p.ppn_out ?? p.tax_rate ?? 11),
                        ppn_chk: p.ppn_chk || ((p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N'),
                        harga_beli_terakhir: Number(p.harga_beli_terakhir ?? p.last_cost_price ?? p.cost_price) || 0,
                        status_barang: p.status_barang || p.status || 'TERSEDIA',
                        kode_suplier: p.kode_suplier || p.ko_sup || (p.supplier_code || '-'),
                        manufacturing_date: p.manufacturing_date || '',
                        exp_date: p.exp_date || p.expired_date || ''
                    }));
                    const result = this.deduplicateProducts(mapped);
                    this.products = result.unique;
                    this.saveProductsToStorage();
                    return;
                }
            }
        } catch (e) {
            console.warn('API products fetch fallback:', e);
        }
        this.products = [];
    },

    saveProductsToStorage() {
        window.dispatchEvent(new CustomEvent('pos-inventory-synced', { detail: { count: this.products.length } }));
    },

    get availableCategories() {
        const standard = ['Minuman', 'Snack', 'Kebutuhan Pokok', 'Pembersih', 'Makanan', 'Obat & Kesehatan'];
        const catMap = new Map();
        standard.forEach(c => catMap.set(c.trim().toLowerCase(), c.trim()));
        (this.products || []).forEach(p => {
            if (p && p.category) {
                const label = String(p.category).trim();
                if (label && !catMap.has(label.toLowerCase())) {
                    catMap.set(label.toLowerCase(), label);
                }
            }
        });
        return Array.from(catMap.values());
    },

    get filteredProducts() {
        return this.branchProducts.filter(p => {
            if (this.selectedCategory !== 'all' && p.category !== this.selectedCategory) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = (p.name || '').toLowerCase().includes(q);
                const matchBrand = (p.brand || '').toLowerCase().includes(q);
                const matchBarcode = (p.barcode || '').includes(q);
                const matchSku = (p.sku || '').toLowerCase().includes(q);
                const matchIlpId = (p.ilp_id || '').toLowerCase().includes(q);
                const matchRack = (p.rackLocation || '').toLowerCase().includes(q);
                const matchKoSup = (p.koSup || '').toLowerCase().includes(q);
                return matchName || matchBrand || matchBarcode || matchSku || matchIlpId || matchRack || matchKoSup;
            }
            return true;
        });
    },

    searchTimer: null,
    onSearchInput() {
        this.currentPage = 1;
        const q = (this.searchQuery || '').trim();
        if (this.searchTimer) clearTimeout(this.searchTimer);
        if (q.length >= 2) {
            this.searchTimer = setTimeout(() => {
                this.fetchServerSearch(q);
            }, 300);
        }
    },
    async fetchServerSearch(q) {
        if (!q || q.length < 2) return;
        try {
            const res = await fetch(`/api/products?search=${encodeURIComponent(q)}&per_page=100`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                const items = json.data || (Array.isArray(json) ? json : []);
                if (Array.isArray(items)) {
                    const mapped = items.map(p => ({
                        id: p.id,
                        ilp_id: p.ilp_id || String(p.id),
                        sku: p.sku,
                        barcode: p.barcode,
                        name: p.name,
                        brand: p.supplier_name || p.brand || p.category || 'Lokal',
                        category: p.category || 'Kebutuhan Pokok',
                        unit: p.unit || 'Pcs',
                        costPrice: Number(p.cost_price) || 0,
                        lastCostPrice: Number(p.last_cost_price ?? p.cost_price) || 0,
                        price: Number(p.price ?? p.original_price) || 0,
                        memberPrice: Number(p.member_price ?? p.original_member_price ?? p.price) || 0,
                        minQty1: p.min_qty1 !== null && p.min_qty1 !== undefined ? Number(p.min_qty1) : 1,
                        minQty2: p.min_qty2 !== null && p.min_qty2 !== undefined ? Number(p.min_qty2) : null,
                        minQty3: p.min_qty3 !== null && p.min_qty3 !== undefined ? Number(p.min_qty3) : null,
                        hargaLevel1: Number(p.harga_level1 ?? p.member_price ?? p.price) || 0,
                        disc: Number(p.clearance_discount_percent) || 0,
                        minStock: Number(p.min_stock) || 5,
                        stockMin: Number(p.min_stock) || 5,
                        reorder: Number(p.reorder_point ?? ((p.min_stock || 5) * 2)),
                        ppnOut: Number(p.tax_rate) || 11,
                        ppnChk: (p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N',
                        statusBarang: p.status || 'TERSEDIA',
                        koSup: p.ko_sup || (p.supplier_code || ''),
                        act: p.act || 'Y',
                        masa: p.masa || '',
                        expiredDate: p.expired_date || '',
                        up: Number(p.markup_percent) || 0,
                        dump: p.dump || '',
                        rackLocation: p.rack_location || 'Lorong Toko',
                        branchStock: (p.branch_stock && typeof p.branch_stock === 'object') ? p.branch_stock : {},
                        allocatedBranches: Array.isArray(p.allocated_branches) ? p.allocated_branches : Object.keys(p.branch_stock || {}),
                        masterStock: Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0,
                        warehouseStock: Number(p.warehouse_stock !== undefined ? p.warehouse_stock : (p.branch_stock && p.branch_stock['GDG-001'] !== undefined ? p.branch_stock['GDG-001'] : p.stock)) || 0,
                        stock: Number(p.stock) || 0,
                        status: p.stock_status || this.calculateStatus(p.stock, p.min_stock, p.expired_date),
                        batchLot: 'LOT-' + p.sku,
                        isConsignment: Boolean(p.is_consignment),
                        supplier: p.supplier || null,
                        suppliedBy: Array.isArray(p.supplied_by) ? p.supplied_by : [],
                        // 19 Kolom Standar Master Produk Gudang
                        id_produk: p.id_produk || p.ilp_id || String(p.id),
                        kode_barcode: p.kode_barcode || p.barcode || '',
                        kode_produk: p.kode_produk || p.sku || '',
                        qty_produk: p.qty_produk !== undefined ? Number(p.qty_produk) : (Number(p.master_stock !== undefined ? p.master_stock : p.stock) || 0),
                        nama_produk: p.nama_produk || p.name || '',
                        satuan_unit: p.satuan_unit || p.unit || 'Pcs',
                        harga_beli: Number(p.harga_beli ?? p.cost_price) || 0,
                        harga_jual_umum: Number(p.harga_jual_umum ?? p.price ?? p.original_price) || 0,
                        harga_jual_member: Number(p.harga_jual_member ?? p.member_price ?? p.original_member_price ?? p.price) || 0,
                        produk_disc: Number(p.produk_disc ?? p.clearance_discount_percent ?? p.discount_percent ?? 0),
                        minimum_stock: Number(p.minimum_stock ?? p.min_stock ?? 5),
                        ppn_out: Number(p.ppn_out ?? p.tax_rate ?? 11),
                        ppn_chk: p.ppn_chk || ((p.is_taxable !== false && p.ppn_chk !== 'N') ? 'Y' : 'N'),
                        harga_beli_terakhir: Number(p.harga_beli_terakhir ?? p.last_cost_price ?? p.cost_price) || 0,
                        status_barang: p.status_barang || p.status || 'TERSEDIA',
                        kode_suplier: p.kode_suplier || p.ko_sup || (p.supplier_code || '-'),
                        manufacturing_date: p.manufacturing_date || '',
                        exp_date: p.exp_date || p.expired_date || ''
                    }));
                    const existingSkus = new Set((this.products || []).map(p => p.sku));
                    const newItems = mapped.filter(p => !existingSkus.has(p.sku));
                    if (newItems.length > 0) {
                        this.products = [...newItems, ...this.products];
                    }
                }
            }
        } catch (err) {}
    },

    currentPage: 1,
    pageSize: 15,
    get paginatedProducts() {
        const start = (this.currentPage - 1) * this.pageSize;
        return this.filteredProducts.slice(start, start + this.pageSize);
    },
    get totalPages() {
        return Math.ceil(this.filteredProducts.length / this.pageSize) || 1;
    },
    nextPage() {
        if (this.currentPage < this.totalPages) this.currentPage++;
    },
    prevPage() {
        if (this.currentPage > 1) this.currentPage--;
    },

    // Import & Export Excel Feature State
    showImportModal: false,
    importStep: 'upload', // 'upload', 'invalid_structure', 'preview', 'processing', 'result'
    importFile: null,
    importFileName: '',
    isUploading: false,
    isDragging: false,
    validationData: null,
    validationErrorDetails: null,
    duplicateAction: 'update',
    importResult: null,
    importPreviewFilter: 'all', // 'all', 'error', 'existing', 'new'
    importPreviewPage: 1,
    importPreviewPerPage: 100,
    importPreviewSearch: '',
    showExistingDetailsBox: false,

    getFilteredPreviewRows() {
        if (!this.validationData || !this.validationData.preview_rows) return [];
        let rows = this.validationData.preview_rows;
        if (this.importPreviewFilter === 'error') {
            rows = rows.filter(r => r.has_error);
        } else if (this.importPreviewFilter === 'existing') {
            rows = rows.filter(r => !r.has_error && r.is_existing);
        } else if (this.importPreviewFilter === 'new') {
            rows = rows.filter(r => !r.has_error && !r.is_existing);
        }
        if (this.importPreviewSearch && this.importPreviewSearch.trim()) {
            const q = this.importPreviewSearch.toLowerCase().trim();
            rows = rows.filter(r => 
                String(r.row).includes(q) ||
                (r.sku && String(r.sku).toLowerCase().includes(q)) ||
                (r.nama_produk && String(r.nama_produk).toLowerCase().includes(q)) ||
                (r.kode_barcode && String(r.kode_barcode).toLowerCase().includes(q)) ||
                (r.kode_produk && String(r.kode_produk).toLowerCase().includes(q))
            );
        }
        return rows;
    },

    getPaginatedPreviewRows() {
        const rows = this.getFilteredPreviewRows();
        if (this.importPreviewPerPage === 'all') return rows;
        const perPage = parseInt(this.importPreviewPerPage) || 100;
        const start = (this.importPreviewPage - 1) * perPage;
        return rows.slice(start, start + perPage);
    },

    getTotalPreviewPages() {
        const rows = this.getFilteredPreviewRows();
        if (this.importPreviewPerPage === 'all') return 1;
        const perPage = parseInt(this.importPreviewPerPage) || 100;
        return Math.max(1, Math.ceil(rows.length / perPage));
    },

    openImportModal() {
        this.showImportModal = true;
        this.importStep = 'upload';
        this.importFile = null;
        this.importFileName = '';
        this.isUploading = false;
        this.isDragging = false;
        this.validationData = null;
        this.validationErrorDetails = null;
        this.duplicateAction = 'update';
        this.importResult = null;
        this.importPreviewFilter = 'all';
        this.importPreviewPage = 1;
        this.importPreviewPerPage = 100;
        this.importPreviewSearch = '';
        this.showExistingDetailsBox = false;
        this.resetFileInput();
        this.$nextTick(() => {
            const f = document.getElementById('excelFileInput');
            if (f) f.value = '';
        });
    },

    triggerFileInput() {
        const f = document.getElementById('excelFileInput');
        if (f) {
            f.click();
        }
    },

    resetFileInput() {
        this.importFile = null;
        this.importFileName = '';
        this.isDragging = false;
        const f = document.getElementById('excelFileInput');
        if (f) f.value = '';
    },

    onFileSelected(event) {
        const file = event.target.files && event.target.files[0];
        if (file) {
            this.importFile = file;
            this.importFileName = file.name;
        }
    },

    handleFileDrop(event) {
        this.isDragging = false;
        const files = event.dataTransfer ? event.dataTransfer.files : null;
        if (files && files.length > 0) {
            this.importFile = files[0];
            this.importFileName = files[0].name;
        }
    },

    async uploadAndValidateFile() {
        if (!this.importFile) {
            this.triggerToast('Silakan pilih file Excel terlebih dahulu.', 'warning');
            return;
        }

        this.isUploading = true;
        const formData = new FormData();
        formData.append('file', this.importFile);

        try {
            const res = await fetch('/api/products/import/validate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.validationData = json.data;
                this.importStep = 'preview';
                this.importPreviewFilter = 'all';
                this.importPreviewPage = 1;
                this.importPreviewSearch = '';
                this.showExistingDetailsBox = false;
                this.triggerToast('Struktur 19 kolom Excel valid! Menampilkan pratinjau data...', 'success');
            } else {
                this.validationErrorDetails = json.details || { message: json.message || 'Format file tidak sesuai standar Master Produk.' };
                this.importStep = 'invalid_structure';
                this.triggerToast(json.message || 'Struktur file Excel tidak sesuai standar Master Produk (19 Kolom).', 'error');
            }
        } catch (e) {
            console.error('Validation error:', e);
            this.triggerToast('Gagal memvalidasi file: ' + e.message, 'error');
        } finally {
            this.isUploading = false;
        }
    },

    async confirmExecuteImport() {
        if (!this.validationData || !this.validationData.token) {
            this.triggerToast('Token data import tidak ditemukan.', 'error');
            return;
        }

        this.importStep = 'processing';

        try {
            const res = await fetch('/api/products/import/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    token: this.validationData.token,
                    duplicate_action: this.duplicateAction
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.importResult = json;
                this.importStep = 'result';
                await this.initProducts();
                this.triggerToast('Import master produk berhasil diselesaikan!', 'success');
            } else {
                this.triggerToast('Gagal memproses import: ' + (json.message || 'Terjadi kesalahan sistem.'), 'error');
                this.importStep = 'preview';
            }
        } catch (e) {
            console.error('Import execution error:', e);
            this.triggerToast('Terjadi kesalahan jaringan saat import: ' + e.message, 'error');
            this.importStep = 'preview';
        }
    },

    exportExcelData() {
        const params = new URLSearchParams();
        if (this.searchQuery && this.searchQuery.trim()) {
            params.append('search', this.searchQuery.trim());
        }
        if (this.selectedCategory && this.selectedCategory !== 'all' && this.selectedCategory !== 'Semua') {
            params.append('category', this.selectedCategory);
        }
        window.location.href = '/manager/products/export?' + params.toString();
        this.triggerToast('Mengunduh Master Produk Excel aktual dari database...', 'info');
    },

    downloadILPTemplate() {
        window.location.href = '/manager/products/template';
        this.triggerToast('Mengunduh Template Excel Master Barang (19 Kolom)...', 'info');
    },

    get totalItems() {
        return this.branchProducts.length;
    },

    get lowStockCount() {
        return this.branchProducts.filter(p => this.getProductStatus(p) === 'STOK_RENDAH').length;
    },

    get nearExpiredCount() {
        return this.branchProducts.filter(p => p.status === 'NEAR_EXPIRED').length;
    },

    get outOfStockCount() {
        return this.branchProducts.filter(p => this.getProductStatus(p) === 'HABIS').length;
    },

    get totalInventoryValue() {
        return this.branchProducts.reduce((acc, p) => acc + (this.getProductStock(p) * (Number(p.costPrice || p.price) || 0)), 0);
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
    isSavingProduct: false,
    modalMode: 'create',
    formSkuOriginal: '',
    duplicateExistingProduct: null,
    formData: {
        id: null,
        ilp_id: '',
        sku: '',
        barcode: '',
        name: '',
        brand: '',
        category: 'Minuman',
        unit: 'Pcs',
        statusBarang: 'TERSEDIA',
        act: 'Y',

        costPrice: 0,
        lastCostPrice: 0,
        ppnChk: 'Y',
        ppnOut: 11,
        up: 0,
        disc: 0,

        price: 0,
        memberPrice: 0,
        hargaLevel1: 0,
        minQty1: 1,
        minQty2: '',
        minQty3: '',

        stock: 0,
        minStock: 10,
        reorder: 20,
        rackLocation: 'Display Rak',

        koSup: '',
        batchLot: '',
        expiredDate: '',
        masa: '30',
        dump: ''
    },
    formErrors: {},

    onCostOrPriceChange() {
        const c = Number(this.formData.costPrice) || 0;
        const p = Number(this.formData.price) || 0;
        if (c > 0 && p >= c) {
            this.formData.up = Number((((p - c) / c) * 100).toFixed(1));
        }
        if (!this.formData.memberPrice || Number(this.formData.memberPrice) === 0) {
            this.formData.memberPrice = p;
        }
        if (!this.formData.hargaLevel1 || Number(this.formData.hargaLevel1) === 0) {
            this.formData.hargaLevel1 = this.formData.memberPrice || p;
        }
    },

    onMarkupChange() {
        const c = Number(this.formData.costPrice) || 0;
        const up = Number(this.formData.up) || 0;
        if (c > 0 && up > 0) {
            this.formData.price = Math.round(c * (1 + (up / 100)));
            if (!this.formData.memberPrice || Number(this.formData.memberPrice) <= c) {
                this.formData.memberPrice = this.formData.price;
            }
            if (!this.formData.hargaLevel1) {
                this.formData.hargaLevel1 = this.formData.memberPrice;
            }
        }
    },

    onPpnToggle() {
        if (this.formData.ppn_chk === 'N' || this.formData.ppnChk === 'N') {
            this.formData.ppn_out = 0;
            this.formData.ppnOut = 0;
        } else if ((this.formData.ppn_chk === 'Y' || this.formData.ppnChk === 'Y') && (!this.formData.ppn_out || Number(this.formData.ppn_out) === 0)) {
            this.formData.ppn_out = 11;
            this.formData.ppnOut = 11;
        }
    },

    onMinStockChange() {
        const ms = Number(this.formData.minimum_stock ?? this.formData.minStock) || 0;
        this.formData.minimum_stock = ms;
        this.formData.minStock = ms;
        if (!this.formData.reorder || Number(this.formData.reorder) <= ms) {
            this.formData.reorder = ms * 2;
        }
    },

    openCreateModal() {
        this.modalMode = 'create';
        this.formSkuOriginal = '';
        this.formErrors = {};
        this.duplicateExistingProduct = null;

        let maxNum = 0;
        this.products.forEach(p => {
            const m = (p.sku || p.kode_produk || '').match(/PRD-(\d+)/i);
            if (m) {
                const num = parseInt(m[1], 10);
                if (num > maxNum) maxNum = num;
            }
        });
        const nextSku = 'PRD-' + String(maxNum + 1).padStart(3, '0');
        const randomBarcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);

        const todayStr = new Date().toISOString().split('T')[0];
        const oneYearFromNow = new Date();
        oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);
        const defaultExpDate = oneYearFromNow.toISOString().split('T')[0];

        this.formData = {
            id: null,
            // 19 Kolom Standar Master Produk Gudang
            id_produk: nextSku,
            sku: nextSku,
            kode_barcode: randomBarcode,
            kode_produk: nextSku,
            qty_produk: 0,
            nama_produk: '',
            satuan_unit: 'Pcs',
            harga_beli: 0,
            harga_jual_umum: 0,
            harga_jual_member: 0,
            produk_disc: 0,
            minimum_stock: 5,
            ppn_out: 11,
            ppn_chk: 'Y',
            harga_beli_terakhir: 0,
            status_barang: 'TERSEDIA',
            kode_suplier: '',
            manufacturing_date: todayStr,
            exp_date: defaultExpDate,

            // Legacy compatibility aliases
            ilp_id: nextSku,
            barcode: randomBarcode,
            name: '',
            brand: 'Lokal Mandiri',
            category: (this.availableCategories && this.availableCategories.length > 0) ? this.availableCategories[0] : 'Kebutuhan Pokok',
            unit: 'Pcs',
            statusBarang: 'TERSEDIA',
            status: 'TERSEDIA',
            act: 'Y',
            costPrice: 0,
            lastCostPrice: 0,
            ppnChk: 'Y',
            ppnOut: 11,
            up: 0,
            disc: 0,
            price: 0,
            memberPrice: 0,
            hargaLevel1: 0,
            minQty1: 1,
            minQty2: '',
            minQty3: '',
            stock: 0,
            minStock: 5,
            reorder: 10,
            rackLocation: 'Display Rak',
            koSup: '',
            batchLot: 'BATCH-' + new Date().getFullYear() + String(new Date().getMonth() + 1).padStart(2, '0'),
            expiredDate: defaultExpDate,
            masa: '30',
            dump: ''
        };
        this.showProductModal = true;
    },

    openEditModal(product) {
        this.modalMode = 'edit';
        this.formSkuOriginal = product.sku || product.kode_produk || '';
        this.formErrors = {};
        this.duplicateExistingProduct = null;

        let expVal = '';
        const rawExp = product.exp_date || product.expiredDate || product.expired_date;
        if (rawExp) {
            if (/^\d{4}-\d{2}-\d{2}$/.test(rawExp)) {
                expVal = rawExp;
            } else {
                const months = { 'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04', 'Mei': '05', 'May': '05', 'Jun': '06', 'Jul': '07', 'Agu': '08', 'Aug': '08', 'Sep': '09', 'Okt': '10', 'Oct': '10', 'Nov': '11', 'Des': '12', 'Dec': '12' };
                const parts = String(rawExp).split(' ');
                if (parts.length === 3) {
                    const day = parts[0].padStart(2, '0');
                    const m = months[parts[1]] || '01';
                    const yr = parts[2];
                    expVal = `${yr}-${m}-${day}`;
                }
            }
        }

        let mfgVal = '';
        const rawMfg = product.manufacturing_date || product.mfgDate;
        if (rawMfg && /^\d{4}-\d{2}-\d{2}/.test(rawMfg)) {
            mfgVal = String(rawMfg).substring(0, 10);
        }

        const costP = Number(product.harga_beli ?? product.costPrice ?? product.cost_price) || 0;
        const sellP = Number(product.harga_jual_umum ?? product.price) || 0;
        const memberP = Number(product.harga_jual_member ?? product.memberPrice ?? product.hargaLevel1 ?? sellP);
        const lastCostP = Number(product.harga_beli_terakhir ?? product.lastCostPrice ?? product.last_cost_price) || costP;
        const stockQty = Number(product.qty_produk !== undefined ? product.qty_produk : (product.masterStock ?? product.stock ?? 0));
        const minStk = Number(product.minimum_stock ?? product.minStock ?? product.stockMin ?? 5);
        const discVal = Number(product.produk_disc ?? product.disc ?? product.clearance_discount_percent ?? 0);
        const ppnOutVal = Number(product.ppn_out !== null && product.ppn_out !== undefined ? product.ppn_out : (product.ppnOut ?? product.tax_rate ?? 11));
        const ppnChkVal = product.ppn_chk || product.ppnChk || (product.is_taxable !== false ? 'Y' : 'N');
        const statusVal = product.status_barang || product.statusBarang || product.status || 'TERSEDIA';
        const supplierCode = product.kode_suplier || product.koSup || product.supplier_code || product.ko_sup || '';
        const prodId = product.id_produk || product.ilp_id || (product.id ? String(product.id) : '');
        const skuVal = product.sku || product.kode_produk || '';
        const barcodeVal = product.kode_barcode || product.barcode || '';
        const kodeProdukVal = product.kode_produk || product.sku || '';
        const namaProdukVal = product.nama_produk || product.name || '';
        const unitVal = product.satuan_unit || product.unit || 'Pcs';

        let upVal = Number(product.up ?? product.markup_percent ?? 0);
        if (!upVal && costP > 0 && sellP > costP) {
            upVal = Number((((sellP - costP) / costP) * 100).toFixed(1));
        }

        this.formData = {
            id: product.id || null,
            // 19 Kolom Standar Master Produk Gudang
            id_produk: prodId,
            sku: skuVal,
            kode_barcode: barcodeVal,
            kode_produk: kodeProdukVal,
            qty_produk: stockQty,
            nama_produk: namaProdukVal,
            satuan_unit: unitVal,
            harga_beli: costP,
            harga_jual_umum: sellP,
            harga_jual_member: memberP,
            produk_disc: discVal,
            minimum_stock: minStk,
            ppn_out: ppnOutVal,
            ppn_chk: ppnChkVal,
            harga_beli_terakhir: lastCostP,
            status_barang: statusVal,
            kode_suplier: supplierCode,
            manufacturing_date: mfgVal,
            exp_date: expVal,

            // Legacy compatibility aliases
            ilp_id: prodId,
            barcode: barcodeVal,
            name: namaProdukVal,
            brand: product.brand || 'Lokal Mandiri',
            category: product.category || 'Kebutuhan Pokok',
            unit: unitVal,
            statusBarang: statusVal,
            status: statusVal,
            act: product.act || 'Y',
            costPrice: costP,
            lastCostPrice: lastCostP,
            ppnChk: ppnChkVal,
            ppnOut: ppnOutVal,
            up: upVal,
            disc: discVal,
            price: sellP,
            memberPrice: memberP,
            hargaLevel1: memberP,
            minQty1: product.minQty1 !== null && product.minQty1 !== undefined ? Number(product.minQty1) : 1,
            minQty2: product.minQty2 !== null && product.minQty2 !== undefined && product.minQty2 !== '' ? Number(product.minQty2) : '',
            minQty3: product.minQty3 !== null && product.minQty3 !== undefined && product.minQty3 !== '' ? Number(product.minQty3) : '',
            stock: stockQty,
            minStock: minStk,
            reorder: Number(product.reorder ?? (minStk * 2)),
            rackLocation: product.rackLocation || product.rack_location || 'Display Rak',
            koSup: supplierCode,
            batchLot: product.batchLot || ('LOT-' + skuVal),
            expiredDate: expVal,
            masa: product.masa || '30',
            dump: product.dump || ''
        };
        this.showProductModal = true;
    },

    generateRandomBarcode() {
        const randCode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);
        this.formData.kode_barcode = randCode;
        this.formData.barcode = randCode;
    },

    async saveProduct() {
        if (this.isSavingProduct) return;
        this.formErrors = {};
        this.duplicateExistingProduct = null;
        const f = this.formData;

        const cleanSku = (f.sku || f.kode_produk || '').trim().toUpperCase();
        const cleanBarcode = (f.kode_barcode || f.barcode || '').trim();
        const cleanName = (f.nama_produk || f.name || '').trim();

        if (!cleanName) {
            this.formErrors.name = 'Nama produk (NamaProduk) wajib diisi.';
        }

        // 1. Periksa SKU terlebih dahulu
        if (!cleanSku) {
            this.formErrors.sku = 'SKU master produk wajib diisi.';
        } else {
            const dupSku = this.products.find(p => 
                ((p.sku || p.kode_produk || '').trim().toUpperCase() === cleanSku) && 
                (this.modalMode === 'create' || (p.sku || p.kode_produk || '').trim().toUpperCase() !== (this.formSkuOriginal || '').trim().toUpperCase())
            );
            if (dupSku) {
                this.formErrors.sku = 'SKU sudah terdaftar. Silakan gunakan data produk yang sudah ada.';
                this.duplicateExistingProduct = dupSku;
            }
        }

        // 2. Periksa barcode jika diisi
        if (cleanBarcode) {
            const dupBarcode = this.products.find(p => 
                ((p.kode_barcode || p.barcode || '').trim() === cleanBarcode) && 
                (this.modalMode === 'create' || (p.sku || p.kode_produk || '').trim().toUpperCase() !== (this.formSkuOriginal || '').trim().toUpperCase())
            );
            if (dupBarcode) {
                this.formErrors.barcode = 'Barcode sudah digunakan oleh produk lain.';
                if (!this.duplicateExistingProduct) {
                    this.duplicateExistingProduct = dupBarcode;
                }
            }
        }

        const priceVal = Number(f.harga_jual_umum !== undefined ? f.harga_jual_umum : f.price);
        const memberPriceVal = Number(f.harga_jual_member !== undefined ? f.harga_jual_member : f.memberPrice);
        const costPriceVal = Number(f.harga_beli !== undefined ? f.harga_beli : f.costPrice);
        const stockVal = Number(f.qty_produk !== undefined ? f.qty_produk : f.stock);
        const minStockVal = Number(f.minimum_stock !== undefined ? f.minimum_stock : f.minStock);

        if (priceVal < 0 || isNaN(priceVal)) {
            this.formErrors.price = 'Harga jual umum harus >= 0.';
        }
        if (memberPriceVal < 0 || isNaN(memberPriceVal)) {
            this.formErrors.memberPrice = 'Harga member harus >= 0.';
        }
        if (stockVal < 0 || isNaN(stockVal)) {
            this.formErrors.stock = 'Jumlah stok (QtyProduk) tidak boleh negatif.';
        }
        if (minStockVal < 0 || isNaN(minStockVal)) {
            this.formErrors.minStock = 'Minimum stok buffer tidak boleh negatif.';
        }

        if (Object.keys(this.formErrors).length > 0) {
            return;
        }

        const calculatedStatus = f.status_barang || this.calculateStatus(stockVal, minStockVal, f.exp_date || f.expiredDate);

        let displayExpDate = f.exp_date || f.expiredDate || '-';
        if (/^\d{4}-\d{2}-\d{2}$/.test(displayExpDate)) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const [y, m, d] = displayExpDate.split('-');
            displayExpDate = `${parseInt(d, 10)} ${months[parseInt(m, 10) - 1]} ${y}`;
        }

        const payload = {
            // 19 Kolom Standar Master Produk Gudang
            id_produk: (f.id_produk || f.ilp_id || cleanSku).trim(),
            sku: cleanSku,
            kode_barcode: cleanBarcode,
            kode_produk: (f.kode_produk || cleanSku).trim().toUpperCase(),
            qty_produk: stockVal,
            nama_produk: cleanName,
            satuan_unit: (f.satuan_unit || f.unit || 'Pcs').trim(),
            harga_beli: costPriceVal,
            harga_jual_umum: priceVal,
            harga_jual_member: memberPriceVal,
            produk_disc: Number(f.produk_disc !== undefined ? f.produk_disc : f.disc) || 0,
            minimum_stock: minStockVal,
            ppn_out: (f.ppn_chk === 'Y' || f.ppnChk === 'Y') ? (Number(f.ppn_out !== undefined ? f.ppn_out : f.ppnOut) || 11) : 0,
            ppn_chk: f.ppn_chk || f.ppnChk || 'Y',
            harga_beli_terakhir: Number(f.harga_beli_terakhir !== undefined ? f.harga_beli_terakhir : f.lastCostPrice) || costPriceVal,
            status_barang: calculatedStatus,
            kode_suplier: (f.kode_suplier || f.koSup || '').trim(),
            manufacturing_date: f.manufacturing_date || null,
            exp_date: f.exp_date || f.expiredDate || null,

            // Legacy compatibility fields
            ilp_id: (f.id_produk || f.ilp_id || cleanSku).trim(),
            barcode: cleanBarcode,
            name: cleanName,
            brand: (f.brand || 'Lokal Mandiri').trim(),
            category: f.category || 'Kebutuhan Pokok',
            unit: (f.satuan_unit || f.unit || 'Pcs').trim(),
            rackLocation: (f.rackLocation || 'Display Rak').trim(),
            stock: stockVal,
            minStock: minStockVal,
            price: priceVal,
            memberPrice: memberPriceVal,
            costPrice: costPriceVal,
            lastCostPrice: Number(f.harga_beli_terakhir !== undefined ? f.harga_beli_terakhir : f.lastCostPrice) || costPriceVal,
            status: calculatedStatus,
            batchLot: f.batchLot || ('LOT-' + cleanSku),
            expiredDate: displayExpDate
        };

        const apiPayload = {
            // 19 Kolom Standar Master Produk Gudang
            id_produk: payload.id_produk,
            sku: payload.sku,
            kode_barcode: payload.kode_barcode,
            kode_produk: payload.kode_produk,
            qty_produk: payload.qty_produk,
            nama_produk: payload.nama_produk,
            satuan_unit: payload.satuan_unit,
            harga_beli: payload.harga_beli,
            harga_jual_umum: payload.harga_jual_umum,
            harga_jual_member: payload.harga_jual_member,
            produk_disc: payload.produk_disc,
            minimum_stock: payload.minimum_stock,
            ppn_out: payload.ppn_out,
            ppn_chk: payload.ppn_chk,
            harga_beli_terakhir: payload.harga_beli_terakhir,
            status_barang: payload.status_barang,
            kode_suplier: payload.kode_suplier || null,
            manufacturing_date: payload.manufacturing_date,
            exp_date: payload.exp_date,

            // Legacy attributes
            ilp_id: payload.id_produk,
            barcode: payload.kode_barcode,
            name: payload.nama_produk,
            brand: payload.brand,
            category: payload.category || 'Kebutuhan Pokok',
            unit: payload.satuan_unit || 'Pcs',
            cost_price: payload.harga_beli,
            last_cost_price: payload.harga_beli_terakhir,
            price: payload.harga_jual_umum,
            member_price: payload.harga_jual_member,
            harga_level1: payload.harga_jual_member,
            min_qty1: 1,
            min_qty2: null,
            min_qty3: null,
            stock: payload.qty_produk,
            min_stock: payload.minimum_stock,
            reorder_point: payload.minimum_stock * 2,
            markup_percent: 0,
            clearance_discount_percent: payload.produk_disc,
            is_taxable: payload.ppn_chk === 'Y',
            tax_rate: payload.ppn_out,
            ko_sup: payload.kode_suplier || null,
            act: payload.status_barang === 'NONAKTIF' ? 'N' : 'Y',
            masa: '30',
            dump: null,
            rack_location: payload.rackLocation || 'Display Rak',
            expired_date: payload.exp_date
        };

        payload.ilp_id = apiPayload.ilp_id;
        payload.costPrice = apiPayload.cost_price;
        payload.lastCostPrice = apiPayload.last_cost_price;
        payload.hargaLevel1 = apiPayload.harga_level1;
        payload.minQty1 = apiPayload.min_qty1;
        payload.minQty2 = apiPayload.min_qty2;
        payload.minQty3 = apiPayload.min_qty3;
        payload.up = apiPayload.markup_percent;
        payload.disc = apiPayload.clearance_discount_percent;
        payload.ppnChk = payload.ppn_chk;
        payload.ppnOut = apiPayload.tax_rate;
        payload.is_taxable = apiPayload.is_taxable;
        payload.koSup = apiPayload.ko_sup;
        payload.act = apiPayload.act;
        payload.masa = apiPayload.masa;
        payload.dump = apiPayload.dump;
        payload.reorder = apiPayload.reorder_point;

        this.isSavingProduct = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('prd-' + Date.now());
        try {
            if (this.modalMode === 'create') {
                const res = await fetch('/api/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Idempotency-Key': idemKey
                    },
                    body: JSON.stringify(apiPayload)
                });
                const json = await res.json().catch(() => ({}));
                if (res.ok && json.success !== false) {
                    if (json.data && json.data.id) {
                        payload.id = json.data.id;
                    }
                    payload.allocatedBranches = (json.data && Array.isArray(json.data.allocated_branches))
                        ? json.data.allocated_branches
                        : ['GDG-001'];
                    payload.branchStock = (json.data && json.data.branch_stock && typeof json.data.branch_stock === 'object')
                        ? json.data.branch_stock
                        : { 'GDG-001': Number(payload.stock) || 0 };
                    payload.masterStock = Number(payload.stock) || 0;
                    payload.warehouseStock = (json.data && json.data.warehouse_stock !== undefined) ? Number(json.data.warehouse_stock) : Number(payload.stock) || 0;
                    this.products.unshift(payload);
                    this.saveProductsToStorage();
                    this.showProductModal = false;
                    this.triggerToast(`Produk '${payload.name}' berhasil ditambahkan ke katalog stok.`, 'success');
                } else {
                    this.formErrors.general = json.message || 'Gagal menyimpan produk ke server.';
                    this.triggerToast(this.formErrors.general, 'error');
                }
            } else {
                const idx = this.products.findIndex(p => p.sku === this.formSkuOriginal);
                if (idx !== -1) {
                    const existingProduct = this.products[idx];
                    if (existingProduct.id) {
                        const res = await fetch(`/api/products/${existingProduct.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(apiPayload)
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            this.formErrors.general = json.message || 'Gagal memperbarui produk di server.';
                            this.triggerToast(this.formErrors.general, 'error');
                            return;
                        }
                    }
                    payload.id = existingProduct.id;
                    payload.allocatedBranches = existingProduct.allocatedBranches || ['GDG-001'];
                    payload.branchStock = existingProduct.branchStock || {};
                    payload.masterStock = Number(payload.stock) || 0;
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
        } catch (err) {
            console.error('API /api/products save error:', err);
            this.formErrors.general = 'Terjadi kesalahan koneksi ke server database.';
            this.triggerToast(this.formErrors.general, 'error');
        } finally {
            this.isSavingProduct = false;
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

    async confirmDelete() {
        if (!this.productToDelete) return;
        if (this.deleteConfirmInput.trim().toLowerCase() !== 'hapus') {
            return;
        }

        const name = this.productToDelete.name;
        const targetSku = this.productToDelete.sku;

        if (this.productToDelete.id) {
            try {
                const res = await fetch(`/api/products/${this.productToDelete.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                if (!res.ok) {
                    const errJson = await res.json().catch(() => ({}));
                    this.triggerToast(errJson.message || 'Gagal menghapus produk dari database.', 'error');
                    return;
                }
            } catch (err) {
                console.warn('API /api/products delete error:', err);
                this.triggerToast('Gagal menghubungi server saat menghapus produk.', 'error');
                return;
            }
        }

        const targetId = this.productToDelete.id;
        this.products = this.products.filter(p => {
            if (targetId && p.id) return p.id !== targetId;
            if (targetSku && p.sku) return p.sku !== targetSku;
            return p !== this.productToDelete;
        });
        this.saveProductsToStorage();

        if (this.selectedProduct && ((targetId && this.selectedProduct.id === targetId) || (targetSku && this.selectedProduct.sku === targetSku))) {
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
    },

    // ==========================================
    // INVENTARIS STOK BARANG RETUR (GUDANG PUSAT) - ISOLASI TOTAL DARI STOK MASTER
    // ==========================================
    inventoryTab: (new URLSearchParams(window.location.search).get('tab') === 'retur' ? 'retur' : 'normal'),
    returnStockItems: [],
    returnSearchQuery: '',
    returnStatusFilter: 'all',
    returnFollowUpFilter: 'all',
    returnBranchFilter: 'all',
    returnViewMode: (new URLSearchParams(window.location.search).get('view') === 'summary' ? 'summary' : 'batch'), // 'batch' (per dokumen penerimaan) | 'summary' (ringkasan per SKU)
    showInspectionModal: false,
    selectedReturnItem: null,
    showReturnDetailModal: false,
    selectedReturnForDetail: null,
    skuHistoryList: [],
    skuTotalQty: 0,
    openReturnItemDetail(item) {
        this.selectedReturnForDetail = item;
        this.skuHistoryList = this.returnStockItems.filter(i => i.sku === item.sku);
        this.skuTotalQty = this.skuHistoryList.reduce((acc, curr) => acc + (parseInt(curr.qtyReceived) || 0), 0);
        this.showReturnDetailModal = true;
    },
    inspectionForm: {
        status: '',
        followUp: '',
        notes: '',
        inspector: @js(auth()->user()?->name ?? 'Manager')
    },

    async initReturnStock() {
        try {
            const res = await fetch('/api/returns?direction=toko_ke_gudang', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    const items = [];
                    json.data.forEach(doc => {
                        if (doc.statusKey === 'disetujui' || doc.statusKey === 'selesai') {
                            (doc.products || []).forEach((p, idx) => {
                                const qtyNum = parseInt(p.qty, 10) || 0;
                                if (qtyNum > 0) {
                                    items.push({
                                        id: 'RET-GD-' + doc.id + '-' + idx,
                                        sku: p.sku || '-',
                                        barcode: p.barcode || '-',
                                        name: p.name,
                                        branchOrigin: doc.branchOrigin || 'Cabang',
                                        docRef: doc.docNo,
                                        receivedDate: doc.date || '-',
                                        qtyReceived: qtyNum,
                                        unit: 'Pcs',
                                        initialCondition: doc.conditionLabel || 'Barang Retur Diterima',
                                        inspectionStatus: doc.statusKey === 'selesai' ? 'QC Selesai' : 'Menunggu Pemeriksaan',
                                        followUp: 'Karantina Gudang / Klaim Supplier',
                                        inspectorNotes: doc.conditionDesc || '-',
                                        inspectedBy: @js(auth()->user()?->name ?? 'Manager'),
                                        updatedAt: doc.date || '-'
                                    });
                                }
                            });
                        }
                    });
                    this.returnStockItems = items;
                    return;
                }
            }
        } catch (e) {
            console.error('Failed to load return stock from API:', e);
        }
        this.returnStockItems = [];
    },

    saveReturnStock() {},

    getDefaultReturnStock() {
        return [];
    },

    get uniqueSkuCount() {
        const set = new Set(this.returnStockItems.map(i => i.sku).filter(Boolean));
        return set.size;
    },

    get totalReturnQty() {
        return this.returnStockItems.reduce((acc, curr) => acc + (parseInt(curr.qtyReceived) || 0), 0);
    },

    get returnPendingInspectionCount() {
        return this.returnStockItems.filter(i => (i.inspectionStatus || '').toLowerCase().includes('menunggu')).length;
    },

    get returnReadySupplierCount() {
        return this.returnStockItems.filter(i => (i.inspectionStatus || '').toLowerCase().includes('supplier')).length;
    },

    get returnDamagedCount() {
        return this.returnStockItems.filter(i => (i.inspectionStatus || '').toLowerCase().includes('rusak') || (i.inspectionStatus || '').toLowerCase().includes('afkir')).length;
    },

    get totalRejectedInDC() {
        return 0;
    },

    get filteredReturnStock() {
        return this.returnStockItems.filter(item => {
            if (this.returnFollowUpFilter && this.returnFollowUpFilter !== 'all') {
                const target = this.returnFollowUpFilter.toLowerCase();
                const itemFollowUp = (item.followUp || '').toLowerCase();
                if (!itemFollowUp.includes(target)) return false;
            }
            if (this.returnBranchFilter !== 'all') {
                if (this.returnBranchFilter === 'Cabang A' && !(item.branchOrigin || '').includes('Cabang A')) return false;
                if (this.returnBranchFilter === 'Cabang B' && !(item.branchOrigin || '').includes('Cabang B')) return false;
                if (this.returnBranchFilter === 'Pusat' && !(item.branchOrigin || '').includes('Pusat')) return false;
            }
            if (this.returnSearchQuery.trim() !== '') {
                const q = this.returnSearchQuery.toLowerCase();
                const matchSku = (item.sku || '').toLowerCase().includes(q);
                const matchBarcode = (item.barcode || '').toLowerCase().includes(q);
                const matchName = (item.name || '').toLowerCase().includes(q);
                const matchBranch = (item.branchOrigin || '').toLowerCase().includes(q);
                const matchDoc = (item.docRef || '').toLowerCase().includes(q);
                const matchFollowUp = (item.followUp || '').toLowerCase().includes(q);
                const matchCond = (item.initialCondition || '').toLowerCase().includes(q);
                return matchSku || matchBarcode || matchName || matchBranch || matchDoc || matchFollowUp || matchCond;
            }
            return true;
        });
    },

    // Pagination & formatting state for Return Stock Table
    returnPerPage: 10,
    returnCurrentPage: 1,

    formatReturnDate(dateStr) {
        if (typeof window.formatReturnDateHelper === 'function') {
            return window.formatReturnDateHelper(dateStr);
        }
        return { date: dateStr || '-', time: '-' };
    },

    get returnTotalPages() {
        const total = this.returnViewMode === 'batch' ? this.filteredReturnStock.length : this.groupedReturnStockBySku.length;
        return Math.max(1, Math.ceil(total / this.returnPerPage));
    },

    get paginatedReturnStock() {
        const start = (this.returnCurrentPage - 1) * this.returnPerPage;
        return this.filteredReturnStock.slice(start, start + this.returnPerPage);
    },

    get paginatedGroupedReturnStock() {
        const start = (this.returnCurrentPage - 1) * this.returnPerPage;
        return this.groupedReturnStockBySku.slice(start, start + this.returnPerPage);
    },

    getReturnPaginationInfo() {
        const total = this.returnViewMode === 'batch' ? this.filteredReturnStock.length : this.groupedReturnStockBySku.length;
        if (total === 0) return 'Tidak ada data barang retur';
        const start = (this.returnCurrentPage - 1) * this.returnPerPage + 1;
        const end = Math.min(this.returnCurrentPage * this.returnPerPage, total);
        const unitLabel = this.returnViewMode === 'batch' ? 'dokumen penerimaan' : 'kelompok SKU';
        return `Menampilkan ${start} - ${end} dari total ${total} ${unitLabel}`;
    },

    goToReturnPage(page) {
        if (page >= 1 && page <= this.returnTotalPages) {
            this.returnCurrentPage = page;
        }
    },

    get groupedReturnStockBySku() {
        const groups = {};
        this.filteredReturnStock.forEach(item => {
            const sku = item.sku || 'UNKNOWN';
            if (!groups[sku]) {
                groups[sku] = {
                    sku: sku,
                    barcode: item.barcode || '-',
                    name: item.name,
                    unit: 'Pcs',
                    totalQty: 0,
                    batches: [],
                    branches: new Set(),
                    conditions: new Set(),
                    statuses: new Set(),
                    lastReceivedDate: item.receivedDate || '-',
                    followUp: item.followUp || 'Klaim Retur Supplier',
                    representativeItem: item
                };
            }
            groups[sku].totalQty += (parseInt(item.qtyReceived) || 0);
            groups[sku].batches.push(item);
            if (item.branchOrigin) groups[sku].branches.add(item.branchOrigin);
            if (item.initialCondition) groups[sku].conditions.add(item.initialCondition);
            if (item.inspectionStatus) groups[sku].statuses.add(item.inspectionStatus);
            if (item.receivedDate) groups[sku].lastReceivedDate = item.receivedDate;
            if (item.followUp) groups[sku].followUp = item.followUp;
        });
        return Object.values(groups).map(g => ({
            ...g,
            branchesList: Array.from(g.branches).join(', '),
            conditionsList: Array.from(g.conditions).join(' • '),
            statusesList: Array.from(g.statuses)
        }));
    },

    openInspectionModal(item) {
        this.selectedReturnItem = item;
        this.inspectionForm = {
            status: item.inspectionStatus || 'Menunggu Pemeriksaan',
            followUp: item.followUp || '',
            notes: item.inspectorNotes || '',
            inspector: item.inspectedBy || @js(auth()->user()?->name ?? 'Manager')
        };
        this.showInspectionModal = true;
    },

    saveInspectionStatus() {
        if (!this.selectedReturnItem) return;
        const oldStatus = this.selectedReturnItem.inspectionStatus;
        this.selectedReturnItem.inspectionStatus = this.inspectionForm.status;
        this.selectedReturnItem.followUp = this.inspectionForm.followUp;
        this.selectedReturnItem.inspectorNotes = this.inspectionForm.notes;
        this.selectedReturnItem.inspectedBy = this.inspectionForm.inspector;
        this.selectedReturnItem.updatedAt = new Date().toLocaleString('id-ID');
        
        this.saveReturnStock();
        this.logReturnStockAudit(this.selectedReturnItem, oldStatus, this.inspectionForm.status);
        this.showInspectionModal = false;
        this.triggerToast(`Status pemeriksaan ${this.selectedReturnItem.sku} berhasil diperbarui menjadi '${this.inspectionForm.status}'.`);
    },

    logReturnStockAudit(item, oldStatus, newStatus) {
        // Audit log dicatat oleh backend saat operasi API
    }
}"
x-init="initOpening(); initProducts(); initReturnStock();"
@pos-inventory-synced.window="initProducts()"
@pos-return-stock-synced.window="initReturnStock()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Inventaris Produk</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">inventory_2</span>
                    <span>Kelola Master Produk</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button @click="window.PosRealtime?.syncNow?.(); initProducts()" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">

            <!-- Tab Switcher: Master Normal vs Inventaris Stok Barang Retur (Gudang Pusat) -->
            <div class="bg-white p-2.5 rounded-2xl border border-gray-200/80 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- Tab 1 Button -->
                    <button type="button"
                            @click="inventoryTab = 'normal'; currentPage = 1;"
                            :class="inventoryTab === 'normal' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">inventory_2</span>
                        <span>Master Produk &amp; Stok</span>
                        <span :class="inventoryTab === 'normal' ? 'bg-white text-[#0052cc]' : 'bg-slate-200 text-slate-700'"
                              class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                              x-text="totalItems + ' Produk'"></span>
                    </button>

                    <!-- Tab 2 Button -->
                    <button type="button"
                            @click="inventoryTab = 'retur'"
                            :class="inventoryTab === 'retur' ? 'bg-amber-600 text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">assignment_return</span>
                        <span>Inventaris Stok Barang Retur</span>
                        <span :class="inventoryTab === 'retur' ? 'bg-white text-amber-700' : 'bg-amber-100 text-amber-800'"
                              class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                              x-text="uniqueSkuCount + ' SKU Retur'"></span>
                    </button>
                </div>

                <!-- Right Action / Shortcut Button -->
                <div class="flex items-center gap-2.5">
                    <a href="{{ url('/manager/retur-masuk') }}"
                       x-show="inventoryTab === 'retur'"
                       style="display: none;"
                       class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-xl text-[12px] font-extrabold flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">archive</span>
                        <span>Verifikasi Barang Masuk Retur &rarr;</span>
                    </a>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 1: MASTER PRODUK & KATALOG TOKO        -->
            <!-- ========================================== -->
            <div x-show="inventoryTab === 'normal'" class="space-y-6">

                <!-- Quick Branch Selector Tabs (Produk Master (Stok Gudang), Cabang Pusat, Cabang A, Cabang B) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                    <div class="px-5 pt-3.5 border-b border-gray-100 flex items-center gap-2 overflow-x-auto bg-slate-50/70">
                        <button type="button" 
                                @click="selectedBranch = 'all'; currentPage = 1;"
                                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                                :class="selectedBranch === 'all' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                            <span class="material-symbols-rounded text-[16px]">warehouse</span>
                            <span>Produk Master (Stok Gudang)</span>
                        </button>

                        <button type="button" 
                                @click="selectedBranch = 'CAB-001'; currentPage = 1;"
                                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                                :class="selectedBranch === 'CAB-001' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                            <span class="material-symbols-rounded text-[16px]">storefront</span>
                            <span>Cabang Pusat</span>
                        </button>

                        <button type="button" 
                                @click="selectedBranch = 'CAB-002'; currentPage = 1;"
                                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                                :class="selectedBranch === 'CAB-002' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                            <span class="material-symbols-rounded text-[16px]">storefront</span>
                            <span>Cabang A</span>
                        </button>

                        <button type="button" 
                                @click="selectedBranch = 'CAB-003'; currentPage = 1;"
                                class="px-4 py-2.5 text-xs font-bold transition-all border-b-2 inline-flex items-center gap-2 whitespace-nowrap cursor-pointer"
                                :class="selectedBranch === 'CAB-003' ? 'border-[#0052cc] text-[#0052cc] bg-white rounded-t-xl shadow-2xs' : 'border-transparent text-slate-600 hover:text-slate-900'">
                            <span class="material-symbols-rounded text-[16px]">storefront</span>
                            <span>Cabang B</span>
                        </button>
                    </div>

                    <!-- Context Info Callout -->
                    <div class="px-5 py-2.5 bg-blue-50/50 border-b border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-600">
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-rounded text-[16px] text-[#0052cc]">info</span>
                            <span x-show="selectedBranch === 'all'">
                                <strong>Inventaris Gudang Pusat (Stok Master):</strong> Menampilkan saldo fisik stok barang yang tersedia di Gudang Pusat (DC). Ketika barang dikirim ke cabang via Kirim Barang, stok gudang ini otomatis berkurang.
                            </span>
                            <span x-show="selectedBranch === 'CAB-001'">
                                <strong>Inventaris Cabang Pusat:</strong> Menampilkan saldo stok fisik dan status barang khusus gerai ritel Cabang Pusat (Toko Pusat).
                            </span>
                            <span x-show="selectedBranch === 'CAB-002'">
                                <strong>Inventaris Cabang A:</strong> Menampilkan saldo stok fisik dan status barang khusus gerai ritel Cabang A.
                            </span>
                            <span x-show="selectedBranch === 'CAB-003'">
                                <strong>Inventaris Cabang B:</strong> Menampilkan saldo stok fisik dan status barang khusus gerai ritel Cabang B.
                            </span>
                        </div>
                        <span class="font-bold text-[#0052cc] shrink-0" x-text="'Tampilan Aktif: ' + getBranchName(selectedBranch)"></span>
                    </div>
                </div>

                <!-- Filter, Search Bar & Import/Export Actions Toolbar -->
                <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-gray-200 shadow-xs">
                    <div class="flex flex-col sm:flex-row items-center gap-3 flex-1">
                        <!-- Search Input -->
                        <div class="relative flex-1 w-full">
                            <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text"
                                   x-model="searchQuery"
                                   @input="onSearchInput()"
                                   placeholder="Cari nama produk, barcode, SKU, ID, atau kategori..."
                                   class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                        </div>

                        <!-- Category Select -->
                        <div class="relative w-full sm:w-56 shrink-0">
                            <select x-model="selectedCategory"
                                    @change="currentPage = 1"
                                    class="appearance-none w-full bg-slate-50/50 border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs transition-all font-semibold cursor-pointer">
                                <option value="all">Semua Kategori</option>
                                <template x-for="cat in availableCategories" :key="cat">
                                    <option :value="cat" x-text="cat"></option>
                                </template>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>

                    <!-- Action Buttons: Import Excel, Export Excel, Tambah Produk -->
                    <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                        <!-- Tombol Import Excel -->
                        <button type="button"
                                @click.stop="openImportModal()"
                                class="h-10 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                                title="Import data master produk massal dari file Excel (19 Kolom, Dukung Ratusan Ribu Data)">
                            <span class="material-symbols-rounded text-[18px]">upload_file</span>
                            <span>Import Excel</span>
                        </button>

                        <!-- Tombol Export Excel -->
                        <button type="button"
                                @click="exportExcelData()"
                                class="h-10 px-4 bg-white hover:bg-slate-50 text-slate-700 border border-gray-300 rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer"
                                title="Export seluruh master produk database ke file Excel (19 Kolom Master)">
                            <span class="material-symbols-rounded text-[18px]">download</span>
                            <span>Export Excel</span>
                        </button>

                        <!-- Tombol Tambah Produk Manual -->
                        <button type="button"
                                @click="openCreateModal()"
                                class="h-10 px-4 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">add</span>
                            <span>Tambah Produk</span>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL SKU (<span x-text="selectedBranch === 'all' ? 'STOK GUDANG' : getBranchName(selectedBranch)"></span>)</span>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-3xl font-black text-gray-800 tracking-tight" x-text="totalItems">0</span>
                            <span class="bg-[#dbeafe] text-[#0052cc] text-[11px] font-bold px-2.5 py-1 rounded-md" x-text="totalItems + ' Aktif'"></span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex flex-col justify-between relative overflow-hidden border-l-4 border-l-amber-500">
                        <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">STOK RENDAH (<span x-text="selectedBranch === 'all' ? 'GUDANG' : getBranchName(selectedBranch)"></span>)</span>
                        <div class="flex items-center justify-between mt-3 relative z-10">
                            <span class="text-3xl font-black text-amber-500 tracking-tight" x-text="lowStockCount">0</span>
                            <span class="material-symbols-rounded text-amber-500 text-[26px]">warning</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-red-200 p-5 shadow-xs flex flex-col justify-between border-l-4 border-l-[#e11d48] relative overflow-hidden">
                        <span class="text-[11px] font-bold text-[#e11d48] tracking-wider uppercase">MENDEKATI EXPIRED</span>
                        <div class="flex items-center justify-between mt-3 relative z-10">
                            <span class="text-3xl font-black text-[#e11d48] tracking-tight" x-text="nearExpiredCount">0</span>
                            <span class="material-symbols-rounded text-[#e11d48] text-[26px]">event_busy</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">NILAI INVENTARIS (<span x-text="selectedBranch === 'all' ? 'GUDANG' : getBranchName(selectedBranch)"></span>)</span>
                        <div class="flex items-center justify-between mt-3">
                            <span class="text-[22px] font-black text-gray-800 tracking-tight font-mono" x-text="formatRupiah(totalInventoryValue)">Rp 0</span>
                            <span class="text-[12px] font-bold text-gray-400">IDR</span>
                        </div>
                    </div>
                </div>

                <!-- Master Products Data Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/50">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory</span>
                            <h2 class="text-sm font-bold text-gray-900">Tabel Master Data Barang</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-[#0052cc]" x-text="getBranchName(selectedBranch)"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Toggle Button: Mode Standar vs Format 19 Kolom Master Produk -->
                            <button type="button" 
                                    @click="tableMode = (tableMode === 'standard' ? 'ilp_25' : 'standard')"
                                    class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    :class="tableMode === 'ilp_25' ? 'bg-[#0052cc] text-white border-[#0052cc] shadow-2xs' : 'bg-white text-slate-700 border-gray-300 hover:bg-slate-50'">
                                <span class="material-symbols-rounded text-[16px]">view_column</span>
                                <span x-text="tableMode === 'ilp_25' ? 'Format 19 Kolom Master (Aktif)' : 'Tampilkan 19 Kolom Master'"></span>
                            </button>
                            <span class="text-xs text-gray-500 font-medium whitespace-nowrap" x-text="'Menampilkan ' + paginatedProducts.length + ' dari ' + filteredProducts.length + ' produk'"></span>
                        </div>
                    </div>

                    <!-- MODE 1: FORMAT 19 KOLOM STANDAR MASTER PRODUK -->
                    <template x-if="tableMode === 'ilp_25'">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-[12px] whitespace-nowrap" style="min-width: 2200px;">
                                <thead>
                                    <tr class="bg-gray-100/80 border-b border-gray-200 text-gray-700 font-bold tracking-wider text-[11px] uppercase">
                                        <th class="px-3 py-3.5 text-center w-12 sticky left-0 bg-gray-100 z-10 shadow-r">NO</th>
                                        <th class="px-4 py-3.5 text-left font-mono text-[#0052cc]">1. IdProduk</th>
                                        <th class="px-4 py-3.5 text-left font-mono">2. SKU</th>
                                        <th class="px-4 py-3.5 text-left font-mono">3. Kode Barcode</th>
                                        <th class="px-4 py-3.5 text-left font-mono">4. KodeProduk</th>
                                        <th class="px-3 py-3.5 text-center font-mono">5. QtyProduk</th>
                                        <th class="px-5 py-3.5 text-left">6. NamaProduk</th>
                                        <th class="px-3 py-3.5 text-center">7. SatuanUnit</th>
                                        <th class="px-4 py-3.5 text-right font-mono">8. HargaBeli</th>
                                        <th class="px-4 py-3.5 text-right font-mono text-emerald-700">9. HargaJualUmum</th>
                                        <th class="px-4 py-3.5 text-right font-mono text-[#0052cc]">10. HargaJualMember</th>
                                        <th class="px-3 py-3.5 text-center font-mono">11. ProdukDisc</th>
                                        <th class="px-3 py-3.5 text-center font-mono text-amber-700">12. MinimumStock</th>
                                        <th class="px-3 py-3.5 text-center font-mono">13. PPNOut</th>
                                        <th class="px-3 py-3.5 text-center font-mono">14. PPNChk</th>
                                        <th class="px-4 py-3.5 text-right font-mono">15. HargaBeliTerakhir</th>
                                        <th class="px-3 py-3.5 text-center">16. StatusBarang</th>
                                        <th class="px-3 py-3.5 text-center font-mono">17. KodeSuplier</th>
                                        <th class="px-3 py-3.5 text-center font-mono">18. ManufacturingDate</th>
                                        <th class="px-3 py-3.5 text-center font-mono">19. ExpDate</th>
                                        <th class="px-4 py-3.5 text-center font-mono bg-blue-50/70 text-[#0052cc]">
                                            <span x-text="selectedBranch === 'all' ? 'STOK GUDANG' : ('STOK ' + getBranchName(selectedBranch).toUpperCase())"></span>
                                        </th>
                                        <th class="px-4 py-3.5 text-center sticky right-0 bg-gray-100 z-10 shadow-l">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                    <template x-for="(p, idx) in paginatedProducts" :key="p.sku">
                                        <tr class="hover:bg-blue-50/40 transition-colors">
                                            <td class="px-3 py-3 text-center text-gray-400 font-mono sticky left-0 bg-white" x-text="((currentPage - 1) * pageSize) + idx + 1"></td>
                                            <td class="px-4 py-3 font-mono font-bold text-[#0052cc]" x-text="p.id_produk || p.ilp_id || p.id"></td>
                                            <td class="px-4 py-3 font-mono font-bold text-gray-900" x-text="p.sku"></td>
                                            <td class="px-4 py-3 font-mono text-gray-700" x-text="p.kode_barcode || p.barcode || '-'"></td>
                                            <td class="px-4 py-3 font-mono text-gray-700" x-text="p.kode_produk || p.sku || '-'"></td>
                                            <td class="px-3 py-3 text-center font-mono font-bold" x-text="p.qty_produk !== undefined ? p.qty_produk : (p.masterStock ?? p.stock ?? 0)"></td>
                                            <td class="px-5 py-3 font-bold text-gray-900 max-w-[220px] truncate" :title="p.nama_produk || p.name" x-text="p.nama_produk || p.name"></td>
                                            <td class="px-3 py-3 text-center text-gray-800 font-bold" x-text="p.satuan_unit || p.unit || 'Pcs'"></td>
                                            <td class="px-4 py-3 text-right font-mono" x-text="formatRupiah(p.harga_beli ?? p.costPrice)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700" x-text="formatRupiah(p.harga_jual_umum ?? p.price)"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-[#0052cc]" x-text="formatRupiah(p.harga_jual_member ?? p.memberPrice ?? p.price)"></td>
                                            <td class="px-3 py-3 text-center font-mono" x-text="(p.produk_disc ?? p.disc ?? 0) + '%'"></td>
                                            <td class="px-3 py-3 text-center font-mono font-bold text-amber-700" x-text="p.minimum_stock ?? p.minStock ?? p.stockMin ?? 5"></td>
                                            <td class="px-3 py-3 text-center font-mono" x-text="(p.ppn_out ?? p.ppnOut ?? 11) + '%'"></td>
                                            <td class="px-3 py-3 text-center font-mono font-bold" :class="(p.ppn_chk || p.ppnChk) === 'Y' ? 'text-emerald-700' : 'text-slate-500'" x-text="p.ppn_chk || p.ppnChk || 'Y'"></td>
                                            <td class="px-4 py-3 text-right font-mono text-slate-800" x-text="formatRupiah(p.harga_beli_terakhir ?? p.lastCostPrice ?? p.costPrice)"></td>
                                            <td class="px-3 py-3 text-center font-bold" x-text="p.status_barang || p.statusBarang || p.status || 'TERSEDIA'"></td>
                                            <td class="px-3 py-3 text-center font-mono" x-text="p.kode_suplier || p.koSup || '-'"></td>
                                            <td class="px-3 py-3 text-center font-mono" x-text="p.manufacturing_date || p.mfgDate || '-'"></td>
                                            <td class="px-3 py-3 text-center font-mono" x-text="p.exp_date || p.expiredDate || '-'"></td>
                                            <td class="px-4 py-3 text-center font-mono font-black bg-blue-50/40 text-[#0052cc]" x-text="getProductStock(p) + ' ' + (p.satuan_unit || p.unit || 'Pcs')"></td>
                                            <td class="px-4 py-3 text-center sticky right-0 bg-white shadow-l">
                                                <div class="inline-flex items-center justify-center gap-1">
                                                    <button type="button" @click="openDetail(p)" title="Lihat Tabel Detail Lengkap 19 Kolom" class="p-1 text-gray-400 hover:text-[#0052cc] rounded cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                                    </button>
                                                    <button type="button" @click="openEditModal(p)" title="Edit Produk" class="p-1 text-gray-400 hover:text-amber-600 rounded cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                                    </button>
                                                    <button type="button" @click="openDeleteModal(p)" title="Hapus Produk" class="p-1 text-gray-400 hover:text-rose-600 rounded cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="filteredProducts.length === 0">
                                        <tr>
                                            <td colspan="27" class="px-6 py-12 text-center bg-white">
                                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-3">
                                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400">
                                                        <span class="material-symbols-rounded text-[28px]" x-text="selectedBranch === 'all' ? 'search_off' : 'inventory_2'"></span>
                                                    </div>
                                                    <h4 class="text-sm font-bold text-gray-800" x-text="selectedBranch === 'all' ? 'Tidak Ada Produk Ditemukan' : ('Belum Ada Produk di ' + getBranchName(selectedBranch))"></h4>
                                                    <p class="text-xs text-gray-500" x-text="selectedBranch === 'all' ? 'Tidak ada produk yang sesuai dengan filter pencarian.' : 'Belum ada produk yang didistribusikan ke cabang ini. Produk baru di Gudang Pusat dapat didistribusikan melalui menu Kirim Barang.'"></p>
                                                    <template x-if="selectedBranch !== 'all'">
                                                        <a href="/manager/kirim-barang" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs">
                                                            <span class="material-symbols-rounded text-[16px]">local_shipping</span>
                                                            <span>Buka Kirim Barang</span>
                                                        </a>
                                                    </template>
                                                    <template x-if="selectedBranch === 'all'">
                                                        <button type="button"
                                                                @click="searchQuery = ''; selectedCategory = 'all'; currentPage = 1;"
                                                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-all cursor-pointer">
                                                            Reset Filter
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- MODE 2: STANDAR CARD TABLE (Default Clean View) -->
                    <template x-if="tableMode === 'standard'">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[1080px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                        <th class="px-6 py-3.5 text-left w-[260px]">PRODUK &amp; ID</th>
                                        <th class="px-4 py-3.5 text-left w-[170px]">BARCODE &amp; KODE</th>
                                        <th class="px-4 py-3.5 text-left w-[130px]">KATEGORI &amp; RAK</th>
                                        <th class="px-4 py-3.5 text-center w-[130px]">
                                            <span x-text="selectedBranch === 'all' ? 'STOK GUDANG' : ('STOK ' + getBranchName(selectedBranch).toUpperCase())"></span>
                                        </th>
                                        <th class="px-4 py-3.5 text-right w-[150px]">HARGA JUAL</th>
                                        <th class="px-4 py-3.5 text-center w-[120px]">STATUS</th>
                                        <th class="px-6 py-3.5 text-center w-[110px]">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[13px] divide-y divide-gray-100 font-medium text-slate-700">
                                    <template x-for="p in paginatedProducts" :key="p.sku">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <!-- 1. Nama, Brand & ID -->
                                            <td class="px-6 py-3.5">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-gray-900 text-[13.5px]" x-text="p.name"></span>
                                                    <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-400">
                                                        <span class="font-mono text-[#0052cc] font-bold" x-text="'ID: ' + (p.ilp_id || p.id)"></span>
                                                        <span>&bull;</span>
                                                        <span x-text="p.brand || 'Lokal'"></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- 2. Barcode & SKU -->
                                            <td class="px-4 py-3.5 font-mono text-[12px] whitespace-nowrap">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-gray-800" x-text="p.barcode || '-'"></span>
                                                    <span class="text-[10px] text-gray-400" x-text="'Kode: ' + p.sku"></span>
                                                </div>
                                            </td>

                                            <!-- 3. Kategori & Rak -->
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <div class="flex flex-col">
                                                    <span class="px-2 py-0.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold w-fit" x-text="p.category || 'Umum'"></span>
                                                    <span class="text-[10.5px] text-gray-400 mt-0.5" x-text="p.rackLocation || 'Lorong Toko'"></span>
                                                </div>
                                            </td>

                                            <!-- 4. Stok (Branch-Aware) -->
                                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                                <div class="flex flex-col items-center">
                                                    <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-bold font-mono"
                                                          :class="getProductStock(p) <= 0 ? 'bg-rose-50 text-rose-600 border border-rose-200' : (getProductStock(p) <= (p.minStock || 5) ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200')"
                                                          x-text="getProductStock(p) + ' ' + (p.unit || 'Pcs')"></span>
                                                    <template x-if="selectedBranch !== 'all'">
                                                        <span class="text-[10px] text-gray-400 font-mono mt-0.5" x-text="'Gudang: ' + getProductStock(p, 'GDG-001')"></span>
                                                    </template>
                                                </div>
                                            </td>

                                            <!-- 5. Harga -->
                                            <td class="px-4 py-3.5 text-right font-mono whitespace-nowrap">
                                                <div class="flex flex-col items-end">
                                                    <span class="font-bold text-gray-900 text-[13px]" x-text="formatRupiah(p.price)"></span>
                                                    <template x-if="p.hargaLevel1 || p.memberPrice">
                                                        <span class="text-[10.5px] text-[#0052cc] font-semibold" x-text="'Lvl 1: ' + formatRupiah(p.hargaLevel1 || p.memberPrice)"></span>
                                                    </template>
                                                    <template x-if="(p.lastCostPrice ?? p.costPrice ?? 0) > 0">
                                                        <span class="text-[10px] text-slate-400 font-medium" x-text="'Beli: ' + formatRupiah(p.lastCostPrice ?? p.costPrice)"></span>
                                                    </template>
                                                </div>
                                            </td>

                                            <!-- 6. Status -->
                                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                                <span class="px-2.5 py-1 rounded-full text-[10.5px] font-bold border"
                                                      :class="getProductStatus(p) === 'HABIS' ? 'bg-rose-50 text-rose-700 border-rose-200' : (getProductStatus(p) === 'STOK_RENDAH' ? 'bg-amber-50 text-amber-700 border-amber-200' : (getProductStatus(p) === 'NONAKTIF' ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'))"
                                                      x-text="getProductStatus(p)">
                                                </span>
                                            </td>

                                            <!-- 7. Aksi -->
                                            <td class="px-6 py-3.5 text-center whitespace-nowrap">
                                                <div class="inline-flex items-center justify-center gap-1.5">
                                                    <button type="button"
                                                            @click="openDetail(p)"
                                                            title="Lihat Tabel Detail Lengkap 19 Kolom"
                                                            class="p-1.5 text-gray-400 hover:text-[#0052cc] hover:bg-blue-50 rounded-lg transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">visibility</span>
                                                    </button>
                                                    <button type="button"
                                                            @click="openEditModal(p)"
                                                            title="Edit Data Produk"
                                                            class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">edit</span>
                                                    </button>
                                                    <button type="button"
                                                            @click="openDeleteModal(p)"
                                                            title="Hapus Produk"
                                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-if="filteredProducts.length === 0">
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center">
                                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-3">
                                                    <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400">
                                                        <span class="material-symbols-rounded text-[28px]" x-text="selectedBranch === 'all' ? 'search_off' : 'inventory_2'"></span>
                                                    </div>
                                                    <h4 class="text-sm font-bold text-gray-800" x-text="selectedBranch === 'all' ? 'Tidak Ada Produk Ditemukan' : ('Belum Ada Produk di ' + getBranchName(selectedBranch))"></h4>
                                                    <p class="text-xs text-gray-500" x-text="selectedBranch === 'all' ? 'Tidak ada produk yang sesuai dengan filter pencarian.' : 'Belum ada produk yang didistribusikan ke cabang ini. Produk baru di Gudang Pusat dapat didistribusikan melalui menu Kirim Barang.'"></p>
                                                    <template x-if="selectedBranch !== 'all'">
                                                        <a href="/manager/kirim-barang" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs">
                                                            <span class="material-symbols-rounded text-[16px]">local_shipping</span>
                                                            <span>Buka Kirim Barang</span>
                                                        </a>
                                                    </template>
                                                    <template x-if="selectedBranch === 'all'">
                                                        <button type="button"
                                                                @click="searchQuery = ''; selectedCategory = 'all'; currentPage = 1;"
                                                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-all cursor-pointer">
                                                            Reset Filter
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <!-- Pagination Footer -->
                    <div class="border-t border-gray-200 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white text-xs text-gray-500">
                        <span x-text="'Halaman ' + currentPage + ' dari ' + totalPages + ' (' + filteredProducts.length + ' total produk)'"></span>
                        <div class="flex items-center gap-1.5">
                            <button type="button"
                                    @click="prevPage()"
                                    :disabled="currentPage <= 1"
                                    :class="currentPage <= 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 cursor-pointer'"
                                    class="px-3 py-1.5 rounded-lg border border-gray-200 font-bold text-gray-700 flex items-center gap-1 transition-all">
                                <span class="material-symbols-rounded text-[16px]">chevron_left</span>
                                <span>Sebelumnya</span>
                            </button>
                            <span class="px-3 py-1.5 rounded-lg bg-blue-50 text-[#0052cc] font-black font-mono" x-text="currentPage">1</span>
                            <button type="button"
                                    @click="nextPage()"
                                    :disabled="currentPage >= totalPages"
                                    :class="currentPage >= totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100 cursor-pointer'"
                                    class="px-3 py-1.5 rounded-lg border border-gray-200 font-bold text-gray-700 flex items-center gap-1 transition-all">
                                <span>Selanjutnya</span>
                                <span class="material-symbols-rounded text-[16px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            <!-- END TAB 1 -->

            <!-- ========================================== -->
            <!-- TAB 2: MONITORING STOK BARANG RETUR (GUDANG PUSAT) -->
            <!-- ========================================== -->
            <div x-show="inventoryTab === 'retur'" style="display: none;" class="space-y-6">

                <!-- Alert Notice Zona Karantina -->
                <div class="bg-amber-50/90 border border-amber-200/90 rounded-2xl p-4.5 flex items-start gap-3.5 shadow-2xs">
                    <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 mt-0.5">
                        <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-extrabold text-amber-900 flex items-center gap-2">
                                <span>INVENTARIS STOK BARANG RETUR — GUDANG PUSAT</span>
                                <span class="bg-amber-200/80 text-amber-900 text-[10.5px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">Karantina Logistik</span>
                            </h3>
                            <span class="text-[11px] font-bold text-amber-800 bg-amber-100/80 px-2.5 py-0.5 rounded-lg border border-amber-200 font-mono">
                                Saldo: pos_ilp_stok_retur_gudang_v1
                            </span>
                        </div>
                        <p class="text-[12px] text-amber-800 font-medium mt-1 leading-relaxed">
                            Tabel ini mencatat saldo inventaris fisik barang retur yang telah diverifikasi dan diterima Manager di Gudang Pusat. Stok Barang Retur <strong>TIDAK</strong> menambah saldo Stok Master toko, tidak menambah stok siap jual POS, dan tidak menggabungkan fisik karantina dengan barang display toko.
                        </p>
                        <div class="mt-2 pt-2 border-t border-amber-200/60 flex items-center justify-between text-[11px] text-amber-900">
                            <span>Ingin memverifikasi kiriman fisik barang retur baru dari Cabang?</span>
                            <a href="{{ url('/manager/retur-masuk') }}" class="font-bold underline hover:text-amber-950 flex items-center gap-1 cursor-pointer">
                                <span>Buka Verifikasi Barang Masuk Retur</span>
                                <span class="material-symbols-rounded text-[14px]">open_in_new</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards Stok Retur -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Retur Fisik -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                        <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL UNIT STOK RETUR</span>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-black text-gray-800 tracking-tight" x-text="totalReturnQty">0</span>
                                <span class="text-xs font-bold text-gray-500 font-mono">Pcs</span>
                            </div>
                            <span class="bg-amber-100 text-amber-800 text-[11px] font-bold px-2.5 py-1 rounded-md" x-text="uniqueSkuCount + ' SKU Terdata'"></span>
                        </div>
                    </div>

                    <!-- Total SKU Produk Retur -->
                    <div class="bg-white rounded-2xl border border-blue-200 p-5 shadow-xs flex flex-col justify-between border-l-4 border-l-[#0052cc]">
                        <span class="text-[11px] font-bold text-[#0052cc] tracking-wider uppercase">TOTAL SKU PRODUK RETUR</span>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-black text-[#0052cc] tracking-tight" x-text="uniqueSkuCount">0</span>
                                <span class="text-xs font-bold text-blue-600 font-mono">SKU</span>
                            </div>
                            <span class="material-symbols-rounded text-[#0052cc] text-[26px]">category</span>
                        </div>
                    </div>

                    <!-- Barang Rusak / Afkir -->
                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex flex-col justify-between border-l-4 border-l-rose-500">
                        <span class="text-[11px] font-bold text-rose-700 tracking-wider uppercase">BARANG RUSAK / AFKIR</span>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-black text-rose-600 tracking-tight" x-text="returnDamagedCount">0</span>
                                <span class="text-xs font-bold text-rose-700 font-mono">Batch</span>
                            </div>
                            <span class="material-symbols-rounded text-rose-500 text-[26px]">broken_image</span>
                        </div>
                    </div>

                    <!-- Siap Retur Supplier -->
                    <div class="bg-white rounded-2xl border border-purple-200 p-5 shadow-xs flex flex-col justify-between border-l-4 border-l-purple-600">
                        <span class="text-[11px] font-bold text-purple-700 tracking-wider uppercase">SIAP RETUR KE SUPPLIER</span>
                        <div class="flex items-center justify-between mt-3">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-black text-purple-600 tracking-tight" x-text="returnReadySupplierCount">0</span>
                                <span class="text-xs font-bold text-purple-700 font-mono">Batch</span>
                            </div>
                            <span class="material-symbols-rounded text-purple-500 text-[26px]">assignment_return</span>
                        </div>
                    </div>
                </div>

                <!-- Banner Pemantauan Barang Ditolak Menunggu Pengembalian -->
                <template x-if="totalRejectedInDC > 0">
                    <div class="bg-rose-50/90 border border-rose-200 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-2xs">
                        <div class="flex items-start md:items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 font-black">
                                <span class="material-symbols-rounded text-[22px]">reply_all</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-black text-rose-900 flex items-center gap-2">
                                    <span>PEMANTAUAN BARANG DITOLAK DI GUDANG:</span>
                                    <span class="bg-rose-200 text-rose-900 px-2 py-0.5 rounded font-mono font-black" x-text="totalRejectedInDC + ' Pcs'"></span>
                                    <span>MENUNGGU PENGEMBALIAN KE CABANG</span>
                                </h4>
                                <p class="text-[11.5px] text-rose-700 font-medium mt-0.5">
                                    Barang ditolak ini secara fisik masih berada di Gudang Pusat tetapi <strong>TIDAK</strong> dimasukkan ke dalam Saldo Stok Retur maupun Stok Master toko.
                                </p>
                            </div>
                        </div>
                        <a href="{{ url('/manager/retur-masuk') }}"
                           class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shrink-0 flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">local_shipping</span>
                            <span>Proses Pengembalian ke Toko &rarr;</span>
                        </a>
                    </div>
                </template>

                <!-- Filter & Search Toolbar Retur -->
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
                    <!-- Search Input (Elemen Utama) -->
                    <div class="relative flex-1">
                        <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="returnSearchQuery"
                               @input="returnCurrentPage = 1"
                               placeholder="Cari SKU, nama produk, cabang, no. dokumen..."
                               class="w-full h-10 pl-10 pr-4 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] transition-all font-medium bg-white placeholder:text-slate-400">
                    </div>

                    <!-- Filter & Control Group -->
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
                        <!-- Filter Tindak Lanjut -->
                        <div class="relative w-full sm:w-48">
                            <select x-model="returnFollowUpFilter"
                                    @change="returnCurrentPage = 1"
                                    class="appearance-none w-full h-10 bg-white border border-gray-200 rounded-xl px-3.5 pr-9 text-[12.5px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 transition-all font-medium cursor-pointer">
                                <option value="all">Semua Tindak Lanjut</option>
                                <option value="Supplier">Retur ke Supplier</option>
                                <option value="Distributor">Garansi Distributor</option>
                                <option value="Afkir">Pemusnahan / Afkir</option>
                                <option value="Clearance">Clearance / Diskon</option>
                                <option value="Promo">Promo / Konsinyasi</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Filter Asal Toko -->
                        <div class="relative w-full sm:w-40">
                            <select x-model="returnBranchFilter"
                                    @change="returnCurrentPage = 1"
                                    class="appearance-none w-full h-10 bg-white border border-gray-200 rounded-xl px-3.5 pr-9 text-[12.5px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 transition-all font-medium cursor-pointer">
                                <option value="all">Semua Cabang</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                                <option value="Pusat">Pusat</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Switcher Mode View -->
                        <div class="inline-flex h-10 items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
                            <button type="button"
                                    @click="returnViewMode = 'batch'; returnCurrentPage = 1;"
                                    :class="returnViewMode === 'batch' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                    class="h-full px-3 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    title="Tampilkan per bukti penerimaan fisik">
                                <span class="material-symbols-rounded text-[15px]">receipt_long</span>
                                <span>Per Dokumen</span>
                            </button>
                            <button type="button"
                                    @click="returnViewMode = 'summary'; returnCurrentPage = 1;"
                                    :class="returnViewMode === 'summary' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                                    class="h-full px-3 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    title="Tampilkan ringkasan saldo fisik per SKU">
                                <span class="material-symbols-rounded text-[15px]">category</span>
                                <span>Ringkasan SKU</span>
                            </button>
                        </div>

                        <!-- Tombol Verifikasi Penerimaan Baru -->
                        <a href="{{ url('/manager/retur-masuk') }}"
                           class="h-10 px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-[12.5px] flex items-center justify-center gap-1.5 transition-colors shrink-0 shadow-2xs cursor-pointer"
                           title="Verifikasi fisik barang masuk dari cabang">
                            <span class="material-symbols-rounded text-[18px]">archive</span>
                            <span class="hidden sm:inline">Penerimaan Baru</span>
                        </a>
                    </div>
                </div>

                <!-- Table Stok Barang Retur (Mode Batch: Per Dokumen Penerimaan) -->
                <div x-show="returnViewMode === 'batch'" class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc]">receipt_long</span>
                            <h2 class="text-sm font-bold text-gray-900">Tabel Inventaris Stok Barang Retur (Per Dokumen Penerimaan)</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + paginatedReturnStock.length + ' dari ' + filteredReturnStock.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse min-w-[1300px] text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-3.5 py-3 text-left w-[95px] min-w-[95px] whitespace-nowrap">SKU</th>
                                    <th class="px-3.5 py-3 text-left w-[115px] min-w-[115px] whitespace-nowrap">Barcode</th>
                                    <th class="px-3.5 py-3 text-left min-w-[190px] max-w-[250px] whitespace-nowrap">Nama Produk</th>
                                    <th class="px-3.5 py-3 text-left w-[130px] min-w-[130px] whitespace-nowrap">Asal Toko</th>
                                    <th class="px-3.5 py-3 text-left w-[160px] min-w-[160px] whitespace-nowrap">No. Dokumen</th>
                                    <th class="px-3.5 py-3 text-left w-[110px] min-w-[110px] whitespace-nowrap">Tanggal Masuk</th>
                                    <th class="px-3.5 py-3 text-left w-[85px] min-w-[85px] whitespace-nowrap">Waktu</th>
                                    <th class="px-3.5 py-3 text-center w-[105px] min-w-[105px] whitespace-nowrap">Qty Tersimpan</th>
                                    <th class="px-3.5 py-3 text-left w-[165px] min-w-[165px] whitespace-nowrap">Kondisi Fisik</th>
                                    <th class="px-3.5 py-3 text-left w-[165px] min-w-[165px] whitespace-nowrap">Tindak Lanjut</th>
                                    <th class="px-3.5 py-3 text-center w-[95px] min-w-[95px] whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                <template x-for="item in paginatedReturnStock" :key="item.id">
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <!-- 1. SKU -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono font-medium text-slate-800 text-[12px] whitespace-nowrap block" x-text="item.sku"></span>
                                        </td>

                                        <!-- 2. Barcode -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono text-slate-500 text-[11.5px] whitespace-nowrap block" x-text="item.barcode || '-'"></span>
                                        </td>

                                        <!-- 3. Nama Produk -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[250px]">
                                            <span class="font-medium text-slate-900 text-[12.5px] truncate block whitespace-nowrap"
                                                  :title="item.name"
                                                  x-text="item.name"></span>
                                        </td>

                                        <!-- 4. Asal Toko -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[130px]">
                                            <span class="text-slate-700 text-[12px] font-medium truncate block whitespace-nowrap"
                                                  :title="item.branchOrigin"
                                                  x-text="item.branchOrigin"></span>
                                        </td>

                                        <!-- 5. No. Dokumen -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[160px]">
                                            <span class="font-mono text-slate-600 text-[11.5px] font-medium truncate block whitespace-nowrap"
                                                  :title="item.docRef"
                                                  x-text="item.docRef"></span>
                                        </td>

                                        <!-- 6. Tanggal Masuk -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="text-slate-700 text-[12px] font-medium block whitespace-nowrap"
                                                  x-text="formatReturnDate(item.receivedDate).date"></span>
                                        </td>

                                        <!-- 7. Waktu -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono text-slate-500 text-[11px] block whitespace-nowrap"
                                                  x-text="formatReturnDate(item.receivedDate).time || '-'"></span>
                                        </td>

                                        <!-- 8. Qty Tersimpan -->
                                        <td class="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-md text-[11.5px] font-bold font-mono bg-slate-100 text-slate-800 border border-slate-200/80 whitespace-nowrap"
                                                  x-text="item.qtyReceived + ' Pcs'"></span>
                                        </td>

                                        <!-- 9. Kondisi Fisik -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[165px]">
                                            <span class="text-slate-600 text-[11.5px] truncate block whitespace-nowrap"
                                                  :title="item.initialCondition"
                                                  x-text="item.initialCondition || '-'"></span>
                                        </td>

                                        <!-- 10. Tindak Lanjut -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[165px]">
                                            <span class="text-slate-700 text-[11.5px] font-medium truncate block whitespace-nowrap"
                                                  :title="item.followUp || '-'"
                                                  x-text="item.followUp || '-'"></span>
                                        </td>

                                        <!-- 11. Aksi -->
                                        <td class="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                                            <!-- Jika status Menunggu QC / Verifikasi: JANGAN ADA AKSI APA-APA -->
                                            <template x-if="(item.inspectionStatus || '').toLowerCase().includes('menunggu')">
                                                <span class="inline-flex items-center gap-1 text-[10.5px] font-semibold text-slate-400 bg-slate-50 border border-slate-200/80 px-2 py-0.5 rounded-md cursor-not-allowed select-none whitespace-nowrap"
                                                      title="Menunggu persetujuan verifikasi di halaman Barang Masuk Retur. Tidak ada aksi di halaman ini.">
                                                    <span class="material-symbols-rounded text-[13px]">lock</span>
                                                    <span>Tanpa Aksi</span>
                                                </span>
                                            </template>

                                            <!-- Jika SUDAH DISETUJUI / DITERIMA: Tampilkan Tombol Detail -->
                                            <template x-if="!(item.inspectionStatus || '').toLowerCase().includes('menunggu')">
                                                <button type="button"
                                                        @click="openReturnItemDetail(item)"
                                                        class="px-2.5 py-1 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-lg font-bold text-[11px] inline-flex items-center justify-center gap-1 transition-colors cursor-pointer shadow-2xs whitespace-nowrap"
                                                        title="Lihat Detail & Histori Penerimaan">
                                                    <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                    <span>Detail</span>
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Empty State -->
                                <tr x-show="filteredReturnStock.length === 0">
                                    <td colspan="11" class="px-6 py-14 text-center">
                                        <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-3">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                                <span class="material-symbols-rounded text-[26px]">inventory_2</span>
                                            </div>
                                            <h4 class="text-sm font-bold text-slate-800">Tidak Ada Barang Retur Ditemukan</h4>
                                            <p class="text-xs text-slate-500">Tidak ada data stok retur yang sesuai dengan pencarian atau filter yang dipilih.</p>
                                            <button type="button"
                                                    @click="returnSearchQuery = ''; returnFollowUpFilter = 'all'; returnBranchFilter = 'all'; returnCurrentPage = 1;"
                                                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer">
                                                Reset Filter Pencarian
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer & Pagination -->
                    <div class="border-t border-gray-200 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between bg-white text-xs text-slate-500 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[16px] text-slate-400">info</span>
                            <span x-text="getReturnPaginationInfo()"></span>
                        </div>

                        <div class="flex items-center gap-1" x-show="returnTotalPages > 1">
                            <button type="button"
                                    @click="goToReturnPage(returnCurrentPage - 1)"
                                    :disabled="returnCurrentPage <= 1"
                                    class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs flex items-center gap-1 cursor-pointer transition-colors">
                                <span class="material-symbols-rounded text-[14px]">chevron_left</span>
                                <span>Sebelumnya</span>
                            </button>

                            <div class="flex items-center gap-1 mx-1">
                                <template x-for="p in returnTotalPages" :key="p">
                                    <button type="button"
                                            @click="goToReturnPage(p)"
                                            :class="p === returnCurrentPage ? 'bg-[#0052cc] text-white font-bold' : 'text-slate-700 hover:bg-slate-100 font-medium'"
                                            class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors cursor-pointer"
                                            x-text="p"></button>
                                </template>
                            </div>

                            <button type="button"
                                    @click="goToReturnPage(returnCurrentPage + 1)"
                                    :disabled="returnCurrentPage >= returnTotalPages"
                                    class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs flex items-center gap-1 cursor-pointer transition-colors">
                                <span>Berikutnya</span>
                                <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Stok Barang Retur (Mode Summary: Ringkasan Saldo per SKU) -->
                <div x-show="returnViewMode === 'summary'" style="display: none;" class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc]">category</span>
                            <h2 class="text-sm font-bold text-gray-900">Ringkasan Saldo Inventaris Stok Retur (Per SKU Produk)</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + paginatedGroupedReturnStock.length + ' dari ' + groupedReturnStockBySku.length + ' SKU'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse min-w-[1300px] text-xs">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-3.5 py-3 text-left w-[95px] min-w-[95px] whitespace-nowrap">SKU</th>
                                    <th class="px-3.5 py-3 text-left w-[115px] min-w-[115px] whitespace-nowrap">Barcode</th>
                                    <th class="px-3.5 py-3 text-left min-w-[190px] max-w-[250px] whitespace-nowrap">Nama Produk</th>
                                    <th class="px-3.5 py-3 text-left w-[130px] min-w-[130px] whitespace-nowrap">Asal Toko</th>
                                    <th class="px-3.5 py-3 text-left w-[160px] min-w-[160px] whitespace-nowrap">No. Dokumen</th>
                                    <th class="px-3.5 py-3 text-left w-[110px] min-w-[110px] whitespace-nowrap">Tanggal Masuk</th>
                                    <th class="px-3.5 py-3 text-left w-[85px] min-w-[85px] whitespace-nowrap">Waktu</th>
                                    <th class="px-3.5 py-3 text-center w-[105px] min-w-[105px] whitespace-nowrap">Qty Tersimpan</th>
                                    <th class="px-3.5 py-3 text-left w-[165px] min-w-[165px] whitespace-nowrap">Kondisi Fisik</th>
                                    <th class="px-3.5 py-3 text-left w-[165px] min-w-[165px] whitespace-nowrap">Tindak Lanjut</th>
                                    <th class="px-3.5 py-3 text-center w-[95px] min-w-[95px] whitespace-nowrap">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                <template x-for="gItem in paginatedGroupedReturnStock" :key="gItem.sku">
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <!-- 1. SKU -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono font-medium text-[#0052cc] text-[12px] whitespace-nowrap block" x-text="gItem.sku"></span>
                                        </td>

                                        <!-- 2. Barcode -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono text-slate-500 text-[11.5px] whitespace-nowrap block" x-text="gItem.barcode || '-'"></span>
                                        </td>

                                        <!-- 3. Nama Produk -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[250px]">
                                            <span class="font-medium text-slate-900 text-[12.5px] truncate block whitespace-nowrap"
                                                  :title="gItem.name"
                                                  x-text="gItem.name"></span>
                                        </td>

                                        <!-- 4. Asal Toko -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[130px]">
                                            <span class="text-slate-700 text-[12px] font-medium truncate block whitespace-nowrap"
                                                  :title="gItem.branchesList"
                                                  x-text="gItem.branchesList || '-'"></span>
                                        </td>

                                        <!-- 5. No. Dokumen -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[160px]">
                                            <span class="font-mono text-slate-600 text-[11.5px] font-medium truncate block whitespace-nowrap"
                                                  :title="gItem.batches.map(b => b.docRef).join(', ')"
                                                  x-text="gItem.batches.length > 1 ? (gItem.batches[0].docRef + ' (+' + (gItem.batches.length - 1) + ')') : (gItem.batches[0]?.docRef || '-')"></span>
                                        </td>

                                        <!-- 6. Tanggal Masuk (Terakhir) -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="text-slate-700 text-[12px] font-medium block whitespace-nowrap"
                                                  x-text="formatReturnDate(gItem.lastReceivedDate).date"></span>
                                        </td>

                                        <!-- 7. Waktu -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle">
                                            <span class="font-mono text-slate-500 text-[11px] block whitespace-nowrap"
                                                  x-text="formatReturnDate(gItem.lastReceivedDate).time || '-'"></span>
                                        </td>

                                        <!-- 8. Qty Tersimpan (Total Saldo) -->
                                        <td class="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-md text-[11.5px] font-bold font-mono bg-blue-50 text-[#0052cc] border border-blue-200 whitespace-nowrap"
                                                  x-text="gItem.totalQty + ' Pcs'"></span>
                                        </td>

                                        <!-- 9. Kondisi Fisik -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[165px]">
                                            <span class="text-slate-600 text-[11.5px] truncate block whitespace-nowrap"
                                                  :title="gItem.conditionsList"
                                                  x-text="gItem.conditionsList || '-'"></span>
                                        </td>

                                        <!-- 10. Tindak Lanjut -->
                                        <td class="px-3.5 py-3 text-left whitespace-nowrap align-middle max-w-[165px]">
                                            <span class="text-slate-700 text-[11.5px] font-medium truncate block whitespace-nowrap"
                                                  :title="gItem.followUp || '-'"
                                                  x-text="gItem.followUp || '-'"></span>
                                        </td>

                                        <!-- 11. Aksi -->
                                        <td class="px-3.5 py-3 text-center whitespace-nowrap align-middle">
                                            <!-- Jika status Menunggu QC: TANPA AKSI APA-APA -->
                                            <template x-if="gItem.statusesList.every(s => s.toLowerCase().includes('menunggu'))">
                                                <span class="inline-flex items-center gap-1 text-[10.5px] font-semibold text-slate-400 bg-slate-50 border border-slate-200/80 px-2 py-0.5 rounded-md cursor-not-allowed select-none whitespace-nowrap"
                                                      title="Menunggu persetujuan verifikasi di halaman Barang Masuk Retur. Tidak ada aksi di halaman ini.">
                                                    <span class="material-symbols-rounded text-[13px]">lock</span>
                                                    <span>Tanpa Aksi</span>
                                                </span>
                                            </template>
                                            <!-- Jika SUDAH DISETUJUI / DITERIMA: Tampilkan Tombol Detail -->
                                            <template x-if="!gItem.statusesList.every(s => s.toLowerCase().includes('menunggu'))">
                                                <button type="button"
                                                        @click="openReturnItemDetail(gItem.representativeItem)"
                                                        class="px-2.5 py-1 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-lg font-bold text-[11px] inline-flex items-center justify-center gap-1 transition-colors cursor-pointer shadow-2xs whitespace-nowrap"
                                                        title="Lihat Rincian Histori Semua Dokumen SKU Ini">
                                                    <span class="material-symbols-rounded text-[14px]">visibility</span>
                                                    <span>Detail</span>
                                                </button>
                                            </template>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Empty State -->
                                <tr x-show="groupedReturnStockBySku.length === 0">
                                    <td colspan="11" class="px-6 py-14 text-center">
                                        <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-3">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400">
                                                <span class="material-symbols-rounded text-[26px]">category</span>
                                            </div>
                                            <h4 class="text-sm font-bold text-slate-800">Tidak Ada Kelompok SKU Ditemukan</h4>
                                            <p class="text-xs text-slate-500">Sesuaikan filter pencarian untuk melihat data.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer & Pagination -->
                    <div class="border-t border-gray-200 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between bg-white text-xs text-slate-500 gap-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[16px] text-slate-400">info</span>
                            <span x-text="getReturnPaginationInfo()"></span>
                        </div>

                        <div class="flex items-center gap-1" x-show="returnTotalPages > 1">
                            <button type="button"
                                    @click="goToReturnPage(returnCurrentPage - 1)"
                                    :disabled="returnCurrentPage <= 1"
                                    class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs flex items-center gap-1 cursor-pointer transition-colors">
                                <span class="material-symbols-rounded text-[14px]">chevron_left</span>
                                <span>Sebelumnya</span>
                            </button>

                            <div class="flex items-center gap-1 mx-1">
                                <template x-for="p in returnTotalPages" :key="p">
                                    <button type="button"
                                            @click="goToReturnPage(p)"
                                            :class="p === returnCurrentPage ? 'bg-[#0052cc] text-white font-bold' : 'text-slate-700 hover:bg-slate-100 font-medium'"
                                            class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors cursor-pointer"
                                            x-text="p"></button>
                                </template>
                            </div>

                            <button type="button"
                                    @click="goToReturnPage(returnCurrentPage + 1)"
                                    :disabled="returnCurrentPage >= returnTotalPages"
                                    class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-medium text-xs flex items-center gap-1 cursor-pointer transition-colors">
                                <span>Berikutnya</span>
                                <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
            <!-- END TAB 2 -->

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
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[92vh] overflow-hidden border border-gray-200 flex flex-col">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]" x-text="modalMode === 'create' ? 'add_circle' : 'edit_document'"></span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="modalMode === 'create' ? 'Tambah Master Produk Baru (Standar 19 Kolom Master)' : 'Edit Informasi & Stok Master Produk (Standar 19 Kolom Master)'"></h3>
                            <p class="text-xs text-gray-500 font-medium" x-text="modalMode === 'create' ? 'Daftarkan master produk baru sesuai struktur 19 kolom standar master gudang.' : 'Perbarui data barang, harga beli/jual, PPN, dan stok sesuai 19 kolom standar master.'"></p>
                        </div>
                    </div>
                    <button @click="showProductModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <!-- Body (Scrollable Form) -->
                <div class="p-6 space-y-5 overflow-y-auto text-xs flex-1">
                    <template x-if="formErrors.general">
                        <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 flex items-center gap-2 font-medium">
                            <span class="material-symbols-rounded text-[18px]">error</span>
                            <span x-text="formErrors.general"></span>
                        </div>
                    </template>

                    <!-- CARD 1: IDENTITAS & KODE BARANG (Kolom 1 - 7) -->
                    <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/70 pb-2">
                            <span class="text-[11px] font-black uppercase text-slate-800 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-[#0052cc]">badge</span>
                                <span>1. Identitas &amp; Kode Master Barang</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-slate-400">Kolom 1 - 7</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <!-- Kolom 6: NamaProduk -->
                            <div class="md:col-span-3">
                                <label class="block font-bold text-gray-700 mb-1">Nama Produk (NamaProduk) <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formData.nama_produk"
                                       @input="formData.name = formData.nama_produk"
                                       placeholder="Contoh: Aqua Mineral 600ml Botol"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs bg-white">
                                <template x-if="formErrors.name">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.name"></p>
                                </template>
                            </div>

                            <!-- Kolom 7: SatuanUnit -->
                            <div class="md:col-span-1">
                                <label class="block font-bold text-gray-700 mb-1">Satuan Unit (SatuanUnit) <span class="text-rose-500">*</span></label>
                                <select x-model="formData.satuan_unit"
                                        @change="formData.unit = formData.satuan_unit"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold text-xs bg-white">
                                    <option value="Pcs">Pcs</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Pack">Pack</option>
                                    <option value="Karton">Karton</option>
                                    <option value="Kaleng">Kaleng</option>
                                    <option value="Sachet">Sachet</option>
                                    <option value="Dus">Dus</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Gram">Gram</option>
                                    <option value="Liter">Liter</option>
                                    <option value="Box">Box</option>
                                    <option value="Bungkus">Bungkus</option>
                                </select>
                            </div>

                            <!-- Kolom 1: IdProduk -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">ID Produk (IdProduk)</label>
                                <input type="text"
                                       x-model="formData.id_produk"
                                       @input="formData.ilp_id = formData.id_produk"
                                       placeholder="Auto / ID Master"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono text-xs bg-white">
                            </div>

                            <!-- Kolom 2: SKU -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">SKU Master (SKU) <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formData.sku"
                                       @input="if(!formData.kode_produk || formData.kode_produk === formData.sku) formData.kode_produk = formData.sku"
                                       placeholder="PRD-001"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs uppercase bg-white">
                                <template x-if="formErrors.sku">
                                    <p class="text-rose-500 text-[11px] font-semibold mt-1" x-text="formErrors.sku"></p>
                                </template>
                            </div>

                            <!-- Kolom 3: Kode Barcode -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kode Barcode (EAN-13) <span class="text-rose-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                           x-model="formData.kode_barcode"
                                           @input="formData.barcode = formData.kode_barcode"
                                           placeholder="899..."
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                    <button type="button"
                                            @click="generateRandomBarcode()"
                                            class="px-2.5 py-2 bg-slate-200 hover:bg-slate-300 text-gray-700 rounded-xl font-bold text-[11px] shrink-0 cursor-pointer">
                                        Acak
                                    </button>
                                </div>
                                <template x-if="formErrors.barcode">
                                    <p class="text-rose-500 text-[11px] font-semibold mt-1" x-text="formErrors.barcode"></p>
                                </template>
                            </div>

                            <!-- Kolom 4: KodeProduk -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kode Produk (KodeProduk)</label>
                                <input type="text"
                                       x-model="formData.kode_produk"
                                       placeholder="PRD-001"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs uppercase bg-white">
                            </div>

                            <!-- Kolom 5: QtyProduk -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Stok Master Gudang (QtyProduk) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <input type="number"
                                           min="0"
                                           x-model.number="formData.qty_produk"
                                           @input="formData.stock = formData.qty_produk"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold" x-text="formData.satuan_unit || 'Pcs'"></span>
                                </div>
                                <template x-if="formErrors.stock">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.stock"></p>
                                </template>
                            </div>

                            <!-- Kategori Produk (Pelengkap Master) -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Kategori Produk (Kelompok)</label>
                                <select x-model="formData.category"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold text-xs bg-white">
                                    <template x-for="cat in availableCategories" :key="cat">
                                        <option :value="cat" x-text="cat"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: PENETAPAN HARGA, DISKON & PPN (Kolom 8 - 15) -->
                    <div class="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/70 space-y-3">
                        <div class="flex items-center justify-between border-b border-amber-200/60 pb-2">
                            <span class="text-[11px] font-black uppercase text-amber-900 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-amber-700">payments</span>
                                <span>2. Penetapan Harga, Diskon &amp; PPN</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-amber-700/70">Kolom 8 - 15</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <!-- Kolom 8: HargaBeli -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Harga Beli Pokok / HPP (HargaBeli) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.harga_beli"
                                           @input="formData.costPrice = formData.harga_beli; if(!formData.harga_beli_terakhir) formData.harga_beli_terakhir = formData.harga_beli"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                </div>
                            </div>

                            <!-- Kolom 15: HargaBeliTerakhir -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Harga Beli Terakhir (HargaBeliTerakhir)</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.harga_beli_terakhir"
                                           @input="formData.lastCostPrice = formData.harga_beli_terakhir"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                </div>
                            </div>

                            <!-- Kolom 9: HargaJualUmum -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-gray-700 mb-1">Harga Jual Konsumen (HargaJualUmum) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.harga_jual_umum"
                                           @input="formData.price = formData.harga_jual_umum; if(!formData.harga_jual_member) formData.harga_jual_member = formData.harga_jual_umum"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white text-emerald-800">
                                </div>
                                <template x-if="formErrors.price">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.price"></p>
                                </template>
                            </div>

                            <!-- Kolom 10: HargaJualMember -->
                            <div class="md:col-span-2">
                                <label class="block font-bold text-[#0052cc] mb-1">Harga Member Koperasi (HargaJualMember) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#0052cc] font-bold font-mono">Rp</span>
                                    <input type="number"
                                           min="0"
                                           step="100"
                                           x-model.number="formData.harga_jual_member"
                                           @input="formData.memberPrice = formData.harga_jual_member; formData.hargaLevel1 = formData.harga_jual_member"
                                           class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white text-[#0052cc]">
                                </div>
                                <template x-if="formErrors.memberPrice">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.memberPrice"></p>
                                </template>
                            </div>

                            <!-- Kolom 11: ProdukDisc -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Diskon Produk (ProdukDisc %)</label>
                                <div class="relative">
                                    <input type="number"
                                           min="0"
                                           max="100"
                                           step="0.5"
                                           x-model.number="formData.produk_disc"
                                           @input="formData.disc = formData.produk_disc"
                                           placeholder="0"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">%</span>
                                </div>
                            </div>

                            <!-- Kolom 12: MinimumStock -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Batas Minimum (MinimumStock) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <input type="number"
                                           min="0"
                                           x-model.number="formData.minimum_stock"
                                           @input="formData.minStock = formData.minimum_stock; onMinStockChange()"
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold" x-text="formData.satuan_unit || 'Pcs'"></span>
                                </div>
                                <template x-if="formErrors.minStock">
                                    <p class="text-rose-500 text-[11px] mt-1 font-semibold" x-text="formErrors.minStock"></p>
                                </template>
                            </div>

                            <!-- Kolom 14: PPNChk -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Status Pajak (PPNChk)</label>
                                <select x-model="formData.ppn_chk"
                                        @change="formData.ppnChk = formData.ppn_chk; onPpnToggle()"
                                        class="w-full px-3 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-bold text-xs bg-white">
                                    <option value="Y">Y - Kena PPN</option>
                                    <option value="N">N - Non PPN</option>
                                </select>
                            </div>

                            <!-- Kolom 13: PPNOut -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tarif PPN (PPNOut %)</label>
                                <div class="relative">
                                    <input type="number"
                                           min="0"
                                           max="100"
                                           step="0.5"
                                           x-model.number="formData.ppn_out"
                                           @input="formData.ppnOut = formData.ppn_out"
                                           class="w-full px-3 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs bg-white">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3: STATUS, PEMASOK & MASA BERLAKU (Kolom 16 - 19) -->
                    <div class="p-4 bg-purple-50/40 rounded-2xl border border-purple-200/70 space-y-3">
                        <div class="flex items-center justify-between border-b border-purple-200/60 pb-2">
                            <span class="text-[11px] font-black uppercase text-purple-900 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[17px] text-purple-700">inventory</span>
                                <span>3. Status, Pemasok &amp; Masa Berlaku</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-purple-700/70">Kolom 16 - 19</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <!-- Kolom 16: StatusBarang -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Status Barang (StatusBarang) <span class="text-rose-500">*</span></label>
                                <select x-model="formData.status_barang"
                                        @change="formData.statusBarang = formData.status_barang; formData.status = formData.status_barang"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-bold text-xs bg-white">
                                    <option value="TERSEDIA">TERSEDIA</option>
                                    <option value="STOK_RENDAH">STOK_RENDAH</option>
                                    <option value="HABIS">HABIS</option>
                                    <option value="NONAKTIF">NONAKTIF</option>
                                </select>
                            </div>

                            <!-- Kolom 17: KodeSuplier -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kode Pemasok (KodeSuplier)</label>
                                <input type="text"
                                       x-model="formData.kode_suplier"
                                       @input="formData.koSup = formData.kode_suplier"
                                       placeholder="SUP-001 / UNILEVER"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono text-xs bg-white">
                            </div>

                            <!-- Kolom 18: ManufacturingDate -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tgl Produksi (ManufacturingDate)</label>
                                <input type="date"
                                       x-model="formData.manufacturing_date"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs bg-white">
                            </div>

                            <!-- Kolom 19: ExpDate -->
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Tgl Kedaluwarsa (ExpDate)</label>
                                <input type="date"
                                       x-model="formData.exp_date"
                                       @input="formData.expiredDate = formData.exp_date"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium text-xs bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            :disabled="isSavingProduct"
                            @click="showProductModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        Batal
                    </button>

                    <button type="button"
                            :disabled="isSavingProduct"
                            @click="saveProduct()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="material-symbols-rounded text-[18px]" :class="isSavingProduct ? 'animate-spin' : ''" x-text="isSavingProduct ? 'progress_activity' : 'save'"></span>
                        <span x-text="isSavingProduct ? 'Menyimpan...' : (modalMode === 'create' ? 'Simpan Produk Baru' : 'Perbarui Data Produk')"></span>
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
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold shrink-0">
                            <span class="material-symbols-rounded text-[22px]">inventory_2</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="selectedProduct?.name"></h3>
                            <span class="font-mono text-xs text-gray-500" x-text="'ID: ' + (selectedProduct?.ilp_id || selectedProduct?.id) + ' • Barcode: ' + (selectedProduct?.barcode || '-') + ' • Kode: ' + selectedProduct?.sku"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto flex-1">
                    <!-- 1. SEBARAN STOK MULTI-CABANG (Produk Master, Cabang Pusat, Cabang A, Cabang B) -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-gray-800 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">hub</span>
                                <span>Sebaran Stok Multi-Cabang</span>
                            </span>
                            <span class="text-[11px] font-mono font-bold text-[#0052cc]" x-text="'Total Master: ' + (selectedProduct?.masterStock ?? selectedProduct?.stock ?? 0) + ' ' + (selectedProduct?.unit || 'Pcs')"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                            <!-- Gudang Pusat (Stok Master) -->
                            <div class="p-2.5 rounded-lg bg-blue-50/70 border border-blue-200">
                                <span class="text-[10px] font-bold text-[#0052cc] uppercase block">Gudang Pusat</span>
                                <div class="text-base font-black text-[#0052cc] font-mono mt-0.5" x-text="getProductStock(selectedProduct, 'GDG-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-gray-400">Stok Gudang (DC)</span>
                            </div>
                            <!-- Cabang Pusat -->
                            <div class="p-2.5 rounded-lg bg-white border border-gray-200">
                                <span class="text-[10px] font-bold text-gray-700 uppercase block">Cabang Pusat</span>
                                <div class="text-base font-black text-gray-900 font-mono mt-0.5" x-text="getProductStock(selectedProduct, 'CAB-001') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-gray-400">Toko Pusat</span>
                            </div>
                            <!-- Cabang A -->
                            <div class="p-2.5 rounded-lg bg-white border border-gray-200">
                                <span class="text-[10px] font-bold text-gray-700 uppercase block">Cabang A</span>
                                <div class="text-base font-black text-gray-900 font-mono mt-0.5" x-text="getProductStock(selectedProduct, 'CAB-002') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-gray-400">Unit Ritel A</span>
                            </div>
                            <!-- Cabang B -->
                            <div class="p-2.5 rounded-lg bg-white border border-gray-200">
                                <span class="text-[10px] font-bold text-gray-700 uppercase block">Cabang B</span>
                                <div class="text-base font-black text-gray-900 font-mono mt-0.5" x-text="getProductStock(selectedProduct, 'CAB-003') + ' ' + (selectedProduct?.unit || 'Pcs')"></div>
                                <span class="text-[9.5px] text-gray-400">Unit Ritel B</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. TABEL LENGKAP DETAIL BARANG (STANDAR 19 KOLOM MASTER PRODUK) -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-gray-700 tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-emerald-600">table_rows</span>
                                <span>Tabel Detail Barang (Struktur 19 Kolom Master Produk)</span>
                            </span>
                            <span class="text-[10.5px] font-mono text-gray-400">19 Kolom Terstandarisasi</span>
                        </div>
                        <div class="max-h-72 overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 border-b border-gray-200 text-[11px]">
                                    <tr>
                                        <th class="py-2 px-3 w-12 text-center">No</th>
                                        <th class="py-2 px-4 w-52">Nama Kolom (Field)</th>
                                        <th class="py-2 px-4">Nilai Data Produk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-slate-800">
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">1</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">IdProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.id_produk || selectedProduct?.ilp_id || selectedProduct?.id || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">2</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">SKU</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.sku || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">3</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">Kode Barcode</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.kode_barcode || selectedProduct?.barcode || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">4</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">KodeProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="selectedProduct?.kode_produk || selectedProduct?.sku || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">5</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">QtyProduk</td><td class="py-2 px-4 font-mono font-bold text-slate-900" x-text="(selectedProduct?.qty_produk !== undefined ? selectedProduct.qty_produk : (selectedProduct?.masterStock ?? selectedProduct?.stock ?? 0)) + ' ' + (selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs')"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">6</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">NamaProduk</td><td class="py-2 px-4 font-bold text-slate-900" x-text="selectedProduct?.nama_produk || selectedProduct?.name || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">7</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">SatuanUnit</td><td class="py-2 px-4 font-bold text-slate-800" x-text="selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">8</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaBeli</td><td class="py-2 px-4 font-mono font-bold text-slate-800" x-text="formatRupiah(selectedProduct?.harga_beli ?? selectedProduct?.costPrice)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">9</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaJualUmum</td><td class="py-2 px-4 font-mono font-bold text-emerald-700" x-text="formatRupiah(selectedProduct?.harga_jual_umum ?? selectedProduct?.price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">10</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaJualMember</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]" x-text="formatRupiah(selectedProduct?.harga_jual_member ?? selectedProduct?.memberPrice ?? selectedProduct?.price)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">11</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ProdukDisc</td><td class="py-2 px-4 font-mono" x-text="(selectedProduct?.produk_disc ?? selectedProduct?.disc ?? 0) + '%'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">12</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">MinimumStock</td><td class="py-2 px-4 font-mono font-bold text-amber-700" x-text="(selectedProduct?.minimum_stock ?? selectedProduct?.minStock ?? selectedProduct?.stockMin ?? 5) + ' ' + (selectedProduct?.satuan_unit || selectedProduct?.unit || 'Pcs')"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">13</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">PPNOut</td><td class="py-2 px-4 font-mono" x-text="(selectedProduct?.ppn_out ?? selectedProduct?.ppnOut ?? 11) + '%'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">14</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">PPNChk</td><td class="py-2 px-4 font-mono font-bold" :class="(selectedProduct?.ppn_chk || selectedProduct?.ppnChk) === 'Y' ? 'text-emerald-700' : 'text-slate-500'" x-text="selectedProduct?.ppn_chk || selectedProduct?.ppnChk || 'Y'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">15</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">HargaBeliTerakhir</td><td class="py-2 px-4 font-mono font-bold text-slate-800" x-text="formatRupiah(selectedProduct?.harga_beli_terakhir ?? selectedProduct?.lastCostPrice ?? selectedProduct?.costPrice)"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">16</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">StatusBarang</td><td class="py-2 px-4 font-bold" x-text="selectedProduct?.status_barang || selectedProduct?.statusBarang || selectedProduct?.status || 'TERSEDIA'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">17</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">KodeSuplier</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.kode_suplier || selectedProduct?.koSup || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">18</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ManufacturingDate</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.manufacturing_date || selectedProduct?.mfgDate || '-'"></td></tr>
                                    <tr class="hover:bg-slate-50"><td class="py-2 px-3 text-center text-gray-400 font-mono">19</td><td class="py-2 px-4 font-mono font-bold text-[#0052cc]">ExpDate</td><td class="py-2 px-4 font-mono" x-text="selectedProduct?.exp_date || selectedProduct?.expiredDate || '-'"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. SUPPLIER PRODUK (Jika ada relasi supplier) -->
                    <template x-if="selectedProduct?.suppliedBy && selectedProduct.suppliedBy.length > 0">
                        <div class="p-4 bg-white rounded-xl border border-gray-200 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Pemasok Produk (Supplier)</span>
                                <span class="text-[10.5px] font-semibold text-gray-400" x-text="selectedProduct.suppliedBy.length + ' Supplier'"></span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="sup in selectedProduct.suppliedBy" :key="sup.supplier_id">
                                    <a :href="'{{ url('/suppliers') }}/' + sup.supplier_id"
                                       class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-slate-50 hover:bg-blue-50/70 border border-gray-100 transition-colors">
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 truncate" x-text="sup.supplier_name"></div>
                                            <div class="text-[10.5px] font-mono text-gray-500" x-text="sup.supplier_code + ' • ' + sup.total_transactions + 'x Pembelian'"></div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="font-mono font-bold text-[#0052cc]" x-text="formatRupiah(sup.last_purchase_price)"></div>
                                            <div class="text-[10px] text-gray-400">Hrg Beli Supplier</div>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>

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

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
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

    <!-- MODAL DETAIL BARANG RETUR KARANTINA GUDANG -->
    <template x-teleport="body">
        <div x-show="showReturnDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showReturnDetailModal"
                 @click.away="showReturnDetailModal = false"
                 x-transition.scale.origin.center
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200 flex flex-col max-h-[90vh]">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-900 text-white shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">warehouse</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-white">Detail Inventaris Barang Retur (Karantina Gudang)</h3>
                            <p class="text-xs text-slate-400 font-medium">Asal Pengiriman Kasir Cabang &bull; Karantina Logistik Terisolasi</p>
                        </div>
                    </div>
                    <button @click="showReturnDetailModal = false" class="text-slate-400 hover:text-white cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto flex-1 bg-[#f8fafc]" x-if="selectedReturnForDetail">
                    <!-- Product Info Box -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">IDENTITAS PRODUK RETUR</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200" x-text="selectedReturnForDetail?.sku"></span>
                                <span class="bg-amber-100 text-amber-900 font-black font-mono px-2.5 py-0.5 rounded-md border border-amber-200"
                                      x-text="'Total Saldo SKU: ' + skuTotalQty + ' Pcs'"></span>
                            </div>
                        </div>
                        <div class="text-[15px] font-black text-slate-900" x-text="selectedReturnForDetail?.name"></div>
                        <div class="text-[11px] text-slate-500 font-mono" x-text="'Barcode: ' + (selectedReturnForDetail?.barcode || '-')"></div>
                    </div>

                    <!-- Metadata Grid (Batch Info Terpilih) -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-2xs space-y-1">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">TOKO / CABANG ASAL DOKUMEN</span>
                            <div class="font-bold text-slate-800 text-[13px] flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">store</span>
                                <span x-text="selectedReturnForDetail?.branchOrigin"></span>
                            </div>
                            <span class="text-[11px] text-slate-500 block" x-text="'Kasir: ' + (selectedReturnForDetail?.cashierSender || 'Kasir Toko Cabang')"></span>
                        </div>

                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-2xs space-y-1">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">REFERENSI DOKUMEN</span>
                            <div class="font-mono font-bold text-slate-800 text-[13px]" x-text="selectedReturnForDetail?.docRef"></div>
                            <span class="text-[11px] text-slate-500 block" x-text="'Tgl Terima: ' + selectedReturnForDetail?.receivedDate"></span>
                        </div>

                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-2xs space-y-1">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">FISIK DITERIMA BATCH INI</span>
                            <div class="font-mono font-black text-amber-800 text-[16px]" x-text="(selectedReturnForDetail?.qtyReceived || 0) + ' Pcs'"></div>
                            <span class="text-[10.5px] text-slate-400 block">Karantina Gudang Pusat</span>
                        </div>

                        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-2xs space-y-1">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">STATUS VERIFIKASI RETUR</span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-extrabold border mt-0.5 bg-emerald-50 text-emerald-800 border-emerald-300">
                                <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                <span>QC Selesai (Diterima di Gudang)</span>
                            </span>
                        </div>
                    </div>

                    <!-- Peringatan jika status menunggu QC / Verifikasi -->
                    <template x-if="(selectedReturnForDetail?.inspectionStatus || '').toLowerCase().includes('menunggu')">
                        <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 flex items-start gap-2.5">
                            <span class="material-symbols-rounded text-[20px] text-amber-600 shrink-0 mt-0.5">lock</span>
                            <div class="space-y-0.5">
                                <div class="font-bold text-[12px]">Status: Menunggu Persetujuan Verifikasi Retur Masuk</div>
                                <div class="text-[11px] text-amber-800 leading-relaxed">
                                    Barang ini berstatus menunggu pemeriksaan. Sesuai operasional gudang, verifikasi fisik, persetujuan penerimaan, maupun penolakan dilakukan melalui halaman <strong>Barang Masuk Retur</strong>. Tidak ada tindakan pengubahan status langsung di halaman inventaris ini.
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Condition & Notes Box -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-2.5">
                        <div>
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block mb-1">KONDISI AWAL SERAH TERIMA DOKUMEN INI</span>
                            <span class="text-rose-700 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-200 text-xs" x-text="selectedReturnForDetail?.initialCondition"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block mb-1">CATATAN PEMERIKSAAN QC GUDANG</span>
                            <p class="text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200 leading-relaxed font-medium" x-text="selectedReturnForDetail?.inspectorNotes || '-'"></p>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[11px] text-slate-500">
                            <span>Petugas QC: <strong class="text-slate-700" x-text="selectedReturnForDetail?.inspectedBy || 'Staff Gudang'"></strong></span>
                            <span>Tindak Lanjut: <strong class="text-slate-700" x-text="selectedReturnForDetail?.followUp || '-'"></strong></span>
                        </div>
                    </div>

                    <!-- Tabel Histori Semua Dokumen Penerimaan SKU Ini -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10.5px] font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px] text-[#0052cc]">history</span>
                                <span>Histori Penerimaan Dokumen untuk SKU Ini (<span x-text="skuHistoryList.length + ' Penerimaan'"></span>)</span>
                            </span>
                            <span class="text-[11px] font-mono text-slate-500">Dapat ditelusuri per nomor dokumen</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-[11px]">
                                <thead class="bg-slate-50 font-bold text-slate-600 border-b border-slate-200">
                                    <tr>
                                        <th class="p-2.5">No. Dokumen</th>
                                        <th class="p-2.5">Asal Cabang</th>
                                        <th class="p-2.5">Tgl Terima</th>
                                        <th class="p-2.5 text-center">Qty Diterima</th>
                                        <th class="p-2.5">Kondisi Awal</th>
                                        <th class="p-2.5">Tindak Lanjut</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium">
                                    <template x-for="hist in skuHistoryList" :key="hist.id">
                                        <tr class="hover:bg-slate-50/80 transition-colors"
                                            :class="hist.id === selectedReturnForDetail?.id ? 'bg-amber-50/40 font-bold' : ''">
                                            <td class="p-2.5 font-mono text-slate-800" x-text="hist.docRef"></td>
                                            <td class="p-2.5 text-slate-700" x-text="hist.branchOrigin"></td>
                                            <td class="p-2.5 font-mono text-[10.5px] text-slate-500 whitespace-nowrap" x-text="hist.receivedDate"></td>
                                            <td class="p-2.5 text-center font-mono font-black text-amber-800" x-text="hist.qtyReceived + ' Pcs'"></td>
                                            <td class="p-2.5 text-rose-700 max-w-[140px] truncate" :title="hist.initialCondition" x-text="hist.initialCondition"></td>
                                            <td class="p-2.5">
                                                <span class="text-slate-700 font-semibold text-[11px]" x-text="hist.followUp || '-'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SOP Notice -->
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900 leading-relaxed">
                        <strong>Isolasi Stok:</strong> Barang retur ini disimpan di database terpisah (<code>pos_ilp_stok_retur_gudang_v1</code>) dan tidak menambah stok siap jual toko normal.
                    </div>
                </div>

                <div class="px-6 py-4 bg-white border-t border-gray-100 flex items-center justify-between shrink-0">
                    <button type="button" @click="showReturnDetailModal = false" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-100 cursor-pointer">Tutup</button>
                    <a href="{{ url('/manager/retur-masuk') }}" class="px-4 py-2 rounded-xl text-xs font-bold text-amber-900 bg-amber-50 hover:bg-amber-100 border border-amber-200 inline-flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">archive</span>
                        <span>Buka Halaman Barang Masuk Retur</span>
                    </a>
                </div>
            </div>
        </div>
    </template>

    <!-- ============================================================== -->
    <!-- MODAL IMPORT EXCEL MASTER PRODUK (STANDAR 36 KOLOM ILP)       -->
    <!-- ============================================================== -->
    <template x-teleport="body">
        <div x-show="showImportModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs"
             style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-6xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150"
                 @click.away="if (importStep !== 'processing') showImportModal = false">
                
                <!-- Modal Header -->
                <div class="px-6 py-4.5 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
                            <span class="material-symbols-rounded text-[22px]">upload_file</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white">Import Master Produk Excel</h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 border border-emerald-400/30 text-emerald-300">Format 19 Kolom (High-Volume Ready)</span>
                            </div>
                            <p class="text-xs text-slate-300 mt-0.5">Unggah dan sinkronisasi master barang hingga ratusan ribu data secara cepat dengan format 19 kolom</p>
                        </div>
                    </div>
                    <button type="button" 
                            x-show="importStep !== 'processing'"
                            @click="showImportModal = false" 
                            class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">close</span>
                    </button>
                </div>

                <!-- Modal Body: Step by Step -->
                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    
                    <!-- ============================================== -->
                    <!-- STEP 1: UPLOAD FILE                            -->
                    <!-- ============================================== -->
                    <div x-show="importStep === 'upload'" class="space-y-5">
                        
                        <!-- Panduan Standar 19 Kolom Banner -->
                        <div class="p-4 bg-emerald-50/80 border border-emerald-200 rounded-xl text-emerald-950 flex items-start gap-3">
                            <span class="material-symbols-rounded text-emerald-600 text-[22px] shrink-0 mt-0.5">verified</span>
                            <div class="text-xs space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-emerald-900 block text-sm">Standar Format Master Barang Gudang (19 Kolom)</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-200 text-emerald-900">Kapasitas Ratusan Ribu Data</span>
                                </div>
                                <p class="text-emerald-800 leading-relaxed">
                                    File Excel / CSV wajib memuat <strong>19 kolom terstandarisasi</strong>: <code>IdProduk, SKU, Kode Barcode, KodeProduk, QtyProduk, NamaProduk, SatuanUnit, HargaBeli, HargaJualUmum, HargaJualMember, ProdukDisc, MinimumStock, PPNOut, PPNChk, HargaBeliTerakhir, StatusBarang, KodeSuplier, ManufacturingDate, ExpDate</code>.
                                </p>
                            </div>
                        </div>

                        <!-- Drag and drop / File Picker Area -->
                        <div class="border-2 border-dashed rounded-2xl p-8 text-center transition-all cursor-pointer relative select-none"
                             :class="importFile ? 'border-emerald-400 bg-emerald-50/30' : (isDragging ? 'border-[#0052cc] bg-blue-50/50' : 'border-slate-300 hover:border-[#0052cc] hover:bg-slate-50/50')"
                             @click="triggerFileInput()"
                             @dragenter.prevent="isDragging = true"
                             @dragover.prevent="isDragging = true"
                             @dragleave.prevent="isDragging = false"
                             @drop.prevent="handleFileDrop($event)">
                            <input type="file" 
                                   id="excelFileInput"
                                   x-ref="excelFileInput" 
                                   @click.stop=""
                                   @change="onFileSelected($event)" 
                                   accept=".xlsx,.xls,.csv" 
                                   class="hidden">

                            <template x-if="!importFile">
                                <div class="space-y-3 pointer-events-none">
                                    <div class="w-14 h-14 mx-auto rounded-2xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                                        <span class="material-symbols-rounded text-[32px]">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">Pilih file Excel Master Barang atau tarik file ke sini</p>
                                        <p class="text-xs text-slate-400 mt-1">Mendukung format .XLSX, .XLS, atau .CSV (Maksimal 25MB • Mendukung Ratusan Ribu Data)</p>
                                    </div>
                                    <button type="button" @click.stop="triggerFileInput()" class="pointer-events-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">folder_open</span>
                                        <span>Telusuri File di Komputer</span>
                                    </button>
                                </div>
                            </template>

                            <template x-if="importFile">
                                <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-emerald-200 shadow-2xs text-left" @click.stop="">
                                    <div class="flex items-center gap-3.5">
                                        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-rounded text-[28px]">description</span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm" x-text="importFileName"></p>
                                            <p class="text-xs text-slate-400 font-mono mt-0.5" x-text="(importFile.size / 1024).toFixed(1) + ' KB'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" 
                                                @click.stop="resetFileInput()" 
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                            Ganti File
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Template Download & Safety Info Row -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="material-symbols-rounded text-blue-600 text-[18px]">info</span>
                                <span>Perlu contoh format struktur 19 kolom?</span>
                            </div>
                            <button type="button" 
                                    @click="downloadILPTemplate()"
                                    class="text-xs font-bold text-[#0052cc] hover:text-[#0043a8] hover:underline flex items-center gap-1 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Template Format Master (19 Kolom)</span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- STEP 2: INVALID STRUCTURE (FORMAT TIDAK COCOK) -->
                    <!-- ============================================== -->
                    <div x-show="importStep === 'invalid_structure'" style="display: none;" class="space-y-5">
                        <div class="p-5 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-rose-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                                <span class="material-symbols-rounded text-[28px]">error</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <h4 class="font-bold text-rose-900 text-base">Format Kolom Excel Tidak Sesuai Standar Master Produk</h4>
                                <p class="text-xs text-rose-800 leading-relaxed" x-text="validationErrorDetails?.message || 'Jumlah atau nama kolom file yang diunggah tidak sesuai dengan 19 kolom master barang.'"></p>
                                <div class="flex items-center gap-4 text-xs font-semibold text-rose-700 pt-1">
                                    <span>Kolom Terdeteksi: <strong class="font-mono text-sm" x-text="validationErrorDetails?.actual_count || 0"></strong></span>
                                    <span>Kolom Wajib: <strong class="font-mono text-sm" x-text="validationErrorDetails?.expected_count || 19"></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Missing Columns Alert -->
                        <template x-if="validationErrorDetails?.missing_columns && validationErrorDetails.missing_columns.length > 0">
                            <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-2xs space-y-2">
                                <span class="text-xs font-extrabold text-rose-700 uppercase tracking-wider block">Kolom Wajib yang Hilang / Belum Ada (<span x-text="validationErrorDetails.missing_columns.length"></span> Kolom):</span>
                                <div class="flex flex-wrap gap-1.5 max-h-40 overflow-y-auto p-1">
                                    <template x-for="(col, idx) in validationErrorDetails.missing_columns" :key="idx">
                                        <span class="px-2.5 py-1 rounded-md bg-rose-100 text-rose-800 text-[11px] font-mono font-bold" x-text="col"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Unexpected Columns Alert -->
                        <template x-if="validationErrorDetails?.unexpected_columns && validationErrorDetails.unexpected_columns.length > 0">
                            <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-2xs space-y-2">
                                <span class="text-xs font-extrabold text-amber-700 uppercase tracking-wider block">Kolom Tak Dikenal / Di Luar Format Standar (<span x-text="validationErrorDetails.unexpected_columns.length"></span> Kolom):</span>
                                <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto p-1">
                                    <template x-for="(col, idx) in validationErrorDetails.unexpected_columns" :key="idx">
                                        <span class="px-2.5 py-1 rounded-md bg-amber-100 text-amber-800 text-[11px] font-mono font-bold" x-text="col"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex items-start gap-2.5">
                            <span class="material-symbols-rounded text-slate-400 text-[18px] shrink-0 mt-0.5">shield</span>
                            <p class="leading-relaxed">
                                <strong>Sistem Menolak Import Ini:</strong> Untuk mencegah data corrupt atau salah kolom (seperti harga masuk ke kolom satuan), seluruh data dibatalkan dan tidak ada yang masuk ke database. Silakan gunakan template resmi atau perbaiki nama kolom pada file Excel Anda.
                            </p>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- STEP 3: PREVIEW DATA SEBELUM IMPORT           -->
                    <!-- ============================================== -->
                    <div x-show="importStep === 'preview'" style="display: none;" class="space-y-5">
                        
                        <!-- Success Validation Header -->
                        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center">
                                    <span class="material-symbols-rounded text-[22px]">check_circle</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-emerald-950 text-sm">Struktur 19 Kolom Valid & Terverifikasi</h4>
                                        <span x-show="validationData?.summary?.is_high_volume" class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-blue-100 text-[#0052cc] border border-blue-200">Mode Bulk Import (Ratusan Ribu Data)</span>
                                    </div>
                                    <p class="text-xs text-emerald-800" x-text="'File: ' + (validationData?.file_name || importFileName)"></p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">Siap Diimport</span>
                        </div>

                        <!-- Statistics Grid (Interactive Filter Cards) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 transition-all cursor-pointer hover:border-slate-400"
                                 @click="importPreviewFilter = 'all'; importPreviewPage = 1"
                                 :class="importPreviewFilter === 'all' ? 'ring-2 ring-slate-700 bg-white shadow-xs' : ''">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Baris File</span>
                                <div class="text-lg font-black text-slate-800 font-mono mt-0.5" x-text="validationData?.summary?.total_rows || 0"></div>
                                <div class="text-[11px] text-slate-500 mt-1">Semua baris terdeteksi</div>
                            </div>
                            <div class="bg-emerald-50/70 p-3.5 rounded-xl border border-emerald-200 transition-all cursor-pointer hover:border-emerald-400"
                                 @click="importPreviewFilter = 'new'; importPreviewPage = 1"
                                 :class="importPreviewFilter === 'new' ? 'ring-2 ring-emerald-500 bg-emerald-50 shadow-xs' : ''">
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Data Valid</span>
                                <div class="text-lg font-black text-emerald-700 font-mono mt-0.5" x-text="validationData?.summary?.valid_rows || 0"></div>
                                <div class="text-[11px] text-emerald-700 mt-1">Siap diproses sistem</div>
                            </div>
                            <div class="bg-blue-50/70 p-3.5 rounded-xl border border-blue-200 transition-all cursor-pointer hover:border-[#0052cc]"
                                 @click="importPreviewFilter = (importPreviewFilter === 'existing' ? 'all' : 'existing'); importPreviewPage = 1"
                                 :class="importPreviewFilter === 'existing' ? 'ring-2 ring-[#0052cc] bg-blue-100/60 shadow-xs' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider block">Produk Terdaftar</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-blue-200 text-blue-900 font-bold" x-show="(validationData?.summary?.existing_in_db_count || 0) > 0">Klik Filter</span>
                                </div>
                                <div class="text-lg font-black text-[#0052cc] font-mono mt-0.5" x-text="validationData?.summary?.existing_in_db_count || 0"></div>
                                <template x-if="validationData?.existing_rows_list?.length > 0">
                                    <div class="text-[11px] text-blue-900 font-semibold mt-1 truncate" :title="validationData.existing_rows_list.map(e => 'Baris ' + e.row).join(', ')">
                                        <span>Baris: </span>
                                        <span class="font-mono text-[#0052cc]" x-text="validationData.existing_rows_list.slice(0, 4).map(e => e.row).join(', ') + (validationData.existing_rows_list.length > 4 ? ' (+' + (validationData.existing_rows_list.length - 4) + ')' : '')"></span>
                                    </div>
                                </template>
                                <template x-if="!validationData?.existing_rows_list?.length">
                                    <div class="text-[11px] text-slate-500 mt-1">Semua produk baru</div>
                                </template>
                            </div>
                            <div class="bg-rose-50/70 p-3.5 rounded-xl border border-rose-200 transition-all cursor-pointer hover:border-rose-400"
                                 @click="importPreviewFilter = (importPreviewFilter === 'error' ? 'all' : 'error'); importPreviewPage = 1"
                                 :class="importPreviewFilter === 'error' ? 'ring-2 ring-rose-500 bg-rose-100/60 shadow-xs' : ''">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-extrabold text-rose-700 uppercase tracking-wider block">Baris Error</span>
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-rose-200 text-rose-900 font-bold" x-show="(validationData?.summary?.error_rows || 0) > 0">Klik Filter</span>
                                </div>
                                <div class="text-lg font-black text-rose-700 font-mono mt-0.5" x-text="validationData?.summary?.error_rows || 0"></div>
                                <template x-if="validationData?.error_rows_list?.length > 0">
                                    <div class="text-[11px] text-rose-900 font-semibold mt-1 truncate" :title="validationData.error_rows_list.map(e => 'Baris ' + e.row + ': ' + e.error).join('; ')">
                                        <span>Baris: </span>
                                        <span class="font-mono text-rose-700" x-text="validationData.error_rows_list.slice(0, 4).map(e => e.row).join(', ') + (validationData.error_rows_list.length > 4 ? ' (+' + (validationData.error_rows_list.length - 4) + ')' : '')"></span>
                                    </div>
                                </template>
                                <template x-if="!validationData?.error_rows_list?.length">
                                    <div class="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-1">
                                        <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                        <span>Tidak ada error</span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Card Rincian Baris Error (Jika Ada Error) -->
                        <template x-if="validationData?.error_rows_list?.length > 0">
                            <div class="bg-rose-50 border border-rose-300 rounded-xl p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-rose-900 flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-rose-600 text-[18px]">error</span>
                                        <span>Daftar Rincian Baris Error (<span x-text="validationData.error_rows_list.length"></span> Baris Bermasalah):</span>
                                    </span>
                                    <button type="button" @click="importPreviewFilter = (importPreviewFilter === 'error' ? 'all' : 'error'); importPreviewPage = 1"
                                            class="text-[11px] font-bold text-rose-700 hover:text-rose-900 underline cursor-pointer">
                                        <span x-text="importPreviewFilter === 'error' ? 'Tampilkan Semua Baris' : 'Filter Tabel ke Baris Error Saja'"></span>
                                    </button>
                                </div>
                                <div class="max-h-36 overflow-y-auto space-y-1.5 pr-1 divide-y divide-rose-200">
                                    <template x-for="err in validationData.error_rows_list" :key="err.row">
                                        <div class="text-[11px] pt-1.5 flex items-start gap-2">
                                            <span class="px-2 py-0.5 rounded bg-rose-200 text-rose-900 font-mono font-bold shrink-0" x-text="'Baris ' + err.row"></span>
                                            <span class="font-bold text-slate-800" x-text="(err.sku !== '-' ? err.sku : (err.nama_produk !== '-' ? err.nama_produk : 'Data Kosong')) + ' — '"></span>
                                            <span class="text-rose-700 font-semibold" x-text="err.error || (err.messages ? err.messages.join(', ') : 'Format data tidak valid')"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Card Rincian Produk Terdaftar (Jika Ada Produk Terdaftar di Database) -->
                        <template x-if="validationData?.existing_rows_list?.length > 0">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3.5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-blue-950 flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[#0052cc] text-[18px]">verified</span>
                                        <span>Daftar Baris Produk Sudah Terdaftar di Database (<span x-text="validationData.existing_rows_list.length"></span> Produk):</span>
                                    </span>
                                    <button type="button" @click="importPreviewFilter = (importPreviewFilter === 'existing' ? 'all' : 'existing'); importPreviewPage = 1"
                                            class="text-[11px] font-bold text-[#0052cc] hover:underline cursor-pointer">
                                        <span x-text="importPreviewFilter === 'existing' ? 'Tampilkan Semua Baris' : 'Filter Tabel ke Produk Terdaftar Saja'"></span>
                                    </button>
                                </div>
                                <div class="text-xs text-blue-900 bg-white p-2.5 rounded-lg border border-blue-200 max-h-32 overflow-y-auto leading-relaxed">
                                    <div class="font-semibold text-slate-600 mb-1">Nomor Baris di File Excel:</div>
                                    <div class="flex flex-wrap gap-1.5 font-mono text-[11px]">
                                        <template x-for="ex in validationData.existing_rows_list" :key="ex.row">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-100 text-blue-900 border border-blue-200"
                                                  :title="ex.nama_produk">
                                                <strong x-text="'Baris ' + ex.row"></strong>
                                                <span class="text-blue-600" x-text="'(' + ex.sku + ')'"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Stock Protection Alert (CRITICAL REQUIREMENT) -->
                        <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-start gap-2.5">
                            <span class="material-symbols-rounded text-[#0052cc] text-[20px] shrink-0 mt-0.5">lock_clock</span>
                            <div class="space-y-0.5 leading-relaxed">
                                <strong>Jaminan Keamanan Stok Fisik Toko:</strong>
                                <span>Import master produk ini memperbarui atau mendaftarkan master SKU secara massal via stream processing hemat memori. <strong>Stok fisik toko di cabang tetap aman</strong> dan tersinkronisasi otomatis dengan master gudang.</span>
                            </div>
                        </div>

                        <!-- Duplicate Handling Strategy Selector -->
                        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs space-y-2">
                            <label class="text-xs font-bold text-slate-700 block">Tindakan Jika SKU / Barcode Sudah Terdaftar di Database:</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="duplicateAction === 'update' ? 'border-[#0052cc] bg-blue-50/30' : 'border-slate-200 hover:bg-slate-50'">
                                    <input type="radio" name="duplicateAction" value="update" x-model="duplicateAction" class="mt-0.5 text-[#0052cc] focus:ring-[#0052cc]">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Perbarui Data (Update)</span>
                                        <span class="text-slate-500">Perbarui harga, nama, PPN, dan atribut 19 kolom master. Stok tetap utuh.</span>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="duplicateAction === 'skip' ? 'border-[#0052cc] bg-blue-50/30' : 'border-slate-200 hover:bg-slate-50'">
                                    <input type="radio" name="duplicateAction" value="skip" x-model="duplicateAction" class="mt-0.5 text-[#0052cc] focus:ring-[#0052cc]">
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-800 block">Lewati (Skip Duplikat)</span>
                                        <span class="text-slate-500">Hanya tambahkan produk baru, jangan ubah data produk yang sudah terdaftar.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Preview Table with High-Volume Pagination and Color-Coded Rows -->
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-2xs space-y-0">
                            <!-- Table Card Header (Row 1: Title, Badge & Per Page) -->
                            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-rounded text-[18px] text-[#0052cc]">table_view</span>
                                        <span>Pratinjau Data (Struktur 19 Kolom Standar)</span>
                                    </span>
                                    <span class="text-xs font-mono px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold whitespace-nowrap"
                                          x-text="(getFilteredPreviewRows().length.toLocaleString('id-ID')) + ' dari ' + ((validationData?.summary?.total_rows || validationData?.preview_rows?.length || 0).toLocaleString('id-ID')) + ' baris'"></span>
                                </div>

                                <!-- Per Page Selector -->
                                <div class="flex items-center gap-2 text-xs shrink-0">
                                    <span class="text-[11px] text-slate-500 font-medium whitespace-nowrap">Tampilkan:</span>
                                    <select x-model="importPreviewPerPage" @change="importPreviewPage = 1"
                                            class="text-xs py-1 px-2.5 bg-white border border-slate-300 rounded-lg font-bold text-slate-700 shadow-2xs focus:ring-2 focus:ring-[#0052cc] focus:border-transparent cursor-pointer">
                                        <option value="50">50 baris</option>
                                        <option value="100">100 baris</option>
                                        <option value="250">250 baris</option>
                                        <option value="500">500 baris</option>
                                        <option value="all">Semua baris</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Table Controls Toolbar (Row 2: Filter Tabs & Search Bar) -->
                            <div class="p-3 bg-white border-b border-slate-200 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                <!-- Filter Tabs (Pills) -->
                                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                                    <!-- Tab: Semua -->
                                    <button type="button" @click="importPreviewFilter = 'all'; importPreviewPage = 1"
                                            class="px-3 py-1.5 rounded-xl font-bold text-xs transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 shrink-0"
                                            :class="importPreviewFilter === 'all' ? 'bg-slate-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                                        <span>Semua</span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono"
                                              :class="importPreviewFilter === 'all' ? 'bg-slate-700 text-slate-200' : 'bg-slate-200 text-slate-700'"
                                              x-text="(validationData?.preview_rows?.length || 0).toLocaleString('id-ID')"></span>
                                    </button>

                                    <!-- Tab: Baru -->
                                    <button type="button" @click="importPreviewFilter = 'new'; importPreviewPage = 1"
                                            class="px-3 py-1.5 rounded-xl font-bold text-xs transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 shrink-0"
                                            :class="importPreviewFilter === 'new' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200'">
                                        <span class="material-symbols-rounded text-[14px]">add_circle</span>
                                        <span>Baru</span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                                              :class="importPreviewFilter === 'new' ? 'bg-emerald-800 text-emerald-100' : 'bg-emerald-200 text-emerald-900'"
                                              x-text="Math.max(0, (validationData?.summary?.valid_rows || 0) - (validationData?.summary?.existing_in_db_count || 0)).toLocaleString('id-ID')"></span>
                                    </button>

                                    <!-- Tab: Produk Terdaftar -->
                                    <button type="button" @click="importPreviewFilter = 'existing'; importPreviewPage = 1"
                                            class="px-3 py-1.5 rounded-xl font-bold text-xs transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 shrink-0"
                                            :class="importPreviewFilter === 'existing' ? 'bg-[#0052cc] text-white shadow-2xs' : 'bg-blue-50 text-[#0052cc] hover:bg-blue-100 border border-blue-200'">
                                        <span class="material-symbols-rounded text-[14px]">verified</span>
                                        <span>Terdaftar di DB</span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                                              :class="importPreviewFilter === 'existing' ? 'bg-blue-900 text-blue-100' : 'bg-blue-200 text-blue-950'"
                                              x-text="(validationData?.summary?.existing_in_db_count || 0).toLocaleString('id-ID')"></span>
                                    </button>

                                    <!-- Tab: Baris Error -->
                                    <button type="button" @click="importPreviewFilter = 'error'; importPreviewPage = 1"
                                            class="px-3 py-1.5 rounded-xl font-bold text-xs transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5 shrink-0"
                                            :class="importPreviewFilter === 'error' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200'">
                                        <span class="material-symbols-rounded text-[14px]">error</span>
                                        <span>Baris Error</span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold"
                                              :class="importPreviewFilter === 'error' ? 'bg-rose-800 text-rose-100' : 'bg-rose-200 text-rose-900'"
                                              x-text="(validationData?.summary?.error_rows || 0).toLocaleString('id-ID')"></span>
                                    </button>
                                </div>

                                <!-- Search Input (Clean & Centered Icon) -->
                                <div class="relative w-full lg:w-72 shrink-0">
                                    <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">search</span>
                                    <input type="text" x-model="importPreviewSearch" @input="importPreviewPage = 1" placeholder="Cari baris, SKU, nama barang..."
                                           class="w-full h-9 pl-9 pr-8 text-xs bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0052cc] focus:border-transparent transition-all shadow-2xs">
                                    <button type="button" x-show="importPreviewSearch" @click="importPreviewSearch = ''; importPreviewPage = 1"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-0.5 rounded cursor-pointer">
                                        <span class="material-symbols-rounded text-[16px]">close</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Table Scroll Container -->
                            <div class="max-h-[460px] overflow-x-auto overflow-y-auto">
                                <table class="w-full text-left text-xs whitespace-nowrap border-collapse">
                                    <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 border-b border-slate-200 text-[11px] z-10">
                                        <tr>
                                            <th class="p-2.5 text-center">Baris</th>
                                            <th class="p-2.5 font-mono text-[#0052cc]">IdProduk</th>
                                            <th class="p-2.5 font-mono">SKU</th>
                                            <th class="p-2.5 font-mono">Kode Barcode</th>
                                            <th class="p-2.5 font-mono">KodeProduk</th>
                                            <th class="p-2.5 text-center font-mono">QtyProduk</th>
                                            <th class="p-2.5">NamaProduk</th>
                                            <th class="p-2.5 text-center">SatuanUnit</th>
                                            <th class="p-2.5 text-right font-mono">HargaBeli</th>
                                            <th class="p-2.5 text-right font-mono text-emerald-700">HargaJualUmum</th>
                                            <th class="p-2.5 text-right font-mono text-[#0052cc]">HargaJualMember</th>
                                            <th class="p-2.5 text-center font-mono">ProdukDisc</th>
                                            <th class="p-2.5 text-center font-mono">MinimumStock</th>
                                            <th class="p-2.5 text-center font-mono">PPNOut</th>
                                            <th class="p-2.5 text-center font-mono">PPNChk</th>
                                            <th class="p-2.5 text-right font-mono">HargaBeliTerakhir</th>
                                            <th class="p-2.5 text-center">StatusBarang</th>
                                            <th class="p-2.5 text-center font-mono">KodeSuplier</th>
                                            <th class="p-2.5 text-center font-mono">ManufacturingDate</th>
                                            <th class="p-2.5 text-center font-mono">ExpDate</th>
                                            <th class="p-2.5 text-center sticky right-0 bg-slate-100 z-20">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="pRow in getPaginatedPreviewRows()" :key="pRow.row">
                                            <tr :class="{
                                                    'bg-rose-100 text-rose-950 font-bold border-l-4 border-l-rose-600 hover:bg-rose-200': pRow.has_error,
                                                    'bg-blue-100 text-blue-950 font-semibold border-l-4 border-l-[#0052cc] hover:bg-blue-200': !pRow.has_error && pRow.is_existing,
                                                    'hover:bg-slate-50/80 font-medium': !pRow.has_error && !pRow.is_existing
                                                }">
                                                <td class="p-2.5 text-center font-mono"
                                                    :class="{
                                                        'text-rose-900 font-black': pRow.has_error,
                                                        'text-[#0052cc] font-bold': !pRow.has_error && pRow.is_existing,
                                                        'text-slate-400': !pRow.has_error && !pRow.is_existing
                                                    }" x-text="pRow.row"></td>
                                                <td class="p-2.5 font-mono font-bold text-[#0052cc]" x-text="pRow.id_produk || pRow.ilp_id || '-'"></td>
                                                <td class="p-2.5 font-mono font-bold text-slate-800" x-text="pRow.sku"></td>
                                                <td class="p-2.5 font-mono text-slate-600" x-text="pRow.kode_barcode || pRow.barcode || '-'"></td>
                                                <td class="p-2.5 font-mono text-slate-600" x-text="pRow.kode_produk || pRow.sku || '-'"></td>
                                                <td class="p-2.5 text-center font-mono font-bold" x-text="pRow.qty_produk !== undefined ? pRow.qty_produk : (pRow.stock || 0)"></td>
                                                <td class="p-2.5 max-w-[220px] truncate" :title="pRow.nama_produk || pRow.name" x-text="pRow.nama_produk || pRow.name"></td>
                                                <td class="p-2.5 text-center text-slate-600 font-bold" x-text="pRow.satuan_unit || pRow.unit || 'Pcs'"></td>
                                                <td class="p-2.5 text-right font-mono" x-text="'Rp ' + (pRow.harga_beli ?? pRow.cost_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-right font-mono font-bold text-emerald-700" x-text="'Rp ' + (pRow.harga_jual_umum ?? pRow.price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-right font-mono font-bold text-[#0052cc]" x-text="'Rp ' + (pRow.harga_jual_member ?? pRow.member_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="(pRow.produk_disc ?? 0) + '%'"></td>
                                                <td class="p-2.5 text-center font-mono text-slate-600" x-text="pRow.minimum_stock ?? pRow.stock_min ?? 5"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="(pRow.ppn_out ?? 11) + '%'"></td>
                                                <td class="p-2.5 text-center font-mono font-bold" :class="(pRow.ppn_chk || 'Y') === 'Y' ? 'text-emerald-700' : 'text-slate-400'" x-text="pRow.ppn_chk || 'Y'"></td>
                                                <td class="p-2.5 text-right font-mono" x-text="'Rp ' + (pRow.harga_beli_terakhir ?? pRow.cost_price ?? 0).toLocaleString('id-ID')"></td>
                                                <td class="p-2.5 text-center font-bold" x-text="pRow.status_barang || 'TERSEDIA'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.kode_suplier || '-'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.manufacturing_date || '-'"></td>
                                                <td class="p-2.5 text-center font-mono" x-text="pRow.exp_date || '-'"></td>
                                                <td class="p-2.5 text-center sticky right-0"
                                                    :class="{
                                                        'bg-rose-100': pRow.has_error,
                                                        'bg-blue-100': !pRow.has_error && pRow.is_existing,
                                                        'bg-white': !pRow.has_error && !pRow.is_existing
                                                    }">
                                                    <template x-if="pRow.has_error">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white shadow-2xs flex items-center gap-1 justify-center" :title="pRow.error_message">
                                                            <span class="material-symbols-rounded text-[12px]">error</span>
                                                            <span>Error</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!pRow.has_error && pRow.is_existing">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#0052cc] text-white shadow-2xs flex items-center gap-1 justify-center" title="Sudah terdaftar di database">
                                                            <span class="material-symbols-rounded text-[12px]">verified</span>
                                                            <span>Terdaftar</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="!pRow.has_error && !pRow.is_existing">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-600 text-white shadow-2xs flex items-center gap-1 justify-center" title="Produk baru">
                                                            <span class="material-symbols-rounded text-[12px]">add_circle</span>
                                                            <span>Baru</span>
                                                        </span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Footer -->
                            <div class="p-3 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
                                <div class="text-slate-500 font-medium text-[11px]">
                                    <span>Halaman </span>
                                    <strong class="text-slate-800" x-text="importPreviewPage"></strong>
                                    <span> dari </span>
                                    <strong class="text-slate-800" x-text="getTotalPreviewPages()"></strong>
                                    <span class="ml-1 text-slate-400">
                                        (Menampilkan baris <span x-text="getFilteredPreviewRows().length ? ((importPreviewPage - 1) * (importPreviewPerPage === 'all' ? getFilteredPreviewRows().length : parseInt(importPreviewPerPage)) + 1) : 0"></span> -
                                        <span x-text="Math.min(importPreviewPage * (importPreviewPerPage === 'all' ? getFilteredPreviewRows().length : parseInt(importPreviewPerPage)), getFilteredPreviewRows().length)"></span>
                                        dari <span x-text="getFilteredPreviewRows().length"></span> data)
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5" x-show="getTotalPreviewPages() > 1">
                                    <button type="button" @click="importPreviewPage = 1" :disabled="importPreviewPage <= 1"
                                            class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        « Awal
                                    </button>
                                    <button type="button" @click="importPreviewPage = Math.max(1, importPreviewPage - 1)" :disabled="importPreviewPage <= 1"
                                            class="px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        ‹ Sebelumnya
                                    </button>
                                    <button type="button" @click="importPreviewPage = Math.min(getTotalPreviewPages(), importPreviewPage + 1)" :disabled="importPreviewPage >= getTotalPreviewPages()"
                                            class="px-2.5 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        Berikutnya ›
                                    </button>
                                    <button type="button" @click="importPreviewPage = getTotalPreviewPages()" :disabled="importPreviewPage >= getTotalPreviewPages()"
                                            class="px-2 py-1 rounded bg-white border border-slate-200 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-[11px] cursor-pointer">
                                        Akhir »
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- STEP 4: PROCESSING (IMPORT RUNNING)           -->
                    <!-- ============================================== -->
                    <div x-show="importStep === 'processing'" style="display: none;" class="py-12 flex flex-col items-center justify-center text-center space-y-4">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full border-4 border-emerald-100 border-t-emerald-600 animate-spin"></div>
                            <span class="material-symbols-rounded absolute inset-0 m-auto w-6 h-6 text-emerald-600 text-[24px]">sync</span>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-800">Memproses Data Master Produk...</h4>
                            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                                Mohon tunggu sebentar. Sistem sedang menyimpan data ke database, mengaitkan kategori, dan menyinkronkan multi-satuan unit konversi secara bertahap.
                            </p>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- STEP 5: RESULT / SELESAI                       -->
                    <!-- ============================================== -->
                    <div x-show="importStep === 'result'" style="display: none;" class="py-6 space-y-6 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-xs">
                            <span class="material-symbols-rounded text-[36px]">task_alt</span>
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-slate-800">Import Master Produk Berhasil Selesai!</h4>
                            <p class="text-xs text-slate-500 mt-1" x-text="importResult?.message || 'Data master produk telah tersimpan ke dalam database aplikasi.'"></p>
                        </div>

                        <!-- Result Counters -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl mx-auto text-left">
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Diproses</span>
                                <div class="text-xl font-black text-slate-800 font-mono mt-0.5" x-text="importResult?.summary?.total_processed || 0"></div>
                            </div>
                            <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-200">
                                <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Produk Baru Dibuat</span>
                                <div class="text-xl font-black text-emerald-700 font-mono mt-0.5" x-text="importResult?.summary?.created || 0"></div>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200">
                                <span class="text-[10px] font-extrabold text-[#0052cc] uppercase tracking-wider block">Produk Diperbarui</span>
                                <div class="text-xl font-black text-[#0052cc] font-mono mt-0.5" x-text="importResult?.summary?.updated || 0"></div>
                            </div>
                            <div class="bg-amber-50 p-4 rounded-xl border border-amber-200">
                                <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider block">Dilewati (Skip)</span>
                                <div class="text-xl font-black text-amber-700 font-mono mt-0.5" x-text="importResult?.summary?.skipped || 0"></div>
                            </div>
                        </div>

                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 max-w-xl mx-auto">
                            Tabel produk di halaman ini telah diperbarui secara otomatis dengan data terbaru.
                        </div>
                    </div>

                </div>

                <!-- Modal Footer Actions -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                    
                    <!-- Footer: Step 1 (Upload) -->
                    <template x-if="importStep === 'upload'">
                        <div class="w-full flex items-center justify-between">
                            <button type="button" 
                                    @click="showImportModal = false" 
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                Batal
                            </button>
                            <button type="button" 
                                    @click="uploadAndValidateFile()" 
                                    :disabled="!importFile || isUploading"
                                    class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span x-show="isUploading" class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                                <span class="material-symbols-rounded text-[18px]" x-show="!isUploading">fact_check</span>
                                <span x-text="isUploading ? 'Memvalidasi 19 Kolom...' : 'Periksa & Validasi File'"></span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 2 (Invalid Structure) -->
                    <template x-if="importStep === 'invalid_structure'">
                        <div class="w-full flex items-center justify-between">
                            <button type="button" 
                                    @click="downloadILPTemplate()" 
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-[#0052cc] hover:bg-blue-50 border border-blue-200 transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">download</span>
                                <span>Unduh Format Template Resmi ILP</span>
                            </button>
                            <button type="button" 
                                    @click="importStep = 'upload'; resetFileInput();" 
                                    class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5">
                                <span class="material-symbols-rounded text-[16px]">replay</span>
                                <span>Unggah Ulang File Lain</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 3 (Preview) -->
                    <template x-if="importStep === 'preview'">
                        <div class="w-full flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button type="button" 
                                        @click="showImportModal = false" 
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Batal
                                </button>
                                <button type="button" 
                                        @click="importStep = 'upload'; resetFileInput();" 
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-200 transition-colors cursor-pointer">
                                    Ganti File
                                </button>
                            </div>
                            <button type="button" 
                                    @click="confirmExecuteImport()" 
                                    :disabled="!validationData || (validationData?.summary?.valid_rows || 0) === 0"
                                    class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">cloud_done</span>
                                <span>Konfirmasi & Mulai Import (<span x-text="validationData?.summary?.valid_rows || 0"></span> Produk)</span>
                            </button>
                        </div>
                    </template>

                    <!-- Footer: Step 4 (Processing) -->
                    <template x-if="importStep === 'processing'">
                        <div class="w-full text-center text-xs text-slate-500 font-medium">
                            Harap jangan menutup browser selama proses import berlangsung...
                        </div>
                    </template>

                    <!-- Footer: Step 5 (Result) -->
                    <template x-if="importStep === 'result'">
                        <div class="w-full flex justify-end">
                            <button type="button" 
                                    @click="showImportModal = false" 
                                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors cursor-pointer inline-flex items-center gap-1.5 shadow-xs">
                                <span class="material-symbols-rounded text-[16px]">check</span>
                                <span>Selesai & Tutup</span>
                            </button>
                        </div>
                    </template>

                </div>

            </div>
        </div>
    </template>

</div>

<script>
window.formatReturnDateHelper = function(dateStr) {
    if (!dateStr || typeof dateStr !== 'string') return { date: '-', time: '-' };
    var raw = dateStr.trim();
    if (!raw) return { date: '-', time: '-' };

    var monthMap = {
        'januari': 'Jan', 'jan': 'Jan', 'februari': 'Feb', 'feb': 'Feb',
        'maret': 'Mar', 'mar': 'Mar', 'april': 'Apr', 'apr': 'Apr',
        'mei': 'Mei', 'juni': 'Jun', 'jun': 'Jun',
        'juli': 'Jul', 'jul': 'Jul', 'agustus': 'Agu', 'agu': 'Agu', 'agus': 'Agu',
        'september': 'Sep', 'sep': 'Sep', 'sept': 'Sep',
        'oktober': 'Okt', 'okt': 'Okt', 'november': 'Nov', 'nov': 'Nov',
        'desember': 'Des', 'des': 'Des'
    };

    var datePart = '';
    var timePart = '';

    // 1. Cek pemisah eksplisit bullet • atau pipe |
    if (raw.indexOf('•') !== -1) {
        var parts = raw.split('•');
        datePart = parts[0].trim();
        timePart = (parts[1] || '').trim();
    } else if (raw.indexOf('|') !== -1) {
        var parts = raw.split('|');
        datePart = parts[0].trim();
        timePart = (parts[1] || '').trim();
    } else {
        // 2. Ekstrak waktu via regex dari ujung string: HH:mm atau HH.mm opsional detik & timezone
        var timeMatch = raw.match(/(\d{1,2}[:.]\d{2}(?::\d{2})?(?:\s*(?:WIB|WITA|WIT|AM|PM|am|pm))?)$/i);
        if (timeMatch) {
            timePart = timeMatch[1].trim();
            datePart = raw.substring(0, timeMatch.index).trim();
            datePart = datePart.replace(/[-,\s]+$/, '').trim();
        } else {
            datePart = raw;
            timePart = '-';
        }
    }

    // Normalisasi timePart
    if (timePart && timePart !== '-') {
        var cleanTime = timePart.replace(/\./g, ':');
        var tm = cleanTime.match(/(\d{1,2}):(\d{2})/);
        if (tm) {
            var hh = tm[1].length < 2 ? '0' + tm[1] : tm[1];
            var mm = tm[2];
            var tzMatch = cleanTime.match(/WIB|WITA|WIT/i);
            var tz = tzMatch ? tzMatch[0].toUpperCase() : 'WIB';
            timePart = hh + ':' + mm + ' ' + tz;
        }
    } else {
        timePart = '-';
    }

    // Normalisasi datePart
    if (datePart && datePart !== '-') {
        // Format ISO: YYYY-MM-DD
        var isoMatch = datePart.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
        if (isoMatch) {
            var y = isoMatch[1];
            var m = parseInt(isoMatch[2], 10);
            var d = parseInt(isoMatch[3], 10);
            var idMonths = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            datePart = d + ' ' + (idMonths[m] || m) + ' ' + y;
        } else {
            // Format Slash: DD/MM/YYYY
            var slashMatch = datePart.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
            if (slashMatch) {
                var d = parseInt(slashMatch[1], 10);
                var m = parseInt(slashMatch[2], 10);
                var y = slashMatch[3];
                var idMonths = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                datePart = d + ' ' + (idMonths[m] || m) + ' ' + y;
            } else {
                // Standarkan nama bulan Indonesia
                for (var mKey in monthMap) {
                    var reg = new RegExp('\\b' + mKey + '\\b', 'gi');
                    datePart = datePart.replace(reg, monthMap[mKey]);
                }
            }
        }
    } else {
        datePart = '-';
    }

    return { date: datePart || '-', time: timePart || '-' };
};
</script>
@endsection
