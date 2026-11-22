@extends('layouts.app')

@section('title', 'Produk Mitra UMKM - Kasir ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    selectedPartnerId: 'all',
    isLoading: false,
    isSyncingToPos: false,

    umkmProducts: [],
    partners: [],
    locationInfo: null,
    summary: {
        total_umkm_skus: 0,
        ready_to_transact_count: 0,
        total_branch_stock: 0,
        total_warehouse_stock: 0,
        total_partners: 0
    },

    activeCartCount: 0,

    // Toast Notification
    toastMessage: '',
    toastType: 'success',
    showToast: false,
    toastTimer: null,

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        if (this.toastTimer) clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => {
            this.showToast = false;
        }, 3800);
    },

    getActiveBranch() {
        const serverBranch = @js(auth()->user()?->branch ? explode(',', auth()->user()->branch)[0] : '');
        const raw = this.activeSession?.storeLocation || localStorage.getItem('pos_kasir_branch') || serverBranch || this.selectedBranch || 'Pusat';
        return this.normalizeBranchName ? this.normalizeBranchName(raw) : raw;
    },

    getActiveTerminal() {
        if (this.activeSession?.terminalLabel) {
            return this.activeSession.terminalLabel;
        }
        const branch = String(this.getActiveBranch() || '').toLowerCase();
        const candidate = this.formatShiftLabel ? this.formatShiftLabel(this.selectedShift) : 'POS-01';
        const m = /^POS-(\d+)$/i.exec(candidate || '');
        const num = m ? parseInt(m[1], 10) : 1;
        if (branch.includes('cabang a') || branch.includes('cabang 1')) {
            return (num >= 6 && num <= 10) ? candidate : 'POS-06';
        }
        if (branch.includes('cabang b') || branch.includes('cabang 2')) {
            return (num >= 11 && num <= 15) ? candidate : 'POS-11';
        }
        return (num >= 1 && num <= 5) ? candidate : 'POS-01';
    },

    getBranchCacheKey(branch) {
        const slug = String(branch || 'pusat').toLowerCase().replace(/[^a-z0-9]+/g, '_');
        return `pos_ilp_stock_toko_${slug}_v1`;
    },

    refreshCartCount() {
        try {
            const raw = localStorage.getItem('pos_ilp_active_cart_v1');
            const parsed = raw ? JSON.parse(raw) : [];
            if (Array.isArray(parsed)) {
                this.activeCartCount = parsed.reduce((acc, it) => acc + (Number(it.qty) || 0), 0);
            } else {
                this.activeCartCount = 0;
            }
        } catch (e) {
            this.activeCartCount = 0;
        }
    },

    async initUmkmKasir() {
        this.refreshCartCount();
        await this.fetchUmkmProducts();
        await this.syncUmkmIntoLocalPosCache(this.umkmProducts);
        if (typeof this.$watch === 'function') {
            this.$watch('selectedBranch', async () => {
                await this.fetchUmkmProducts();
                await this.syncUmkmIntoLocalPosCache(this.umkmProducts);
            });
        }
    },

    async fetchUmkmProducts() {
        this.isLoading = true;
        try {
            const branch = this.getActiveBranch();
            const terminal = this.getActiveTerminal();
            const params = new URLSearchParams({
                branch: branch,
                terminal_code: terminal
            });
            if (this.activeSession?.locationId) {
                params.set('location_id', String(this.activeSession.locationId));
            }
            const res = await fetch('/api/pos/products/umkm?' + params.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.umkmProducts = json.data || [];
                    this.partners = json.partners || [];
                    this.locationInfo = json.location || null;
                    if (json.summary) {
                        this.summary = { ...this.summary, ...json.summary };
                    }
                }
            }
        } catch (e) {
            console.warn('Error fetching UMKM products for cashier:', e);
            this.triggerToast('Gagal memuat daftar produk UMKM dari server.', 'error');
        } finally {
            this.isLoading = false;
        }
    },

    async loadDataProdukUmkm(silent = false, targetProductId = null) {
        if (this.isSyncingToPos) return;
        this.isSyncingToPos = true;
        try {
            const branch = this.getActiveBranch();
            const terminal = this.getActiveTerminal();
            const payload = {
                branch: branch,
                terminal_code: terminal,
                location_id: this.activeSession?.locationId || null,
                product_id: targetProductId
            };

            const res = await fetch('/api/pos/products/umkm/load', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (res.ok && json.success) {
                this.umkmProducts = json.data || [];
                if (json.location) this.locationInfo = json.location;
                if (json.summary) this.summary = { ...this.summary, ...json.summary };

                await this.syncUmkmIntoLocalPosCache(this.umkmProducts);
                await this.fetchUmkmProducts();

                if (!silent) {
                    this.triggerToast(json.message || 'Data Produk UMKM berhasil di-load ke katalog kasir & siap ditransaksikan!', 'success');
                }
            } else if (!silent) {
                this.triggerToast(json.message || 'Gagal memuat data produk UMKM.', 'error');
            }
        } catch (e) {
            if (!silent) {
                this.triggerToast('Gagal menghubungi server saat memuat produk UMKM.', 'error');
            }
        } finally {
            this.isSyncingToPos = false;
        }
    },

    async syncUmkmIntoLocalPosCache(umkmItems) {
        const validUmkm = Array.isArray(umkmItems) ? umkmItems : [];
        try {
            const branch = this.getActiveBranch();
            const branchKey = this.getBranchCacheKey(branch);
            const locId = this.locationInfo?.id || this.activeSession?.locationId || 2;
            const validSkus = new Set(validUmkm.map(p => String(p?.sku || '').toUpperCase()).filter(Boolean));

            // 1. Update localStorage branch catalog cache (removing any unshipped UMKM items)
            let existing = [];
            const rawBranch = localStorage.getItem(branchKey);
            if (rawBranch) {
                try {
                    const parsed = JSON.parse(rawBranch);
                    if (Array.isArray(parsed)) existing = parsed;
                } catch (err) {}
            }

            const bySku = new Map();
            existing.forEach(p => {
                if (!p || !p.sku) return;
                const skuUpper = String(p.sku).toUpperCase();
                const isUmkm = Boolean(p.is_consignment) || skuUpper.startsWith('UMKM-');
                if (isUmkm && !validSkus.has(skuUpper)) {
                    return; // Do not keep UMKM products that were not sent to this branch
                }
                bySku.set(skuUpper, p);
            });
            validUmkm.forEach(p => {
                if (p && p.sku) bySku.set(String(p.sku).toUpperCase(), p);
            });

            const merged = Array.from(bySku.values());
            localStorage.setItem(branchKey, JSON.stringify(merged.slice(0, 150)));
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(merged.slice(0, 150)));

            // 2. Update Dexie IndexedDB if available (purge unshipped UMKM records for this location)
            if (window.PosDb) {
                await window.PosDb.init();
                if (window.PosDb.db && window.PosDb.db.product_cache) {
                    const cachedInLoc = await window.PosDb.db.product_cache.where('location_id').equals(Number(locId)).toArray();
                    const idsToDelete = cachedInLoc
                        .filter(item => {
                            const skuU = String(item.sku || '').toUpperCase();
                            const isUmkm = Boolean(item.is_consignment) || skuU.startsWith('UMKM-');
                            return isUmkm && !validSkus.has(skuU);
                        })
                        .map(item => item.id);
                    if (idsToDelete.length > 0) {
                        await window.PosDb.db.product_cache.bulkDelete(idsToDelete);
                    }
                }
                if (validUmkm.length > 0 && typeof window.PosDb.products?.bulkUpsert === 'function') {
                    await window.PosDb.products.bulkUpsert(locId, validUmkm);
                }
            }

            window.dispatchEvent(new CustomEvent('pos-inventory-synced', { detail: { count: merged.length } }));
        } catch (e) {
            console.warn('Failed to sync UMKM products into local POS cache:', e);
        }
    },

    get filteredUmkmProducts() {
        return (this.umkmProducts || []).filter(p => {
            if (this.selectedPartnerId !== 'all' && String(p.supplier_id) !== String(this.selectedPartnerId)) {
                return false;
            }
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchName = (p.name || '').toLowerCase().includes(q);
                const matchSku = (p.sku || '').toLowerCase().includes(q);
                const matchBarcode = (p.barcode || '').toLowerCase().includes(q);
                const matchPartner = (p.supplier_name || '').toLowerCase().includes(q);
                const matchCat = (p.category || '').toLowerCase().includes(q);
                return matchName || matchSku || matchBarcode || matchPartner || matchCat;
            }
            return true;
        });
    },

    addToPosCart(p, redirectToPos = false) {
        if (!p) return;
        const availableStock = Number(p.branch_stock_qty ?? p.stock ?? 0);
        if (availableStock <= 0) {
            this.triggerToast(`Stok [${p.name}] di cabang ini masih 0. Klik 'Load Data Produk UMKM' atau minta Manager kirim stok ke cabang.`, 'error');
            return;
        }

        let cart = [];
        try {
            const raw = localStorage.getItem('pos_ilp_active_cart_v1');
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) cart = parsed;
            }
        } catch (e) {
            cart = [];
        }

        const targetSku = (p.sku || '').trim().toUpperCase();
        const targetBarcode = (p.barcode || '').trim();

        const existing = cart.find(it => {
            const itSku = (it.sku || '').trim().toUpperCase();
            const itBarcode = (it.barcode || '').trim();
            return (targetSku && itSku === targetSku) || (targetBarcode && itBarcode === targetBarcode);
        });

        if (existing) {
            if ((Number(existing.qty) || 0) + 1 > availableStock) {
                this.triggerToast(`Jumlah di keranjang melebihi sisa stok [${p.name}] (${availableStock} ${p.unit || 'Pcs'}).`, 'error');
                return;
            }
            existing.qty = (Number(existing.qty) || 0) + 1;
        } else {
            cart.push({
                sku: p.sku || '',
                barcode: p.barcode || '',
                name: p.name || 'Produk UMKM',
                originalPrice: Number(p.original_price || p.price) || 0,
                price: Number(p.price) || 0,
                memberPrice: Number(p.member_price || p.price) || 0,
                qty: 1,
                unit: p.unit || 'Pcs',
                isTaxable: p.is_taxable !== false,
                isNearExpired: Boolean(p.is_near_expired),
                daysUntilExpiration: p.days_until_expiration,
                clearanceDiscountPercent: Number(p.clearance_discount_percent) || 0,
                clearanceWarningBadge: p.clearance_warning_badge || null,
                isConsignment: true,
                supplierName: p.supplier_name || 'Mitra UMKM'
            });
        }

        localStorage.setItem('pos_ilp_active_cart_v1', JSON.stringify(cart));
        this.refreshCartCount();

        if (redirectToPos) {
            window.location.href = '/pos';
        } else {
            this.triggerToast(`+1 ${p.name} (${p.supplier_name}) ditambahkan ke Keranjang POS!`, 'success');
        }
    }
}"
x-init="initOpening(); initUmkmKasir();"
@pos-session-sync.window="initOpening(); fetchUmkmProducts();"
@pos:inventory_updated.window="fetchUmkmProducts()"
@pos:goods_in_updated.window="loadDataProdukUmkm(true)"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden relative">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="produk_umkm" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Katalog Produk Mitra UMKM">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200 flex items-center gap-1.5 shadow-2xs">
                    <span class="material-symbols-rounded text-[14px]">handshake</span>
                    <span>Konsinyasi &amp; Produk UMKM Cabang</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Data Produk Mitra UMKM</h1>
                            <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 text-[#0052cc] border border-blue-200 text-xs font-bold"
                                  x-text="locationInfo?.name || getActiveBranch()"></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- TOMBOL UTAMA: LOAD DATA PRODUK UMKM -->
                        <button type="button"
                                @click="loadDataProdukUmkm(false)"
                                :disabled="isSyncingToPos"
                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer active:scale-95 disabled:opacity-50">
                            <span class="material-symbols-rounded text-[18px]"
                                  :class="isSyncingToPos ? 'animate-spin' : ''">cloud_download</span>
                            <span x-text="isSyncingToPos ? 'Memuat Data Produk UMKM...' : 'Load Data Produk UMKM'"></span>
                        </button>

                        <!-- TOMBOL KE LAYAR POS -->
                        <a href="{{ url('/pos') }}"
                           class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                            <span>Buka Layar Transaksi POS</span>
                            <template x-if="activeCartCount > 0">
                                <span class="px-2 py-0.5 rounded-full bg-white text-[#0052cc] font-black text-[11px]"
                                      x-text="activeCartCount + ' Item'"></span>
                            </template>
                        </a>
                    </div>
                </div>

                <!-- 4 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">TOTAL PRODUK UMKM</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-900" x-text="summary.total_umkm_skus">0</h3>
                                <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-md">SKU Mitra</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Terdaftar dari Mitra UMKM</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">inventory_2</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[11px] font-bold text-emerald-700 tracking-wider uppercase">SIAP TRANSAKSI POS</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-emerald-600" x-text="summary.ready_to_transact_count">0</h3>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">Stok Tersedia</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Dapat langsung dijual di kasir</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">check_circle</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">TOTAL STOK UMKM CABANG</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-900 font-mono" x-text="summary.total_branch_stock">0</h3>
                                <span class="text-[10px] font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-md">Unit Aktif</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block" x-text="'Stok Gudang Pusat: ' + (summary.total_warehouse_stock || 0) + ' Unit'"></span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">storefront</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">MITRA UMKM PEMASOK</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-purple-700" x-text="summary.total_partners">0</h3>
                                <span class="text-[10px] font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md">Mitra Lokal</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Konsinyasi Koperasi ILP</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">handshake</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Bar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nama produk UMKM, barcode, SKU, atau nama mitra UMKM..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] text-[13px] font-medium bg-slate-50/50 focus:bg-white">
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <select x-model="selectedPartnerId"
                                class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-[13px] text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] min-w-[220px]">
                            <option value="all">Semua Mitra UMKM</option>
                            <template x-for="p in partners" :key="p.id">
                                <option :value="String(p.id)" x-text="p.name + ' (' + p.code + ')'"></option>
                            </template>
                        </select>

                        <button type="button"
                                x-show="searchQuery || selectedPartnerId !== 'all'"
                                @click="searchQuery = ''; selectedPartnerId = 'all'"
                                class="px-3 py-2 text-xs font-bold text-gray-500 hover:text-gray-800 cursor-pointer">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- UMKM Products Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">storefront</span>
                            <h2 class="text-sm font-bold text-gray-900">Katalog Produk Mitra UMKM Siap Transaksi</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium"
                              x-text="'Menampilkan ' + filteredUmkmProducts.length + ' produk UMKM'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse min-w-[1050px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5">SKU &amp; BARCODE</th>
                                    <th class="px-4 py-3.5">NAMA PRODUK UMKM</th>
                                    <th class="px-4 py-3.5">MITRA UMKM</th>
                                    <th class="px-4 py-3.5 text-center">KATEGORI</th>
                                    <th class="px-4 py-3.5 text-center">STOK CABANG</th>
                                    <th class="px-4 py-3.5 text-right">HARGA UMUM</th>
                                    <th class="px-4 py-3.5 text-right">HARGA MEMBER</th>
                                    <th class="px-4 py-3.5 text-center">STATUS POS</th>
                                    <th class="px-4 py-3.5 text-center">AKSI TRANSAKSI</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs divide-y divide-gray-100">
                                <template x-if="filteredUmkmProducts.length === 0">
                                    <tr>
                                        <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                                            <span class="material-symbols-rounded text-4xl text-gray-300 block mb-2">handshake</span>
                                            <p class="font-bold text-gray-700 text-sm">Belum ada produk Mitra UMKM yang dikirim ke cabang ini.</p>
                                            <p class="text-xs text-gray-500 mt-1">Produk UMKM dari Gudang Pusat harus dikirim terlebih dahulu ke cabang ini melalui menu <strong>Kirim Barang</strong> di akun Manager, lalu klik <strong>Load Data Produk UMKM</strong>.</p>
                                            <button type="button"
                                                    @click="loadDataProdukUmkm(false)"
                                                    class="mt-4 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer">
                                                <span class="material-symbols-rounded text-[16px]">cloud_download</span>
                                                <span>Load Data Produk UMKM Cabang Ini</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <template x-for="p in filteredUmkmProducts" :key="p.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-4 py-3.5 whitespace-nowrap">
                                            <div class="font-mono font-bold text-gray-800" x-text="p.sku"></div>
                                            <div class="font-mono text-[10.5px] text-gray-400" x-text="p.barcode"></div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-gray-900" x-text="p.name"></div>
                                            <div class="text-[10.5px] text-gray-400" x-text="'Lokasi Rak: ' + (p.rack_location || 'Rak UMKM')"></div>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                                  x-text="p.supplier_name"></span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <span class="bg-[#eef2ff] text-[#0052cc] text-[10.5px] font-bold px-2.5 py-0.5 rounded-full"
                                                  x-text="p.category"></span>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <div class="font-mono font-black text-xs"
                                                 :class="p.branch_stock_qty > 0 ? 'text-gray-900' : 'text-red-500'"
                                                 x-text="p.branch_stock_qty + ' ' + (p.unit || 'Pcs')"></div>
                                            <template x-if="p.branch_stock_qty <= 0 && p.warehouse_stock_qty > 0">
                                                <div class="text-[10px] text-amber-700 font-semibold"
                                                     x-text="'Gudang: ' + p.warehouse_stock_qty + ' ' + (p.unit || 'Pcs')"></div>
                                            </template>
                                        </td>
                                        <td class="px-4 py-3.5 text-right whitespace-nowrap font-mono font-bold text-gray-900"
                                            x-text="formatRupiah(p.price)"></td>
                                        <td class="px-4 py-3.5 text-right whitespace-nowrap font-mono font-semibold text-emerald-600"
                                            x-text="formatRupiah(p.member_price || p.price)"></td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <template x-if="p.branch_stock_qty > 0">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Siap Transaksi</span>
                                                </span>
                                            </template>
                                            <template x-if="p.branch_stock_qty <= 0">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500 border border-gray-200">
                                                    <span>Stok Cabang Habis</span>
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="addToPosCart(p, false)"
                                                        :disabled="p.branch_stock_qty <= 0"
                                                        class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 rounded-lg font-bold text-[11px] inline-flex items-center gap-1 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                        title="Tambahkan ke Keranjang POS">
                                                    <span class="material-symbols-rounded text-[15px]">add_shopping_cart</span>
                                                    <span>+ Keranjang</span>
                                                </button>
                                                <button type="button"
                                                        @click="addToPosCart(p, true)"
                                                        :disabled="p.branch_stock_qty <= 0"
                                                        class="px-2.5 py-1.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-lg font-bold text-[11px] inline-flex items-center gap-1 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                        title="Langsung Buat Transaksi di Layar POS">
                                                    <span class="material-symbols-rounded text-[15px]">point_of_sale</span>
                                                    <span>Buat Transaksi</span>
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
        </div>
    </main>

    <!-- Toast Notification -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-xs font-bold"
         :class="toastType === 'error' ? 'bg-red-900 text-white border-red-800' : 'bg-gray-900 text-white border-gray-800'">
        <span class="material-symbols-rounded text-emerald-400" x-show="toastType === 'success'">check_circle</span>
        <span class="material-symbols-rounded text-red-400" x-show="toastType === 'error'">error</span>
        <span x-text="toastMessage"></span>
    </div>
</div>
@endsection
