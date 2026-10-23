@extends('layouts.app')

@section('title', 'Refund Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    activeTab: 'form', // 'form' | 'history'
    searchFakturInput: '{{ request()->query('faktur', '#TRX-20261024-0024') }}',
    foundFaktur: null,
    searchError: '',
    selectedItems: [],
    refundReason: 'CACAT_KEMASAN',
    itemCondition: 'RUSAK_KEMASAN',
    refundNotes: 'Tutup kemasan pecah saat dibuka pelanggan di rumah, belum terkonsumsi.',
    supervisorPin: '',
    pinError: '',
    showReceiptModal: false,
    selectedRefundDoc: null,
    toastMessage: '',
    showToast: false,

    // Database Mock Faktur Asli
    fakturDatabase: {
        '#TRX-20261024-0024': {
            id: '#TRX-20261024-0024',
            date: '24 Oktober 2026 • 09:15 WIB',
            cashier: 'Budi Kurniawan',
            terminal: 'Kasir 01',
            method: 'TUNAI',
            totalPaid: 28638,
            items: [
                { id: 101, name: 'Aqua Mineral 600ml', buyQty: 3, refundedQty: 0, price: 4300, unit: 'Botol' },
                { id: 102, name: 'Oreo Vanilla 133g', buyQty: 1, refundedQty: 0, price: 8200, unit: 'Pcs' },
                { id: 103, name: 'Teh Pucuk Harum 350ml', buyQty: 2, refundedQty: 0, price: 4000, unit: 'Botol' }
            ]
        },
        '#TRX-20261024-0027': {
            id: '#TRX-20261024-0027',
            date: '24 Oktober 2026 • 11:20 WIB',
            cashier: 'Budi Kurniawan',
            terminal: 'Kasir 01',
            method: 'TUNAI',
            totalPaid: 84000,
            items: [
                { id: 201, name: 'Chitato Sapi Panggang 68g', buyQty: 2, refundedQty: 0, price: 12800, unit: 'Pcs' },
                { id: 202, name: 'Sunlight Jeruk Nipis 755ml', buyQty: 1, refundedQty: 0, price: 15500, unit: 'Botol' },
                { id: 203, name: 'Indomie Goreng Spesial 85g', buyQty: 10, refundedQty: 0, price: 3500, unit: 'Pcs' },
                { id: 204, name: 'Aqua Mineral 600ml', buyQty: 2, refundedQty: 0, price: 5500, unit: 'Botol' }
            ]
        },
        '#TRX-20261024-0028': {
            id: '#TRX-20261024-0028',
            date: '24 Oktober 2026 • 11:42 WIB',
            cashier: 'Budi Kurniawan',
            terminal: 'Kasir 01',
            method: 'CAMPURAN',
            totalPaid: 145000,
            items: [
                { id: 301, name: 'Beras Pandan Wangi 5kg', buyQty: 1, refundedQty: 0, price: 82000, unit: 'Pack' },
                { id: 302, name: 'Minyak Goreng Sania 2L', buyQty: 1, refundedQty: 0, price: 36000, unit: 'Pouch' },
                { id: 303, name: 'Ultra Milk Full Cream 1L', buyQty: 1, refundedQty: 0, price: 19200, unit: 'Tetra' },
                { id: 304, name: 'Teh Pucuk Harum 350ml', buyQty: 2, refundedQty: 0, price: 4000, unit: 'Botol' }
            ]
        }
    },

    // Riwayat Dokumen Refund
    refundHistory: [
        {
            refundNo: '#RFD-20261024-001',
            date: '24 Oktober 2026 • 10:20 WIB',
            originFaktur: '#TRX-20261024-0019',
            cashier: 'Budi Kurniawan',
            reason: 'Kemasan Susu Rembes Cacat Pabrik',
            condition: 'Karantina Barang Rusak',
            itemCount: 1,
            refundAmount: 19200,
            status: 'SELESAI_DIREFUND',
            spvApproval: 'Hendra Wijaya (Supervisor)',
            items: [
                { name: 'Ultra Milk Full Cream 1L', qty: 1, price: 19200, unit: 'Tetra' }
            ]
        },
        {
            refundNo: '#RFD-20261023-002',
            date: '23 Oktober 2026 • 16:45 WIB',
            originFaktur: '#TRX-20261023-0044',
            cashier: 'Andi Pratama',
            reason: 'Salah Beli Varian Rasa Biskuit',
            condition: 'Segel Masih Utuh',
            itemCount: 1,
            refundAmount: 25600,
            status: 'SELESAI_DIREFUND',
            spvApproval: 'Hendra Wijaya (Supervisor)',
            items: [
                { name: 'Chitato Sapi Panggang 68g', qty: 2, price: 12800, unit: 'Pcs' }
            ]
        }
    ],

    lookupFaktur() {
        this.searchError = '';
        const q = (this.searchFakturInput || '').trim();
        if (!q) {
            this.searchError = 'Masukkan nomor faktur nota kasir.';
            this.foundFaktur = null;
            return;
        }

        const match = this.fakturDatabase[q] || Object.values(this.fakturDatabase).find(f => f.id.toLowerCase() === q.toLowerCase());
        if (match) {
            // Deep clone
            this.foundFaktur = JSON.parse(JSON.stringify(match));
            // Initialize selectable refund items
            this.selectedItems = this.foundFaktur.items.map(it => ({
                id: it.id,
                name: it.name,
                buyQty: it.buyQty,
                unit: it.unit,
                price: it.price,
                refundQty: 1,
                checked: false
            }));
            this.searchError = '';
        } else {
            this.foundFaktur = null;
            this.searchError = `Faktur ${q} tidak ditemukan pada antrean sesi aktif. Pastikan nomor faktur nota kasir benar.`;
        }
    },

    get totalRefundCalculated() {
        if (!this.selectedItems) return 0;
        return this.selectedItems
            .filter(it => it.checked)
            .reduce((acc, it) => acc + (Number(it.refundQty || 0) * Number(it.price || 0)), 0);
    },

    get needsSupervisorAuth() {
        return this.totalRefundCalculated > 50000;
    },

    processRefundSubmission() {
        this.pinError = '';
        const activeRefundItems = this.selectedItems.filter(it => it.checked && it.refundQty > 0);
        if (activeRefundItems.length === 0) {
            this.pinError = 'Pilih minimal satu item belanja yang akan direfund.';
            return;
        }

        // Validate max allowed qty
        for (const it of activeRefundItems) {
            if (it.refundQty > it.buyQty) {
                this.pinError = `Kuantitas refund untuk ${it.name} melebihi jumlah pembelian asli (${it.buyQty} ${it.unit}).`;
                return;
            }
        }

        // Supervisor PIN validation if > 50k
        if (this.needsSupervisorAuth) {
            if (!this.supervisorPin || (this.supervisorPin !== '123456' && this.supervisorPin.length < 4)) {
                this.pinError = 'Nominal refund di atas Rp 50.000 wajib otorisasi PIN Supervisor (Demo: 123456).';
                return;
            }
        }

        const newRefundNo = '#RFD-20261024-00' + (this.refundHistory.length + 1);
        const nowStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' • ' +
                       new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':') + ' WIB';

        const refundDoc = {
            refundNo: newRefundNo,
            date: nowStr,
            originFaktur: this.foundFaktur.id,
            cashier: this.activeSession.cashierName || 'Budi Kurniawan',
            reason: this.refundReason,
            condition: this.itemCondition,
            itemCount: activeRefundItems.length,
            refundAmount: this.totalRefundCalculated,
            status: 'SELESAI_DIREFUND',
            spvApproval: this.needsSupervisorAuth ? 'Disetujui Supervisor' : 'Otorisasi Kasir Langsung',
            items: activeRefundItems.map(it => ({
                name: it.name,
                qty: it.refundQty,
                price: it.price,
                unit: it.unit
            }))
        };

        // Record to cash movements as RETUR_TUNAI if cash method
        if (this.foundFaktur.method === 'TUNAI' || this.foundFaktur.method === 'CAMPURAN') {
            this.activeSession.cashReturn = (Number(this.activeSession.cashReturn) || 0) + this.totalRefundCalculated;
            this.cashMovements.unshift({
                id: 'CM-' + Math.floor(100 + Math.random() * 900),
                time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':'),
                type: 'RETUR_TUNAI',
                label: `Refund Pelanggan ${this.foundFaktur.id} (${newRefundNo})`,
                amount: this.totalRefundCalculated,
                direction: 'out',
                actor: this.activeSession.cashierName || 'Budi Kurniawan'
            });
            this.persistAllState();
        }

        this.refundHistory.unshift(refundDoc);
        this.selectedRefundDoc = refundDoc;
        this.showReceiptModal = true;
        this.showToastNotice(`Refund ${newRefundNo} sebesar ${this.formatRupiah(this.totalRefundCalculated)} berhasil diproses!`);

        // Reset form
        this.foundFaktur = null;
        this.searchFakturInput = '';
        this.selectedItems = [];
        this.supervisorPin = '';
    },

    openHistoryDetail(doc) {
        this.selectedRefundDoc = doc;
        this.showReceiptModal = true;
    },

    showToastNotice(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3600);
    }
}"
x-init="initOpening(); lookupFaktur();"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="transaksi_refund" />

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Refund Transaksi Pelanggan">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">currency_exchange</span>
                    <span>Pencatatan Retur Pelanggan</span>
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
                            <span>TRANSAKSI</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">REFUND PELANGGAN KE TOKO</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Pengembalian Dana & Barang Belanja (Refund)</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Klaim retur/refund pelanggan berdasarkan referensi faktur asli.</p>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="flex items-center bg-gray-200/70 p-1 rounded-xl gap-1 shrink-0">
                        <button type="button"
                                @click="activeTab = 'form'"
                                :class="activeTab === 'form' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">edit_document</span>
                            <span>Form Pengajuan Refund</span>
                        </button>
                        <button type="button"
                                @click="activeTab = 'history'"
                                :class="activeTab === 'history' ? 'bg-white text-[#0052cc] font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                class="px-4 py-2 rounded-lg text-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">history</span>
                            <span>Riwayat Refund</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700" x-text="refundHistory.length">2</span>
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">TOTAL KLAIM REFUND HARI INI</span>
                            <h3 class="text-2xl font-black text-gray-900 mt-1" x-text="refundHistory.length + ' Nota'">2 Nota</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Shift: Kasir 01</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">receipt</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-rose-500">
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 tracking-wider uppercase">TOTAL NILAI PENGEMBALIAN</span>
                            <h3 class="text-xl font-black text-rose-600 font-mono mt-1" x-text="formatRupiah(refundHistory.reduce((a, b) => a + Number(b.refundAmount), 0))">Rp 44.800</h3>
                            <span class="text-[11px] text-rose-600 font-medium mt-0.5 block">Kas keluar laci</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">currency_exchange</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">BATAS PLAFON OTORISASI KASIR</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1">Rp 50.000</h3>
                            <span class="text-[10px] text-amber-600 font-bold mt-0.5 block">&gt; Rp 50.000 Perlu SPV PIN</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-gray-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">verified_user</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-amber-500">
                        <div>
                            <span class="text-[10px] font-bold text-amber-700 tracking-wider uppercase">STATUS MUTASI STOK REFUND</span>
                            <h3 class="text-sm font-black text-gray-900 mt-1">Area Karantina Toko</h3>
                            <span class="text-[10px] text-amber-700 font-bold mt-0.5 block">Tidak otomatis masuk rak aktif</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">policy</span>
                        </div>
                    </div>
                </div>

                <!-- VIEW 1: FORM PENGAJUAN REFUND -->
                <div x-show="activeTab === 'form'" class="grid grid-cols-12 gap-6">

                    <!-- Search Faktur & Detail Header (Col 5) -->
                    <div class="col-span-12 lg:col-span-5 bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-5">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Pencarian Faktur Pembelian Asli</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Ketik atau scan nomor struk nota kasir yang dibawa pelanggan</p>
                        </div>

                        <!-- Search Bar -->
                        <div>
                            <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">NOMOR STRUK / FAKTUR</label>
                            <div class="flex gap-2">
                                <input type="text"
                                       x-model="searchFakturInput"
                                       @keyup.enter="lookupFaktur()"
                                       placeholder="Contoh: #TRX-20261024-0024"
                                       class="flex-1 px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-xl font-mono text-xs font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20">
                                <button type="button"
                                        @click="lookupFaktur()"
                                        class="bg-[#0052cc] hover:bg-[#0043a8] text-white px-4 rounded-xl font-bold text-xs transition-colors shadow-2xs cursor-pointer">
                                    Cari
                                </button>
                            </div>
                            <span x-show="searchError" class="text-xs font-bold text-rose-600 mt-1.5 block" x-text="searchError"></span>
                        </div>

                        <!-- Faktur Info Card -->
                        <template x-if="foundFaktur">
                            <div class="bg-blue-50/50 border border-blue-200 rounded-xl p-4 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono font-bold text-[#0052cc]" x-text="foundFaktur.id"></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-[#0052cc]" x-text="foundFaktur.method"></span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-gray-600">
                                    <div>
                                        <span class="text-[10px] text-gray-400 uppercase block">TANGGAL TRANSAKSI</span>
                                        <span class="font-bold text-gray-800" x-text="foundFaktur.date"></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-gray-400 uppercase block">KASIR / TERMINAL</span>
                                        <span class="font-bold text-gray-800" x-text="foundFaktur.cashier + ' (' + foundFaktur.terminal + ')'"></span>
                                    </div>
                                </div>
                                <div class="pt-2 border-t border-blue-100 flex justify-between items-baseline font-bold">
                                    <span class="text-gray-600">Total Pembayaran Asli:</span>
                                    <span class="font-mono text-sm text-gray-900" x-text="formatRupiah(foundFaktur.totalPaid)"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Reason & Conditions Form -->
                        <div class="space-y-4 pt-2 border-t border-gray-100">
                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">ALASAN KLAIM REFUND</label>
                                <select x-model="refundReason"
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="CACAT_KEMASAN">Cacat Produk / Kemasan Rusak Tersembunyi</option>
                                    <option value="KEDALUWARSA">Produk Kedaluwarsa Belum Terdeteksi</option>
                                    <option value="SALAH_VARIAN">Salah Ambil Varian Produk oleh Pelanggan</option>
                                    <option value="BARANG_TIDAK_SESUAI">Barang Tidak Sesuai Spesifikasi</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">KONDISI FISIK SAAT DIKEMBALIKAN</label>
                                <select x-model="itemCondition"
                                        class="w-full px-3 py-2.5 bg-slate-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-[#0052cc]">
                                    <option value="RUSAK_KEMASAN">Kemasan Rusak (Masuk Karantina Gerai)</option>
                                    <option value="SEMPURNA_SEGEL">Masih Segel Pabrik Utuh</option>
                                    <option value="KADALUWARSA">Kedaluwarsa (Siap Retur ke DC)</option>
                                </select>
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-gray-700 tracking-wider block mb-1.5">CATATAN PEMERIKSAAN KASIR</label>
                                <textarea rows="2"
                                          x-model="refundNotes"
                                          placeholder="Keterangan fisik barang..."
                                          class="w-full bg-slate-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-800 focus:outline-none focus:border-[#0052cc]"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Item Selection & Calculation (Col 7) -->
                    <div class="col-span-12 lg:col-span-7 flex flex-col gap-5">

                        <!-- Table Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-rounded text-[20px] text-[#0052cc]">checklist</span>
                                    <h3 class="font-bold text-gray-900 text-sm">Pilih Item Pembelian yang Direfund</h3>
                                </div>
                                <span class="text-xs text-gray-400 font-medium">Centang item dan tentukan kuantitas</span>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold uppercase text-[10px] tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3 text-center">PILIH</th>
                                            <th class="px-4 py-3">PRODUK</th>
                                            <th class="px-3 py-3 text-center">QTY BELI</th>
                                            <th class="px-4 py-3 text-center">QTY REFUND</th>
                                            <th class="px-5 py-3 text-right">NILAI REFUND</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(it, idx) in selectedItems" :key="it.id">
                                            <tr :class="it.checked ? 'bg-blue-50/40' : 'hover:bg-slate-50/50'">
                                                <!-- Checkbox -->
                                                <td class="px-4 py-3.5 text-center">
                                                    <input type="checkbox"
                                                           x-model="it.checked"
                                                           class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc] cursor-pointer">
                                                </td>

                                                <!-- Name -->
                                                <td class="px-4 py-3.5">
                                                    <span class="font-bold text-gray-900 block" x-text="it.name"></span>
                                                    <span class="font-mono text-[11px] text-gray-400" x-text="'@ ' + formatRupiah(it.price) + ' / ' + it.unit"></span>
                                                </td>

                                                <!-- Buy Qty -->
                                                <td class="px-3 py-3.5 text-center font-bold text-gray-600" x-text="it.buyQty + ' ' + it.unit"></td>

                                                <!-- Refund Qty (Input with strict validation) -->
                                                <td class="px-4 py-3.5 text-center">
                                                    <div class="inline-flex items-center gap-1">
                                                        <input type="number"
                                                               min="1"
                                                               :max="it.buyQty"
                                                               :disabled="!it.checked"
                                                               x-model.number="it.refundQty"
                                                               class="w-16 px-2 py-1 border border-gray-300 rounded-lg text-center font-bold text-gray-900 focus:outline-none focus:border-[#0052cc] disabled:bg-gray-100 disabled:opacity-50">
                                                        <span class="text-[10px] text-gray-400" x-text="it.unit"></span>
                                                    </div>
                                                </td>

                                                <!-- Subtotal -->
                                                <td class="px-5 py-3.5 text-right font-mono font-bold"
                                                    :class="it.checked ? 'text-gray-900' : 'text-gray-300'"
                                                    x-text="formatRupiah(it.checked ? (it.refundQty * it.price) : 0)">
                                                </td>
                                            </tr>
                                        </template>

                                        <template x-if="!foundFaktur">
                                            <tr>
                                                <td colspan="5" class="py-12 text-center text-gray-400">
                                                    Silakan masukkan dan cari nomor faktur struk di sebelah kiri terlebih dahulu.
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Calculation & Supervisor Approval Card -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-xs p-6 flex flex-col gap-4">
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                                <div>
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">TOTAL NILAI PENGEMBALIAN DANA</span>
                                    <span class="text-[11px] text-gray-400">Uang tunai diserahkan kembali kepada pelanggan</span>
                                </div>
                                <span class="font-mono text-2xl font-black text-rose-600" x-text="formatRupiah(totalRefundCalculated)">Rp 0</span>
                            </div>

                            <!-- Supervisor PIN Input if > 50k -->
                            <template x-if="needsSupervisorAuth">
                                <div class="p-4 bg-amber-50/80 border border-amber-300 rounded-xl space-y-2">
                                    <div class="flex items-center gap-2 text-amber-900 text-xs font-bold">
                                        <span class="material-symbols-rounded text-[18px] text-amber-600">lock</span>
                                        <span>Otorisasi Supervisor Diperlukan (Nominal &gt; Rp 50.000):</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <input type="password"
                                               x-model="supervisorPin"
                                               maxlength="6"
                                               placeholder="Ketik PIN Supervisor (Demo: 123456)..."
                                               class="w-64 px-4 py-2 bg-white border border-amber-300 rounded-xl text-xs font-mono font-bold focus:outline-none focus:border-[#0052cc]">
                                        <span class="text-[11px] text-gray-500">PIN Supervisor Toko</span>
                                    </div>
                                </div>
                            </template>

                            <span x-show="pinError" class="text-xs font-bold text-rose-600" x-text="pinError"></span>

                            <!-- Strict Stock Policy Alert -->
                            <div class="p-3.5 bg-slate-50 border border-gray-200 rounded-xl text-xs text-gray-700 flex items-start gap-3">
                                <span class="material-symbols-rounded text-gray-500 text-[18px] shrink-0 mt-0.5">inventory</span>
                                <div class="space-y-0.5">
                                    <p class="font-bold">Integritas Stok Fisik:</p>
                                    <p class="text-gray-500 text-[11px] leading-relaxed">
                                        Barang refund dialokasikan ke area karantina untuk inspeksi supervisor, tidak menambah stok aktif otomatis.
                                    </p>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <button type="button"
                                        @click="processRefundSubmission()"
                                        :disabled="totalRefundCalculated === 0"
                                        :class="totalRefundCalculated === 0 ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-rose-600 hover:bg-rose-700 cursor-pointer shadow-sm'"
                                        class="px-6 py-3 rounded-xl text-white font-bold text-xs flex items-center gap-2 transition-all">
                                    <span class="material-symbols-rounded text-[18px]">payments</span>
                                    <span>Proses Pengembalian Dana (Refund)</span>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- VIEW 2: RIWAYAT REFUND PELANGGAN -->
                <div x-show="activeTab === 'history'" class="space-y-4">
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-bold text-gray-900">Riwayat Nota Refund Pelanggan</h2>
                            <span class="text-xs text-gray-500 font-medium" x-text="'Total ' + refundHistory.length + ' dokumen refund tercatat'"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold uppercase text-[10px] tracking-wider">
                                        <th class="px-6 py-3.5">NO. REFUND & WAKTU</th>
                                        <th class="px-4 py-3.5">FAKTUR ASLI</th>
                                        <th class="px-4 py-3.5">ALASAN KLAIM</th>
                                        <th class="px-4 py-3.5 text-right">NILAI REFUND</th>
                                        <th class="px-4 py-3.5 text-center">STATUS</th>
                                        <th class="px-6 py-3.5 text-right">BUKTI NOTA</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <template x-for="rf in refundHistory" :key="rf.refundNo">
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4">
                                                <span class="font-mono font-bold text-gray-900 text-xs block" x-text="rf.refundNo"></span>
                                                <span class="text-[11px] text-gray-400" x-text="rf.date"></span>
                                            </td>

                                            <td class="px-4 py-4">
                                                <span class="font-mono font-bold text-[#0052cc] text-xs" x-text="rf.originFaktur"></span>
                                                <span class="text-[10px] text-gray-400 block" x-text="'Kasir: ' + rf.cashier"></span>
                                            </td>

                                            <td class="px-4 py-4">
                                                <span class="font-bold text-gray-800 text-xs block" x-text="rf.reason"></span>
                                                <span class="text-[10px] text-gray-400" x-text="'Kondisi: ' + rf.condition"></span>
                                            </td>

                                            <td class="px-4 py-4 text-right">
                                                <span class="font-mono font-bold text-rose-600 text-sm" x-text="formatRupiah(rf.refundAmount)"></span>
                                            </td>

                                            <td class="px-4 py-4 text-center">
                                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    Selesai Refund
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 text-right">
                                                <button type="button"
                                                        @click="openHistoryDetail(rf)"
                                                        class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5 ml-auto cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">receipt</span>
                                                    <span>Nota Refund</span>
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

    <!-- MODAL BUKTI NOTA REFUND -->
    <template x-teleport="body">
        <div x-show="showReceiptModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">

            <div x-show="showReceiptModal"
                 @click.away="showReceiptModal = false"
                 x-transition.scale.origin.bottom
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 flex flex-col">

                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                            <span class="material-symbols-rounded text-[20px]">receipt</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm">Nota Pengembalian Dana Kasir</h3>
                            <span class="font-mono text-xs text-gray-400" x-text="selectedRefundDoc?.refundNo"></span>
                        </div>
                    </div>
                    <button @click="showReceiptModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-1.5 font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ref Faktur Asli:</span>
                            <span class="font-bold text-gray-900" x-text="selectedRefundDoc?.originFaktur"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Tanggal Klaim:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.date"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Kasir Petugas:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.cashier"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Alasan:</span>
                            <span class="text-gray-800" x-text="selectedRefundDoc?.reason"></span>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600 text-[11px]">
                                <tr>
                                    <th class="px-3 py-2">PRODUK</th>
                                    <th class="px-2 py-2 text-center">QTY</th>
                                    <th class="px-3 py-2 text-right">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedRefundDoc?.items" :key="idx">
                                    <tr>
                                        <td class="px-3 py-2 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-2 py-2 text-center text-gray-600" x-text="it.qty + ' ' + (it.unit || '')"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(it.qty * it.price)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-rose-50/60 border border-rose-200 rounded-xl flex justify-between items-baseline">
                        <span class="font-bold text-rose-900">DANA PENGEMBALIAN:</span>
                        <span class="font-mono font-black text-lg text-rose-600" x-text="formatRupiah(selectedRefundDoc?.refundAmount)"></span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Nota</span>
                    </button>
                    <button type="button"
                            @click="showReceiptModal = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Selesai
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
