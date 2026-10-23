@extends('layouts.app')

@section('title', 'Ajukan Retur Barang - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'form', // 'form' | 'history'
    searchHistory: '',
    statusFilter: 'all',
    showDetailModal: false,
    selectedDoc: null,
    toastMessage: '',
    showToast: false,

    // Form State
    barcodeInput: '8992388102315',
    selectedProduct: {
        sku: 'MYK-0021',
        barcode: '8992388102315',
        name: 'Minyak Goreng Sawit 2 Liter Pouch',
        category: 'Kebutuhan Pokok',
        buyPrice: 34500,
        unit: 'Pouch'
    },
    inputQty: 2,
    selectedUnit: 'Pouch',
    selectedReason: 'RUSAK_BOCOR',
    kasirNotes: 'Kardus minyak rembes di bagian dasar, kemasan bocor halus saat penataan di rak display.',

    // Active Draft Batch Items
    draftItems: [
        { id: 1, sku: 'SKU-SUSU-0914', name: 'Susu UHT Full Cream 1000ml', reason: 'RUSAK_BOCOR', reasonLabel: 'RUSAK / BOCOR', note: 'Tutup jebol di display rak', qty: 4, unit: 'Karton', claimPrice: 135000, subtotal: 540000 },
        { id: 2, sku: 'SKU-ROTI-0102', name: 'Roti Tawar Gandum Spesial', reason: 'KEDALUWARSA', reasonLabel: 'KEDALUWARSA / ED', note: 'Batas ED lewat batas retur (H-1)', qty: 5, unit: 'Pack', claimPrice: 17000, subtotal: 85000 },
        { id: 3, sku: 'SKU-BSK-4420', name: 'Biskuit Butter Cookies Kaleng', reason: 'OVERSTOCK', reasonLabel: 'MUTASI OVERSTOCK', note: 'Sisa stok musiman tidak bergerak', qty: 3, unit: 'Kaleng', claimPrice: 65000, subtotal: 195000 },
        { id: 4, sku: 'MYK-0021', name: 'Minyak Goreng Sawit 2L', reason: 'RUSAK_BOCOR', reasonLabel: 'RUSAK / BOCOR', note: 'Rembes lipatan kemasan pouch', qty: 6, unit: 'Pouch', claimPrice: 34500, subtotal: 207000 }
    ],

    // Return History Documents (Toko -> Gudang DC) with SOP Lifecycle
    historyDocuments: [
        {
            docNo: '#RTR-TK-20261024-001',
            date: '24 Oktober 2026 • 10:15 WIB',
            storeLocation: 'Minimarket ILP - Sektor A',
            targetWarehouse: 'DC Cikarang (Pusat Logistik)',
            sopStatus: 'DIAJUKAN_TOKO', // DIAJUKAN_TOKO | DISETUJUI_SPV | PENYERAHAN_FISIK | DITERIMA_GUDANG
            statusLabel: 'DRAFT DIAJUKAN',
            statusDesc: 'Menunggu verifikasi Supervisor Toko. Barang masih di area karantina toko (stok belum dipotong).',
            totalSku: 4,
            totalKoli: 18,
            totalClaimValue: 1027000,
            submittedBy: 'Budi Kurniawan (Kasir)',
            spvApprover: null,
            dispatchedAt: null,
            items: [
                { sku: 'SKU-SUSU-0914', name: 'Susu UHT Full Cream 1000ml', qty: '4 Karton', reason: 'Rusak / Bocor kemasan' },
                { sku: 'SKU-ROTI-0102', name: 'Roti Tawar Gandum Spesial', qty: '5 Pack', reason: 'Kedaluwarsa ED' },
                { sku: 'SKU-BSK-4420', name: 'Biskuit Butter Cookies Kaleng', qty: '3 Kaleng', reason: 'Mutasi Overstock' },
                { sku: 'MYK-0021', name: 'Minyak Goreng Sawit 2L', qty: '6 Pouch', reason: 'Rembes kemasan' }
            ]
        },
        {
            docNo: '#RTR-TK-20261023-004',
            date: '23 Oktober 2026 • 16:30 WIB',
            storeLocation: 'Minimarket ILP - Sektor A',
            targetWarehouse: 'DC Cikarang (Pusat Logistik)',
            sopStatus: 'DISETUJUI_SPV',
            statusLabel: 'DISETUJUI SUPERVISOR',
            statusDesc: 'Telah disetujui Kepala Toko. Menunggu kedatangan armada logistik DC untuk serah terima fisik.',
            totalSku: 2,
            totalKoli: 12,
            totalClaimValue: 720000,
            submittedBy: 'Andi Pratama (Kasir)',
            spvApprover: 'Hendra Wijaya (Supervisor Toko)',
            dispatchedAt: null,
            items: [
                { sku: '8993456789012', name: 'Ultra Milk Cokelat 1L', qty: '8 Tetra', reason: 'Kemasan penyok' },
                { sku: '8992345678901', name: 'Chitato Sapi Panggang 68g', qty: '4 Karton', reason: 'Kedaluwarsa batch' }
            ]
        },
        {
            docNo: '#RTR-TK-20261022-002',
            date: '22 Oktober 2026 • 11:00 WIB',
            storeLocation: 'Minimarket ILP - Sektor A',
            targetWarehouse: 'DC Cikarang (Pusat Logistik)',
            sopStatus: 'PENYERAHAN_FISIK',
            statusLabel: 'PENYERAHAN FISIK (GOODS ISSUED)',
            statusDesc: 'BAST serah terima fisik barang telah ditandatangani pengemudi DC. Titik mutasi pengurangan stok toko.',
            totalSku: 3,
            totalKoli: 24,
            totalClaimValue: 1450000,
            submittedBy: 'Budi Kurniawan (Kasir)',
            spvApprover: 'Hendra Wijaya (Supervisor Toko)',
            dispatchedAt: '22 Okt 2026 13:45 WIB • Driver: Joko (B 9211 UQ)',
            items: [
                { sku: '8995678901234', name: 'Sunlight Jeruk Nipis 755ml', qty: '12 Botol', reason: 'Tutup pecah' },
                { sku: '8996789012345', name: 'Minyak Goreng Sania 2L', qty: '8 Pouch', reason: 'Bocor kemasan' },
                { sku: '8994567890123', name: 'Beras Pandan Wangi 5kg', qty: '4 Pack', reason: 'Kutu beras' }
            ]
        },
        {
            docNo: '#RTR-TK-20261020-001',
            date: '20 Oktober 2026 • 09:15 WIB',
            storeLocation: 'Minimarket ILP - Sektor A',
            targetWarehouse: 'DC Cikarang (Pusat Logistik)',
            sopStatus: 'DITERIMA_GUDANG',
            statusLabel: 'SELESAI (DITERIMA DC)',
            statusDesc: 'Gudang DC telah menyelesaikan verifikasi fisik barang dan menerbitkan nota kredit/kompensasi.',
            totalSku: 2,
            totalKoli: 15,
            totalClaimValue: 890000,
            submittedBy: 'Citra Dewi (Kasir)',
            spvApprover: 'Hendra Wijaya (Supervisor Toko)',
            dispatchedAt: '20 Okt 2026 11:20 WIB • Driver: Rahmat (B 9102 UQ)',
            items: [
                { sku: '8991234567890', name: 'Aqua Mineral 600ml', qty: '10 Karton', reason: 'Bocor karton' },
                { sku: '899275321004', name: 'Indomie Goreng 85g', qty: '5 Karton', reason: 'Remuk packaging' }
            ]
        }
    ],

    get totalDraftValue() {
        return this.draftItems.reduce((acc, it) => acc + (it.subtotal || 0), 0);
    },

    get totalDraftQty() {
        return this.draftItems.reduce((acc, it) => acc + (it.qty || 0), 0);
    },

    get filteredHistory() {
        return this.historyDocuments.filter(doc => {
            if (this.statusFilter !== 'all' && doc.sopStatus !== this.statusFilter) return false;
            if (this.searchHistory.trim() !== '') {
                const q = this.searchHistory.toLowerCase();
                const matchNo = doc.docNo.toLowerCase().includes(q);
                const matchStore = doc.storeLocation.toLowerCase().includes(q);
                const matchWh = doc.targetWarehouse.toLowerCase().includes(q);
                return matchNo || matchStore || matchWh;
            }
            return true;
        });
    },

    addItemToDraft() {
        if (!this.selectedProduct || this.inputQty <= 0) return;

        const reasonLabels = {
            'RUSAK_BOCOR': 'RUSAK / BOCOR',
            'KEDALUWARSA': 'KEDALUWARSA / ED',
            'OVERSTOCK': 'MUTASI OVERSTOCK',
            'CACAT_PABRIK': 'CACAT SEGEL PABRIK'
        };

        const newItem = {
            id: Date.now(),
            sku: this.selectedProduct.sku,
            name: this.selectedProduct.name,
            reason: this.selectedReason,
            reasonLabel: reasonLabels[this.selectedReason] || 'RUSAK',
            note: this.kasirNotes.trim() || 'Catatan pemeriksaan kasir',
            qty: this.inputQty,
            unit: this.selectedUnit,
            claimPrice: this.selectedProduct.buyPrice,
            subtotal: this.inputQty * this.selectedProduct.buyPrice
        };

        this.draftItems.push(newItem);
        this.showToastNotice(`${this.selectedProduct.name} (${this.inputQty} ${this.selectedUnit}) dimasukkan ke draf retur.`);
        this.inputQty = 1;
    },

    removeDraftItem(idx) {
        this.draftItems.splice(idx, 1);
        this.showToastNotice('Item berhasil dihapus dari draf retur.');
    },

    submitBatchToSupervisor() {
        if (this.draftItems.length === 0) return;

        const newDocNo = '#RTR-TK-' + new Date().getFullYear() + '10' + Math.floor(100 + Math.random() * 900);
        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const newDoc = {
            docNo: newDocNo,
            date: nowStr,
            storeLocation: 'Minimarket ILP - Sektor A',
            targetWarehouse: 'DC Cikarang (Pusat Logistik)',
            sopStatus: 'DIAJUKAN_TOKO',
            statusLabel: 'DRAFT DIAJUKAN',
            statusDesc: 'Pengajuan retur berhasil dikirim ke antrean review Supervisor Toko. Barang disimpan di karantina gerai.',
            totalSku: this.draftItems.length,
            totalKoli: this.totalDraftQty,
            totalClaimValue: this.totalDraftValue,
            submittedBy: (this.activeSession.cashierName || 'Budi Kurniawan') + ' (Kasir)',
            spvApprover: null,
            dispatchedAt: null,
            items: this.draftItems.map(it => ({
                sku: it.sku,
                name: it.name,
                qty: it.qty + ' ' + it.unit,
                reason: it.reasonLabel + ' (' + it.note + ')'
            }))
        };

        this.historyDocuments.unshift(newDoc);
        this.draftItems = [];
        this.activeTab = 'history';
        this.showToastNotice(`Dokumen pengajuan retur ${newDocNo} berhasil dibuat!`);
    },

    openDetailModal(doc) {
        this.selectedDoc = doc;
        this.showDetailModal = true;
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
    <x-cashier-sidebar active="retur" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Ajukan Retur Barang (Toko ke Gudang)">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">local_shipping</span>
                    <span>Retur Toko <span class="material-symbols-rounded text-[12px]">arrow_forward</span> Gudang DC</span>
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
                            <span class="text-[#0052cc]">AJUKAN RETUR KE GUDANG PUSAT</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Formulir Pengajuan Retur Barang</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Pengajuan retur barang cacat, rusak kemasan, atau kedaluwarsa ke Gudang DC.</p>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="flex items-center bg-gray-200/70 p-1 rounded-xl gap-1 shrink-0">
                        <button type="button"
                                @click="activeTab = 'form'"
                                :class="activeTab === 'form' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">edit_note</span>
                            <span>Form Pengajuan</span>
                        </button>
                        <button type="button"
                                @click="activeTab = 'history'"
                                :class="activeTab === 'history' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">history</span>
                            <span>Riwayat Dokumen Retur</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-blue-100 text-[#0052cc]" x-text="historyDocuments.length">4</span>
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">DRAFT MUATAN SAAT INI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-900" x-text="draftItems.length + ' SKU'">4 SKU</h3>
                                <span class="text-xs font-bold text-gray-500" x-text="totalDraftQty + ' Unit'">18 Unit</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Di area karantina toko</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">inventory_2</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 tracking-wider uppercase">KATEGORI RUSAK / BOCOR</span>
                            <h3 class="text-2xl font-black text-rose-600 mt-1">2 SKU</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Rembes lipatan & kemasan cacat</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">broken_image</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">KEDALUWARSA / ED</span>
                            <h3 class="text-2xl font-black text-amber-600 mt-1">1 SKU</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">&le; Batas retur pajang H-14</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">event_busy</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase">TOTAL NILAI KLAIM POKOK</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1" x-text="formatRupiah(totalDraftValue)">Rp 1.027.000</h3>
                            <span class="text-[10px] text-emerald-600 font-bold mt-0.5 block">Dijamin kompensasi DC</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified</span>
                        </div>
                    </div>
                </div>

                <!-- VIEW 1: FORM PENGAJUAN RETUR TOKO -->
                <div x-show="activeTab === 'form'" class="grid grid-cols-12 gap-6">

                    <!-- Form Input Card (Col 5) -->
                    <div class="col-span-12 lg:col-span-5 bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-5">
                        <div class="flex items-start justify-between pb-3 border-b border-gray-100">
                            <div>
                                <h3 class="font-bold text-gray-900 text-sm">Input Item Retur Barang</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5">Pindai barcode atau pilih produk yang rusak/expired</p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-gray-600 font-mono">[F2] Scan</span>
                        </div>

                        <!-- Barcode Scanner Input -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">BARCODE / SCANNER EAN-13</label>
                            <div class="flex gap-2">
                                <input type="text"
                                       x-model="barcodeInput"
                                       placeholder="Scan barcode..."
                                       class="flex-1 px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl font-mono text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                                <button type="button"
                                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 rounded-xl font-bold text-xs transition-colors shadow-2xs">
                                    Deteksi
                                </button>
                            </div>
                        </div>

                        <!-- Product Display Card -->
                        <div class="bg-blue-50/50 border border-blue-200 rounded-xl p-4 flex flex-col gap-1.5">
                            <span class="text-[10px] font-bold text-[#0052cc] uppercase tracking-wider" x-text="selectedProduct.category">KEBUTUHAN POKOK</span>
                            <h4 class="font-bold text-gray-900 text-sm leading-tight" x-text="selectedProduct.name">Minyak Goreng Sawit 2 Liter Pouch</h4>
                            <div class="flex items-center gap-4 mt-1 text-xs text-gray-600 font-medium">
                                <span>SKU: <strong class="font-mono text-gray-900" x-text="selectedProduct.sku">MYK-0021</strong></span>
                                <span>•</span>
                                <span>Hrg Beli: <strong class="font-mono text-gray-900" x-text="formatRupiah(selectedProduct.buyPrice)">Rp 34.500</strong></span>
                            </div>
                        </div>

                        <!-- Quantity & Unit -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">KUANTITAS RETUR</label>
                                <div class="flex items-center justify-between border border-gray-200 rounded-xl bg-white p-1">
                                    <button type="button"
                                            @click="inputQty = Math.max(1, inputQty - 1)"
                                            class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg text-sm font-black cursor-pointer">−</button>
                                    <span class="font-bold text-gray-900 text-sm" x-text="inputQty">2</span>
                                    <button type="button"
                                            @click="inputQty++"
                                            class="w-8 h-8 flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg text-sm font-black cursor-pointer">+</button>
                                </div>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">SATUAN</label>
                                <select x-model="selectedUnit"
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="Pouch">Pouch / Bks</option>
                                    <option value="Pcs">Pcs / Ecer</option>
                                    <option value="Karton">Karton / Dus</option>
                                    <option value="Botol">Botol</option>
                                    <option value="Kaleng">Kaleng</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reason Options (4 Cards) -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-2">KONDISI / ALASAN RETUR</label>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <label class="p-3 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-2"
                                       :class="selectedReason === 'RUSAK_BOCOR' ? 'border-rose-500 bg-rose-50/70 text-rose-900' : 'border-gray-200 bg-slate-50 text-gray-700 hover:bg-gray-100'">
                                    <input type="radio" name="reason" value="RUSAK_BOCOR" x-model="selectedReason" class="mt-0.5 text-rose-600">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[11.5px] leading-tight">Rusak / Bocor</span>
                                        <span class="text-[10px] text-gray-500 mt-0.5">Kemasan pecah / sobek</span>
                                    </div>
                                </label>

                                <label class="p-3 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-2"
                                       :class="selectedReason === 'KEDALUWARSA' ? 'border-amber-500 bg-amber-50/70 text-amber-900' : 'border-gray-200 bg-slate-50 text-gray-700 hover:bg-gray-100'">
                                    <input type="radio" name="reason" value="KEDALUWARSA" x-model="selectedReason" class="mt-0.5 text-amber-600">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[11.5px] leading-tight">Kedaluwarsa ED</span>
                                        <span class="text-[10px] text-gray-500 mt-0.5">&le; H-14 batas pajang</span>
                                    </div>
                                </label>

                                <label class="p-3 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-2"
                                       :class="selectedReason === 'OVERSTOCK' ? 'border-blue-500 bg-blue-50/70 text-blue-900' : 'border-gray-200 bg-slate-50 text-gray-700 hover:bg-gray-100'">
                                    <input type="radio" name="reason" value="OVERSTOCK" x-model="selectedReason" class="mt-0.5 text-blue-600">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[11.5px] leading-tight">Overstock</span>
                                        <span class="text-[10px] text-gray-500 mt-0.5">Stok tidak bergerak</span>
                                    </div>
                                </label>

                                <label class="p-3 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-2"
                                       :class="selectedReason === 'CACAT_PABRIK' ? 'border-purple-500 bg-purple-50/70 text-purple-900' : 'border-gray-200 bg-slate-50 text-gray-700 hover:bg-gray-100'">
                                    <input type="radio" name="reason" value="CACAT_PABRIK" x-model="selectedReason" class="mt-0.5 text-purple-600">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[11.5px] leading-tight">Cacat Pabrik</span>
                                        <span class="text-[10px] text-gray-500 mt-0.5">Segel / barcode gagal</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notes Textarea -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">CATATAN TEMUAN KASIR</label>
                            <textarea rows="2"
                                      x-model="kasirNotes"
                                      placeholder="Keterangan kondisi fisik barang..."
                                      class="w-full bg-slate-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20"></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2 pt-2">
                            <button type="button"
                                    @click="addItemToDraft()"
                                    class="flex-1 bg-[#0052cc] hover:bg-[#0043a8] text-white py-3 rounded-xl font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-colors cursor-pointer">
                                <span class="material-symbols-rounded text-[18px]">add_circle</span>
                                <span>Masukkan ke Draf Retur</span>
                            </button>
                        </div>
                    </div>

                    <!-- Right Column: Batch Muatan Table & Finalize (Col 7) -->
                    <div class="col-span-12 lg:col-span-7 flex flex-col gap-5">

                        <!-- Table Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">inventory</span>
                                    <h3 class="font-bold text-gray-900 text-sm">Daftar Muatan Batch Retur (Siap Diajukan)</h3>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc]" x-text="draftItems.length + ' SKU'"></span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold tracking-wider uppercase text-[10px]">
                                        <tr>
                                            <th class="px-5 py-3">PRODUK & SKU</th>
                                            <th class="px-4 py-3">KONDISI / ALASAN</th>
                                            <th class="px-3 py-3 text-center">QTY</th>
                                            <th class="px-4 py-3 text-right">ESTIMASI KLAIM</th>
                                            <th class="px-4 py-3 text-center">HAPUS</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(item, idx) in draftItems" :key="item.id">
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-5 py-3.5">
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-gray-900" x-text="item.name"></span>
                                                        <span class="font-mono text-[11px] text-gray-400 mt-0.5" x-text="item.sku"></span>
                                                    </div>
                                                </td>

                                                <td class="px-4 py-3.5">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                          :class="item.reason === 'RUSAK_BOCOR' ? 'bg-rose-50 text-rose-700 border border-rose-200' : (item.reason === 'KEDALUWARSA' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-blue-50 text-blue-700 border border-blue-200')"
                                                          x-text="item.reasonLabel"></span>
                                                    <p class="text-[10.5px] text-gray-500 mt-1 line-clamp-1" x-text="item.note"></p>
                                                </td>

                                                <td class="px-3 py-3.5 text-center font-bold text-gray-900">
                                                    <span x-text="item.qty + ' ' + item.unit"></span>
                                                </td>

                                                <td class="px-4 py-3.5 text-right font-mono font-bold text-gray-900">
                                                    <span x-text="formatRupiah(item.subtotal)"></span>
                                                </td>

                                                <td class="px-4 py-3.5 text-center">
                                                    <button type="button"
                                                            @click="removeDraftItem(idx)"
                                                            class="text-gray-400 hover:text-rose-600 transition-colors cursor-pointer">
                                                        <span class="material-symbols-rounded text-[18px]">delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>

                                        <template x-if="draftItems.length === 0">
                                            <tr>
                                                <td colspan="5" class="py-12 text-center text-gray-400">
                                                    Belum ada item di dalam draf retur ini. Pindai barcode di sebelah kiri untuk menambahkan.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Summary & SOP Information Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-4">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">TOTAL ESTIMASI NILAI POKOK RETUR</span>
                                <span class="font-mono text-2xl font-black text-[#0052cc]" x-text="formatRupiah(totalDraftValue)">Rp 1.027.000</span>
                            </div>

                            <!-- SOP Mutation Point Policy Notice -->
                            <div class="p-4 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-3">
                                <span class="material-symbols-rounded text-amber-600 text-[20px] shrink-0 mt-0.5">policy</span>
                                <div class="space-y-1">
                                    <p class="font-bold">Ketentuan SOP Titik Mutasi Stok Retur:</p>
                                    <p class="text-amber-800 text-[11.5px] leading-relaxed">
                                        Stok berkurang saat serah terima fisik ke armada DC (Goods Issued), bukan saat pengajuan draf.
                                    </p>
                                </div>
                            </div>

                            <!-- Action Button: Finalize Draft -->
                            <div class="flex items-center justify-between pt-2">
                                <span class="text-xs text-gray-500 font-medium">Petugas Pembuat: <strong class="text-gray-800" x-text="activeSession.cashierName">Budi Kurniawan</strong></span>

                                <button type="button"
                                        @click="submitBatchToSupervisor()"
                                        :disabled="draftItems.length === 0"
                                        :class="draftItems.length === 0 ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-[#0052cc] hover:bg-[#0043a8] cursor-pointer shadow-sm'"
                                        class="px-6 py-3 rounded-xl text-white font-bold text-xs flex items-center gap-2 transition-all">
                                    <span class="material-symbols-rounded text-[18px]">send</span>
                                    <span>Ajukan ke Supervisor & Gudang DC</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- VIEW 2: RIWAYAT DOKUMEN PENGAJUAN RETUR -->
                <div x-show="activeTab === 'history'" class="space-y-4">

                    <!-- Search & Filter Bar -->
                    <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="relative flex-1 w-full max-w-xl">
                            <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text"
                                   x-model="searchHistory"
                                   placeholder="Cari nomor pengajuan retur (#RTR-TK)..."
                                   class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                        </div>

                        <div class="relative w-full md:w-auto">
                            <select x-model="statusFilter"
                                    class="appearance-none w-full bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer min-w-[220px]">
                                <option value="all">Semua Status SOP</option>
                                <option value="DIAJUKAN_TOKO">Draft Diajukan (Menunggu SPV)</option>
                                <option value="DISETUJUI_SPV">Disetujui SPV (Siap Angkut)</option>
                                <option value="PENYERAHAN_FISIK">Penyerahan Fisik (Stok Dimutasi)</option>
                                <option value="DITERIMA_GUDANG">Selesai (Diterima DC)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold uppercase text-[10px] tracking-wider">
                                        <th class="px-6 py-3.5">NO. DOKUMEN RETUR</th>
                                        <th class="px-4 py-3.5">GUDANG TUJUAN</th>
                                        <th class="px-4 py-3.5 text-center">MUATAN</th>
                                        <th class="px-4 py-3.5 text-right">TOTAL NILAI</th>
                                        <th class="px-4 py-3.5 text-center">STATUS SOP SIKLUS</th>
                                        <th class="px-6 py-3.5 text-right">RINCIAN</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <template x-for="doc in filteredHistory" :key="doc.docNo">
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <!-- Doc No -->
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="font-mono font-bold text-gray-900 text-[13px]" x-text="doc.docNo"></span>
                                                    <span class="text-[11px] text-gray-500 mt-0.5" x-text="doc.date"></span>
                                                </div>
                                            </td>

                                            <!-- Warehouse -->
                                            <td class="px-4 py-4">
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-gray-800 text-[12.5px]" x-text="doc.targetWarehouse"></span>
                                                    <span class="text-[11px] text-gray-400" x-text="'Dari: ' + doc.storeLocation"></span>
                                                </div>
                                            </td>

                                            <!-- Muatan -->
                                            <td class="px-4 py-4 text-center">
                                                <span class="font-bold text-gray-900" x-text="doc.totalSku + ' SKU'"></span>
                                                <span class="text-[11px] text-gray-500 block" x-text="'(' + doc.totalKoli + ' Koli/Unit)'"></span>
                                            </td>

                                            <!-- Nilai -->
                                            <td class="px-4 py-4 text-right">
                                                <span class="font-mono font-bold text-gray-900" x-text="formatRupiah(doc.totalClaimValue)"></span>
                                            </td>

                                            <!-- Status -->
                                            <td class="px-4 py-4 text-center">
                                                <template x-if="doc.sopStatus === 'DIAJUKAN_TOKO'">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        <span>Menunggu Review SPV</span>
                                                    </span>
                                                </template>
                                                <template x-if="doc.sopStatus === 'DISETUJUI_SPV'">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200">
                                                        <span class="material-symbols-rounded text-[14px]">check</span>
                                                        <span>Disetujui SPV</span>
                                                    </span>
                                                </template>
                                                <template x-if="doc.sopStatus === 'PENYERAHAN_FISIK'">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                        <span class="material-symbols-rounded text-[14px]">local_shipping</span>
                                                        <span>Penyerahan Fisik (Goods Issued)</span>
                                                    </span>
                                                </template>
                                                <template x-if="doc.sopStatus === 'DITERIMA_GUDANG'">
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span class="material-symbols-rounded text-[14px]">verified</span>
                                                        <span>Diterima Selesai di DC</span>
                                                    </span>
                                                </template>
                                            </td>

                                            <!-- Action -->
                                            <td class="px-6 py-4 text-right">
                                                <button type="button"
                                                        @click="openDetailModal(doc)"
                                                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-[#0052cc] hover:text-white text-gray-700 rounded-xl text-xs font-bold transition-all shadow-2xs flex items-center gap-1.5 ml-auto cursor-pointer group">
                                                    <span class="material-symbols-rounded text-[16px] text-gray-400 group-hover:text-white transition-colors">visibility</span>
                                                    <span>Lihat Rincian</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </main>

    <!-- MODAL DETAIL DOKUMEN PENGAJUAN RETUR -->
    <template x-teleport="body">
        <div x-show="showDetailModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showDetailModal"
                 @click.away="showDetailModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[22px]">description</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Rincian Dokumen Retur Toko</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedDoc?.docNo + ' • ' + selectedDoc?.date"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[70vh]">
                    <!-- Status Lifecycle Progress -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-2">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">STATUS SIKLUS SOP</span>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-gray-900" x-text="selectedDoc?.statusLabel"></span>
                            <span class="font-mono font-bold text-sm text-[#0052cc]" x-text="formatRupiah(selectedDoc?.totalClaimValue)"></span>
                        </div>
                        <p class="text-gray-600 text-[11.5px] leading-relaxed italic" x-text="selectedDoc?.statusDesc"></p>
                    </div>

                    <!-- Items Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK</th>
                                    <th class="px-3 py-2.5 text-center">QTY</th>
                                    <th class="px-4 py-2.5">ALASAN / KONDISI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-3 py-2.5 text-center font-bold text-gray-900" x-text="it.qty"></td>
                                        <td class="px-4 py-2.5 text-gray-600" x-text="it.reason"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Log Details -->
                    <div class="grid grid-cols-2 gap-3 text-gray-600">
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">PETUGAS PEMBUAT</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.submittedBy"></span>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <span class="text-[10px] font-bold text-gray-400 uppercase block">APPROVAL SPV TOKO</span>
                            <span class="font-bold text-gray-800" x-text="selectedDoc?.spvApprover || 'Belum Diotorisasi'"></span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end bg-slate-50">
                    <button type="button"
                            @click="showDetailModal = false"
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
