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

    transactions: [],

    async initTransactions() {
        const localRaw = localStorage.getItem('pos_ilp_transactions_v1');
        let localList = [];
        try { localList = localRaw ? JSON.parse(localRaw) : []; } catch (e) {}

        try {
            const params = new URLSearchParams({
                search: this.searchQuery.trim(),
                payment_method: this.methodFilter,
                status: this.statusFilter,
                date: this.dateFilter,
                per_page: 50
            });
            const res = await fetch(`/api/pos/transactions?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    // Retain any pending unsynced offline transactions at the top of the list
                    const pendingTrxs = localList.filter(t => t.syncStatus === 'PENDING_SYNC' || t.isOffline);
                    const serverIds = new Set(json.data.map(d => d.invoice_number || d.id));
                    const unsyncedOnly = pendingTrxs.filter(p => !serverIds.has(p.invoice_number || p.id));

                    this.transactions = [...unsyncedOnly, ...json.data];
                    localStorage.setItem('pos_ilp_transactions_v1', JSON.stringify(this.transactions));
                    return;
                }
            }
        } catch (e) {
            console.warn('API transactions fetch error:', e);
        }

        this.transactions = localList;
    },

    get filteredTransactions() {
        return this.transactions.filter(t => {
            if (this.methodFilter !== 'all' && t.method !== this.methodFilter) return false;
            if (this.statusFilter !== 'all' && t.status !== this.statusFilter) return false;
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase();
                const matchId = (t.id || '').toLowerCase().includes(q);
                const matchCashier = (t.cashier || '').toLowerCase().includes(q);
                const matchItems = Array.isArray(t.items) && t.items.some(it => (it.name || '').toLowerCase().includes(q));
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
        if (!t) return;
        localStorage.setItem('pos_ilp_latest_printed_receipt_v1', JSON.stringify(t));
        window.location.href = '/cetak-struk?faktur=' + encodeURIComponent(t.id);
    },

    goToRefund(t) {
        window.location.href = '/kasir/transaksi/refund?faktur=' + encodeURIComponent(t.id);
    }
}"
x-init="initOpening(); initTransactions();"
@pos-session-sync.window="initOpening(); initTransactions();"
@pos:transactions_updated.window="initTransactions()"
@pos:sync-all.window="initOpening(); initTransactions();"
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
                        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Riwayat Transaksi</h1>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Manual Sync Button -->
                        <button type="button"
                                @click="$store.offlineSync?.openModal()"
                                class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-2 cursor-pointer border"
                                :class="($store.offlineSync && $store.offlineSync.totalPending > 0)
                                    ? 'bg-amber-500 hover:bg-amber-600 text-white border-amber-600 animate-pulse'
                                    : 'bg-white hover:bg-gray-50 text-gray-700 border-gray-300'">
                            <span class="material-symbols-rounded text-[18px]"
                                  :class="($store.offlineSync && $store.offlineSync.isSyncing) ? 'animate-spin' : ''">sync</span>
                            <span x-text="($store.offlineSync && $store.offlineSync.totalPending > 0) ? ('Sinkronkan (' + $store.offlineSync.totalPending + ' Offline)') : 'Sync Manager'">Sync Manager</span>
                        </button>

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
                               @input.debounce.300ms="initTransactions()"
                               placeholder="Cari nomor faktur (#TRX-...), nama kasir, atau nama item..."
                               class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/20 text-[13px] shadow-xs font-medium bg-slate-50/50">
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Method Filter -->
                        <div class="relative">
                            <select x-model="methodFilter"
                                    @change="initTransactions()"
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
                                    @change="initTransactions()"
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

                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1100px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 font-bold uppercase text-[11px] tracking-wider whitespace-nowrap">
                                    <th class="px-4 py-3.5 text-left">No. Faktur</th>
                                    <th class="px-4 py-3.5 text-left">Tanggal</th>
                                    <th class="px-4 py-3.5 text-left">Waktu</th>
                                    <th class="px-4 py-3.5 text-left">Kasir</th>
                                    <th class="px-4 py-3.5 text-left">Terminal</th>
                                    <th class="px-4 py-3.5 text-left">Nama Produk</th>
                                    <th class="px-4 py-3.5 text-center">Qty</th>
                                    <th class="px-4 py-3.5 text-right">Harga Satuan</th>
                                    <th class="px-4 py-3.5 text-right">Subtotal</th>
                                    <th class="px-4 py-3.5 text-left">Metode Bayar</th>
                                    <th class="px-4 py-3.5 text-right">Total Belanja</th>
                                    <th class="px-4 py-3.5 text-center">Status Sync</th>
                                    <th class="px-4 py-3.5 text-center">Aksi Nota</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                <template x-for="t in filteredTransactions" :key="t.id">
                                    <template x-for="(it, iIdx) in ((t.items && t.items.length > 0) ? t.items : [{ name: '-', qty: 0, price: 0 }])" :key="iIdx">
                                        <tr class="hover:bg-blue-50/30 transition-colors" :class="iIdx === 0 ? 'border-t border-gray-200' : 'border-t border-gray-100'">
                                            <!-- Parent Col 1: No Faktur -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white font-mono font-bold text-gray-900 text-[13px]">
                                                    <span x-text="t.id"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 2: Tanggal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-medium" x-text="t.date">
                                                </td>
                                            </template>

                                            <!-- Parent Col 3: Waktu -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-mono" x-text="t.time">
                                                </td>
                                            </template>

                                            <!-- Parent Col 4: Kasir -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs font-bold text-gray-800" x-text="t.cashier">
                                                </td>
                                            </template>

                                            <!-- Parent Col 5: Terminal -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white text-xs text-gray-500 font-mono" x-text="t.terminal">
                                                </td>
                                            </template>

                                            <!-- Child Col 6: Nama Produk -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap font-bold text-gray-900 text-xs max-w-[200px] truncate" :title="it.name" x-text="it.name"></td>

                                            <!-- Child Col 7: Qty -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-center font-mono font-bold text-xs" x-text="it.qty"></td>

                                            <!-- Child Col 8: Harga Satuan -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-right font-mono text-xs text-gray-600" x-text="formatRupiah(it.price)"></td>

                                            <!-- Child Col 9: Subtotal -->
                                            <td class="px-4 py-3 align-middle whitespace-nowrap text-right font-mono font-bold text-xs text-gray-900" x-text="formatRupiah(it.subtotal || (it.qty * it.price))"></td>

                                            <!-- Parent Col 10: Metode Pembayaran -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap bg-white">
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap"
                                                          :class="t.method === 'TUNAI' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (t.method === 'QRIS' ? 'bg-blue-50 text-[#0052cc] border border-blue-200' : (t.method === 'KREDIT ANGGOTA' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-amber-50 text-amber-700 border border-amber-200'))"
                                                          x-text="t.methodLabel"></span>
                                                </td>
                                            </template>

                                            <!-- Parent Col 11: Total Belanja -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap text-right bg-white font-mono font-black text-gray-900 text-sm" x-text="formatRupiah(t.total)">
                                                </td>
                                            </template>

                                            <!-- Parent Col 12: Status Sync -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap text-center bg-white">
                                                    <template x-if="t.syncStatus === 'PENDING_SYNC' || t.isOffline">
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-300 px-2.5 py-0.5 rounded-full whitespace-nowrap animate-pulse"
                                                              title="Transaksi offline tersimpan lokal di browser">
                                                            <span class="material-symbols-rounded text-[13px] text-amber-600">cloud_off</span>
                                                            <span>Offline (Pending)</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="t.status === 'SELESAI' && !(t.syncStatus === 'PENDING_SYNC' || t.isOffline)">
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[13px]">cloud_done</span>
                                                            <span>Tersinkron</span>
                                                        </span>
                                                    </template>
                                                    <template x-if="t.status === 'DIBATALKAN'">
                                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-0.5 rounded-full whitespace-nowrap">
                                                            <span class="material-symbols-rounded text-[13px]">cancel</span>
                                                            <span>Dibatalkan</span>
                                                        </span>
                                                    </template>
                                                </td>
                                            </template>

                                            <!-- Parent Col 13: Aksi Nota -->
                                            <template x-if="iIdx === 0">
                                                <td :rowspan="t.items && t.items.length > 0 ? t.items.length : 1" class="px-4 py-3 align-middle whitespace-nowrap text-center bg-white">
                                                    <div class="inline-flex items-center justify-center gap-1.5">
                                                        <button type="button"
                                                                @click="openDetail(t)"
                                                                class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 cursor-pointer">
                                                            <span class="material-symbols-rounded text-[16px]">visibility</span>
                                                            <span>Detail</span>
                                                        </button>
                                                        <button type="button"
                                                                @click="printStruk(t)"
                                                                class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 cursor-pointer">
                                                            <span class="material-symbols-rounded text-[16px]">print</span>
                                                            <span>Struk</span>
                                                        </button>
                                                        <button type="button"
                                                                @click="goToRefund(t)"
                                                                title="Ajukan Refund untuk Faktur ini"
                                                                class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition-colors inline-flex items-center gap-1 cursor-pointer">
                                                            <span class="material-symbols-rounded text-[16px]">currency_exchange</span>
                                                            <span>Refund</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                                <tr x-show="filteredTransactions.length === 0">
                                    <td colspan="13" class="py-10 text-center text-slate-400">
                                        <span class="material-symbols-rounded text-3xl block text-slate-300 mb-1">receipt</span>
                                        <span class="font-bold text-xs text-slate-600">Belum ada transaksi.</span>
                                    </td>
                                </tr>
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
                    <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse text-xs min-w-[450px]">
                                <thead class="bg-gray-50/80 border-b border-gray-200 font-bold text-gray-600 uppercase tracking-wider text-[11px] whitespace-nowrap">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left w-[200px]">PRODUK</th>
                                        <th class="px-3 py-2.5 text-center w-[80px]">QTY</th>
                                        <th class="px-4 py-2.5 text-right w-[110px]">HARGA</th>
                                        <th class="px-4 py-2.5 text-right w-[110px]">SUBTOTAL</th>
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
