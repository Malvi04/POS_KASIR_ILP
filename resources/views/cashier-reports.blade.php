@extends('layouts.app')

@section('title', 'Laporan Penjualan - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    periodFilter: 'today',
    selectedSessionFilter: 'all',
    selectedMethodFilter: 'all',
    selectedStatusFilter: 'all',
    searchQuery: '',
    showSummaryModal: false,

    transactions: [
        { id: '#TRX-20261024-0028', sessionId: 'CS-20261024-K01-003', terminal: 'Kasir 01', time: '11:42 WIB', cashier: 'Budi Kurniawan', method: 'CAMPURAN', cashPortion: 100000, nonCashPortion: 45000, subtotal: 132000, tax: 14500, total: 145000, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261024-0027', sessionId: 'CS-20261024-K01-003', terminal: 'Kasir 01', time: '11:20 WIB', cashier: 'Budi Kurniawan', method: 'TUNAI', cashPortion: 84000, nonCashPortion: 0, subtotal: 75675, tax: 8325, total: 84000, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261024-0026', sessionId: 'CS-20261024-K01-003', terminal: 'Kasir 01', time: '10:55 WIB', cashier: 'Budi Kurniawan', method: 'QRIS', cashPortion: 0, nonCashPortion: 245500, subtotal: 221171, tax: 24329, total: 245500, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261024-0025', sessionId: 'CS-20261024-K01-002', terminal: 'Kasir 01', time: '09:48 WIB', cashier: 'Andi Pratama', method: 'TUNAI', cashPortion: 120000, nonCashPortion: 0, subtotal: 108108, tax: 11892, total: 120000, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261024-0024', sessionId: 'CS-20261024-K01-002', terminal: 'Kasir 01', time: '09:15 WIB', cashier: 'Andi Pratama', method: 'KREDIT ANGGOTA', cashPortion: 0, nonCashPortion: 240000, subtotal: 216216, tax: 23784, total: 240000, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261024-0023', sessionId: 'CS-20261024-K01-002', terminal: 'Kasir 01', time: '08:40 WIB', cashier: 'Andi Pratama', method: 'QRIS', cashPortion: 0, nonCashPortion: 450000, subtotal: 405405, tax: 44595, total: 450000, status: 'DIBATALKAN', date: '2026-10-24' },
        { id: '#TRX-20261024-0022', sessionId: 'CS-20261024-K01-001', terminal: 'Kasir 01', time: '07:30 WIB', cashier: 'Budi Kurniawan', method: 'TUNAI', cashPortion: 310000, nonCashPortion: 0, subtotal: 279279, tax: 30721, total: 310000, status: 'SELESAI', date: '2026-10-24' },
        { id: '#TRX-20261023-0089', sessionId: 'CS-20261023-K01-002', terminal: 'Kasir 01', time: '16:20 WIB', cashier: 'Budi Kurniawan', method: 'TUNAI', cashPortion: 175000, nonCashPortion: 0, subtotal: 157657, tax: 17343, total: 175000, status: 'SELESAI', date: '2026-10-23' },
        { id: '#TRX-20261023-0088', sessionId: 'CS-20261023-K01-002', terminal: 'Kasir 01', time: '15:10 WIB', cashier: 'Budi Kurniawan', method: 'QRIS', cashPortion: 0, nonCashPortion: 92000, subtotal: 82882, tax: 9118, total: 92000, status: 'SELESAI', date: '2026-10-23' }
    ],

    get filteredTransactions() {
        return this.transactions.filter(t => {
            // Period filter
            if (this.periodFilter === 'today' && t.date !== '2026-10-24') return false;
            if (this.periodFilter === 'yesterday' && t.date !== '2026-10-23') return false;

            // Session filter
            if (this.selectedSessionFilter === 'active' && t.sessionId !== this.activeSession.sessionId && t.sessionId !== 'CS-20261024-K01-003') return false;
            if (this.selectedSessionFilter !== 'all' && this.selectedSessionFilter !== 'active' && t.sessionId !== this.selectedSessionFilter) return false;

            // Method filter
            if (this.selectedMethodFilter !== 'all' && t.method !== this.selectedMethodFilter) return false;

            // Status filter
            if (this.selectedStatusFilter !== 'all' && t.status !== this.selectedStatusFilter) return false;

            // Search query
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                if (!t.id.toLowerCase().includes(q) && !t.cashier.toLowerCase().includes(q) && !t.sessionId.toLowerCase().includes(q)) {
                    return false;
                }
            }

            return true;
        });
    },

    get totalFilteredOmzet() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI')
            .reduce((sum, t) => sum + t.total, 0);
    },

    get totalFilteredCash() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI')
            .reduce((sum, t) => sum + (t.cashPortion || 0), 0);
    },

    get totalFilteredNonCash() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI' && t.method !== 'TUNAI')
            .reduce((sum, t) => sum + (t.nonCashPortion || (t.method !== 'CAMPURAN' ? t.total : 0)), 0);
    },

    get totalFilteredCredit() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI' && t.method === 'KREDIT ANGGOTA')
            .reduce((sum, t) => sum + t.total, 0);
    }
}"
x-init="initOpening()"
@pos-session-sync.window="initOpening()"
class="flex flex-col h-screen w-full font-sans">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar -->
        <x-cashier-sidebar active="reports" />

        <!-- Main Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-[#fafafa]">
            <!-- Header -->
            <x-cashier-header title="Laporan Penjualan" />

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 md:p-8 bg-[#f8f9fb]">

                <!-- Page Top Action Bar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Laporan Penjualan Toko</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-blue-100 text-[#0052cc]">
                                Kasir Operasional
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Rekapitulasi penjualan toko, rincian pembayaran, dan pajak PPN 11%.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="showSummaryModal = true"
                                class="px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">receipt</span>
                            <span>Rekap Pembayaran</span>
                        </button>
                        <button type="button"
                                @click="window.print()"
                                class="px-4 py-2 bg-[#0052cc] hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-2 transition-colors shadow-2xs cursor-pointer">
                            <span class="material-symbols-rounded text-[18px]">print</span>
                            <span>Cetak Laporan</span>
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Total Omzet Penjualan</span>
                        <h2 class="text-2xl font-black text-[#1e40af] mt-1.5 font-mono" x-text="formatRupiah(totalFilteredOmzet)">Rp 0</h2>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs font-bold text-gray-500" x-text="filteredTransactions.filter(t => t.status === 'SELESAI').length + ' Transaksi Selesai'"></span>
                            <span class="text-[10px] text-gray-400 font-mono">PPN 11% Included</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-emerald-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-emerald-700 uppercase tracking-wider">Tunai Kas Laci</span>
                        <h2 class="text-2xl font-black text-emerald-700 mt-1.5 font-mono" x-text="formatRupiah(totalFilteredCash)">Rp 0</h2>
                        <span class="text-xs font-bold text-emerald-600 mt-1 block">Uang Fisik Diterima</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-blue-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-blue-700 uppercase tracking-wider">Non-Tunai (QRIS & EDC)</span>
                        <h2 class="text-2xl font-black text-[#0052cc] mt-1.5 font-mono" x-text="formatRupiah(totalFilteredNonCash)">Rp 0</h2>
                        <span class="text-xs font-bold text-blue-600 mt-1 block">Bank & Digital Payment</span>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-orange-200 shadow-xs">
                        <span class="text-[11px] font-extrabold text-orange-700 uppercase tracking-wider">Kredit Anggota Koperasi</span>
                        <h2 class="text-2xl font-black text-orange-700 mt-1.5 font-mono" x-text="formatRupiah(totalFilteredCredit)">Rp 0</h2>
                        <span class="text-xs font-bold text-orange-600 mt-1 block">Potong Saldo / Piutang Anggota</span>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="bg-white rounded-t-xl p-5 border border-gray-200 shadow-xs flex flex-col gap-4 border-b-0">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <!-- Quick Period Switcher -->
                        <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl">
                            <button type="button"
                                    @click="periodFilter = 'today'"
                                    :class="periodFilter === 'today' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer">
                                Hari Ini (24 Okt)
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'yesterday'"
                                    :class="periodFilter === 'yesterday' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer">
                                Kemarin (23 Okt)
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'all'"
                                    :class="periodFilter === 'all' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer">
                                Semua Periode
                            </button>
                        </div>

                        <!-- Search Box -->
                        <div class="relative w-full sm:w-64">
                            <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="Cari faktur, kasir..."
                                   class="w-full pl-9 pr-3.5 py-1.5 bg-slate-50 border border-gray-200 rounded-lg text-xs font-medium text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#0052cc] focus:bg-white transition-colors" />
                        </div>
                    </div>

                    <div class="flex items-end gap-4 flex-wrap pt-2 border-t border-gray-100">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Sesi Kasir</label>
                            <div class="relative">
                                <select x-model="selectedSessionFilter" class="appearance-none pl-3.5 pr-9 py-2 border border-gray-200 rounded-lg text-xs font-bold text-gray-800 min-w-[220px] outline-none bg-white cursor-pointer focus:border-[#0052cc]">
                                    <option value="all">Semua Sesi</option>
                                    <option value="active">Sesi Aktif Saat Ini</option>
                                    <option value="CS-20261024-K01-003">CS-20261024-K01-003 (Budi Kurniawan)</option>
                                    <option value="CS-20261024-K01-002">CS-20261024-K01-002 (Andi Pratama)</option>
                                    <option value="CS-20261024-K01-001">CS-20261024-K01-001 (Budi Kurniawan)</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[18px]">expand_more</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Metode Bayar</label>
                            <div class="relative">
                                <select x-model="selectedMethodFilter" class="appearance-none pl-3.5 pr-9 py-2 border border-gray-200 rounded-lg text-xs font-bold text-gray-800 w-[160px] outline-none bg-white cursor-pointer focus:border-[#0052cc]">
                                    <option value="all">Semua Metode</option>
                                    <option value="TUNAI">Tunai</option>
                                    <option value="QRIS">QRIS / Debit</option>
                                    <option value="KREDIT ANGGOTA">Kredit Anggota</option>
                                    <option value="CAMPURAN">Campuran</option>
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[18px]">expand_more</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Status Transaksi</label>
                            <div class="flex bg-[#f8fafc] p-1 rounded-lg border border-gray-200">
                                <button type="button" @click="selectedStatusFilter = 'all'" :class="selectedStatusFilter === 'all' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3 py-1 font-bold text-xs rounded cursor-pointer">Semua</button>
                                <button type="button" @click="selectedStatusFilter = 'SELESAI'" :class="selectedStatusFilter === 'SELESAI' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3 py-1 font-bold text-xs rounded cursor-pointer">Selesai</button>
                                <button type="button" @click="selectedStatusFilter = 'DIBATALKAN'" :class="selectedStatusFilter === 'DIBATALKAN' ? 'bg-white border border-blue-200 text-[#1d4ed8] shadow-2xs' : 'text-gray-500'" class="px-3 py-1 font-bold text-xs rounded cursor-pointer">Batal</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-b-xl border border-gray-200 shadow-xs overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#f8fafc] border-b border-gray-200 text-gray-500 text-[10px] font-black uppercase tracking-widest">
                                <th class="px-5 py-3.5">No. Transaksi</th>
                                <th class="px-5 py-3.5">ID Sesi</th>
                                <th class="px-5 py-3.5">Petugas Kasir</th>
                                <th class="px-5 py-3.5">Waktu</th>
                                <th class="px-5 py-3.5">Metode Bayar</th>
                                <th class="px-5 py-3.5 text-right">Subtotal</th>
                                <th class="px-5 py-3.5 text-right">PPN (11%)</th>
                                <th class="px-5 py-3.5 text-right">Total Transaksi</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            <template x-for="trx in filteredTransactions" :key="trx.id">
                                <tr class="hover:bg-blue-50/20 transition-colors">
                                    <td class="px-5 py-3.5 font-mono font-bold text-[#0052cc]" x-text="trx.id"></td>
                                    <td class="px-5 py-3.5 font-mono text-gray-600" x-text="trx.sessionId"></td>
                                    <td class="px-5 py-3.5 font-bold text-gray-900" x-text="trx.cashier"></td>
                                    <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap" x-text="trx.time"></td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                              :class="{
                                                  'bg-emerald-50 text-emerald-700 border border-emerald-200': trx.method === 'TUNAI',
                                                  'bg-blue-50 text-[#0052cc] border border-blue-200': trx.method === 'QRIS',
                                                  'bg-orange-50 text-orange-700 border border-orange-200': trx.method === 'KREDIT ANGGOTA',
                                                  'bg-purple-50 text-purple-700 border border-purple-200': trx.method === 'CAMPURAN'
                                              }"
                                              x-text="trx.method"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono text-gray-600" x-text="formatRupiah(trx.subtotal || Math.round(trx.total / 1.11))"></td>
                                    <td class="px-5 py-3.5 text-right font-mono text-gray-500" x-text="formatRupiah(trx.tax || Math.round(trx.total - (trx.total / 1.11)))"></td>
                                    <td class="px-5 py-3.5 text-right font-mono font-black text-gray-900" x-text="formatRupiah(trx.total)"></td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase"
                                              :class="trx.status === 'SELESAI' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'"
                                              x-text="trx.status"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a :href="'{{ url('/kasir/transaksi/riwayat') }}?cari=' + encodeURIComponent(trx.id)"
                                               title="Buka di Riwayat Transaksi"
                                               class="p-1 rounded-lg text-gray-500 hover:text-[#0052cc] hover:bg-blue-50 transition-colors">
                                                <span class="material-symbols-rounded text-[18px]">visibility</span>
                                            </a>
                                            <a href="{{ url('/cetak-struk') }}"
                                               title="Cetak Ulang Struk"
                                               class="p-1 rounded-lg text-[#0052cc] hover:text-blue-800 hover:bg-blue-50 transition-colors">
                                                <span class="material-symbols-rounded text-[18px]">print</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredTransactions.length === 0">
                                <td colspan="10" class="text-center py-12 text-gray-400">
                                    <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">receipt_long</span>
                                    <p class="font-bold text-gray-600">Tidak ada transaksi yang cocok dengan filter</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Silakan sesuaikan filter sesi, metode bayar, atau periode.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </main>
        </div>
    </div>

    <!-- MODAL REKAP PEMBAYARAN -->
    <template x-teleport="body">
        <div x-show="showSummaryModal"
             x-transition.opacity
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div @click.away="showSummaryModal = false"
                 class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden animate-scale-up">

                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-rounded text-[#0052cc] text-[22px]">summarize</span>
                        <h3 class="font-bold text-gray-900 text-sm">Rekapitulasi Pembayaran Penjualan</h3>
                    </div>
                    <button @click="showSummaryModal = false" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <span class="material-symbols-rounded text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100 text-blue-900">
                        <div class="flex justify-between font-bold">
                            <span>Total Omzet Bersih:</span>
                            <span class="font-mono text-sm" x-text="formatRupiah(totalFilteredOmzet)"></span>
                        </div>
                        <div class="text-[11px] text-blue-700 mt-1">
                            Berdasarkan <span class="font-bold" x-text="filteredTransactions.filter(t => t.status === 'SELESAI').length"></span> transaksi selesai pada filter terpilih.
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="font-bold text-gray-700">Tunai Laci Kasir</span>
                            </div>
                            <span class="font-mono font-bold text-emerald-700" x-text="formatRupiah(totalFilteredCash)"></span>
                        </div>

                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                                <span class="font-bold text-gray-700">QRIS & Kartu Debit</span>
                            </div>
                            <span class="font-mono font-bold text-blue-700" x-text="formatRupiah(totalFilteredNonCash)"></span>
                        </div>

                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-orange-500"></span>
                                <span class="font-bold text-gray-700">Kredit Anggota (Piutang)</span>
                            </div>
                            <span class="font-mono font-bold text-orange-700" x-text="formatRupiah(totalFilteredCredit)"></span>
                        </div>
                    </div>

                    <div class="text-[11px] text-gray-500 bg-amber-50 border border-amber-200 p-3 rounded-xl flex gap-2">
                        <span class="material-symbols-rounded text-amber-600 text-[18px] shrink-0">info</span>
                        <span>Seluruh perhitungan transaksi telah memisahkan komponen DPP dan PPN 11% sesuai regulasi perpajakan yang berlaku di Koperasi ILP.</span>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-2 bg-slate-50">
                    <button type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-[#0052cc] rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer">
                        <span class="material-symbols-rounded text-[16px]">print</span>
                        <span>Cetak Rekap</span>
                    </button>
                    <button type="button"
                            @click="showSummaryModal = false"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl font-bold text-xs transition-colors cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    </template>
</div>
@endsection
