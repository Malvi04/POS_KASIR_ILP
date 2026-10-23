@extends('layouts.app')

@section('title', 'Riwayat Transaksi - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    searchQuery: '',
    methodFilter: 'all',
    statusFilter: 'all',
    dateFilter: 'today',
    showDetailModal: false,
    selectedTrx: null,
    toastMessage: '',
    showToast: false,

    transactions: [
        {
            id: '#TRX-20261024-0028',
            rawId: 'TRX-0028',
            time: '11:42 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'CAMPURAN',
            methodLabel: 'Tunai + QRIS',
            cashPortion: 100000,
            nonCashPortion: 45000,
            subtotal: 132000,
            discount: 1500,
            tax: 14500,
            total: 145000,
            cashGiven: 100000,
            change: 0,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Beras Pandan Wangi 5kg', qty: 1, unit: 'Pack', price: 82000, total: 82000 },
                { name: 'Minyak Goreng Sania 2L', qty: 1, unit: 'Pouch', price: 36000, total: 36000 },
                { name: 'Ultra Milk Full Cream 1L', qty: 1, unit: 'Tetra', price: 19200, total: 19200 },
                { name: 'Teh Pucuk Harum 350ml', qty: 2, unit: 'Botol', price: 4000, total: 8000 }
            ]
        },
        {
            id: '#TRX-20261024-0027',
            rawId: 'TRX-0027',
            time: '11:20 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'TUNAI',
            methodLabel: 'Uang Tunai',
            cashPortion: 84000,
            nonCashPortion: 0,
            subtotal: 77000,
            discount: 1300,
            tax: 8300,
            total: 84000,
            cashGiven: 100000,
            change: 16000,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Chitato Sapi Panggang 68g', qty: 2, unit: 'Pcs', price: 12800, total: 25600 },
                { name: 'Sunlight Jeruk Nipis 755ml', qty: 1, unit: 'Botol', price: 15500, total: 15500 },
                { name: 'Indomie Goreng Spesial 85g', qty: 10, unit: 'Pcs', price: 3500, total: 35000 },
                { name: 'Aqua Mineral 600ml', qty: 2, unit: 'Botol', price: 5500, total: 11000 }
            ]
        },
        {
            id: '#TRX-20261024-0026',
            rawId: 'TRX-0026',
            time: '10:35 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'QRIS',
            methodLabel: 'QRIS Dinamis',
            cashPortion: 0,
            nonCashPortion: 245500,
            subtotal: 225000,
            discount: 4000,
            tax: 24500,
            total: 245500,
            cashGiven: 0,
            change: 0,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Beras Pandan Wangi 5kg', qty: 2, unit: 'Pack', price: 82000, total: 164000 },
                { name: 'Minyak Goreng Sania 2L', qty: 2, unit: 'Pouch', price: 36000, total: 72000 },
                { name: 'Oreo Vanilla 133g', qty: 2, unit: 'Pcs', price: 9500, total: 19000 }
            ]
        },
        {
            id: '#TRX-20261024-0025',
            rawId: 'TRX-0025',
            time: '09:48 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'KREDIT ANGGOTA',
            methodLabel: 'Tempo Belanja Anggota',
            cashPortion: 0,
            nonCashPortion: 120000,
            subtotal: 110000,
            discount: 2000,
            tax: 12000,
            total: 120000,
            cashGiven: 0,
            change: 0,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Gula Pasir Gulaku 1kg', qty: 4, unit: 'Pack', price: 18000, total: 72000 },
                { name: 'Ultra Milk Full Cream 1L', qty: 2, unit: 'Tetra', price: 19200, total: 38400 },
                { name: 'Aqua Mineral 600ml', qty: 2, unit: 'Botol', price: 5500, total: 11000 }
            ]
        },
        {
            id: '#TRX-20261024-0024',
            rawId: 'TRX-0024',
            time: '09:15 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'TUNAI',
            methodLabel: 'Uang Tunai',
            cashPortion: 28638,
            nonCashPortion: 0,
            subtotal: 27100,
            discount: 1300,
            tax: 2838,
            total: 28638,
            cashGiven: 50000,
            change: 21362,
            status: 'SELESAI',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Aqua Mineral 600ml', qty: 3, unit: 'Botol', price: 4300, total: 12900 },
                { name: 'Oreo Vanilla 133g', qty: 1, unit: 'Pcs', price: 8200, total: 8200 },
                { name: 'Teh Pucuk 350ml', qty: 2, unit: 'Botol', price: 4000, total: 8000 }
            ]
        },
        {
            id: '#TRX-20261024-0023',
            rawId: 'TRX-0023',
            time: '08:40 WIB',
            date: '24 Oktober 2026',
            terminal: 'Kasir 01',
            cashier: 'Budi Kurniawan',
            method: 'QRIS',
            methodLabel: 'QRIS Dinamis',
            cashPortion: 0,
            nonCashPortion: 450000,
            subtotal: 410000,
            discount: 5000,
            tax: 45000,
            total: 450000,
            cashGiven: 0,
            change: 0,
            status: 'DIBATALKAN',
            syncStatus: 'SYNCED',
            items: [
                { name: 'Beras Pandan Wangi 5kg', qty: 5, unit: 'Pack', price: 82000, total: 410000 }
            ]
        }
    ],

    get filteredTransactions() {
        return this.transactions.filter(t => {
            if (this.methodFilter !== 'all' && t.method !== this.methodFilter) return false;
            if (this.statusFilter !== 'all' && t.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchId = t.id.toLowerCase().includes(q);
                const matchCashier = t.cashier.toLowerCase().includes(q);
                const matchItems = t.items.some(it => it.name.toLowerCase().includes(q));
                return matchId || matchCashier || matchItems;
            }
            return true;
        });
    },

    get totalOmzet() {
        return this.transactions.filter(t => t.status === 'SELESAI').reduce((a, b) => a + Number(b.total), 0);
    },

    get totalCash() {
        return this.transactions.filter(t => t.status === 'SELESAI').reduce((a, b) => a + Number(b.cashPortion), 0);
    },

    get totalNonCash() {
        return this.transactions.filter(t => t.status === 'SELESAI').reduce((a, b) => a + Number(b.nonCashPortion), 0);
    },

    openDetail(t) {
        this.selectedTrx = t;
        this.showDetailModal = true;
    },

    printStruk(t) {
        window.location.href = '/cetak-struk';
    },

    goToRefund(t) {
        window.location.href = '/kasir/transaksi/refund?faktur=' + encodeURIComponent(t.id);
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">

    <!-- Sidebar Kasir -->
    <x-cashier-sidebar active="transaksi_riwayat" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header -->
        <x-cashier-header title="Riwayat Transaksi">
            <div class="flex items-center gap-2 ml-2">
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-[#0052cc] border border-blue-200 flex items-center gap-1.5">
                    <span class="material-symbols-rounded text-[14px]">receipt_long</span>
                    <span>Audit Nota Sesi Berjalan</span>
                </span>
            </div>
        </x-cashier-header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            <div class="max-w-[1400px] mx-auto space-y-6">

                <!-- Breadcrumbs & Title -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold text-gray-400 tracking-wider mb-1 uppercase">
                            <span>OPERASIONAL KASIR</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span>TRANSAKSI</span>
                            <span class="material-symbols-rounded text-[14px]">chevron_right</span>
                            <span class="text-[#0052cc]">RIWAYAT & DOKUMEN FAKTUR</span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Riwayat Transaksi Penjualan Kasir</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Daftar faktur transaksi kasir, detail rincian belanja, dan cetak ulang struk.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ url('/pos') }}" class="px-4 py-2.5 bg-[#0052cc] hover:bg-[#0043a8] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-2">
                            <span class="material-symbols-rounded text-[18px]">point_of_sale</span>
                            <span>Buka Terminal Kasir (F4)</span>
                        </a>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">TOTAL TRANSAKSI</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-2xl font-black text-gray-900" x-text="transactions.length + ' Nota'">6 Nota</h3>
                                <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-md">Shift Ini</span>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Sesi: Kasir 01</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">receipt_long</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-emerald-200 p-5 shadow-xs flex items-center justify-between border-l-4 border-l-emerald-500">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-700 tracking-wider uppercase">OMZET SELESAI</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1" x-text="formatRupiah(totalOmzet)">Rp 623.138</h3>
                            <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Penjualan lunas tercatat</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">payments</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">PENERIMAAN TUNAI</span>
                            <h3 class="text-xl font-black text-gray-900 font-mono mt-1" x-text="formatRupiah(totalCash)">Rp 212.638</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">Uang fisik laci kasir</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-slate-100 text-gray-700 flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">point_of_sale</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">NON-TUNAI & TEMPO</span>
                            <h3 class="text-xl font-black text-[#0052cc] font-mono mt-1" x-text="formatRupiah(totalNonCash)">Rp 410.500</h3>
                            <span class="text-[11px] text-gray-500 font-medium mt-0.5 block">QRIS, Debit, Piutang Member</span>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0052cc] flex items-center justify-center shrink-0">
                            <span class="material-symbols-rounded text-[24px]">qr_code_2</span>
                        </div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="relative flex-1 w-full max-w-xl">
                        <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Cari nomor faktur (#TRX-...), nama kasir, atau nama item..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Method Filter -->
                        <div class="relative">
                            <select x-model="methodFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Metode Pembayaran</option>
                                <option value="TUNAI">Tunai</option>
                                <option value="QRIS">QRIS</option>
                                <option value="CAMPURAN">Campuran (Tunai + Non-Tunai)</option>
                                <option value="KREDIT ANGGOTA">Kredit Anggota (Tempo)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>

                        <!-- Status Filter -->
                        <div class="relative">
                            <select x-model="statusFilter"
                                    class="appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-[13px] text-gray-700 focus:outline-none focus:border-[#0052cc] shadow-xs font-semibold cursor-pointer">
                                <option value="all">Semua Status</option>
                                <option value="SELESAI">Selesai (Lunas)</option>
                                <option value="DIBATALKAN">Dibatalkan (Void)</option>
                            </select>
                            <span class="material-symbols-rounded absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-[20px]">expand_more</span>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-rounded text-[20px] text-[#0052cc]">receipt</span>
                            <h2 class="text-sm font-bold text-gray-900">Daftar Transaksi Selesai & Faktur</h2>
                        </div>
                        <span class="text-xs text-gray-500 font-medium" x-text="'Menampilkan ' + filteredTransactions.length + ' transaksi'"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-gray-200 text-gray-500 font-bold uppercase text-[10px] tracking-wider">
                                    <th class="px-6 py-3.5">FAKTUR / WAKTU</th>
                                    <th class="px-4 py-3.5">KASIR & TERMINAL</th>
                                    <th class="px-4 py-3.5">METODE PEMBAYARAN</th>
                                    <th class="px-4 py-3.5 text-right">TOTAL BELANJA</th>
                                    <th class="px-4 py-3.5 text-center">STATUS SYNC</th>
                                    <th class="px-6 py-3.5 text-right">AKSI NOTA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <template x-for="t in filteredTransactions" :key="t.id">
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <!-- No Faktur & Waktu -->
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-mono font-bold text-gray-900 text-[13px]" x-text="t.id"></span>
                                                <span class="text-[11px] text-gray-500 mt-0.5" x-text="t.time + ' • ' + t.date"></span>
                                            </div>
                                        </td>

                                        <!-- Kasir & Terminal -->
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-800 text-xs" x-text="t.cashier"></span>
                                                <span class="text-[11px] text-gray-400" x-text="t.terminal"></span>
                                            </div>
                                        </td>

                                        <!-- Metode Pembayaran -->
                                        <td class="px-4 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                                  :class="t.method === 'TUNAI' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (t.method === 'QRIS' ? 'bg-blue-50 text-[#0052cc] border border-blue-200' : (t.method === 'KREDIT ANGGOTA' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-amber-50 text-amber-700 border border-amber-200'))"
                                                  x-text="t.methodLabel"></span>
                                        </td>

                                        <!-- Total -->
                                        <td class="px-4 py-4 text-right">
                                            <span class="font-mono font-bold text-gray-900 text-sm" x-text="formatRupiah(t.total)"></span>
                                            <span class="text-[10px] text-gray-400 block" x-text="t.items.length + ' Macam Produk'"></span>
                                        </td>

                                        <!-- Status Sync -->
                                        <td class="px-4 py-4 text-center">
                                            <template x-if="t.status === 'SELESAI'">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                                    <span class="material-symbols-rounded text-[13px]">cloud_done</span>
                                                    <span>Tersinkron</span>
                                                </span>
                                            </template>
                                            <template x-if="t.status === 'DIBATALKAN'">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-0.5 rounded-full">
                                                    <span class="material-symbols-rounded text-[13px]">cancel</span>
                                                    <span>Dibatalkan</span>
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button type="button"
                                                        @click="openDetail(t)"
                                                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-lg text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">visibility</span>
                                                    <span>Detail</span>
                                                </button>
                                                <button type="button"
                                                        @click="printStruk(t)"
                                                        class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-lg text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">print</span>
                                                    <span>Struk</span>
                                                </button>
                                                <button type="button"
                                                        @click="goToRefund(t)"
                                                        title="Ajukan Refund untuk Faktur ini"
                                                        class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition-colors flex items-center gap-1 cursor-pointer">
                                                    <span class="material-symbols-rounded text-[16px]">currency_exchange</span>
                                                    <span>Refund</span>
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

    <!-- MODAL DETAIL TRANSAKSI FAKTUR -->
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
                            <span class="material-symbols-rounded text-[22px]">receipt_long</span>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-gray-900">Rincian Faktur Belanja</h3>
                            <span class="font-mono text-xs text-gray-500" x-text="selectedTrx?.id + ' • ' + selectedTrx?.time"></span>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[24px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[70vh]">
                    <!-- Metadata Grid -->
                    <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-gray-200">
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">KASIR & TERMINAL</span>
                            <span class="font-bold text-gray-800" x-text="selectedTrx?.cashier + ' (' + selectedTrx?.terminal + ')'"></span>
                        </div>
                        <div>
                            <span class="text-gray-400 font-bold uppercase text-[10px] block">METODE PEMBAYARAN</span>
                            <span class="font-bold text-[#0052cc]" x-text="selectedTrx?.methodLabel"></span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-100 font-bold text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5">PRODUK</th>
                                    <th class="px-3 py-2.5 text-center">QTY</th>
                                    <th class="px-4 py-2.5 text-right">HARGA</th>
                                    <th class="px-4 py-2.5 text-right">SUBTOTAL</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(it, idx) in selectedTrx?.items" :key="idx">
                                    <tr>
                                        <td class="px-4 py-2.5 font-bold text-gray-800" x-text="it.name"></td>
                                        <td class="px-3 py-2.5 text-center text-gray-600" x-text="it.qty + ' ' + it.unit"></td>
                                        <td class="px-4 py-2.5 text-right font-mono text-gray-600" x-text="formatRupiah(it.price)"></td>
                                        <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-900" x-text="formatRupiah(it.total)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Summary -->
                    <div class="p-4 bg-slate-50 rounded-xl border border-gray-200 space-y-2">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal Belanja</span>
                            <span class="font-mono" x-text="formatRupiah(selectedTrx?.subtotal)"></span>
                        </div>
                        <div class="flex justify-between text-rose-600 font-semibold">
                            <span>Diskon Promosi / Kupon</span>
                            <span class="font-mono" x-text="'-' + formatRupiah(selectedTrx?.discount)"></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>PPN 11% (Perhitungan Existing)</span>
                            <span class="font-mono" x-text="formatRupiah(selectedTrx?.tax)"></span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex justify-between font-bold text-sm text-gray-900">
                            <span>Total Pembayaran</span>
                            <span class="font-mono text-base text-[#0052cc]" x-text="formatRupiah(selectedTrx?.total)"></span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between bg-slate-50">
                    <button type="button"
                            @click="goToRefund(selectedTrx)"
                            class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">currency_exchange</span>
                        <span>Ajukan Refund</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="printStruk(selectedTrx)"
                                class="px-4 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-[#0052cc] font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                            <span class="material-symbols-rounded text-[16px]">print</span>
                            <span>Cetak Ulang Struk</span>
                        </button>
                        <button type="button"
                                @click="showDetailModal = false"
                                class="px-4 py-2 rounded-xl bg-gray-800 hover:bg-gray-900 text-white font-bold text-xs transition-colors cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

</div>
@endsection
