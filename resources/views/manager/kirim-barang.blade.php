@extends('layouts.app')

@section('title', 'Kirim Barang - Manager ILP POS')

@section('content')
<div x-data="{
    searchQuery: '',
    statusFilter: 'all',
    destinationFilter: 'all',

    // Modals
    showCreateModal: false,
    showDetailModal: false,
    selectedDoc: null,

    // Data Storage
    documents: [],
    availableProducts: [],
    isSubmitting: false,

    // Toast
    toastMessage: '',
    toastType: 'success',
    showToast: false,

    // Form Pengiriman Baru
    form: {
        destinationStore: 'Cabang A',
        requestRef: '',
        driverName: 'Andi Kurniawan',
        plateNumber: 'B 9182 UQ',
        date: '',
        generalNotes: '',
        items: []
    },

    triggerToast(msg, type = 'success') {
        this.toastMessage = msg;
        this.toastType = type;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3500);
    },

    async initKirimBarang() {
        // 1. Baca data pengiriman dari localStorage
        let localDocs = [];
        const stored = localStorage.getItem('pos_ilp_goods_in_docs_v1');
        if (stored) {
            try {
                localDocs = JSON.parse(stored);
                if (!Array.isArray(localDocs)) localDocs = [];
            } catch(e) {
                console.error('Failed to parse shipments storage:', e);
                localDocs = [];
            }
        }
        this.documents = localDocs;

        // 2. Fetch live data dari API backend goods-receipts
        try {
            const res = await fetch('/api/goods-receipts', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    const apiDocs = json.data;
                    const merged = [...apiDocs];
                    // Pertahankan local drafts atau offline shipments
                    for (const loc of localDocs) {
                        const exists = merged.some(m => (m.id && m.id === loc.id) || (m.docNo && m.docNo === loc.docNo));
                        if (!exists) {
                            merged.push(loc);
                        }
                    }
                    this.documents = merged;
                    this.saveDocuments();
                }
            }
        } catch(e) {
            console.warn('API goods-receipts fetch error:', e);
        }

        // 3. Load referensi produk katalog master
        await this.loadAvailableProducts();

        // 4. Sinkronisasi real-time antar tab browser
        window.addEventListener('storage', (e) => {
            if (e.key === 'pos_ilp_goods_in_docs_v1' && e.newValue) {
                try {
                    this.documents = JSON.parse(e.newValue);
                } catch(err) {}
            }
            if (e.key === 'pos_ilp_inventory_products_v1') {
                this.loadAvailableProducts();
            }
        });
    },

    async loadAvailableProducts() {
        const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    this.availableProducts = parsed.map(p => ({
                        id: p.id,
                        sku: p.sku || p.barcode || '-',
                        name: p.name || p.nama || '-',
                        category: p.category || p.kategori || 'Umum',
                        unit: p.unit || 'Pcs',
                        stock: Math.max(0, Number(p.stock !== undefined ? p.stock : (p.stok || 0)))
                    }));
                    return;
                }
            } catch(e) {
                console.error('Failed to parse catalog products:', e);
            }
        }

        // Fallback: Ambil dari API produk jika localStorage belum ada
        try {
            const res = await fetch('/api/pos/products/search?limit=100', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                const prods = json.data || json;
                if (Array.isArray(prods) && prods.length > 0) {
                    this.availableProducts = prods.map(p => ({
                        id: p.id,
                        sku: p.sku || p.barcode || '-',
                        name: p.name || p.nama || '-',
                        category: p.category || p.kategori || 'Umum',
                        unit: p.unit || 'Pcs',
                        stock: Math.max(0, Number(p.stock !== undefined ? p.stock : (p.stok || 0)))
                    }));
                    localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(this.availableProducts));
                    return;
                }
            }
        } catch(e) {
            console.warn('Failed to load products from API:', e);
        }

        this.availableProducts = [];
    },

    saveDocuments() {
        try {
            localStorage.setItem('pos_ilp_goods_in_docs_v1', JSON.stringify(this.documents));
        } catch(e) {
            console.error('Failed to save shipments storage:', e);
        }
    },

    get filteredDocuments() {
        return this.documents.filter(doc => {
            if (this.statusFilter !== 'all' && doc.status !== this.statusFilter) return false;
            if (this.destinationFilter !== 'all' && doc.destinationStore !== this.destinationFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchNo = (doc.docNo || '').toLowerCase().includes(q);
                const matchRef = (doc.requestRef || '').toLowerCase().includes(q);
                const matchWh = (doc.warehouseOrigin || '').toLowerCase().includes(q);
                const matchDest = (doc.destinationStore || '').toLowerCase().includes(q);
                const matchDriver = (doc.driverName || '').toLowerCase().includes(q) || (doc.plateNumber || '').toLowerCase().includes(q);
                const matchProd = Array.isArray(doc.items) && doc.items.some(it => (it.name || '').toLowerCase().includes(q) || (it.sku || '').toLowerCase().includes(q));
                return matchNo || matchRef || matchWh || matchDest || matchDriver || matchProd;
            }
            return true;
        });
    },

    openCreateModal() {
        this.loadAvailableProducts();
        const today = new Date().toISOString().split('T')[0];

        // Temukan produk pertama yang memiliki stok > 0
        const firstWithStock = this.availableProducts.find(p => p.stock > 0);

        this.form = {
            destinationStore: 'Cabang A',
            requestRef: '',
            driverName: 'Andi Kurniawan',
            plateNumber: 'B 9182 UQ',
            date: today,
            generalNotes: '',
            items: [
                {
                    sku: firstWithStock ? firstWithStock.sku : '',
                    name: firstWithStock ? firstWithStock.name : '',
                    category: firstWithStock ? firstWithStock.category : '',
                    unit: firstWithStock ? firstWithStock.unit : 'Pcs',
                    availableStock: firstWithStock ? firstWithStock.stock : 0,
                    qtySent: firstWithStock ? 1 : 0
                }
            ]
        };
        this.showCreateModal = true;
    },

    addItemRow() {
        // Cari produk yang belum dipilih
        const selectedSkus = this.form.items.map(i => i.sku);
        const nextProd = this.availableProducts.find(p => !selectedSkus.includes(p.sku) && p.stock > 0) || this.availableProducts[0];

        this.form.items.push({
            sku: nextProd ? nextProd.sku : '',
            name: nextProd ? nextProd.name : '',
            category: nextProd ? nextProd.category : '',
            unit: nextProd ? nextProd.unit : 'Pcs',
            availableStock: nextProd ? nextProd.stock : 0,
            qtySent: nextProd && nextProd.stock > 0 ? 1 : 0
        });
    },

    removeItemRow(index) {
        if (this.form.items.length <= 1) {
            this.triggerToast('Minimal harus ada 1 produk dalam dokumen pengiriman.', 'warning');
            return;
        }
        this.form.items.splice(index, 1);
    },

    onProductChange(item) {
        const prod = this.availableProducts.find(p => p.sku === item.sku);
        if (prod) {
            item.name = prod.name;
            item.category = prod.category;
            item.unit = prod.unit;
            item.availableStock = prod.stock;
            if (item.qtySent > prod.stock || item.qtySent <= 0) {
                item.qtySent = prod.stock > 0 ? 1 : 0;
            }
        } else {
            item.name = '';
            item.category = '';
            item.unit = 'Pcs';
            item.availableStock = 0;
            item.qtySent = 0;
        }
    },

    hasValidationErrors() {
        if (!this.form.destinationStore) return 'Toko tujuan pengiriman wajib dipilih.';
        if (!this.form.driverName.trim()) return 'Nama pengirim / driver wajib diisi.';
        if (!this.form.plateNumber.trim()) return 'Plat nomor kendaraan wajib diisi.';
        if (!this.form.items || this.form.items.length === 0) return 'Daftar produk pengiriman tidak boleh kosong.';

        const skus = [];
        for (let idx = 0; idx < this.form.items.length; idx++) {
            const it = this.form.items[idx];
            if (!it.sku) return `Baris ke-${idx + 1}: Produk belum dipilih.`;
            if (skus.includes(it.sku)) return `Produk ${it.name} (${it.sku}) dipilih lebih dari satu kali. Gabungkan kuantitasnya.`;
            skus.push(it.sku);

            const qty = Number(it.qtySent);
            if (isNaN(qty) || qty <= 0) return `Baris ke-${idx + 1}: Kuantitas pengiriman harus lebih dari 0.`;
            if (qty > it.availableStock) return `Baris ke-${idx + 1}: Kuantitas ${it.name} (${qty} ${it.unit}) melebihi stok gudang yang tersedia (${it.availableStock} ${it.unit}).`;
        }
        return null;
    },

    async submitShipment(mode = 'kirim') {
        const err = this.hasValidationErrors();
        if (err) {
            this.triggerToast(err, 'error');
            return;
        }

        if (this.isSubmitting) return;
        this.isSubmitting = true;

        try {
            const now = new Date();
            const dateStr = this.form.date
                ? new Date(this.form.date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
                : now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

            // Generate nomor surat jalan resmi
            const yearStr = now.getFullYear();
            const seqNumber = String(this.documents.length + 1).padStart(4, '0');
            const docNo = `SJ-TRF-${yearStr}-${seqNumber}`;

            const isDraft = (mode === 'draft');
            const status = isDraft ? 'DRAFT' : 'MENUNGGU_VERIFIKASI';

            const preparedItems = this.form.items.map(it => ({
                sku: it.sku,
                name: it.name,
                category: it.category || 'Umum',
                qtySent: Number(it.qtySent),
                qtyReceived: 0,
                unit: it.unit || 'Pcs',
                condition: 'BAIK'
            }));

            let newDoc = {
                id: 'trf_' + Date.now(),
                docNo: docNo,
                date: dateStr,
                time: timeStr,
                warehouseOrigin: 'Gudang Pusat (DC)',
                destinationStore: this.form.destinationStore,
                requestRef: this.form.requestRef.trim() || '-',
                driverName: this.form.driverName.trim(),
                plateNumber: this.form.plateNumber.trim().toUpperCase(),
                status: status,
                generalNotes: this.form.generalNotes.trim() || (isDraft ? 'Draft dokumen pengiriman barang.' : 'Pengiriman fisik barang transfer ke gerai cabang.'),
                createdAt: now.toISOString(),
                sentAt: isDraft ? null : `${dateStr} • ${timeStr}`,
                receivedAt: null,
                receiverName: null,
                items: preparedItems
            };

            // Simpan ke database backend jika bukan offline
            try {
                const apiRes = await fetch('/api/goods-receipts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        docNo: docNo,
                        origin_source: newDoc.warehouseOrigin,
                        destination_store: newDoc.destinationStore,
                        driver_name: newDoc.driverName,
                        plate_number: newDoc.plateNumber,
                        status: newDoc.status,
                        notes: newDoc.generalNotes,
                        items: preparedItems.map(it => ({
                            sku: it.sku,
                            name: it.name,
                            category: it.category,
                            unit: it.unit,
                            qty_sent: it.qtySent,
                            condition: it.condition
                        }))
                    })
                });

                if (apiRes.ok) {
                    const apiJson = await apiRes.json();
                    if (apiJson.success && apiJson.data) {
                        newDoc = { ...newDoc, ...apiJson.data };
                    }
                }
            } catch(apiErr) {
                console.warn('API post shipment warning (persisted locally):', apiErr);
            }

            // Jika status langsung dikirim, kurangi stok master gudang
            if (!isDraft) {
                this.deductMasterStock(preparedItems);
            }

            // Simpan dokumen pengiriman
            this.documents.unshift(newDoc);
            this.saveDocuments();

            this.showCreateModal = false;
            if (isDraft) {
                this.triggerToast(`Dokumen draft pengiriman ${newDoc.docNo} berhasil disimpan. Stok gudang belum berkurang.`);
            } else {
                this.triggerToast(`Dokumen pengiriman ${newDoc.docNo} berhasil dibuat & dikirim ke ${newDoc.destinationStore}. Stok gudang telah diperbarui!`);
            }
        } catch(e) {
            console.error('Error submitting shipment:', e);
            this.triggerToast('Terjadi kesalahan saat menyimpan pengiriman.', 'error');
        } finally {
            this.isSubmitting = false;
        }
    },

    deductMasterStock(items) {
        const raw = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (!raw) return;
        try {
            let prods = JSON.parse(raw);
            items.forEach(it => {
                const p = prods.find(pr => pr.sku === it.sku || pr.barcode === it.sku);
                if (p) {
                    const currentStock = Math.max(0, Number(p.stock !== undefined ? p.stock : (p.stok || 0)));
                    const nextStock = Math.max(0, currentStock - it.qtySent);
                    p.stock = nextStock;
                    p.stok = nextStock;
                }
            });
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(prods));
            window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
            this.loadAvailableProducts();
        } catch(e) {
            console.error('Failed to deduct master stock:', e);
        }
    },

    async dispatchDraft(doc) {
        if (!confirm(`Konfirmasi pengiriman fisik untuk dokumen ${doc.docNo}? Stok gudang akan dipotong sesuai kuantitas pengiriman.`)) {
            return;
        }

        // Validasi ketersediaan stok aktual
        await this.loadAvailableProducts();
        for (let it of doc.items) {
            const p = this.availableProducts.find(pr => pr.sku === it.sku);
            const currentStock = p ? p.stock : 0;
            if (it.qtySent > currentStock) {
                this.triggerToast(`Gagal kirim: Stok ${it.name} tidak mencukupi (dibutuhkan ${it.qtySent}, stok saat ini: ${currentStock}).`, 'error');
                return;
            }
        }

        // Potong stok
        this.deductMasterStock(doc.items);

        // Update status dokumen
        const now = new Date();
        const dateStr = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        doc.status = 'MENUNGGU_VERIFIKASI';
        doc.sentAt = `${dateStr} • ${timeStr}`;

        // Sinkronisasi ke backend DB jika memiliki ID di database
        if (typeof doc.id === 'number') {
            try {
                await fetch('/api/goods-receipts/' + doc.id + '/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ status: 'MENUNGGU_VERIFIKASI' })
                });
            } catch(e) {
                console.warn('API update status error:', e);
            }
        } else {
            try {
                const apiRes = await fetch('/api/goods-receipts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        docNo: doc.docNo,
                        origin_source: doc.warehouseOrigin,
                        destination_store: doc.destinationStore,
                        driver_name: doc.driverName,
                        plate_number: doc.plateNumber,
                        status: 'MENUNGGU_VERIFIKASI',
                        notes: doc.generalNotes,
                        items: (doc.items || []).map(it => ({
                            sku: it.sku,
                            name: it.name,
                            category: it.category,
                            unit: it.unit,
                            qty_sent: it.qtySent,
                            condition: it.condition
                        }))
                    })
                });
                if (apiRes.ok) {
                    const apiJson = await apiRes.json();
                    if (apiJson.success && apiJson.data) {
                        doc.id = apiJson.data.id;
                    }
                }
            } catch(e) {}
        }

        this.saveDocuments();

        if (this.selectedDoc && this.selectedDoc.id === doc.id) {
            this.selectedDoc = JSON.parse(JSON.stringify(doc));
        }

        this.triggerToast(`Dokumen ${doc.docNo} berhasil dikirim! Status kini menunggu konfirmasi penerimaan toko.`);
    },

    async cancelDraft(doc) {
        if (!confirm(`Batalkan dokumen draft pengiriman ${doc.docNo}?`)) {
            return;
        }
        doc.status = 'DIBATALKAN';

        if (typeof doc.id === 'number') {
            try {
                await fetch('/api/goods-receipts/' + doc.id + '/status', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ status: 'DIBATALKAN' })
                });
            } catch(e) {
                console.warn('API update status error:', e);
            }
        }

        this.saveDocuments();

        if (this.selectedDoc && this.selectedDoc.id === doc.id) {
            this.selectedDoc = JSON.parse(JSON.stringify(doc));
        }

        this.triggerToast(`Dokumen draft ${doc.docNo} telah dibatalkan.`, 'info');
    },

    openDetail(doc) {
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        this.showDetailModal = true;
    },

    getStatusBadge(status) {
        switch(status) {
            case 'DRAFT':
                return { label: 'Draft Pengiriman', class: 'bg-slate-100 text-slate-700 border-slate-300' };
            case 'MENUNGGU_VERIFIKASI':
                return { label: 'Dalam Pengiriman', class: 'bg-amber-50 text-amber-700 border-amber-300' };
            case 'DITERIMA_LENGKAP':
                return { label: 'Diterima Lengkap', class: 'bg-emerald-50 text-emerald-700 border-emerald-300' };
            case 'DITERIMA_SELISIH':
                return { label: 'Diterima Selisih', class: 'bg-rose-50 text-rose-700 border-rose-300' };
            case 'DIBATALKAN':
                return { label: 'Dibatalkan', class: 'bg-red-50 text-red-600 border-red-200' };
            default:
                return { label: status, class: 'bg-gray-100 text-gray-700 border-gray-300' };
        }
    }
}"
x-init="initKirimBarang()"
@pos-inventory-synced.window="loadAvailableProducts()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Manager -->
    <x-manager-sidebar :active="'kirim_barang'" />

    <!-- Main Workspace -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

        <!-- Top Header Navigation -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 z-10 shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Persediaan</span>
                <span class="material-symbols-rounded text-gray-400 text-sm">chevron_right</span>
                <h1 class="text-base font-bold text-gray-800">Kirim Barang</h1>
            </div>

            <!-- Header Action Button -->
            <button type="button"
                    @click="openCreateModal()"
                    class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-2 cursor-pointer transition-colors">
                <span class="material-symbols-rounded text-[18px]">add</span>
                <span>Buat Pengiriman</span>
            </button>
        </header>

        <!-- Main Scrollable Area -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fa]">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Search & Filters Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor surat jalan, toko tujuan, driver, atau produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Filter Tujuan -->
                        <div class="relative">
                            <select x-model="destinationFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Toko Tujuan</option>
                                <option value="Cabang A">Cabang A</option>
                                <option value="Cabang B">Cabang B</option>
                                <option value="Toko Pusat">Toko Pusat</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Filter Status -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status Pengiriman</option>
                                <option value="DRAFT">Draft Pengiriman</option>
                                <option value="MENUNGGU_VERIFIKASI">Dalam Pengiriman</option>
                                <option value="DITERIMA_LENGKAP">Diterima Lengkap</option>
                                <option value="DITERIMA_SELISIH">Diterima Selisih</option>
                                <option value="DIBATALKAN">Dibatalkan</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Tabel Riwayat Pengiriman (Single-Line Cell & Grouped Rows) -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">local_shipping</span>
                            <h2 class="text-sm font-bold text-gray-900">Riwayat Pengiriman Barang Keluar</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1250px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5 text-left">No. Surat Jalan</th>
                                    <th class="px-4 py-3.5 text-left">Tanggal</th>
                                    <th class="px-4 py-3.5 text-left">Gudang Asal</th>
                                    <th class="px-4 py-3.5 text-left">Toko Tujuan</th>
                                    <th class="px-4 py-3.5 text-left">No. Referensi</th>
                                    <th class="px-4 py-3.5 text-left">SKU</th>
                                    <th class="px-4 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-4 py-3.5 text-left">Kategori</th>
                                    <th class="px-4 py-3.5 text-center">Qty Dikirim</th>
                                    <th class="px-4 py-3.5 text-left">Pengirim</th>
                                    <th class="px-4 py-3.5 text-left">Plat Nomor</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                    <th class="px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700 divide-y divide-gray-100">
                                <!-- Kondisi Kosong: Header tetap tampil, isi tabel menampilkan tombol Buat Pengiriman -->
                                <template x-if="filteredDocuments.length === 0">
                                    <tr>
                                        <td colspan="13" class="px-6 py-12 text-center text-gray-500 bg-white">
                                            <div class="flex flex-col items-center justify-center">
                                                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                                                    <span class="material-symbols-rounded text-2xl">local_shipping</span>
                                                </div>
                                                <p class="font-bold text-gray-800 text-sm">Belum ada data pengiriman.</p>
                                                <p class="text-xs text-gray-400 mt-0.5">Mulai buat dokumen pengiriman barang keluar untuk distribusi ke toko cabang.</p>
                                                <button type="button"
                                                        @click="openCreateModal()"
                                                        class="mt-4 px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors">
                                                    <span class="material-symbols-rounded text-[16px]">add</span>
                                                    <span>Buat Pengiriman</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <!-- Baris Dokumen dengan Grouped Rows (rowspan) -->
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <template x-for="(it, iIdx) in ((doc.items && doc.items.length > 0) ? doc.items : [{ sku: '-', name: '-', category: '-', qtySent: 0, unit: 'Unit' }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- Parent Col 1: No. Surat Jalan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-xs">
                                                    <button type="button"
                                                            @click="openDetail(doc)"
                                                            class="text-[#0052cc] hover:underline font-bold inline-flex items-center gap-1 cursor-pointer"
                                                            :title="'Buka detail ' + doc.docNo">
                                                        <span x-text="doc.docNo"></span>
                                                    </button>
                                                </td>
                                            </template>

                                            <!-- Parent Col 2: Tanggal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="doc.date">
                                                </td>
                                            </template>

                                            <!-- Parent Col 3: Gudang Asal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-semibold text-gray-800" x-text="doc.warehouseOrigin">
                                                </td>
                                            </template>

                                            <!-- Parent Col 4: Toko Tujuan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-900">
                                                    <span class="inline-flex items-center gap-1 text-slate-800">
                                                        <span class="material-symbols-rounded text-[14px] text-[#0052cc]">store</span>
                                                        <span x-text="doc.destinationStore"></span>
                                                    </span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 5: No. Referensi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-mono text-gray-600" x-text="doc.requestRef || '-'">
                                                </td>
                                            </template>

                                            <!-- Child Col 6: SKU -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-mono font-bold text-gray-700 text-xs" x-text="it.sku || '-'"></td>

                                            <!-- Child Col 7: Nama Produk -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-bold text-gray-900 text-xs max-w-[200px] truncate" :title="it.name" x-text="it.name || '-'"></td>

                                            <!-- Child Col 8: Kategori -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-slate-500" x-text="it.category || '-'"></td>

                                            <!-- Child Col 9: Qty Dikirim -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs bg-slate-50/50">
                                                <span class="inline-block bg-blue-50 text-[#0047b3] border border-blue-200 px-2 py-0.5 rounded font-bold font-mono text-xs" x-text="it.qtySent + ' ' + (it.unit || 'Pcs')"></span>
                                            </td>

                                            <!-- Parent Col 10: Pengirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-medium text-gray-800" x-text="doc.driverName || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 11: Plat Nomor -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono text-xs text-gray-600" x-text="doc.plateNumber || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 12: Status -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border"
                                                          :class="getStatusBadge(doc.status).class"
                                                          x-text="getStatusBadge(doc.status).label"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 13: Aksi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button"
                                                                @click="openDetail(doc)"
                                                                class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs transition-colors cursor-pointer">
                                                            Detail
                                                        </button>
                                                        <template x-if="doc.status === 'DRAFT'">
                                                            <div class="inline-flex items-center gap-1">
                                                                <button type="button"
                                                                        @click="dispatchDraft(doc)"
                                                                        class="px-3 py-1.5 rounded-lg bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs transition-colors cursor-pointer shadow-2xs"
                                                                        title="Kirim Sekarang">
                                                                    Kirim
                                                                </button>
                                                                <button type="button"
                                                                        @click="cancelDraft(doc)"
                                                                        class="p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                                                        title="Batalkan Draft">
                                                                    <span class="material-symbols-rounded text-[18px]">close</span>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- MODAL: BUAT PENGIRIMAN (FORMULIR MULTI-ITEM) -->
    <div x-show="showCreateModal"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="if (!isSubmitting) showCreateModal = false"
             class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">local_shipping</span>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Buat Pengiriman Barang Keluar</h2>
                        <p class="text-[11px] text-gray-500">Pencatatan surat jalan transfer barang dari Gudang Pusat ke toko cabang.</p>
                    </div>
                </div>
                <button type="button"
                        @click="showCreateModal = false"
                        :disabled="isSubmitting"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body (Scrollable Form) -->
            <div class="p-6 overflow-y-auto space-y-5 text-xs">

                <!-- Alert jika katalog produk kosong -->
                <template x-if="availableProducts.length === 0">
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-start gap-3">
                        <span class="material-symbols-rounded text-amber-600 text-[20px] shrink-0 mt-0.5">warning</span>
                        <div class="space-y-1">
                            <p class="font-bold">Katalog Produk Kosong atau Belum Terdaftar</p>
                            <p class="text-[11.5px] leading-relaxed">Belum ada data produk di master stok. Silakan tambahkan produk terlebih dahulu di menu <strong>Master Produk &amp; Stok</strong> sebelum membuat pengiriman.</p>
                        </div>
                    </div>
                </template>

                <!-- Grid Data Pengiriman Induk -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Toko Tujuan -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Toko Tujuan <span class="text-rose-500">*</span></label>
                        <select x-model="form.destinationStore"
                                class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                            <option value="Cabang A">Cabang A</option>
                            <option value="Cabang B">Cabang B</option>
                            <option value="Toko Pusat">Toko Pusat</option>
                        </select>
                    </div>

                    <!-- Tanggal Pengiriman -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Tanggal Pengiriman <span class="text-rose-500">*</span></label>
                        <input type="date"
                               x-model="form.date"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- No. Referensi Permintaan (Opsional) -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">No. Referensi Permintaan Toko (Opsional)</label>
                        <input type="text"
                               x-model="form.requestRef"
                               placeholder="Contoh: REQ-CAB-2024-001"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- Driver / Petugas Pengirim -->
                    <div>
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Nama Pengirim / Driver <span class="text-rose-500">*</span></label>
                        <input type="text"
                               x-model="form.driverName"
                               placeholder="Nama pengemudi armada"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-medium">
                    </div>

                    <!-- Plat Nomor Kendaraan -->
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Plat Nomor Kendaraan <span class="text-rose-500">*</span></label>
                        <input type="text"
                               x-model="form.plateNumber"
                               placeholder="Contoh: B 9182 UQ"
                               class="w-full bg-white border border-gray-200 rounded-xl px-3.5 py-2 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 font-mono uppercase font-bold">
                    </div>
                </div>

                <!-- Bagian Daftar Produk yang Dikirim (Multi-Item Support) -->
                <div class="border-t border-gray-200 pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block font-bold text-gray-900 text-[12px] uppercase tracking-wider">Daftar Produk yang Dikirim</span>
                            <span class="text-[11px] text-gray-500">Pilih produk dari master katalog dan tentukan kuantitas yang dikeluarkan.</span>
                        </div>
                        <button type="button"
                                @click="addItemRow()"
                                :disabled="availableProducts.length === 0"
                                class="px-3 py-1.5 rounded-lg border border-[#0052cc] text-[#0052cc] hover:bg-blue-50 font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">add</span>
                            <span>Tambah Produk</span>
                        </button>
                    </div>

                    <!-- Tabel Item Form -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px] whitespace-nowrap">
                                <tr>
                                    <th class="px-3.5 py-2.5">Pilih Produk</th>
                                    <th class="px-3.5 py-2.5 text-center">Stok Gudang</th>
                                    <th class="px-3.5 py-2.5 text-center w-32">Qty Kirim</th>
                                    <th class="px-3.5 py-2.5 text-center w-16">Satuan</th>
                                    <th class="px-2 py-2.5 text-center w-12">Hapus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template x-for="(item, idx) in form.items" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <!-- Dropdown Produk -->
                                        <td class="px-3.5 py-2.5">
                                            <select x-model="item.sku"
                                                    @change="onProductChange(item)"
                                                    class="w-full bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-800 font-medium focus:outline-none focus:border-[#0052cc]">
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="p in availableProducts" :key="p.sku">
                                                    <option :value="p.sku"
                                                            :disabled="p.stock <= 0"
                                                            x-text="p.name + ' (' + p.sku + ') - Stok: ' + p.stock + ' ' + p.unit + (p.stock <= 0 ? ' [HABIS]' : '')">
                                                    </option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Info Stok Tersedia -->
                                        <td class="px-3.5 py-2.5 text-center whitespace-nowrap">
                                            <span class="font-mono font-bold text-xs"
                                                  :class="item.availableStock > 0 ? 'text-emerald-700' : 'text-rose-600'"
                                                  x-text="item.availableStock + ' ' + item.unit"></span>
                                        </td>

                                        <!-- Input Qty Dikirim -->
                                        <td class="px-3.5 py-2.5 text-center">
                                            <input type="number"
                                                   min="1"
                                                   :max="item.availableStock"
                                                   x-model.number="item.qtySent"
                                                   class="w-24 text-center font-mono font-bold bg-white border rounded-lg px-2 py-1 text-xs focus:outline-none"
                                                   :class="item.qtySent > item.availableStock || item.qtySent <= 0 ? 'border-rose-400 text-rose-700 bg-rose-50/40 focus:border-rose-500' : 'border-gray-200 focus:border-[#0052cc]'">
                                        </td>

                                        <!-- Satuan -->
                                        <td class="px-3.5 py-2.5 text-center font-medium text-gray-500 whitespace-nowrap" x-text="item.unit || 'Pcs'"></td>

                                        <!-- Tombol Hapus Baris -->
                                        <td class="px-2 py-2.5 text-center">
                                            <button type="button"
                                                    @click="removeItemRow(idx)"
                                                    :disabled="form.items.length <= 1"
                                                    class="p-1 rounded text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer">
                                                <span class="material-symbols-rounded text-[18px]">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Catatan Pengiriman -->
                <div>
                    <label class="block font-bold text-gray-700 mb-1.5 uppercase tracking-wider text-[10.5px]">Catatan Pengiriman (Opsional)</label>
                    <textarea x-model="form.generalNotes"
                              rows="2"
                              placeholder="Catatan tambahan untuk petugas penerima di toko cabang..."
                              class="w-full bg-white border border-gray-200 rounded-xl p-3 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                </div>

            </div>

            <!-- Modal Footer Actions -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50/50">
                <button type="button"
                        @click="showCreateModal = false"
                        :disabled="isSubmitting"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer">
                    Batal
                </button>

                <div class="flex items-center gap-2">
                    <!-- Tombol Simpan Draft -->
                    <button type="button"
                            @click="submitShipment('draft')"
                            :disabled="isSubmitting || availableProducts.length === 0"
                            class="px-4 py-2 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer disabled:opacity-50">
                        Simpan sebagai Draft
                    </button>

                    <!-- Tombol Kirim Sekarang -->
                    <button type="button"
                            @click="submitShipment('kirim')"
                            :disabled="isSubmitting || availableProducts.length === 0"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span class="material-symbols-rounded text-[16px]">send</span>
                        <span>Kirim Sekarang</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL: DETAIL DOKUMEN PENGIRIMAN (SURAT JALAN TRANSFER) -->
    <div x-show="showDetailModal"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showDetailModal = false"
             class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center">
                        <span class="material-symbols-rounded text-[20px]">description</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-gray-900" x-text="selectedDoc ? selectedDoc.docNo : 'Detail Pengiriman'"></h2>
                            <template x-if="selectedDoc">
                                <span class="px-2 py-0.5 rounded-full text-[10.5px] font-bold border"
                                      :class="getStatusBadge(selectedDoc.status).class"
                                      x-text="getStatusBadge(selectedDoc.status).label"></span>
                            </template>
                        </div>
                        <p class="text-[11px] text-gray-500">Surat Jalan Transfer Barang Antar Cabang</p>
                    </div>
                </div>
                <button type="button"
                        @click="showDetailModal = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 cursor-pointer">
                    <span class="material-symbols-rounded text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <template x-if="selectedDoc">
                <div class="p-6 overflow-y-auto space-y-5 text-xs">

                    <!-- Info Ringkasan Header -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50/80 rounded-xl border border-gray-200/80">
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Tanggal</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.date"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Gudang Asal</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Toko Tujuan</span>
                            <span class="text-xs font-bold text-[#0052cc] mt-0.5 block" x-text="selectedDoc.destinationStore"></span>
                        </div>
                        <div>
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">No. Referensi</span>
                            <span class="text-xs font-mono font-bold text-gray-700 mt-0.5 block" x-text="selectedDoc.requestRef || '-'"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Pengemudi / Driver</span>
                            <span class="text-xs font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.driverName || '-'"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10.5px] font-bold text-gray-400 uppercase tracking-wider block">Plat Nomor Kendaraan</span>
                            <span class="text-xs font-mono font-bold text-gray-800 mt-0.5 block" x-text="selectedDoc.plateNumber || '-'"></span>
                        </div>
                    </div>

                    <!-- Tabel Rincian Barang Dikirim -->
                    <div>
                        <h3 class="font-bold text-gray-900 text-xs uppercase tracking-wider mb-2">Rincian Barang Dikirim</h3>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase text-[10.5px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-3.5 py-2.5">No</th>
                                        <th class="px-3.5 py-2.5">SKU</th>
                                        <th class="px-3.5 py-2.5">Nama Produk</th>
                                        <th class="px-3.5 py-2.5">Kategori</th>
                                        <th class="px-3.5 py-2.5 text-center">Qty Dikirim</th>
                                        <th class="px-3.5 py-2.5 text-center">Qty Diterima</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <template x-for="(it, idx) in selectedDoc.items" :key="idx">
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="px-3.5 py-2.5 text-gray-400 font-bold" x-text="idx + 1"></td>
                                            <td class="px-3.5 py-2.5 font-mono font-bold text-gray-800" x-text="it.sku"></td>
                                            <td class="px-3.5 py-2.5 font-bold text-gray-900" x-text="it.name"></td>
                                            <td class="px-3.5 py-2.5 text-gray-500" x-text="it.category || '-'"></td>
                                            <td class="px-3.5 py-2.5 text-center font-mono font-bold text-blue-700 bg-blue-50/30" x-text="it.qtySent + ' ' + (it.unit || 'Pcs')"></td>
                                            <td class="px-3.5 py-2.5 text-center font-mono font-bold"
                                                :class="selectedDoc.status.includes('DITERIMA') ? (it.qtyReceived === it.qtySent ? 'text-emerald-700 bg-emerald-50/30' : 'text-rose-700 bg-rose-50/30') : 'text-gray-400'"
                                                x-text="selectedDoc.status.includes('DITERIMA') ? (it.qtyReceived + ' ' + (it.unit || 'Pcs')) : '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Catatan Pengiriman & Penerimaan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl border border-gray-200 bg-gray-50/40">
                            <span class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Catatan Pengiriman (Manager)</span>
                            <p class="text-xs text-gray-700 italic font-medium" x-text="selectedDoc.generalNotes || 'Tidak ada catatan.'"></p>
                        </div>
                        <div class="p-3 rounded-xl border border-gray-200 bg-gray-50/40">
                            <span class="text-[10.5px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Status Verifikasi Toko</span>
                            <template x-if="selectedDoc.status.includes('DITERIMA')">
                                <p class="text-xs text-emerald-800 font-semibold" x-text="'Diverifikasi oleh: ' + (selectedDoc.receiverName || 'Kasir Cabang') + ' pada ' + (selectedDoc.receivedAt || '-')"></p>
                            </template>
                            <template x-if="!selectedDoc.status.includes('DITERIMA')">
                                <p class="text-xs text-amber-700 font-medium" x-text="selectedDoc.status === 'DRAFT' ? 'Belum dikirim (Draft)' : 'Menunggu perhitungan fisik kasir cabang saat tiba'"></p>
                            </template>
                        </div>
                    </div>

                </div>
            </template>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50/50">
                <button type="button"
                        @click="showDetailModal = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-100 font-bold text-xs cursor-pointer">
                    Tutup
                </button>

                <template x-if="selectedDoc && selectedDoc.status === 'DRAFT'">
                    <button type="button"
                            @click="dispatchDraft(selectedDoc); showDetailModal = false;"
                            class="px-5 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl font-bold text-xs shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">send</span>
                        <span>Kirim Sekarang</span>
                    </button>
                </template>
            </div>

        </div>
    </div>

    <!-- Notification Toast -->
    <div x-show="showToast"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-xs font-bold"
         :class="{
             'bg-emerald-600 text-white border-emerald-500': toastType === 'success',
             'bg-blue-600 text-white border-blue-500': toastType === 'info',
             'bg-amber-500 text-white border-amber-400': toastType === 'warning',
             'bg-rose-600 text-white border-rose-500': toastType === 'error'
         }">
        <span class="material-symbols-rounded text-[18px]">info</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
