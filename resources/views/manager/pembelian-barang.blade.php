@extends('layouts.app')

@section('title', 'Pembelian Barang - Manager ILP POS')

@section('content')
<div x-data="{
    // Active Tab: 'list' | 'create' | 'receive' | 'price_history'
    activeTab: (['list', 'create', 'receive', 'price_history'].includes(new URLSearchParams(window.location.search).get('tab')) ? new URLSearchParams(window.location.search).get('tab') : 'list'),

    // Shared Metadata
    suppliers: [],
    products: [],
    warehouseName: 'Gudang Pusat (DC)',
    nextPurchaseNumber: '',
    isLoading: false,
    isSubmitting: false,

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
        this.toastTimer = setTimeout(() => { this.showToast = false; }, 3800);
    },

    formatRupiah(val) {
        const n = Number(val) || 0;
        const abs = Math.abs(n);
        const formatted = new Intl.NumberFormat('id-ID').format(abs);
        return (n < 0 ? '-Rp' : 'Rp') + formatted;
    },

    formatQty(val) {
        const n = Number(val) || 0;
        return Number.isInteger(n) ? n.toString() : n.toFixed(2).replace(/\.?0+$/, '');
    },

    // ==========================================
    // TAB 1: DAFTAR PEMBELIAN
    // ==========================================
    purchases: [],
    purchaseSummary: {
        total_purchases: 0,
        waiting_receipt: 0,
        partial_receipt: 0,
        total_value: 0
    },
    listMeta: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
    listFilters: {
        search: '',
        supplier_id: 'all',
        status: 'all',
        date_from: '',
        date_to: '',
        page: 1
    },
    listSearchTimer: null,

    // Detail Modal
    showDetailModal: false,
    selectedPurchase: null,
    isLoadingDetail: false,

    // ==========================================
    // TAB 2: BUAT / EDIT PEMBELIAN
    // ==========================================
    editingPurchaseId: null,
    form: {
        purchase_number: '',
        purchase_date: '{{ date('Y-m-d') }}',
        supplier_id: '',
        supplier_invoice_number: '',
        notes: '',
        items: []
    },
    productSearchQuery: '',
    showProductDropdown: false,

    // Quick Supplier Modal
    showSupplierModal: false,
    isSavingSupplier: false,
    supplierSubmitKey: '',
    newSupplier: {
        name: '',
        owner_name: '',
        phone: '',
        address: '',
        category: 'Supplier Barang'
    },
    openSupplierModal() {
        if (!this.supplierSubmitKey) {
            this.supplierSubmitKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('sup-' + Date.now());
        }
        this.showSupplierModal = true;
    },

    // ==========================================
    // TAB 3: PENERIMAAN BARANG
    // ==========================================
    pendingPurchases: [],
    receiveSearch: '',
    showReceiveModal: false,
    receivingPurchase: null,
    receiveForm: {
        received_at: '{{ date('Y-m-d\TH:i') }}',
        notes: '',
        items: []
    },

    // ==========================================
    // TAB 4: RIWAYAT HARGA BELI
    // ==========================================
    priceHistories: [],
    priceSummary: {
        products_with_history: 0,
        total_price_changes: 0,
        products_with_increase: 0,
        products_with_decrease: 0
    },
    priceChartSeries: [],
    priceMeta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    priceFilters: {
        search: '',
        supplier_id: 'all',
        date_from: '',
        date_to: '',
        change_type: 'all',
        sort: 'latest',
        page: 1
    },
    priceSearchTimer: null,
    hoveredChartPoint: null,

    // Modal Detail Riwayat Harga Produk
    showPriceDetailModal: false,
    productPriceDetail: null,
    isLoadingPriceDetail: false,
    hoveredModalPoint: null,

    // ==========================================
    // INITIALIZATION & DATA LOADING
    // ==========================================
    async initPage() {
        await this.loadMeta();
        await Promise.all([
            this.loadPurchases(),
            this.loadPendingReceipts(),
            this.loadPriceHistory()
        ]);
        if (!this.editingPurchaseId && !this.form.purchase_number) {
            this.form.purchase_number = this.nextPurchaseNumber;
        }
    },

    async refreshAll() {
        await this.loadMeta();
        await Promise.all([
            this.loadPurchases(),
            this.loadPendingReceipts(),
            this.loadPriceHistory()
        ]);
        this.triggerToast('Data pembelian dan persediaan berhasil disinkronkan.', 'info');
    },

    async loadMeta() {
        try {
            const res = await fetch('/api/purchases/meta', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && json.data) {
                    this.suppliers = json.data.suppliers || [];
                    this.products = json.data.products || [];
                    this.warehouseName = json.data.warehouse_name || 'Gudang Pusat (DC)';
                    this.nextPurchaseNumber = json.data.next_purchase_number || ('PO-' + new Date().getFullYear() + '-0001');
                    if (!this.editingPurchaseId && !this.form.purchase_number) {
                        this.form.purchase_number = this.nextPurchaseNumber;
                    }
                }
            }
        } catch (e) {
            console.error('Failed to load purchase meta:', e);
        }
    },

    async loadPurchases() {
        this.isLoading = true;
        try {
            const params = new URLSearchParams({
                page: this.listFilters.page,
                per_page: 10
            });
            if (this.listFilters.search.trim()) params.set('search', this.listFilters.search.trim());
            if (this.listFilters.supplier_id !== 'all') params.set('supplier_id', this.listFilters.supplier_id);
            if (this.listFilters.status !== 'all') params.set('status', this.listFilters.status);
            if (this.listFilters.date_from) params.set('date_from', this.listFilters.date_from);
            if (this.listFilters.date_to) params.set('date_to', this.listFilters.date_to);

            const res = await fetch('/api/purchases?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.purchases = json.data || [];
                    this.purchaseSummary = json.summary || this.purchaseSummary;
                    this.listMeta = json.meta || this.listMeta;
                }
            }
        } catch (e) {
            console.error('Failed to load purchases:', e);
        } finally {
            this.isLoading = false;
        }
    },

    onListSearchInput() {
        this.listFilters.page = 1;
        if (this.listSearchTimer) clearTimeout(this.listSearchTimer);
        this.listSearchTimer = setTimeout(() => { this.loadPurchases(); }, 300);
    },

    goToListPage(page) {
        if (page < 1 || page > this.listMeta.last_page) return;
        this.listFilters.page = page;
        this.loadPurchases();
    },

    resetListFilters() {
        this.listFilters = {
            search: '',
            supplier_id: 'all',
            status: 'all',
            date_from: '',
            date_to: '',
            page: 1
        };
        this.loadPurchases();
    },

    async openPurchaseDetail(purchaseOrId) {
        const id = typeof purchaseOrId === 'object' ? purchaseOrId.id : purchaseOrId;
        this.isLoadingDetail = true;
        this.showDetailModal = true;
        try {
            const res = await fetch(`/api/purchases/${id}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.selectedPurchase = json.data;
                }
            }
        } catch (e) {
            this.triggerToast('Gagal memuat detail transaksi pembelian.', 'error');
        } finally {
            this.isLoadingDetail = false;
        }
    },

    getStatusBadge(status) {
        switch (status) {
            case 'DRAFT':
                return { label: 'Draft', class: 'bg-slate-100 text-slate-700 border-slate-300' };
            case 'MENUNGGU_PENERIMAAN':
                return { label: 'Menunggu Penerimaan', class: 'bg-amber-50 text-amber-700 border-amber-300' };
            case 'DITERIMA_SEBAGIAN':
                return { label: 'Diterima Sebagian', class: 'bg-blue-50 text-[#0052cc] border-blue-200' };
            case 'SELESAI':
                return { label: 'Selesai', class: 'bg-emerald-50 text-emerald-700 border-emerald-300' };
            case 'DIBATALKAN':
                return { label: 'Dibatalkan', class: 'bg-rose-50 text-rose-700 border-rose-200' };
            default:
                return { label: status || '-', class: 'bg-gray-100 text-gray-700 border-gray-300' };
        }
    },

    // ==========================================
    // TAB 2: BUAT / EDIT PEMBELIAN LOGIC
    // ==========================================
    switchToCreateNew() {
        this.editingPurchaseId = null;
        this.form = {
            purchase_number: this.nextPurchaseNumber,
            purchase_date: '{{ date('Y-m-d') }}',
            supplier_id: this.suppliers.length > 0 ? String(this.suppliers[0].id) : '',
            supplier_invoice_number: '',
            notes: '',
            items: []
        };
        this.productSearchQuery = '';
        this.activeTab = 'create';
    },

    editPurchase(p) {
        if (!p.can_edit) {
            this.triggerToast('Transaksi yang sudah memiliki penerimaan tidak dapat diedit.', 'error');
            return;
        }
        this.editingPurchaseId = p.id;
        this.form = {
            purchase_number: p.purchase_number,
            purchase_date: p.purchase_date || '{{ date('Y-m-d') }}',
            supplier_id: p.supplier_id ? String(p.supplier_id) : '',
            supplier_invoice_number: p.supplier_invoice_number || '',
            notes: p.notes || '',
            items: (p.items || []).map(it => {
                const prod = this.products.find(pr => pr.id === it.product_id);
                return {
                    product_id: it.product_id,
                    name: it.product_name,
                    sku: it.sku,
                    unit: it.unit || 'Pcs',
                    conversion_factor: it.conversion_factor || 1,
                    unit_options: prod?.unit_options || [{ unit_name: it.unit || 'Pcs', conversion_factor: it.conversion_factor || 1 }],
                    qty: Number(it.qty) || 1,
                    purchase_price: Number(it.purchase_price) || 0,
                    discount_amount: Number(it.discount_amount) || 0
                };
            })
        };
        this.showDetailModal = false;
        this.activeTab = 'create';
    },

    get filteredCatalogProducts() {
        const q = (this.productSearchQuery || '').trim().toLowerCase();
        const list = this.products || [];
        if (!q) return list.slice(0, 12);
        return list.filter(p =>
            (p.name || '').toLowerCase().includes(q) ||
            (p.sku || '').toLowerCase().includes(q) ||
            (p.barcode || '').toLowerCase().includes(q)
        ).slice(0, 15);
    },

    addProductToForm(prod) {
        if (!prod) return;
        const existingIdx = this.form.items.findIndex(it => Number(it.product_id) === Number(prod.id));
        if (existingIdx !== -1) {
            this.form.items[existingIdx].qty = Number(this.form.items[existingIdx].qty || 0) + 1;
            this.productSearchQuery = '';
            this.showProductDropdown = false;
            this.triggerToast(`Jumlah ${prod.name} ditambah menjadi ${this.form.items[existingIdx].qty}.`, 'info');
            return;
        }

        const basePrice = Number(prod.last_cost_price ?? prod.cost_price ?? 0);
        this.form.items.push({
            product_id: prod.id,
            name: prod.name,
            sku: prod.sku,
            unit: prod.unit || 'Pcs',
            conversion_factor: 1,
            unit_options: prod.unit_options || [{ unit_name: prod.unit || 'Pcs', conversion_factor: 1 }],
            qty: 1,
            purchase_price: basePrice,
            discount_amount: 0
        });
        this.productSearchQuery = '';
        this.showProductDropdown = false;
    },

    onFormUnitChange(item) {
        const opt = (item.unit_options || []).find(u => u.unit_name === item.unit);
        const newFactor = opt ? Math.max(1, Number(opt.conversion_factor)) : 1;
        const oldFactor = Math.max(1, Number(item.conversion_factor || 1));
        if (newFactor !== oldFactor) {
            // Adjust suggested unit price proportionally when switching between base unit & wholesale unit
            const baseUnitCost = Number(item.purchase_price || 0) / oldFactor;
            item.purchase_price = Math.round(baseUnitCost * newFactor);
            item.conversion_factor = newFactor;
        }
    },

    removeFormItem(index) {
        this.form.items.splice(index, 1);
    },

    getItemSubtotal(item) {
        const qty = Math.max(0, Number(item.qty) || 0);
        const price = Math.max(0, Number(item.purchase_price) || 0);
        const disc = Math.max(0, Number(item.discount_amount) || 0);
        return Math.max(0, (qty * price) - disc);
    },

    get formTotalQty() {
        return this.form.items.reduce((sum, it) => sum + Math.max(0, Number(it.qty) || 0), 0);
    },

    get formTotalDiscount() {
        return this.form.items.reduce((sum, it) => sum + Math.max(0, Number(it.discount_amount) || 0), 0);
    },

    get formGrandTotal() {
        return this.form.items.reduce((sum, it) => sum + this.getItemSubtotal(it), 0);
    },

    async submitPurchase(targetStatus) {
        if (this.isSubmitting) return;
        if (!this.form.supplier_id) {
            this.triggerToast('Pilih supplier terlebih dahulu.', 'error');
            return;
        }
        if (!this.form.purchase_date) {
            this.triggerToast('Tanggal pembelian wajib diisi.', 'error');
            return;
        }
        if (!this.form.items || this.form.items.length === 0) {
            this.triggerToast('Tambahkan minimal 1 produk ke daftar pembelian.', 'error');
            return;
        }

        for (const it of this.form.items) {
            if ((Number(it.qty) || 0) <= 0) {
                this.triggerToast(`Jumlah pembelian untuk ${it.name} harus lebih dari 0.`, 'error');
                return;
            }
            if ((Number(it.purchase_price) || 0) < 0) {
                this.triggerToast(`Harga beli untuk ${it.name} tidak boleh negatif.`, 'error');
                return;
            }
        }

        this.isSubmitting = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('pur-' + Date.now());
        try {
            const url = this.editingPurchaseId ? `/api/purchases/${this.editingPurchaseId}` : '/api/purchases';
            const method = this.editingPurchaseId ? 'PUT' : 'POST';

            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    purchase_number: this.form.purchase_number,
                    purchase_date: this.form.purchase_date,
                    supplier_id: Number(this.form.supplier_id),
                    supplier_invoice_number: this.form.supplier_invoice_number,
                    notes: this.form.notes,
                    status: targetStatus,
                    items: this.form.items.map(it => ({
                        product_id: Number(it.product_id),
                        unit: it.unit,
                        conversion_factor: Number(it.conversion_factor) || 1,
                        qty: Number(it.qty),
                        purchase_price: Number(it.purchase_price),
                        discount_amount: Number(it.discount_amount) || 0
                    }))
                })
            });

            const json = await res.json();
            if (!res.ok || !json.success) {
                this.triggerToast(json.message || 'Gagal menyimpan transaksi pembelian.', 'error');
                return;
            }

            this.triggerToast(json.message, 'success');
            this.editingPurchaseId = null;
            await this.loadMeta();
            this.form = {
                purchase_number: this.nextPurchaseNumber,
                purchase_date: '{{ date('Y-m-d') }}',
                supplier_id: '',
                supplier_invoice_number: '',
                notes: '',
                items: []
            };
            await Promise.all([
                this.loadPurchases(),
                this.loadPendingReceipts()
            ]);
            this.activeTab = 'list';
        } catch (e) {
            this.triggerToast('Terjadi kesalahan koneksi saat menyimpan pembelian.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    async cancelPurchase(p) {
        if (this.isSubmitting) return;
        if (!confirm(`Batalkan transaksi pembelian ${p.purchase_number}?`)) return;
        this.isSubmitting = true;
        try {
            const res = await fetch(`/api/purchases/${p.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: 'DIBATALKAN' })
            });
            const json = await res.json();
            if (res.ok && json.success) {
                this.triggerToast(`Pembelian ${p.purchase_number} berhasil dibatalkan.`, 'info');
                this.showDetailModal = false;
                await Promise.all([this.loadPurchases(), this.loadPendingReceipts()]);
            } else {
                this.triggerToast(json.message || 'Gagal membatalkan pembelian.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi gagal saat membatalkan pembelian.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    async createQuickSupplier() {
        if (this.isSavingSupplier) return;
        const trimmedName = (this.newSupplier.name || '').trim();
        if (!trimmedName) {
            this.triggerToast('Nama supplier wajib diisi.', 'error');
            return;
        }
        if (!this.supplierSubmitKey) {
            this.supplierSubmitKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('sup-' + Date.now());
        }
        this.isSavingSupplier = true;
        try {
            const res = await fetch('/api/purchases/suppliers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': this.supplierSubmitKey
                },
                body: JSON.stringify({
                    ...this.newSupplier,
                    name: trimmedName
                })
            });
            const json = await res.json();
            if (res.ok && json.success && json.data) {
                const exists = this.suppliers.some(s => Number(s.id) === Number(json.data.id));
                if (!exists) {
                    this.suppliers.push(json.data);
                    this.suppliers.sort((a, b) => a.name.localeCompare(b.name));
                }
                this.form.supplier_id = String(json.data.id);
                this.newSupplier = { name: '', owner_name: '', phone: '', address: '', category: 'Supplier Barang' };
                this.supplierSubmitKey = '';
                this.showSupplierModal = false;
                this.triggerToast(json.message, 'success');
            } else {
                this.triggerToast(json.message || 'Gagal menambah supplier.', 'error');
            }
        } catch (e) {
            this.triggerToast('Koneksi gagal saat menambah supplier.', 'error');
        } finally {
            this.isSavingSupplier = false;
        }
    },

    // ==========================================
    // TAB 3: PENERIMAAN BARANG LOGIC
    // ==========================================
    async loadPendingReceipts() {
        try {
            const res = await fetch('/api/purchases?pending_receipt=1&per_page=100', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.pendingPurchases = json.data || [];
                }
            }
        } catch (e) {
            console.error('Failed to load pending receipts:', e);
        }
    },

    get filteredPendingPurchases() {
        const q = (this.receiveSearch || '').trim().toLowerCase();
        if (!q) return this.pendingPurchases;
        return this.pendingPurchases.filter(p =>
            (p.purchase_number || '').toLowerCase().includes(q) ||
            (p.supplier_name || '').toLowerCase().includes(q) ||
            (p.supplier_invoice_number || '').toLowerCase().includes(q)
        );
    },

    openReceiveModal(p) {
        this.receivingPurchase = JSON.parse(JSON.stringify(p));
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        const localIso = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

        this.receiveForm = {
            received_at: localIso,
            notes: '',
            items: (p.items || []).map(it => {
                const remaining = Math.max(0, Number(it.remaining_qty) || 0);
                // Effective unit purchase price
                const netUnitPrice = (Number(it.qty) > 0 && Number(it.discount_amount) > 0)
                    ? Math.max(0, Math.round((Number(it.subtotal) / Number(it.qty)) * 100) / 100)
                    : Number(it.purchase_price);
                return {
                    purchase_item_id: it.id,
                    product_id: it.product_id,
                    product_name: it.product_name,
                    sku: it.sku,
                    unit: it.unit || 'Pcs',
                    conversion_factor: it.conversion_factor || 1,
                    qty: Number(it.qty) || 0,
                    received_qty: Number(it.received_qty) || 0,
                    remaining_qty: remaining,
                    qty_received_now: remaining,
                    actual_unit_price: netUnitPrice,
                    condition: 'BAIK',
                    expired_date: '',
                    note: ''
                };
            })
        };
        this.showDetailModal = false;
        this.showReceiveModal = true;
    },

    fillAllRemaining() {
        this.receiveForm.items.forEach(it => {
            it.qty_received_now = it.remaining_qty;
        });
    },

    clampReceiveQty(it) {
        let val = Number(it.qty_received_now);
        if (isNaN(val) || val < 0) val = 0;
        if (val > it.remaining_qty) {
            val = it.remaining_qty;
            this.triggerToast(`Maksimal penerimaan untuk ${it.product_name} adalah ${this.formatQty(it.remaining_qty)} ${it.unit}.`, 'warning');
        }
        it.qty_received_now = val;
    },

    getAfterReceiveRemaining(it) {
        const nowQty = Math.max(0, Number(it.qty_received_now) || 0);
        return Math.max(0, Math.round((it.remaining_qty - nowQty) * 100) / 100);
    },

    get totalReceivingNowQty() {
        return this.receiveForm.items.reduce((sum, it) => sum + Math.max(0, Number(it.qty_received_now) || 0), 0);
    },

    async confirmReceiveGoods() {
        if (this.isSubmitting) return;
        if (!this.receivingPurchase) return;
        if (this.totalReceivingNowQty <= 0) {
            this.triggerToast('Masukkan minimal 1 barang dengan jumlah diterima lebih dari 0.', 'error');
            return;
        }

        for (const it of this.receiveForm.items) {
            const nowQty = Number(it.qty_received_now) || 0;
            if (nowQty > it.remaining_qty + 0.0001) {
                this.triggerToast(`Jumlah diterima untuk ${it.product_name} melebihi sisa pembelian.`, 'error');
                return;
            }
        }

        this.isSubmitting = true;
        const idemKey = window.createIdempotencyKey ? window.createIdempotencyKey() : ('rcv-' + Date.now());
        try {
            const res = await fetch(`/api/purchases/${this.receivingPurchase.id}/receive`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Idempotency-Key': idemKey
                },
                body: JSON.stringify({
                    received_at: this.receiveForm.received_at,
                    notes: this.receiveForm.notes,
                    items: this.receiveForm.items.map(it => ({
                        purchase_item_id: it.purchase_item_id,
                        qty_received_now: Number(it.qty_received_now) || 0,
                        actual_unit_price: Number(it.actual_unit_price) || 0,
                        condition: it.condition || 'BAIK',
                        expired_date: it.expired_date || null,
                        note: it.note || ''
                    }))
                })
            });

            const json = await res.json();
            if (!res.ok || !json.success) {
                this.triggerToast(json.message || 'Gagal mengonfirmasi penerimaan barang.', 'error');
                return;
            }

            this.showReceiveModal = false;
            this.triggerToast(json.message, 'success');

            // Refresh all tabs & broadcast realtime update
            await Promise.all([
                this.loadMeta(),
                this.loadPurchases(),
                this.loadPendingReceipts(),
                this.loadPriceHistory()
            ]);
            localStorage.removeItem('pos_ilp_inventory_products_v1');
            window.dispatchEvent(new CustomEvent('pos:inventory_updated'));
        } catch (e) {
            this.triggerToast('Terjadi kesalahan koneksi saat konfirmasi penerimaan.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    // ==========================================
    // TAB 4: RIWAYAT HARGA BELI LOGIC
    // ==========================================
    async loadPriceHistory() {
        try {
            const params = new URLSearchParams({
                page: this.priceFilters.page,
                per_page: 15,
                sort: this.priceFilters.sort
            });
            if (this.priceFilters.search.trim()) params.set('search', this.priceFilters.search.trim());
            if (this.priceFilters.supplier_id !== 'all') params.set('supplier_id', this.priceFilters.supplier_id);
            if (this.priceFilters.date_from) params.set('date_from', this.priceFilters.date_from);
            if (this.priceFilters.date_to) params.set('date_to', this.priceFilters.date_to);
            if (this.priceFilters.change_type !== 'all') params.set('change_type', this.priceFilters.change_type);

            const res = await fetch('/api/purchases/price-history?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.priceHistories = json.data || [];
                    this.priceSummary = json.summary || this.priceSummary;
                    this.priceChartSeries = json.chart_series || [];
                    this.priceMeta = json.meta || this.priceMeta;
                }
            }
        } catch (e) {
            console.error('Failed to load price history:', e);
        }
    },

    onPriceSearchInput() {
        this.priceFilters.page = 1;
        if (this.priceSearchTimer) clearTimeout(this.priceSearchTimer);
        this.priceSearchTimer = setTimeout(() => { this.loadPriceHistory(); }, 300);
    },

    goToPricePage(page) {
        if (page < 1 || page > this.priceMeta.last_page) return;
        this.priceFilters.page = page;
        this.loadPriceHistory();
    },

    resetPriceFilters() {
        this.priceFilters = {
            search: '',
            supplier_id: 'all',
            date_from: '',
            date_to: '',
            change_type: 'all',
            sort: 'latest',
            page: 1
        };
        this.loadPriceHistory();
    },

    async openProductPriceDetail(productId) {
        this.isLoadingPriceDetail = true;
        this.showPriceDetailModal = true;
        this.hoveredModalPoint = null;
        try {
            const res = await fetch(`/api/purchases/price-history/product/${productId}`, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success) {
                    this.productPriceDetail = json.data;
                }
            }
        } catch (e) {
            this.triggerToast('Gagal memuat histori harga produk.', 'error');
        } finally {
            this.isLoadingPriceDetail = false;
        }
    },

    // Clean SVG Chart Helper
    buildSvgChart(series, priceKey = 'price') {
        if (!Array.isArray(series) || series.length === 0) {
            return { points: [], polyline: '', areaPath: '', min: 0, max: 0, mid: 0 };
        }
        const width = 680;
        const height = 190;
        const padX = 36;
        const padY = 24;
        const values = series.map(s => Number(s[priceKey]) || 0);
        let minVal = Math.min(...values);
        let maxVal = Math.max(...values);
        if (minVal === maxVal) {
            minVal = Math.max(0, minVal * 0.9);
            maxVal = maxVal * 1.1 || 1000;
        } else {
            const padding = (maxVal - minVal) * 0.15;
            minVal = Math.max(0, minVal - padding);
            maxVal = maxVal + padding;
        }
        const range = Math.max(1, maxVal - minVal);
        const usableW = width - (padX * 2);
        const usableH = height - (padY * 2);

        const points = series.map((item, idx) => {
            const x = series.length === 1 ? width / 2 : padX + (idx / (series.length - 1)) * usableW;
            const val = Number(item[priceKey]) || 0;
            const y = padY + usableH - ((val - minVal) / range) * usableH;
            return {
                ...item,
                x: Math.round(x * 10) / 10,
                y: Math.round(y * 10) / 10,
                val
            };
        });

        const polyline = points.map(p => `${p.x},${p.y}`).join(' ');
        const first = points[0];
        const last = points[points.length - 1];
        const baselineY = height - padY;
        const areaPath = points.length > 1
            ? `M ${first.x},${baselineY} L ${points.map(p => `${p.x},${p.y}`).join(' L ')} L ${last.x},${baselineY} Z`
            : '';

        return {
            points,
            polyline,
            areaPath,
            min: Math.round(minVal),
            max: Math.round(maxVal),
            mid: Math.round((minVal + maxVal) / 2)
        };
    }
}"
x-init="initPage()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manager -->
    <x-manager-sidebar :active="'pembelian_barang'" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden relative">

        <!-- Top Header Bar -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 z-10 shrink-0 select-none">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider hidden sm:inline">Persediaan</span>
                <span class="material-symbols-rounded text-gray-400 text-sm hidden sm:inline">chevron_right</span>
                <h1 class="text-sm sm:text-base font-bold text-gray-800 truncate">Pembelian Barang</h1>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <x-notification-bell role="MANAGER" />
                <button type="button"
                        @click="refreshAll()"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer"
                        title="Sinkronisasi Data">
                    <span class="material-symbols-rounded text-[20px]" :class="isLoading ? 'animate-spin text-[#0052cc]' : ''">sync</span>
                </button>
                <x-realtime-network-badge />
                <div class="h-6 w-px bg-gray-200 mx-0.5 hidden sm:block"></div>
                <button type="button"
                        @click="switchToCreateNew()"
                        class="px-3.5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors">
                    <span class="material-symbols-rounded text-[18px]">add</span>
                    <span class="hidden sm:inline">Buat Pembelian</span>
                </button>
            </div>
        </header>

        <!-- Scrollable Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1440px] mx-auto space-y-6">

                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">Pembelian Barang</h2>
                        <p class="text-xs sm:text-[13px] text-gray-500 font-medium mt-1">
                            Kelola transaksi pembelian produk, penerimaan barang, dan riwayat harga beli supplier.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500 bg-white border border-gray-200 rounded-xl px-3.5 py-2 shadow-2xs self-start md:self-auto">
                        <span class="material-symbols-rounded text-[18px] text-[#0052cc]">warehouse</span>
                        <span>Pusat Penerimaan: <strong class="text-gray-800" x-text="warehouseName">Gudang Pusat (DC)</strong></span>
                    </div>
                </div>

                <!-- Internal Tab Navigation -->
                <div class="bg-white border border-gray-200 rounded-2xl p-2 shadow-xs overflow-x-auto">
                    <div class="flex items-center gap-2 min-w-max">
                        <button type="button"
                                @click="activeTab = 'list'"
                                :class="activeTab === 'list' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">receipt_long</span>
                            <span>Daftar Pembelian</span>
                            <span :class="activeTab === 'list' ? 'bg-white text-[#0052cc]' : 'bg-slate-200 text-slate-700'"
                                  class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                                  x-text="purchaseSummary.total_purchases">0</span>
                        </button>

                        <button type="button"
                                @click="activeTab = 'create'"
                                :class="activeTab === 'create' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">add_shopping_cart</span>
                            <span x-text="editingPurchaseId ? 'Edit Pembelian' : 'Buat Pembelian'">Buat Pembelian</span>
                        </button>

                        <button type="button"
                                @click="activeTab = 'receive'; loadPendingReceipts();"
                                :class="activeTab === 'receive' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">inventory</span>
                            <span>Penerimaan Barang</span>
                            <span :class="activeTab === 'receive' ? 'bg-white text-[#0052cc]' : 'bg-amber-100 text-amber-800'"
                                  class="text-[10.5px] font-extrabold px-2 py-0.5 rounded-full"
                                  x-text="pendingPurchases.length">0</span>
                        </button>

                        <button type="button"
                                @click="activeTab = 'price_history'; loadPriceHistory();"
                                :class="activeTab === 'price_history' ? 'bg-[#0052cc] text-white shadow-xs' : 'bg-slate-50 hover:bg-slate-100 text-slate-600'"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">trending_up</span>
                            <span>Riwayat Harga Beli</span>
                        </button>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 1: DAFTAR PEMBELIAN                                   -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'list'" class="space-y-6">

                    <!-- Summary Cards (4 Cards) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL PEMBELIAN</span>
                                <span class="material-symbols-rounded text-[22px] text-[#0052cc]">shopping_bag</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-gray-900 font-mono" x-text="purchaseSummary.total_purchases">0</span>
                                <span class="text-[11px] font-semibold text-gray-400">Transaksi</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-amber-200 border-l-4 border-l-amber-500 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">MENUNGGU PENERIMAAN</span>
                                <span class="material-symbols-rounded text-[22px] text-amber-500">schedule</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-amber-600 font-mono" x-text="purchaseSummary.waiting_receipt">0</span>
                                <span class="text-[11px] font-semibold text-amber-700">Siap diterima</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-blue-200 border-l-4 border-l-[#0052cc] p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-[#0052cc] tracking-wider uppercase">DITERIMA SEBAGIAN</span>
                                <span class="material-symbols-rounded text-[22px] text-[#0052cc]">timelapse</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-[#0052cc] font-mono" x-text="purchaseSummary.partial_receipt">0</span>
                                <span class="text-[11px] font-semibold text-blue-700">Parsial masuk</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL NILAI PEMBELIAN</span>
                                <span class="material-symbols-rounded text-[22px] text-emerald-600">payments</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between gap-2">
                                <span class="text-xl sm:text-2xl font-black text-gray-900 font-mono truncate" x-text="formatRupiah(purchaseSummary.total_value)">Rp0</span>
                                <span class="text-[11px] font-bold text-gray-400">IDR</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filters & Actions Toolbar -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs space-y-3">
                        <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 flex-1">
                                <!-- Search -->
                                <div class="relative lg:col-span-2">
                                    <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                                    <input type="text"
                                           x-model="listFilters.search"
                                           @input="onListSearchInput()"
                                           placeholder="Cari no. pembelian, invoice, atau supplier..."
                                           class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-xs font-medium bg-slate-50/50">
                                </div>

                                <!-- Supplier Filter -->
                                <div class="relative">
                                    <select x-model="listFilters.supplier_id"
                                            @change="listFilters.page = 1; loadPurchases();"
                                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                        <option value="all">Semua Supplier</option>
                                        <template x-for="sup in suppliers" :key="sup.id">
                                            <option :value="sup.id" x-text="sup.name"></option>
                                        </template>
                                    </select>
                                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                                </div>

                                <!-- Status Filter -->
                                <div class="relative">
                                    <select x-model="listFilters.status"
                                            @change="listFilters.page = 1; loadPurchases();"
                                            class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                        <option value="all">Semua Status</option>
                                        <option value="DRAFT">Draft</option>
                                        <option value="MENUNGGU_PENERIMAAN">Menunggu Penerimaan</option>
                                        <option value="DITERIMA_SEBAGIAN">Diterima Sebagian</option>
                                        <option value="SELESAI">Selesai</option>
                                        <option value="DIBATALKAN">Dibatalkan</option>
                                    </select>
                                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                                </div>

                                <!-- Date Filter -->
                                <div class="flex items-center gap-1.5">
                                    <input type="date"
                                           x-model="listFilters.date_from"
                                           @change="listFilters.page = 1; loadPurchases();"
                                           title="Tanggal Mulai"
                                           class="w-full px-2.5 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                    <span class="text-gray-400 text-xs">-</span>
                                    <input type="date"
                                           x-model="listFilters.date_to"
                                           @change="listFilters.page = 1; loadPurchases();"
                                           title="Tanggal Akhir"
                                           class="w-full px-2.5 py-2 rounded-xl border border-gray-200 text-xs font-medium text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button"
                                        @click="resetListFilters()"
                                        class="px-3.5 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold cursor-pointer transition-colors">
                                    Reset
                                </button>
                                <button type="button"
                                        @click="switchToCreateNew()"
                                        class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors">
                                    <span class="material-symbols-rounded text-[17px]">add</span>
                                    <span>Buat Pembelian Baru</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Daftar Pembelian -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">receipt_long</span>
                                <h3 class="text-sm font-bold text-gray-900">Daftar Transaksi Pembelian</h3>
                            </div>
                            <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + purchases.length + ' dari ' + listMeta.total + ' transaksi'"></span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[980px] text-xs">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                        <th class="px-4 py-3.5 text-left">No. Pembelian</th>
                                        <th class="px-4 py-3.5 text-left">Tanggal</th>
                                        <th class="px-4 py-3.5 text-left">Supplier</th>
                                        <th class="px-4 py-3.5 text-center">Total Item</th>
                                        <th class="px-4 py-3.5 text-center">Total Qty</th>
                                        <th class="px-4 py-3.5 text-right">Total Pembelian</th>
                                        <th class="px-4 py-3.5 text-center">Status</th>
                                        <th class="px-4 py-3.5 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                    <template x-if="purchases.length === 0">
                                        <tr>
                                            <td colspan="8" class="px-6 py-12 text-center bg-white">
                                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-2.5">
                                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                                                        <span class="material-symbols-rounded text-[26px]">shopping_bag</span>
                                                    </div>
                                                    <p class="font-bold text-gray-800 text-sm">Belum ada transaksi pembelian</p>
                                                    <p class="text-xs text-gray-500">Buat transaksi pembelian baru dari supplier untuk mencatat stok masuk gudang.</p>
                                                    <button type="button"
                                                            @click="switchToCreateNew()"
                                                            class="mt-2 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                                                        <span class="material-symbols-rounded text-[16px]">add</span>
                                                        <span>Buat Pembelian Baru</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="p in purchases" :key="p.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <button type="button"
                                                        @click="openPurchaseDetail(p)"
                                                        class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer block"
                                                        x-text="p.purchase_number"></button>
                                                <span x-show="p.supplier_invoice_number" class="text-[10.5px] text-gray-400 font-mono" x-text="'Inv: ' + p.supplier_invoice_number"></span>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="p.purchase_date_label || p.purchase_date"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap font-semibold text-gray-900" x-text="p.supplier_name"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold text-gray-700" x-text="p.total_items + ' Produk'"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold">
                                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800" x-text="formatQty(p.total_qty)"></span>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-bold text-gray-900" x-text="formatRupiah(p.total_amount)"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                      :class="getStatusBadge(p.status).class"
                                                      x-text="getStatusBadge(p.status).label"></span>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button type="button"
                                                            @click="openPurchaseDetail(p)"
                                                            class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-[11px] cursor-pointer transition-colors">
                                                        Detail
                                                    </button>
                                                    <template x-if="p.can_edit">
                                                        <button type="button"
                                                                @click="editPurchase(p)"
                                                                class="px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-bold text-[11px] cursor-pointer transition-colors">
                                                            Edit
                                                        </button>
                                                    </template>
                                                    <template x-if="p.can_receive">
                                                        <button type="button"
                                                                @click="openReceiveModal(p)"
                                                                class="px-2.5 py-1.5 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-[11px] cursor-pointer transition-colors">
                                                            Terima
                                                        </button>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="border-t border-gray-200 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white text-xs text-gray-500">
                            <span x-text="'Halaman ' + listMeta.current_page + ' dari ' + listMeta.last_page + ' (' + listMeta.total + ' total transaksi)'"></span>
                            <div class="flex items-center gap-1.5" x-show="listMeta.last_page > 1">
                                <button type="button"
                                        @click="goToListPage(listMeta.current_page - 1)"
                                        :disabled="listMeta.current_page <= 1"
                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold cursor-pointer">
                                    Sebelumnya
                                </button>
                                <button type="button"
                                        @click="goToListPage(listMeta.current_page + 1)"
                                        :disabled="listMeta.current_page >= listMeta.last_page"
                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed font-semibold cursor-pointer">
                                    Berikutnya
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 2: BUAT PEMBELIAN                                     -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'create'" style="display: none;" class="space-y-6">

                    <!-- Edit Mode Banner -->
                    <template x-if="editingPurchaseId">
                        <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 text-amber-900 text-xs font-bold">
                                <span class="material-symbols-rounded text-[20px] text-amber-600">edit_document</span>
                                <span>Sedang mengedit transaksi <span class="font-mono underline" x-text="form.purchase_number"></span></span>
                            </div>
                            <button type="button"
                                    @click="switchToCreateNew()"
                                    class="px-3 py-1.5 rounded-lg bg-white border border-amber-300 text-amber-800 hover:bg-amber-100 text-xs font-bold cursor-pointer">
                                Batalkan Edit &amp; Buat Baru
                            </button>
                        </div>
                    </template>

                    <!-- Section A: Informasi Pembelian -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">description</span>
                            <h3 class="text-sm font-bold text-gray-900">A. Informasi Pembelian</h3>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Nomor Pembelian</label>
                                <div class="relative">
                                    <input type="text"
                                           x-model="form.purchase_number"
                                           readonly
                                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 bg-slate-100 text-gray-800 font-mono font-bold focus:outline-none">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold bg-blue-100 text-[#0052cc] px-2 py-0.5 rounded">Otomatis</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Tanggal Pembelian <span class="text-rose-500">*</span></label>
                                <input type="date"
                                       x-model="form.purchase_date"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="font-bold text-gray-700">Supplier <span class="text-rose-500">*</span></label>
                                    <button type="button"
                                            @click="openSupplierModal()"
                                            class="text-[11px] font-bold text-[#0052cc] hover:underline cursor-pointer flex items-center gap-0.5">
                                        <span class="material-symbols-rounded text-[14px]">add</span>
                                        <span>Tambah Supplier</span>
                                    </button>
                                </div>
                                <div class="relative">
                                    <select x-model="form.supplier_id"
                                            class="appearance-none w-full px-3.5 py-2.5 pr-9 rounded-xl border border-gray-300 bg-white focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-semibold text-gray-800 cursor-pointer">
                                        <option value="">-- Pilih Supplier --</option>
                                        <template x-for="sup in suppliers" :key="sup.id">
                                            <option :value="sup.id" x-text="sup.name + (sup.code ? ' (' + sup.code + ')' : '')"></option>
                                        </template>
                                    </select>
                                    <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-gray-700 mb-1.5">Nomor Invoice Supplier</label>
                                <input type="text"
                                       x-model="form.supplier_invoice_number"
                                       placeholder="Contoh: INV-SUP-2026/001"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono font-medium">
                            </div>

                            <div class="md:col-span-2 lg:col-span-4">
                                <label class="block font-bold text-gray-700 mb-1.5">Catatan Pembelian</label>
                                <textarea x-model="form.notes"
                                          rows="2"
                                          placeholder="Catatan pengiriman, termin pembayaran, atau instruksi khusus gudang..."
                                          class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section B: Detail Produk -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-visible">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory_2</span>
                                <h3 class="text-sm font-bold text-gray-900">B. Detail Produk Pembelian</h3>
                            </div>
                            <span class="text-[11px] font-medium text-gray-500">Harga beli dapat diisi bebas sesuai invoice aktual supplier</span>
                        </div>

                        <div class="p-6 space-y-5">
                            <!-- Search & Add Product Box -->
                            <div class="relative max-w-2xl" @click.away="showProductDropdown = false">
                                <label class="block text-xs font-bold text-gray-700 mb-1.5">Cari &amp; Tambah Produk dari Master Produk (Non-Konsinyasi)</label>
                                <div class="relative">
                                    <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                                    <input type="text"
                                           x-model="productSearchQuery"
                                           @focus="showProductDropdown = true"
                                           @input="showProductDropdown = true"
                                           placeholder="Ketik nama produk, SKU, atau scan barcode..."
                                           class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-xs font-medium bg-slate-50/50">
                                </div>

                                <!-- Product Search Dropdown -->
                                <div x-show="showProductDropdown"
                                     style="display: none;"
                                     class="absolute z-30 mt-1.5 w-full bg-white rounded-xl border border-gray-200 shadow-xl max-h-64 overflow-y-auto divide-y divide-gray-100">
                                    <template x-if="filteredCatalogProducts.length === 0">
                                        <div class="px-4 py-3 text-xs text-gray-500">Produk reguler tidak ditemukan.</div>
                                    </template>
                                    <template x-for="prod in filteredCatalogProducts" :key="prod.id">
                                        <button type="button"
                                                @click="addProductToForm(prod)"
                                                class="w-full px-4 py-2.5 text-left hover:bg-blue-50/70 flex items-center justify-between gap-3 cursor-pointer transition-colors">
                                            <div class="min-w-0">
                                                <div class="font-bold text-gray-900 text-xs truncate" x-text="prod.name"></div>
                                                <div class="text-[11px] text-gray-500 font-mono">
                                                    <span x-text="prod.sku"></span> &bull; Stok: <span x-text="prod.stock + ' ' + prod.unit"></span>
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="text-[11px] font-bold text-[#0052cc] font-mono" x-text="formatRupiah(prod.last_cost_price ?? prod.cost_price)"></div>
                                                <div class="text-[10px] text-gray-400">Hrg Beli Terakhir</div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <!-- Items Table -->
                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="w-full text-left border-collapse min-w-[920px] text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px] whitespace-nowrap">
                                            <th class="px-4 py-3">Produk</th>
                                            <th class="px-3 py-3">SKU</th>
                                            <th class="px-3 py-3 w-[120px]">Satuan</th>
                                            <th class="px-3 py-3 w-[110px]">Jumlah</th>
                                            <th class="px-3 py-3 w-[160px]">Harga Beli (Rp)</th>
                                            <th class="px-3 py-3 w-[140px]">Diskon (Rp)</th>
                                            <th class="px-4 py-3 text-right w-[150px]">Subtotal</th>
                                            <th class="px-3 py-3 text-center w-[70px]">Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-if="form.items.length === 0">
                                            <tr>
                                                <td colspan="8" class="px-6 py-10 text-center text-gray-400">
                                                    Belum ada produk dipilih. Gunakan kolom pencarian di atas untuk menambahkan produk.
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-for="(item, idx) in form.items" :key="item.product_id + '-' + idx">
                                            <tr class="hover:bg-slate-50/60">
                                                <td class="px-4 py-3 font-bold text-gray-900" x-text="item.name"></td>
                                                <td class="px-3 py-3 font-mono text-gray-600 whitespace-nowrap" x-text="item.sku"></td>
                                                <td class="px-3 py-3">
                                                    <select x-model="item.unit"
                                                            @change="onFormUnitChange(item)"
                                                            class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 bg-white font-semibold text-xs focus:outline-none focus:border-[#0052cc]">
                                                        <template x-for="opt in item.unit_options" :key="opt.unit_name">
                                                            <option :value="opt.unit_name" x-text="opt.unit_name + (opt.conversion_factor > 1 ? ' (x' + opt.conversion_factor + ')' : '')"></option>
                                                        </template>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-3">
                                                    <input type="number"
                                                           min="1"
                                                           step="any"
                                                           x-model.number="item.qty"
                                                           class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 font-mono font-bold text-xs focus:outline-none focus:border-[#0052cc]">
                                                </td>
                                                <td class="px-3 py-3">
                                                    <input type="number"
                                                           min="0"
                                                           step="any"
                                                           x-model.number="item.purchase_price"
                                                           class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 bg-white font-mono font-bold text-xs focus:outline-none focus:border-[#0052cc]">
                                                </td>
                                                <td class="px-3 py-3">
                                                    <input type="number"
                                                           min="0"
                                                           step="any"
                                                           x-model.number="item.discount_amount"
                                                           class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 font-mono font-semibold text-xs focus:outline-none focus:border-[#0052cc]">
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 whitespace-nowrap" x-text="formatRupiah(getItemSubtotal(item))"></td>
                                                <td class="px-3 py-3 text-center">
                                                    <button type="button"
                                                            @click="removeFormItem(idx)"
                                                            class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 cursor-pointer transition-colors"
                                                            title="Hapus Produk">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary & Submit Bar -->
                            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 pt-2">
                                <div class="bg-slate-50 border border-gray-200 rounded-xl p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1 text-xs">
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase block">TOTAL ITEM</span>
                                        <span class="text-base font-black text-gray-900 font-mono" x-text="form.items.length + ' Produk'">0 Produk</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase block">TOTAL QTY</span>
                                        <span class="text-base font-black text-gray-900 font-mono" x-text="formatQty(formTotalQty)">0</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase block">TOTAL DISKON</span>
                                        <span class="text-base font-black text-rose-600 font-mono" x-text="formatRupiah(formTotalDiscount)">Rp0</span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-[#0052cc] uppercase block">TOTAL PEMBELIAN</span>
                                        <span class="text-lg font-black text-[#0052cc] font-mono" x-text="formatRupiah(formGrandTotal)">Rp0</span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                                    <button type="button"
                                            :disabled="isSubmitting"
                                            @click="submitPurchase('DRAFT')"
                                            class="px-5 py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs flex items-center gap-2 cursor-pointer transition-colors disabled:opacity-50">
                                        <span class="material-symbols-rounded text-[18px]">draft</span>
                                        <span>Simpan Draft</span>
                                    </button>
                                    <button type="button"
                                            :disabled="isSubmitting"
                                            @click="submitPurchase('MENUNGGU_PENERIMAAN')"
                                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs cursor-pointer transition-colors disabled:opacity-50">
                                        <span class="material-symbols-rounded text-[18px]">save</span>
                                        <span>Simpan Pembelian</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 3: PENERIMAAN BARANG                                  -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'receive'" style="display: none;" class="space-y-6">

                    <!-- Search & Info Banner -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                            <input type="text"
                                   x-model="receiveSearch"
                                   placeholder="Cari nomor pembelian, invoice, atau supplier..."
                                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] text-xs font-medium bg-slate-50/50">
                        </div>

                        <div class="flex items-center gap-2.5 text-xs text-blue-800 bg-blue-50 border border-blue-200 rounded-xl px-4 py-2.5 font-semibold">
                            <span class="material-symbols-rounded text-[18px] text-[#0052cc]">info</span>
                            <span>Konfirmasi penerimaan menambah stok gudang (FIFO/FEFO) &amp; memperbarui harga beli terakhir Master Produk.</span>
                        </div>
                    </div>

                    <!-- Tabel Pembelian Siap Terima -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory</span>
                                <h3 class="text-sm font-bold text-gray-900">Daftar Pembelian Menunggu Penerimaan Gudang</h3>
                            </div>
                            <span class="text-xs text-gray-500 font-medium" x-text="filteredPendingPurchases.length + ' transaksi belum lengkap'"></span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[1050px] text-xs">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                        <th class="px-4 py-3.5">Nomor Pembelian</th>
                                        <th class="px-4 py-3.5">Tanggal Pembelian</th>
                                        <th class="px-4 py-3.5">Supplier</th>
                                        <th class="px-4 py-3.5 text-center">Total Item</th>
                                        <th class="px-4 py-3.5 text-center">Total Qty</th>
                                        <th class="px-4 py-3.5 text-center">Qty Sudah Diterima</th>
                                        <th class="px-4 py-3.5 text-center">Sisa Qty</th>
                                        <th class="px-4 py-3.5 text-center">Status</th>
                                        <th class="px-4 py-3.5 text-center">Aksi Terima Barang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                    <template x-if="filteredPendingPurchases.length === 0">
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center bg-white">
                                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto space-y-2">
                                                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                                        <span class="material-symbols-rounded text-[26px]">task_alt</span>
                                                    </div>
                                                    <p class="font-bold text-gray-800 text-sm">Tidak ada pembelian menunggu penerimaan</p>
                                                    <p class="text-xs text-gray-500">Semua transaksi pembelian yang aktif telah diterima di gudang.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="p in filteredPendingPurchases" :key="p.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <button type="button"
                                                        @click="openPurchaseDetail(p)"
                                                        class="font-mono font-bold text-[#0052cc] hover:underline cursor-pointer"
                                                        x-text="p.purchase_number"></button>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="p.purchase_date_label || p.purchase_date"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap font-semibold text-gray-900" x-text="p.supplier_name"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold" x-text="p.total_items + ' Produk'"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold" x-text="formatQty(p.total_qty)"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold text-emerald-700" x-text="formatQty(p.total_received_qty)"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center font-mono font-bold">
                                                <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200" x-text="formatQty(p.total_remaining_qty)"></span>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                      :class="getStatusBadge(p.status).class"
                                                      x-text="getStatusBadge(p.status).label"></span>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <button type="button"
                                                        @click="openReceiveModal(p)"
                                                        class="px-3.5 py-1.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-lg font-bold text-xs inline-flex items-center gap-1.5 shadow-2xs cursor-pointer transition-colors">
                                                    <span class="material-symbols-rounded text-[16px]">move_to_inbox</span>
                                                    <span>Terima Barang</span>
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
                <!-- TAB 4: RIWAYAT HARGA BELI                                 -->
                <!-- ========================================================= -->
                <div x-show="activeTab === 'price_history'" style="display: none;" class="space-y-6">

                    <!-- Summary Cards (4 Cards) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">PRODUK DENGAN RIWAYAT</span>
                                <span class="material-symbols-rounded text-[22px] text-[#0052cc]">inventory_2</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-gray-900 font-mono" x-text="priceSummary.products_with_history">0</span>
                                <span class="text-[11px] font-semibold text-gray-400">Produk tercatat</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL PERUBAHAN HARGA</span>
                                <span class="material-symbols-rounded text-[22px] text-slate-600">swap_vert</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-gray-900 font-mono" x-text="priceSummary.total_price_changes">0</span>
                                <span class="text-[11px] font-semibold text-gray-400">Kali perubahan</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-rose-200 border-l-4 border-l-rose-500 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-rose-700 tracking-wider uppercase">KENAIKAN HARGA BELI</span>
                                <span class="material-symbols-rounded text-[22px] text-rose-500">trending_up</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-rose-600 font-mono" x-text="priceSummary.products_with_increase">0</span>
                                <span class="text-[11px] font-semibold text-rose-700">Produk naik</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-emerald-200 border-l-4 border-l-emerald-500 p-5 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-emerald-700 tracking-wider uppercase">PENURUNAN HARGA BELI</span>
                                <span class="material-symbols-rounded text-[22px] text-emerald-600">trending_down</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-3xl font-black text-emerald-600 font-mono" x-text="priceSummary.products_with_decrease">0</span>
                                <span class="text-[11px] font-semibold text-emerald-700">Produk turun</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filters Toolbar -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div class="relative lg:col-span-2">
                                <span class="material-symbols-rounded absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-[19px]">search</span>
                                <input type="text"
                                       x-model="priceFilters.search"
                                       @input="onPriceSearchInput()"
                                       placeholder="Cari nama produk, SKU, atau no. pembelian..."
                                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] text-xs font-medium bg-slate-50/50">
                            </div>

                            <div class="relative">
                                <select x-model="priceFilters.supplier_id"
                                        @change="priceFilters.page = 1; loadPriceHistory();"
                                        class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                    <option value="all">Semua Supplier</option>
                                    <template x-for="sup in suppliers" :key="sup.id">
                                        <option :value="sup.id" x-text="sup.name"></option>
                                    </template>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                            </div>

                            <div class="relative">
                                <select x-model="priceFilters.change_type"
                                        @change="priceFilters.page = 1; loadPriceHistory();"
                                        class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                    <option value="all">Semua Perubahan</option>
                                    <option value="NAIK">Kenaikan Harga</option>
                                    <option value="TURUN">Penurunan Harga</option>
                                    <option value="TETAP">Harga Tetap</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                            </div>

                            <div class="relative">
                                <select x-model="priceFilters.sort"
                                        @change="priceFilters.page = 1; loadPriceHistory();"
                                        class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 pr-9 text-xs text-gray-700 font-semibold focus:outline-none focus:border-[#0052cc] cursor-pointer">
                                    <option value="latest">Urutkan: Terbaru</option>
                                    <option value="price_asc">Harga Terendah</option>
                                    <option value="price_desc">Harga Tertinggi</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[18px]">expand_more</span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <input type="date"
                                       x-model="priceFilters.date_from"
                                       @change="priceFilters.page = 1; loadPriceHistory();"
                                       class="w-full px-2 py-2 rounded-xl border border-gray-200 text-xs font-medium">
                                <input type="date"
                                       x-model="priceFilters.date_to"
                                       @change="priceFilters.page = 1; loadPriceHistory();"
                                       class="w-full px-2 py-2 rounded-xl border border-gray-200 text-xs font-medium">
                                <button type="button"
                                        @click="resetPriceFilters()"
                                        title="Reset Filter"
                                        class="p-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 cursor-pointer shrink-0">
                                    <span class="material-symbols-rounded text-[16px]">restart_alt</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Perubahan Harga Beli -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">show_chart</span>
                                    <span>Grafik Tren Perubahan Harga Beli</span>
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">Klik titik grafik atau baris tabel untuk melihat riwayat harga lengkap per produk.</p>
                            </div>
                            <template x-if="hoveredChartPoint">
                                <div class="px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-xs flex items-center gap-2">
                                    <span class="font-bold text-gray-900" x-text="hoveredChartPoint.product_name"></span>
                                    <span class="text-gray-500" x-text="'(' + hoveredChartPoint.supplier_name + ' • ' + hoveredChartPoint.full_date + ')'"></span>
                                    <span class="font-mono font-black text-[#0052cc]" x-text="formatRupiah(hoveredChartPoint.price)"></span>
                                </div>
                            </template>
                        </div>

                        <template x-if="priceChartSeries.length === 0">
                            <div class="h-44 flex items-center justify-center rounded-xl bg-slate-50 border border-dashed border-gray-200 text-xs text-gray-400">
                                Grafik akan muncul otomatis setelah ada penerimaan barang pembelian yang tercatat.
                            </div>
                        </template>

                        <template x-if="priceChartSeries.length > 0">
                            <div class="w-full overflow-x-auto" x-data="{ get chart() { return buildSvgChart(priceChartSeries, 'price'); } }">
                                <svg viewBox="0 0 680 190" class="w-full min-w-[540px] h-48 overflow-visible">
                                    <defs>
                                        <linearGradient id="priceAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#0052cc" stop-opacity="0.18" />
                                            <stop offset="100%" stop-color="#0052cc" stop-opacity="0.01" />
                                        </linearGradient>
                                    </defs>
                                    <!-- Grid lines -->
                                    <line x1="36" y1="24" x2="644" y2="24" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                    <line x1="36" y1="95" x2="644" y2="95" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                    <line x1="36" y1="166" x2="644" y2="166" stroke="#cbd5e1" />

                                    <!-- Y Labels -->
                                    <text x="32" y="28" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.max)"></text>
                                    <text x="32" y="99" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.mid)"></text>
                                    <text x="32" y="170" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(chart.min)"></text>

                                    <!-- Area & Line -->
                                    <path x-show="chart.areaPath" :d="chart.areaPath" fill="url(#priceAreaGrad)" />
                                    <polyline x-show="chart.points.length > 1" :points="chart.polyline" fill="none" stroke="#0052cc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                                    <!-- Points -->
                                    <template x-for="(pt, i) in chart.points" :key="i">
                                        <g class="cursor-pointer"
                                           @click="if(pt.product_id) openProductPriceDetail(pt.product_id)"
                                           @mouseenter="hoveredChartPoint = pt"
                                           @mouseleave="hoveredChartPoint = null">
                                            <circle :cx="pt.x" :cy="pt.y" r="5"
                                                    :fill="pt.change_type === 'NAIK' ? '#e11d48' : (pt.change_type === 'TURUN' ? '#059669' : '#0052cc')"
                                                    stroke="#ffffff" stroke-width="2" />
                                            <text :x="pt.x" y="182" text-anchor="middle" class="fill-gray-400 text-[9px] font-mono" x-text="pt.label"></text>
                                        </g>
                                    </template>
                                </svg>
                            </div>
                        </template>
                    </div>

                    <!-- Tabel Riwayat Harga Beli -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-rounded text-[20px] text-[#0052cc]">history</span>
                                <h3 class="text-sm font-bold text-gray-900">Tabel Riwayat Perubahan Harga Beli</h3>
                            </div>
                            <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + priceHistories.length + ' dari ' + priceMeta.total + ' catatan'"></span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[1050px] text-xs">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                        <th class="px-4 py-3.5">Tanggal</th>
                                        <th class="px-4 py-3.5">Produk</th>
                                        <th class="px-4 py-3.5">SKU</th>
                                        <th class="px-4 py-3.5">Supplier</th>
                                        <th class="px-4 py-3.5 text-right">Harga Sebelumnya</th>
                                        <th class="px-4 py-3.5 text-right">Harga Beli Terbaru</th>
                                        <th class="px-4 py-3.5 text-center">Perubahan</th>
                                        <th class="px-4 py-3.5">Nomor Pembelian</th>
                                        <th class="px-4 py-3.5 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 font-medium text-slate-700">
                                    <template x-if="priceHistories.length === 0">
                                        <tr>
                                            <td colspan="9" class="px-6 py-12 text-center bg-white text-gray-500">
                                                Belum ada riwayat perubahan harga beli. Riwayat akan tercatat otomatis saat penerimaan barang dikonfirmasi.
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="h in priceHistories" :key="h.id">
                                        <tr class="hover:bg-blue-50/30 transition-colors">
                                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600" x-text="h.recorded_date_label || h.recorded_date"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap font-bold text-gray-900" x-text="h.product_name"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap font-mono text-gray-600" x-text="h.sku"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-800 font-semibold" x-text="h.supplier_name"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono text-gray-500" x-text="formatRupiah(h.previous_price)"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono font-bold text-gray-900" x-text="formatRupiah(h.new_price)"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <template x-if="h.change_type === 'NAIK'">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                        <span class="material-symbols-rounded text-[14px]">arrow_upward</span>
                                                        <span x-text="'+' + h.change_percent + '%'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="h.change_type === 'TURUN'">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span class="material-symbols-rounded text-[14px]">arrow_downward</span>
                                                        <span x-text="h.change_percent + '%'"></span>
                                                    </span>
                                                </template>
                                                <template x-if="h.change_type === 'TETAP'">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                                        <span>0% (Tetap)</span>
                                                    </span>
                                                </template>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap font-mono font-bold text-[#0052cc]" x-text="h.purchase_number"></td>
                                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                                <button type="button"
                                                        @click="openProductPriceDetail(h.product_id)"
                                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-[11px] cursor-pointer transition-colors">
                                                    Detail
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="border-t border-gray-200 px-6 py-3.5 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white text-xs text-gray-500">
                            <span x-text="'Halaman ' + priceMeta.current_page + ' dari ' + priceMeta.last_page + ' (' + priceMeta.total + ' total catatan)'"></span>
                            <div class="flex items-center gap-1.5" x-show="priceMeta.last_page > 1">
                                <button type="button"
                                        @click="goToPricePage(priceMeta.current_page - 1)"
                                        :disabled="priceMeta.current_page <= 1"
                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 cursor-pointer font-semibold">
                                    Sebelumnya
                                </button>
                                <button type="button"
                                        @click="goToPricePage(priceMeta.current_page + 1)"
                                        :disabled="priceMeta.current_page >= priceMeta.last_page"
                                        class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 disabled:opacity-40 cursor-pointer font-semibold">
                                    Berikutnya
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- ========================================================= -->
    <!-- MODAL: DETAIL TRANSAKSI PEMBELIAN                         -->
    <!-- ========================================================= -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div @click.away="showDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="'Detail Pembelian ' + (selectedPurchase?.purchase_number || '')"></h3>
                            <p class="text-xs text-gray-500 font-medium" x-text="(selectedPurchase?.supplier_name || '') + ' • ' + (selectedPurchase?.purchase_date_label || '')"></p>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-5 text-xs flex-1">
                    <template x-if="selectedPurchase">
                        <div class="space-y-5">
                            <!-- Info Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">SUPPLIER</span>
                                    <span class="font-bold text-gray-900 mt-0.5 block" x-text="selectedPurchase.supplier_name"></span>
                                </div>
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">NO. INVOICE SUPPLIER</span>
                                    <span class="font-mono font-bold text-gray-900 mt-0.5 block" x-text="selectedPurchase.supplier_invoice_number || '-'"></span>
                                </div>
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">STATUS</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold border mt-1"
                                          :class="getStatusBadge(selectedPurchase.status).class"
                                          x-text="getStatusBadge(selectedPurchase.status).label"></span>
                                </div>
                                <div class="p-3.5 bg-blue-50 rounded-xl border border-blue-200">
                                    <span class="text-[10px] font-bold text-[#0052cc] uppercase block">TOTAL PEMBELIAN</span>
                                    <span class="font-mono font-black text-[#0052cc] text-base mt-0.5 block" x-text="formatRupiah(selectedPurchase.total_amount)"></span>
                                </div>
                            </div>

                            <template x-if="selectedPurchase.notes">
                                <div class="p-3.5 bg-slate-50 border border-gray-200 rounded-xl">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block mb-1">CATATAN PEMBELIAN</span>
                                    <p class="text-gray-700 font-medium" x-text="selectedPurchase.notes"></p>
                                </div>
                            </template>

                            <!-- Items Table -->
                            <div>
                                <h4 class="font-bold text-gray-900 mb-2">Daftar Produk Dibeli</h4>
                                <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px]">
                                                <th class="px-3.5 py-2.5">Produk</th>
                                                <th class="px-3 py-2.5">SKU</th>
                                                <th class="px-3 py-2.5 text-center">Qty Beli</th>
                                                <th class="px-3 py-2.5 text-center">Diterima</th>
                                                <th class="px-3 py-2.5 text-center">Sisa</th>
                                                <th class="px-3 py-2.5 text-right">Harga Beli</th>
                                                <th class="px-3 py-2.5 text-right">Diskon</th>
                                                <th class="px-3.5 py-2.5 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <template x-for="it in selectedPurchase.items" :key="it.id">
                                                <tr>
                                                    <td class="px-3.5 py-2.5 font-bold text-gray-900" x-text="it.product_name"></td>
                                                    <td class="px-3 py-2.5 font-mono text-gray-600" x-text="it.sku"></td>
                                                    <td class="px-3 py-2.5 text-center font-mono font-bold" x-text="formatQty(it.qty) + ' ' + it.unit"></td>
                                                    <td class="px-3 py-2.5 text-center font-mono font-bold text-emerald-700" x-text="formatQty(it.received_qty)"></td>
                                                    <td class="px-3 py-2.5 text-center font-mono font-bold text-amber-700" x-text="formatQty(it.remaining_qty)"></td>
                                                    <td class="px-3 py-2.5 text-right font-mono" x-text="formatRupiah(it.purchase_price)"></td>
                                                    <td class="px-3 py-2.5 text-right font-mono text-rose-600" x-text="formatRupiah(it.discount_amount)"></td>
                                                    <td class="px-3.5 py-2.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(it.subtotal)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Receipt History for this Purchase -->
                            <template x-if="selectedPurchase.receipts && selectedPurchase.receipts.length > 0">
                                <div>
                                    <h4 class="font-bold text-gray-900 mb-2">Catatan Transaksi Penerimaan Gudang</h4>
                                    <div class="space-y-2.5">
                                        <template x-for="r in selectedPurchase.receipts" :key="r.id">
                                            <div class="p-3.5 rounded-xl border border-gray-200 bg-slate-50/80 space-y-2">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-mono font-bold text-[#0052cc]" x-text="r.receipt_number"></span>
                                                        <span class="text-gray-500" x-text="r.received_at_label"></span>
                                                    </div>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200" x-text="r.status"></span>
                                                </div>
                                                <div class="text-[11px] text-gray-600" x-show="r.notes" x-text="'Catatan: ' + r.notes"></div>
                                                <div class="flex flex-wrap gap-2">
                                                    <template x-for="gi in r.items" :key="gi.id">
                                                        <span class="px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-[11px] font-medium">
                                                            <strong x-text="gi.product_name"></strong>:
                                                            <span class="font-mono font-bold text-emerald-700" x-text="formatQty(gi.qty_received)"></span>
                                                            <span class="text-gray-400" x-text="'(' + gi.condition + ')'"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex flex-wrap items-center justify-between gap-2 shrink-0">
                    <button type="button"
                            @click="showDetailModal = false"
                            class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer">
                        Tutup
                    </button>
                    <div class="flex items-center gap-2">
                        <template x-if="selectedPurchase?.can_edit">
                            <button type="button"
                                    @click="cancelPurchase(selectedPurchase)"
                                    class="px-4 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs cursor-pointer">
                                Batalkan Pembelian
                            </button>
                        </template>
                        <template x-if="selectedPurchase?.can_edit">
                            <button type="button"
                                    @click="editPurchase(selectedPurchase)"
                                    class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs cursor-pointer">
                                Edit Pembelian
                            </button>
                        </template>
                        <template x-if="selectedPurchase?.can_receive">
                            <button type="button"
                                    @click="openReceiveModal(selectedPurchase)"
                                    class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs cursor-pointer">
                                Terima Barang
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================================================= -->
    <!-- MODAL: TERIMA BARANG (PENERIMAAN GUDANG)                  -->
    <!-- ========================================================= -->
    <template x-teleport="body">
        <div x-show="showReceiveModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div @click.away="showReceiveModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">move_to_inbox</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Penerimaan Barang Pembelian</h3>
                            <p class="text-xs text-gray-500 font-medium">
                                <span class="font-mono font-bold text-[#0052cc]" x-text="receivingPurchase?.purchase_number"></span>
                                &bull; Supplier: <strong x-text="receivingPurchase?.supplier_name"></strong>
                            </p>
                        </div>
                    </div>
                    <button @click="showReceiveModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-5 text-xs flex-1">
                    <!-- Metadata Penerimaan -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Tanggal &amp; Waktu Penerimaan</label>
                            <input type="datetime-local"
                                   x-model="receiveForm.received_at"
                                   class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] font-medium">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-bold text-gray-700 mb-1">Catatan Transaksi Penerimaan</label>
                            <input type="text"
                                   x-model="receiveForm.notes"
                                   placeholder="Contoh: Diterima di Gudang Pusat, segel dus aman / ada selisih..."
                                   class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc] font-medium">
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs font-bold text-gray-800">Rincian Item Penerimaan</span>
                        <button type="button"
                                @click="fillAllRemaining()"
                                class="px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#0052cc] border border-blue-200 font-bold text-[11px] cursor-pointer">
                            Terima Semua Sisa Qty
                        </button>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-left border-collapse min-w-[980px] text-xs">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px] whitespace-nowrap">
                                    <th class="px-3.5 py-3">Produk &amp; SKU</th>
                                    <th class="px-2.5 py-3 text-center">Dibeli</th>
                                    <th class="px-2.5 py-3 text-center">Sudah Diterima</th>
                                    <th class="px-2.5 py-3 w-[120px]">Diterima Sekarang</th>
                                    <th class="px-2.5 py-3 text-center">Sisa</th>
                                    <th class="px-2.5 py-3 w-[130px]">Harga Beli Satuan</th>
                                    <th class="px-2.5 py-3 w-[135px]">Expired (FEFO)</th>
                                    <th class="px-3 py-3 w-[200px]">Kondisi &amp; Keterangan Selisih</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="it in receiveForm.items" :key="it.purchase_item_id">
                                    <tr>
                                        <td class="px-3.5 py-3">
                                            <div class="font-bold text-gray-900" x-text="it.product_name"></div>
                                            <div class="text-[10.5px] font-mono text-gray-500" x-text="it.sku + ' • ' + it.unit"></div>
                                        </td>
                                        <td class="px-2.5 py-3 text-center font-mono font-bold" x-text="formatQty(it.qty)"></td>
                                        <td class="px-2.5 py-3 text-center font-mono font-bold text-emerald-700" x-text="formatQty(it.received_qty)"></td>
                                        <td class="px-2.5 py-3">
                                            <input type="number"
                                                   min="0"
                                                   :max="it.remaining_qty"
                                                   step="any"
                                                   x-model.number="it.qty_received_now"
                                                   @change="clampReceiveQty(it)"
                                                   :disabled="it.remaining_qty <= 0"
                                                   class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 font-mono font-bold text-xs focus:outline-none focus:border-[#0052cc] disabled:bg-gray-100">
                                        </td>
                                        <td class="px-2.5 py-3 text-center font-mono font-bold text-amber-700" x-text="formatQty(getAfterReceiveRemaining(it))"></td>
                                        <td class="px-2.5 py-3">
                                            <input type="number"
                                                   min="0"
                                                   step="any"
                                                   x-model.number="it.actual_unit_price"
                                                   class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 font-mono font-bold text-xs focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                        <td class="px-2.5 py-3">
                                            <input type="date"
                                                   x-model="it.expired_date"
                                                   class="w-full px-2 py-1.5 rounded-lg border border-gray-300 text-[11px] font-medium focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                        <td class="px-3 py-3 space-y-1.5">
                                            <select x-model="it.condition"
                                                    class="w-full px-2 py-1 rounded-lg border border-gray-300 bg-white text-[11px] font-semibold">
                                                <option value="BAIK">Baik</option>
                                                <option value="KURANG">Kurang / Selisih</option>
                                                <option value="RUSAK">Rusak</option>
                                                <option value="KEMASAN_RUSAK">Kemasan Rusak</option>
                                            </select>
                                            <input type="text"
                                                   x-model="it.note"
                                                   placeholder="Keterangan jika ada selisih/rusak..."
                                                   class="w-full px-2.5 py-1 rounded-lg border border-gray-200 text-[11px]">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3.5 rounded-xl bg-blue-50/70 border border-blue-200 text-blue-900 flex items-start gap-2.5">
                        <span class="material-symbols-rounded text-[18px] text-[#0052cc] shrink-0 mt-0.5">verified_user</span>
                        <div class="text-[11px] leading-relaxed">
                            Hanya jumlah yang dikonfirmasi <strong>Diterima Sekarang</strong> yang akan menambah stok gudang (tercatat sebagai batch FIFO/FEFO) dan memperbarui <strong>Harga Beli Terakhir</strong> pada Master Produk. Harga jual produk tidak berubah otomatis.
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex items-center justify-between shrink-0">
                    <button type="button"
                            @click="showReceiveModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer">
                        Batal
                    </button>
                    <button type="button"
                            :disabled="isSubmitting || totalReceivingNowQty <= 0"
                            @click="confirmReceiveGoods()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-xs cursor-pointer disabled:opacity-50">
                        <span class="material-symbols-rounded text-[18px]">check_circle</span>
                        <span>Konfirmasi Penerimaan Barang</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================================================= -->
    <!-- MODAL: DETAIL RIWAYAT HARGA PER PRODUK                    -->
    <!-- ========================================================= -->
    <template x-teleport="body">
        <div x-show="showPriceDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div @click.away="showPriceDetailModal = false"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                            <span class="material-symbols-rounded text-[22px]">timeline</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900" x-text="'Riwayat Harga Beli: ' + (productPriceDetail?.product?.name || '')"></h3>
                            <p class="text-xs text-gray-500 font-mono" x-text="'SKU: ' + (productPriceDetail?.product?.sku || '-') + ' • Harga Jual Saat Ini: ' + formatRupiah(productPriceDetail?.product?.selling_price || 0)"></p>
                        </div>
                    </div>
                    <button @click="showPriceDetailModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-5 text-xs flex-1">
                    <template x-if="productPriceDetail">
                        <div class="space-y-5">
                            <!-- 5 Stats -->
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">HARGA PERTAMA</span>
                                    <span class="font-mono font-black text-gray-900 text-sm mt-1 block" x-text="formatRupiah(productPriceDetail.stats.first_price)"></span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200">
                                    <span class="text-[10px] font-bold text-[#0052cc] uppercase block">HARGA TERAKHIR</span>
                                    <span class="font-mono font-black text-[#0052cc] text-sm mt-1 block" x-text="formatRupiah(productPriceDetail.stats.last_price)"></span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200">
                                    <span class="text-[10px] font-bold text-rose-700 uppercase block">HARGA TERTINGGI</span>
                                    <span class="font-mono font-black text-rose-700 text-sm mt-1 block" x-text="formatRupiah(productPriceDetail.stats.highest_price)"></span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200">
                                    <span class="text-[10px] font-bold text-emerald-700 uppercase block">HARGA TERENDAH</span>
                                    <span class="font-mono font-black text-emerald-700 text-sm mt-1 block" x-text="formatRupiah(productPriceDetail.stats.lowest_price)"></span>
                                </div>
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-gray-200 col-span-2 sm:col-span-1">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase block">SUPPLIER TERAKHIR</span>
                                    <span class="font-bold text-gray-900 mt-1 block truncate" :title="productPriceDetail.stats.last_supplier" x-text="productPriceDetail.stats.last_supplier"></span>
                                </div>
                            </div>

                            <!-- Grafik Perubahan Harga Produk -->
                            <div class="p-4 rounded-xl border border-gray-200 bg-white">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="font-bold text-gray-800">Grafik Perubahan Harga Beli Kronologis</span>
                                    <template x-if="hoveredModalPoint">
                                        <span class="text-[11px] font-bold text-[#0052cc] font-mono"
                                              x-text="hoveredModalPoint.date_label + ' • ' + hoveredModalPoint.supplier_name + ': ' + formatRupiah(hoveredModalPoint.new_price)"></span>
                                    </template>
                                </div>
                                <template x-if="productPriceDetail.history && productPriceDetail.history.length > 0">
                                    <div class="w-full overflow-x-auto" x-data="{ get mChart() { return buildSvgChart(productPriceDetail.history, 'new_price'); } }">
                                        <svg viewBox="0 0 680 190" class="w-full min-w-[480px] h-44 overflow-visible">
                                            <line x1="36" y1="24" x2="644" y2="24" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                            <line x1="36" y1="95" x2="644" y2="95" stroke="#e2e8f0" stroke-dasharray="4 4" />
                                            <line x1="36" y1="166" x2="644" y2="166" stroke="#cbd5e1" />

                                            <text x="32" y="28" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(mChart.max)"></text>
                                            <text x="32" y="99" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(mChart.mid)"></text>
                                            <text x="32" y="170" text-anchor="end" class="fill-gray-400 text-[9px] font-mono" x-text="formatRupiah(mChart.min)"></text>

                                            <path x-show="mChart.areaPath" :d="mChart.areaPath" fill="url(#priceAreaGrad)" />
                                            <polyline x-show="mChart.points.length > 1" :points="mChart.polyline" fill="none" stroke="#0052cc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                                            <template x-for="(pt, i) in mChart.points" :key="i">
                                                <g class="cursor-pointer" @mouseenter="hoveredModalPoint = pt" @mouseleave="hoveredModalPoint = null">
                                                    <circle :cx="pt.x" :cy="pt.y" r="5" fill="#0052cc" stroke="#ffffff" stroke-width="2" />
                                                    <text :x="pt.x" y="182" text-anchor="middle" class="fill-gray-400 text-[9px] font-mono" x-text="pt.short_date"></text>
                                                </g>
                                            </template>
                                        </svg>
                                    </div>
                                </template>
                            </div>

                            <!-- Chronological Table -->
                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px]">
                                            <th class="px-4 py-3">Tanggal</th>
                                            <th class="px-4 py-3">Supplier</th>
                                            <th class="px-4 py-3">No. Pembelian</th>
                                            <th class="px-4 py-3 text-right">Harga Beli</th>
                                            <th class="px-4 py-3 text-center">Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in productPriceDetail.history" :key="row.id">
                                            <tr>
                                                <td class="px-4 py-3 text-gray-700" x-text="row.date_label"></td>
                                                <td class="px-4 py-3 font-semibold text-gray-900" x-text="row.supplier_name"></td>
                                                <td class="px-4 py-3 font-mono text-[#0052cc] font-bold" x-text="row.purchase_number"></td>
                                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(row.new_price)"></td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold border"
                                                          :class="row.change_type === 'NAIK' ? 'bg-rose-50 text-rose-700 border-rose-200' : (row.change_type === 'TURUN' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200')"
                                                          x-text="row.change_type === 'NAIK' ? ('+' + row.change_percent + '%') : (row.change_type === 'TURUN' ? (row.change_percent + '%') : '0%')"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex justify-end shrink-0">
                    <button type="button"
                            @click="showPriceDetailModal = false"
                            class="px-5 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================================================= -->
    <!-- MODAL: TAMBAH SUPPLIER CEPAT                              -->
    <!-- ========================================================= -->
    <template x-teleport="body">
        <div x-show="showSupplierModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <form @submit.prevent="createQuickSupplier()"
                  @click.away="if (!isSavingSupplier) showSupplierModal = false"
                  class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <h3 class="text-sm font-black text-gray-900">Tambah Supplier Baru</h3>
                    <button type="button" :disabled="isSavingSupplier" @click="showSupplierModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Nama Supplier <span class="text-rose-500">*</span></label>
                        <input type="text" x-model="newSupplier.name" placeholder="Contoh: PT Sumber Makmur Distribusi" class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">PIC / Sales</label>
                            <input type="text" x-model="newSupplier.owner_name" placeholder="Nama kontak" class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">No. Telepon</label>
                            <input type="text" x-model="newSupplier.phone" placeholder="0812..." class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Alamat</label>
                        <textarea x-model="newSupplier.address" rows="2" placeholder="Alamat gudang / kantor supplier" class="w-full px-3.5 py-2 rounded-xl border border-gray-300 focus:outline-none focus:border-[#0052cc]"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 bg-slate-50 flex justify-end gap-2">
                    <button type="button" :disabled="isSavingSupplier" @click="showSupplierModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Batal</button>
                    <button type="submit" :disabled="isSavingSupplier" class="px-5 py-2 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs cursor-pointer flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="isSavingSupplier" class="material-symbols-rounded text-[15px] animate-spin">progress_activity</span>
                        <span x-text="isSavingSupplier ? 'Menyimpan...' : 'Simpan Supplier'"></span>
                    </button>
                </div>
            </form>
        </div>
    </template>

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
             'bg-amber-600 border-amber-500': toastType === 'warning',
             'bg-blue-600 border-blue-500': toastType === 'info'
         }">
        <span class="material-symbols-rounded text-[20px]"
              x-text="toastType === 'success' ? 'check_circle' : (toastType === 'error' ? 'error' : (toastType === 'warning' ? 'warning' : 'info'))"></span>
        <span x-text="toastMessage"></span>
    </div>
</div>
@endsection
