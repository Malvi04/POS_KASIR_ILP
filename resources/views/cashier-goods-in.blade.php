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

    documents: [
        {
            id: 1,
            docNo: '#SJ-DC-20261024-001',
            date: '24 Oktober 2026 • 09:30 WIB',
            warehouseOrigin: 'DC Cikarang (Pusat Logistik)',
            destinationStore: 'Minimarket ILP - Sektor A',
            driverName: 'Rahmat Hidayat',
            plateNumber: 'B 9102 UQ',
            status: 'MENUNGGU_VERIFIKASI', // MENUNGGU_VERIFIKASI | DITERIMA_LENGKAP | DITERIMA_SELISIH
            receivedAt: null,
            receiverName: null,
            generalNotes: '',
            items: [
                { sku: '899275321004', name: 'Indomie Goreng Spesial 85g', category: 'Makanan', unit: 'Pcs', qtySent: 120, qtyReceived: 120, condition: 'BAIK', note: '' },
                { sku: '8991234567890', name: 'Aqua Mineral 600ml', category: 'Minuman', unit: 'Botol', qtySent: 96, qtyReceived: 96, condition: 'BAIK', note: '' },
                { sku: '8993456789012', name: 'Ultra Milk Full Cream 1L', category: 'Minuman', unit: 'Tetra', qtySent: 48, qtyReceived: 48, condition: 'BAIK', note: '' },
                { sku: '8992345678901', name: 'Chitato Sapi Panggang 68g', category: 'Snack', unit: 'Pcs', qtySent: 36, qtyReceived: 36, condition: 'BAIK', note: '' }
            ]
        },
        {
            id: 2,
            docNo: '#SJ-DC-20261024-002',
            date: '24 Oktober 2026 • 11:15 WIB',
            warehouseOrigin: 'DC Cikarang (Pusat Logistik)',
            destinationStore: 'Minimarket ILP - Sektor A',
            driverName: 'Dedi Kurnia',
            plateNumber: 'B 9844 XZ',
            status: 'MENUNGGU_VERIFIKASI',
            receivedAt: null,
            receiverName: null,
            generalNotes: '',
            items: [
                { sku: '8994567890123', name: 'Beras Pandan Wangi 5kg', category: 'Kebutuhan Pokok', unit: 'Pack', qtySent: 25, qtyReceived: 25, condition: 'BAIK', note: '' },
                { sku: '8995678901234', name: 'Minyak Goreng Sania 2L', category: 'Kebutuhan Pokok', unit: 'Pouch', qtySent: 60, qtyReceived: 60, condition: 'BAIK', note: '' },
                { sku: '8996789012345', name: 'Gula Pasir Gulaku 1kg', category: 'Kebutuhan Pokok', unit: 'Pack', qtySent: 40, qtyReceived: 40, condition: 'BAIK', note: '' }
            ]
        },
        {
            id: 3,
            docNo: '#SJ-DC-20261023-014',
            date: '23 Oktober 2026 • 14:20 WIB',
            warehouseOrigin: 'DC Cibitung (Hub Sembako)',
            destinationStore: 'Minimarket ILP - Sektor A',
            driverName: 'Andi Pratama',
            plateNumber: 'B 9481 UQ',
            status: 'DITERIMA_LENGKAP',
            receivedAt: '23 Okt 2026 14:45 WIB',
            receiverName: 'Budi Kurniawan (Kasir)',
            generalNotes: 'Penerimaan fisik lengkap, segel boks dalam kondisi prima.',
            items: [
                { sku: '8997890123456', name: 'Sunlight Jeruk Nipis 755ml', category: 'Pembersih', unit: 'Botol', qtySent: 48, qtyReceived: 48, condition: 'BAIK', note: 'Sesuai' },
                { sku: '8998901234567', name: 'Deterjen Rinso Molto 800g', category: 'Pembersih', unit: 'Pack', qtySent: 30, qtyReceived: 30, condition: 'BAIK', note: 'Sesuai' }
            ]
        },
        {
            id: 4,
            docNo: '#SJ-DC-20261022-009',
            date: '22 Oktober 2026 • 10:05 WIB',
            warehouseOrigin: 'DC Cikarang (Pusat Logistik)',
            destinationStore: 'Minimarket ILP - Sektor A',
            driverName: 'Joko Susilo',
            plateNumber: 'B 9211 UQ',
            status: 'DITERIMA_SELISIH',
            receivedAt: '22 Okt 2026 10:35 WIB',
            receiverName: 'Budi Kurniawan (Kasir)',
            generalNotes: 'Ditemukan selisih kurang 2 pack karena kemasan sobek di perjalanan truk pengirim.',
            items: [
                { sku: '8993456789012', name: 'Ultra Milk Cokelat 1L', category: 'Minuman', unit: 'Tetra', qtySent: 48, qtyReceived: 46, condition: 'RUSAK', note: '2 pack sobek/bocor rembes di karton' },
                { sku: '8991234567890', name: 'Teh Pucuk Harum 350ml', category: 'Minuman', unit: 'Botol', qtySent: 72, qtyReceived: 72, condition: 'BAIK', note: 'Kondisi baik' }
            ]
        }
    ],

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

    confirmGoodsReceipt() {
        if (!this.selectedDoc) return;

        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const original = this.documents.find(d => d.id === this.selectedDoc.id);
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
            original.receiverName = (this.activeSession.cashierName || 'Budi Kurniawan') + ' (Kasir)';
            original.generalNotes = this.verifyNotes.trim() || (hasVariance ? 'Penerimaan diverifikasi dengan catatan selisih/kondisi.' : 'Penerimaan fisik barang transfer lengkap dan sesuai.');

            this.showVerifyModal = false;
            this.showToastNotice(`Dokumen ${original.docNo} berhasil dikonfirmasi (${original.status === 'DITERIMA_LENGKAP' ? 'Lengkap Sesuai' : 'Ada Selisih Fisik'}).`);
        }
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening()"
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
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Penerimaan Barang Masuk (Gudang DC <span class="material-symbols-rounded relative top-1 text-[22px]">arrow_forward</span> Toko)</h1>
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

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-gray-200">
                                    <th class="px-6 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">NO. SURAT JALAN</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">GUDANG ASAL</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase">ARMADA / DRIVER</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-center">TOTAL KOLI</th>
                                    <th class="px-4 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-center">STATUS</th>
                                    <th class="px-6 py-3.5 text-[10px] font-bold text-gray-500 tracking-wider uppercase text-right">AKSI VERIFIKASI</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100">
                                <template x-for="doc in filteredDocuments" :key="doc.id">
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <!-- No Dokumen -->
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-mono font-bold text-gray-900 text-[13px]" x-text="doc.docNo">#SJ-DC-20261024-001</span>
                                                <span class="text-[11px] text-gray-500 mt-0.5" x-text="doc.date">24 Okt 2026 • 09:30</span>
                                            </div>
                                        </td>

                                        <!-- Gudang Asal -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 text-[13px]" x-text="doc.warehouseOrigin">DC Cikarang</span>
                                                <span class="text-[11px] text-gray-500 flex items-center gap-1 mt-0.5">
                                                    <span class="material-symbols-rounded text-[13px] text-gray-400">arrow_forward</span>
                                                    <span x-text="doc.destinationStore">Minimarket ILP</span>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Driver -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 text-[12.5px]" x-text="doc.driverName">Rahmat Hidayat</span>
                                                <span class="font-mono text-[11px] text-gray-500 font-medium" x-text="doc.plateNumber">B 9102 UQ</span>
                                            </div>
                                        </td>

                                        <!-- Total Item -->
                                        <td class="px-4 py-4 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="font-bold text-gray-900 text-[13px]" x-text="doc.items.length + ' SKU'">4 SKU</span>
                                                <span class="text-[11px] text-gray-500 font-medium" x-text="doc.items.reduce((a, b) => a + Number(b.qtySent), 0) + ' Unit'">300 Unit</span>
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

                                        <!-- Aksi -->
                                        <td class="px-6 py-4 text-right">
                                            <template x-if="doc.status === 'MENUNGGU_VERIFIKASI'">
                                                <button type="button"
                                                        @click="openVerification(doc)"
                                                        class="px-4 py-2 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 ml-auto cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">fact_check</span>
                                                    <span>Periksa Dokumen</span>
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
                    <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-100 text-gray-600 font-bold border-b border-gray-200">
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

                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK</th>
                                    <th class="px-3 py-2.5 text-center">QTY SURAT JALAN</th>
                                    <th class="px-3 py-2.5 text-center">QTY DITERIMA</th>
                                    <th class="px-3 py-2.5 text-center">SELISIH</th>
                                    <th class="px-4 py-2.5">KONDISI</th>
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
