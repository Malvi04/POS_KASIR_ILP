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
                if (Array.isArray(items) && items.length > 0) {
                    const mapped = items.map(p => ({
                        id: p.id,
                        sku: p.sku,
                        barcode: p.barcode,
                        name: p.name,
                        brand: p.supplier_name || 'Lokal',
                        category: p.category || 'Umum',
                        rackLocation: p.rack_location || 'Lorong Toko',
                        stock: p.stock ?? 0,
                        minStock: p.min_stock ?? 10,
                        unit: p.unit || 'Pcs',
                        price: p.price ?? p.original_price ?? 0,
                        memberPrice: p.member_price ?? p.original_member_price ?? p.price ?? 0,
                        status: p.stock_status || this.calculateStatus(p.stock, p.min_stock, p.expired_date),
                        batchLot: 'LOT-' + p.sku,
                        expiredDate: p.expired_date || ''
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

        try {
            const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    const cleaned = parsed.map(p => {
                        let u = String(p.unit || 'Pcs').trim();
                        if (u.toLowerCase().includes('pouch') || u.toLowerCase().includes('tetra') || u.toLowerCase().includes('puch')) {
                            p.unit = 'Pcs';
                        }
                        return p;
                    });
                    const result = this.deduplicateProducts(cleaned);
                    this.products = result.unique;
                    if (result.hasDuplicates) {
                        this.saveProductsToStorage();
                    }
                    return;
                }
            }
        } catch (e) {
            console.error('Failed to parse inventory storage:', e);
        }
        this.products = [];
    },

    saveProductsToStorage() {
        try {
            const sliceToSave = this.products.length > 200 ? this.products.slice(0, 200) : this.products;
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(sliceToSave));
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
                if (Array.isArray(items) && items.length > 0) {
                    const mapped = items.map(p => ({
                        id: p.id,
                        sku: p.sku,
                        barcode: p.barcode,
                        name: p.name,
                        brand: p.brand || p.category,
                        category: p.category || 'Kebutuhan Pokok',
                        stock: Number(p.stock) || 0,
                        minStock: Number(p.min_stock) || 10,
                        unit: p.unit || 'Pcs',
                        price: Number(p.price) || 0,
                        memberPrice: Number(p.member_price || p.price) || 0,
                        rackLocation: p.rack_location || '-',
                        status: Number(p.stock) <= 0 ? 'HABIS' : (Number(p.stock) <= Number(p.min_stock || 10) ? 'STOK_RENDAH' : 'TERSEDIA'),
                        isConsignment: Boolean(p.is_consignment),
                        supplier: p.supplier || null,
                        expiredDate: p.expired_date || null
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
    duplicateExistingProduct: null,
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
        this.duplicateExistingProduct = null;

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
        this.duplicateExistingProduct = null;

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

    async saveProduct() {
        this.formErrors = {};
        this.duplicateExistingProduct = null;
        const f = this.formData;

        const cleanSku = (f.sku || '').trim().toUpperCase();
        const cleanBarcode = (f.barcode || '').trim();
        const cleanName = (f.name || '').trim();

        if (!cleanName) {
            this.formErrors.name = 'Nama produk wajib diisi.';
        }

        // 1. Periksa SKU terlebih dahulu
        if (!cleanSku) {
            this.formErrors.sku = 'SKU produk wajib diisi.';
        } else {
            const dupSku = this.products.find(p => 
                (p.sku || '').trim().toUpperCase() === cleanSku && 
                (this.modalMode === 'create' || (p.sku || '').trim().toUpperCase() !== (this.formSkuOriginal || '').trim().toUpperCase())
            );
            if (dupSku) {
                this.formErrors.sku = 'SKU sudah terdaftar. Silakan gunakan data produk yang sudah ada.';
                this.duplicateExistingProduct = dupSku;
            }
        }

        // 2. Periksa barcode jika diisi
        if (cleanBarcode) {
            const dupBarcode = this.products.find(p => 
                (p.barcode || '').trim() === cleanBarcode && 
                (this.modalMode === 'create' || (p.sku || '').trim().toUpperCase() !== (this.formSkuOriginal || '').trim().toUpperCase())
            );
            if (dupBarcode) {
                this.formErrors.barcode = 'Barcode sudah digunakan oleh produk lain.';
                if (!this.duplicateExistingProduct) {
                    this.duplicateExistingProduct = dupBarcode;
                }
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
            try {
                const res = await fetch('/api/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        sku: payload.sku,
                        barcode: payload.barcode,
                        name: payload.name,
                        brand: payload.brand,
                        category: payload.category || 'Kebutuhan Pokok',
                        unit: payload.unit || 'Pcs',
                        cost_price: Math.round(Number(payload.price) * 0.75),
                        price: Number(payload.price),
                        member_price: Number(payload.memberPrice || payload.price),
                        stock: Number(payload.stock),
                        min_stock: Number(payload.minStock),
                        rack_location: payload.rackLocation || 'Lorong A - Rak 01'
                    })
                });
                if (res.ok) {
                    const json = await res.json();
                    if (json.data && json.data.id) {
                        payload.id = json.data.id;
                    }
                }
            } catch (err) {
                console.warn('API /api/products create error, storing locally', err);
            }

            this.products.unshift(payload);
            this.saveProductsToStorage();
            this.showProductModal = false;
            this.triggerToast(`Produk '${payload.name}' berhasil ditambahkan ke katalog stok.`, 'success');
        } else {
            const idx = this.products.findIndex(p => p.sku === this.formSkuOriginal);
            if (idx !== -1) {
                const existingProduct = this.products[idx];
                if (existingProduct.id) {
                    try {
                        await fetch(`/api/products/${existingProduct.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                name: payload.name,
                                sku: payload.sku,
                                barcode: payload.barcode,
                                category: payload.category,
                                unit: payload.unit,
                                cost_price: Math.round(Number(payload.price) * 0.75),
                                price: Number(payload.price),
                                member_price: Number(payload.memberPrice || payload.price),
                                stock: Number(payload.stock),
                                min_stock: Number(payload.minStock),
                                rack_location: payload.rackLocation
                            })
                        });
                    } catch (err) {
                        console.warn('API /api/products update error, saving locally', err);
                    }
                }
                payload.id = existingProduct.id;
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

    async confirmDelete() {
        if (!this.productToDelete) return;
        if (this.deleteConfirmInput.trim().toLowerCase() !== 'hapus') {
            return;
        }

        const name = this.productToDelete.name;
        const targetSku = this.productToDelete.sku;

        if (this.productToDelete.id) {
            try {
                await fetch(`/api/products/${this.productToDelete.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
            } catch (err) {
                console.warn('API /api/products delete error, removing locally', err);
            }
        }

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
        inspector: 'Hendra W. (QC Gudang Pusat)'
    },

    initReturnStock() {
        // Cek database Barang Masuk Retur (pos_ilp_manager_retur_masuk_v1)
        const rawInDocs = localStorage.getItem('pos_ilp_manager_retur_masuk_v1');
        let inDocs = null;
        if (rawInDocs) {
            try { inDocs = JSON.parse(rawInDocs); } catch(e) {}
        }

        const stored = localStorage.getItem('pos_ilp_stok_retur_gudang_v1');
        let rawItems = [];
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    rawItems = parsed;
                }
            } catch(e) {
                console.error('Failed to parse return stock:', e);
            }
        }
        if (rawItems.length === 0) {
            rawItems = this.getDefaultReturnStock();
        }

        // ATURAN MUTLAK BISNIS RETUR GUDANG KOPKAR:
        // 1. Barang retur HANYA masuk ke stok inventaris jika sudah DISETUJUI / DITERIMA di halaman Barang Masuk Retur!
        // 2. Barang yang TIDAK DISETUJUI (DITOLAK / MENUNGGU PENGEMBALIAN) TIDAK MASUK STOK APAPUN!
        // 3. Barang yang masih MENUNGGU VERIFIKASI belum boleh masuk stok aktif sampai disetujui.
        const seenIds = new Set();
        const cleaned = [];
        rawItems.forEach(i => {
            if (!i) return;

            // Jika item merujuk ke dokumen di Barang Masuk Retur:
            if (inDocs && Array.isArray(inDocs)) {
                const matchedDoc = inDocs.find(d => d.docNo === i.docRef);
                if (matchedDoc) {
                    // Jika dokumen ditolak (MENUNGGU_PENGEMBALIAN atau DITOLAK), JANGAN MASUKKAN KE STOK!
                    if (matchedDoc.status === 'MENUNGGU_PENGEMBALIAN' || matchedDoc.status === 'DITOLAK') {
                        return;
                    }
                    // Jika dokumen masih menunggu verifikasi, BELUM MASUK STOK sampai disetujui!
                    if (matchedDoc.status === 'MENUNGGU_VERIFIKASI') {
                        return;
                    }
                    // Jika dokumen diterima sebagian, pastikan item yang qtyAccepted == 0 tidak masuk stok
                    if (matchedDoc.status === 'DITERIMA_SEBAGIAN' && Array.isArray(matchedDoc.items)) {
                        const matchedItem = matchedDoc.items.find(it => it.sku === i.sku);
                        if (matchedItem && (parseInt(matchedItem.qtyAccepted) || 0) <= 0) {
                            return;
                        }
                    }
                }
            }

            // Cegah dokumen yang ditolak historis masuk ke stok
            if (i.docRef === '#RTR-TK-2024-0892' || i.docRef === '#RTR-TK-2024-0890') {
                return;
            }

            const id = i.id || ('RET-' + (i.sku || 'SKU') + '-' + (i.docRef || 'DOC'));
            if (!seenIds.has(id)) {
                seenIds.add(id);
                i.id = id;
                i.qtyReceived = Math.max(0, parseInt(i.qtyReceived) || 0);
                i.unit = 'Pcs';
                cleaned.push(i);
            }
        });

        // Sinkronisasi dokumen yang SUDAH DISETUJUI dari Barang Masuk Retur
        if (inDocs && Array.isArray(inDocs)) {
            inDocs.forEach(d => {
                if (d.status === 'DITERIMA_LENGKAP' || d.status === 'DITERIMA_SEBAGIAN') {
                    if (Array.isArray(d.items)) {
                        d.items.forEach(it => {
                            const acceptedQty = parseInt(it.qtyAccepted, 10) || 0;
                            if (acceptedQty > 0) {
                                const existingIndex = cleaned.findIndex(c => c.docRef === d.docNo && c.sku === it.sku);
                                if (existingIndex >= 0) {
                                    cleaned[existingIndex].qtyReceived = acceptedQty;
                                } else {
                                    cleaned.unshift({
                                        id: 'RET-GD-' + (it.sku || 'SKU') + '-' + (d.docNo.replace(/[^a-zA-Z0-9]/g, '')),
                                        sku: it.sku || 'PRD-RET',
                                        barcode: it.sku || '899000000',
                                        name: it.name,
                                        branchOrigin: d.warehouseOrigin || 'Cabang',
                                        docRef: d.docNo,
                                        receivedDate: d.receivedAt || d.date || 'Hari ini',
                                        qtyReceived: acceptedQty,
                                        unit: 'Pcs',
                                        initialCondition: it.condition || 'Barang Retur Diterima',
                                        inspectionStatus: 'QC Selesai',
                                        followUp: 'Karantina Gudang / Klaim Supplier',
                                        inspectorNotes: `Diterima dari ${d.warehouseOrigin} via dokumen ${d.docNo}. Disetujui masuk stok retur. ${it.note || ''}`,
                                        inspectedBy: d.receiverName || 'Budi Santoso (Manager Operasional)',
                                        updatedAt: d.receivedAt || new Date().toLocaleString('id-ID')
                                    });
                                }
                            }
                        });
                    }
                }
            });
        }

        this.returnStockItems = cleaned;
        this.saveReturnStock();
    },

    saveReturnStock() {
        try {
            localStorage.setItem('pos_ilp_stok_retur_gudang_v1', JSON.stringify(this.returnStockItems));
        } catch(e) {
            console.error('Failed to save return stock:', e);
        }
    },

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
        try {
            const raw = localStorage.getItem('pos_ilp_manager_retur_masuk_v1');
            if (raw) {
                const docs = JSON.parse(raw);
                return docs.reduce((acc, d) => acc + (parseInt(d.sisaBarangDitolakDiGudang) || 0), 0);
            }
        } catch(e) {}
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
            inspector: item.inspectedBy || 'Hendra W. (DC Head)'
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
        try {
            const rawLogs = localStorage.getItem('pos_ilp_audit_logs_v1');
            const logs = rawLogs ? JSON.parse(rawLogs) : [];
            const newLog = {
                id: 'AUD-' + Date.now(),
                timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
                actor_id: 'USR-MGR-001',
                actor_name: 'Budi Santoso',
                actor_role: 'Manager',
                action_category: 'INVENTORY',
                action_type: 'UPDATE_RETURN_INSPECTION',
                reference_number: item.docRef || item.id,
                location: 'Gudang Pusat',
                description: `Pemeriksaan stok retur ${item.sku} - ${item.name} (${item.qtyReceived} ${item.unit}) diubah dari '${oldStatus}' ke '${newStatus}'.`,
                previous_state: JSON.stringify({ status: oldStatus }),
                new_state: JSON.stringify({ status: newStatus, followUp: item.followUp, notes: item.inspectorNotes }),
                status: 'SUCCESS'
            };
            logs.unshift(newLog);
            localStorage.setItem('pos_ilp_audit_logs_v1', JSON.stringify(logs.slice(0, 300)));
        } catch(e) {
            console.error('Audit log failed:', e);
        }
    }
}"
x-init="initOpening(); initProducts(); initReturnStock();"
@pos-inventory-synced.window="initProducts()"
@pos-return-stock-synced.window="initReturnStock()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Manager Sidebar -->
    <x-manager-sidebar active="inventory" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

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
                            @click="inventoryTab = 'normal'"
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
                            :class="inventoryTab === 'retur' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                            class="px-4 py-2.5 rounded-xl text-[12.5px] font-extrabold flex items-center gap-2.5 transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">assignment_return</span>
                        <span>Inventaris Stok Barang Retur</span>
                        <span :class="inventoryTab === 'retur' ? 'bg-white text-[#0052cc]' : 'bg-amber-100 text-amber-800'"
                              class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                              x-text="uniqueSkuCount + ' SKU Retur'"></span>
                    </button>
                </div>

                <!-- Right Action / Shortcut Button -->
                <div class="flex items-center gap-2.5">
                    <button type="button"
                            x-show="inventoryTab === 'normal'"
                            @click="openCreateModal()"
                            class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 py-2 rounded-xl text-[12px] font-extrabold flex items-center gap-2 shadow-xs transition-all cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">add</span>
                        <span>Tambah Produk Baru</span>
                    </button>
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
            <!-- TAB 1: MASTER NORMAL (MASTER PRODUK & STOK) -->
            <!-- ========================================== -->
            <div x-show="inventoryTab === 'normal'" class="space-y-6">

                <!-- Filter & Search Bar with Add Product Button -->
                <div class="flex flex-col md:flex-row items-center gap-4">
                    <div class="relative flex-[2] w-full">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               @input="onSearchInput()"
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
                                    <div class="mt-1">
                                        <p class="text-rose-500 text-[11px] font-semibold" x-text="formErrors.barcode"></p>
                                        <template x-if="duplicateExistingProduct">
                                            <button type="button" @click="openEditModal(duplicateExistingProduct)" class="text-[11px] text-[#0052cc] hover:underline font-bold mt-1 inline-flex items-center gap-1 cursor-pointer">
                                                <span class="material-symbols-rounded text-[14px]">edit</span>
                                                <span>Buka dan edit data produk ini</span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Kode SKU Toko <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formData.sku"
                                       placeholder="PRD-001"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-bold text-xs uppercase">
                                <template x-if="formErrors.sku">
                                    <div class="mt-1">
                                        <p class="text-rose-500 text-[11px] font-semibold" x-text="formErrors.sku"></p>
                                        <template x-if="duplicateExistingProduct">
                                            <button type="button" @click="openEditModal(duplicateExistingProduct)" class="text-[11px] text-[#0052cc] hover:underline font-bold mt-1 inline-flex items-center gap-1 cursor-pointer">
                                                <span class="material-symbols-rounded text-[14px]">edit</span>
                                                <span>Buka dan edit data produk ini</span>
                                            </button>
                                        </template>
                                    </div>
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
                                    <option value="Karton">Karton</option>
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
