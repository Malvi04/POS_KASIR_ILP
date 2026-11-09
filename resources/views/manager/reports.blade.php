@extends('layouts.app')

@section('title', 'Laporan Penjualan - ILP POS')

@section('content')
<div x-data="{
    transactions: [],
    searchQuery: '',
    selectedPeriod: 'Bulan Ini',

    init() {
        try {
            const raw = localStorage.getItem('pos_ilp_transactions_v1');
            if (raw) {
                this.transactions = JSON.parse(raw);
            }
        } catch(e) {
            this.transactions = [];
        }
    },

    get totalRevenue() {
        return this.transactions.reduce((sum, t) => sum + (Number(t.total) || 0), 0);
    },

    get netProfit() {
        return Math.round(this.totalRevenue * 0.18);
    },

    get avgTransaction() {
        if (!this.transactions.length) return 0;
        return Math.round(this.totalRevenue / this.transactions.length);
    },

    get totalItemsSold() {
        return this.transactions.reduce((sum, t) => {
            if (Array.isArray(t.items)) {
                return sum + t.items.reduce((s, item) => s + (Number(item.qty) || Number(item.quantity) || 1), 0);
            }
            return sum + 1;
        }, 0);
    },

    formatRp(num) {
        return 'Rp ' + (Number(num) || 0).toLocaleString('id-ID');
    }
}" class="flex h-screen w-full bg-[#f8f9fa] font-sans text-gray-800 overflow-hidden">
    
    <!-- Sidebar -->
    <x-manager-sidebar active="reports" />

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        
        <!-- Top Header -->
        <header class="h-[68px] bg-white border-b border-gray-200 flex items-center justify-between px-8 shrink-0">
            <div class="relative w-[360px]">
                <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[18px]">search</span>
                <input type="text" x-model="searchQuery" placeholder="Cari laporan..." class="w-full pl-11 pr-4 py-2 rounded-full bg-[#f1f5f9] border border-transparent focus:bg-white focus:border-[#0052cc] focus:ring-2 focus:ring-[#0052cc]/15 focus:outline-none text-[13px] text-gray-700 placeholder-gray-400 transition-all">
            </div>

            <div class="flex items-center gap-4 text-gray-500">
                <button class="hover:text-gray-900 transition-colors p-1">
                    <span class="material-symbols-rounded text-[22px]">notifications</span>
                </button>
                <button class="hover:text-gray-900 transition-colors p-1">
                    <span class="material-symbols-rounded text-[22px]">help_outline</span>
                </button>
                <div class="h-6 w-px bg-gray-200 ml-1"></div>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <div class="flex-1 overflow-y-auto p-8 pb-6">
            <div class="max-w-[1400px] mx-auto space-y-6">
                
                <!-- Subheader: Title & Period Filter -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-[19px] font-bold text-gray-900 tracking-tight">Laporan Penjualan</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="bg-white hover:bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-2.5 shadow-sm transition-colors text-[13px] font-semibold text-gray-700 shrink-0">
                            <span class="material-symbols-rounded text-[18px] text-gray-500">calendar_today</span>
                            <span x-text="selectedPeriod"></span>
                        </button>
                    </div>
                </div>

                <!-- 4 Summary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Card 1: TOTAL PENDAPATAN -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#dbeafe] text-[#0052cc] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">payments</span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">TOTAL PENDAPATAN</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight" x-text="formatRp(totalRevenue)">Rp 0</h3>
                        </div>
                    </div>

                    <!-- Card 2: LABA BERSIH -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#ffedd5] text-[#c2410c] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">savings</span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase">LABA BERSIH</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight" x-text="formatRp(netProfit)">Rp 0</h3>
                        </div>
                    </div>

                    <!-- Card 3: RATA-RATA TRANSAKSI -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#e0e7ff] text-[#3730a3] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">shopping_basket</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase leading-snug">RATA-RATA TRANSAKSI</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight" x-text="formatRp(avgTransaction)">Rp 0</h3>
                        </div>
                    </div>

                    <!-- Card 4: TOTAL PRODUK TERJUAL -->
                    <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm flex flex-col justify-between min-h-[130px]">
                        <div class="flex items-center justify-between">
                            <div class="w-9 h-9 rounded-lg bg-[#fef3c7] text-[#d97706] flex items-center justify-center">
                                <span class="material-symbols-rounded text-[20px]">inventory</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <p class="text-[11px] font-bold text-gray-500 tracking-wider uppercase leading-snug">TOTAL PRODUK TERJUAL</p>
                            <h3 class="text-[19px] font-bold text-gray-900 font-mono mt-1 tracking-tight" x-text="totalItemsSold + ' Unit'">0 Unit</h3>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area: Charts / Tables -->
                <div class="grid grid-cols-12 gap-6 items-stretch">
                    
                    <!-- Left: Tren Penjualan Harian -->
                    <div class="col-span-12 lg:col-span-8 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-[15px] font-bold text-gray-900">Tren Penjualan Harian</h2>
                        </div>

                        <!-- Empty state if no transactions -->
                        <div x-show="transactions.length === 0" class="py-16 text-center text-gray-400 font-medium">
                            <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">bar_chart</span>
                            <p>Belum ada data untuk ditampilkan.</p>
                        </div>

                        <!-- Content if transactions exist -->
                        <div x-show="transactions.length > 0" class="relative flex-1 flex flex-col justify-end min-h-[220px] pt-4">
                            <div class="text-sm text-gray-500 text-center py-8">
                                Menampilkan tren dari <span class="font-bold text-gray-800" x-text="transactions.length"></span> transaksi.
                            </div>
                        </div>
                    </div>

                    <!-- Right: Analisis Pergerakan Stok -->
                    <div class="col-span-12 lg:col-span-4 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-[15px] font-bold text-gray-900">Pergerakan Stok Terlaris</h2>
                        </div>

                        <div class="py-16 text-center text-gray-400 font-medium">
                            <span class="material-symbols-rounded text-4xl mb-2 text-gray-300">inventory_2</span>
                            <p>Belum ada data untuk ditampilkan.</p>
                        </div>
                    </div>

                </div>

                <!-- Bottom Row: Tables -->
                <div class="grid grid-cols-12 gap-6 items-start">
                    
                    <!-- Table 1: Daftar Transaksi Terkini -->
                    <div class="col-span-12 lg:col-span-7 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-[15px] font-bold text-gray-900">Transaksi Terkini</h2>
                            <span class="text-xs text-gray-500 font-medium" x-text="transactions.length + ' Transaksi'"></span>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[550px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 text-[11px] font-bold uppercase tracking-wider">
                                        <th class="py-3 px-6">ID Transaksi</th>
                                        <th class="py-3 px-4">Kasir / Sesi</th>
                                        <th class="py-3 px-4 text-right">Total</th>
                                        <th class="py-3 px-6 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-[13px]">
                                    <template x-for="t in transactions.slice(0, 10)" :key="t.id || t.invoice">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-3.5 px-6 font-mono font-bold text-gray-900" x-text="t.invoice || t.id"></td>
                                            <td class="py-3.5 px-4 text-gray-600" x-text="t.cashier || 'Kasir'"></td>
                                            <td class="py-3.5 px-4 text-right font-mono font-bold text-gray-900" x-text="formatRp(t.total)"></td>
                                            <td class="py-3.5 px-6 text-center">
                                                <span class="inline-block bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded">Sukses</span>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="transactions.length === 0">
                                        <td colspan="4" class="py-12 text-center text-gray-400 font-medium">
                                            Belum ada data untuk ditampilkan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Table 2: Performa Kasir -->
                    <div class="col-span-12 lg:col-span-5 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-[15px] font-bold text-gray-900">Performa Kasir</h2>
                        </div>

                        <div class="overflow-x-auto min-w-0 w-full">
                            <table class="w-full text-left border-collapse min-w-[400px]">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 text-[11px] font-bold uppercase tracking-wider">
                                        <th class="py-3 px-6">Kasir</th>
                                        <th class="py-3 px-4 text-center">Trx</th>
                                        <th class="py-3 px-6 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-[13px]">
                                    <tr x-show="transactions.length === 0">
                                        <td colspan="3" class="py-12 text-center text-gray-400 font-medium">
                                            Belum ada data untuk ditampilkan.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Bottom Action Bar -->
        <footer class="bg-white border-t border-gray-200 px-8 py-4 flex items-center justify-between shrink-0">
            <span class="text-[12.5px] font-medium text-gray-500">
                Laporan Penjualan Konsolidasian
            </span>
            <div class="flex items-center gap-3">
                <button type="button" @click="window.print()" class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-[12.5px] font-bold text-gray-700 flex items-center gap-2 shadow-sm transition-colors cursor-pointer">
                    <span class="material-symbols-rounded text-[17px]">print</span>
                    <span>Cetak Laporan</span>
                </button>
            </div>
        </footer>

    </main>
</div>
@endsection
