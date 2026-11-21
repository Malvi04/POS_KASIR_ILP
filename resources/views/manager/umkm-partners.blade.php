@extends('layouts.app')

@section('title', 'Manajemen Konsinyasi & Mitra UMKM - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'partners', // 'partners' | 'products' | 'report'
    
    // Partners State
    partners: [],
    partnerSummary: {
        total_partners: 0,
        active_partners: 0,
        total_consignment_skus: 0,
        average_commission: 15
    },
    searchPartner: '',
    statusPartner: 'all',
    isLoadingPartners: false,

    // UMKM Products State
    umkmProducts: [],
    productSummary: {
        total_products: 0,
        total_warehouse_stock: 0,
        total_branch_stock: 0,
        total_stock: 0,
        total_cost_value: 0,
        total_retail_value: 0
    },
    stores: [],
    searchProduct: '',
    filterProductPartnerId: 'all',
    isLoadingProducts: false,
    isSavingProduct: false,

    // Product Create / Edit Modal
    showProductModal: false,
    productModalMode: 'create', // 'create' | 'edit'
    productForm: {
        id: null,
        supplier_id: '',
        name: '',
        sku: '',
        barcode: '',
        category: 'Makanan Ringan',
        unit: 'Pcs',
        cost_price: 0,
        price: 0,
        member_price: 0,
        commission_percent: 15,
        stock: 20,
        min_stock: 5,
        expired_date: '',
        rack_location: 'Rak Mitra UMKM',
        target_location: 'GUDANG_PUSAT'
    },
    productFormErrors: {},

    // Restock / Tambah Stok Beli dari Mitra Modal
    showRestockModal: false,
    isRestocking: false,
    restockTargetProduct: null,
    restockForm: {
        qty: 10,
        cost_price: 0,
        price: 0,
        expired_date: '',
        target_location: 'GUDANG_PUSAT',
        notes: ''
    },

    // Report State
    month: '{{ date('Y-m') }}',
    selectedSupplierId: 'all',
    reportSummary: {
        total_gross_sales: 0,
        total_coop_commission: 0,
        total_umkm_net_share: 0,
        total_sold_units: 0,
        active_consignment_skus: 0
    },
    reportRows: [],
    isLoadingReport: false,

    // Partner Modal
    showPartnerModal: false,
    partnerModalMode: 'create', // 'create' | 'edit'
    partnerFormData: {
        id: null,
        name: '',
        owner_name: '',
        phone: '',
        address: '',
        category: 'Makanan Ringan',
        commission_percent: 15.00,
        settlement_period: 'BULANAN',
        bank_name: 'BRI',
        bank_account: '',
        status: 'AKTIF',
        notes: ''
    },
    partnerFormErrors: {},

    showSettlementModal: false,
    settlementSupplier: null,
    settlementNotes: '',
    isSettling: false,

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    async initUmkm() {
        await Promise.all([
            this.loadPartners(),
            this.loadUmkmProducts(),
            this.loadReport()
        ]);
    },

    async loadPartners() {
        this.isLoadingPartners = true;
        try {
            const params = new URLSearchParams({
                search: this.searchPartner.trim(),
                status: this.statusPartner
            });
            const res = await fetch(`/api/consignment/partners?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.partners = json.data;
                    this.partnerSummary = json.summary;
                }
            }
        } catch (e) {
            console.error('Error fetching partners:', e);
            this.triggerToast('Gagal memuat mitra UMKM.', 'error');
        } finally {
            this.isLoadingPartners = false;
        }
    },

    async loadUmkmProducts() {
        this.isLoadingProducts = true;
        try {
            const params = new URLSearchParams({
                search: this.searchProduct.trim(),
                supplier_id: this.filterProductPartnerId
            });
            const res = await fetch(`/api/consignment/products?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.umkmProducts = json.data || [];
                    if (json.summary) this.productSummary = json.summary;
                    if (Array.isArray(json.stores)) this.stores = json.stores;
                    if (this.partnerSummary) {
                        this.partnerSummary.total_consignment_skus = this.productSummary.total_products || this.umkmProducts.length;
                    }
                }
            }
        } catch (e) {
            console.error('Error fetching UMKM products:', e);
            this.triggerToast('Gagal memuat daftar produk UMKM.', 'error');
        } finally {
            this.isLoadingProducts = false;
        }
    },

    async loadReport() {
        this.isLoadingReport = true;
        try {
            const params = new URLSearchParams({
                month: this.month,
                supplier_id: this.selectedSupplierId
            });
            const res = await fetch(`/api/consignment/report?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.reportSummary = json.summary;
                    this.reportRows = json.data;
                }
            }
        } catch (e) {
            console.error('Error fetching consignment report:', e);
            this.triggerToast('Gagal memuat laporan bagi hasil konsinyasi.', 'error');
        } finally {
            this.isLoadingReport = false;
        }
    },

    openCreatePartner() {
        this.partnerModalMode = 'create';
        this.partnerFormErrors = {};
        this.partnerFormData = {
            id: null,
            name: '',
            owner_name: '',
            phone: '',
            address: '',
            category: 'Makanan Ringan',
            commission_percent: 15.00,
            settlement_period: 'MINGGUAN',
            bank_name: 'BRI',
            bank_account: '',
            status: 'AKTIF',
            notes: 'Mitra UMKM Titipan Baru'
        };
        this.showPartnerModal = true;
    },

    openEditPartner(p) {
        this.partnerModalMode = 'edit';
        this.partnerFormErrors = {};
        this.partnerFormData = {
            id: p.id,
            name: p.name,
            owner_name: p.owner_name || '',
            phone: p.phone || '',
            address: p.address || '',
            category: p.category || 'Makanan Ringan',
            commission_percent: p.commission_percent,
            settlement_period: p.settlement_period || 'BULANAN',
            bank_name: p.bank_name || '',
            bank_account: p.bank_account || '',
            status: p.status,
            notes: p.notes || ''
        };
        this.showPartnerModal = true;
    },

    async savePartner() {
        if (this.isLoadingPartners) return;
        this.partnerFormErrors = {};
        if (!this.partnerFormData.name || !this.partnerFormData.name.trim()) this.partnerFormErrors.name = 'Nama UMKM / Usaha wajib diisi.';
        if (Object.keys(this.partnerFormErrors).length > 0) return;

        this.isLoadingPartners = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('umkm-' + Date.now());
        try {
            const url = this.partnerModalMode === 'create' ? '/api/consignment/partners' : `/api/consignment/partners/${this.partnerFormData.id}`;
            const method = this.partnerModalMode === 'create' ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify(this.partnerFormData)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showPartnerModal = false;
                this.triggerToast(json.message);
                await this.loadPartners();
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan mitra UMKM.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus.', 'error');
        } finally {
            this.isLoadingPartners = false;
        }
    },

    async deletePartner(p) {
        if (!confirm(`Hapus mitra UMKM [${p.name}]?`)) return;
        try {
            const res = await fetch(`/api/consignment/partners/${p.id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(json.message);
                await this.loadPartners();
            }
        } catch (e) {
            this.triggerToast('Gagal menghapus.', 'error');
        }
    },

    // =====================================================
    // UMKM PRODUCT CRUD & STOCK METHODS
    // =====================================================
    viewPartnerProducts(partner) {
        this.filterProductPartnerId = String(partner.id);
        this.activeTab = 'products';
        this.loadUmkmProducts();
    },

    openCreateProductModal(preselectedPartner = null) {
        if (this.partners.length === 0) {
            this.triggerToast('Silakan tambahkan Mitra UMKM terlebih dahulu sebelum menambah produk.', 'error');
            this.openCreatePartner();
            return;
        }

        this.productModalMode = 'create';
        this.productFormErrors = {};
        const partner = preselectedPartner || this.partners[0];
        const nextNum = (this.umkmProducts.length || 0) + 1;
        const nextSku = 'UMKM-' + String(nextNum).padStart(3, '0') + '-' + Math.floor(10 + Math.random() * 89);
        const randomBarcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);

        const defaultExp = new Date();
        defaultExp.setMonth(defaultExp.getMonth() + 6);

        const commPct = Number(partner?.commission_percent ?? 15);
        const defaultBuyPrice = 10000;
        const defaultSellPrice = Math.round(defaultBuyPrice * (1 + commPct / 100));

        this.productForm = {
            id: null,
            supplier_id: partner ? partner.id : '',
            name: '',
            sku: nextSku,
            barcode: randomBarcode,
            category: partner?.category || 'Makanan Ringan',
            unit: 'Pcs',
            cost_price: defaultBuyPrice,
            price: defaultSellPrice,
            member_price: defaultSellPrice,
            commission_percent: commPct,
            stock: 25,
            min_stock: 5,
            expired_date: defaultExp.toISOString().split('T')[0],
            rack_location: 'Rak Mitra UMKM',
            target_location: 'GUDANG_PUSAT'
        };
        this.showProductModal = true;
    },

    openEditProductModal(prod) {
        this.productModalMode = 'edit';
        this.productFormErrors = {};
        this.productForm = {
            id: prod.id,
            supplier_id: prod.supplier_id,
            name: prod.name,
            sku: prod.sku,
            barcode: prod.barcode,
            category: prod.category || 'Makanan Ringan',
            unit: prod.unit || 'Pcs',
            cost_price: Number(prod.cost_price) || 0,
            price: Number(prod.price) || 0,
            member_price: Number(prod.member_price || prod.price) || 0,
            commission_percent: Number(prod.commission_percent) || 15,
            stock: Number(prod.warehouse_stock ?? prod.stock) || 0,
            min_stock: Number(prod.min_stock) || 5,
            expired_date: prod.expired_date || '',
            rack_location: prod.rack_location || 'Rak Mitra UMKM',
            target_location: 'GUDANG_PUSAT'
        };
        this.showProductModal = true;
    },

    onProductPartnerChange() {
        const p = this.partners.find(it => String(it.id) === String(this.productForm.supplier_id));
        if (p) {
            if (p.category) this.productForm.category = p.category;
            this.productForm.commission_percent = Number(p.commission_percent) || 15;
            this.syncSellPriceFromCommission();
        }
    },

    syncSellPriceFromCommission() {
        const buy = Number(this.productForm.cost_price) || 0;
        const comm = Math.max(0, Number(this.productForm.commission_percent) || 0);
        if (buy > 0) {
            const oldSell = Number(this.productForm.price) || 0;
            const newSell = Math.round(buy * (1 + (comm / 100)));
            this.productForm.price = newSell;
            if (!this.productForm.member_price || Number(this.productForm.member_price) === 0 || Number(this.productForm.member_price) === oldSell) {
                this.productForm.member_price = newSell;
            }
        }
    },

    syncCommissionFromSellPrice() {
        const sell = Number(this.productForm.price) || 0;
        const buy = Number(this.productForm.cost_price) || 0;
        if (buy > 0 && sell >= buy) {
            this.productForm.commission_percent = Number((((sell - buy) / buy) * 100).toFixed(1));
        }
        if (!this.productForm.member_price || Number(this.productForm.member_price) === 0) {
            this.productForm.member_price = sell;
        }
    },

    generateNewBarcode() {
        this.productForm.barcode = '899' + Math.floor(1000000000 + Math.random() * 9000000000);
    },

    async saveUmkmProduct() {
        if (this.isSavingProduct) return;
        this.productFormErrors = {};

        if (!this.productForm.supplier_id) {
            this.productFormErrors.supplier_id = 'Mitra UMKM wajib dipilih.';
        }
        if (!this.productForm.name || !this.productForm.name.trim()) {
            this.productFormErrors.name = 'Nama produk wajib diisi.';
        }
        if (Number(this.productForm.price) <= 0) {
            this.productFormErrors.price = 'Harga jual toko harus lebih dari 0.';
        }
        if (Number(this.productForm.cost_price) < 0) {
            this.productFormErrors.cost_price = 'Harga beli dari mitra tidak boleh negatif.';
        }
        if (Object.keys(this.productFormErrors).length > 0) return;

        this.isSavingProduct = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('umkm-prd-' + Date.now());
        try {
            const isCreate = this.productModalMode === 'create';
            const url = isCreate ? '/api/consignment/products' : `/api/consignment/products/${this.productForm.id}`;
            const method = isCreate ? 'POST' : 'PUT';

            const res = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify(this.productForm)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showProductModal = false;
                this.triggerToast(json.message);
                await Promise.all([
                    this.loadUmkmProducts(),
                    this.loadPartners(),
                    this.loadReport()
                ]);
                window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
            } else {
                this.triggerToast(json.message || 'Gagal menyimpan produk UMKM.', 'error');
            }
        } catch (e) {
            this.triggerToast('Terjadi kesalahan koneksi saat menyimpan produk UMKM.', 'error');
        } finally {
            this.isSavingProduct = false;
        }
    },

    openRestockModal(prod) {
        this.restockTargetProduct = prod;
        this.restockForm = {
            qty: 20,
            cost_price: Number(prod.cost_price) || 0,
            price: Number(prod.price) || 0,
            expired_date: prod.expired_date || '',
            target_location: 'GUDANG_PUSAT',
            notes: `Pembelian / pasokan stok baru dari ${prod.supplier_name}`
        };
        this.showRestockModal = true;
    },

    async submitRestock() {
        if (this.isRestocking || !this.restockTargetProduct) return;
        if (Number(this.restockForm.qty) <= 0) {
            this.triggerToast('Jumlah stok masuk harus lebih dari 0.', 'error');
            return;
        }

        this.isRestocking = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('umkm-rst-' + Date.now());
        try {
            const res = await fetch(`/api/consignment/products/${this.restockTargetProduct.id}/restock`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify(this.restockForm)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showRestockModal = false;
                this.triggerToast(json.message);
                await this.loadUmkmProducts();
                window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
            } else {
                this.triggerToast(json.message || 'Gagal menambah stok produk UMKM.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi terputus saat menambah stok.', 'error');
        } finally {
            this.isRestocking = false;
        }
    },

    async deleteUmkmProduct(prod) {
        if (!confirm(`Hapus produk UMKM [${prod.name}] (${prod.sku})?`)) return;
        try {
            const res = await fetch(`/api/consignment/products/${prod.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(json.message);
                await Promise.all([
                    this.loadUmkmProducts(),
                    this.loadPartners(),
                    this.loadReport()
                ]);
            } else {
                this.triggerToast(json.message || 'Gagal menghapus produk UMKM.', 'error');
            }
        } catch (e) {
            this.triggerToast('Gagal menghubungi server.', 'error');
        }
    },

    openSettlement(supplierId, supplierName) {
        this.settlementSupplier = { id: supplierId, name: supplierName };
        this.settlementNotes = `Pembayaran bagi hasil konsinyasi periode ${this.month}`;
        this.showSettlementModal = true;
    },

    async processSettlement() {
        if (this.isSettling) return;
        if (!this.settlementSupplier) return;
        this.isSettling = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('settle-' + Date.now());

        try {
            const res = await fetch('/api/consignment/settlement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    supplier_id: this.settlementSupplier.id,
                    period_month: this.month,
                    notes: this.settlementNotes
                })
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.showSettlementModal = false;
                this.triggerToast(json.message);
                await this.loadReport();
            } else {
                this.triggerToast(json.message || 'Gagal membukukan settlement.', 'error');
            }
        } catch (e) {
            this.triggerToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            this.isSettling = false;
        }
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 4000);
    }
}"
x-init="initOpening(); initUmkm();"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar -->
    <x-manager-sidebar active="umkm" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        
        <!-- Header -->
        <header class="h-20 bg-[#f8f9fa] border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-3">
                <h1 class="text-[20px] font-bold text-gray-900">Mitra UMKM Konsinyasi</h1>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">handshake</span>
                    <span>Mitra, Produk UMKM &amp; Distribusi Cabang</span>
                </span>
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <x-notification-bell role="MANAGER" />
                <button type="button" @click="window.PosRealtime?.syncNow?.(); initUmkm();" class="hover:text-gray-900 transition-colors cursor-pointer" title="Sinkronkan Data">
                    <span class="material-symbols-rounded text-[24px]" :class="$store.network?.isChecking ? 'animate-spin text-[#0052cc]' : ''">autorenew</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <x-realtime-network-badge />
            </div>
        </header>

        <!-- Workspace -->
        <div class="flex-1 overflow-y-auto p-8 flex flex-col space-y-6">
            
            <!-- Tab Switcher Navigation & Quick Action Buttons -->
            <div class="bg-white p-2.5 rounded-2xl border border-gray-200/90 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex p-1 bg-gray-100 rounded-xl gap-1 flex-wrap">
                    <button type="button"
                            @click="activeTab = 'partners'"
                            :class="activeTab === 'partners' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">handshake</span>
                        <span>Daftar Mitra UMKM</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'products'; loadUmkmProducts();"
                            :class="activeTab === 'products' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">inventory_2</span>
                        <span>Produk Mitra UMKM &amp; Stok Cabang</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black"
                              :class="activeTab === 'products' ? 'bg-blue-100 text-[#0052cc]' : 'bg-gray-200 text-gray-700'"
                              x-text="productSummary.total_products || umkmProducts.length">0</span>
                    </button>
                    <button type="button"
                            @click="activeTab = 'report'; loadReport();"
                            :class="activeTab === 'report' ? 'bg-white text-[#0052cc] shadow-xs font-black' : 'text-gray-600 hover:text-gray-900 font-bold'"
                            class="px-4 py-2 rounded-lg text-xs transition-all flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">account_balance_wallet</span>
                        <span>Laporan Bagi Hasil Konsinyasi</span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="openCreateProductModal()"
                            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">add_box</span>
                        <span>Tambah Produk UMKM</span>
                    </button>
                </div>
            </div>
            
            <!-- ============================================== -->
            <!-- TAB 1: DAFTAR MITRA UMKM                       -->
            <!-- ============================================== -->
            <div x-show="activeTab === 'partners'" class="space-y-6">
                <!-- 4 KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">TOTAL MITRA UMKM</span>
                            <span class="material-symbols-rounded text-[20px] text-amber-600">store</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-gray-900" x-text="partnerSummary.total_partners">0</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="partnerSummary.active_partners + ' Mitra Aktif'"></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between cursor-pointer hover:border-blue-300 transition-colors"
                         @click="activeTab = 'products'; loadUmkmProducts();">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">PRODUK KONSINYASI</span>
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-[#0052cc]" x-text="partnerSummary.total_consignment_skus">0</h3>
                            <p class="text-[11px] text-[#0052cc] font-semibold mt-1">Klik untuk kelola produk &amp; stok &rarr;</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">RATA-RATA KOMISI</span>
                            <span class="material-symbols-rounded text-[20px] text-purple-600">percent</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-purple-600" x-text="partnerSummary.average_commission + '%'">15%</h3>
                            <p class="text-[11px] text-gray-400 mt-1">Bagian milik Koperasi</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">STOK DI CABANG</span>
                            <span class="material-symbols-rounded text-[20px] text-emerald-600">storefront</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[26px] font-black text-emerald-700" x-text="(productSummary.total_branch_stock || 0) + ' Unit'">0 Unit</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="'Gudang Pusat: ' + (productSummary.total_warehouse_stock || 0) + ' Unit'"></p>
                        </div>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative flex-1 w-full">
                        <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                        <input type="text"
                               x-model="searchPartner"
                               @input.debounce.300ms="loadPartners()"
                               placeholder="Cari kode UMKM, nama usaha, pemilik..."
                               class="w-full pl-9 pr-3 py-2 bg-white rounded-xl border border-gray-200 text-xs focus:outline-none focus:border-[#0052cc]">
                    </div>

                    <button type="button"
                            @click="openCreatePartner()"
                            class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">person_add</span>
                        <span>Tambah Mitra UMKM</span>
                    </button>
                </div>

                <!-- Partners Table -->
                <div class="bg-white rounded-2xl border border-gray-200/90 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-5 py-3.5">KODE &amp; NAMA USAHA UMKM</th>
                                    <th class="px-5 py-3.5">PEMILIK &amp; KONTAK</th>
                                    <th class="px-5 py-3.5">KATEGORI PRODUK</th>
                                    <th class="px-5 py-3.5 text-center">KOMISI KOPERASI</th>
                                    <th class="px-5 py-3.5 text-center">PRODUK TITIPAN</th>
                                    <th class="px-5 py-3.5">REKENING PENCAIRAN</th>
                                    <th class="px-5 py-3.5 text-center">STATUS</th>
                                    <th class="px-5 py-3.5 text-center">KELOLA PRODUK &amp; AKSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <template x-if="partners.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-6 py-10 text-center text-gray-400">
                                            Belum ada Mitra UMKM terdaftar. Klik <strong>Tambah Mitra UMKM</strong> untuk memulai.
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="p in partners" :key="p.id">
                                    <tr class="hover:bg-blue-50/20 transition-colors">
                                        <td class="px-5 py-3.5">
                                            <div class="font-bold text-gray-900" x-text="p.name"></div>
                                            <span class="font-mono text-[10.5px] text-gray-400" x-text="p.code"></span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="font-semibold text-gray-800" x-text="p.owner_name || '-'"></div>
                                            <div class="text-[11px] text-gray-400" x-text="p.phone || '-'"></div>
                                        </td>
                                        <td class="px-5 py-3.5 font-semibold text-gray-700" x-text="p.category"></td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="inline-block px-2.5 py-0.5 rounded-full font-mono font-bold text-xs bg-purple-50 text-purple-700 border border-purple-200"
                                                  x-text="p.commission_percent + '%'">
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <button type="button"
                                                    @click="viewPartnerProducts(p)"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#0052cc] font-mono font-bold text-xs transition-colors cursor-pointer"
                                                    title="Lihat produk mitra ini">
                                                <span x-text="(p.products_count || 0) + ' SKU'"></span>
                                                <span class="material-symbols-rounded text-[14px]">open_in_new</span>
                                            </button>
                                        </td>
                                        <td class="px-5 py-3.5 font-mono text-[11px]">
                                            <span class="font-bold text-gray-800" x-text="p.bank_name"></span>: <span x-text="p.bank_account || '-'"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                                  :class="p.status === 'AKTIF' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'"
                                                  x-text="p.status">
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="openCreateProductModal(p)"
                                                        class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-[11px] inline-flex items-center gap-1 transition-colors cursor-pointer"
                                                        title="Tambah produk untuk mitra ini">
                                                    <span class="material-symbols-rounded text-[15px]">add_circle</span>
                                                    <span>+ Produk</span>
                                                </button>
                                                <button type="button" @click="openEditPartner(p)" class="p-1.5 text-gray-400 hover:text-gray-900 rounded-lg cursor-pointer" title="Edit Mitra">
                                                    <span class="material-symbols-rounded text-[18px]">edit</span>
                                                </button>
                                                <button type="button" @click="deletePartner(p)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg cursor-pointer" title="Hapus Mitra">
                                                    <span class="material-symbols-rounded text-[18px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TAB 2: PRODUK MITRA UMKM & STOK CABANG         -->
            <!-- ============================================== -->
            <div x-show="activeTab === 'products'" class="space-y-6" style="display: none;">
                <!-- 4 Summary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">TOTAL SKU PRODUK UMKM</span>
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[24px] font-black text-gray-900" x-text="(productSummary.total_products || 0) + ' SKU'">0 SKU</h3>
                            <p class="text-[11px] text-gray-400 mt-1">Terdaftar di Katalog &amp; POS</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex flex-col justify-between bg-amber-50/20">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">STOK GUDANG PUSAT (DC)</span>
                            <span class="material-symbols-rounded text-[20px] text-amber-600">warehouse</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[24px] font-mono font-black text-amber-700" x-text="(productSummary.total_warehouse_stock || 0) + ' Unit'">0 Unit</h3>
                            <p class="text-[11px] text-amber-700/80 mt-1">Siap didistribusikan via menu Kirim Barang</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex flex-col justify-between bg-emerald-50/20">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">STOK AKTIF DI CABANG</span>
                            <span class="material-symbols-rounded text-[20px] text-emerald-600">storefront</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[24px] font-mono font-black text-emerald-700" x-text="(productSummary.total_branch_stock || 0) + ' Unit'">0 Unit</h3>
                            <p class="text-[11px] text-emerald-700/80 mt-1">Siap ditransaksikan oleh Kasir</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">NILAI JUAL KATALOG UMKM</span>
                            <span class="material-symbols-rounded text-[20px] text-purple-600">payments</span>
                        </div>
                        <div class="mt-2">
                            <h3 class="text-[20px] font-mono font-black text-gray-900" x-text="formatRupiah(productSummary.total_retail_value || 0)">Rp 0</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="'Modal Beli: ' + formatRupiah(productSummary.total_cost_value || 0)"></p>
                        </div>
                    </div>
                </div>

                <!-- Filter & Action Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3 flex-1">
                        <div class="relative flex-1 min-w-[220px]">
                            <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text"
                                   x-model="searchProduct"
                                   @input.debounce.300ms="loadUmkmProducts()"
                                   placeholder="Cari nama produk UMKM, SKU, barcode..."
                                   class="w-full pl-9 pr-3 py-2 bg-gray-50 rounded-xl border border-gray-200 text-xs focus:outline-none focus:border-[#0052cc] focus:bg-white">
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-500">Mitra UMKM:</span>
                            <select x-model="filterProductPartnerId"
                                    @change="loadUmkmProducts()"
                                    class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                <option value="all">Semua Mitra UMKM</option>
                                <template x-for="p in partners" :key="p.id">
                                    <option :value="String(p.id)" x-text="p.name + ' (' + p.code + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <button type="button"
                                x-show="filterProductPartnerId !== 'all' || searchProduct !== ''"
                                @click="filterProductPartnerId = 'all'; searchProduct = ''; loadUmkmProducts();"
                                class="px-3 py-2 text-xs font-bold text-gray-500 hover:text-gray-800 cursor-pointer">
                            Reset Filter
                        </button>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                                @click="openCreateProductModal()"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">add_circle</span>
                            <span>Tambah Produk Mitra</span>
                        </button>
                    </div>
                </div>

                <!-- UMKM Products Table -->
                <div class="bg-white rounded-2xl border border-gray-200/90 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse min-w-[1050px]">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                                <tr>
                                    <th class="px-4 py-3.5">SKU &amp; BARCODE</th>
                                    <th class="px-4 py-3.5">NAMA PRODUK UMKM</th>
                                    <th class="px-4 py-3.5">MITRA UMKM</th>
                                    <th class="px-4 py-3.5 text-right">HARGA BELI (MITRA)</th>
                                    <th class="px-4 py-3.5 text-right">HARGA JUAL (TOKO)</th>
                                    <th class="px-4 py-3.5 text-center">STOK GUDANG</th>
                                    <th class="px-4 py-3.5">STOK DI CABANG (SIAP JUAL)</th>
                                    <th class="px-4 py-3.5 text-center">AKSI &amp; DISTRIBUSI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <template x-if="umkmProducts.length === 0">
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                            <span class="material-symbols-rounded text-3xl text-gray-300 block mb-1">inventory_2</span>
                                            <p class="font-bold text-gray-600 text-sm">Belum ada produk Mitra UMKM terdaftar.</p>
                                            <p class="text-xs text-gray-400 mt-0.5">Klik tombol <strong>Tambah Produk Mitra</strong> untuk mendaftarkan barang yang dibeli/dititipkan dari Mitra UMKM.</p>
                                            <button type="button"
                                                    @click="openCreateProductModal()"
                                                    class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer">
                                                <span class="material-symbols-rounded text-[16px]">add</span>
                                                <span>Tambah Produk Mitra UMKM</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="prod in umkmProducts" :key="prod.id">
                                    <tr class="hover:bg-blue-50/20 transition-colors">
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <div class="font-mono font-bold text-gray-900" x-text="prod.sku"></div>
                                            <div class="font-mono text-[10.5px] text-gray-400" x-text="prod.barcode"></div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-gray-900" x-text="prod.name"></div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#0052cc]" x-text="prod.category"></span>
                                                <span class="text-[10.5px] text-gray-400" x-text="'Satuan: ' + prod.unit"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-gray-800" x-text="prod.supplier_name"></div>
                                            <span class="font-mono text-[10px] text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded font-bold"
                                                  x-text="'Komisi Kopkar: ' + prod.commission_percent + '%'"></span>
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-mono font-semibold text-gray-700 whitespace-nowrap"
                                            x-text="formatRupiah(prod.cost_price)"></td>
                                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                            <div class="font-mono font-black text-gray-900" x-text="formatRupiah(prod.price)"></div>
                                            <div class="text-[10px] font-mono text-emerald-600 font-semibold"
                                                 x-text="'Margin: ' + formatRupiah(prod.margin_per_unit)"></div>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-lg font-mono font-bold text-xs border"
                                                  :class="prod.warehouse_stock > 0 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-gray-100 text-gray-500 border-gray-200'"
                                                  x-text="prod.warehouse_stock + ' ' + prod.unit"></span>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <template x-for="st in stores" :key="st.code">
                                                    <span x-show="prod.branch_stock && prod.branch_stock[st.code] !== undefined"
                                                          class="px-2 py-0.5 rounded-md text-[10.5px] font-mono font-bold border"
                                                          :class="(prod.branch_stock && prod.branch_stock[st.code] > 0) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-50 text-gray-500 border-gray-200'"
                                                          x-text="st.name + ': ' + ((prod.branch_stock && prod.branch_stock[st.code]) || 0)">
                                                    </span>
                                                </template>
                                                <template x-if="!prod.allocated_branches || prod.allocated_branches.length === 0">
                                                    <span class="px-2 py-0.5 rounded-md text-[10.5px] font-semibold bg-gray-50 text-gray-400 border border-gray-200 italic">
                                                        Belum dikirim ke cabang
                                                    </span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="openRestockModal(prod)"
                                                        class="px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-[11px] inline-flex items-center gap-0.5 transition-colors cursor-pointer"
                                                        title="Tambah Stok Pembelian dari Mitra">
                                                    <span class="material-symbols-rounded text-[14px]">add</span>
                                                    <span>Stok</span>
                                                </button>
                                                <button type="button"
                                                        @click="openEditProductModal(prod)"
                                                        class="p-1.5 text-gray-400 hover:text-gray-900 rounded-lg cursor-pointer"
                                                        title="Edit Produk">
                                                    <span class="material-symbols-rounded text-[17px]">edit</span>
                                                </button>
                                                <button type="button"
                                                        @click="deleteUmkmProduct(prod)"
                                                        class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg cursor-pointer"
                                                        title="Hapus Produk">
                                                    <span class="material-symbols-rounded text-[17px]">delete</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- TAB 3: LAPORAN BAGI HASIL KONSINYASI           -->
            <!-- ============================================== -->
            <div x-show="activeTab === 'report'" class="space-y-6" style="display: none;">
                <!-- 3 Summary KPI Cards: Gross Sales vs Koperasi Commission vs UMKM Share -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <!-- Total Gross -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs flex flex-col justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">TOTAL OMZET PENJUALAN KOTOR</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-gray-900" x-text="formatRupiah(reportSummary.total_gross_sales)">Rp 0</h3>
                            <p class="text-[11px] text-gray-400 mt-1" x-text="reportSummary.total_sold_units + ' Unit Terjual di Kasir'"></p>
                        </div>
                    </div>

                    <!-- Koperasi Commission -->
                    <div class="bg-white rounded-2xl border border-purple-200 p-5 shadow-xs flex flex-col justify-between bg-purple-50/20">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-700">BAGIAN KOMISI KOPERASI</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-purple-700" x-text="formatRupiah(reportSummary.total_coop_commission)">Rp 0</h3>
                            <p class="text-[11px] text-purple-600 mt-1">Pendapatan bagi hasil Koperasi</p>
                        </div>
                    </div>

                    <!-- UMKM Net Share -->
                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex flex-col justify-between bg-emerald-50/20">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">HAK BERSIH MILIK UMKM</span>
                        <div class="mt-2">
                            <h3 class="text-[22px] font-mono font-black text-emerald-700" x-text="formatRupiah(reportSummary.total_umkm_net_share)">Rp 0</h3>
                            <p class="text-[11px] text-emerald-600 mt-1">Wajib dicairkan / ditransfer ke mitra</p>
                        </div>
                    </div>
                </div>

                <!-- Filter Period -->
                <div class="bg-white rounded-2xl border border-gray-200/90 p-4 shadow-xs flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500">Periode Bulan:</span>
                        <input type="month"
                               x-model="month"
                               @change="loadReport()"
                               class="px-3 py-1.5 bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500">Filter Mitra:</span>
                        <select x-model="selectedSupplierId"
                                @change="loadReport()"
                                class="px-3 py-1.5 bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 focus:outline-none">
                            <option value="all">Semua Mitra UMKM</option>
                            <template x-for="p in partners" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="bg-white rounded-2xl border border-gray-200/90 shadow-xs overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-5 py-3.5">PRODUK TITIPAN &amp; SKU</th>
                                <th class="px-5 py-3.5">MITRA UMKM</th>
                                <th class="px-5 py-3.5 text-center">SISA STOK</th>
                                <th class="px-5 py-3.5 text-center">TERJUAL</th>
                                <th class="px-5 py-3.5 text-right">TOTAL OMZET KOTOR</th>
                                <th class="px-5 py-3.5 text-right text-purple-700">BAGIAN KOMISI KOPKAR</th>
                                <th class="px-5 py-3.5 text-right text-emerald-700">BAGIAN BERSIH UMKM</th>
                                <th class="px-5 py-3.5 text-center">AKSI PENCAIRAN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <template x-if="reportRows.length === 0">
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                        Tidak ada data penjualan konsinyasi pada periode ini.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="r in reportRows" :key="r.product_id">
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3.5">
                                        <div class="font-bold text-gray-900" x-text="r.name"></div>
                                        <span class="font-mono text-[10px] text-gray-400" x-text="r.sku"></span>
                                    </td>
                                    <td class="px-5 py-3.5 font-semibold text-gray-800" x-text="r.supplier_name"></td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="r.stock"></td>
                                    <td class="px-5 py-3.5 text-center font-mono font-bold" x-text="r.units_sold"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(r.gross_sales)"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-bold text-purple-700">
                                        <div x-text="formatRupiah(r.coop_commission)"></div>
                                        <span class="text-[9.5px] text-purple-500" x-text="r.commission_percent + '%'"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-emerald-700" x-text="formatRupiah(r.umkm_net_share)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <button type="button"
                                                @click="openSettlement(r.supplier_id, r.supplier_name)"
                                                :disabled="r.umkm_net_share <= 0"
                                                class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg font-bold text-[11px] transition-colors disabled:opacity-30 cursor-pointer">
                                            Cairkan Bagi Hasil
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- ===================================================== -->
    <!-- MODAL 1: TAMBAH / EDIT MITRA UMKM                     -->
    <!-- ===================================================== -->
    <div x-show="showPartnerModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showPartnerModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1" x-text="partnerModalMode === 'create' ? 'Daftarkan Mitra UMKM Lokal' : 'Edit Mitra UMKM'"></h3>
            <p class="text-xs text-gray-400 mb-4">Pengaturan bagi hasil omzet titipan konsinyasi.</p>

            <form @submit.prevent="savePartner()" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Nama Usaha / Merk UMKM <span class="text-red-500">*</span></label>
                    <input type="text" x-model="partnerFormData.name" placeholder="Contoh: Dapur Bu Eni" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Pemilik</label>
                        <input type="text" x-model="partnerFormData.owner_name" placeholder="Nama pemilik" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">No. WhatsApp / HP</label>
                        <input type="text" x-model="partnerFormData.phone" placeholder="0812-xxxx-xxxx" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-purple-700 mb-1">Bagian Komisi Koperasi (%)</label>
                        <input type="number" step="0.5" x-model="partnerFormData.commission_percent" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono font-bold text-purple-700 focus:outline-none">
                        <p class="text-[10px] text-gray-400 mt-1">Hak bersih UMKM = <span x-text="(100 - partnerFormData.commission_percent) + '%'"></span></p>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Periode Settlement</label>
                        <select x-model="partnerFormData.settlement_period" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-semibold focus:outline-none">
                            <option value="MINGGUAN">Mingguan</option>
                            <option value="BULANAN">Bulanan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Bank Penampung</label>
                        <input type="text" x-model="partnerFormData.bank_name" placeholder="BRI, Mandiri, BSI" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">No. Rekening</label>
                        <input type="text" x-model="partnerFormData.bank_account" placeholder="Nomor rekening" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Alamat / Lokasi Usaha</label>
                    <input type="text" x-model="partnerFormData.address" placeholder="Alamat mitra" class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" :disabled="isLoadingPartners" @click="showPartnerModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600 disabled:opacity-50 cursor-pointer">Batal</button>
                    <button type="submit" :disabled="isLoadingPartners" class="px-5 py-2 bg-[#0052cc] text-white rounded-xl font-bold flex items-center gap-1.5 disabled:opacity-50 cursor-pointer">
                        <span x-show="isLoadingPartners" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                        <span x-text="isLoadingPartners ? 'Menyimpan...' : 'Simpan Mitra'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- MODAL 2: TAMBAH / EDIT PRODUK MITRA UMKM (BELI & JUAL)-->
    <!-- ===================================================== -->
    <div x-show="showProductModal"
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 max-h-[92vh] overflow-y-auto"
             @click.outside="showProductModal = false">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                <div>
                    <h3 class="text-base font-black text-gray-900"
                        x-text="productModalMode === 'create' ? 'Tambah Produk Mitra UMKM (Beli & Jual)' : 'Edit Produk Mitra UMKM'"></h3>
                    <p class="text-xs text-gray-500">Daftarkan produk dari Mitra UMKM beserta harga beli, harga jual toko, dan alokasi stok.</p>
                </div>
                <button type="button" @click="showProductModal = false" class="text-gray-400 hover:text-gray-700 cursor-pointer">
                    <span class="material-symbols-rounded">close</span>
                </button>
            </div>

            <form @submit.prevent="saveUmkmProduct()" class="space-y-4 text-xs">
                <!-- Pilih Mitra UMKM -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Pilih Mitra UMKM Pemasok <span class="text-red-500">*</span></label>
                        <select x-model="productForm.supplier_id"
                                @change="onProductPartnerChange()"
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                            <template x-for="p in partners" :key="p.id">
                                <option :value="p.id" x-text="p.name + ' (Komisi ' + p.commission_percent + '%)'"></option>
                            </template>
                        </select>
                        <template x-if="productFormErrors.supplier_id">
                            <p class="text-red-600 text-[11px] mt-1 font-semibold" x-text="productFormErrors.supplier_id"></p>
                        </template>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Produk UMKM <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="productForm.name"
                               placeholder="Contoh: Keripik Pisang Coklat 250g"
                               required
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        <template x-if="productFormErrors.name">
                            <p class="text-red-600 text-[11px] mt-1 font-semibold" x-text="productFormErrors.name"></p>
                        </template>
                    </div>
                </div>

                <!-- SKU, Barcode, Kategori, Satuan -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Kode SKU</label>
                        <input type="text"
                               x-model="productForm.sku"
                               placeholder="UMKM-001"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono uppercase focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Barcode Kasir</label>
                        <div class="flex gap-1">
                            <input type="text"
                                   x-model="productForm.barcode"
                                   placeholder="899..."
                                   class="w-full px-2.5 py-2 rounded-xl border border-gray-300 font-mono focus:outline-none focus:border-[#0052cc]">
                            <button type="button"
                                    @click="generateNewBarcode()"
                                    title="Generate Barcode Baru"
                                    class="px-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-gray-700 cursor-pointer">
                                <span class="material-symbols-rounded text-[16px]">refresh</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Kategori</label>
                        <input type="text"
                               x-model="productForm.category"
                               placeholder="Makanan Ringan"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Satuan Jual</label>
                        <select x-model="productForm.unit"
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 font-semibold focus:outline-none focus:border-[#0052cc]">
                            <option value="Pcs">Pcs</option>
                            <option value="Bungkus">Bungkus</option>
                            <option value="Pack">Pack</option>
                            <option value="Botol">Botol</option>
                            <option value="Kotak">Kotak</option>
                            <option value="Toples">Toples</option>
                        </select>
                    </div>
                </div>

                <!-- Harga Beli (Modal Mitra), Harga Jual (Toko), Harga Member & Komisi -->
                <div class="p-4 rounded-2xl bg-blue-50/50 border border-blue-200/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-black text-[#0052cc] uppercase tracking-wider text-[11px]">Pengaturan Harga Beli &amp; Harga Jual</span>
                        <span class="text-[11px] font-bold text-emerald-700"
                              x-text="'Margin Koperasi: ' + formatRupiah(Math.max(0, (Number(productForm.price) || 0) - (Number(productForm.cost_price) || 0))) + ' / ' + productForm.unit"></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Harga Beli dari Mitra (Rp) <span class="text-red-500">*</span></label>
                            <input type="number"
                                   min="0"
                                   x-model="productForm.cost_price"
                                   @input="syncSellPriceFromCommission()"
                                   class="w-full px-3 py-2 rounded-xl border border-gray-300 bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Harga Jual Toko (Rp) <span class="text-red-500">*</span></label>
                            <input type="number"
                                   min="0"
                                   x-model="productForm.price"
                                   @input="syncCommissionFromSellPrice(); productForm.member_price = productForm.price;"
                                   class="w-full px-3 py-2 rounded-xl border border-gray-300 bg-white font-mono font-bold text-[#0052cc] focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Harga Member (Rp)</label>
                            <input type="number"
                                   min="0"
                                   x-model="productForm.member_price"
                                   class="w-full px-3 py-2 rounded-xl border border-gray-300 bg-white font-mono font-bold text-emerald-700 focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="block font-bold text-purple-700 mb-1">Komisi Koperasi (%)</label>
                            <input type="number"
                                   step="0.1"
                                   min="0"
                                   max="100"
                                   x-model="productForm.commission_percent"
                                   @input="syncSellPriceFromCommission()"
                                   class="w-full px-3 py-2 rounded-xl border border-purple-300 bg-white font-mono font-bold text-purple-700 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Stok Awal, Min Stok, Lokasi Penempatan Stok & Kadaluwarsa -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <template x-if="productModalMode === 'create'">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Stok Masuk Awal <span class="text-red-500">*</span></label>
                            <input type="number"
                                   min="0"
                                   x-model="productForm.stock"
                                   class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono font-bold focus:outline-none focus:border-[#0052cc]">
                        </div>
                    </template>
                    <template x-if="productModalMode === 'create'">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Penempatan Stok Awal</label>
                            <select x-model="productForm.target_location"
                                    class="w-full px-3 py-2 rounded-xl border border-gray-300 font-bold text-gray-800 bg-gray-50 focus:outline-none focus:border-[#0052cc]">
                                <option value="GUDANG_PUSAT">Gudang Pusat (DC)</option>
                            </select>
                            <p class="text-[10px] text-gray-400 mt-0.5">Kirim ke cabang melalui menu Kirim Barang.</p>
                        </div>
                    </template>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Min. Buffer Stok</label>
                        <input type="number"
                               min="0"
                               x-model="productForm.min_stock"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Tgl. Kadaluwarsa (ED)</label>
                        <input type="date"
                               x-model="productForm.expired_date"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <button type="button"
                            :disabled="isSavingProduct"
                            @click="showProductModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-xl font-bold text-gray-600 cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="isSavingProduct"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span x-show="isSavingProduct" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                        <span x-text="isSavingProduct ? 'Menyimpan...' : (productModalMode === 'create' ? 'Simpan Produk UMKM' : 'Perbarui Produk UMKM')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- MODAL 3: TAMBAH STOK / BELI DARI MITRA UMKM           -->
    <!-- ===================================================== -->
    <div x-show="showRestockModal"
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showRestockModal = false">
            <h3 class="text-base font-black text-gray-900 mb-0.5">Tambah Stok dari Mitra UMKM</h3>
            <p class="text-xs text-gray-500 mb-4" x-text="restockTargetProduct ? (restockTargetProduct.name + ' • ' + restockTargetProduct.supplier_name) : ''"></p>

            <form @submit.prevent="submitRestock()" class="space-y-3.5 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Jumlah Stok Masuk <span class="text-red-500">*</span></label>
                        <input type="number" min="1" x-model="restockForm.qty" required class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono font-bold text-gray-900">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Lokasi Penerimaan</label>
                        <select x-model="restockForm.target_location" class="w-full px-3 py-2 rounded-xl border border-gray-300 bg-gray-50 font-bold">
                            <option value="GUDANG_PUSAT">Gudang Pusat (DC)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Harga Beli Mitra (Rp)</label>
                        <input type="number" min="0" x-model="restockForm.cost_price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Harga Jual Toko (Rp)</label>
                        <input type="number" min="0" x-model="restockForm.price" class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Tanggal Kadaluwarsa Batch Ini</label>
                    <input type="date" x-model="restockForm.expired_date" class="w-full px-3 py-2 rounded-xl border border-gray-300">
                </div>

                <div>
                    <label class="block font-bold text-gray-700 mb-1">Catatan</label>
                    <input type="text" x-model="restockForm.notes" class="w-full px-3 py-2 rounded-xl border border-gray-300">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showRestockModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600 cursor-pointer">Batal</button>
                    <button type="submit" :disabled="isRestocking" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold cursor-pointer">
                        <span x-text="isRestocking ? 'Memproses...' : 'Tambah Stok'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Settlement -->
    <div x-show="showSettlementModal"
         class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-200"
             @click.outside="showSettlementModal = false">
            <h3 class="text-base font-black text-gray-900 mb-1">Bukukan Bagi Hasil Konsinyasi</h3>
            <p class="text-xs text-gray-400 mb-4" x-text="settlementSupplier ? settlementSupplier.name : ''"></p>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-gray-700 mb-1">Catatan Pencairan</label>
                    <input type="text" x-model="settlementNotes" class="w-full px-3 py-2 rounded-xl border border-gray-300">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="showSettlementModal = false" class="px-4 py-2 border rounded-xl font-bold text-gray-600 cursor-pointer">Batal</button>
                    <button type="button" @click="processSettlement()" :disabled="isSettling" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold cursor-pointer">Konfirmasi Pembayaran</button>
                </div>
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
