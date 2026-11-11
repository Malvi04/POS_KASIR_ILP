@extends('layouts.app')

@section('title', 'Barang Masuk (Transfer Gudang) - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    statusFilter: 'all',
    showVerifyModal: false,
    showBastModal: false,
    selectedDoc: null,
    verifyNotes: '',
    toastMessage: '',
    showToast: false,

    documents: [],

    async initGoodsIn() {
        let localDocs = [];
        const stored = localStorage.getItem('pos_ilp_goods_in_docs_v1');
        if (stored) {
            try {
                localDocs = JSON.parse(stored);
                if (!Array.isArray(localDocs)) localDocs = [];
            } catch(e) {
                localDocs = [];
            }
        }
        this.documents = localDocs;

        try {
            const res = await fetch('/api/goods-receipts', { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    const apiDocs = json.data;
                    const merged = [...apiDocs];
                    // Pertahankan dokumen lokal yang belum tersimpan di backend atau draft
                    for (const loc of localDocs) {
                        const exists = merged.some(m => (m.id && m.id === loc.id) || (m.docNo && m.docNo === loc.docNo));
                        if (!exists) {
                            merged.push(loc);
                        }
                    }
                    this.documents = merged;
                    this.saveDocs();
                }
            }
        } catch(e) {
            console.warn('API goods-receipts fetch error:', e);
        }

        // Sinkronisasi real-time antar tab (Manager Kirim Barang -> Kasir Barang Masuk)
        window.addEventListener('storage', (e) => {
            if (e.key === 'pos_ilp_goods_in_docs_v1' && e.newValue) {
                try {
                    this.documents = JSON.parse(e.newValue);
                } catch(err) {}
            }
        });
    },

    saveDocs() {
        localStorage.setItem('pos_ilp_goods_in_docs_v1', JSON.stringify(this.documents));
    },

    getDefaultDocs() {
        return [];
    },

    get filteredDocuments() {
        return this.documents.filter(doc => {
            if (this.statusFilter !== 'all' && doc.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchNo = doc.docNo.toLowerCase().includes(q);
                const matchWh = doc.warehouseOrigin.toLowerCase().includes(q);
                const matchDriver = doc.driverName.toLowerCase().includes(q) || doc.plateNumber.toLowerCase().includes(q);
                const matchProd = doc.items.some(it => it.name.toLowerCase().includes(q) || it.sku.includes(q));
                return matchNo || matchWh || matchDriver || matchProd;
            }
            return true;
        });
    },

    get totalItemsCount() {
        return this.documents.length;
    },

    get pendingCount() {
        return this.documents.filter(d => d.status === 'MENUNGGU_VERIFIKASI').length;
    },

    get completedFullCount() {
        return this.documents.filter(d => d.status === 'DITERIMA_LENGKAP').length;
    },

    get completedVarianceCount() {
        return this.documents.filter(d => d.status === 'DITERIMA_SELISIH').length;
    },

    openVerification(doc) {
        // Clone doc to avoid direct mutation before confirmation
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        if (this.selectedDoc && Array.isArray(this.selectedDoc.items)) {
            // Default received quantity to sent quantity if not yet filled
            this.selectedDoc.items.forEach(it => {
                if (it.qtyReceived === undefined || it.qtyReceived === null || it.qtyReceived === 0) {
                    it.qtyReceived = it.qtySent;
                }
            });
        }
        this.verifyNotes = doc.generalNotes || '';
        this.showVerifyModal = true;
    },

    openBastView(doc) {
        this.selectedDoc = doc;
        this.showBastModal = true;
    },

    calculateDocVariance(doc) {
        if (!doc || !doc.items) return 0;
        return doc.items.reduce((acc, it) => acc + (Number(it.qtyReceived) - Number(it.qtySent)), 0);
    },

    updateInventoryStock(items) {
        const rawProd = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (!rawProd) return;
        try {
            let prods = JSON.parse(rawProd);
            items.forEach(it => {
                const receivedQty = parseInt(it.qtyReceived, 10) || 0;
                if (receivedQty > 0) {
                    const p = prods.find(pr => (pr.sku && pr.sku === it.sku) || (pr.barcode && pr.barcode === it.sku) || (pr.name && pr.name.toLowerCase() === (it.name || '').toLowerCase()) || (pr.nama && pr.nama.toLowerCase() === (it.name || '').toLowerCase()));
                    if (p) {
                        const cur = parseInt(p.stock !== undefined ? p.stock : (p.stok || 0), 10) || 0;
                        p.stock = cur + receivedQty;
                        p.stok = cur + receivedQty;
                    }
                }
            });
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(prods));
            window.dispatchEvent(new CustomEvent('pos-inventory-synced'));
        } catch(e) {
            console.error('Error updating stock', e);
        }
    },

    async confirmGoodsReceipt() {
        if (!this.selectedDoc) return;

        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.documents.find(d => (d.id && d.id === this.selectedDoc.id) || (d.docNo && d.docNo === this.selectedDoc.docNo));
        if (original) {
            // Apply items confirmation
            original.items = this.selectedDoc.items.map(it => ({
                ...it,
                qtyReceived: Math.max(0, parseInt(it.qtyReceived, 10) || 0)
            }));

            // Check if there is any quantity variance or damaged condition
            const hasVariance = original.items.some(it => it.qtyReceived !== it.qtySent || it.condition !== 'BAIK');

            original.status = hasVariance ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            original.receivedAt = nowStr;
            original.receiverName = (this.activeSession?.cashierName || 'Budi Kurniawan') + ' (Kasir)';
            original.generalNotes = this.verifyNotes.trim() || (hasVariance ? 'Penerimaan diverifikasi dengan catatan selisih/kondisi.' : 'Penerimaan fisik barang transfer lengkap dan sesuai.');

            // Update master inventory stock via API
            if (original.id && typeof original.id === 'number') {
                try {
                    await fetch('/api/goods-receipts/' + original.id + '/verify', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({
                            receiver_name: original.receiverName,
                            notes: original.generalNotes,
                            items: (original.items || []).map(it => ({
                                id: it.id,
                                qty_received: it.qtyReceived,
                                condition: it.condition,
                                note: it.note
                            }))
                        })
                    });
                } catch (e) {
                    console.warn('API sync receipt error', e);
                }
            }

            this.updateInventoryStock(original.items);
            this.saveDocs();

            this.showVerifyModal = false;
            this.showToastNotice(`Dokumen ${original.docNo} berhasil dikonfirmasi (${original.status === 'DITERIMA_LENGKAP' ? 'Lengkap Sesuai' : 'Ada Selisih Fisik'}) dan stok produk telah diperbarui!`);
        }
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); initGoodsIn();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="produk_masuk" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Barang Masuk (Transfer Gudang)">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-pulse"></span>
                    <span>Penerimaan Fisik Toko</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Page Info -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>PRODUK</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">BARANG MASUK TRANSFER</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Barang Masuk</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Verifikasi surat jalan transfer dari gudang pusat dan pencatatan selisih barang.</p>
                    </div>

                    <!-- Online Status Badge -->
                    <div class="flex items-center gap-3">
                        <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs">
                            <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                            <div class="flex flex-col">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">TERMINAL PENERIMA</span>
                                <span class="text-xs font-bold text-gray-800" x-text="activeSession.terminalLabel + ' • ' + activeSession.cashierName">Kasir 01 • Budi Kurniawan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1 -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold text-gray-400 tracking-wider uppercase">TOTAL DOKUMEN</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="totalItemsCount">4</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Transfer periode aktif</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">local_shipping</span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[11px] font-bold text-amber-700 tracking-wider uppercase">PERLU VERIFIKASI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-amber-600" x-text="pendingCount">2</h3>
                                <span class="text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">Tiba di Toko</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Harus dihitung kasir</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pending_actions</span>
                        </div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[11px] font-bold text-emerald-700 tracking-wider uppercase">DITERIMA LENGKAP</span>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1" x-text="completedFullCount">1</h3>
                            <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Fisik 100% Sesuai</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified</span>
                        </div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[11px] font-bold text-rose-700 tracking-wider uppercase">DITERIMA SELISIH</span>
                            <h3 class="text-2xl font-black text-rose-600 mt-1" x-text="completedVarianceCount">1</h3>
                            <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Catatan Deviasi/Rusak</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">report_problem</span>
                        </div>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor surat jalan, DC pengirim, nama driver, atau produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs transition-all font-medium bg-slate-50/50">
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <div class="relative w-full md:w-auto">
                            <select x-model="statusFilter"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status Dokumen</option>
                                <option value="MENUNGGU_VERIFIKASI">Menunggu Verifikasi (Tiba)</option>
                                <option value="DITERIMA_LENGKAP">Diterima Lengkap (Sesuai)</option>
                                <option value="DITERIMA_SELISIH">Diterima Selisih / Cacat</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inbox</span>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Dokumen Transfer Gudang</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1050px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold tracking-wider uppercase text-[11px] whitespace-nowrap">
                                    <th class="px-4 py-3.5 text-left">No. Surat Jalan</th>
                                    <th class="px-4 py-3.5 text-left">Tanggal</th>
                                    <th class="px-4 py-3.5 text-left">Gudang Asal</th>
                                    <th class="px-4 py-3.5 text-left">Toko Tujuan</th>
                                    <th class="px-4 py-3.5 text-left">SKU</th>
                                    <th class="px-4 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-4 py-3.5 text-left">Kategori</th>
                                    <th class="px-4 py-3.5 text-center">Qty Dikirim</th>
                                    <th class="px-4 py-3.5 text-left">Pengirim</th>
                                    <th class="px-4 py-3.5 text-left">Plat Nomor</th>
                                    <th class="px-4 py-3.5 text-center">Status</th>
                                    <th class="px-4 py-3.5 text-center">Aksi Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <template x-for="(it, iIdx) in ((doc.items && doc.items.length > 0) ? doc.items : [{ sku: '-', name: '-', category: '-', qtySent: 0, unit: 'Unit' }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- Parent Col 1: No. Surat Jalan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-[13px]">
                                                    <span x-text="doc.docNo"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 2: Tanggal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="doc.date">
                                                </td>
                                            </template>

                                            <!-- Parent Col 3: Gudang Asal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-bold text-gray-800 text-xs" x-text="doc.warehouseOrigin">
                                                </td>
                                            </template>

                                            <!-- Parent Col 4: Toko Tujuan -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-600 font-medium" x-text="doc.destinationStore">
                                                </td>
                                            </template>

                                            <!-- Child Col 5: SKU -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-mono font-bold text-gray-700 text-xs" x-text="it.sku || '-'"></td>

                                            <!-- Child Col 6: Nama Produk -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-bold text-gray-900 text-xs max-w-[200px] truncate" :title="it.name" x-text="it.name"></td>

                                            <!-- Child Col 7: Kategori -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-slate-500" x-text="it.category || '-'"></td>

                                            <!-- Child Col 8: Qty Dikirim -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs bg-slate-50/50">
                                                <span class="inline-block bg-blue-50 text-[#0047b3] border border-blue-200 px-2.5 py-0.5 rounded font-black font-mono text-xs" x-text="it.qtySent + ' ' + (it.unit || 'Unit')"></span>
                                            </td>

                                            <!-- Parent Col 9: Pengirim -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-800" x-text="doc.driverName || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 10: Plat Nomor -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono text-xs text-gray-500" x-text="doc.plateNumber || '-'">
                                                </td>
                                            </template>

                                            <!-- Parent Col 11: Status -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-center">
                                                    <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                            <span>Menunggu Verifikasi</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'DITERIMA_LENGKAP'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                                            <span>Diterima Lengkap</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="doc.status === 'DITERIMA_SELISIH'">
                                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[14px]">warning</span>
                                                            <span>Diterima Selisih</span>
                                                        </span>
                                                    </template>
                                                </td>
                                            </template>

                                            <!-- Parent Col 12: Aksi Verifikasi -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="doc.items && doc.items.length > 0 ? doc.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap text-center bg-white">
                                                    <div class="inline-flex items-center justify-center gap-1.5">
                                                        <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                            <button type="button"
                                                                    @click="openVerification(doc)"
                                                                    class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[16px]">fact_check</span>
                                                                <span>Periksa Dokumen</span>
                                                            </button>
                                                        </template>
                                                        <template x-if="doc.status !== 'MENUNGGU_VERIFIKASI'">
                                                            <button type="button"
                                                                    @click="openBastView(doc)"
                                                                    class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 cursor-pointer">
                                                                <span class="material-symbols-rounded text-[16px]">receipt_long</span>
                                                                <span>Lihat BAST</span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                                <tr x-show="!filteredDocuments || filteredDocuments.length === 0">
                                    <td colspan="12" class="py-12 text-center text-slate-400 font-medium">
                                        Belum ada dokumen penerimaan barang.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL 1: PERIKSA & KONFIRMASI PENERIMAAN FISIK -->
    <template x-teleport="body">
        <div x-show="showVerifyModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showVerifyModal"
                 @click.away="showVerifyModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200">

                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50/70">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">fact_check</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Verifikasi Fisik Barang Masuk</h3>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="selectedDoc ? selectedDoc.docNo + ' • Dari ' + selectedDoc.warehouseOrigin : ''"></p>
                        </div>
                    </div>
                    <button @click="showVerifyModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <!-- Modal Body: Scrollable Form -->
                <div class="p-6 overflow-y-auto space-y-5 flex-1">
                    <!-- Delivery Manifest Info -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PENGEMUDI / ARMADA</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">GUDANG PENGIRIM</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">TOTAL SKU</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.items?.length + ' Macam Produk'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PETUGAS PEMERIKSA</span>
                            <span class="font-bold text-[#0052cc]" x-text="activeSession.cashierName">Budi Kurniawan</span>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[240px]">PRODUK / SKU</th>
                                        <th class="px-3 py-3 text-center w-[120px]">QTY SURAT JALAN</th>
                                        <th class="px-3 py-3 text-center w-[140px]">QTY AKTUAL DITERIMA</th>
                                        <th class="px-3 py-3 text-center w-[100px]">SELISIH</th>
                                        <th class="px-3 py-3 text-left w-[180px]">KONDISI FISIK</th>
                                        <th class="px-4 py-3 text-left w-[180px]">CATATAN KHUSUS</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, idx) in selectedDoc?.items" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <!-- Product -->
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900" x-text="item.name"></span>
                                                <span class="font-mono text-[11px] text-gray-400" x-text="'SKU: ' + item.sku + ' (' + item.unit + ')'"></span>
                                            </div>
                                        </td>

                                        <!-- Qty Sent -->
                                        <td class="px-3 py-3 text-center font-bold text-gray-700" x-text="item.qtySent + ' ' + item.unit"></td>

                                        <!-- Qty Received (Input) -->
                                        <td class="px-3 py-3 text-center">
                                            <input type="number"
                                                   min="0"
                                                   x-model.number="item.qtyReceived"
                                                   class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-1 focus:ring-[#0052cc]">
                                        </td>

                                        <!-- Selisih -->
                                        <td class="px-3 py-3 text-center font-bold font-mono">
                                            <span :class="(item.qtyReceived - item.qtySent) === 0 ? 'text-emerald-600' : 'text-rose-600 bg-rose-50 px-2 py-0.5 rounded'"
                                                  x-text="(item.qtyReceived - item.qtySent) > 0 ? '+' + (item.qtyReceived - item.qtySent) : (item.qtyReceived - item.qtySent)">0</span>
                                        </td>

                                        <!-- Kondisi -->
                                        <td class="px-3 py-3">
                                            <select x-model="item.condition"
                                                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                                                <option value="BAIK">Baik / Sempurna</option>
                                                <option value="KEMASAN_RUSAK">Kemasan Rusak / Penyok</option>
                                                <option value="BOCOR_PECAH">Bocor / Pecah Rembes</option>
                                                <option value="NEAR_EXPIRED">Mendekati Expired (H-30)</option>
                                                <option value="KADALUWARSA">Kadaluarsa (ED Lewat)</option>
                                            </select>
                                        </td>

                                        <!-- Catatan -->
                                        <td class="px-4 py-3">
                                            <input type="text"
                                                   x-model="item.note"
                                                   placeholder="Keterangan..."
                                                   class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- General Reception Notes -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-gray-700 flex items-center justify-between">
                            <span>Catatan Serah Terima Fisik (BAST):</span>
                            <span class="text-[11px] text-gray-400 font-normal">Wajib diisi jika terdapat selisih atau kerusakan</span>
                        </label>
                        <textarea x-model="verifyNotes"
                                  rows="2"
                                  placeholder="Contoh: Seluruh kardus telah dibongkar, ditemukan 1 botol pecah di tumpukan bawah..."
                                  class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                    </div>

                    <!-- Warning / Info Notice -->
                    <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl flex items-start gap-3 text-xs text-blue-900">
                        <span class="material-symbols-rounded text-[20px] text-[#0052cc] shrink-0 mt-0.5">policy</span>
                        <div class="space-y-0.5">
                            <p class="font-bold">Ketentuan SOP Penerimaan Toko:</p>
                            <p class="text-blue-800 text-[11.5px] leading-relaxed">
                                Konfirmasi BAST fisik. Stok toko disesuaikan otomatis sesuai kuantitas aktual diterima.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50/50">
                    <button type="button"
                            @click="showVerifyModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Batal
                    </button>

                    <button type="button"
                            @click="confirmGoodsReceipt()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">verified</span>
                        <span>Konfirmasi Penerimaan (BAST)</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL 2: LIHAT BAST (DOKUMEN PENERIMAAN SELESAI) -->
    <template x-teleport="body">
        <div x-show="showBastModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showBastModal"
                 @click.away="showBastModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Berita Acara Penerimaan Barang (BAST)</h3>
                            <p class="text-xs text-gray-500 font-mono mt-0.5" x-text="selectedDoc?.docNo"></p>
                        </div>
                    </div>
                    <button @click="showBastModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh] text-xs">
                    <div class="grid grid-cols-2 gap-4 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">GUDANG ASAL</span>
                            <span class="font-bold text-gray-900 text-sm" x-text="selectedDoc?.warehouseOrigin"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Driver: ' + selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">STATUS & PENERIMA</span>
                            <span class="font-bold text-emerald-700 text-sm" x-text="selectedDoc?.status === 'DITERIMA_LENGKAP' ? 'Diterima Lengkap' : 'Diterima dengan Selisih'"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Oleh: ' + selectedDoc?.receiverName + ' pada ' + selectedDoc?.receivedAt"></span>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[650px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 font-bold text-gray-600 uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left w-[240px]">PRODUK</th>
                                        <th class="px-3 py-2.5 text-center w-[120px]">QTY SURAT JALAN</th>
                                        <th class="px-3 py-2.5 text-center w-[120px]">QTY DITERIMA</th>
                                        <th class="px-3 py-2.5 text-center w-[100px]">SELISIH</th>
                                        <th class="px-4 py-2.5 text-left w-[180px]">KONDISI</th>
                                    </tr>
                                </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-3 py-2.5 text-center text-gray-600" x-text="it.qtySent + ' ' + it.unit"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-gray-900" x-text="it.qtyReceived + ' ' + it.unit"></td>
                                        <td class="px-3 py-2.5 text-center font-bold font-mono">
                                            <span :class="(it.qtyReceived - it.qtySent) === 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                  x-text="(it.qtyReceived - it.qtySent) === 0 ? 'Sesuai' : (it.qtyReceived - it.qtySent)"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-700" x-text="it.condition + (it.note ? ' (' + it.note + ')' : '')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="font-bold text-gray-500 text-[10px] uppercase block mb-1">CATATAN BAST DOKUMEN:</span>
                        <p class="text-gray-800 italic" x-text="selectedDoc?.generalNotes || 'Tidak ada catatan tambahan.'"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-slate-50">
                    <button type="button"
                            @click="showBastModal = false"
                            class="px-5 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
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
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span class="text-xs font-semibold" x-text="toastMessage"></span>
    </div>

</div>
@endsection
