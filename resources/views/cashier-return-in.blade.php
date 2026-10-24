@extends('layouts.app')

@section('title', 'Barang Masuk Retur - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    typeFilter: 'all',
    statusFilter: 'all',
    showVerifyModal: false,
    showBastModal: false,
    selectedDoc: null,
    verifyNotes: '',
    toastMessage: '',
    showToast: false,

    returnInDocuments: [],

    initReturnIn() {
        const stored = localStorage.getItem('pos_ilp_return_in_docs_v1');
        if (stored) {
            try {
                this.returnInDocuments = JSON.parse(stored);
            } catch(e) {
                this.returnInDocuments = this.getDefaultDocs();
            }
        } else {
            this.returnInDocuments = this.getDefaultDocs();
            this.saveDocs();
        }
    },

    saveDocs() {
        localStorage.setItem('pos_ilp_return_in_docs_v1', JSON.stringify(this.returnInDocuments));
    },

    getDefaultDocs() {
        return [
            {
                id: 1,
                docNo: '#REP-GD-20261024-001',
                type: 'PENGGANTIAN_GARANSI', // PENGGANTIAN_GARANSI | RETUR_DITOLAK | PENGEMBALIAN_TOKO
                typeLabel: 'BARANG PENGGANTI (GARANSI)',
                originRef: '#RTR-TK-20261020-001',
                warehouseOrigin: 'DC Cikarang (Pusat Logistik)',
                destinationStore: 'Minimarket ILP - Sektor A',
                date: '24 Oktober 2026 • 11:30 WIB',
                driverName: 'Rahmat Hidayat',
                plateNumber: 'B 9102 UQ',
                status: 'MENUNGGU_VERIFIKASI', // MENUNGGU_VERIFIKASI | DITERIMA_LENGKAP | DITERIMA_SELISIH
                receivedAt: null,
                receiverName: null,
                generalNotes: 'Barang baru pengganti kompensasi supplier atas retur klaim susu rusak sebelumnya.',
                items: [
                    { sku: '8993456789012', name: 'Ultra Milk Full Cream 1L (Fresh Batch)', unit: 'Tetra', qtySent: 48, qtyReceived: 48, condition: 'BAIK', note: 'Segel pabrik baru, batch ED 2027' },
                    { sku: '8991234567890', name: 'Aqua Mineral 600ml (Ganti Bocor)', unit: 'Botol', qtySent: 24, qtyReceived: 24, condition: 'BAIK', note: 'Kondisi mulus' }
                ]
            },
            {
                id: 2,
                docNo: '#REJ-GD-20261023-002',
                type: 'RETUR_DITOLAK',
                typeLabel: 'RETUR DITOLAK GUDANG',
                originRef: '#RTR-TK-20261018-005',
                warehouseOrigin: 'DC Cikarang (QC Logistik)',
                destinationStore: 'Minimarket ILP - Sektor A',
                date: '23 Oktober 2026 • 15:45 WIB',
                driverName: 'Andi Pratama',
                plateNumber: 'B 9481 UQ',
                status: 'MENUNGGU_VERIFIKASI',
                receivedAt: null,
                receiverName: null,
                generalNotes: 'Klaim retur ditolak QC DC karena kerusakan akibat kelalaian penanganan toko (bukan cacat pabrik). Dikembalikan untuk penyesuaian susut gerai.',
                items: [
                    { sku: '8998901234567', name: 'Biskuit Kaleng Butter Cookies', unit: 'Kaleng', qtySent: 2, qtyReceived: 2, condition: 'CACAT_DITOLAK', note: 'Kaleng penyok akibat jatuh di gerai' }
                ]
            },
            {
                id: 3,
                docNo: '#REP-GD-20261022-008',
                type: 'PENGGANTIAN_GARANSI',
                typeLabel: 'BARANG PENGGANTI (GARANSI)',
                originRef: '#RTR-TK-20261015-002',
                warehouseOrigin: 'DC Cibitung (Hub Sembako)',
                destinationStore: 'Minimarket ILP - Sektor A',
                date: '22 Oktober 2026 • 10:15 WIB',
                driverName: 'Joko Susilo',
                plateNumber: 'B 9211 UQ',
                status: 'DITERIMA_LENGKAP',
                receivedAt: '22 Okt 2026 10:40 WIB',
                receiverName: 'Budi Kurniawan (Kasir)',
                generalNotes: 'Barang pengganti minyak goreng bocor telah diverifikasi fisik dan masuk ke stok aktif rak.',
                items: [
                    { sku: '8996789012345', name: 'Minyak Goreng Sania 2L', unit: 'Pouch', qtySent: 12, qtyReceived: 12, condition: 'BAIK', note: 'Kondisi karton baik' }
                ]
            },
            {
                id: 4,
                docNo: '#RTN-GD-20261021-003',
                type: 'PENGEMBALIAN_TOKO',
                typeLabel: 'BARANG DIKEMBALIKAN KE TOKO',
                originRef: '#RTR-TK-20261016-004',
                warehouseOrigin: 'DC Cikarang (Pusat Logistik)',
                destinationStore: 'Minimarket ILP - Sektor A',
                date: '21 Oktober 2026 • 13:20 WIB',
                driverName: 'Dedi Kurnia',
                plateNumber: 'B 9844 XZ',
                status: 'DITERIMA_SELISIH',
                receivedAt: '21 Okt 2026 14:05 WIB',
                receiverName: 'Andi Pratama (Kasir)',
                generalNotes: 'Pengembalian produk setelah rekonsiliasi transfer. Terdapat selisih kurang 1 pack.',
                items: [
                    { sku: '8994567890123', name: 'Beras Pandan Wangi 5kg', unit: 'Pack', qtySent: 6, qtyReceived: 5, condition: 'BAIK', note: 'Kurang 1 pack saat serah terima armada' }
                ]
            }
        ];
    },

    get filteredDocuments() {
        return this.returnInDocuments.filter(doc => {
            if (this.typeFilter !== 'all' && doc.type !== this.typeFilter) return false;
            if (this.statusFilter !== 'all' && doc.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchNo = doc.docNo.toLowerCase().includes(q);
                const matchRef = doc.originRef.toLowerCase().includes(q);
                const matchWh = doc.warehouseOrigin.toLowerCase().includes(q);
                const matchDriver = doc.driverName.toLowerCase().includes(q) || doc.plateNumber.toLowerCase().includes(q);
                const matchProd = doc.items.some(it => it.name.toLowerCase().includes(q) || it.sku.includes(q));
                return matchNo || matchRef || matchWh || matchDriver || matchProd;
            }
            return true;
        });
    },

    get totalCount() {
        return this.returnInDocuments.length;
    },

    get pendingCount() {
        return this.returnInDocuments.filter(d => d.status === 'MENUNGGU_VERIFIKASI').length;
    },

    get replacementCount() {
        return this.returnInDocuments.filter(d => d.type === 'PENGGANTIAN_GARANSI').length;
    },

    get rejectedCount() {
        return this.returnInDocuments.filter(d => d.type === 'RETUR_DITOLAK').length;
    },

    openVerification(doc) {
        this.selectedDoc = JSON.parse(JSON.stringify(doc));
        this.verifyNotes = doc.generalNotes || '';
        this.showVerifyModal = true;
    },

    openBastView(doc) {
        this.selectedDoc = doc;
        this.showBastModal = true;
    },

    updateInventoryStock(items, docType) {
        // Only add stock if it is replacement or returned to store for sale
        if (docType === 'RETUR_DITOLAK') return; // Rejected items are defective
        const rawProd = localStorage.getItem('pos_ilp_inventory_products_v1');
        if (!rawProd) return;
        try {
            let prods = JSON.parse(rawProd);
            items.forEach(it => {
                const receivedQty = parseInt(it.qtyReceived, 10) || 0;
                if (receivedQty > 0 && it.condition === 'BAIK') {
                    const p = prods.find(pr => pr.sku === it.sku || pr.barcode === it.sku || (pr.nama && pr.nama.toLowerCase().includes(it.name.toLowerCase())));
                    if (p) {
                        p.stok = (parseInt(p.stok, 10) || 0) + receivedQty;
                    }
                }
            });
            localStorage.setItem('pos_ilp_inventory_products_v1', JSON.stringify(prods));
        } catch(e) {
            console.error('Error updating stock', e);
        }
    },

    confirmReturnReceipt() {
        if (!this.selectedDoc) return;

        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.returnInDocuments.find(d => d.id === this.selectedDoc.id);
        if (original) {
            original.items = this.selectedDoc.items.map(it => ({
                ...it,
                qtyReceived: Math.max(0, parseInt(it.qtyReceived, 10) || 0)
            }));

            const hasVariance = original.items.some(it => it.qtyReceived !== it.qtySent || (it.condition !== 'BAIK' && original.type !== 'RETUR_DITOLAK'));

            original.status = hasVariance ? 'DITERIMA_SELISIH' : 'DITERIMA_LENGKAP';
            original.receivedAt = nowStr;
            original.receiverName = (this.activeSession?.cashierName || 'Budi Kurniawan') + ' (Kasir)';
            original.generalNotes = this.verifyNotes.trim() || 'Verifikasi fisik barang masuk retur selesai dikonfirmasi kasir.';

            this.updateInventoryStock(original.items, original.type);
            this.saveDocs();

            this.showVerifyModal = false;
            this.showToastNotice(`Dokumen retur masuk ${original.docNo} berhasil diverifikasi!`);
        }
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); initReturnIn();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="retur_masuk" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Barang Masuk Retur (Gudang ke Toko)">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">assignment_return</span>
                    <span>Retur Masuk Toko</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumb & Header Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>RETUR BARANG</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">BARANG MASUK RETUR (GUDANG KE TOKO)</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Verifikasi Barang Masuk Retur</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Verifikasi fisik barang pengganti garansi atau pengembalian retur dari Gudang DC.</p>
                    </div>

                    <!-- Terminal Info Badge -->
                    <div class="bg-white border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs">
                        <div class="w-2.5 h-2.5 rounded-full bg-purple-600"></div>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">PETUGAS VERIFIKASI</span>
                            <span class="text-xs font-bold text-gray-800" x-text="activeSession.terminalLabel + ' • ' + activeSession.cashierName">Kasir 01 • Budi Kurniawan</span>
                        </div>
                    </div>
                </div>

                <!-- 4 Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1 -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">TOTAL DOKUMEN RETUR MASUK</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="totalCount">4</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Transfer periode aktif</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">assignment_return</span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">MENUNGGU VERIFIKASI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-amber-600" x-text="pendingCount">2</h3>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">Tiba di Toko</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Perlu cek fisik & BAST</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">pending_actions</span>
                        </div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase">BARANG PENGGANTI (GARANSI)</span>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1" x-text="replacementCount">2</h3>
                            <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Kompensasi DC Supplier</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">published_with_changes</span>
                        </div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 tracking-wider uppercase">RETUR DITOLAK GUDANG</span>
                            <h3 class="text-2xl font-black text-rose-600 mt-1" x-text="rejectedCount">1</h3>
                            <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Write-off / Audit Gerai</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">cancel</span>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor dokumen, referensi klaim, DC asal, atau produk..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Type Filter -->
                        <div class="relative">
                            <select x-model="typeFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Jenis Retur Masuk</option>
                                <option value="PENGGANTIAN_GARANSI">Barang Pengganti (Garansi)</option>
                                <option value="RETUR_DITOLAK">Retur Ditolak Gudang</option>
                                <option value="PENGEMBALIAN_TOKO">Pengembalian ke Toko</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Status Filter -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status</option>
                                <option value="MENUNGGU_VERIFIKASI">Menunggu Verifikasi</option>
                                <option value="DITERIMA_LENGKAP">Diterima Lengkap</option>
                                <option value="DITERIMA_SELISIH">Diterima Selisih</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-purple-700">move_to_inbox</span>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Dokumen Barang Masuk Retur</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredDocuments.length + ' dokumen'"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold uppercase text-[10px] tracking-wider">
                                    <th class="px-6 py-3.5">JENIS & NO. DOKUMEN</th>
                                    <th class="px-4 py-3.5">REFERENSI ASAL</th>
                                    <th class="px-4 py-3.5">CABANG / DC ASAL</th>
                                    <th class="px-4 py-3.5 text-center">KUANTITAS</th>
                                    <th class="px-4 py-3.5 text-center">STATUS</th>
                                    <th class="px-6 py-3.5 text-right">AKSI VERIFIKASI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <!-- Jenis & No Dokumen -->
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col items-start gap-1">
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold"
                                                      :class="doc.type === 'PENGGANTIAN_GARANSI' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (doc.type === 'RETUR_DITOLAK' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200')"
                                                      x-text="doc.typeLabel"></span>
                                                <span class="font-mono font-bold text-gray-900 text-[13px] mt-0.5" x-text="doc.docNo"></span>
                                                <span class="text-[11px] text-gray-500" x-text="doc.date"></span>
                                            </div>
                                        </td>

                                        <!-- Referensi Asal -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 text-xs">Klaim Retur Awal:</span>
                                                <span class="font-mono text-[#0052cc] font-bold text-xs" x-text="doc.originRef"></span>
                                            </div>
                                        </td>

                                        <!-- Cabang Asal & Driver -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-900 text-xs" x-text="doc.warehouseOrigin"></span>
                                                <span class="text-[11px] text-gray-500 mt-0.5" x-text="'Driver: ' + doc.driverName + ' (' + doc.plateNumber + ')'"></span>
                                            </div>
                                        </td>

                                        <!-- Kuantitas -->
                                        <td class="px-4 py-4 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="font-bold text-gray-900 text-[13px]" x-text="doc.items.length + ' SKU'"></span>
                                                <span class="text-[11px] text-gray-500" x-text="doc.items.reduce((a, b) => a + Number(b.qtySent), 0) + ' Unit Fisik'"></span>
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="px-4 py-4 text-center">
                                            <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    <span>Menunggu Verifikasi</span>
                                                </span>
                                            </template>
                                            <template x-if="doc.status === 'DITERIMA_LENGKAP'">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="material-symbols-rounded text-[14px]">check_circle</span>
                                                    <span>Diterima Lengkap</span>
                                                </span>
                                            </template>
                                            <template x-if="doc.status === 'DITERIMA_SELISIH'">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="material-symbols-rounded text-[14px]">warning</span>
                                                    <span>Diterima Selisih</span>
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Action Button -->
                                        <td class="px-6 py-4 text-right">
                                            <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                <button type="button"
                                                        @click="openVerification(doc)"
                                                        class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 ml-auto cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">fact_check</span>
                                                    <span>Verifikasi Fisik</span>
                                                </button>
                                            </template>
                                            <template x-if="doc.status !== 'MENUNGGU_VERIFIKASI'">
                                                <button type="button"
                                                        @click="openBastView(doc)"
                                                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 ml-auto cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">receipt_long</span>
                                                    <span>Lihat BAST</span>
                                                </button>
                                            </template>
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

    <!-- MODAL VERIFIKASI FISIK BARANG MASUK RETUR -->
    <template x-teleport="body">
        <div x-show="showVerifyModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showVerifyModal"
                 @click.away="showVerifyModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">assignment_return</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Verifikasi Fisik Barang Masuk Retur</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedDoc?.docNo + ' • Ref: ' + selectedDoc?.originRef"></span>
                        </div>
                    </div>
                    <button @click="showVerifyModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-4 text-xs">
                    <!-- Delivery Header Info -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">JENIS DOKUMEN</span>
                            <span class="font-bold text-purple-700" x-text="selectedDoc?.typeLabel"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">DC PENGIRIM</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.warehouseOrigin"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PENGEMUDI / ARMADA</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.driverName + ' (' + selectedDoc?.plateNumber + ')'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">KASIR PENERIMA</span>
                            <span class="font-bold text-[#0052cc]" x-text="activeSession.cashierName">Budi Kurniawan</span>
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600">
                                <tr>
                                    <th class="px-4 py-3">PRODUK / SKU</th>
                                    <th class="px-3 py-3 text-center">QTY SURAT JALAN</th>
                                    <th class="px-3 py-3 text-center">QTY AKTUAL DITERIMA</th>
                                    <th class="px-3 py-3 text-center">SELISIH</th>
                                    <th class="px-3 py-3">KONDISI FISIK</th>
                                    <th class="px-4 py-3">CATATAN KHUSUS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(item, idx) in selectedDoc?.items" :key="idx">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-3">
                                            <span class="font-bold text-gray-900 block" x-text="item.name"></span>
                                            <span class="font-mono text-[11px] text-gray-400" x-text="'SKU: ' + item.sku"></span>
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold text-gray-700" x-text="item.qtySent + ' ' + item.unit"></td>
                                        <td class="px-3 py-3 text-center">
                                            <input type="number"
                                                   min="0"
                                                   x-model.number="item.qtyReceived"
                                                   class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                        <td class="px-3 py-3 text-center font-bold font-mono">
                                            <span :class="(item.qtyReceived - item.qtySent) === 0 ? 'text-emerald-600' : 'text-rose-600 bg-rose-50 px-2 py-0.5 rounded'"
                                                  x-text="(item.qtyReceived - item.qtySent) > 0 ? '+' + (item.qtyReceived - item.qtySent) : (item.qtyReceived - item.qtySent)">0</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <select x-model="item.condition"
                                                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white focus:outline-none focus:border-[#0052cc]">
                                                <option value="BAIK">Baik / Segel Pabrik Baru</option>
                                                <option value="CACAT_DITOLAK">Cacat Ditolak Gudang</option>
                                                <option value="KEMASAN_RUSAK">Kemasan Rusak Transit</option>
                                                <option value="KADALUWARSA">Kadaluarsa / ED Lewat</option>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text"
                                                   x-model="item.note"
                                                   placeholder="Catatan..."
                                                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:border-[#0052cc]">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-gray-700">Catatan Berita Acara Penerimaan Retur:</label>
                        <textarea x-model="verifyNotes"
                                  rows="2"
                                  placeholder="Catatan hasil inspeksi fisik kasir..."
                                  class="w-full px-4 py-2 border border-gray-200 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-[#0052cc]"></textarea>
                    </div>

                    <!-- Policy Info Box -->
                    <div class="p-3.5 bg-purple-50/70 border border-purple-200 rounded-xl text-xs text-purple-900 flex items-start gap-3">
                        <span class="material-symbols-rounded text-purple-700 text-[20px] shrink-0 mt-0.5">policy</span>
                        <div>
                            <p class="font-bold">Ketentuan Stok Retur Masuk:</p>
                            <p class="text-purple-800 text-[11.5px] leading-relaxed">
                                Barang pengganti garansi menambah stok toko. Retur ditolak dialokasikan ke area afkir.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="showVerifyModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 font-bold text-xs hover:bg-gray-100 transition-colors cursor-pointer">
                        Batal
                    </button>

                    <button type="button"
                            @click="confirmReturnReceipt()"
                            class="px-6 py-2.5 rounded-xl bg-[#0052cc] hover:bg-[#0043a8] text-white font-bold text-xs flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[18px]">verified</span>
                        <span>Konfirmasi Penerimaan (BAST)</span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- MODAL LIHAT BAST SELESAI -->
    <template x-teleport="body">
        <div x-show="showBastModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showBastModal"
                 @click.away="showBastModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">BAST Penerimaan Barang Masuk Retur</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedDoc?.docNo + ' • ' + selectedDoc?.receivedAt"></span>
                        </div>
                    </div>
                    <button @click="showBastModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[70vh]">
                    <div class="grid grid-cols-2 gap-3 p-4 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">JENIS DOKUMEN</span>
                            <span class="font-bold text-purple-700" x-text="selectedDoc?.typeLabel"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Ref Asal: ' + selectedDoc?.originRef"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">STATUS & PENERIMA</span>
                            <span class="font-bold text-emerald-700" x-text="selectedDoc?.status === 'DITERIMA_LENGKAP' ? 'Diterima Lengkap' : 'Diterima Selisih'"></span>
                            <span class="text-gray-500 text-[11px] block mt-0.5" x-text="'Oleh: ' + selectedDoc?.receiverName"></span>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK</th>
                                    <th class="px-3 py-2.5 text-center">QTY SURAT JALAN</th>
                                    <th class="px-3 py-2.5 text-center">QTY DITERIMA</th>
                                    <th class="px-4 py-2.5">KONDISI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-3 py-2.5 text-center text-gray-600" x-text="it.qtySent + ' ' + it.unit"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-gray-900" x-text="it.qtyReceived + ' ' + it.unit"></td>
                                        <td class="px-4 py-2.5 text-gray-700" x-text="it.condition + (it.note ? ' (' + it.note + ')' : '')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="font-bold text-gray-500 text-[10px] uppercase block mb-1">CATATAN BAST:</span>
                        <p class="text-gray-800 italic" x-text="selectedDoc?.generalNotes"></p>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-slate-50">
                    <button type="button"
                            @click="showBastModal = false"
                            class="px-5 py-2 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>

    <!-- TOAST -->
    <div x-show="showToast"
         x-transition.opacity
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 bg-gray-900 text-white px-5 py-3.5 rounded-2xl shadow-xl flex items-center gap-3 border border-gray-800 text-xs font-semibold">
        <span class="material-symbols-rounded text-emerald-400 text-[20px]">check_circle</span>
        <span x-text="toastMessage"></span>
    </div>

</div>
@endsection
