@extends('layouts.app')

@section('title', 'Laporan Penjualan - ILP POS')

@section('content')
<div x-data="{
    ...cashierOpeningData(),
    periodFilter: 'all',
    selectedSessionFilter: 'all',
    selectedMethodFilter: 'all',
    selectedStatusFilter: 'all',
    searchQuery: '',
    showSummaryModal: false,

    transactions: [],

    async initReports() {
        try {
            const res = await fetch('/api/pos/transactions?per_page=100&date=all', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    this.transactions = json.data.map(t => ({
                        id: t.id,
                        sessionId: t.terminal || '-',
                        terminal: t.terminal || '-',
                        time: t.time || '-',
                        cashier: t.cashier || '-',
                        method: t.method || 'TUNAI',
                        cashPortion: Number(t.cashPortion || 0),
                        nonCashPortion: Number(t.nonCashPortion || 0),
                        subtotal: Number(t.subtotal || 0),
                        tax: Number(t.tax || 0),
                        total: Number(t.total || 0),
                        status: t.status || 'SELESAI',
                        statusCode: t.statusCode || t.status || 'SUCCESS',
                        date: t.date_iso || t.rawDate || (t.date && t.date.includes('-') ? t.date : ''),
                        displayDate: t.date || '-'
                    }));
                    return;
                }
            }
        } catch (e) {
            console.warn('API report fetch fallback:', e);
        }

        this.transactions = [];
    },

    get filteredTransactions() {
        const now = new Date();
        const todayStr = now.toISOString().split('T')[0];
        const startOfWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay()).toISOString().split('T')[0];
        const monthPrefix = now.toISOString().substring(0, 7);
        const yearPrefix = now.getFullYear().toString();

        return this.transactions.filter(t => {
            // Period filter (Harian, Mingguan, Bulanan, Tahunan)
            if (this.periodFilter === 'today' && t.date && t.date !== todayStr) return false;
            if (this.periodFilter === 'week' && t.date && t.date < startOfWeek) return false;
            if (this.periodFilter === 'month' && t.date && !t.date.startsWith(monthPrefix)) return false;
            if (this.periodFilter === 'year' && t.date && !t.date.startsWith(yearPrefix)) return false;

            // Session filter
            if (this.selectedSessionFilter === 'active' && this.activeSession && t.sessionId !== this.activeSession.sessionId) return false;
            if (this.selectedSessionFilter !== 'all' && this.selectedSessionFilter !== 'active' && t.sessionId !== this.selectedSessionFilter) return false;

            // Method filter
            if (this.selectedMethodFilter !== 'all') {
                const meth = (t.method || '').toUpperCase();
                if (this.selectedMethodFilter === 'QRIS' && !meth.includes('QRIS')) return false;
                else if (this.selectedMethodFilter === 'TRANSFER' && !meth.includes('TRANSFER')) return false;
                else if (this.selectedMethodFilter === 'TUNAI' && meth !== 'TUNAI') return false;
                else if (this.selectedMethodFilter === 'KREDIT' && !meth.includes('KREDIT') && !meth.includes('PIUTANG')) return false;
                else if (this.selectedMethodFilter === 'CAMPURAN' && !meth.includes('CAMPURAN')) return false;
            }

            // Status filter
            if (this.selectedStatusFilter !== 'all') {
                const isSuccess = t.status === 'SELESAI' || t.status === 'SUCCESS' || t.statusCode === 'SUCCESS';
                const isVoided = t.status === 'DIBATALKAN' || t.status === 'VOIDED' || t.statusCode === 'VOIDED';
                if (this.selectedStatusFilter === 'SELESAI' && !isSuccess) return false;
                if (this.selectedStatusFilter === 'DIBATALKAN' && !isVoided) return false;
            }

            // Search query
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                if (!t.id.toLowerCase().includes(q) && !(t.cashier || '').toLowerCase().includes(q) && !(t.sessionId || '').toLowerCase().includes(q)) {
                    return false;
                }
            }

            return true;
        });
    },

    get totalFilteredOmzet() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI' || t.status === 'SUCCESS' || t.statusCode === 'SUCCESS')
            .reduce((sum, t) => sum + t.total, 0);
    },

    get totalFilteredCash() {
        return this.filteredTransactions
            .filter(t => t.status === 'SELESAI' || t.status === 'SUCCESS' || t.statusCode === 'SUCCESS')
            .reduce((sum, t) => sum + (t.cashPortion || 0), 0);
    },

    get totalFilteredNonCash() {
        return this.filteredTransactions
            .filter(t => (t.status === 'SELESAI' || t.status === 'SUCCESS' || t.statusCode === 'SUCCESS') && t.method !== 'TUNAI')
            .reduce((sum, t) => sum + (t.nonCashPortion || (t.method !== 'CAMPURAN' ? t.total : 0)), 0);
    },

    get totalFilteredCredit() {
        return this.filteredTransactions
            .filter(t => (t.status === 'SELESAI' || t.status === 'SUCCESS' || t.statusCode === 'SUCCESS') && t.method === 'KREDIT ANGGOTA')
            .reduce((sum, t) => sum + t.total, 0);
    }
}"
x-init="initOpening(); initReports();"
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
                        <!-- Quick Period Switcher (Harian, Mingguan, Bulanan, Tahunan) -->
                        <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-xl overflow-x-auto">
                            <button type="button"
                                    @click="periodFilter = 'today'"
                                    :class="periodFilter === 'today' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer whitespace-nowrap">
                                Hari Ini
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'week'"
                                    :class="periodFilter === 'week' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer whitespace-nowrap">
                                Minggu Ini
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'month'"
                                    :class="periodFilter === 'month' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer whitespace-nowrap">
                                Bulan Ini
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'year'"
                                    :class="periodFilter === 'year' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer whitespace-nowrap">
                                Tahun Ini
                            </button>
                            <button type="button"
                                    @click="periodFilter = 'all'"
                                    :class="periodFilter === 'all' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-600 hover:text-gray-900 font-semibold'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all cursor-pointer whitespace-nowrap">
                                Semua
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
                                </select>
                                <span class="material-symbols-rounded absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[18px]">expand_more</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Metode Bayar</label>
                            <div class="relative">
                                <select x-model="selectedMethodFilter" class="appearance-none pl-3.5 pr-9 py-2 border border-gray-200 rounded-lg text-xs font-bold text-gray-800 w-[180px] outline-none bg-white cursor-pointer focus:border-[#0052cc]">
                                    <option value="all">Semua Metode</option>
                                    <option value="TUNAI">Tunai</option>
                                    <option value="TRANSFER">Transfer Bank</option>
                                    <option value="QRIS">QRIS</option>
                                    <option value="KREDIT">Piutang / Kredit</option>
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
                    <div class="overflow-x-auto min-w-0 w-full">
                        <table class="w-full text-left border-collapse text-xs min-w-[1250px]">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                                    <th class="px-5 py-3.5 text-left w-[140px]">No. Transaksi</th>
                                    <th class="px-5 py-3.5 text-left w-[120px]">ID Sesi</th>
                                    <th class="px-5 py-3.5 text-left w-[160px]">Petugas Kasir</th>
                                    <th class="px-5 py-3.5 text-left w-[140px]">Waktu</th>
                                    <th class="px-5 py-3.5 text-left w-[140px]">Metode Bayar</th>
                                    <th class="px-5 py-3.5 text-right w-[130px]">Subtotal</th>
                                    <th class="px-5 py-3.5 text-right w-[110px]">PPN (11%)</th>
                                    <th class="px-5 py-3.5 text-right w-[140px]">Total Transaksi</th>
                                    <th class="px-5 py-3.5 text-center w-[110px]">Status</th>
                                    <th class="px-5 py-3.5 text-center w-[100px]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                                <template x-for="trx in filteredTransactions" :key="trx.id">
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-5 py-3.5 font-mono font-bold text-[#0052cc] whitespace-nowrap" x-text="trx.id"></td>
                                        <td class="px-5 py-3.5 font-mono text-gray-600 whitespace-nowrap" x-text="trx.sessionId"></td>
                                        <td class="px-5 py-3.5 font-bold text-gray-900 whitespace-nowrap" x-text="trx.cashier"></td>
                                        <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap" x-text="trx.time"></td>
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                                  :class="{
                                                      'bg-emerald-50 text-emerald-700 border border-emerald-200': trx.method === 'TUNAI',
                                                      'bg-blue-50 text-[#0052cc] border border-blue-200': trx.method === 'QRIS',
                                                      'bg-orange-50 text-orange-700 border border-orange-200': trx.method === 'KREDIT ANGGOTA',
                                                      'bg-purple-50 text-purple-700 border border-purple-200': trx.method === 'CAMPURAN'
                                                  }"
                                                  x-text="trx.method"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right font-mono text-gray-600 whitespace-nowrap" x-text="formatRupiah(trx.subtotal || Math.round(trx.total / 1.11))"></td>
                                        <td class="px-5 py-3.5 text-right font-mono text-gray-500 whitespace-nowrap" x-text="formatRupiah(trx.tax || Math.round(trx.total - (trx.total / 1.11)))"></td>
                                        <td class="px-5 py-3.5 text-right font-mono font-black text-gray-900 whitespace-nowrap" x-text="formatRupiah(trx.total)"></td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase"
                                                  :class="trx.status === 'SELESAI' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'"
                                                  x-text="trx.status"></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center justify-center gap-1.5">
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
                                        <p class="font-bold text-gray-600">Belum ada transaksi untuk ditampilkan.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
